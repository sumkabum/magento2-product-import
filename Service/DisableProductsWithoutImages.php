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

    public function __construct(
        StoreManagerInterface $storeManager,
        State $state,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Model\Product\Gallery\ReadHandler $galleryReaderHandler,
        DirectoryList $directoryList,
        ObjectManagerInterface $objectManager,
        \Magento\Catalog\Model\Product\Action $productAction,
        \Magento\Framework\Registry $registry
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
    }

    /**
     * @throws FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(bool $deleteInsteadDisable = false, ?OutputInterface $output = null, ?Report $report = null, ?LoggerInterface $logger = null, ?string $filterField = null, ?string $filterValue = null)
    {
        $limit = 500;
        $currentPage = 1;

        if ($deleteInsteadDisable) {
            $this->registry->register('isSecureArea', true);
        }
        $products = $this->getProducts($limit, $currentPage, $filterField, $filterValue);

        if ($output) {
            $progressBar = new ProgressBar($output, $products->getTotalCount());
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();
        }

        $productSkusToDelete = [];
        $productSkusToDisable = [];

        while (count($products->getItems()) > 0) {

            foreach ($products->getItems() as $product) {
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
        }

        $totalProductsCountToHandle = count($productSkusToDelete) + count($productSkusToDisable);
        if ($totalProductsCountToHandle == 0) {
            $output->writeln('No products to handle');
            return;
        }

        if ($output) {
            $progressBar = new ProgressBar($output, count($productSkusToDelete) + count($productSkusToDisable));
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();
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

    public function getProducts(int $limit, int $currentPage = 1, ?string $filterField = null, ?string $filterValue = null): \Magento\Catalog\Api\Data\ProductSearchResultsInterface
    {
        $this->setAreaCode();

        $searchCriteriaBuilder = $this->searchCriteriaBuilder
            ->setCurrentPage($currentPage)
            ->setPageSize($limit)
        ;

        if (!empty($filterField) && !empty($filterValue)) {
            $searchCriteriaBuilder->addFilter($filterField, $filterValue);
        }

        $searchCriteria = $searchCriteriaBuilder->create();

        $productList = $this->productRepository->getList($searchCriteria);

        // fix magento last $currentPage bug
        if ((($currentPage-1) * $limit) > $productList->getTotalCount()) {
            return $this->objectManager->create(\Magento\Catalog\Api\Data\ProductSearchResultsInterface::class);
        }

        return $productList;
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
