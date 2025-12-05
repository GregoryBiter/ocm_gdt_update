<?php
/**
 * Приклад використання відрефакторених сервісів GDT Module Manager
 * 
 * Цей файл демонструє як правильно використовувати новий сервісний підхід
 * в контролері модуля gdt_updater
 */

class ControllerExtensionModuleGdtUpdaterExample extends Controller {
    
    /**
     * Приклад 1: Отримання списку встановлених модулів
     */
    public function getModulesList() {
        // Завантажуємо Manager (facade)
      
        $manager = new \Gbitstudio\Modules\Manager($this->registry);
        
        // Отримуємо всі модулі з усіх джерел (OpenCart, System, DB)
        $modules = $manager->getInstalledModules();
        
        // Результат: масив модулів з пріоритетом джерел
        // OpenCart modifications > System files > gdt_modules (deprecated)
        
        return $modules;
    }
    
    /**
     * Приклад 2: Отримання конкретного модуля за кодом
     */
    public function getSpecificModule($code) {
      
        $manager = new \Gbitstudio\Modules\Manager($this->registry);
        
        // Шукаємо модуль за кодом
        $module = $manager->getModuleByCode($code);
        
        if ($module) {
            // Модуль знайдено
            // $module['source'] показує джерело: 
            // - 'opencart_modification'
            // - 'system_file'
            // - 'gdt_modules_deprecated'
            
            return $module;
        }
        
        return null;
    }
    
    /**
     * Приклад 3: Перевірка наявності оновлень для модуля
     */
    public function checkForUpdates() {
      
        $this->load->language('extension/module/gdt_updater');
        
        $manager = new \Gbitstudio\Modules\Manager($this->registry);
        
        // Параметри
        $server_url = $this->config->get('module_gdt_updater_server_url');
        $api_key = $this->config->get('module_gdt_updater_api_key');
        
        // Отримуємо модуль
        $module = $manager->getModuleByCode('ocm_my_module');
        
        if (!$module) {
            return ['error' => 'Module not found'];
        }
        
        // Перевіряємо оновлення через UpdateService (внутрішньо)
        $update_info = $manager->checkModuleUpdate(
            $server_url,
            $module,
            'default',  // client_id (не використовується)
            $api_key
        );
        
        // Результат:
        // При наявності оновлення: array(
        //   'status' => 'update_available',
        //   'version' => '2.0.0',
        //   'download_url' => 'https://...',
        //   'changelog' => '...',
        //   ...
        // )
        // При відсутності: false
        // При помилці: array('error' => 'curl|http', ...)
        
        return $update_info;
    }
    
    /**
     * Приклад 4: Встановлення/оновлення модуля з URL
     */
    public function installOrUpdateModule() {
      
        $this->load->language('extension/module/gdt_updater');
        
        $manager = new \Gbitstudio\Modules\Manager($this->registry);
        
        // Дані для встановлення
        $module_code = 'ocm_my_module';
        $download_url = 'https://update-server.com/download/ocm_my_module.zip';
        
        // Встановлюємо через OpenCart процес (рекомендовано)
        // Внутрішньо використовує:
        // 1. UpdateService->downloadModule() - завантаження
        // 2. InstallService->installModule() - встановлення
        $result = $manager->installModuleURL(
            $module_code,
            $download_url,
            true  // use_opencart_process = true (за замовчуванням)
        );
        
        if ($result === true) {
            // Успішно встановлено
            // Очищаємо кеш
            $manager->clearCache();
            
            return ['success' => 'Module installed successfully'];
        } else {
            // Помилка встановлення
            // $result містить текст помилки
            return ['error' => $result];
        }
    }
    
    /**
     * Приклад 5: Прямий виклик InstallService (якщо вже є ZIP файл)
     */
    public function installFromZip($zip_path) {
      
        $manager = new \Gbitstudio\Modules\Manager($this->registry);
        
        // Якщо ZIP вже завантажений, можна встановити напряму
        $result = $manager->installViaOpenCartProcess(
            $zip_path,
            'ocm_my_module'  // опціонально
        );
        
        if ($result === true) {
            return ['success' => 'Module installed'];
        } else {
            return ['error' => $result];
        }
    }
    
    /**
     * Приклад 6: Використання сервісів напряму (advanced)
     * Це для випадків коли потрібна лише одна функція без Manager facade
     */
    public function advancedUsageWithServices() {
        // Варіант 1: Використання ModuleService напряму
        $moduleService = new \Gbitstudio\Modules\Services\ModuleService(
            $this->db,
            $this->log
        );
        
        $modules = $moduleService->getInstalledModules();
        
        
        // Варіант 2: Використання UpdateService напряму
        $updateService = new \Gbitstudio\Modules\Services\UpdateService(
            $this->log
        );
        
        $server_url = 'https://update-server.com';
        $module = ['code' => 'test', 'version' => '1.0.0'];
        $api_key = 'your_api_key';
        
        $update_info = $updateService->checkModuleUpdate(
            $server_url,
            $module,
            $api_key
        );
        
        
        // Варіант 3: Використання InstallService напряму
        $installService = new \Gbitstudio\Modules\Services\InstallService(
            $this->registry,
            $this->log
        );
        
        $result = $installService->installModule(
            '/path/to/module.zip',
            'module_code'
        );
        
        return [
            'modules_count' => count($modules),
            'update_available' => $update_info !== false,
            'install_result' => $result
        ];
    }
    
    /**
     * Приклад 7: Комплексний workflow оновлення модуля
     */
    public function fullUpdateWorkflow($module_code) {
      
        $manager = new \Gbitstudio\Modules\Manager($this->registry);
        
        $json = [];
        
        try {
            // Крок 1: Отримуємо інформацію про модуль
            $module = $manager->getModuleByCode($module_code);
            
            if (!$module) {
                throw new \Exception('Модуль не знайдено');
            }
            
            $json['current_version'] = $module['version'];
            
            // Крок 2: Перевіряємо наявність оновлень
            $server_url = $this->config->get('module_gdt_updater_server_url');
            $api_key = $this->config->get('module_gdt_updater_api_key');
            
            $update_info = $manager->checkModuleUpdate(
                $server_url,
                $module,
                'default',
                $api_key
            );
            
            if ($update_info === false) {
                $json['message'] = 'Оновлень немає';
                return $json;
            }
            
            if (isset($update_info['error'])) {
                throw new \Exception('Помилка перевірки оновлень: ' . json_encode($update_info));
            }
            
            // Крок 3: Є оновлення, встановлюємо
            $json['new_version'] = $update_info['version'];
            $json['changelog'] = $update_info['changelog'] ?? '';
            
            $install_result = $manager->installModuleURL(
                $module_code,
                $update_info['download_url'],
                true
            );
            
            if ($install_result === true) {
                // Крок 4: Очищаємо кеш після успішного оновлення
                $manager->clearCache();
                
                $json['success'] = 'Модуль успішно оновлено з ' . 
                                   $module['version'] . ' до ' . 
                                   $update_info['version'];
            } else {
                throw new \Exception('Помилка встановлення: ' . $install_result);
            }
            
        } catch (\Exception $e) {
            $json['error'] = $e->getMessage();
        }
        
        return $json;
    }
    
    /**
     * Приклад 8: Масове оновлення всіх модулів
     */
    public function updateAllModules() {
      
        $manager = new \Gbitstudio\Modules\Manager($this->registry);
        
        $server_url = $this->config->get('module_gdt_updater_server_url');
        $api_key = $this->config->get('module_gdt_updater_api_key');
        
        // Отримуємо всі модулі
        $modules = $manager->getInstalledModules();
        
        $results = [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($modules as $module) {
            $results['checked']++;
            
            // Перевіряємо оновлення
            $update_info = $manager->checkModuleUpdate(
                $server_url,
                $module,
                'default',
                $api_key
            );
            
            if ($update_info && isset($update_info['status']) && 
                $update_info['status'] === 'update_available') {
                
                // Є оновлення - встановлюємо
                $install_result = $manager->installModuleURL(
                    $module['code'],
                    $update_info['download_url'],
                    true
                );
                
                if ($install_result === true) {
                    $results['updated']++;
                    $results['details'][] = [
                        'code' => $module['code'],
                        'from' => $module['version'],
                        'to' => $update_info['version'],
                        'status' => 'success'
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'code' => $module['code'],
                        'error' => $install_result,
                        'status' => 'failed'
                    ];
                }
            }
        }
        
        // Очищаємо кеш після всіх оновлень
        if ($results['updated'] > 0) {
            $manager->clearCache();
        }
        
        return $results;
    }
}
