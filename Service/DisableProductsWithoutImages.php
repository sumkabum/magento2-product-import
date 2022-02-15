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
     * @var LoggerInterface
     */
    private $logger;
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
        LoggerInterface $logger,
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
        $this->logger = $logger;
        $this->productAction = $productAction;
        $this->registry = $registry;
    }

    /**
     * @throws FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(bool $deleteInsteadDisable = false, ?OutputInterface $output = null)
    {
        $limit = 500;
        $currentPage = 1;

        if ($deleteInsteadDisable) {
            $this->registry->register('isSecureArea', true);
        }
        $products = $this->getProducts($limit, $currentPage);

        if ($output) {
            $progressBar = new ProgressBar($output, $products->getTotalCount());
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();
        }

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
                        $this->deleteProduct($product);
                    } else {
                        $this->disableProduct($product);
                    }
                    $this->logger->info($product->getSku() . ' Product ' . ($deleteInsteadDisable ? 'deleted' : 'disabled') . ' because having no images');
                }

                if ($output) $progressBar->advance();
            }

            $products = $this->getProducts($limit, ++$currentPage);
        }

        if ($output) {
            $progressBar->finish();
            $output->writeln('');
        }
    }

    private function disableProduct(Product $product)
    {
        $this->productAction->updateAttributes([$product->getEntityId()], ['status' => Product\Attribute\Source\Status::STATUS_DISABLED], 0);
    }

    /**
     * @throws \Magento\Framework\Exception\StateException
     */
    private function deleteProduct(Product $product)
    {
        $this->productRepository->delete($product);
    }

    public function getProducts(int $limit, int $currentPage = 1): \Magento\Catalog\Api\Data\ProductSearchResultsInterface
    {
        $this->setAreaCode();

        $searchCriteria = $this->searchCriteriaBuilder
            ->setCurrentPage($currentPage)
            ->setPageSize($limit)
            ->create();

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
