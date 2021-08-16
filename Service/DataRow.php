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
}
