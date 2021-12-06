<?php
namespace Sumkabum\Magento2ProductImport\Service;

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

    public function __construct(
        \Sumkabum\Magento2ProductImport\Service\Magento\Product $productService,
        ProductImage $productImageService,
        LoggerInterface $logger,
        ObjectManagerInterface $objectManager
    ) {

        $this->productService = $productService;
        $this->productImageService = $productImageService;
        $this->logger = $logger;
        $this->objectManager = $objectManager;
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
                $configurableProduct = $this->productService->save($configurableDataRow->mappedDataFields, $doNotUpdateFields, $configurableDataRow->storeBasedAttributeValuesArray);
                $configurableProduct = $this->updateImages($configurableProduct, $configurableDataRow);

                $simpleProductDataRows = $this->getSimpleProductsForConfigurable($configurableDataRow->mappedDataFields['sku'], $dataRows);

                $simpleProducts = [];
                foreach ($simpleProductDataRows as $simpleProductDataRow) {
                    $simpleProduct = $this->productService->save($simpleProductDataRow->mappedDataFields, $doNotUpdateFields, $simpleProductDataRow->storeBasedAttributeValuesArray);
                    $this->report->increaseByNumber($this->report::KEY_PRODUCTS_UPDATED);

                    $simpleProduct = $this->updateImages($simpleProduct, $simpleProductDataRow);
                    $simpleProducts[] = $simpleProduct;
                }

                $this->productService->associateConfigurableWithSimpleProducts($configurableProduct, $simpleProducts, $linkableAttributeCodes);
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

            try {
                $simpleProduct = $this->productService->save($notConfigurableDataRow->mappedDataFields, $doNotUpdateFields, $notConfigurableDataRow->storeBasedAttributeValuesArray);
                $this->report->increaseByNumber($this->report::KEY_PRODUCTS_UPDATED);
                $this->updateImages($simpleProduct, $notConfigurableDataRow);
            } catch (\Exception $e) {
                $this->logger->error($notConfigurableDataRow->mappedDataFields['sku'] . ' Failed to save! ' . $e->getMessage() . $e->getTraceAsString());
                $this->report->addMessage($this->report::KEY_ERRORS, $notConfigurableDataRow->mappedDataFields['sku'] . ' ' . $e->getMessage());
            }
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
        if ($this->productService->isNewProduct($product) || $dataRow->catUpdateImagesIfProductExists) {
            $product = $this->productImageService->updateImages($product, $dataRow->images);
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
            foreach ($dataRows as $firstChildDataRow) {
                if ($firstChildDataRow->parentSku != $configurableSku) {
                    continue;
                }

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
                break;
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
    private function getSimpleProductsForConfigurable($configurableSku, array $dataRows): array
    {
        $childProducts = [];

        foreach ($dataRows as $dataRow) {
            if ($dataRow->parentSku == $configurableSku) {
                $childProducts[] = $dataRow;
            }
        }
        return $childProducts;
    }

    /**
     * @param DataRow[] $dataRows
     * @return DataRow[]
     */
    private function getNotConfigurableDataRows(array $dataRows): array
    {
        $resultNotConfigurableDataRows = [];

        foreach ($dataRows as $dataRow) {
            if (!empty($dataRow->parentSku)) {
                continue;
            }

            if ($dataRow->mappedDataFields['type_id'] == Type::TYPE_SIMPLE) {
                $resultNotConfigurableDataRows[] = $dataRow;
            }
        }

        return $resultNotConfigurableDataRows;
    }
}
