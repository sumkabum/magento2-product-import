<?php
namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filter\FilterManager;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollection;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Psr\Log\LoggerInterface;

class UrlKey
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var FilterManager
     */
    private $filterManager;
    /**
     * @var UrlPersistInterface
     */
    private $urlPersist;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ProductUrlRewriteGenerator
     */
    private $productUrlRewriteGenerator;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ProductRepositoryInterface $productRepository,
        FilterManager $filterManager,
        UrlPersistInterface $urlPersist,
        StoreManagerInterface $storeManager,
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;
        $this->filterManager = $filterManager;
        $this->urlPersist = $urlPersist;
        $this->storeManager = $storeManager;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->logger = $logger;
    }

    /**
     * @param LoggerInterface $logger
     * @return UrlKey
     */
    public function setLogger(LoggerInterface $logger): UrlKey
    {
        $this->logger = $logger;
        return $this;
    }

    public function checkForDuplicateUrlKey(\Magento\Catalog\Model\Product $product)
    {
        if (empty($product->getUrlKey())) {
            throw new \Exception('sku: ' . $product->getSku() . ' url_key is empty');
        }

        $productUrlSuffix = (string) $this->scopeConfig->getValue('catalog/seo/product_url_suffix');

        /** @var UrlRewriteCollection $urlRewriteCollection */
        $urlRewriteCollection = ObjectManager::getInstance()->create(UrlRewriteCollection::class);
        $urlRewriteCollection->addFieldToFilter('request_path', $this->generateUrlKey($product->getUrlKey()) . $productUrlSuffix)
            ->load();

        foreach ($urlRewriteCollection->getItems() as $urlRewrite) {
            /** @var \Magento\UrlRewrite\Model\UrlRewrite $urlRewrite */
            if ($urlRewrite->getEntityType() == 'product' && $urlRewrite->getEntityId() != $product->getId()) {
                try {
                    /** @var \Magento\Catalog\Model\Product $productNeedsFix */
                    $productNeedsFix = $this->productRepository->getById($urlRewrite->getEntityId());
                    $productNeedsFix->setUrlKey($this->generateUrlKey($productNeedsFix->getName() . '-' . $productNeedsFix->getSku()));
                    $this->productRepository->save($productNeedsFix);
                    $this->regenerateUrlRewrites($productNeedsFix);

                    $logMessage = 'urlRewrites regenerated for product sku: ' . $productNeedsFix->getSku();
                    $logMessage .= ' old url_key = ' . $this->generateUrlKey($product->getUrlKey()) . $productUrlSuffix . ' new: ' . $productNeedsFix->getUrlKey() . $productUrlSuffix;

                    $this->logger->info($product->getSku() . ' ' . $logMessage);
                } catch (NoSuchEntityException $e) {
                    $this->deleteUrlRewriteEntity($urlRewrite->getEntityId());
                    $this->logger->info($product->getSku() . ' urlRewrite deleted. entity_id: ' . $urlRewrite->getData('entity_id') . ' request_path: ' . $urlRewrite->getData('request_path'));
                } catch (Exception $e) {
                    $this->logger->error($e);
                    $this->logger->info($product->getSku() . ' Failed to fix url_key');
                }
            }
        }
    }

    protected function deleteUrlRewriteEntity(int $productId, string $entityType = ProductUrlRewriteGenerator::ENTITY_TYPE)
    {
        $this->urlPersist->deleteByData([
            UrlRewrite::ENTITY_ID => $productId,
            UrlRewrite::ENTITY_TYPE => $entityType,
        ]);
    }

    public function generateUrlKey(string $value)
    {
        return $this->filterManager->translitUrl($value);
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @throws UrlAlreadyExistsException
     */
    public function regenerateUrlRewrites(\Magento\Catalog\Model\Product $product)
    {
        foreach ($this->storeManager->getStores() as $store) {
            $tmpProduct = clone $product;
            $tmpProduct->setStoreId($store->getId());

            $this->urlPersist->deleteByData([
                UrlRewrite::ENTITY_ID => $product->getId(),
                UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE,
            ]);

            $this->urlPersist->replace(
                $this->productUrlRewriteGenerator->generate($tmpProduct)
            );
        }
    }
}
