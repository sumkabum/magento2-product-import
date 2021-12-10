<?php

namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\ObjectManager;

class CategoryAttribute
{
    /**
     * @var EavSetup
     */
    private $eavSetup;
    /**
     * @var Attribute
     */
    private $attributeService;

    public function __construct(
        EavSetup $eavSetup,
        Attribute $attributeService
    ) {
        $this->eavSetup = $eavSetup;
        $this->attributeService = $attributeService;
    }

    public function getDefaultAttributeSetId(): int
    {
        return $this->eavSetup->getDefaultAttributeGroupId(\Magento\Catalog\Model\Category::ENTITY);
    }

    public function attributeExists(string $attributeCode): bool
    {
        return $this->attributeService->attributeExists(\Magento\Catalog\Model\Category::ENTITY, $attributeCode);
    }

    /**
     * @throws \Zend_Validate_Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createAttribute(string $attributeCode, array $createData = [])
    {
        $attributeData = [
            'type' => 'varchar',
            'label' => $attributeCode,
            'input' => 'text',
            'required' => false,
            'sort_order' => 35,
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'wysiwyg_enabled' => false,
            'group' => 'General Information',
        ];

        foreach ($createData as $key => $value) {
            $attributeData[$key] = $value;
        }

        /** @var EavSetup $eavSetup */
        $eavSetup = ObjectManager::getInstance()->get(EavSetup::class);
        $eavSetup->addAttribute(\Magento\Catalog\Model\Category::ENTITY, $attributeCode, $attributeData);

        $attributeSetId = $this->getDefaultAttributeSetId();
        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId(\Magento\Catalog\Model\Category::ENTITY, $attributeSetId);

        $attributeId = $eavSetup->getAttributeId(\Magento\Catalog\Model\Category::ENTITY, $attributeCode);

        $eavSetup->addAttributeToSet(\Magento\Catalog\Model\Category::ENTITY, $attributeSetId, $attributeGroupId, $attributeId);
    }
}
