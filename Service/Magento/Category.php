<?php
namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;

class Category
{
    private $cacheCategory = [];

    /**
     * @throws CouldNotSaveException
     */
    public function getOrCreateCategoryIdsByCategoryNamesPaths(array $categoryNamesPaths): array
    {
        $magentoCategoryIds = [];
        foreach ($categoryNamesPaths as $categoryNamesPath) {
            foreach ($this->getOrCreateCategoryIds($categoryNamesPath) as $categoryId) {
                $magentoCategoryIds[$categoryId] = $categoryId;
            }
        }
        return $magentoCategoryIds;
    }

    /**
     * @throws CouldNotSaveException
     */
    public function getOrCreateCategoryIds(array $categoryPathNames): array
    {
        $categoryIds = [];
        $parentCategoryId = null;
        foreach ($categoryPathNames as $categoryPathName) {
            $category = $this->getOrCreateCategory($categoryPathName, $parentCategoryId);
            $categoryIds[] = $category->getId();
            $parentCategoryId = $category->getId();
        }

        return $categoryIds;
    }

    /**
     * @param string $categoryName
     * @param int|null $parentCategoryId
     * @return \Magento\Catalog\Model\Category|DataObject
     * @throws CouldNotSaveException
     */
    public function getOrCreateCategory(string $categoryName, ?int $parentCategoryId)
    {
        if ($parentCategoryId === null) {
            $parentCategoryId = \Magento\Catalog\Model\Category::TREE_ROOT_ID;
        }
        if (empty($this->cacheCategory[$this->getCacheCategoryKey($categoryName, $parentCategoryId)])) {

            $category = $this->loadCategory($categoryName, $parentCategoryId);
            if (!$category) {
                $category = $this->createCategory($categoryName, $parentCategoryId);
            }
            $this->cacheCategory[$this->getCacheCategoryKey($categoryName, $parentCategoryId)] = $category;
        }
        return  $this->cacheCategory[$this->getCacheCategoryKey($categoryName, $parentCategoryId)];
    }

    /**
     * @param string $categoryName
     * @param int $parentId
     * @return \Magento\Catalog\Model\Category|DataObject|null
     */
    public function loadCategory(string $categoryName, int $parentId)
    {
        /** @var Collection $categoryCollection */
        $categoryCollection = ObjectManager::getInstance()->create(Collection::class);
        $categoryCollection->addFieldToFilter('name', $categoryName)
            ->addFieldToFilter('parent_id', $parentId)
            ->setPageSize(1)
            ->load();

        if (!$categoryCollection->count()) {
            return null;
        }
        return $categoryCollection->getFirstItem();
    }

    private function getCacheCategoryKey($categoryName, $parentId): string
    {
        return $categoryName . '/parent_id:' . $parentId;
    }

    /**
     * @param string $categoryName
     * @param int $parentId
     * @return \Magento\Catalog\Model\Category
     * @throws CouldNotSaveException
     */
    public function createCategory(string $categoryName, int $parentId): \Magento\Catalog\Model\Category
    {
        /** @var \Magento\Catalog\Model\Category $category */
        $category = ObjectManager::getInstance()->create(\Magento\Catalog\Model\Category::class);
        $category->setName($categoryName)
            ->setParentId($parentId)
            ->setIsActive(true)
            ->setIncludeInMenu(true);

        /** @var CategoryRepository $categoryRepository */
        $categoryRepository = ObjectManager::getInstance()->create(CategoryRepositoryInterface::class);
        return $categoryRepository->save($category);
    }
}
