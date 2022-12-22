<?php

namespace Sumkabum\Magento2ProductImport\Service\Image;

interface ConsumerImageDataRowInterface
{
    /**
     * @return string
     */
    public function getUrl(): string;

    /**
     * @param string $url
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface
     */
    public function setUrl(string $url): \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface;

    /**
     * @return string|null
     */
    public function getLabel(): ?string;

    /**
     * @param string|null $label
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface
     */
    public function setLabel(?string $label): \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface;

    /**
     * @return bool
     */
    public function isBaseImage(): bool;

    /**
     * @param bool $isBaseImage
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface
     */
    public function setIsBaseImage(bool $isBaseImage): \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface;

    /**
     * @return bool
     */
    public function isSmallImage(): bool;

    /**
     * @param bool $isSmallImage
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface
     */
    public function setIsSmallImage(bool $isSmallImage): \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface;

    /**
     * @return bool
     */
    public function isThumbnail(): bool;

    /**
     * @param bool $isThumbnail
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface
     */
    public function setIsThumbnail(bool $isThumbnail): \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface;

    /**
     * @return bool
     */
    public function isSwatchImage(): bool;

    /**
     * @param bool $isSwatchImage
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface
     */
    public function setIsSwatchImage(bool $isSwatchImage): \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface;
}
