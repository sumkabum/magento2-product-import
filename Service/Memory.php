<?php

namespace Sumkabum\Magento2ProductImport\Service;

class Memory
{
    public function getMemoryUsage(): string
    {
        $size = memory_get_usage();
        $unit = array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }
}
