<?php

namespace Sumkabum\Magento2ProductImport\Service;

use Magento\Cron\Model\ResourceModel\Schedule;
use Magento\Cron\Model\ResourceModel\Schedule\Collection;
use Magento\Framework\App\ObjectManager;

class Scheduler
{
    /**
     * @return Schedule|null
     */
    public function getCurrentRunningJob(string $jobCode): ?\Magento\Framework\DataObject
    {
        /** @var Collection $jobsCollection */
        $jobsCollection = ObjectManager::getInstance()->create(Collection::class);
        $jobsCollection
            ->addFieldToFilter('job_code', $jobCode)
            ->addFieldToFilter('status', 'running');
        return $jobsCollection->count() > 0 ? $jobsCollection->getFirstItem() : null;
    }

    /**
     * @return Schedule|null
     */
    public function getNextJob(string $jobCode): ?\Magento\Framework\DataObject
    {
        /** @var Collection $jobsCollection */
        $jobsCollection = ObjectManager::getInstance()->create(Collection::class);
        $jobsCollection
            ->addFieldToFilter('job_code', $jobCode)
            ->addFieldToFilter('status', 'pending')
            ->addFieldToFilter('scheduled_at', ['gt' => (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s')])
            ->addOrder('scheduled_at')
        ;
        return ($jobsCollection->count() > 0) ? $jobsCollection->getFirstItem() : null;
    }
}
