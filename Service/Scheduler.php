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

    public function scheduleAt($jobCode, $dateTime)
    {
        $nextJob = $this->getNextJob($jobCode);
        if (!$nextJob) {
            throw \Exception('Unable to start cron job at: ' . $dateTime->format('Y-m-d H:i:s') . '. Next job not found. Code: ' . $jobCode);
        }
        $nextJob->setData('scheduled_at', $dateTime->format('Y-m-d H:i:s'));
        $nextJob->save($nextJob);
    }

    public function addAndScheduleAt($jobCode, $dateTime)
    {
        /** @var \Magento\Cron\Model\Schedule $job */
        $job = ObjectManager::getInstance()->create(\Magento\Cron\Model\Schedule::class);
        $nextJob->setData('job_code', $jobCode);
        $nextJob->setData('status', 'pending');
        $nextJob->setData('created_at', (new \DateTime())->format('Y-m-d H:i:s'));
        $nextJob->setData('scheduled_at', $dateTime->format('Y-m-d H:i:s'));
        $nextJob->save($nextJob);
    }
}
