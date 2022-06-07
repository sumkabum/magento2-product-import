<?php
namespace Sumkabum\Magento2ProductImport\Service;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductLinkRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\ObjectManagerInterface;
use Sumkabum\Magento2ProductImport\Service\Magento\ProductImage;
use Psr\Log\LoggerInterface;
use Throwable;

class Importer
{
    /**
     * @var Magento\Product
     */
    private $productService;
    /**
     * @var ProductImage
     */
    private $productImageService;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Report
     */
    private $report;

    private $objectManager;
    /**
     * @var []DataRow[]
     */
    private $childDataRowsByConfigurableSku = [];
    /**
     * @var ProductCollectionCache
     */
    private $productCollectionCache;

    public function __construct(
        \Sumkabum\Magento2ProductImport\Service\Magento\Product $productService,
        ProductImage $productImageService,
        Logger $logger,
        ObjectManagerInterface $objectManager,
        ProductCollectionCache $productCollectionCache
    ) {

        $this->productService = $productService;
        $this->productImageService = $productImageService;
        $this->logger = $logger;
        $this->objectManager = $objectManager;
        $this->productCollectionCache = $productCollectionCache;
    }

    /**
     * @param LoggerInterface $logger
     * @return Importer
     */
    public function setLogger(LoggerInterface $logger): Importer
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return Report
     */
    public function getReport(): Report
    {
        if (!$this->report) {
            $this->report = $this->objectManager->get(Report::class);
        }
        return $this->report;
    }

    /**
     * @param Report $report
     * @return Importer
     */
    public function setReport(Report $report): Importer
    {
        $this->report = $report;
        return $this;
    }

    /**
     * @param DataRow[] $dataRows
     * @param array|null $doNotUpdateFields
     * @param array|null $fieldsToCopyFromSimpleToConfigurable
     * @param array|null $linkableAttributeCodes
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function import(array $dataRows, array $doNotUpdateFields = [], array $fieldsToCopyFromSimpleToConfigurable = [], array $linkableAttributeCodes = [])
    {
        $this->productImageService->setLogger($this->logger);
        $this->productImageService->setReport($this->getReport());

        $configurableDataRows = $this->getConfigurableProducts($dataRows, $fieldsToCopyFromSimpleToConfigurable);
        $count = count($configurableDataRows);

        $i = 0;
        foreach ($configurableDataRows as $configurableDataRow) {
            $i++;
            if ($i % 10 == 0) {
                $this->logger->info('progress configurable: ' . $i . ' of ' . $count);
            }

            try {
                $simpleProductDataRows = $this->getSimpleProductsForConfigurable($configurableDataRow->mappedDataFields['sku'], $dataRows);

                $someOfChildrenNeedUpdating = false;
                foreach ($simpleProductDataRows as $simpleProductDataRow) {
                    if ($simpleProductDataRow->needsUpdatingInMagento) {
                        $someOfChildrenNeedUpdating = true;
                        break;
                    }
                }

                if (
                    $this->productCollectionCache->getProductData($configurableDataRow->mappedDataFields['sku'])
                    && $configurableDataRow->needsUpdatingInMagento
                    && $configurableDataRow->overwriteNeedsUpdatingIfIsParentAndChildrenDoesntNeedUpdate
                    && !$someOfChildrenNeedUpdating
                ) {
                    $configurableDataRow->needsUpdatingInMagento = false;
                }

                if (!$configurableDataRow->needsUpdatingInMagento && !$someOfChildrenNeedUpdating) {
                    $this->report->increaseByNumber($this->report::KEY_PRODUCTS_DIDNT_NEED_UPDATING, count($simpleProductDataRows)+1);
                    continue;
                }

                $configurableProduct = $this->saveProduct($configurableDataRow, $doNotUpdateFields);
                $configurableProduct = $this->updateImages($configurableProduct, $configurableDataRow);

                $simpleProducts = [];
                foreach ($simpleProductDataRows as $simpleProductDataRow) {
                    $simpleProduct = $this->saveProduct($simpleProductDataRow, $doNotUpdateFields);

                    $simpleProduct = $this->updateImages($simpleProduct, $simpleProductDataRow);
                    $simpleProducts[] = $simpleProduct;
                }

                if ($configurableDataRow->needsUpdatingInMagento) {
                    $this->productService->associateConfigurableWithSimpleProducts($configurableProduct, $simpleProducts, $linkableAttributeCodes);
                }
            } catch (Throwable $t) {
                $this->logger->error($configurableDataRow->mappedDataFields['sku'] . ' Failed to save! ' . $t->getMessage() . $t->getTraceAsString());
                $this->report->addMessage($this->report::KEY_ERRORS, $configurableDataRow->mappedDataFields['sku'] . ' ' . $t->getMessage());
            }
        }

        $notConfigurableDataRows = $this->getNotConfigurableDataRows($dataRows);
        $count = count($notConfigurableDataRows);

        $i = 0;
        foreach ($notConfigurableDataRows as $notConfigurableDataRow) {
            $i++;
            if ($i % 10 == 0) {
                $this->logger->info('progress non-configurable: ' . $i . ' of ' . $count);
            }

            if (!$notConfigurableDataRow->needsUpdatingInMagento) {
                $this->report->increaseByNumber($this->report::KEY_PRODUCTS_DIDNT_NEED_UPDATING);
                continue;
            }

            try {
                $simpleProduct = $this->saveProduct($notConfigurableDataRow, $doNotUpdateFields);
                $this->updateImages($simpleProduct, $notConfigurableDataRow);
            } catch (Exception $e) {
                $this->logger->error($notConfigurableDataRow->mappedDataFields['sku'] . ' Failed to save! ' . $e->getMessage() . $e->getTraceAsString());
                $this->report->addMessage($this->report::KEY_ERRORS, $notConfigurableDataRow->mappedDataFields['sku'] . ' ' . $e->getMessage());
            }
        }

        $this->removeInvalidProductLinks($dataRows);
        $i = 0;
        foreach ($dataRows as $dataRow) {
            $i++;
            if ($i % 10 == 0) {
                $this->logger->info('progress update product links: ' . $i . ' of ' . $count);
            }
            try {
                $this->updateProductLinks($dataRow);
            } catch (\Exception $e) {
                $message = $dataRow->mappedDataFields['sku'] . ' Error when adding product links: ' . $e->getMessage();
                $this->logger->info($message . $e->getTraceAsString());
                $this->getReport()->addMessage('Warning', $message);
            }
        }
    }

    /**
     * @param DataRow[] $dataRows
     * @return void
     */
    public function removeInvalidProductLinks(array $dataRows)
    {
        $this->productCollectionCache->clearCache();
        foreach ($dataRows as $dataRow) {
            foreach ($dataRow->productLinks as $key => $productLink) {
                if (!$this->productCollectionCache->getProductData($productLink->getLinkSku())) {
                    unset($dataRow->productLinks[$key]);
                    $message = $dataRow->mappedDataFields['sku'] . ' removing sku: ' . $productLink->getLinkSku() . ' from links because product not exists.';
                    $this->logger->info($message);
                    $this->getReport()->addMessage('Warning', $message);
                }
            }
        }
    }

    public function updateProductLinks(DataRow $dataRow)
    {
        if (!$dataRow->needsUpdatingInMagento) return;
        if (!$dataRow->updateProductLinks) return;
        if (!$this->productCollectionCache->getProductData($dataRow->mappedDataFields['sku'])) return;

        $importProductLinks = [];
        foreach ($dataRow->productLinks as $productLink) {
            /** @var  \Magento\Catalog\Api\Data\ProductLinkInterface $magentoProductLink */
            $magentoProductLink = $this->objectManager->create(\Magento\Catalog\Api\Data\ProductLinkInterface::class);
            $magentoProductLink
                ->setSku($dataRow->mappedDataFields['sku'])
                ->setLinkedProductSku($productLink->getLinkSku())
                ->setLinkType($productLink->getType());
            $importProductLinks[] = $magentoProductLink;
        }

        /** @var \Magento\Catalog\Model\Product\Link\SaveHandler $productLinkSaveHandler */
        $productLinkSaveHandler = $this->objectManager->get(\Magento\Catalog\Model\Product\Link\SaveHandler::class);
        /** @var Product $product */
        $product = $this->objectManager->create(ProductInterface::class);
        $product->setSku($dataRow->mappedDataFields['sku']);
        $product->setEntityId($this->productCollectionCache->getProductData($dataRow->mappedDataFields['sku'])['entity_id']);
        $product->setProductLinks($importProductLinks);
        $productLinkSaveHandler->execute(\Magento\Catalog\Api\Data\ProductInterface::class, $product);
    }

    /**
     * @param DataRow $dataRow
     * @param array $doNotUpdateFields
     * @return \Magento\Catalog\Api\Data\ProductInterface|mixed
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function saveProduct(DataRow $dataRow, array $doNotUpdateFields)
    {
        if ($dataRow->needsUpdatingInMagento) {
            $this->report->increaseByNumber($this->report::KEY_PRODUCTS_UPDATED);
            return $this->productService->save($dataRow->mappedDataFields, $doNotUpdateFields, $dataRow->storeBasedAttributeValuesArray);
        } else {
            $this->report->increaseByNumber($this->report::KEY_PRODUCTS_DIDNT_NEED_UPDATING);
            return $this->productService->getProduct($dataRow->mappedDataFields['sku']);
        }
    }

    /**
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\StateException
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateImages(Product $product, DataRow $dataRow): Product
    {
        if ($dataRow->needsUpdatingInMagento
            && ($this->productService->isNewProduct($product) || $dataRow->catUpdateImagesIfProductExists)
        ) {
            $product = $this->productImageService->updateImages($product, $dataRow->images, $dataRow->removeTmpImages);
            $existingImages = $product->getMediaGalleryEntries();
            if ($dataRow->disableProductIfNoImages
                && count($existingImages) <= 0
                && $product->getStatus() == Status::STATUS_ENABLED
            ) {
                $this->productService->disableProduct($product);
                $this->logger->info($product->getSku() . ' Disabling product because having no images');
            }
        }
        return $product;
    }

    /**
     * @param DataRow[] $dataRows
     * @param array $fieldsToCopyFromSimpleToConfigurable
     * @return DataRow[]
     */
    private function getConfigurableProducts(array $dataRows, array $fieldsToCopyFromSimpleToConfigurable): array
    {
        $configurableDataRows = [];

        // add sku as array key
        foreach ($dataRows as $key => $dataRow) {
            $dataRows[$dataRow->mappedDataFields['sku']] = $dataRow;
            if ((string) $dataRow->mappedDataFields['sku'] != (string)$key) {
                unset($dataRows[$key]);
            }
        }

        $configurableSkus = $this->collectConfigurableSkus($dataRows);

        $configurableProductDefaultValues = [
            'type_id' => Configurable::TYPE_CODE,
            'status' => Status::STATUS_ENABLED,
            'stock_data' => [
                'use_config_manage_stock' => 1,
                'is_in_stock' => 1
            ],
        ];

        foreach ($configurableSkus as $configurableSku) {
            // create dataRow
            if (isset($dataRows[$configurableSku])) {
                $configurableDataRow = $dataRows[$configurableSku];
            } else {
                $configurableDataRow = new DataRow();
                $configurableDataRow->mappedDataFields = [
                    'sku' => $configurableSku
                ];
            }

            // set configurable product default values
            foreach ($configurableProductDefaultValues as $configurableProductDefaultKey => $configurableProductDefaultValue) {
                if (!isset($configurableDataRow->mappedDataFields[$configurableProductDefaultKey])) {
                    $configurableDataRow->mappedDataFields[$configurableProductDefaultKey] = $configurableProductDefaultValue;
                }
            }

            // find first child
            $firstChildDataRow = $this->getSimpleProductsForConfigurable($configurableSku, $dataRows)[0] ?? null;
            if ($firstChildDataRow) {
                if (!count($configurableDataRow->images)) {
                    foreach ($firstChildDataRow->images as $dataRowImage) {
                        $configurableDataRow->images[] = $dataRowImage;
                    }
                }

                foreach ($fieldsToCopyFromSimpleToConfigurable as $fieldToCopy) {
                    if (!array_key_exists($fieldToCopy, $firstChildDataRow->mappedDataFields)) {
                        continue;
                    }
                    $configurableDataRow->mappedDataFields[$fieldToCopy] = $firstChildDataRow->mappedDataFields[$fieldToCopy];
                    foreach ($firstChildDataRow->storeBasedAttributeValuesArray as $storeBasedAttributeValues) {
                        foreach ($storeBasedAttributeValues->mappedDataFields as $storeBasedAttributeCode => $storeBasedAttributeValue) {
                            if ($storeBasedAttributeCode != $fieldToCopy) {
                                continue;
                            }
                            $configurableDataRow->addStoreBasedValue($storeBasedAttributeValues->storeId, $storeBasedAttributeCode, $storeBasedAttributeValue);
                        }
                    }
                }

                $configurableDataRow->mappedDataFields['attribute_set_id'] = $firstChildDataRow->mappedDataFields['attribute_set_id'];

                if (empty($configurableDataRow->mappedDataFields['url_key'])) {
                    if (empty($configurableDataRow->mappedDataFields['name']) || empty($configurableDataRow->mappedDataFields['sku'])) {
                        continue;
                    }
                    $configurableDataRow->mappedDataFields['url_key'] = $configurableDataRow->mappedDataFields['name'] . '-' . $configurableDataRow->mappedDataFields['sku'];
                }
            }

            foreach ($configurableDataRow->storeBasedAttributeValuesArray as $storeBasedAttributeValue) {
                if (
                    !empty($storeBasedAttributeValue->mappedDataFields['name']) &&
                    !empty($configurableDataRow->mappedDataFields['sku']) &&
                    !isset($storeBasedAttributeValue->mappedDataFields['url_key'])
                ) {
                    $storeBasedAttributeValue->mappedDataFields['url_key'] = $storeBasedAttributeValue->mappedDataFields['name'] . '-' . $configurableDataRow->mappedDataFields['sku'];
                }
            }

            $configurableDataRows[$configurableDataRow->mappedDataFields['sku']] = $configurableDataRow;
        }

        return $configurableDataRows;
    }

    /**
     * @param DataRow[] $dataRows
     */
    private function collectConfigurableSkus(array $dataRows): array
    {
        // collect configurable skus
        $configurableSkus = [];
        foreach ($dataRows as $dataRow) {
            if (empty($dataRow->parentSku)) {
                continue;
            }
            $configurableSkus[$dataRow->parentSku] = $dataRow->parentSku;
        }
        return $configurableSkus;
    }

    public function convertChildrenToSimpleIfLinkableAttributesEmpty(array $dataRows, array $linkableAttributeCodes): array
    {
        $configurableSkus = $this->collectConfigurableSkus($dataRows);
        foreach ($configurableSkus as $configurableSku) {
            $childProducts = $this->getSimpleProductsForConfigurable($configurableSku, $dataRows);
            $configurableSkuHaveValidChildren = false;
            foreach ($childProducts as $childProduct) {
                $linkableAttributeValueExists = false;
                foreach ($linkableAttributeCodes as $linkableAttributeCode) {
                    if (!empty($childProduct->mappedDataFields[$linkableAttributeCode])) {
                        $linkableAttributeValueExists = true;
                    }
                }
                if (!$linkableAttributeValueExists) {
                    $childProduct->parentSku = null;
                    $childProduct->mappedDataFields[Product::VISIBILITY] = Product\Visibility::VISIBILITY_BOTH;
                } else {
                    $configurableSkuHaveValidChildren = true;
                }
            }
            if (!$configurableSkuHaveValidChildren) {
                foreach ($dataRows as $dataRowKey => $dataRow) {
                    if ($dataRow->mappedDataFields['sku'] == $configurableSku) {
                        unset($dataRows[$dataRowKey]);
                    }
                }
            }
        }
        return $dataRows;
    }

    /**
     * @param $configurableSku
     * @param DataRow[] $dataRows
     * @return DataRow[]
     */
    public function getSimpleProductsForConfigurable($configurableSku, array $dataRows): array
    {
        if (count($this->childDataRowsByConfigurableSku) == 0) {
            foreach ($dataRows as $dataRow) {
                $this->childDataRowsByConfigurableSku[$dataRow->parentSku][] = $dataRow;
            }
        }

        return $this->childDataRowsByConfigurableSku[$configurableSku] ?? [];
    }

    /**
     * @param DataRow[] $dataRows
     * @return DataRow[]
     * @throws Exception
     */
    private function getNotConfigurableDataRows(array $dataRows): array
    {
        $resultNotConfigurableDataRows = [];

        foreach ($dataRows as $dataRow) {
            if (!empty($dataRow->parentSku)) {
                continue;
            }

            if (!array_key_exists('type_id', $dataRow->mappedDataFields)) {
                throw new Exception($dataRow->mappedDataFields['sku'] . ' Unable to get configurable dataRows. missing key type_id');
            }

            if ($dataRow->mappedDataFields['type_id'] == Type::TYPE_SIMPLE) {
                $resultNotConfigurableDataRows[] = $dataRow;
            }
        }

        return $resultNotConfigurableDataRows;
    }
}
