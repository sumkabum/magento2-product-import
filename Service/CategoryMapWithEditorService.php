<?php
namespace Sumkabum\Magento2ProductImport\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;

class CategoryMapWithEditorService
{
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function getMappedCategoryPathAsArray(string $sourceCategoryPath, string $configPath)
    {
        return explode('/', $this->getMappedCategoryPath($sourceCategoryPath, $configPath));
    }

    public function getMappedCategoryPath(string $sourceCategoryPath, string $configPath)
    {
        $sourceCategoryNames = explode('/', $sourceCategoryPath);
        $sourceCategoryNamesCleaned = [];
        foreach ($sourceCategoryNames as $sourceCategoryName) {
            $sourceCategoryNamesCleaned[] = trim($sourceCategoryName);
        }
        $cleanedSourceCategoryPath = implode('/', $sourceCategoryNamesCleaned);

        $configMap = $this->scopeConfig->getValue($configPath);
        $configLines = explode("\n", $configMap);
        foreach ($configLines as $configLine) {
            $columns = explode('->', $configLine);
            $cleanedColumns = [];
            foreach ($columns as $column) {
                $names = explode('/', $column);
                $tmp = [];
                foreach ($names as $name) {
                    $tmp[] = trim($name);
                }
                $cleanedColumns[] = implode('/', $tmp);
            }
            if ($cleanedColumns[0] == $cleanedSourceCategoryPath) {
                return $cleanedColumns[1];
            }
        }
    }
}
