<?php
namespace Sumkabum\Magento2ProductImport\Service;

use Magento\Catalog\Model\Product;

interface UpdateFieldInterface
{
    public function getNewValue(Product $product);
}
