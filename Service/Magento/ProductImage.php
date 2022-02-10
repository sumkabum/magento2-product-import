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
use Sumkabum\Magento2ProductImport\Service\Image;
use Sumkabum\Magento2ProductImport\Service\Logger;
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
        Logger $logger
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
     * @param Image[] $images
     * @return ProductInterface|\Magento\Catalog\Model\Product
     * @throws CouldNotSaveException
     * @throws FileSystemException
     * @throws InputException
     * @throws LocalizedException
     * @throws StateException
     * @throws Exception
     */
    public function updateImages(\Magento\Catalog\Model\Product $product, array $images)
    {
        $this->emulation->startEnvironmentEmulation(0, 'adminhtml');

        $this->galleryReadHandler->execute($product);

        $existingImages = $product->getMediaGalleryEntries();

        /** @var Image[] $imagesToAdd */
        $imagesToAdd = [];
        /** @var Entry[] $imagesToDelete */
        $imagesToDelete = [];

        $alreadyCheckedExistingImageFileNames = [];
        // Get images to delete
        foreach ($existingImages as $existingImageKey => $existingImage) {
            $needsDelete = true;
            foreach ($images as $image) {
                if ($this->areTheFilenamesSame($existingImage->getFile(), $this->getFilenameFromUrl($image->getUrl()))
                    && file_exists($this->getCatalogProductImageFullPath($existingImage->getFile()))
                    && !isset($alreadyCheckedExistingImageFileNames[$this->getFilenameFromUrl($image->getUrl())])
                ) {
                    $alreadyCheckedExistingImageFileNames[$this->getFilenameFromUrl($image->getUrl())] = $this->getFilenameFromUrl($image->getUrl());
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
        foreach ($images as $image) {
            $imageAlreadyExists = false;
            foreach ($existingImages as $existingImage) {
                if ($this->areTheFilenamesSame($existingImage->getFile(), $this->getFilenameFromUrl($image->getUrl()))) {
                    $imageAlreadyExists = true;
                    break;
                }
            }
            if (!$imageAlreadyExists) {
                $imagesToAdd[] = $image;
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
        foreach ($imagesToAdd as $imageToAdd) {

            $imageLocalFullPath = $this->getImagesLocalDirPath() . $this->getFilenameFromUrl($imageToAdd->getUrl());

            if ($downloader = $imageToAdd->getDownloader()) {
                $downloader->download($imageToAdd->getUrl(), $imageLocalFullPath);
            } else {
                $this->download($imageToAdd->getUrl(), $imageLocalFullPath, $imageToAdd->getUsername(), $imageToAdd->getPassword());
            }

            if (!$this->isValid($imageLocalFullPath)) {
                $this->logger->info($product->getSku() . ' Invalid image. Url: ' . $imageToAdd->getUrl());
                continue;
            }

            try {
                $product->addImageToMediaGallery($imageLocalFullPath, null, true, false);
                $this->logger->info($product->getSku() . ' adding image: ' . $imageToAdd->getUrl());
                $this->report->increaseByNumber($this->report::KEY_IMAGES_ADDED);

            } catch (Exception $e) {
                $this->logger->info($product->getSku() . ' image full path: ' . $imageLocalFullPath . ' Error message: ' . $e->getMessage());
                $this->report->messages[$this->report::KEY_ERRORS][] = $product->getSku() . ' ' . $e->getMessage();
            }
        }

        if (!empty($imagesToAdd)) {
            $product = $this->productRepository->save($product);
        }

        if (!empty($imagesToAdd) || !empty($imagesToDelete) || $this->someThumbnailsMissing($product) && (count($images) > 0)) {
            $firstImage = reset($images) ? reset($images) : null;
            $this->updateThumbnails($product, $firstImage);
            $this->updateImagesPositions($product, $images);
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
        $sourceFilename = str_replace('.jpeg', '.jpg', $sourceFilename);
        $magentoFilename = str_replace('.jpeg', '.jpg', $magentoFilename);
        $sourceFilePathInfo = pathinfo($sourceFilename);

        $magentoFilename = $this->replaceLastMatch('/' . $sourceFilePathInfo['filename'], '/', $magentoFilename);
        $magentoFilename = str_replace('.' . strtolower($sourceFilePathInfo['extension']), '', $magentoFilename);

        $magentoFilename = preg_replace('/(_\d+)+/', '', $magentoFilename);
        $magentoFilename = preg_replace('/\/.\/.\//', '', $magentoFilename);

        return $magentoFilename === '';
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param Image|null $image
     * @throws LocalizedException
     */
    public function updateThumbnails(\Magento\Catalog\Model\Product $product, ?Image $image)
    {
        $this->galleryReadHandler->execute($product);
        $mediaGalleryEntries = $product->getMediaGalleryEntries();

        $thumbnailImageName = '';

        foreach ($mediaGalleryEntries as $existingImage) {
            if ($image && $this->areTheFilenamesSame($existingImage->getFile(), $this->getFilenameFromUrl($image->getUrl()))) {
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
     * @param Image[] $images
     * @throws LocalizedException
     */
    public function updateImagesPositions(\Magento\Catalog\Model\Product $product, array $images)
    {
        $this->galleryReadHandler->execute($product);

        $mediaGalleryEntries = $product->getMediaGalleryEntries();
        foreach ($mediaGalleryEntries as $existingImage) {
            foreach ($images as $position => $image) {
                if ($this->areTheFilenamesSame($existingImage->getFile(), $this->getFilenameFromUrl($image->getUrl()))) {
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
     * @param string|null $username
     * @param string|null $password
     * @throws Exception
     */
    public function download(string $imageUrl, string $localFullPath, ?string $username = null, ?string $password = null)
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
        if (!empty($username)) {
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        }

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
        $filename = strtolower($filename);
        $filename = preg_replace('/[^A-Za-z0-9\.\_]/', '_', $filename);
        $pathInfo = pathinfo($filename);
        return str_replace('.', '', $pathInfo['filename']) . '.' . $pathInfo['extension'];
    }

}
