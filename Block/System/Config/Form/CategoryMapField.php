<?php
namespace Sumkabum\Magento2ProductImport\Block\System\Config\Form;

use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Model\Category;
use Magento\Framework\UrlInterface;
use Sumkabum\Magento2ProductImport\Model\SourceCategory;
use Sumkabum\Magento2ProductImport\Service\SourceCategoryService;

class CategoryMapField extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_template = 'Sumkabum_Magento2ProductImport::config/category_map_field.phtml';

    protected $sourceCode = null;

    protected $magentoRootCategoryId = 2;

    /**
     * @var SourceCategoryService
     */
    private $sourceCategoryService;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        SourceCategoryService $sourceCategoryService,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->sourceCategoryService = $sourceCategoryService;
    }

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
            ->addFieldToFilter('entity_id', $this->magentoRootCategoryId)
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

        $collection = $this->sourceCategoryService->getSourceCategoryCollection($this->sourceCode);
        /** @var SourceCategory[] $sourceCategoryItems */
        $sourceCategoryItems = $collection->getItems();

        foreach ($sourceCategoryItems as $sourceCategory) {
            $optionsArray[] = [
                'id' => $sourceCategory->getData(SourceCategory::FIELD_CATEGORY_ID),
                'name' => $this->sourceCategoryService->getNamesPathAsString($sourceCategory, $collection),
            ];
        }

        return $this->sortOptionsByNameAsc($optionsArray);
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
