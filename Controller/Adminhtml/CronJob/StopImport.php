<?php
namespace Sumkabum\Magento2ProductImport\Controller\Adminhtml\CronJob;

use Exception;
use Magento\Backend\App\AbstractAction;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Sumkabum\Magento2ProductImport\Service\ImporterStop;

class StopImport extends AbstractAction
{

    /**
     * @var ImporterStop
     */
    private $importerStop;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        ImporterStop $importerStop
    ) {
        parent::__construct($context);
        $this->resultFactory = $resultFactory;
        $this->messageManager = $messageManager;
        $this->importerStop = $importerStop;
    }

    /**
     * @throws Exception
     */
    public function execute(): Redirect
    {
        /** @var Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $result->setRefererUrl();

        $this->importerStop->requestForImporterStop($this->getRequest()->getParam('job_code'));

        $this->messageManager->addSuccessMessage('Importer stop requested!');

        return $result;
    }
}
