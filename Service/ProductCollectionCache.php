<?php

namespace Sumkabum\Magento2ProductImport\Service;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Data\Collection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductCollectionCache
{
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var State
     */
    private $state;
    /**
     * @var Memory
     */
    private $memory;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        State $state,
        Memory $memory,
        Logger $logger
    ) {
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->state = $state;
        $this->memory = $memory;
    }

    private $cachedProducts = [];

    public function getProductData(string $sku, array $attributeCodes = ['sku', 'entity_id']): ?array
    {
        $cacheKey = implode('-', $attributeCodes);
        if (empty($cacheKey)) {
            $cacheKey = '-';
        }
        if (!array_key_exists($cacheKey, $this->cachedProducts)) {
            $limit = 500;
            $currentPage = 1;
            $this->cachedProducts[$cacheKey] = [];
            $productCollection = $this->getProductCollection($limit, $currentPage, $attributeCodes);
            while ($productCollection) {
                $products = $productCollection->getItems();

                foreach ($products as $product) {
                    $attributeValues = [
                        'sku' => $product->getData('sku')
                    ];
                    foreach ($attributeCodes as $attributeCode) {
                        $attributeValues[$attributeCode] = $product->getData($attributeCode);
                    }
                    $this->cachedProducts[$cacheKey][$product->getSku()] = $attributeValues;
                }

                $productCollection = $this->getProductCollection($limit, ++$currentPage, $attributeCodes);
            }
        }
        return $this->cachedProducts[$cacheKey][$sku] ?? null;
    }

    public function clearCache()
    {
        $this->cachedProducts = [];
    }

    private function getProductCollection(int $limit, int $currentPage = 1, array $attributesToSelect = []): ?\Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $collection = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);

        $collection->setPage($currentPage, $limit);

        $collection->addAttributeToSelect($attributesToSelect);

        if (in_array('use_config_manage_stock', $attributesToSelect)
            || in_array('manage_stock', $attributesToSelect)
            || in_array('is_in_stock', $attributesToSelect)
            || in_array('qty', $attributesToSelect)
        ) {
            $collection->joinTable(
                'cataloginventory_stock_item',
                'product_id = entity_id',
                ['use_config_manage_stock', 'manage_stock', 'is_in_stock', 'qty']
            );
        }

        if (in_array('category_ids', $attributesToSelect)) {
            $collection->load();
            $collection->addCategoryIds();
        }

        // fix magento last $currentPage bug
        if ($currentPage > $collection->getLastPageNumber()) {
            return null;
        }

        return $collection;
    }
}
