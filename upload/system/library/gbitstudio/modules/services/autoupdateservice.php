<?php

namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для автоматичних оновлень модулів
 * Відповідає за логіку автоматичної перевірки та встановлення оновлень
 */
class AutoUpdateService {
    private $moduleService;
    private $updateService;
    private $installService;
    private $config;
    private $cache;
    private $log;
    
    public function __construct($moduleService, $updateService, $installService, $config, $cache, $log) {
        $this->moduleService = $moduleService;
        $this->updateService = $updateService;
        $this->installService = $installService;
        $this->config = $config;
        $this->cache = $cache;
        $this->log = $log;
    }
    
    /**
     * Виконує автоматичну перевірку та оновлення модулів
     * 
     * @return array
     */
    public function autoCheckAndUpdate() {
        $this->log->write('GDT Auto Update: Starting auto-check');
        
        $installed_modules = $this->moduleService->getInstalledModules();
        if (empty($installed_modules)) {
            $this->log->write('GDT Auto Update: No installed modules found');
            return ['updated' => [], 'errors' => []];
        }
        
        $server_url = $this->getServerUrl();
        if (empty($server_url)) {
            $this->log->write('GDT Auto Update: No server URL configured');
            return ['updated' => [], 'errors' => []];
        }
        
        $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
        $auto_update_modules = $this->config->get('module_gdt_updater_auto_modules') ?: [];
        
        $this->log->write('GDT Auto Update: Auto-update enabled for: ' . implode(', ', $auto_update_modules));
        
        $result = $this->processAutoUpdates($installed_modules, $auto_update_modules, $server_url, $api_key);
        
        $this->cache->set('gdt_updater_last_auto_check', date('Y-m-d'));
        
        return $result;
    }
    
    /**
     * Обробляє автоматичні оновлення для модулів
     * 
     * @param array $installed_modules
     * @param array $auto_update_modules
     * @param string $server_url
     * @param string $api_key
     * @return array
     */
    private function processAutoUpdates($installed_modules, $auto_update_modules, $server_url, $api_key) {
        $updated_modules = [];
        $errors = [];
        
        foreach ($installed_modules as $module) {
            if (!in_array($module['code'], $auto_update_modules)) {
                continue;
            }
            
            $update_result = $this->tryAutoUpdateModule($module, $server_url, $api_key);
            
            if ($update_result['success']) {
                $updated_modules[] = $module['module_name'] ?? $module['name'] ?? $module['code'];
            } elseif ($update_result['error']) {
                $errors[] = $module['code'] . ': ' . $update_result['error'];
            }
        }
        
        return [
            'updated' => $updated_modules,
            'errors' => $errors
        ];
    }
    
    /**
     * Спробує виконати автоматичне оновлення модуля
     * 
     * @param array $module
     * @param string $server_url
     * @param string $api_key
     * @return array
     */
    private function tryAutoUpdateModule($module, $server_url, $api_key) {
        try {
            $update_info = $this->updateService->checkModuleUpdate($server_url, $module, $api_key);
            
            if (!$update_info || isset($update_info['error'])) {
                return ['success' => false, 'error' => null];
            }
            
            $this->log->write('GDT Auto Update: Starting update for ' . $module['code'] . 
                ' from version ' . $module['version'] . ' to ' . $update_info['version']);
            
            $result = $this->performUpdate($module, $update_info, $server_url, $api_key);
            
            if ($result === true) {
                $this->log->write('GDT Auto Update: Successfully updated ' . $module['code'] . 
                    ' to version ' . $update_info['version']);
                return ['success' => true, 'error' => null];
            } else {
                $this->log->write('GDT Auto Update: Failed to update ' . $module['code'] . ' - ' . $result);
                return ['success' => false, 'error' => $result];
            }
        } catch (\Exception $e) {
            $this->log->write('GDT Auto Update Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Виконує оновлення модуля
     * 
     * @param array $module
     * @param array $update_info
     * @param string $server_url
     * @param string $api_key
     * @return bool|string
     */
    private function performUpdate($module, $update_info, $server_url, $api_key) {
        try {
            if (!isset($update_info['download_url'])) {
                return 'Download URL not provided';
            }
            
            $temp_file = $this->updateService->downloadModule($update_info['download_url'], $module['code'], $api_key);
            
            if (!$temp_file) {
                return 'Failed to download module';
            }
            
            $result = $this->installService->installModule($temp_file, $module['code']);
            
            @unlink($temp_file);
            
            return $result;
        } catch (\Exception $e) {
            return 'Update error: ' . $e->getMessage();
        }
    }
    
    /**
     * Перевіряє наявність оновлень для конкретного модуля
     * 
     * @param string $module_code
     * @return array
     */
    public function checkForUpdate($module_code) {
        if (!$module_code) {
            return ['available' => false];
        }
        
        $module = $this->moduleService->getModuleByCode($module_code);
        if (!$module) {
            return ['available' => false];
        }
        
        $server_url = $this->getServerUrl();
        if (empty($server_url)) {
            return ['available' => false];
        }
        
        $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
        $update_info = $this->updateService->checkModuleUpdate($server_url, $module, $api_key);
        
        if ($update_info && !isset($update_info['error'])) {
            return [
                'available' => true,
                'current_version' => $module['version'],
                'new_version' => $update_info['version'],
                'module_name' => $module['module_name'] ?? $module['name']
            ];
        }
        
        return ['available' => false];
    }
    
    /**
     * Виконує оновлення для конкретного модуля
     * 
     * @param string $module_code
     * @param array|null $update_info
     * @return bool|string
     */
    public function performModuleUpdate($module_code, $update_info = null) {
        if (!$module_code) {
            return 'Module code not provided';
        }
        
        $module = $this->moduleService->getModuleByCode($module_code);
        if (!$module) {
            return 'Module not found';
        }
        
        if (!$update_info) {
            $server_url = $this->getServerUrl();
            if (empty($server_url)) {
                return 'Server URL not configured';
            }
            
            $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
            $update_info = $this->updateService->checkModuleUpdate($server_url, $module, $api_key);
            
            if (!$update_info || isset($update_info['error'])) {
                return 'Update not found or server error';
            }
        }
        
        return $this->performUpdate($module, $update_info, $this->getServerUrl(), $this->config->get('module_gdt_updater_api_key') ?: '');
    }
    
    /**
     * Отримує URL сервера
     * 
     * @return string
     */
    private function getServerUrl() {
        $server_url = $this->config->get('module_gdt_updater_server');
        
        if (empty($server_url)) {
            $docker_server_url = getenv('GDT_UPDATE_SERVER');
            if ($docker_server_url) {
                return $docker_server_url;
            }
            
            if (defined('HTTP_SERVER')) {
                return HTTP_SERVER . 'ocm_gdt_update/server';
            }
        }
        
        return $server_url ?: '';
    }
}
