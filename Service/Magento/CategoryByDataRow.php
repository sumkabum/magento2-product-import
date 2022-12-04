<?php
namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Store\Model\StoreManagerInterface;
use Sumkabum\Magento2ProductImport\Service\DataRowCategory;
use Sumkabum\Magento2ProductImport\Service\Logger;

class CategoryByDataRow
{
    public const CATEGORY_FIELD_NAME_SOURCE_CODE = 'source_code';
    public const CATEGORY_FIELD_NAME_SOURCE_ID = 'source_id';
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var CategoryAttribute
     */
    private $categoryAttributeService;
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    private $cacheLoadCategoryByNameAndParentId;

    private $cacheGetOrCreateCategoryUsing = [];

    private $cacheGetMagentoCategoryIds = [];

    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        CategoryAttribute $categoryAttributeService,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->categoryAttributeService = $categoryAttributeService;
        $this->categoryRepository = $categoryRepository;

        try {
            $this->storeManager->setCurrentStore(0);
            /** @var \Magento\Framework\App\State $state */
            $state = ObjectManager::getInstance()->get(\Magento\Framework\App\State::class);
            $state->setAreaCode(Area::AREA_GLOBAL);
        } catch (\Exception $e) { }

    }

    public function disableCategoriesNotInList(array $validSourceIdsList, string $sourceCode, $fieldNameSourceCode = self::CATEGORY_FIELD_NAME_SOURCE_CODE, string $fieldNameSourceId = self::CATEGORY_FIELD_NAME_SOURCE_ID)
    {
        /** @var Collection $categoryCollection */
        $categoryCollection = ObjectManager::getInstance()->create(Collection::class);
        $categoryCollection
            ->setStoreId(0)
            ->addAttributeToSelect('*')
            ->addFieldToFilter($fieldNameSourceCode, $sourceCode)
            ->addFieldToFilter([
              ['attribute' => $fieldNameSourceId, 'nin' => $validSourceIdsList],
              ['attribute' => $fieldNameSourceId, 'null' => true]
            ])
            ->addFieldToFilter('is_active', 1)
            ->load();

        $this->logger->info('Categories count to disable: ' . $categoryCollection->count());

        foreach ($categoryCollection->getItems() as $category) {
            $category->setData('is_active', 0);
            $this->categoryRepository->save($category);
            $this->logger->info('Disabling old category with ' . $fieldNameSourceId . ': ' . $category->getData($fieldNameSourceId)
                . ' magento category id: ' . $category->getData('entity_id')
                . ' name: ' . $category->getData('name')
            );
        }
    }

    public function getMagentoCategoryIdsUsingCache(array $sourceIds, string $sourceCode, $fieldNameSourceCode = self::CATEGORY_FIELD_NAME_SOURCE_CODE, string $fieldNameSourceId = self::CATEGORY_FIELD_NAME_SOURCE_ID)
    {
        $cacheKey = implode('-', $sourceIds) . $sourceCode . $fieldNameSourceCode . $fieldNameSourceId;
        if (!array_key_exists($cacheKey, $this->cacheGetMagentoCategoryIds)) {
            $this->cacheGetMagentoCategoryIds[$cacheKey] = $this->getMagentoCategoryIds($sourceIds, $sourceCode, $fieldNameSourceCode, $fieldNameSourceId);

        }
        return $this->cacheGetMagentoCategoryIds[$cacheKey];
    }

    public function getMagentoCategoryIds(array $sourceIds, string $sourceCode, $fieldNameSourceCode = self::CATEGORY_FIELD_NAME_SOURCE_CODE, string $fieldNameSourceId = self::CATEGORY_FIELD_NAME_SOURCE_ID)
    {
        /** @var Collection $categoryCollection */
        $categoryCollection = ObjectManager::getInstance()->create(Collection::class);
        $categoryCollection
            ->addFieldToFilter($fieldNameSourceCode, $sourceCode)
            ->addFieldToFilter($fieldNameSourceId, ['in' => $sourceIds])
            ->addFieldToFilter('is_active', 1)
            ->setPageSize(1)
            ->load();

        $ids = $categoryCollection->getAllIds();;

        return $ids;
    }

    /**
     * @param DataRowCategory $dataRowCategory
     * @param \Magento\Catalog\Model\Category|null $magentoParentCategory
     * @return \Magento\Catalog\Model\Category|DataObject
     * @throws CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Validate_Exception
     */
    public function getOrCreateCategory(DataRowCategory $dataRowCategory, ?\Magento\Catalog\Model\Category $magentoParentCategory = null)
    {
        if ($magentoParentCategory === null) {
            $parentCategoryId = \Magento\Catalog\Model\Category::TREE_ROOT_ID;
        } else {
            $parentCategoryId = $magentoParentCategory->getEntityId();
        }
        $category = $this->loadCategoryByNameAndParentId($dataRowCategory->mappedDataFields['name'], $parentCategoryId);
        if (!$category) {
            $category = ObjectManager::getInstance()->create(\Magento\Catalog\Model\Category::class);
        }
        return $this->updateCategory($category, $dataRowCategory, $parentCategoryId);
    }

    /**
     * @param DataRowCategory $dataRowCategory
     * @param \Magento\Catalog\Model\Category|null $magentoParentCategory
     * @return \Magento\Catalog\Model\Category|DataObject
     * @throws CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Validate_Exception
     */
    public function getOrCreateCategoryUsingCache(DataRowCategory $dataRowCategory, ?\Magento\Catalog\Model\Category $magentoParentCategory = null)
    {
        $cacheKey = ($magentoParentCategory ? $magentoParentCategory->getEntityId() : '') . '-' . $dataRowCategory->id;
        if (!array_key_exists($cacheKey, $this->cacheGetOrCreateCategoryUsing)) {
            $this->cacheGetOrCreateCategoryUsing[$cacheKey] = $this->getOrCreateCategory($dataRowCategory, $magentoParentCategory);
        }
        return $this->cacheGetOrCreateCategoryUsing[$cacheKey];
    }

    /**
     * @param string $name
     * @param int $parentId
     * @return DataObject|\Magento\Catalog\Model\Category|null
     */
    public function loadCategoryByNameAndParentId(string $name, int $parentId)
    {
        $cacheKey = $name . '/' . $parentId;
        if (!isset($this->cacheLoadCategoryByNameAndParentId[$cacheKey])) {
            /** @var Collection $categoryCollection */
            $categoryCollection = ObjectManager::getInstance()->create(Collection::class);
            $categoryCollection
                ->addFieldToFilter('name', $name)
                ->addFieldToFilter('parent_id', $parentId)
                ->setPageSize(1)
                ->load();

            if (!$categoryCollection->count()) {
                return null;
            }
            $this->cacheLoadCategoryByNameAndParentId[$cacheKey] = $categoryCollection->getFirstItem();
        }
        return $this->cacheLoadCategoryByNameAndParentId[$cacheKey];
    }

    /**
     * @param \Magento\Catalog\Model\Category $category
     * @param DataRowCategory $dataRowCategory
     * @param int $parentId
     * @return \Magento\Catalog\Model\Category
     * @throws CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function updateCategory(\Magento\Catalog\Model\Category $category, DataRowCategory $dataRowCategory, int $parentId): \Magento\Catalog\Model\Category
    {
        foreach ($dataRowCategory->mappedDataFields as $mappedDataKey => $mappedDataValue) {
            $category->setData($mappedDataKey, $mappedDataValue);
        }
        $category
            ->setParentId($parentId)
            ->setIsActive($dataRowCategory->mappedDataFields['is_active'] ?? true)
            ->setIncludeInMenu($dataRowCategory->mappedDataFields['include_in_menu'] ?? true);

        $action = $category->getEntityId() ? 'updated' : 'created';
        /** @var \Magento\Catalog\Model\Category $category */
        $category = $this->categoryRepository->save($category);
        $this->logger->info($action . ' category. id: ' . $category->getId() . ' name: ' . $category->getName());

        foreach ($dataRowCategory->storeBasedAttributeValuesArray as $storeBasedAttributeValues) {

            $this->storeManager->setCurrentStore($storeBasedAttributeValues->storeId);
            $storeCategory = $this->categoryRepository->get($category->getEntityId(), $storeBasedAttributeValues->storeId);

            foreach ($storeBasedAttributeValues->mappedDataFields as $key => $value) {
                $storeCategory->setData($key, $value);
            }
            $storeCategory->setData('is_active', $storeBasedAttributeValues->mappedDataFields['is_active'] ?? null);
            $storeCategory->setData('include_in_menu', $storeBasedAttributeValues->mappedDataFields['include_in_menu'] ?? null);

            $this->categoryRepository->save($storeCategory);
            $this->logger->info('Updated storeId category: ' . $storeCategory->getName() . ' storeId: ' . $storeBasedAttributeValues->storeId);

            $this->storeManager->setCurrentStore(0);
        }
        return $category;
    }


    public function getDataRowPaths(array $dataRowCategories): array
    {
        $paths = [];
        foreach ($dataRowCategories as $dataRowCategory) {
            $paths[] = $this->getDataRowPath($dataRowCategory, $dataRowCategories);
        }

        $pathsByIdKeys = [];

        foreach ($paths as $path) {
            $pathIds = [];
            foreach ($path as $cat) {
                $pathIds[] = $cat->id;
            }
            $pathsByIdKeys[implode('-', $pathIds)] = $path;
        }

        foreach ($pathsByIdKeys as $idsKeyA => $pathA) {
            foreach ($pathsByIdKeys as $idsKeyB => $pathB) {
                if ($idsKeyA === $idsKeyB) continue;
                if (strpos((string)$idsKeyB, (string)$idsKeyA) !== false) {
                    unset($pathsByIdKeys[$idsKeyA]);
                    break;
                }
            }
        }
        return $pathsByIdKeys;
    }

    /**
     * @param DataRowCategory $dataRowCategoryCurrent
     * @param DataRowCategory[] $dataRowCategories
     * @param DataRowCategory[] $path
     * @return array
     */
    public function getDataRowPath(DataRowCategory $dataRowCategoryCurrent, array $dataRowCategories, array $path = []): array
    {
        array_unshift($path, $dataRowCategoryCurrent);
        if ($dataRowCategoryCurrent->parent_id) {
            foreach ($dataRowCategories as $dataRowCategory) {
                if ($dataRowCategory->id != $dataRowCategoryCurrent->parent_id) continue;
                $path = $this->getDataRowPath($dataRowCategory, $dataRowCategories, $path);
            }
        }
        return $path;
    }

    /**
     * @param array $dataRowCategoriesPaths
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Validate_Exception
     */
    public function importDataRows(array $dataRowCategoriesPaths)
    {
        foreach ($dataRowCategoriesPaths as $dataRowCategoriesPath) {
            $this->importDataRowCategoryPath($dataRowCategoriesPath);
        }
    }

    /**
     * @param DataRowCategory[] $dataRowCategoryPath
     * @return array
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Validate_Exception
     */
    public function importDataRowCategoryPath(array $dataRowCategoryPath): array
    {
        $allPossibleAttributeCodes = [];
        foreach ($dataRowCategoryPath as $dataRowCategory) {
            foreach ($dataRowCategory->mappedDataFields as $key => $value) {
                $allPossibleAttributeCodes[$key] = $key;
            }
        }
        foreach ($allPossibleAttributeCodes as $attributeCode) {
            if ($this->categoryAttributeService->attributeExists($attributeCode)) continue;
            $this->categoryAttributeService->createAttribute($attributeCode);
            $this->logger->info('Category attribute created attribute_code: ' . $attributeCode);
        }

        $sourceCategoryIdCreated = [];
        $magentoCategories = [];
        $magentoCategoryParent = null;
        foreach ($dataRowCategoryPath as $dataRowCategory) {
            if (array_key_exists($dataRowCategory->id, $sourceCategoryIdCreated)) continue;
            $magentoCategoryParent = $this->getOrCreateCategoryUsingCache($dataRowCategory, $magentoCategoryParent);
            $magentoCategories[] = $magentoCategoryParent;
            $sourceCategoryIdCreated[$dataRowCategory->id] = $dataRowCategory;
        }
        return $magentoCategories;
    }
}
