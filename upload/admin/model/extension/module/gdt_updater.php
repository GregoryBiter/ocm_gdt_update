<?php
use \Gbitstudio\Modules\Services\LoggerService;
class ModelExtensionModuleGdtUpdater extends Model {
    
    /**
     * Отримує фабрику сервісів
     * 
     * @return \Gbitstudio\Modules\ServiceFactory
     */
    private function getServiceFactory() {
        if (!$this->registry->has('gb_modules')) {
            $this->registry->set('gb_modules', new \Gbitstudio\Modules\ServiceFactory($this->registry));
        }
        
        return $this->registry->get('gb_modules');
    }
    
    /**
     * Створює необхідні таблиці в базі даних
     */
    public function createTables() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "gdt_modules` (
                `module_id` INT(11) NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(64) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `version` VARCHAR(32) NOT NULL,
                `data` TEXT NOT NULL,
                `paths` TEXT NOT NULL,
                `date_added` DATETIME NOT NULL,
                PRIMARY KEY (`module_id`),
                UNIQUE KEY `code` (`code`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");
    }

    /**
     * Мігрує дані з JSON файлів в нову таблицю gdt_modules
     * 
     * @return array ['migrated' => int, 'errors' => array]
     */
    public function migrateFromJsonToDatabase() {
        $result = ['migrated' => 0, 'errors' => []];
        
        try {
            // Переконуємось що таблиця існує
            $this->createTables();
            
            $modules_dir = constant('DIR_SYSTEM') . 'modules/';
            
            if (!is_dir($modules_dir)) {
                LoggerService::info('system/modules/ directory does not exist, migration not needed', 'Model');
                return $result;
            }
            
            $dirs = scandir($modules_dir);
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                
                $json_file = $modules_dir . $dir . '/opencart-module.json';
                if (file_exists($json_file)) {
                    try {
                        $json_content = file_get_contents($json_file);
                        $module_data = json_decode($json_content, true);
                        
                        if (!$module_data || !isset($module_data['code'])) {
                            $result['errors'][] = 'Invalid JSON in ' . $json_file;
                            continue;
                        }
                        
                        $code = $module_data['code'];
                        $name = $module_data['module_name'] ?? $module_data['name'] ?? $code;
                        $version = $module_data['version'] ?? '1.0.0';
                        $paths = $module_data['files'] ?? []; // У старій структурі файли були в 'files'
                        
                        // Вставляємо або оновлюємо в базі
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "gdt_modules` SET 
                            `code` = '" . $this->db->escape($code) . "',
                            `name` = '" . $this->db->escape($name) . "',
                            `version` = '" . $this->db->escape($version) . "',
                            `data` = '" . $this->db->escape(json_encode($module_data, JSON_UNESCAPED_UNICODE)) . "',
                            `paths` = '" . $this->db->escape(json_encode($paths, JSON_UNESCAPED_UNICODE)) . "',
                            `date_added` = NOW()
                            ON DUPLICATE KEY UPDATE 
                            `name` = '" . $this->db->escape($name) . "',
                            `version` = '" . $this->db->escape($version) . "',
                            `data` = '" . $this->db->escape(json_encode($module_data, JSON_UNESCAPED_UNICODE)) . "',
                            `paths` = '" . $this->db->escape(json_encode($paths, JSON_UNESCAPED_UNICODE)) . "'");
                        
                        $result['migrated']++;
                        LoggerService::write('GDT Module Manager: Migrated module ' . $code . ' from JSON to database');
                        
                        // Видаляємо JSON файл після успішної міграції (за бажанням, але краще залишити як бекап або видалити якщо точно не треба)
                        // @unlink($json_file);
                        
                    } catch (Exception $e) {
                        $result['errors'][] = 'Error migrating module ' . $dir . ': ' . $e->getMessage();
                    }
                }
            }
            
            // Якщо все пройшло успішно, можна видалити стару таблицю без префікса (якщо вона була)
            $this->db->query("DROP TABLE IF EXISTS `gdt_modules`");
            
        } catch (Exception $e) {
            $result['errors'][] = 'Migration error: ' . $e->getMessage();
            LoggerService::write('GDT Module Manager: Migration error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Отримує модулі з бази даних
     * 
     * @return array
     */
    public function getModulesFromDatabase() {
        $modules = [];
        
        try {
            $query = "SELECT * FROM `" . DB_PREFIX . "gdt_modules` ORDER BY `date_added` DESC";
            $result = $this->db->query($query);
            
            if ($result->num_rows > 0) {
                foreach ($result->rows as $row) {
                    $module_data = json_decode($row['data'], true);
                    if ($module_data) {
                        $module_data['code'] = $row['code'];
                        $module_data['paths'] = json_decode($row['paths'], true);
                        $modules[] = $module_data;
                    }
                }
            }
        } catch (Exception $e) {
            LoggerService::write('GDT Module Manager: Error getting modules from database: ' . $e->getMessage());
        }
        
        return $modules;
    }
    
    /**
     * Отримує модуль з бази даних за кодом
     * 
     * @param string $code
     * @return array|null
     */
    public function getModuleFromDatabase($code) {
        try {
            $query = "SELECT * FROM `" . DB_PREFIX . "gdt_modules` WHERE `code` = '" . $this->db->escape($code) . "' LIMIT 1";
            $result = $this->db->query($query);
            
            if ($result->num_rows > 0) {
                $row = $result->row;
                $module_data = json_decode($row['data'], true);
                if ($module_data) {
                    $module_data['code'] = $row['code'];
                    $module_data['paths'] = json_decode($row['paths'], true);
                    return $module_data;
                }
            }
        } catch (Exception $e) {
            LoggerService::write('GDT Module Manager: Error getting module from database: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Витягує опис з XML
     * 
     * @param string $xml
     * @return string
     */
    private function extractDescription($xml) {
        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->loadXml($xml);
            $description = $dom->getElementsByTagName('description')->item(0);
            return $description ? $description->nodeValue : '';
        } catch (Exception $e) {
            return '';
        }
    }
    
    /**
     * Автоматическая проверка и запуск обновления модулей при загрузке dashboard
     */
    public function autoCheckUpdate() {
        $autoUpdateService = $this->getServiceFactory()->getAutoUpdateService();
        $result = $autoUpdateService->autoCheckAndUpdate();
        
        if (!empty($result['updated'])) {
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

        $autoUpdateService = $this->getServiceFactory()->getAutoUpdateService();
        return $autoUpdateService->checkForUpdate($module_code);
    }

    /**
     * Выполнение обновления для конкретного модуля
     */
    public function performUpdate($module_code, $update_info = null) {
        if (!$module_code) {
            return 'Не указан код модуля';
        }

        $autoUpdateService = $this->getServiceFactory()->getAutoUpdateService();
        return $autoUpdateService->performModuleUpdate($module_code, $update_info);
    }
}