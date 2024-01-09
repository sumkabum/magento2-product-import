<?php

namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Sumkabum\Magento2ProductImport\Service\Logger;
use Sumkabum\Magento2ProductImport\Service\Report;

class OldProducts
{
    private $productRepository;
    private $objectManager;
    private $storeManager;
    private $state;
    private $logger;
    private $report;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    private $resourceConnection;

    public function __construct (
        ProductRepositoryInterface $productRepository,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        State $state,
        Logger $logger,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        Report $report,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {

        $this->productRepository = $productRepository;
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->state = $state;
        $this->logger = $logger;
        $this->report = $report;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->resourceConnection = $resourceConnection;
    }

    protected function setAreaCode()
    {
        try {
            $this->storeManager->setCurrentStore(0);
            $this->state->setAreaCode(Area::AREA_GLOBAL);
        } catch (\Exception $e) { }
    }

    /**
     * @param string $sourceCode
     * @param array $skuList
     * @param $sourceCodeFieldName
     * @return void
     */
    public function disableProductsThatAreNotInList(string $sourceCode, array $skuList, string $sourceCodeFieldName = 'source_code')
    {
        $skusToDisable = $this->getSkusToDisable($sourceCode, $skuList, $sourceCodeFieldName);

        $this->logger->info('Old products count ' . count($skusToDisable));

        // start disabling
        $limit = 500;
        $currentPage = 1;

        $oldProductsToDisable = $this->getProductsBySkuList($skusToDisable, $limit, $currentPage);
        while (count($oldProductsToDisable->getItems()) > 0) {
            $this->logger->info('Disabling old products. Progress: ' . $limit * $currentPage);
            foreach ($oldProductsToDisable->getItems() as $product) {
                try {
                    $this->disableProduct($product);
                    $this->getReport()->increaseByNumber($this->report::KEY_STATUS_CHANGED_TO_DISABLED);
                    $this->logger->info($product->getSku() . ' Not exists in feed. Updated status to disabled');
                } catch (\Throwable $t) {
                    $errorMessage = $product->getSku() . ' Failed to disable old product.'
                        . ' Error: ' . $t->getMessage();
                    $this->getReport()->addMessage($this->getReport()::KEY_ERRORS, $errorMessage);
                    $this->logger->error($errorMessage . $t->getTraceAsString());
                }
            }
            $oldProductsToDisable = $this->getProductsBySkuList($skusToDisable, $limit, ++$currentPage);
        }
    }

    protected function disableProduct(ProductInterface $product)
    {
        $product->setStatus(Status::STATUS_DISABLED);
        $this->productRepository->save($product);
    }

    protected function getSkusToDisable(string $sourceCode, array $skusNotIn, string $sourceCodeFieldName = 'source_code'): array
    {
        $row = $this->resourceConnection->getConnection()->fetchRow("select attribute_id, backend_type from eav_attribute where attribute_code = 'source_code' and entity_type_id = 4");
        $sourceCodeAttributeId = $row['attribute_id'];
        $sourceCodeTableName = 'catalog_product_entity_' . $row['backend_type'];

        $row = $this->resourceConnection->getConnection()->fetchRow("select attribute_id, backend_type from eav_attribute where attribute_code = 'status' and entity_type_id = 4");
        $statusAttributeId = $row['attribute_id'];
        $statusTableName = 'catalog_product_entity_' . $row['backend_type'];

        $bindParams = [];
        $bindParamsNotIn = [];
        foreach ($skusNotIn as $skuNotIn) {
            $bindParams['sku_' . $skuNotIn] = $skuNotIn;
            $bindParamsNotIn[] = ':sku_' . $skuNotIn;
        }
        $bindParamsNotInString = implode(', ', $bindParamsNotIn);

        $sql = "
        select
            cpe.sku
        from catalog_product_entity cpe
                 left join $sourceCodeTableName table_source_code
                     on cpe.entity_id = table_source_code.entity_id
                            and table_source_code.attribute_id = $sourceCodeAttributeId
                            and table_source_code.store_id = 0
                 left join $statusTableName table_status
                     on cpe.entity_id = table_status.entity_id
                            and table_status.attribute_id = $statusAttributeId
                            and table_status.store_id = 0
        where
            table_status.value = 1
            and table_source_code.value = :source_code
        ";

        if (count($bindParamsNotIn) > 0) {
            $sql .= "
                and cpe.sku not in ($bindParamsNotInString);
            ";
        }

        $bindParams['source_code'] = $sourceCode;

        $connection = $this->resourceConnection->getConnection();
        return $connection->fetchCol($sql, $bindParams);
    }

    /**
     * @param array $skuList
     * @param int $limit
     * @param int $currentPage
     * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
     */
    protected function getProductsBySkuList(array $skuList, int $limit, int $currentPage = 1)
    {
        $this->setAreaCode();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('sku', $skuList, 'in')
            ->setCurrentPage($currentPage)
            ->setPageSize($limit)
            ->create();

        $productList = $this->productRepository->getList($searchCriteria);

        // fix magento last $currentPage bug
        if ((($currentPage-1) * $limit) > $productList->getTotalCount()) {
            return $this->objectManager->create(\Magento\Catalog\Api\Data\ProductSearchResultsInterface::class);
        }

        return $productList;
    }

    /**
     * @return Report
     */
    public function getReport(): Report
    {
        return $this->report;
    }

    /**
     * @param Report $report
     * @return OldProducts
     */
    public function setReport(Report $report): OldProducts
    {
        $this->report = $report;
        return $this;
    }
}
