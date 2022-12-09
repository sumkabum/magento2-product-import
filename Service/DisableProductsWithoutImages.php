<?php

namespace Sumkabum\Magento2ProductImport\Service;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\Acl\Role\Registry;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Sumkabum\Magento2ProductImport\Repository\SumkabumData;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class DisableProductsWithoutImages
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var State
     */
    private $state;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    private $areaCodeIsSet = false;
    /**
     * @var \Magento\Catalog\Model\Product\Gallery\ReadHandler
     */
    private $galleryReaderHandler;
    /**
     * @var DirectoryList
     */
    private $directoryList;
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;
    /**
     * @var ProductAction
     */
    private $productAction;
    /**
     * @var Registry
     */
    private $registry;
    /**
     * @var SumkabumData
     */
    private $sumkabumData;

    public function __construct(
        StoreManagerInterface $storeManager,
        State $state,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Model\Product\Gallery\ReadHandler $galleryReaderHandler,
        DirectoryList $directoryList,
        ObjectManagerInterface $objectManager,
        \Magento\Catalog\Model\Product\Action $productAction,
        \Magento\Framework\Registry $registry,
        SumkabumData $sumkabumData
    ) {
        $this->storeManager = $storeManager;
        $this->state = $state;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productRepository = $productRepository;
        $this->galleryReaderHandler = $galleryReaderHandler;
        $this->directoryList = $directoryList;
        $this->objectManager = $objectManager;
        $this->productAction = $productAction;
        $this->registry = $registry;
        $this->sumkabumData = $sumkabumData;
    }

    /**
     * @throws FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(bool $deleteInsteadDisable = false, ?OutputInterface $output = null, ?Report $report = null, ?LoggerInterface $logger = null, ?string $filterField = null, ?string $filterValue = null, $updateProgress = false)
    {
        $limit = 500;
        $currentPage = 1;

        if ($deleteInsteadDisable) {
            $this->registry->register('isSecureArea', true);
        }
        $products = $this->getProducts($limit, $currentPage, $filterField, $filterValue);

        if ($output) {
            $progressBar = new ProgressBar($output, $products->getSize());
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();
        }
        if ($logger) {
            $logger->info('products to scan: ' . $products->getSize());
        }

        $productSkusToDelete = [];
        $productSkusToDisable = [];

        if ($updateProgress) {
            $this->sumkabumData->set(ImporterStatus::DATA_KEY_IMPORTER_CURRENT_JOB, 'Collection products without images');
            $progressTotal = $products->getSize();
            $progressCurrent = 0;
        }

        while ($products) {

            foreach ($products->getItems() as $product) {

                if ($updateProgress) {
                    $this->sumkabumData->set(ImporterStatus::DATA_KEY_IMPORTER_PROGRESS, (++$progressCurrent * 100 / $progressTotal));
                }

                /** @var Product $product */
                $this->galleryReaderHandler->execute($product);
                $existingImages = $product->getMediaGalleryEntries();

                foreach ($existingImages as $existingImageKey => $existingImage) {
                    if (!file_exists($this->getCatalogProductImageFullPath($existingImage->getFile()))) {
                        unset($existingImages[$existingImageKey]);
                    }
                }

                if (count($existingImages) <= 0) {
                    if (!$deleteInsteadDisable && $product->getStatus() == Product\Attribute\Source\Status::STATUS_DISABLED) {
                        continue;
                    }
                    if ($deleteInsteadDisable) {
                        $productSkusToDelete[] = $product->getSku();
                    } else {
                        $productSkusToDisable[] = $product->getSku();
                    }
                }

                if ($output) $progressBar->advance();
            }

            $products = $this->getProducts($limit, ++$currentPage, $filterField, $filterValue);
        }

        if ($output) {
            $progressBar->finish();
            $output->writeln('');
            $output->writeln('count delete: ' . count($productSkusToDelete) . ' disable: ' . count($productSkusToDisable));
        }
        if ($logger) {
            $logger->info('count delete: ' . count($productSkusToDelete) . ' disable: ' . count($productSkusToDisable));
        }

        $totalProductsCountToHandle = count($productSkusToDelete) + count($productSkusToDisable);
        if ($output && $totalProductsCountToHandle == 0) {
            $output->writeln('No products to handle');
            return;
        }

        if ($output) {
            $progressBar = new ProgressBar($output, count($productSkusToDelete) + count($productSkusToDisable));
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();
        }

        if ($updateProgress) {
            $this->sumkabumData->set(ImporterStatus::DATA_KEY_IMPORTER_CURRENT_JOB, 'Handling products without images');
            $progressCurrent = 0;
        }

        foreach ($productSkusToDelete as $sku)
        {
            try {
                $this->deleteProductBySKu($sku);
                $message = $sku . ' Product deleted because having no images';
                $this->logMessage($logger, $report, $message);
            } catch (\Throwable $t) {
                $this->logMessage($logger, $report, $sku . ' ' . $t->getMessage());
            }
            if ($output) $progressBar->advance();
            if ($updateProgress) {
                $this->sumkabumData->set(ImporterStatus::DATA_KEY_IMPORTER_PROGRESS, (++$progressCurrent * 100 / $totalProductsCountToHandle));
            }
        }

        foreach ($productSkusToDisable as $sku)
        {
            try {
                $this->disableProductBySKu($sku);
                $message = $sku . ' Product disabled because having no images';
                $this->logMessage($logger, $report, $message);
            } catch (\Throwable $t) {
                $this->logMessage($logger, $report, $sku . ' ' . $t->getMessage());
            }
            if ($output) $progressBar->advance();
            if ($updateProgress) {
                $this->sumkabumData->set(ImporterStatus::DATA_KEY_IMPORTER_PROGRESS, (++$progressCurrent * 100 / $totalProductsCountToHandle));
            }
        }
        if ($output) {
            $progressBar->finish();
            $output->writeln('');
        }
    }

    private function logMessage($logger, $report, $message)
    {
        if ($logger) {
            $logger->info($message);
        }
        if ($report) {
            $report->addMessage('Warning', $message);
        }
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function disableProductBySKu(string $sku)
    {
        $product = $this->productRepository->get($sku);
        $this->productAction->updateAttributes([$product->getEntityId()], ['status' => Product\Attribute\Source\Status::STATUS_DISABLED], 0);
    }

    /**
     * @param string $sku
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    private function deleteProductBySKu(string $sku)
    {
        $this->productRepository->deleteById($sku);
    }

    public function getProducts(int $limit, int $currentPage = 1, ?string $filterField = null, ?string $filterValue = null): ?\Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $collection = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);

        if (!empty($filterField) && !empty($filterValue)) {
            $collection->addAttributeToFilter($filterField, ['eq' => $filterValue]);
        }

        $collection->setPage($currentPage, $limit);

        // fix magento last $currentPage bug
        if ($currentPage > $collection->getLastPageNumber()) {
            return null;
        }

        return $collection;
    }

    public function setAreaCode(): void
    {
        if (!$this->areaCodeIsSet) {
            $this->storeManager->setCurrentStore(0);
            try {
                $this->areaCodeIsSet = true;
                $this->state->setAreaCode('adminhtml');
            } catch (Exception $e) {
            }
        }
    }

    /**
     * @param $productImageFilename
     * @return string
     * @throws FileSystemException
     */
    public function getCatalogProductImageFullPath($productImageFilename): string
    {
        $existingImageFullPath = $this->directoryList->getPath(DirectoryList::MEDIA) . '/catalog/product/' . $productImageFilename;
        return str_replace('//', '/', $existingImageFullPath);
    }
}
