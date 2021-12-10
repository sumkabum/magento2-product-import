<?php
namespace Sumkabum\Magento2ProductImport\Service;

class DataRowCategory
{
    use StoreBasedAttributeValuesTrait;

    public $id;
    public $parent_id;
    public $mappedDataFields;
}
