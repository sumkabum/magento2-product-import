<?php
namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Exception;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Sumkabum\Magento2ProductImport\Service\Report;
use Sumkabum\Magento2ProductImport\Service\StoreBasedAttributeValues;
use Sumkabum\Magento2ProductImport\Service\UpdateFieldInterface;

class Product
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
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
     * @var ProductAttribute
     */
    private $productAttributeService;
    /**
     * @var UrlKey
     */
    private $urlKeyService;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
    private $indexerCollectionFactory;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Sumkabum\Magento2ProductImport\Service\Report
     */
    private $report;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        State $state,
        ProductAttribute $productAttributeService,
        UrlKey $urlKeyService,
        LoggerInterface $logger,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->productRepository = $productRepository;
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->state = $state;
        $this->productAttributeService = $productAttributeService;
        $this->urlKeyService = $urlKeyService;
        $this->logger = $logger;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     * @return Product
     */
    public function setLogger(LoggerInterface $logger): Product
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return \Sumkabum\Magento2ProductImport\Service\Report
     */
    public function getReport(): \Sumkabum\Magento2ProductImport\Service\Report
    {
        if (!$this->report) {
            $this->report = $this->objectManager->get(Report::class);
        }
        return $this->report;
    }

    /**
     * @param \Sumkabum\Magento2ProductImport\Service\Report $report
     * @return Product
     */
    public function setReport(\Sumkabum\Magento2ProductImport\Service\Report $report): Product
    {
        $this->report = $report;
        return $this;
    }

    /**
     * @param array $productData
     * @param array $doNotUpdateFields
     * @param StoreBasedAttributeValues[] $storeBasedAttributeValuesArray
     * @return ProductInterface|mixed
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    public function save(array $productData, array $doNotUpdateFields = [], array $storeBasedAttributeValuesArray = []): ProductInterface
    {
        $this->setAreaCode();
        $product = $this->getProduct($productData['sku']);

        foreach ($productData as $fieldName => $fieldValue) {
            if ($fieldValue instanceof UpdateFieldInterface) {
                $productData[$fieldName] = $fieldValue->getNewValue($product);
            }
        }

        if (isset($productData['url_key'])) {
            $productData['url_key'] = $this->urlKeyService->generateUrlKey($productData['url_key']);
        }

        if ($product->getTypeId() == Configurable::TYPE_CODE && empty($productData['extension_attributes'])) {
            /** @var \Magento\Catalog\Api\Data\ProductExtension $extensionAttributes */
            $extensionAttributes = ObjectManager::getInstance()->create(\Magento\Catalog\Api\Data\ProductExtension::class);
            $extensionAttributes
                ->setConfigurableProductLinks([])
                ->setConfigurableProductOptions([]);

            $product->setData('extension_attributes', $extensionAttributes);
        }

        if (!$this->isNewProduct($product)) {
            foreach ($doNotUpdateFields as $doNotUpdateField) {
                unset($productData[$doNotUpdateField]);
            }
        }

        foreach ($productData as $productFieldName => $productFieldValue) {
            if ($productFieldValue instanceof UpdateFieldInterface) {
                $productData[$productFieldName] = $productFieldValue->getNewValue($product);
            }
        }

        $productData = array_merge($product->getData(), $productData);

        $product->setData($productData);

        $this->urlKeyService->checkForDuplicateUrlKey($product);


        $product = $this->productRepository->save($product);
        $this->logger->info($product->getSku() . ' ' . ($this->isNewProduct($product) ? 'created' : 'updated'));

        if (isset($productData['category_ids'])) {
            $this->assignProductToCategories($product->getSku(), $productData['category_ids']);
        }

        if (count($storeBasedAttributeValuesArray) > 0) {
            foreach ($storeBasedAttributeValuesArray as $storeBasedAttributeValues) {
                $this->updateStoreBasedAttributeValue(
                    [$product->getId()],
                    $storeBasedAttributeValues->mappedDataFields,
                    $storeBasedAttributeValues->storeId
                );
            }
        }

        return $product;
    }

    public function updateStoreBasedAttributeValue(array $productIds, $productData, $storeId)
    {
        /** @var ProductAction $productAction */
        $productAction = $this->objectManager->get(ProductAction::class);
        $productAction->updateAttributes($productIds, $productData, $storeId);
    }

    /**
     * @param ProductInterface $configurableProduct
     * @param \Magento\Catalog\Model\Product[] $simpleProducts
     * @param array $linkableAttributeCodes
     * @return ProductInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function associateConfigurableWithSimpleProducts(ProductInterface $configurableProduct, array $simpleProducts, array $linkableAttributeCodes): ProductInterface
    {
        // remove linkable attribute codes that are not filled in simple products data
        foreach ($linkableAttributeCodes as $key => $linkableAttributeCode) {
            $haveValue = false;
            foreach ($simpleProducts as $simpleProduct) {
                if (!empty($simpleProduct->getData($linkableAttributeCode))) {
                    $haveValue = true;
                    break;
                }
            }
            if (!$haveValue) {
                unset($linkableAttributeCodes[$key]);
            }
        }

        // Remove product that have same linkable attribute value
        $existingValues = [];
        foreach ($simpleProducts as $simpleProductKey => $simpleProduct) {
            $existingValue = '';
            foreach ($linkableAttributeCodes as $key => $linkableAttributeCode) {
                $existingValue .= $linkableAttributeCode . $simpleProduct->getData($linkableAttributeCode);
            }
            if (isset($existingValues[$existingValue])) {
                unset($simpleProducts[$simpleProductKey]);
                $message = $configurableProduct->getSku() . ' not associating with ' . $simpleProduct->getSku() . ' because having same set of attribute values than other simple product';
                $this->logger->alert($message);
                $this->getReport()->increaseByNumber('Child products have same configurable attribute values');
            }
            $existingValues[$existingValue] = $existingValue;
        }

        $attributeValues = [];
        foreach ($linkableAttributeCodes as $linkableAttributeCode) {
            $attributeValues[$linkableAttributeCode] = [];
        }
        $associatedProductIds = [];

        foreach ($simpleProducts as $simpleProduct) {
            $associatedProductIds[] = $simpleProduct->getEntityId();

            foreach ($linkableAttributeCodes as $linkableAttributeCode) {
                $linkableAttribute = $this->productAttributeService->getAttribute($linkableAttributeCode);
                $option = $this->productAttributeService->getOptionByIdAndAttributeCode($linkableAttributeCode, $simpleProduct->getData($linkableAttributeCode));

                $attributeValues[$linkableAttributeCode][$option->getValue()] = [
                    'label' => $linkableAttribute->getStoreLabel(),
                    'attribute_id' => $linkableAttribute->getAttributeId(),
                    'value_index' => $option->getValue()
                ];
            }

        }

        $configurableAttributesData = [];

        foreach ($linkableAttributeCodes as $linkableAttributeCode) {
            $linkableAttribute = $this->productAttributeService->getAttribute($linkableAttributeCode);
            $configurableAttributesData[] = [
                'attribute_id' => $linkableAttribute->getId(),
                'code' => $linkableAttribute->getAttributeCode(),
                'label' => $linkableAttribute->getStoreLabel(),
                'position' => '0',
                'values' => $attributeValues[$linkableAttributeCode],
            ];
        }

        /** @var \Magento\ConfigurableProduct\Helper\Product\Options\Factory $optionsFactory */
        $optionsFactory = ObjectManager::getInstance()->create(\Magento\ConfigurableProduct\Helper\Product\Options\Factory::class);

        $configurableAttributesData = $optionsFactory->create($configurableAttributesData);

        $getExtensionAttributes = $configurableProduct->getExtensionAttributes();

        $getExtensionAttributes->setConfigurableProductOptions($configurableAttributesData);
        $getExtensionAttributes->setConfigurableProductLinks($associatedProductIds);

        $this->logger->info($configurableProduct->getSku() . ' associating with ' . count($simpleProducts) . ' simple products. with attribute codes: ' . implode(', ', $linkableAttributeCodes));

        $configurableProduct->setExtensionAttributes($getExtensionAttributes);
        return $this->productRepository->save($configurableProduct);
    }

    protected function getProduct(string $sku)
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException $e) {
            $product = $this->objectManager->create(ProductInterface::class);
        }
        return $product;
    }

    /**
     * @param string $sku
     * @param array $newCategoryIds
     */
    public function assignProductToCategories(string $sku, array $newCategoryIds)
    {
        /** @var CategoryLinkManagementInterface $categoryLinkManagement */
        $categoryLinkManagement = $this->objectManager->get(CategoryLinkManagementInterface::class);
        $categoryLinkManagement->assignProductToCategories($sku, $newCategoryIds);
    }

    public function isNewProduct(ProductInterface $product): bool
    {
        return $product->getCreatedAt() == $product->getUpdatedAt();
    }

    public function setAreaCode()
    {
        try {
            $this->storeManager->setCurrentStore(0);
            $this->state->setAreaCode(Area::AREA_GLOBAL);
        } catch (Exception $e) { }
    }

    /**
     * @param string $sourceCode
     * @param array $skuList
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    public function disableProductsThatAreNotInList(string $sourceCode, array $skuList, $sourceCodeFieldName = 'source_code')
    {
        $limit = 100;
        $currentPage = 1;

        $importedProducts = $this->getProductsBySourceCode($sourceCode, $limit, $currentPage, $sourceCodeFieldName);
        while (count($importedProducts->getItems()) > 0) {
            $this->logger->info('Checking old products. Progress: ' . $limit * $currentPage);
            foreach ($importedProducts->getItems() as $product) {
                if (!in_array($product->getSku(), $skuList)) {
                    if ($product->getStatus() == Status::STATUS_ENABLED) {
                        $this->disableProduct($product);
                        $this->report->increaseByNumber($this->report::KEY_STATUS_CHANGED_TO_DISABLED);
                        $this->logger->info($product->getSku() . ' Not exists in feed. Updated status to disabled');
                    } else {
                        $this->logger->info($product->getSku() . ' Not exists in feed. Already disabled');
                    }
                }
            }
            $currentPage++;
            $importedProducts = $this->getProductsBySourceCode($sourceCode, $limit, $currentPage, $sourceCodeFieldName);
        }
    }

    public function getProductsBySourceCode(string $sourceCode, int $limit, int $currentPage = 1, $sourceCodeFieldName = 'source_code'): \Magento\Catalog\Api\Data\ProductSearchResultsInterface
    {
        $this->setAreaCode();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter($sourceCodeFieldName, $sourceCode)
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

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    public function disableProduct(ProductInterface $product)
    {
        $product->setStatus(Status::STATUS_DISABLED);
        $this->productRepository->save($product);
    }

    public function markIndexesAsInvalid()
    {
        $indexers = $this->indexerCollectionFactory->create()->getItems();
        foreach ($indexers as $indexer) {
            $indexer->invalidate();
        }
    }

    public function isNewJustCreatedProduct(\Magento\Catalog\Model\Product $product): bool
    {
        return $product->getUpdatedAt() == $product->getCreatedAt();
    }
}
