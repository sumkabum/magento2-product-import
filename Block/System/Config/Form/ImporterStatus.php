<?php

namespace Sumkabum\Magento2ProductImport\Block\System\Config\Form;

use Sumkabum\Magento2ProductImport\Service\Scheduler;

class ImporterStatus extends \Magento\Config\Block\System\Config\Form\Field
{
    public const IMPORTER_JOB_CODE = null;

    protected function _renderValue(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        /** @var \Magento\Framework\View\Element\Template $block */
        $block = $this->getLayout()->createBlock(static::class);
        $block->setTemplate('Sumkabum_Magento2ProductImport::config/importer_status.phtml');
        return $block->toHtml();
    }
}
