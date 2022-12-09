<?php
namespace Sumkabum\Magento2ProductImport\Repository;

class SumkabumData
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resourceConnection;

    public function __construct(
      \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    public function set(string $key, $value)
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->query('
            INSERT INTO sumkabum_data
            SET `key`      = :key,
                value      = :value,
                created_at = NOW()
            ON DUPLICATE KEY
            UPDATE value = :value, updated_at = NOW();
        ', [
            'key' => $key,
            'value' => $value,
        ]);
    }

    public function get($key)
    {
        $row = $this->resourceConnection->getConnection()->fetchRow('
            select * from
                sumkabum_data
            where `key` = :key
            ;
        ', [
            'key' => $key,
        ]);
        return $row['value'] ?? null;
    }
}
