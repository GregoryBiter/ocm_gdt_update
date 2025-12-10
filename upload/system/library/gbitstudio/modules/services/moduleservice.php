<?php

namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для роботи з модулями
 * Відповідає за бізнес-логіку роботи з модулями
 * Читає дані тільки з opencart-module.json файлів
 */
class ModuleService {
    
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
     * Шукає в DIR_SYSTEM/module/{code}/opencart-module.json
     * 
     * @return array
     */
    private function getModulesFromJsonFiles() {
        $modules = [];
        
        try {
            $modules_dir = constant('DIR_SYSTEM') . 'modules/';
            
            if (!is_dir($modules_dir)) {
                return $modules;
            }
            
            $dirs = scandir($modules_dir);
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                
                $json_file = $modules_dir . $dir . '/opencart-module.json';
                if (file_exists($json_file)) {
                    $module_data = $this->parseJsonFile($json_file, $dir);
                    if ($module_data) {
                        $modules[] = $module_data;
                    }
                }
            }
        } catch (\Exception $e) {
            LoggerService::error('Error getting modules from JSON files: ' . $e->getMessage(), 'ModuleService');
        }
        
        return $modules;
    }
    
    /**
     * Отримує модуль з opencart-module.json файла за кодом
     * 
     * @param string $code
     * @return array|null
     */
    private function getModuleFromJsonFiles($code) {
        try {
            $json_file = constant('DIR_SYSTEM') . 'modules/' . $code . '/opencart-module.json';
            
            if (file_exists($json_file)) {
                return $this->parseJsonFile($json_file, $code);
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
            $data = json_decode($json_content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                LoggerService::error('JSON parse error in ' . $file_path . ': ' . json_last_error_msg(), 'ModuleService');
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
                'source' => 'opencart_module_json',
                'file_path' => $file_path
            ];
        } catch (\Exception $e) {
            LoggerService::error('Error parsing JSON file ' . $file_path . ': ' . $e->getMessage(), 'ModuleService');
            return null;
        }
    }
}
