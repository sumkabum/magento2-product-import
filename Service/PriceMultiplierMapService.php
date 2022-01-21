<?php

namespace Sumkabum\Magento2ProductImport\Service;

use Magento\Catalog\Model\Category;
use Magento\Framework\App\ObjectManager;

class PriceMultiplierMapService
{
    protected $categoryLevelById = [];
    protected $priceMultiplierByCategoryId = [];
    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    public function getPriceMultiplier(array $productCategoryIds, ?string $categoryMapJson)
    {
        $priceMultiplier = null;
        $productCategoryIds = $this->sortByDeepestCategoryFirst($productCategoryIds);

        foreach ($productCategoryIds as $productCategoryId) {
            $priceMultiplier = $this->getPriceMultiplierByCategoryId($productCategoryId, $categoryMapJson);
            if ($priceMultiplier) {
                return $priceMultiplier;
            }
        }
        return $priceMultiplier;
    }

    protected function getPriceMultiplierByCategoryId($categoryId, ?string $categoryMapJson)
    {
        if (!array_key_exists($categoryMapJson, $this->priceMultiplierByCategoryId)) {

            $this->logger->info('Updating priceMultiplierByCategoryId cache');
            $this->priceMultiplierByCategoryId[$categoryMapJson] = [];
            $categoryMap = json_decode($categoryMapJson, true);

            if (!is_array($categoryMap)) return null;

            foreach ($categoryMap as $row) {
                $this->priceMultiplierByCategoryId[$categoryMapJson][$row['magento_category_id']] = $row['price_multiplier'];
            }
        }
        return $this->priceMultiplierByCategoryId[$categoryMapJson][$categoryId] ?? null;
    }

    public function getCategoryLevelById($categoryId)
    {
        if (!array_key_exists($categoryId, $this->categoryLevelById)) {
            $this->logger->info('Updating getCategoryLevelById cache');
            $this->categoryLevelById = [];

            /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection */
            $categoryCollection = ObjectManager::getInstance()->create(\Magento\Catalog\Model\ResourceModel\Category\Collection::class);
            /** @var Category[] $categories */
            $categories = $categoryCollection->getItems();
            foreach ($categories as $category) {
                $this->categoryLevelById[$category->getEntityId()] = $category->getLevel();
            }
        }
        return $this->categoryLevelById[$categoryId] ?? null;
    }

    public function sortByDeepestCategoryFirst(array $productCategoryIds)
    {
        usort($productCategoryIds, function($a, $b){
            $aLevel = $this->getCategoryLevelById($a);
            $bLevel = $this->getCategoryLevelById($b);
            if ($aLevel == $bLevel) {
                return 0;
            }
            return $aLevel > $bLevel ? -1 : 1;
        });
        return $productCategoryIds;
    }

}
