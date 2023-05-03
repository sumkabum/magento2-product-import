<?php

namespace Sumkabum\Magento2ProductImport\Service;

use Sumkabum\Magento2ProductImport\Repository\SumkabumData;

class ImporterStop
{
    const DATA_KEY_IMPORTER_STOP_REQUESTED = 'importer_stop_requestd_cron_job_code';
    /**
     * @var SumkabumData
     */
    private $sumkabumData;
    /**
     * @var Scheduler
     */
    private $scheduler;
    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        SumkabumData $sumkabumData,
        Scheduler $scheduler,
        Logger $logger
    ) {
        $this->sumkabumData = $sumkabumData;
        $this->scheduler = $scheduler;
        $this->logger = $logger;
    }

    public function checkForImporterStopRequestAndExit()
    {
        if ($cronJobCode = $this->sumkabumData->get(self::DATA_KEY_IMPORTER_STOP_REQUESTED)) {
            $this->sumkabumData->set(\Sumkabum\Magento2ProductImport\Service\ImporterStop::DATA_KEY_IMPORTER_STOP_REQUESTED, 0);
            $currentRunningJob = $this->scheduler->getCurrentRunningJob($cronJobCode);

            if (!$currentRunningJob) {
                $this->logger->info('Importer stop requested but job_code not found: ' . $cronJobCode . ' Not exiting');
                return null;
            }

            $currentRunningJob->delete();
            $this->logger->info('Importer stop requested for job_code: ' . $cronJobCode . ' Exiting ...');
            exit();
        }
    }

    public function requestForImporterStop(string $cronJobCode)
    {
        $this->sumkabumData->set(\Sumkabum\Magento2ProductImport\Service\ImporterStop::DATA_KEY_IMPORTER_STOP_REQUESTED, $cronJobCode);
    }
}
