<?php

namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;
use Sumkabum\Magento2ProductImport\Service\Image;

class ProductImageConsumer
{
    private ProductImage $productImageService;
    private LoggerInterface $logger;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        ProductImage $productImageService,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository
    ) {
        $this->productImageService = $productImageService;
        $this->logger = $logger;
        $this->productRepository = $productRepository;
    }

    /**
     * @param Image\ConsumerImageDataInterface $consumerImageData
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function updateImages(\Sumkabum\Magento2ProductImport\Service\Image\ConsumerImageDataInterface $consumerImageData): void
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->productRepository->get($consumerImageData->getProductSku());
        $this->logger->info(\Sumkabum\Magento2ProductImport\Service\Importer::LOGGER_TOPIC . ' ' . $product->getSku() . ' start updating images with consumer');

        $images = [];
        foreach ($consumerImageData->getConsumerImageDataRows() as $consumerImageDataRow) {
            $image = new Image();
            $image->setUrl($consumerImageDataRow->getUrl())
                ->setLabel($consumerImageDataRow->getLabel())
                ->setIsBaseImage($consumerImageDataRow->isBaseImage())
                ->setIsSmallImage($consumerImageDataRow->isSmallImage())
                ->setIsThumbnail($consumerImageDataRow->isThumbnail())
                ->setIsSwatchImage($consumerImageDataRow->isSwatchImage())
            ;
            $images[] = $image;
        }

        try {
            $this->productImageService->updateImages($product, $images);
        } catch (\Throwable $t) {
            $this->logger->error(\Sumkabum\Magento2ProductImport\Service\Importer::LOGGER_TOPIC . ' ' . $product->getSku() . $t->getMessage() . "\n" . $t->getTraceAsString());
        }
    }
}
