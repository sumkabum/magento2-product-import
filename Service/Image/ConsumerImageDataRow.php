<?php

namespace Sumkabum\Magento2ProductImport\Service\Image;

class ConsumerImageDataRow implements ConsumerImageDataRowInterface
{
    private string $url;
    private ?string $label = null;
    private bool $isBaseImage = false;
    private bool $isSmallImage = false;
    private bool $isThumbnail = false;
    private bool $isSwatchImage = false;
    private ?int $position = 0;

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return ConsumerImageDataRowInterface
     */
    public function setUrl(string $url): ConsumerImageDataRowInterface
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @param string|null $label
     * @return ConsumerImageDataRowInterface
     */
    public function setLabel(?string $label): ConsumerImageDataRowInterface
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return bool
     */
    public function isBaseImage(): bool
    {
        return $this->isBaseImage;
    }

    /**
     * @param bool $isBaseImage
     * @return ConsumerImageDataRowInterface
     */
    public function setIsBaseImage(bool $isBaseImage): ConsumerImageDataRowInterface
    {
        $this->isBaseImage = $isBaseImage;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSmallImage(): bool
    {
        return $this->isSmallImage;
    }

    /**
     * @param bool $isSmallImage
     * @return ConsumerImageDataRowInterface
     */
    public function setIsSmallImage(bool $isSmallImage): ConsumerImageDataRowInterface
    {
        $this->isSmallImage = $isSmallImage;
        return $this;
    }

    /**
     * @return bool
     */
    public function isThumbnail(): bool
    {
        return $this->isThumbnail;
    }

    /**
     * @param bool $isThumbnail
     * @return ConsumerImageDataRowInterface
     */
    public function setIsThumbnail(bool $isThumbnail): ConsumerImageDataRowInterface
    {
        $this->isThumbnail = $isThumbnail;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSwatchImage(): bool
    {
        return $this->isSwatchImage;
    }

    /**
     * @param bool $isSwatchImage
     * @return ConsumerImageDataRowInterface
     */
    public function setIsSwatchImage(bool $isSwatchImage): ConsumerImageDataRowInterface
    {
        $this->isSwatchImage = $isSwatchImage;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getPosition(): ?int
    {
        return $this->position;
    }

    /**
     * @param int|null $position
     * @return ConsumerImageDataRowInterface
     */
    public function setPosition(?int $position): ConsumerImageDataRowInterface
    {
        $this->position = $position;
        return $this;
    }

}
