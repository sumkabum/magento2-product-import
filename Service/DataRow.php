<?php
namespace Sumkabum\Magento2ProductImport\Service;

class DataRow
{
    /**
     * @var array
     */
    public $mappedDataFields = [];

    /**
     * @var Image[]
     */
    public $images = [];

    /**
     * @var string
     */
    public $parentSku;
}
