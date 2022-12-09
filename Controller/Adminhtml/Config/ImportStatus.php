<?php
namespace Sumkabum\Magento2ProductImport\Controller\Adminhtml\Config;

use Exception;
use Magento\Backend\App\AbstractAction;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutInterface;

class ImportStatus extends AbstractAction
{
    /**
     * @var LayoutInterface
     */
    private $layout;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        LayoutInterface $layout
    ) {
        parent::__construct($context);
        $this->layout = $layout;
    }

    /**
     * @throws Exception
     */
    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        /** @var \Magento\Framework\View\Element\Template $block */
        $block = $this->layout->createBlock(\Magento\Framework\View\Element\Template::class);
        $block->setTemplate('Sumkabum_Magento2ProductImport::config/importer_status_ajax_content.phtml');
        $block->setData('job_code', $this->getRequest()->getParam('job_code'));

        $data = [
            'html' => $block->toHtml()
        ];
        $result->setData($data);

        return $result;
    }
}
