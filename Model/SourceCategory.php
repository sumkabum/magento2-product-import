<?php
namespace Sumkabum\Magento2ProductImport\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class SourceCategory extends AbstractModel
{
    public function __construct(
        Context $context,
        Registry $registry,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        $data = []
    ) {
        $this->_init(ResourceModel\SourceCategory::class);
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    const FIELD_ENTITY_ID = 'entity_id';
    const FIELD_SOURCE_CODE = 'source_code';
    const FIELD_PARENT_CATEGORY_ID = 'parent_category_id';
    const FIELD_CATEGORY_ID = 'category_id';
    const FIELD_CATEGORY_NAME = 'category_name';
    const FIELD_CREATED_AT = 'created_at';
    const FIELD_UPDATED_AT = 'updated_at';
}
