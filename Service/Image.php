<?php

namespace Sumkabum\Magento2ProductImport\Service;

class Image
{
    /**
     * @var string
     */
    private $url;
    /**
     * @var string|null
     */
    private $label;
    /**
     * @var bool
     */
    private $isBaseImage = false;
    /**
     * @var bool
     */
    private $isSmallImage = false;
    /**
     * @var bool
     */
    private $isThumbnail = false;
    /**
     * @var bool
     */
    private $isSwatchImage = false;
    /**
     * @var int|null
     */
    private $position;
    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    private $downloader;

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param mixed $url
     * @return Image
     */
    public function setUrl($url): self
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
     * @return Image
     */
    public function setLabel(?string $label): Image
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
     * @return Image
     */
    public function setIsBaseImage(bool $isBaseImage): Image
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
     * @return Image
     */
    public function setIsSmallImage(bool $isSmallImage): Image
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
     * @return Image
     */
    public function setIsThumbnail(bool $isThumbnail): Image
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
     * @return Image
     */
    public function setIsSwatchImage(bool $isSwatchImage): Image
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
     * @return Image
     */
    public function setPosition(?int $position): Image
    {
        $this->position = $position;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string $username
     * @return Image
     */
    public function setUsername(?string $username): Image
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return string
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return Image
     */
    public function setPassword(?string $password): Image
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return DownloaderInterface
     */
    public function getDownloader(): ?DownloaderInterface
    {
        return $this->downloader;
    }

    /**
     * @param DownloaderInterface $downloader
     */
    public function setDownloader(DownloaderInterface $downloader): Image
    {
        $this->downloader = $downloader;
        return $this;
    }

}
