<?php
namespace Sumkabum\Magento2ProductImport\Service;

class DataRow
{
    /**
     * @var array|UpdateFieldInterface[]
     */
    public $mappedDataFields = [];

    /**
     * @var Image[]
     */
    public $images = [];

    /**
     * @var bool
     */
    public $catUpdateImagesIfProductExists = false;

    /**
     * @var string
     */
    public $parentSku;

    /**
     * @var StoreBasedAttributeValues[]
     */
    public $storeBasedAttributeValuesArray = [];

    public function addStoreBasedValue(int $storeId, string $attributeCode, ?string $attributeValue)
    {
        if (!isset($this->storeBasedAttributeValuesArray[$storeId])) {
            $storeBasedAttributeValues = new StoreBasedAttributeValues();
            $storeBasedAttributeValues->storeId = $storeId;
            $this->storeBasedAttributeValuesArray[$storeId] = $storeBasedAttributeValues;
        }

        $this->storeBasedAttributeValuesArray[$storeId]->mappedDataFields[$attributeCode] = $attributeValue;
    }
}
