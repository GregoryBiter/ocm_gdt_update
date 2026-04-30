<?php

namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для обробки даних dashboard
 * Відповідає за формування даних про оновлення для відображення на дашборді
 */
class DashboardService {
    private $moduleService;
    private $updateService;
    private $config;
    private $log;
    
    public function __construct($moduleService, $updateService, $config) {
        $this->moduleService = $moduleService;
        $this->updateService = $updateService;
        $this->config = $config;
    }
    
    /**
     * Отримує дані про доступні оновлення для дашборду
     * 
     * @return array
     */
    public function getDashboardData() {
        $data = [
            'updates' => [],
            'error_message' => '',
            'success_message' => '',
            'no_updates' => true,
            'total_updates' => 0,
            'auto_updates_enabled' => false,
            'auto_update_performed' => false
        ];
        
        try {
            $installed_modules = $this->moduleService->getInstalledModules();
            
            if (empty($installed_modules)) {
                $data['error_message'] = 'text_no_installed_modules';
                return $data;
            }
            
            $server_url = $this->getServerUrl();
            if (empty($server_url)) {
                $data['error_message'] = 'text_server_not_configured';
                return $data;
            }
            
            $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
            $available_updates = $this->checkAvailableUpdates($installed_modules, $server_url, $api_key, $data);
            
            $data['updates'] = $available_updates;
            $data['total_updates'] = count($available_updates);
            $data['no_updates'] = ($data['total_updates'] == 0);
            
            if ($data['no_updates'] && empty($data['error_message']) && empty($data['success_message'])) {
                $data['success_message'] = 'text_all_updated';
            } elseif ($data['total_updates'] > 0 && empty($data['error_message']) && empty($data['success_message'])) {
                $data['success_message'] = 'text_updates_count';
                $data['success_args'] = [$data['total_updates']];
            }
            
        } catch (\Exception $e) {
            $data['error_message'] = 'error_general';
            $data['error_args'] = [$e->getMessage()];
        }
        
        return $data;
    }
    
    /**
     * Перевіряє доступні оновлення
     * 
     * @param array $installed_modules
     * @param string $server_url
     * @param string $api_key
     * @param array &$data
     * @return array
     */
    private function checkAvailableUpdates($installed_modules, $server_url, $api_key, &$data) {
        $available_updates = [];
        
        foreach ($installed_modules as $module) {
            $update_info = $this->updateService->checkModuleUpdate($server_url, $module, $api_key);
            
            if (is_array($update_info) && isset($update_info['error'])) {
                if ($update_info['error'] == 'curl') {
                    $data['error_message'] = 'error_curl';
                    $data['error_args'] = [$update_info['message'] ?: 'Unknown network error'];
                } elseif ($update_info['error'] == 'http') {
                    $data['error_message'] = 'error_http';
                    $data['error_args'] = [$update_info['code'] ?: 'Unknown server error'];
                }
                continue;
            }
            
            if ($update_info && isset($update_info['status']) && $update_info['status'] == 'update_available') {
                $module_update = [
                    'name' => $module['module_name'] ?? $module['name'],
                    'code' => $module['code'],
                    'current_version' => $module['version'],
                    'new_version' => $update_info['version'],
                    'description' => $update_info['description'] ?? '',
                    'auto_update' => false
                ];
                
                $auto_update_setting = $this->config->get('module_gdt_updater_auto_' . $module['code']);
                if ($auto_update_setting) {
                    $module_update['auto_update'] = true;
                    $data['auto_updates_enabled'] = true;
                }
                
                $available_updates[] = $module_update;
            }
        }
        
        return $available_updates;
    }
    
    /**
     * Перевіряє оновлення через AJAX
     * 
     * @return array
     */
    public function checkUpdatesAjax() {
        $json = [];
        
        try {
            $installed_modules = $this->moduleService->getInstalledModules();
            
            if (empty($installed_modules)) {
                $json['error'] = 'text_no_installed_modules';
                return $json;
            }
            
            $server_url = $this->getServerUrl();
            if (empty($server_url)) {
                $json['error'] = 'text_server_not_configured';
                return $json;
            }
            
            $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
            $available_updates = [];
            
            foreach ($installed_modules as $module) {
                $update_info = $this->updateService->checkModuleUpdate($server_url, $module, $api_key);
                
                if (is_array($update_info) && isset($update_info['error'])) {
                    continue;
                }
                
                if ($update_info && isset($update_info['status']) && $update_info['status'] == 'update_available') {
                    $available_updates[] = [
                        'name' => $module['module_name'] ?? $module['name'],
                        'code' => $module['code'],
                        'current_version' => $module['version'],
                        'new_version' => $update_info['version']
                    ];
                }
            }
            
            $json['updates'] = $available_updates;
            $json['total_updates'] = count($available_updates);
            
            if (count($available_updates) > 0) {
                $json['success'] = 'text_updates_count';
                $json['success_args'] = [count($available_updates)];
            } else {
                $json['success'] = 'text_all_updated';
            }
                
        } catch (\Exception $e) {
            $json['error'] = 'error_general';
            $json['error_args'] = [$e->getMessage()];
        }
        
        return $json;
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
            } elseif (defined('HTTP_SERVER')) {
                return HTTP_SERVER . 'ocm_gdt_update/server';
            }
        }
        
        return $server_url ?: '';
    }
}
