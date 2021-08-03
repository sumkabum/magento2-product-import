<?php
namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Gallery\Entry;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Magento\Catalog\Model\Product\Gallery\ReadHandler;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\StateException;
use Magento\Store\Model\App\Emulation;
use Sumkabum\Magento2ProductImport\Service\Report;
use Psr\Log\LoggerInterface;

class ProductImage
{
    /**
     * @var Emulation
     */
    private $emulation;
    /**
     * @var ReadHandler
     */
    private $galleryReadHandler;
    /**
     * @var Processor
     */
    private $imageProcessor;
    /**
     * @var DirectoryList
     */
    private $directoryList;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    protected $imageMimeTypes = [
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
    ];
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Report
     */
    private $report;

    public function __construct(
        Emulation $emulation,
        ReadHandler $galleryReadHandler,
        Processor $imageProcessor,
        DirectoryList $directoryList,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    ) {
        $this->emulation = $emulation;
        $this->galleryReadHandler = $galleryReadHandler;
        $this->imageProcessor = $imageProcessor;
        $this->directoryList = $directoryList;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     * @return ProductImage
     */
    public function setLogger(LoggerInterface $logger): ProductImage
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return Report
     */
    public function getReport(): Report
    {
        return $this->report;
    }

    /**
     * @param Report $report
     * @return ProductImage
     */
    public function setReport(Report $report): ProductImage
    {
        $this->report = $report;
        return $this;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string[] $imageUrls
     * @return ProductInterface|\Magento\Catalog\Model\Product
     * @throws CouldNotSaveException
     * @throws FileSystemException
     * @throws InputException
     * @throws LocalizedException
     * @throws StateException
     * @throws Exception
     */
    public function updateImages(\Magento\Catalog\Model\Product $product, array $imageUrls)
    {
        $this->emulation->startEnvironmentEmulation(0, 'adminhtml');

        $this->galleryReadHandler->execute($product);

        $existingImages = $product->getMediaGalleryEntries();

        /** @var string[] $imageUrlsToAdd */
        $imageUrlsToAdd = [];
        /** @var Entry[] $imagesToDelete */
        $imagesToDelete = [];

        // Get images to delete
        foreach ($existingImages as $existingImageKey => $existingImage) {
            $needsDelete = true;
            foreach ($imageUrls as $imageUrl) {
                if ($this->areTheFilenamesSame($existingImage->getFile(), $this->getFilenameFromUrl($imageUrl)) && file_exists($this->getCatalogProductImageFullPath($existingImage->getFile()))) {
                    $needsDelete = false;
                    break;
                }
            }

            if ($needsDelete) {
                $imagesToDelete[] = $existingImage;
                unset($existingImages[$existingImageKey]);
            }
        }

        // Get images to add
        foreach ($imageUrls as $imageUrl) {
            $imageAlreadyExists = false;
            foreach ($existingImages as $existingImage) {
                if ($this->areTheFilenamesSame($existingImage->getFile(), $this->getFilenameFromUrl($imageUrl))) {
                    $imageAlreadyExists = true;
                    break;
                }
            }
            if (!$imageAlreadyExists) {
                $imageUrlsToAdd[] = $imageUrl;
            }
        }

        // delete images
        /** @var Processor $imageProcessor */
        $imageProcessor = ObjectManager::getInstance()->get(Processor::class);
        foreach ($imagesToDelete as $imageToDelete) {
            $imageProcessor->removeImage($product, $imageToDelete->getFile());
            $this->logger->info($product->getSku() . ' removing image ' . $imageToDelete->getFile());
            $this->report->increaseByNumber($this->report::KEY_IMAGES_REMOVED);
        }
        if (!empty($imagesToDelete)) {
            $product = $this->productRepository->save($product);
        }

        // add images
        foreach ($imageUrlsToAdd as $imageUrlToAdd) {

            $imageLocalFullPath = $this->getImagesLocalDirPath() . $this->getFilenameFromUrl($imageUrlToAdd);

            $this->download($imageUrlToAdd, $imageLocalFullPath);

            if (!$this->isValid($imageLocalFullPath)) {
                $this->logger->info($product->getSku() . ' Invalid image. Url: ' . $imageUrlToAdd);
                continue;
            }

            try {
                $product->addImageToMediaGallery($imageLocalFullPath, null, true, false);
                $this->logger->info($product->getSku() . ' adding image: ' . $imageUrlToAdd);
                $this->report->increaseByNumber($this->report::KEY_IMAGES_ADDED);

            } catch (Exception $e) {
                $this->logger->info($product->getSku() . ' image full path: ' . $imageLocalFullPath . ' Error message: ' . $e->getMessage());
                $this->report->messages[$this->report::KEY_ERRORS][] = $product->getSku() . ' ' . $e->getMessage();
            }
        }

        if (!empty($imageUrlsToAdd)) {
            $product = $this->productRepository->save($product);
        }

        if (!empty($imageUrlsToAdd) || !empty($imagesToDelete) || $this->someThumbnailsMissing($product) && (count($imageUrls) > 0)) {
            $this->updateThumbnails($product, reset($imageUrls));
            $this->updateImagesPositions($product, $imageUrls);
            $product = $this->productRepository->save($product);
        }

        $this->emulation->stopEnvironmentEmulation();

        return $product;
    }

    /**
     * @return string
     * @throws FileSystemException
     */
    protected function getImagesLocalDirPath(): string
    {
        return $this->directoryList->getPath(DirectoryList::MEDIA) . '/import/';
    }

    protected function areTheFilenamesSame($magentoFilename, $sourceFilename): bool
    {
        try {

            $sourceFilePathInfo = pathinfo($sourceFilename);

            $magentoFilename = $this->replaceLastMatch('/' . $sourceFilePathInfo['filename'], '/', $magentoFilename);
            $magentoFilename = str_replace('.' . strtolower($sourceFilePathInfo['extension']), '', $magentoFilename);

            $magentoFilename = preg_replace('/(_\d+)+/', '', $magentoFilename);
            $magentoFilename = preg_replace('/\/.\/.\//', '', $magentoFilename);

            return $magentoFilename === '';
        } catch (\Throwable $t) {
            $t=0;
        }
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string $imageUrl
     * @throws LocalizedException
     */
    public function updateThumbnails(\Magento\Catalog\Model\Product $product, string $imageUrl)
    {
        $this->galleryReadHandler->execute($product);
        $mediaGalleryEntries = $product->getMediaGalleryEntries();

        $thumbnailImageName = '';

        foreach ($mediaGalleryEntries as $existingImage) {
            if ($this->areTheFilenamesSame($existingImage->getFile(), $this->getFilenameFromUrl($imageUrl))) {
                $thumbnailImageName = $existingImage->getFile();
                break;
            }
        }

        foreach (['image', 'small_image', 'thumbnail', 'swatch_image'] as $attrName) {
            $product->setData($attrName, $thumbnailImageName);
        }
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param string[] $imageUrls
     * @throws LocalizedException
     */
    public function updateImagesPositions(\Magento\Catalog\Model\Product $product, array $imageUrls)
    {
        $this->galleryReadHandler->execute($product);

        $mediaGalleryEntries = $product->getMediaGalleryEntries();
        foreach ($mediaGalleryEntries as $existingImage) {
            foreach ($imageUrls as $position => $imageUrl) {
                if ($this->areTheFilenamesSame($existingImage->getFile(), $this->getFilenameFromUrl($imageUrl))) {
                    $this->imageProcessor->updateImage($product, $existingImage->getFile(), ['position' => $position]);
                    break;
                }
            }
        }
    }

    public function someThumbnailsMissing(\Magento\Catalog\Model\Product $product): bool
    {
        $attributes = [
            'image',
            'small_image',
            'thumbnail',
            'swatch_image',
        ];

        foreach ($attributes as $attribute) {
            if (empty($product->getData($attribute)) || $product->getData($attribute) == 'no_selection') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $productImageFilename
     * @return string
     * @throws FileSystemException
     */
    public function getCatalogProductImageFullPath($productImageFilename): string
    {
        $existingImageFullPath = $this->directoryList->getPath(DirectoryList::MEDIA) . '/catalog/product/' . $productImageFilename;
        return str_replace('//', '/', $existingImageFullPath);
    }

    private function isValid(string $imageLocalFullPath): bool
    {
        $mimeType = mime_content_type($imageLocalFullPath);
        return in_array($mimeType, $this->imageMimeTypes);
    }

    private function replaceLastMatch($search, $replace, $subject): string
    {
        $pos = strrpos($subject, $search);
        if($pos !== false){
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject;
    }

    /**
     * @param string $imageUrl
     * @param string $localFullPath
     * @throws Exception
     */
    public function download(string $imageUrl, string $localFullPath)
    {
        $fh = fopen($localFullPath, 'w');

        if (!$fh) {
            throw new Exception('Failed to create file handler for ' . $imageUrl);
        }

        $ch = curl_init($imageUrl);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        curl_exec($ch);

        if (!empty($curlError = curl_error($ch))) {
            throw new Exception('Failed to download file. Curl error: ' . $curlError);
        }
        curl_close($ch);
        fclose($fh);
    }

    protected function getFilenameFromUrl(string $url): string
    {
        preg_match('/[^\/]+$/', $url, $matches);
        $filename = $matches[0];
        $filename = preg_replace('/[^A-Za-z0-9\.\_]/', '_', $filename);
        $pathInfo = pathinfo($filename);
        return str_replace('.', '', $pathInfo['filename']) . '.' . $pathInfo['extension'];
    }

}
