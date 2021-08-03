<?php
namespace Sumkabum\Magento2ProductImport\Service;

Trait SkuFilter
{
    /**
     * @var string
     */
    public $skuFilter;

    private function skuFilterValid(string $sku): bool
    {
        if (!empty($this->skuFilter) && !in_array($sku, explode(',', $this->skuFilter))) {
            return false;
        }
        return true;
    }
}
