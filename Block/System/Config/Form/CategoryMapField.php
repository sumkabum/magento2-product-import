<?php
namespace Sumkabum\Magento2ProductImport\Block\System\Config\Form;

use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Model\Category;
use Magento\Framework\UrlInterface;

class CategoryMapField extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_template = 'Sumkabum_Magento2ProductImport::config/category_map_field.phtml';

    /**
     * @return \Magento\Framework\Data\Form\Element\AbstractElement
     */
    public function getElement(): \Magento\Framework\Data\Form\Element\AbstractElement
    {
        return $this->element;
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->element = $element;
        return $this->_toHtml();
    }

    public function getMagentoCategoryOptionsArray(): array
    {
        $optionsArray = [];

        /** @var Collection $categoryCollection */
        $categoryCollection = ObjectManager::getInstance()->create(Collection::class);
        $categoryCollection
            ->addAttributeToSelect('*')
            ->addFieldToFilter('name', 'Default Category')
            ->addOrder('entity_id', 'asc')
            ->load();


        /** @var Category $rootCategory */
        $rootCategory = $categoryCollection->getFirstItem();

        /** @var Collection $categoryCollection */
        $categoryCollection = ObjectManager::getInstance()->create(Collection::class);
        $categoryCollection
            ->addAttributeToSelect('*')
            ->addFieldToFilter('entity_id', ['in' => $rootCategory->getAllChildren(true)])
            ->load();

        foreach ($categoryCollection->getItems() as $category) {
            /** @var Category $category */

            $children = $category->getParentCategories();

            $childrenNames = [];
            foreach ($children as $childrenCategory) {
                $childrenNames[] = $childrenCategory->getName() . ' (' . $childrenCategory->getEntityId() . ')';
            }

            if (!empty($children)) {
                $name = implode(' / ', $childrenNames);
            } else {
                $name = $category->getName() . ' (' . $category->getEntityId() . ')';
            }

            $optionsArray[] = [
                'id' => $category->getEntityId(),
                'name' => $name,
            ];
        }

        return $this->sortOptionsByNameAsc($optionsArray);
    }

    public function getSourceCategoriesOptionsArray(): array
    {
        $optionsArray = [];
        $optionsArray[] = [
            'id' => 1,
            'name' => 'Name 1',
        ];
        $optionsArray[] = [
            'id' => 2,
            'name' => 'Name 2',
        ];

        return $optionsArray;
    }

    protected function sortOptionsByNameAsc($optionsArray)
    {
        usort($optionsArray, function($a, $b){
            if ($a['name'] == $b['name']) {
                return 0;
            }
            return $a['name'] < $b['name'] ? -1 : 1;
        });
        return $optionsArray;
    }

    public function getImportSourceCategoriesUrl(): string
    {
        /** @var UrlInterface $url */
        $url = ObjectManager::getInstance()->get(UrlInterface::class);
        return $url->getUrl('mpreklaamimporter/sourcecategory/import');
    }

    public function getImportSourceCategoriesName(): string
    {
        return 'Import Source Categories';
    }
}
