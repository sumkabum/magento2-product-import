<?php

namespace Sumkabum\Magento2ProductImport\Block\System\Config\Form;

use Sumkabum\Magento2ProductImport\Service\Scheduler;

class CronInfoField extends \Magento\Config\Block\System\Config\Form\Field
{
    const IMPORTER_JOB_CODE = null;
    /**
     * @var Scheduler
     */
    private $magentoSchedulerService;
    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    private $url;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        Scheduler $magentoSchedulerService,
        \Magento\Backend\Model\UrlInterface $url,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->magentoSchedulerService = $magentoSchedulerService;
        $this->url = $url;
    }

    protected function _renderValue(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $content = '<td>';
        if ($currentlyRunningJob = $this->magentoSchedulerService->getCurrentRunningJob(static::IMPORTER_JOB_CODE)) {
            $content .= 'Import currently running. Started at ' . $currentlyRunningJob->getData('executed_at') . ' UTC';
        } else {
            if ($nextJob = $this->magentoSchedulerService->getNextJob(static::IMPORTER_JOB_CODE)) {
                $content .= 'Scheduled at ' . $nextJob->getData('scheduled_at') . ' UTC';
                $startNowUrl = $this->url->getUrl('sumkabumimporter/cronJob/scheduleNow', ['job_code' => static::IMPORTER_JOB_CODE]);
                $content .= ' <button onclick="window.location.href = \'' . $startNowUrl . '\'; return false;">Schedule now</button></td>';
            } else {
                $content .= 'Next run not found. Probably cron is not enabled';
            }
        }

        return $content;
    }
}
