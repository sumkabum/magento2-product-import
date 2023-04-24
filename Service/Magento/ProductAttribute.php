<?php
namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\OptionManagement;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\Option;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Swatches\Helper\Data;
use Magento\Swatches\Helper\Media;
use Magento\Swatches\Model\Swatch;
use PDO;
use Psr\Log\LoggerInterface;
use Sumkabum\Magento2ProductImport\Service\Logger;
use Zend_Validate_Exception;

class ProductAttribute
{
    /**
     * @var Manager
     */
    private $cacheManager;
    /**
     * @var Config
     */
    private $eavConfig;
    /**
     * @var array
     */
    protected $existingAttributesCache = [];
    /**
     * @var Product
     */
    protected $magentoProductService;
    /**
     * @var Media
     */
    protected $swatchHelperMedia;
    /**
     * @var Product\Attribute\Repository
     */
    protected $attributeRepository;
    /**
     * @var OptionManagement
     */
    protected $attributeOptionManagement;
    /**
     * @var AttributeOptionInterfaceFactory
     */
    protected $attributeOptionFactory;
    /**
     * @var Filesystem
     */
    protected $filesystem;
    /**
     * @var Product\Media\Config
     */
    protected $mediaConfig;
    /**
     * @var LoggerInterface
     */
    public $logger;
    /**
     * @var array
     */
    protected $cacheAttributes = [];
    /**
     * @var array
     */
    protected $cacheAttributeOptions = [];
    protected $cacheAttributeOptionsByAttributeCode = [];
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    public function __construct(
        Manager $cacheManager,
        Config $eavConfig,
        Media $swatchHelperMedia,
        Product\Attribute\Repository $attributeRepository,
        OptionManagement $attributeOptionManagement,
        AttributeOptionInterfaceFactory $attributeOptionFactory,
        Filesystem $filesystem,
        Product\Media\Config $mediaConfig,
        Logger $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->cacheManager = $cacheManager;
        $this->eavConfig = $eavConfig;
        $this->swatchHelperMedia = $swatchHelperMedia;
        $this->attributeRepository = $attributeRepository;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->attributeOptionFactory = $attributeOptionFactory;
        $this->filesystem = $filesystem;
        $this->mediaConfig = $mediaConfig;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param LoggerInterface $logger
     * @return ProductAttribute
     */
    public function setLogger(LoggerInterface $logger): ProductAttribute
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @throws LocalizedException
     */
    public function getOrCreateOptionIdByLabel(string $attributeCode, string $optionLabel): ?int
    {
        if (empty($optionLabel)) {
            return null;
        }
        return $this->getOrCreateOptionByLabel($attributeCode, $optionLabel)->getValue();
    }

    /**
     * @throws LocalizedException
     */
    public function getOrCreateOptionByLabel(string $attributeCode, string $optionLabel): ?AttributeOptionInterface
    {
        /** @var Attribute $attribute */
        $attribute = $this->getAttribute($attributeCode);
        $option = $this->getOptionByLabel($attribute, $optionLabel);

        if (!$option) {
            $option = $this->createOptionByLabel($attribute, $optionLabel);
        }

        return $option;
    }

    /**
     * @param Attribute $attribute
     * @param string $optionLabel
     * @return AttributeOptionInterface|null
     */
    public function getOptionByLabel(Attribute $attribute, string $optionLabel): ?AttributeOptionInterface
    {
        $optionLabel = strtolower($optionLabel);

        if (!array_key_exists($attribute->getAttributeCode(), $this->cacheAttributeOptions)
            || !array_key_exists($optionLabel, $this->cacheAttributeOptions[$attribute->getAttributeCode()]))
        {

            $options = $attribute->getOptions();
            foreach ($options as $option) {
                $this->cacheAttributeOptions[$attribute->getAttributeCode()][strtolower($option->getLabel())] = $option;
            }
        }

        if (array_key_exists($attribute->getAttributeCode(), $this->cacheAttributeOptions)
            && array_key_exists($optionLabel, $this->cacheAttributeOptions[$attribute->getAttributeCode()])) {
            return $this->cacheAttributeOptions[$attribute->getAttributeCode()][$optionLabel];
        }

        return null;
    }

    /**
     * @param string $attributeCode
     * @param $optionId
     * @return AttributeOptionInterface|null
     * @throws LocalizedException
     */
    public function getOptionByIdAndAttributeCode(string $attributeCode, $optionId): ?AttributeOptionInterface
    {
        /** @var Attribute $attribute */
        $attribute = $this->getAttribute($attributeCode);

        $options = $attribute->getOptions();

        foreach ($options as $option) {

            if ($option instanceof Option) {
                if ($option->getValue() == $optionId) {
                    return $option;
                }
            }
        }

        return null;
    }

    /**
     * @param string $attributeCode
     * @param $optionId
     * @return AttributeOptionInterface|null
     * @throws LocalizedException
     */
    public function getOptionByIdAndAttributeCodeUsingCache(string $attributeCode, $optionId): ?AttributeOptionInterface
    {
        if (!array_key_exists($attributeCode, $this->cacheAttributeOptionsByAttributeCode)) {
            /** @var Attribute $attribute */
            $attribute = $this->getAttribute($attributeCode);
            $options = $attribute->getOptions();
            $this->cacheAttributeOptionsByAttributeCode[$attributeCode] = $options;
        } else {
            $options = $this->cacheAttributeOptionsByAttributeCode[$attributeCode];
        }

        foreach ($options as $option) {

            if ($option instanceof Option) {
                if ($option->getValue() == $optionId) {
                    return $option;
                }
            }
        }
        return null;
    }

    /**
     * @param Attribute $attribute
     * @param $optionId
     * @return AttributeOptionInterface|null
     */
    public function getOptionById(Attribute $attribute, $optionId): ?AttributeOptionInterface
    {
        $options = $attribute->getOptions();

        foreach ($options as $option) {

            if ($option instanceof Option) {
                if ($option->getValue() == $optionId) {
                    return $option;
                }
            }
        }

        return null;
    }

    /**
     * @param Attribute $attribute
     * @param string $label
     * @param null $position
     * @return AttributeOptionInterface|null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function createOptionByLabel(Attribute $attribute, string $label, $position = null): ?AttributeOptionInterface
    {
        $option = $this->attributeOptionFactory->create();
        $option->setLabel($label);
        $option->setSortOrder($position);

        $this->attributeOptionManagement->add($attribute->getAttributeCode(), $option);

        $attribute = $this->attributeRepository->save($attribute);
        $this->cacheAttributes[$attribute->getAttributeCode()] = $attribute;
        $this->logger->info('Created new attribute option. attribute_code: ' . $attribute->getAttributeCode() . ' optionLabel: ' . $label);
        return $option;
    }

    /**
     * @param string $attributeCode
     * @param $optionId
     * @return array|mixed|string|null
     * @throws LocalizedException
     * @throws Exception
     */
    public function getOptionLabelById(string $attributeCode, $optionId)
    {
        /** @var Attribute $attribute */
        $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        $options = $attribute->getOptions();

        foreach ($options as $option) {

            if ($option instanceof Option) {
                if ($option->getValue() == $optionId) {
                    return $option->getLabel();
                }
                continue;
            }
        }

        throw new Exception("Unable to find optionLabel. attribute_code: $attributeCode, optionId: $optionId");
    }

    /**
     * @param Attribute $attribute
     * @param $optionId
     * @return array|mixed|string|null
     * @throws LocalizedException
     * @throws Exception
     */
    public function getOptionLabelByIdByAttrObj(Attribute $attribute, $optionId)
    {
        $options = $attribute->getOptions();

        foreach ($options as $option) {

            if ($option instanceof Option) {
                if ($option->getValue() == $optionId) {
                    return $option->getLabel();
                }
                continue;
            }
        }

        throw new Exception("Unable to find optionLabel. attribute_code: {$attribute->getAttributeCode()}, optionId: $optionId");
    }

    /**
     * @param string $attributeCode
     * @param int $attributeSetId
     * @throws LocalizedException
     * @throws Zend_Validate_Exception
     */
    public function createAttribute(string $attributeCode, int $attributeSetId, $attributeCreateData = [])
    {
        $attributeData = [
            'type' => 'varchar',
            'backend' => '',
            'frontend' => '',
            'label' => $attributeCode,
            'input' => 'text',
            'class' => '',
            'source' => '',
            'global' => Attribute::SCOPE_GLOBAL,
            'visible' => true,
            'required' => 0,
            'user_defined' => 1,
            'default' => null,
            'searchable' => 0,
            'filterable' => 0,
            'comparable' => 0,
            'visible_on_front' => 0,
            'used_in_product_listing' => false,
            'unique' => 0,
            'apply_to' => '',
        ];

        foreach ($attributeCreateData as $attributeCreateDataKey => $attributeCreateValue)  {
            $attributeData[$attributeCreateDataKey] = $attributeCreateValue;
        }

        /** @var EavSetup $eavSetup */
        $eavSetup = ObjectManager::getInstance()->get(EavSetup::class);
        $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode, $attributeData);

        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId(\Magento\Catalog\Model\Product::ENTITY, $attributeSetId);

        $attributeId = $eavSetup->getAttributeId(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);

        $eavSetup->addAttributeToSet(\Magento\Catalog\Model\Product::ENTITY, $attributeSetId, $attributeGroupId, $attributeId);
    }

    /**
     * @param DataRow $dataRow
     * @throws LocalizedException
     */
    public function checkForDropdownAttributes(DataRow $dataRow)
    {
        foreach ($dataRow->mappedData as $attributeCode => $attributeValue) {
            /** @var Attribute $attribute */
            $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);

            if ($attribute->getFrontendInput() == 'select' && $attribute->getIsUserDefined()) {
                $dataRow->mappedData[$attributeCode] = $this->getOptionIdByLabel($attributeCode, $attributeValue);
            }
        }
    }

    public function productAttributeExists(string $attribute_code, int $attribute_set_id)
    {
        /** @var \Sumkabum\Magento2ProductImport\Service\Magento\Attribute $attributeService */
        $attributeService = ObjectManager::getInstance()->get(\Sumkabum\Magento2ProductImport\Service\Magento\Attribute::class);
        return $attributeService->attributeExists(Product::ENTITY, $attribute_code);
    }

    /**
     * @param array $dataFields
     * @throws Exception
     */
    public function checkIfAllAttributesExists(array $dataFields, $attributeSetId = null, $attributeCreateData = [])
    {
        $doNotCheckThese = [
            'attribute_set_id',
            'website_ids',
            'type_id',
            'qty',
            'stock_data',
            'extension_attributes'
        ];

        foreach ($dataFields as $key => $value) {
            if (in_array($key, $doNotCheckThese)) {
                continue;
            }
            if (!$this->productAttributeExists($key, $attributeSetId ?? $dataFields['attribute_set_id'])) {
                $this->createAttribute($key, $attributeSetId ?? $dataFields['attribute_set_id'], $attributeCreateData);
                $this->existingAttributesCache = [];
                $this->cacheManager->clean($this->cacheManager->getAvailableTypes());
                $this->logger->info("Created attribute: $key and added to attribute_set_id: {$dataFields['attribute_set_id']}");
            }
        }
    }

    /**
     * @return Product
     */
    public function getMagentoProductService(): Product
    {
        return $this->magentoProductService;
    }

    /**
     * @param \Magentopood\WebshopIntegrationBase\Service\Magento\Product $magentoProductService
     * @return ProductAttribute
     */
    public function setMagentoProductService(\Magentopood\WebshopIntegrationBase\Service\Magento\Product $magentoProductService): ProductAttribute
    {
        $this->magentoProductService = $magentoProductService;
        return $this;
    }

    /**
     * @param string $attributeCode
     * @param $attributeSetId
     * @param string $attributeLabel
     * @return \Magento\Catalog\Api\Data\ProductAttributeInterface|Attribute|\Magento\Eav\Api\Data\AttributeInterface|void
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Validate_Exception
     */
    public function getOrCreateSwatchAttribute(string $attributeCode, $attributeSetId, string $attributeLabel = null): Attribute
    {
        return $this->getAttribute($attributeCode) ?? $this->createSwatchAttribute($attributeCode, $attributeSetId, $attributeLabel);
    }

    public function getOrCreateDropdownAttribute(string $attributeCode, $attributeSetId, string $attributeLabel = null): Attribute
    {
        return $this->getAttribute($attributeCode) ?? $this->createDropdownAttribute($attributeCode, $attributeSetId, $attributeLabel);
    }

    public function getAttribute(string $attributeCode): ?Attribute
    {
        if (!isset($this->cacheAttributes[$attributeCode])) {
            try {
                /** @var Attribute $attribute */
                $this->cacheAttributes[$attributeCode] = $this->attributeRepository->get($attributeCode);
            } catch (NoSuchEntityException $e) {}
        }

        return $this->cacheAttributes[$attributeCode] ?? null;
    }

    /**
     * @param string $attributeCode
     * @param $attributeSetId
     * @param string $attributeLabel
     * @return Attribute
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\StateException
     * @throws \Zend_Validate_Exception
     */
    public function createSwatchAttribute(string $attributeCode, $attributeSetId, string $attributeLabel = null): Attribute
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = ObjectManager::getInstance()->create(\Magento\Eav\Setup\EavSetup::class);

        $attributesData = $this->getSwatchAttributeData($attributeCode, $attributeLabel);

        $eavSetup->addAttribute(Product::ENTITY, $attributeCode, $attributesData);

        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId(\Magento\Catalog\Model\Product::ENTITY, $attributeSetId);
        $attributeId = $eavSetup->getAttributeId(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        $eavSetup->addAttributeToSet(\Magento\Catalog\Model\Product::ENTITY, $attributeSetId, $attributeGroupId, $attributeId);

        /** @var Config $eavConfig */
        $eavConfig = ObjectManager::getInstance()->get(Config::class);
        $eavConfig->clear();

        /** @var Attribute $attribute */
        $attribute = $this->attributeRepository->get($attributeCode);

        $attribute->setData(Swatch::SWATCH_INPUT_TYPE_KEY, Swatch::SWATCH_INPUT_TYPE_VISUAL);
        $attribute->setData('use_product_image_for_swatch', 1);
        $attribute->setData('update_product_preview_image', 1);
        $attribute = $this->attributeRepository->save($attribute);

        $this->logger->info('Attribute created. attribute_code: ' . $attribute->getAttributeCode());

        return $attribute;
    }

    public function createDropdownAttribute(string $attributeCode, $attributeSetId, string $attributeLabel = null): Attribute
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = ObjectManager::getInstance()->create(\Magento\Eav\Setup\EavSetup::class);

        $attributesData = $this->getDropdownAttributeData($attributeCode, $attributeLabel);

        $eavSetup->addAttribute(Product::ENTITY, $attributeCode, $attributesData);

        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId(\Magento\Catalog\Model\Product::ENTITY, $attributeSetId);
        $attributeId = $eavSetup->getAttributeId(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        $eavSetup->addAttributeToSet(\Magento\Catalog\Model\Product::ENTITY, $attributeSetId, $attributeGroupId, $attributeId);

        /** @var Config $eavConfig */
        $eavConfig = ObjectManager::getInstance()->get(Config::class);
        $eavConfig->clear();

        /** @var Attribute $attribute */
        $attribute = $this->attributeRepository->get($attributeCode);

        $this->logger->info('Attribute created. attribute_code: ' . $attribute->getAttributeCode());

        return $attribute;
    }

    /**
     * @param Attribute $attribute
     * @param string $label
     * @param null $filePath
     * @param null $position
     * @return AttributeOptionInterface
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function getOrCreateSwatchOption(Attribute $attribute, string $label, $filePath = null, $position = null): AttributeOptionInterface
    {
        $option = $this->getSwatchOption($attribute, $label);

        if (!$option) {
            $option = $this->createSwatchOption($attribute, $label, $filePath, $position);
        }

        return $option;
    }

    /**
     * @param Attribute $attribute
     * @param string $label
     * @return AttributeOptionInterface|null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function getSwatchOption(Attribute $attribute, string $label): ?AttributeOptionInterface
    {
        $options = $this->attributeOptionManagement->getItems($attribute->getAttributeCode());

        foreach ($options as $option) {
            if (empty($option->getValue())) {
                continue;
            }

            if ($option->getLabel() == $label) {
                return $option;
            }
        }
        return null;
    }

    /**
     * @param Attribute $attribute
     * @param string $label
     * @return AttributeOptionInterface[]
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function getAllSwatchOptions(Attribute $attribute): array
    {
        return $this->attributeOptionManagement->getItems($attribute->getAttributeCode());
    }

    public function createSwatchOption(Attribute $attribute, string $label, $filePath = null, $position = null): ?AttributeOptionInterface
    {
        if ($filePath) {
            $basename = basename($filePath);
            $filenamePrefix1 = substr($basename, 0, 1);
            $filenamePrefix2 = substr($basename, 1, 1);
            $ds = DIRECTORY_SEPARATOR;
            $filename = $ds . $filenamePrefix1 . $ds . $filenamePrefix2 . $ds . $basename;

            $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)
                ->writeFile($this->mediaConfig->getBaseTmpMediaPath() . $filename, file_get_contents($filePath));

            $newFile = $this->swatchHelperMedia->moveImageFromTmp($filename);
            $this->swatchHelperMedia->generateSwatchVariations($newFile);

            $value = $newFile;
        }

        $option = $this->attributeOptionFactory->create();
        $option->setLabel($label);
        $option->setValue($value ?? null);
        $option->setSortOrder($position);

        $this->attributeOptionManagement->add($attribute->getAttributeCode(), $option);

        $this->attributeRepository->save($attribute);
        $this->logger->info('Option created. attribute_code: ' . $attribute->getAttributeCode() . ' option_label: '
            . $option->getLabel() . ' option_value: ' . $option->getValue()
        );
        return $option;
    }

    protected function getSwatchAttributeData(string $attributeCode, string $attributeLabel = null): array
    {
        return [
            'type' => 'int',
            'label' => $attributeLabel ?? $attributeCode,
            'input' => 'select',
            'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
            'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Table',
            'required' => false,
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'used_in_product_listing' => 1,
            'visible_on_front' => 0,
            'user_defined' => true,
            'filterable' => 0,
            'filterable_in_search' => 0,
            'used_for_promo_rules' => 0,
            'is_html_allowed_on_front' => 0,
            'used_for_sort_by' => 0,
            Swatch::SWATCH_INPUT_TYPE_KEY => Swatch::SWATCH_INPUT_TYPE_VISUAL
        ];
    }

    protected function getDropdownAttributeData(string $attributeCode, string $attributeLabel = null): array
    {
        return [
            'type' => 'int',
            'label' => $attributeLabel ?? $attributeCode,
            'input' => 'select',
            'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
            'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Table',
            'required' => false,
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'used_in_product_listing' => 1,
            'visible_on_front' => 0,
            'user_defined' => true,
            'filterable' => 0,
            'filterable_in_search' => 0,
            'used_for_promo_rules' => 0,
            'is_html_allowed_on_front' => 0,
            'used_for_sort_by' => 0,
        ];
    }

    /**
     * @param string $attributeCode
     * @param AttributeOptionInterface $option
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function deleteAttributeOption(string $attributeCode, AttributeOptionInterface $option)
    {
        $this->attributeOptionManagement->delete($attributeCode, $option->getValue());
    }

    /**
     * @param OptionStoreLabel[] $optionStoreLabels
     * @return void
     */
    public function updateAttributeOptionStoreLabels(array $optionStoreLabels)
    {
        $byAttributeCode = [];
        foreach ($optionStoreLabels as $optionStoreLabel) {
            if (!array_key_exists($optionStoreLabel->getAttributeCode(), $byAttributeCode)) {
                $byAttributeCode[$optionStoreLabel->getAttributeCode()] = [];
            }
            $byAttributeCode[$optionStoreLabel->getAttributeCode()][] = $optionStoreLabel;
        }
        foreach ($byAttributeCode as $attributeCode => $optionStoreLabels) {
            /** @var OptionStoreLabel[] $optionStoreLabels */
            $attribute = $this->getAttribute($attributeCode);
            foreach ($optionStoreLabels as $optionStoreLabel) {
                foreach ($attribute->getOptions() as $optionInMagento) {
                    if ($optionStoreLabel->getDefaultLabel() == $optionInMagento->getLabel()) {
                        $eavAttributeOptionValue = $this->getEavAttributeOptionValue($optionInMagento->getValue(), $optionStoreLabel->getStoreId());
                        $message = 'option store label attribute_code: ' . $attributeCode . ' default label: ' . $optionStoreLabel->getDefaultLabel() . ' label: ' . $optionStoreLabel->getLabel() . ' store_id: ' . $optionStoreLabel->getStoreId();
                        if (!$eavAttributeOptionValue) {
                            $this->addEavAttributeOptionValue($optionInMagento->getValue(), $optionStoreLabel->getStoreId(), $optionStoreLabel->getLabel());
                            $this->logger->info('Added ' . $message);
                        } elseif ($eavAttributeOptionValue['value'] !== $optionStoreLabel->getLabel()){
                            $this->updateEavAttributeOptionValue($optionInMagento->getValue(), $optionStoreLabel->getStoreId(), $optionStoreLabel->getLabel());
                            $this->logger->info('Updated ' . $message);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param int $optionId
     * @param int $storeId
     * @return bool|array
     */
    public function getEavAttributeOptionValue(int $optionId, int $storeId)
    {
        $connection = $this->resourceConnection->getConnection();
        return $connection->fetchRow("
            SELECT * FROM eav_attribute_option_value eaov
                WHERE eaov.option_id = :option_id AND
                      eaov.store_id = :store_id
        ", [
            'option_id' => $optionId,
            'store_id' => $storeId
        ]);
    }

    public function addEavAttributeOptionValue(int $optionId, int $storeId, string $value)
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->query("
            INSERT INTO eav_attribute_option_value
                SET option_id = :option_id,
                      store_id = :store_id,
                    value = :value
        ", [
            'option_id' => $optionId,
            'store_id' => $storeId,
            'value' => $value,
        ]);
    }

    public function updateEavAttributeOptionValue(string $optionId, int $storeId, string $label)
    {
        $connection = $this->resourceConnection->getConnection();
        $stmt = $connection->prepare("
            UPDATE eav_attribute_option_value
                SET value = :value
                WHERE option_id = :option_id AND
                      store_id = :store_id
        ");
        $stmt->execute([
            'option_id' => $optionId,
            'store_id' => $storeId,
            'value' => $label,
        ]);
    }

}
