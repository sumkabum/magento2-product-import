<?php
namespace Sumkabum\Magento2ProductImport\Service;

class DataRow
{
    use StoreBasedAttributeValuesTrait;

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

    public $updateImagesAsync = false;

    /**
     * @var string
     */
    public $parentSku;
    /**
     * @var string
     */
    public $parentSkuGrouped;
    /**
     * @var bool
     */
    public $needsUpdatingInMagento = true;
    /**
     * @var bool
     */
    public $overwriteNeedsUpdatingIfIsParentAndChildrenDoesntNeedUpdate = true;
    /**
     * @var bool
     */
    public $disableProductIfNoImages = false;
    /**
     * @var bool
     */
    public $removeTmpImages = true;
    /**
     * @var ProductLink[]
     */
    public $productLinks = [];
    /**
     * @var bool
     */
    public $updateProductLinks = false;
}
