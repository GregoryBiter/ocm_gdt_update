<?php
class ModelExtensionModuleGdtUpdater extends Model {
    
    /**
     * Автоматическая проверка и запуск обновления модулей при загрузке dashboard
     */
    public function autoCheckUpdate() {
        // Логируем вызов метода
        $this->log->write('GDT Updater Auto: autoCheckUpdate method called');
        var_dump('GDT Updater: AutoCheck executed');
        
        // Проверяем, запускали ли уже сегодня (кеширование)
        // $last_check = $this->cache->get('gdt_updater_last_auto_check');
        // if ($last_check == date('Y-m-d')) {
        //     $this->log->write('GDT Updater Auto: Already checked today, skipping');
        //     return;
        // }

        // Загружаем менеджер модулей
        $this->load->library('gbitstudio/modules/manager');
        $manager = new \Gbitstudio\Modules\Manager($this->registry);

        // Получаем список установленных модулей
        $installed_modules = $manager->getInstalledModules();
        
        if (empty($installed_modules)) {
            $this->log->write('GDT Updater Auto: No installed modules found');
            return;
        }

        // Получаем URL сервера обновлений
        $server_url = $this->config->get('module_gdt_updater_server');
        if (empty($server_url)) {
            // Проверяем наличие переменной окружения для Docker
            $docker_server_url = getenv('GDT_UPDATE_SERVER');
            if ($docker_server_url) {
                $server_url = $docker_server_url;
            }
            // Если переменной окружения нет, используем HTTP_SERVER
            elseif (defined('HTTP_SERVER')) {
                $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
            }
        }

        if (empty($server_url)) {
            $this->log->write('GDT Updater Auto: No server URL configured');
            return; // Нет настроенного сервера обновлений
        }

        $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
        $updated_modules = array();
        $errors = array();

        // Получаем массив модулей с включенным автообновлением
        $auto_update_modules = $this->config->get('module_gdt_updater_auto_modules') ?: array();
        
        $this->log->write('GDT Updater Auto: Auto-update enabled for modules: ' . implode(', ', $auto_update_modules));

        var_dump($auto_update_modules);

        foreach ($installed_modules as $module) {
            // Проверяем, включено ли автообновление для этого модуля
            if (!in_array($module['code'], $auto_update_modules)) {
                continue; // Пропускаем модули без автообновления
            }

            $this->log->write('GDT Updater Auto: Checking module ' . $module['code'] . ' for updates');

            try {
                // Проверяем наличие обновлений
                $update_info = $manager->checkModuleUpdate($server_url, $module, '', $api_key);
                
                if ($update_info && !isset($update_info['error'])) {
                    // Логируем начало автообновления
                    $this->log->write('GDT Updater Auto: Starting auto-update for module ' . $module['code'] . ' from version ' . $module['version'] . ' to ' . $update_info['version']);
                    
                    // Выполняем обновление через встроенный процесс OpenCart
                    $result = $manager->downloadAndInstallUpdate($server_url, $module, $update_info, '', $api_key, true);
                    
                    if ($result === true) {
                        $updated_modules[] = $module['module_name'] ?? $module['name'] ?? $module['code'];
                        $this->log->write('GDT Updater Auto: Successfully auto-updated module ' . $module['code'] . ' to version ' . $update_info['version'] . ' via OpenCart process');
                    } else {
                        $errors[] = $module['code'] . ': ' . $result;
                        $this->log->write('GDT Updater Auto Error: Failed to auto-update module ' . $module['code'] . ' - ' . $result);
                    }
                }
            } catch (Exception $e) {
                $errors[] = $module['code'] . ': ' . $e->getMessage();
                $this->log->write('GDT Updater Auto Exception: ' . $e->getMessage());
            }
        }

        // Очищаем кэш после всех обновлений
        if (!empty($updated_modules)) {
            $manager->clearCache();
            
            // Создаем уведомление об успешных автообновлениях
            $success_message = 'Автообновлены модули: ' . implode(', ', $updated_modules);
            $this->session->data['success'] = $success_message;
        }

        // Создаем уведомление об ошибках (если есть)
        if (!empty($errors)) {
            $error_message = 'Ошибки автообновления: ' . implode('; ', $errors);
            $this->session->data['warning'] = $error_message;
        }

        // Кэшируем дату последней проверки
        $this->cache->set('gdt_updater_last_auto_check', date('Y-m-d'));
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