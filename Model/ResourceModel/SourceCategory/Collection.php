<?php
namespace Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Psr\Log\LoggerInterface;
use Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'importer_source_category_importer_source_category_collection';
    protected $_eventObject = 'importer_source_category_collection';

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        AdapterInterface $connection = null,
        AbstractDb $resource = null
    )
    {
        $this->_init(\Sumkabum\Magento2ProductImport\Model\SourceCategory::class, SourceCategory::class);
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $connection = null,
            $resource = null
        );
    }
}
