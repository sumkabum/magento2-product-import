<?php

namespace Sumkabum\Magento2ProductImport\Service;

class Image
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

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

}
