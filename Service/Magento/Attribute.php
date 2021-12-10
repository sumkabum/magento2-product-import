<?php

namespace Sumkabum\Magento2ProductImport\Service\Magento;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use PDO;

class Attribute
{
    public function attributeExists(string $entityTypeCode, string $attributeCode): bool
    {
        /** @var ResourceConnection $resourceConnection */
        $resourceConnection = ObjectManager::getInstance()->get(ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        $existingAttributes = $connection->fetchAll("
            SELECT eav_attribute.attribute_code, eav_attribute.* FROM eav_entity_attribute
                LEFT JOIN eav_attribute on eav_attribute.attribute_id = eav_entity_attribute.attribute_id
                LEFT JOIN eav_entity_type on eav_entity_type.entity_type_id = eav_entity_attribute.entity_type_id
                WHERE eav_entity_type.entity_type_code = :entity_type_code
        ", [
            'entity_type_code' => $entityTypeCode
        ], PDO::FETCH_GROUP|PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

        return isset($existingAttributes[$attributeCode]);
    }
}
