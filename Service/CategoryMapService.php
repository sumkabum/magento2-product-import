<?php
namespace Sumkabum\Magento2ProductImport\Service;

use Countable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Sumkabum\Magento2ProductImport\Service\CategoryMap\CategoryMapRow;
use Sumkabum\Magento2ProductImport\Service\CategoryMap\CategoryMapRowCollection;

class CategoryMapService
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CategoryMapRowCollection[]
     */
    private $cacheCategoryMapRowCollection;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ObjectManagerInterface $objectManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->objectManager = $objectManager;
    }

    public function getMagentoCategoryIds(string $mapConfigPath, array $sourceCategoryIds): array
    {
        $magentoCategoryIds = [];
        foreach ($this->getCategoryMapRowsCollectionForSourceCategoryId($mapConfigPath, $sourceCategoryIds)->items as $categoryMapRow) {
            $magentoCategoryIds[] = $categoryMapRow->magento_category_id;
        }
        return $magentoCategoryIds;
    }

    public function getCategoryMapRowsCollectionForSourceCategoryId(string $mapConfigPath, array $sourceCategoryIds): CategoryMapRowCollection
    {
        /** @var CategoryMapRowCollection $categoryMapRowsCollectionFound */
        $categoryMapRowsCollectionFound = $this->objectManager->create(CategoryMapRowCollection::class);

        $categoryMapRowsCollection = $this->getCategoryMapRowsCollection($mapConfigPath);

        foreach ($categoryMapRowsCollection->items as $categoryMapRow) {
            if (in_array($categoryMapRow->source_category_id, $sourceCategoryIds)) {
                $categoryMapRowsCollectionFound->items[] = $categoryMapRow;
            }
        }
        return $categoryMapRowsCollectionFound;
    }

    public function getCategoryMapRowsCollection(string $configMapPath): CategoryMapRowCollection
    {
        if (!isset($this->cacheCategoryMapRowCollection[$configMapPath])) {
            /** @var CategoryMapRowCollection $categoryMapCollection */
            $categoryMapCollection = $this->objectManager->create(CategoryMapRowCollection::class);

            $configJson = $this->scopeConfig->getValue($configMapPath);
            $configArrayRows = json_decode($configJson, true);

            if (!is_countable($configArrayRows)) {
                $this->cacheCategoryMapRowCollection[$configMapPath] = $categoryMapCollection;
                return $this->cacheCategoryMapRowCollection[$configMapPath];
            }

            foreach ($configArrayRows as $configArrayRow) {
                /** @var CategoryMapRow $categoryMapRow */
                $categoryMapRow = $this->objectManager->create(CategoryMapRow::class);
                $categoryMapRow->source_category_id = $configArrayRow['source_category_id'];
                $categoryMapRow->magento_category_id = $configArrayRow['magento_category_id'];
                $categoryMapCollection->items[] = $categoryMapRow;
            }

            $this->cacheCategoryMapRowCollection[$configMapPath] = $categoryMapCollection;
        }

        return $this->cacheCategoryMapRowCollection[$configMapPath];
    }
}
