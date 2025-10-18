<?php
class ModelExtensionModuleGdtUpdater extends Model {
    
    /**
     * Автоматическая проверка и запуск обновления модулей при загрузке dashboard
     */
    public function autoCheckUpdate() {
        $this->log->write('GDT Updater Auto: autoCheckUpdate method called');
        
        $manager = $this->initializeManager();
        if (!$manager) {
            return;
        }

        $installed_modules = $this->getInstalledModulesForAutoUpdate($manager);
        if (empty($installed_modules)) {
            return;
        }

        $server_url = $this->getServerUrl();
        if (empty($server_url)) {
            $this->log->write('GDT Updater Auto: No server URL configured');
            return;
        }

        $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
        $auto_update_modules = $this->config->get('module_gdt_updater_auto_modules') ?: array();
        
        $this->log->write('GDT Updater Auto: Auto-update enabled for modules: ' . implode(', ', $auto_update_modules));

        $result = $this->processAutoUpdates($manager, $installed_modules, $auto_update_modules, $server_url, $api_key);
        
        $this->handleAutoUpdateResults($result, $manager);
        $this->cache->set('gdt_updater_last_auto_check', date('Y-m-d'));
    }
    
    /**
     * Инициализирует менеджер модулей
     */
    private function initializeManager() {
        $this->load->library('gbitstudio/modules/manager');
        return new \Gbitstudio\Modules\Manager($this->registry);
    }
    
    /**
     * Получает список установленных модулей для автообновления
     */
    private function getInstalledModulesForAutoUpdate($manager) {
        $installed_modules = $manager->getInstalledModules();
        
        if (empty($installed_modules)) {
            $this->log->write('GDT Updater Auto: No installed modules found');
        }
        
        return $installed_modules;
    }
    
    /**
     * Получает URL сервера обновлений
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
        
        return $server_url;
    }
    
    /**
     * Обрабатывает автообновления для модулей
     */
    private function processAutoUpdates($manager, $installed_modules, $auto_update_modules, $server_url, $api_key) {
        $updated_modules = array();
        $errors = array();

        foreach ($installed_modules as $module) {
            if (!in_array($module['code'], $auto_update_modules)) {
                continue;
            }

            $update_result = $this->tryAutoUpdateModule($manager, $module, $server_url, $api_key);
            
            if ($update_result['success']) {
                $updated_modules[] = $module['module_name'] ?? $module['name'] ?? $module['code'];
            } elseif ($update_result['error']) {
                $errors[] = $module['code'] . ': ' . $update_result['error'];
            }
        }

        return array(
            'updated' => $updated_modules,
            'errors' => $errors
        );
    }
    
    /**
     * Пытается выполнить автообновление модуля
     */
    private function tryAutoUpdateModule($manager, $module, $server_url, $api_key) {
        try {
            $update_info = $manager->checkModuleUpdate($server_url, $module, '', $api_key);
            
            if (!$update_info || isset($update_info['error'])) {
                return array('success' => false, 'error' => null);
            }
            
            $this->log->write('GDT Updater Auto: Starting auto-update for module ' . $module['code'] . 
                ' from version ' . $module['version'] . ' to ' . $update_info['version']);
            
            $result = $manager->downloadAndInstallUpdate($server_url, $module, $update_info, '', $api_key, true);
            
            if ($result === true) {
                $this->log->write('GDT Updater Auto: Successfully auto-updated module ' . $module['code'] . 
                    ' to version ' . $update_info['version'] . ' via OpenCart process');
                return array('success' => true, 'error' => null);
            } else {
                $this->log->write('GDT Updater Auto Error: Failed to auto-update module ' . $module['code'] . ' - ' . $result);
                return array('success' => false, 'error' => $result);
            }
        } catch (Exception $e) {
            $this->log->write('GDT Updater Auto Exception: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Обрабатывает результаты автообновления
     */
    private function handleAutoUpdateResults($result, $manager) {
        if (!empty($result['updated'])) {
            $manager->clearCache();
            $this->session->data['success'] = 'Автообновлены модули: ' . implode(', ', $result['updated']);
        }

        if (!empty($result['errors'])) {
            $this->session->data['warning'] = 'Ошибки автообновления: ' . implode('; ', $result['errors']);
        }
    }

    /**
     * Проверка наличия обновлений для конкретного модуля
     */
    public function checkForUpdate($module_code = null) {
        if (!$module_code) {
            return ['available' => false];
        }

        // Загружаем менеджер модулей
        $this->load->library('gbitstudio/modules/manager');
        $manager = new \Gbitstudio\Modules\Manager($this->registry);

        // Получаем информацию о модуле
        $module = $manager->getModuleByCode($module_code);
        if (!$module) {
            return ['available' => false];
        }

        // Получаем URL сервера обновлений
        $server_url = $this->config->get('module_gdt_updater_server');
        if (empty($server_url)) {
            return ['available' => false];
        }

        $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
        
        // Проверяем обновления
        $update_info = $manager->checkModuleUpdate($server_url, $module, '', $api_key);
        
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
     * Выполнение обновления для конкретного модуля
     */
    public function performUpdate($module_code, $update_info = null) {
        if (!$module_code) {
            return 'Не указан код модуля';
        }

        // Загружаем менеджер модулей
        $this->load->library('gbitstudio/modules/manager');
        $manager = new \Gbitstudio\Modules\Manager($this->registry);

        // Получаем информацию о модуле
        $module = $manager->getModuleByCode($module_code);
        if (!$module) {
            return 'Модуль не найден';
        }

        // Если информация об обновлении не передана, получаем её
        if (!$update_info) {
            $server_url = $this->config->get('module_gdt_updater_server');
            if (empty($server_url)) {
                return 'Не настроен сервер обновлений';
            }

            $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
            $update_info = $manager->checkModuleUpdate($server_url, $module, '', $api_key);
            
            if (!$update_info || isset($update_info['error'])) {
                return 'Обновление не найдено или ошибка сервера';
            }
        }

        try {
            // Выполняем обновление через встроенный процесс OpenCart
            $result = $manager->downloadAndInstallUpdate($server_url, $module, $update_info, '', $api_key, true);
            
            if ($result === true) {
                // Очищаем кэш
                $manager->clearCache();
                return true;
            } else {
                return $result;
            }
        } catch (Exception $e) {
            return 'Ошибка при обновлении: ' . $e->getMessage();
        }
    }
}