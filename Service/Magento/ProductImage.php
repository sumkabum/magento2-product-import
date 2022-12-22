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
        if (!$this->report) {
            $this->report = ObjectManager::getInstance()->get(Report::class);
        }
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
    public function updateImages(\Magento\Catalog\Model\Product $product, array $images, $removeTmpImage = true)
    {
        foreach ($images as $key => $image) {
            if (!$this->isUrlValid($image->getUrl())) {
                unset($images[$key]);
                $message = $product->getSku() . ' invalid image url: ' . $image->getUrl();
                $this->getLogger()->error($message);
                $this->getReport()->addMessage($this->getReport()::KEY_ERRORS, $message);
            }
        }
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
            $this->getReport()->increaseByNumber($this->getReport()::KEY_IMAGES_REMOVED);
        }
        if (!empty($imagesToDelete)) {
            $product = $this->productRepository->save($product);
        }

        // add images
        foreach ($imagesToAdd as $imageToAddKey => $imageToAdd) {

            try {
                $imageLocalFullPath = $this->getImagesLocalDirPath() . $this->getFilenameFromUrl($imageToAdd->getUrl());

                if ($downloader = $imageToAdd->getDownloader()) {
                    $downloader->download($imageToAdd->getUrl(), $imageLocalFullPath);
                } else {
                    $this->download($imageToAdd->getUrl(), $imageLocalFullPath, $imageToAdd->getUsername(), $imageToAdd->getPassword());
                }
            } catch (\Throwable $t) {
                $this->logger->error('Failed to download image. ' . $t->getMessage() . $t->getTraceAsString());
            }

            if (!$this->isValid($imageLocalFullPath)) {
                $message = $product->getSku() . ' Invalid image. Url: ' . $imageToAdd->getUrl();
                $this->getReport()->addMessage($this->getReport()::KEY_ERRORS, $message);
                $this->logger->info($message);
                unset($imagesToAdd[$imageToAddKey]);
                continue;
            }

            try {
                $product->addImageToMediaGallery($imageLocalFullPath, null, $removeTmpImage, false);
                $this->logger->info($product->getSku() . ' adding image: ' . $imageToAdd->getUrl());
                $this->getReport()->increaseByNumber($this->getReport()::KEY_IMAGES_ADDED);

            } catch (Exception $e) {
                $this->logger->info($product->getSku() . ' image full path: ' . $imageLocalFullPath . ' Error message: ' . $e->getMessage());
                $this->getReport()->messages[$this->getReport()::KEY_ERRORS][] = $product->getSku() . ' ' . $e->getMessage();
            }
        }

        if (!empty($imagesToAdd)) {
            $product = $this->productRepository->save($product);
        }

        if (!empty($imagesToAdd) || !empty($imagesToDelete) || $this->someThumbnailsMissing($product) && (count($images) > 0)) {
            $firstImage = reset($images) ? reset($images) : null;
            $this->updateThumbnails($product, $images);
            $this->updateImagesPositions($product, $images);
            $this->updateImageLabels($product, $images);
            $product = $this->productRepository->save($product);
        }

        $this->emulation->stopEnvironmentEmulation();

        return $product;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param Image[] $images
     * @return void
     */
    public function updateImageLabels(\Magento\Catalog\Model\Product $product, array $images)
    {
        $existingMediaGalleryEntries = $product->getMediaGalleryEntries();
        foreach ($existingMediaGalleryEntries as $entry) {
            foreach ($images as $image) {
                if ($this->areTheFilenamesSame($entry->getFile(), $this->getFilenameFromUrl($image->getUrl()))) {
                    $entry->setLabel($image->getLabel());
                }
            }
        }

        $product->setMediaGalleryEntries($existingMediaGalleryEntries);
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
        $magentoFilenameExtension = pathinfo($magentoFilename, PATHINFO_EXTENSION);

        $magentoFilename = $this->replaceLastMatch('/' . $sourceFilePathInfo['filename'], '/', $magentoFilename);
        $magentoFilename = str_replace('.' . strtolower($magentoFilenameExtension), '', $magentoFilename);

        $magentoFilename = preg_replace('/(_\d+)+/', '', $magentoFilename);
        $magentoFilename = preg_replace('/\/.\/.\//', '', $magentoFilename);

        return $magentoFilename === '';
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param Image[] $images
     * @throws LocalizedException
     */
    public function updateThumbnails(\Magento\Catalog\Model\Product $product, array $images)
    {
        $this->galleryReadHandler->execute($product);
        $mediaGalleryEntries = $product->getMediaGalleryEntries();

        $valuesIsNotSet = [
            'image' => 'image',
            'small_image' => 'small_image',
            'thumbnail' => 'thumbnail',
            'swatch_image' => 'swatch_image',
        ];
        foreach ($mediaGalleryEntries as $existingImage) {
            foreach ($images as $image) {
                if ($image && $this->areTheFilenamesSame($existingImage->getFile(), $this->getFilenameFromUrl($image->getUrl()))) {
                    if ($image->isBaseImage()) {
                        $product->setData('image', $existingImage->getFile());
                        unset($valuesIsNotSet['image']);
                    }
                    if ($image->isSmallImage()) {
                        $product->setData('small_image', $existingImage->getFile());
                        unset($valuesIsNotSet['small_image']);
                    }
                    if ($image->isThumbnail()) {
                        $product->setData('thumbnail', $existingImage->getFile());
                        unset($valuesIsNotSet['thumbnail']);
                    }
                    if ($image->isSwatchImage()) {
                        $product->setData('swatch_image', $existingImage->getFile());
                        unset($valuesIsNotSet['swatch_image']);
                    }
                }
            }
        }

        if (count($mediaGalleryEntries) > 0) {
            foreach ($valuesIsNotSet as $valueIsNotSet) {
                $product->setData($valueIsNotSet, $mediaGalleryEntries[0]->getFile());
            }
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
                    $this->imageProcessor->updateImage($product, $existingImage->getFile(), ['position' => $image->getPosition()]);
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

        $imageUrl = str_replace(' ', '%20', $imageUrl);

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

    public function isUrlValid(string $url): bool
    {
        return array_key_exists('extension', pathinfo($url));
    }
}
