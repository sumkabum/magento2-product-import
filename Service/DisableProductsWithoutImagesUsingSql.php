<?php

namespace Sumkabum\Magento2ProductImport\Service;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Exception\FileSystemException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Sumkabum\Magento2ProductImport\Repository\SumkabumData;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class DisableProductsWithoutImagesUsingSql
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var State
     */
    private $state;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    private $areaCodeIsSet = false;
    /**
     * @var DirectoryList
     */
    private $directoryList;
    /**
    /**
     * @var ProductAction
     */
    private $productAction;
    /**
     * @var SumkabumData
     */
    private $sumkabumData;
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resourceConnection;
    /**
     * @var ImporterStop
     */
    private $importerStop;

    public $checkForStopRequest = false;

    public function __construct(
        StoreManagerInterface $storeManager,
        State $state,
        ProductRepositoryInterface $productRepository,
        DirectoryList $directoryList,
        \Magento\Catalog\Model\Product\Action $productAction,
        SumkabumData $sumkabumData,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        ImporterStop $importerStop
    ) {
        $this->storeManager = $storeManager;
        $this->state = $state;
        $this->productRepository = $productRepository;
        $this->directoryList = $directoryList;
        $this->productAction = $productAction;
        $this->sumkabumData = $sumkabumData;
        $this->resourceConnection = $resourceConnection;
        $this->importerStop = $importerStop;
    }

    /**
     * @throws FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(?OutputInterface $output = null, ?Report $report = null, ?LoggerInterface $logger = null, $updateProgress = false)
    {
        $this->setAreaCode();
        $limit = 10000;
        $offset = 0;

        $productSkusToDisable = [];

        if ($updateProgress) {
            $this->sumkabumData->set(ImporterStatus::DATA_KEY_IMPORTER_CURRENT_JOB, 'Collection products without images');
        }

        while (true) {

            $rows = $this->getRows($limit, $offset);
            $offset = $offset + $limit;

            $message = 'Checking products without images progress limit: ' . $limit . ' offset: ' . $offset . ' rows count: ' . count($rows);
            if ($output) {
                $output->writeln($message);
            }
            if ($logger) {
                $logger->info($message);
            }

            if ($updateProgress) {
                $this->sumkabumData->set(ImporterStatus::DATA_KEY_IMPORTER_PROGRESS, $offset . ' products checked');
            }

            if (count($rows) == 0 ) {
                break;
            }

            foreach ($rows as $row) {

                if ($this->checkForStopRequest) {
                    $this->importerStop->checkForImporterStopRequestAndExit();
                }
                if (!file_exists($this->getCatalogProductImageFullPath($row['image']))) {
                    $productSkusToDisable[] = $row['sku'];
                }
            }
        }

        if ($output) {
            $output->writeln('');
            $output->writeln('count to disable: ' . count($productSkusToDisable));
        }
        if ($logger) {
            $logger->info('count to disable: ' . count($productSkusToDisable));
        }

        if ($output && count($productSkusToDisable) == 0) {
            $output->writeln('No products to handle');
            return;
        }

        if ($output) {
            $progressBar = new ProgressBar($output, count($productSkusToDisable));
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();
        }

        if ($updateProgress) {
            $this->sumkabumData->set(ImporterStatus::DATA_KEY_IMPORTER_CURRENT_JOB, 'Disabling products without images');
            $progressCurrent = 0;
        }

        foreach ($productSkusToDisable as $sku)
        {
            try {
                if ($this->checkForStopRequest) {
                    $this->importerStop->checkForImporterStopRequestAndExit();
                }
                $this->disableProductBySKu($sku);
                $message = $sku . ' Product disabled because having no images';
                $this->logMessage($logger, $report, $message);
            } catch (\Throwable $t) {
                $this->logMessage($logger, $report, $sku . ' ' . $t->getMessage());
            }
            if ($output) $progressBar->advance();
            if ($updateProgress) {
                $this->sumkabumData->set(ImporterStatus::DATA_KEY_IMPORTER_PROGRESS, (int)(++$progressCurrent * 100 / count($productSkusToDisable)) . ' %');
            }
        }
        if ($output) {
            $progressBar->finish();
            $output->writeln('');
        }
    }

    private function logMessage($logger, $report, $message)
    {
        if ($logger) {
            $logger->info($message);
        }
        if ($report) {
            $report->addMessage('Warning', $message);
        }
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function disableProductBySKu(string $sku)
    {
        $product = $this->productRepository->get($sku);
        $this->productAction->updateAttributes([$product->getEntityId()], ['status' => Product\Attribute\Source\Status::STATUS_DISABLED], 0);
    }

    /**
     * @param string $sku
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    private function deleteProductBySKu(string $sku)
    {
        $this->productRepository->deleteById($sku);
    }

    public function getRows(int $limit = 100, int $offset = 0): ?array
    {
        $sql = "
            select cpe.sku, cpev.value as image
            from catalog_product_entity cpe
                     left join catalog_product_entity_varchar cpev on cpe.entity_id = cpev.entity_id
                        and attribute_id = (select attribute_id from eav_attribute where entity_type_id = 4 and attribute_code = 'image')
                        and store_id = 0
            limit :limit offset :offset;
        ";

        /** @var \Magento\Framework\DB\Statement\Pdo\Mysql $stmt */
        $stmt = $this->resourceConnection->getConnection()->prepare($sql);
        $stmt->bindParam('limit', $limit, \PDO::PARAM_INT, 0);
        $stmt->bindParam('offset', $offset, \PDO::PARAM_INT, 0);

        $stmt->_execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function setAreaCode(): void
    {
        if (!$this->areaCodeIsSet) {
            $this->storeManager->setCurrentStore(0);
            try {
                $this->areaCodeIsSet = true;
                $this->state->setAreaCode('adminhtml');
            } catch (Exception $e) {
            }
        }
    }

    /**
     * @param $productImageFilename
     * @return string
     * @throws FileSystemException
     */
    public function getCatalogProductImageFullPath($productImageFilename): string
    {
        $existingImageFullPath = $this->directoryList->getPath(DirectoryList::MEDIA) . '/catalog/product/' . $productImageFilename;
        return str_replace('//', '/', $existingImageFullPath);
    }
}
