<?php

namespace Sumkabum\Magento2ProductImport\Service\Image;

class ConsumerImageData implements ConsumerImageDataInterface
{
    private string $productSku;
    private array $consumerImageDataRows = [];
    private string $downloaderClassName;

    /**
     * @return string
     */
    public function getProductSku(): string
    {
        return $this->productSku;
    }

    /**
     * @param string $productSku
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataInterface
     */
    public function setProductSku(string $productSku): \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataInterface
    {
        $this->productSku = $productSku;
        return $this;
    }

    /**
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface[]
     */
    public function getConsumerImageDataRows(): array
    {
        return $this->consumerImageDataRows;
    }

    /**
     * @param \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataRowInterface[] $consumerImageDataRows
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageData
     */
    public function setConsumerImageDataRows(array $consumerImageDataRows): \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataInterface
    {
        $this->consumerImageDataRows = $consumerImageDataRows;
        return $this;
    }

    /**
     * @return string
     */
    public function getDownloaderClassName(): string
    {
        return $this->downloaderClassName;
    }

    /**
     * @param string $downloaderClassName
     * @return \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageData
     */
    public function setDownloaderClassName(string $downloaderClassName): \Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataInterface
    {
        $this->downloaderClassName = $downloaderClassName;
        return $this;
    }
}
