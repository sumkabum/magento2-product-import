<?php

namespace Sumkabum\Magento2ProductImport\Service\Image;

interface ConsumerImageDataInterface
{
    /**
     * @return string
     */
    public function getProductSku(): string;

    /**
     * @param string $productSku
     * @return ConsumerImageDataInterface
     */
    public function setProductSku(string $productSku): ConsumerImageDataInterface;


    /**
     * @return ConsumerImageDataRowInterface[]
     */
    public function getConsumerImageDataRows(): array;

    /**
     * @param ConsumerImageDataRowInterface[] $consumerImageDataRows
     * @return ConsumerImageDataInterface
     */
    public function setConsumerImageDataRows(array $consumerImageDataRows): ConsumerImageDataInterface;
}
