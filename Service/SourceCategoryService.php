<?php

namespace Sumkabum\Magento2ProductImport\Service;

use DateTime;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Sumkabum\Magento2ProductImport\Model\SourceCategory;
use Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection;
use Psr\Log\LoggerInterface;

class SourceCategoryService
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ObjectManager
     */
    private $objectManager;

    private $souceCategoryCollectionCache;

    private $souceCategoriesCache;
    private CategoryMapWithEditorService $categoryMapWithEditorService;

    public function __construct(
        LoggerInterface $logger,
        ObjectManagerInterface $objectManager,
        CategoryMapWithEditorService $categoryMapWithEditorService
    ) {
        $this->logger = $logger;
        $this->objectManager = $objectManager;
        $this->categoryMapWithEditorService = $categoryMapWithEditorService;
    }

    public function getMappedNamesPathArrayWithMapEditor($sourceCategoryId, string $sourceCode, string $configPath): ?array
    {
        $sourceCategoryNamesPath = $this->getSourceCategoryNamesPath($sourceCategoryId, $sourceCode);
        return $sourceCategoryNamesPath ? $this->categoryMapWithEditorService->getMappedCategoryPathAsArray($sourceCategoryNamesPath, $configPath) : null;
    }

    public function getSourceCategoryNamesPathAsArray($sourceCategoryId, string $sourceCode): ?array
    {
        $categoryNamesPath = $this->getSourceCategoryNamesPath($sourceCategoryId, $sourceCode);
        if ($categoryNamesPath) {
            $categoryNamesPathArray = [];
            foreach (explode('/', $categoryNamesPath) as $categoryName) {
                $categoryNamesPathArray[] = trim($categoryName);
            }
            return $categoryNamesPathArray;
        }
        return null;
    }

    public function getSourceCategoryNamesPath($sourceCategoryId, string $sourceCode): ?string
    {
        $sourceCategory = $this->getSourceCategory($sourceCategoryId, $sourceCode);
        return $sourceCategory ? $this->getNamesPathAsString($sourceCategory, $this->getSourceCategoryCollection($sourceCode)) : null;
    }

    /**
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Exception
     */
    public function saveSourceCategories(string $sourceCode, array $categoryDataRows)
    {
        /** @var \Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory $sourceCategoryResourceModel */
        $sourceCategoryResourceModel = ObjectManager::getInstance()->create(\Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory::class);

        foreach ($categoryDataRows as $categoryDataRow) {
            /** @var Collection $collection */
            $collection = ObjectManager::getInstance()->create(Collection::class);
            $sourceCategory = $collection
                ->addFieldToFilter(SourceCategory::FIELD_CATEGORY_ID, $categoryDataRow['category_id'])
                ->addFieldToFilter(SourceCategory::FIELD_SOURCE_CODE, $sourceCode)
                ->getItemByColumnValue(SourceCategory::FIELD_CATEGORY_ID, $categoryDataRow['category_id']);

            if (!$sourceCategory) {
                /** @var SourceCategory $sourceCategory */
                $sourceCategory = ObjectManager::getInstance()->create(SourceCategory::class);
                $sourceCategory
                    ->setData(SourceCategory::FIELD_CATEGORY_ID, $categoryDataRow['category_id'])
                    ->setData(SourceCategory::FIELD_SOURCE_CODE, $sourceCode)
                    ->setData(SourceCategory::FIELD_CREATED_AT, (new DateTime())->format('Y-m-d H:i:s'));
            }

            $sourceCategory
                ->setData(SourceCategory::FIELD_PARENT_CATEGORY_ID, $categoryDataRow['parent_category_id'] ?? null)
                ->setData(SourceCategory::FIELD_CATEGORY_NAME, $categoryDataRow['category_name'])
                ->setData(SourceCategory::FIELD_UPDATED_AT, (new DateTime())->format('Y-m-d H:i:s'))
            ;

            $sourceCategoryResourceModel->save($sourceCategory);
            $action = $sourceCategory->getData(SourceCategory::FIELD_CREATED_AT) == $sourceCategory->getData(SourceCategory::FIELD_UPDATED_AT) ? 'created' : 'updated';
            $this->logger->info('(' . $sourceCategory->getData(SourceCategory::FIELD_CATEGORY_ID) . ') "' . $sourceCategory->getData(SourceCategory::FIELD_CATEGORY_NAME) . '" ' . $action);
        }

        /** @var Collection $collection */
        $collection = ObjectManager::getInstance()->create(Collection::class);
        $collection->addFieldToFilter(SourceCategory::FIELD_SOURCE_CODE, $sourceCode)
            ->load();

        foreach ($collection as $sourceCategory) {
            $existsInSource = false;
            foreach ($categoryDataRows as $categoryDataRow) {
                if ($sourceCategory->getData(SourceCategory::FIELD_CATEGORY_ID) == $categoryDataRow['category_id']) {
                    $existsInSource = true;
                    break;
                }
            }
            if (!$existsInSource) {
                $sourceCategoryResourceModel->delete($sourceCategory);
                $this->logger->info('(' . $sourceCategory->getData(SourceCategory::FIELD_CATEGORY_ID) . ') "' . $sourceCategory->getData(SourceCategory::FIELD_CATEGORY_NAME) . '" deleted');
            }
        }
    }

    public function getSourceCategoryCollection(string $sourceCode): \Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection
    {
        if (!isset($this->souceCategoryCollectionCache[$sourceCode])) {
            /** @var \Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection $collection */
            $collection = $this->objectManager->create(\Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection::class);
            $collection->addFieldToFilter('source_code', $sourceCode);
            $this->souceCategoryCollectionCache[$sourceCode] = $collection;
        }
        return $this->souceCategoryCollectionCache[$sourceCode];
    }

    public function getSourceCategory($categoryId, string $sourceCode): ?SourceCategory
    {
        if (!isset($this->souceCategoriesCache[$sourceCode])) {
            $this->souceCategoriesCache[$sourceCode] = [];
            foreach ($this->getSourceCategoryCollection($sourceCode) as $sourceCategory) {
                $this->souceCategoriesCache[$sourceCode][$sourceCategory->getData(SourceCategory::FIELD_CATEGORY_ID)] = $sourceCategory;
            }
        }
        return $this->souceCategoriesCache[$sourceCode][$categoryId] ?? null;
    }

    /**
     * @param SourceCategory $sourceCategory
     * @param Collection $sourceCategoryCollection
     * @return SourceCategory
     */
    public function getSourceCategoryParent(SourceCategory $sourceCategory, \Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection $sourceCategoryCollection): ?SourceCategory
    {
        /** @var SourceCategory[] $collectionItems */
        $collectionItems = $sourceCategoryCollection->getItems();
        foreach ($collectionItems as $collectionItem)
        {
            if (!empty($collectionItem->getData($collectionItem::FIELD_CATEGORY_ID))
            && ($collectionItem->getData($collectionItem::FIELD_CATEGORY_ID) == $sourceCategory->getData($sourceCategory::FIELD_PARENT_CATEGORY_ID))
            ) {
                return $collectionItem;
            }
        }
        return null;
    }

    public function getNamesPathAsString(SourceCategory $sourceCategory, \Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection $sourceCategoryCollection, string $separator = ' / '): string
    {
        $path = $this->getNamesPath($sourceCategory, $sourceCategoryCollection);
        return implode($separator, $path);
    }

    public function getNamesPath(SourceCategory $sourceCategory, \Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection $sourceCategoryCollection, array $path = []): array
    {
        array_unshift($path, $sourceCategory->getData($sourceCategory::FIELD_CATEGORY_NAME));
        $parent = $this->getSourceCategoryParent($sourceCategory, $sourceCategoryCollection);
        if ($parent) {
            $path = $this->getNamesPath($parent, $sourceCategoryCollection, $path);
        }
        return $path;
    }

    public function getSourceCategoryIds(array $sourceCategoriesDataRows): array
    {
        $ids = [];
        foreach ($sourceCategoriesDataRows as $row) {
            $ids[] = $row['category_id'];
        }
        return $ids;
    }

    public function getNamesPathsArrayByCategoryDataRows(array $sourceCategoryDataRows): array
    {
        $categoryPaths = [];
        foreach ($sourceCategoryDataRows as $dataRow) {
            $path = [];
            $this->getNamesPathByCategoryDataRows($dataRow, $sourceCategoryDataRows, $path);
            $categoryPaths[] = $path;
        }
        return $categoryPaths;
    }

    public function getNamesPathByCategoryDataRows($categoryDataRow, array $sourceCategoryDataRows, array &$path)
    {
        array_unshift($path, $categoryDataRow['category_name']);
        $parent = $this->getCategoryDataRowByCategoryId($categoryDataRow['parent_category_id'], $sourceCategoryDataRows);
        if ($parent) {
            $this->getNamesPathByCategoryDataRows($parent, $sourceCategoryDataRows, $path);
        }
    }

    public function getCategoryDataRowByCategoryId($categoryId, array $sourceCategoryDataRows): ?array
    {
        foreach ($sourceCategoryDataRows as $categoryDataRow) {
            if ($categoryId == $categoryDataRow['category_id']) {
                return $categoryDataRow;
            }
        }
        return null;
    }
}
