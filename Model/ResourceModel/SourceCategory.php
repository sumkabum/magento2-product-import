<?php
namespace Sumkabum\Magento2ProductImport\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SourceCategory extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('importer_source_category', 'entity_id');
    }
}
