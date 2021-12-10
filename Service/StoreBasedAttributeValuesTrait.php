<?php
namespace Sumkabum\Magento2ProductImport\Service;

Trait StoreBasedAttributeValuesTrait
{
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
