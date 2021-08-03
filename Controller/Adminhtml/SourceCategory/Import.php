<?php
namespace Sumkabum\Magento2ProductImport\Controller\Adminhtml\SourceCategory;

use Magento\Backend\App\AbstractAction;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Import extends AbstractAction
{
    protected $pageFactory;

    public function __construct(
        Action\Context $context,
        PageFactory $pageFactory,
        ManagerInterface $messageManager,
        ResultFactory $resultFactory
    ) {
        $this->pageFactory = $pageFactory;
        parent::__construct($context);
        $this->messageManager = $messageManager;
        $this->resultFactory = $resultFactory;
    }

    public function execute()
    {
        try {
            $this->messageManager->addSuccessMessage('Source categories imported! SAMPLE REQUEST');
        } catch (\Exception $e) {
            $errorMessage = 'Failed to import source categories. Error: ' . $e->getMessage();
            $this->messageManager->addErrorMessage($errorMessage);
        }
        /** @var Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $result->setRefererUrl();
        return $result;
    }
}
