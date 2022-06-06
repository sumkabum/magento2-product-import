<?php

namespace Sumkabum\Magento2ProductImport\Service\Magento;

class OptionStoreLabel
{
    /**
     * @var string
     */
    private $attributeCode;
    /**
     * @var string
     */
    private $defaultLabel;
    /**
     * @var int
     */
    private $storeId;
    /**
     * @var string
     */
    private $label;

    /**
     * @return string
     */
    public function getAttributeCode(): string
    {
        return $this->attributeCode;
    }

    /**
     * @param string $attributeCode
     * @return OptionStoreLabel
     */
    public function setAttributeCode(string $attributeCode): OptionStoreLabel
    {
        $this->attributeCode = $attributeCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultLabel(): string
    {
        return $this->defaultLabel;
    }

    /**
     * @param string $defaultLabel
     * @return OptionStoreLabel
     */
    public function setDefaultLabel(string $defaultLabel): OptionStoreLabel
    {
        $this->defaultLabel = $defaultLabel;
        return $this;
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * @param int $storeId
     * @return OptionStoreLabel
     */
    public function setStoreId(int $storeId): OptionStoreLabel
    {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     * @return OptionStoreLabel
     */
    public function setLabel(string $label): OptionStoreLabel
    {
        $this->label = $label;
        return $this;
    }

}
