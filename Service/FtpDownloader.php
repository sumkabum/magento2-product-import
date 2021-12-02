<?php

namespace Sumkabum\Magento2ProductImport\Service;

use League\Flysystem\Adapter\Ftp;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;

class FtpDownloader implements DownloaderInterface
{
    public $host;
    public $port;
    public $username;
    public $password;

    /**
     * @var Filesystem
     */
    private $fileSystem;

    public function __construct(string $host, int $port, string $username, string $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @throws FileNotFoundException
     */
    public function download(string $url, $localFullPath)
    {
        $contents = $this->getFilesystem()->read($url);
        file_put_contents($localFullPath, $contents);
    }

    private function getFilesystem(): Filesystem
    {
        if (!$this->fileSystem) {
            $this->fileSystem = new Filesystem(
                new Ftp([
                    'host' => $this->host,
                    'port' => $this->port,
                    'username' => $this->username,
                    'password' => $this->password
                ])
            );
        }
        return $this->fileSystem;
    }
}
