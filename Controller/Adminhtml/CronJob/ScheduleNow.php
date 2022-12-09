<?php
namespace Sumkabum\Magento2ProductImport\Controller\Adminhtml\CronJob;

use Exception;
use Magento\Backend\App\AbstractAction;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;

class ScheduleNow extends AbstractAction
{
    /**
     * @var \Sumkabum\Magento2ProductImport\Service\Scheduler
     */
    private $magentoSchedulerService;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Sumkabum\Magento2ProductImport\Service\Scheduler $magentoSchedulerService,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        parent::__construct($context);
        $this->magentoSchedulerService = $magentoSchedulerService;
        $this->resultFactory = $resultFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * @throws Exception
     */
    public function execute(): Redirect
    {
        /** @var Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $result->setRefererUrl();

        $jobCode = $this->getRequest()->getParam('job_code');
        $nextJob = $this->magentoSchedulerService->getNextJob($jobCode);
        if (!$nextJob) {
            $this->messageManager->addErrorMessage('Job not found with code: ' . $jobCode . '. Probably cron is not enabled');
            return $result;
        }
        $nextJob->setData('scheduled_at', (new \DateTime())->format('Y-m-d H:i:s'));
        $nextJob->save($nextJob);

        $this->messageManager->addSuccessMessage('Job scheduled at . ' . $nextJob->getData('scheduled_at'));

        return $result;
    }
}
