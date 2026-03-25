<?php

namespace Gbitstudio\Modules\Services;

use Gbitstudio\Modules\GdtConstants;
use Gbitstudio\Modules\Traits\JsonHandlerTrait;

/**
 * Сервіс для роботи з модулями
 * Відповідає за бізнес-логіку роботи з модулями
 * Читає дані тільки з opencart-module.json файлів
 */
class ModuleService {
    use JsonHandlerTrait;
    
    /** @var ModuleIndexService */
    private $index_service;

    public function __construct(ModuleIndexService $index_service) {
        $this->index_service = $index_service;
    }
    
    /**
     * Отримує список встановлених модулів
     * Читає тільки з opencart-module.json файлів
     * 
     * @return array
     */
    public function getInstalledModules() {
        return $this->getModulesFromJsonFiles();
    }
    
    /**
     * Отримує модуль за кодом
     * Читає тільки з opencart-module.json файлів
     * 
     * @param string $code Код модуля
     * @return array|null
     */
    public function getModuleByCode($code) {
        return $this->getModuleFromJsonFiles($code);
    }
    
    /**
     * Отримує модулі з opencart-module.json файлів
     * Шукає за індексом в system/module-index.json та окремими файлами в modules/
     * 
     * @return array
     */
    private function getModulesFromJsonFiles() {
        $modules = [];
        
        try {
            $index = $this->index_service->getIndex();
            
            // Look for modules in index
            foreach ($index as $code => $version) {
                $module_data = $this->getModuleFromJsonFiles($code);
                if ($module_data) {
                    $modules[] = $module_data;
                }
            }
            
            // Fallback: scan directory for old structure only if index is empty
            if (empty($modules)) {
                $modules_dir = GdtConstants::DIR_MODULES;
                if (is_dir($modules_dir)) {
                    $items = scandir($modules_dir);
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..' || substr($item, -5) === '.json') {
                            continue;
                        }
                        
                        // Old structure: folder/{code}/opencart-module.json
                        if (is_dir($modules_dir . $item)) {
                            $module_data = $this->getModuleFromJsonFiles($item);
                            if ($module_data) {
                                $modules[] = $module_data;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            LoggerService::error('Error getting modules from JSON files: ' . $e->getMessage(), 'ModuleService');
        }
        
        return $modules;
    }
    
    /**
     * Отримує модуль з JSON файла за кодом
     * Шукає спочатку за новою схемою (modules/{code}.json), потім за старою.
     * 
     * @param string $code
     * @return array|null
     */
    private function getModuleFromJsonFiles($code) {
        try {
            $modules_dir = GdtConstants::DIR_MODULES;
            
            // 1. New structure: modules/{code}.json
            $new_path = $modules_dir . $code . '.json';
            if (file_exists($new_path)) {
                return $this->parseJsonFile($new_path, $code);
            }
            
            // 2. Old structure: modules/{code}/opencart-module.json
            $old_path = $modules_dir . $code . '/opencart-module.json';
            if (file_exists($old_path)) {
                return $this->parseJsonFile($old_path, $code);
            }
        } catch (\Exception $e) {
            LoggerService::error('Error getting module from JSON file: ' . $e->getMessage(), 'ModuleService');
        }
        
        return null;
    }
    
    /**
     * Парсить opencart-module.json файл
     * 
     * @param string $file_path
     * @param string $code
     * @return array|null
     */
    private function parseJsonFile($file_path, $code) {
        if (!file_exists($file_path)) {
            return null;
        }
        
        try {
            $json_content = file_get_contents($file_path);
            $data = $this->decodeJson($json_content);
            
            if (empty($data)) {
                return null;
            }
            
            return [
                'code' => $data['code'] ?? $code,
                'name' => $data['module_name'] ?? $data['name'] ?? $code,
                'module_name' => $data['module_name'] ?? $data['name'] ?? $code,
                'version' => $data['version'] ?? '1.0.0',
                'author' => $data['creator_name'] ?? $data['author'] ?? 'Unknown',
                'creator_name' => $data['creator_name'] ?? $data['author'] ?? 'Unknown',
                'author_url' => $data['author_url'] ?? ($data['link'] ?? ''),
                'link' => $data['link'] ?? ($data['author_url'] ?? ''),
                'description' => $data['description'] ?? '',
                'controller' => $data['controller'] ?? '',
                'provider' => $data['provider'] ?? '',
                'files' => $data['files'] ?? [],
                'source' => GdtConstants::SOURCE_JSON,
                'file_path' => $file_path
            ];
        } catch (\Exception $e) {
            LoggerService::error('Error parsing JSON file ' . $file_path . ': ' . $e->getMessage(), 'ModuleService');
            return null;
        }
    }
}
