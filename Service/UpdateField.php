<?php
namespace Sumkabum\Magento2ProductImport\Service;

use Magento\Catalog\Model\Product;

class UpdateField implements UpdateFieldInterface
{
    public $sourceValue;

    public function __construct($sourceValue)
    {
        $this->sourceValue = $sourceValue;
    }

    public function getNewValue(Product $product)
    {
        return $this->sourceValue;
    }
}
