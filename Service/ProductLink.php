<?php

namespace Sumkabum\Magento2ProductImport\Service;

class ProductLink
{
    const TYPE_RELATED = 'related';
    const TYPE_CROSSSELL = 'crosssell';
    const TYPE_UPSELL = 'upsell';
    /**
     * @var string
     */
    public $linkSku;
    /**
     * @var string
     */
    public $type;

    /**
     * @return string
     */
    public function getLinkSku(): string
    {
        return $this->linkSku;
    }

    /**
     * @param string $linkSku
     * @return ProductLink
     */
    public function setLinkSku(string $linkSku): ProductLink
    {
        $this->linkSku = $linkSku;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return ProductLink
     */
    public function setType(string $type): ProductLink
    {
        $this->type = $type;
        return $this;
    }
}
