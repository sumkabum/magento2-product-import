<?php

namespace Sumkabum\Magento2ProductImport\Service;

interface DownloaderInterface
{
    public function download(string $url, string $localFullPath);
}
