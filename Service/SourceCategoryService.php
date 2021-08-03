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

    public function __construct(
        LoggerInterface $logger,
        ObjectManagerInterface $objectManager
    ) {
        $this->logger = $logger;
        $this->objectManager = $objectManager;
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
        /** @var \Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection $collection */
        $collection = $this->objectManager->create(\Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection::class);
        $collection->addFieldToFilter('source_code', $sourceCode);
        return $collection;
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
        $path = [];
        $this->getNamesPath($sourceCategory, $sourceCategoryCollection, $path);
        return implode($separator, $path);
    }

    public function getNamesPath(SourceCategory $sourceCategory, \Sumkabum\Magento2ProductImport\Model\ResourceModel\SourceCategory\Collection $sourceCategoryCollection, array &$path)
    {
        array_unshift($path, $sourceCategory->getData($sourceCategory::FIELD_CATEGORY_NAME));
        $parent = $this->getSourceCategoryParent($sourceCategory, $sourceCategoryCollection);
        if ($parent) {
            $this->getNamesPath($parent, $sourceCategoryCollection, $path);
        }
    }
}
