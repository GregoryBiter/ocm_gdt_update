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
     * Мігрує дані з старої таблиці gdt_modules та старої структури папок в нову файлову систему
     * 
     * @return array ['migrated' => int, 'errors' => array]
     */
    public function migrateToFiles() {
        $result = ['migrated' => 0, 'errors' => []];
        $index_service = $this->getServiceFactory()->getModuleIndexService();
        $modules_dir = DIR_SYSTEM . 'modules/';

        if (!is_dir($modules_dir)) {
            mkdir($modules_dir, 0755, true);
        }

        try {
            // 1. Міграція з БД (gdt_modules)
            $table_check = $this->db->query("SHOW TABLES LIKE 'gdt_modules'");
            if ($table_check->num_rows > 0) {
                $modules_result = $this->db->query("SELECT * FROM `gdt_modules` track_data");
                foreach ($modules_result->rows as $row) {
                    $module_data = json_decode($row['data'], true);
                    if ($module_data && isset($module_data['code'])) {
                        $code = $module_data['code'];
                        $version = $module_data['version'] ?? '1.0.0';
                        
                        $json_target = $modules_dir . $code . '.json';
                        if (file_put_contents($json_target, json_encode($module_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                            $index_service->updateIndex($code, $version);
                            $result['migrated']++;
                        }
                    }
                }
                // Видаляємо таблицю після переносу
                $this->db->query("DROP TABLE IF EXISTS `gdt_modules` track_data");
            }

            // 2. Міграція зі старої структури папок (DIR_SYSTEM/modules/{code}/opencart-module.json)
            if (is_dir($modules_dir)) {
                $items = scandir($modules_dir);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..' || substr($item, -5) === '.json') {
                        continue;
                    }

                    $old_folder = $modules_dir . $item;
                    $old_json = $old_folder . '/opencart-module.json';

                    if (is_dir($old_folder) && file_exists($old_json)) {
                        $content = file_get_contents($old_json);
                        $module_data = json_decode($content, true);
                        
                        if ($module_data && isset($module_data['code'])) {
                            $code = $module_data['code'];
                            $version = $module_data['version'] ?? '1.0.0';
                            
                            $new_json_path = $modules_dir . $code . '.json';
                            if (file_put_contents($new_json_path, $content)) {
                                $index_service->updateIndex($code, $version);
                                
                                // Видаляємо стару папку з файлом
                                @unlink($old_json);
                                @rmdir($old_folder);
                                
                                $result['migrated']++;
                            }
                        }
                    }
                }
            }
            
            // 3. Виправляємо помилку з папкою 'module' (якщо вона була створена попередньою кривою міграцією)
            $wrong_dir = DIR_SYSTEM . 'module/';
            if (is_dir($wrong_dir)) {
                $this->recursiveRemoveDir($wrong_dir);
            }

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            LoggerService::error('Migration failed: ' . $e->getMessage(), 'Model');
        }

        return $result;
    }

    /**
     * Рекурсивне видалення папки
     */
    private function recursiveRemoveDir($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemoveDir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    /**
     * @deprecated Замінено на migrateToFiles()
     */
    public function migrateFromDatabaseToJson() {
        return $this->migrateToFiles();
    }
    
    /**
     * @deprecated Використовуйте тільки для міграції
     * @return array
     */
    public function getModulesFromOpenCartModifications() {
        try {
            $table_exists_query = "SHOW TABLES LIKE '" . DB_PREFIX . "modification'";
            $table_result = $this->db->query($table_exists_query);
            
            if ($table_result->num_rows > 0) {
                $query = "SELECT * FROM `" . DB_PREFIX . "modification` WHERE `status` = 1";
                $result = $this->db->query($query);
                
                $modules = [];
                if ($result->num_rows > 0) {
                    foreach ($result->rows as $row) {
                        $modules[] = [
                            'code' => $row['code'],
                            'name' => $row['name'],
                            'version' => isset($row['version']) ? $row['version'] : '1.0.0',
                            'author' => isset($row['author']) ? $row['author'] : 'Unknown',
                            'link' => isset($row['link']) ? $row['link'] : '',
                            'description' => isset($row['xml']) ? $this->extractDescription($row['xml']) : '',
                            'source' => 'opencart_modification',
                            'modification_id' => $row['modification_id'],
                            'date_added' => isset($row['date_added']) ? $row['date_added'] : '',
                        ];
                    }
                }
                return $modules;
            }
        } catch (Exception $e) {
            LoggerService::write('GDT Module Manager: Error getting OpenCart modifications: ' . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * @deprecated Використовуйте тільки для міграції
     * @param string $code
     * @return array|null
     */
    public function getModuleFromOpenCartModifications($code) {
        try {
            $table_exists_query = "SHOW TABLES LIKE '" . DB_PREFIX . "modification'";
            $table_result = $this->db->query($table_exists_query);
            
            if ($table_result->num_rows > 0) {
                $query = "SELECT * FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $this->db->escape($code) . "' AND `status` = 1 LIMIT 1";
                $result = $this->db->query($query);
                
                if ($result->num_rows > 0) {
                    $row = $result->row;
                    return [
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'version' => isset($row['version']) ? $row['version'] : '1.0.0',
                        'author' => isset($row['author']) ? $row['author'] : 'Unknown',
                        'link' => isset($row['link']) ? $row['link'] : '',
                        'description' => isset($row['xml']) ? $this->extractDescription($row['xml']) : '',
                        'source' => 'opencart_modification',
                        'modification_id' => $row['modification_id'],
                        'date_added' => isset($row['date_added']) ? $row['date_added'] : '',
                    ];
                }
            }
        } catch (Exception $e) {
            LoggerService::write('GDT Module Manager: Error getting OpenCart modification by code: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Отримує модулі з бази даних (DEPRECATED)
     * 
     * @return array
     */
    public function getModulesFromDatabase() {
        $modules = [];
        
        try {
            $query = "SELECT * FROM `gdt_modules`";
            $result = $this->db->query($query);
            
            if ($result->num_rows > 0) {
                foreach ($result->rows as $row) {
                    $module_data = json_decode($row['data'], true);
                    if ($module_data && isset($module_data['code'])) {
                        $modules[] = $module_data;
                    }
                }
            }
        } catch (Exception $e) {
            LoggerService::write('GDT Module Manager: gdt_modules table not available (deprecated): ' . $e->getMessage());
        }
        
        return $modules;
    }
    
    /**
     * Отримує модуль з бази даних за кодом (DEPRECATED)
     * 
     * @param string $code
     * @return array|null
     */
    public function getModuleFromDatabase($code) {
        try {
            $query = "SELECT * FROM `gdt_modules` WHERE `module_code` = '" . $this->db->escape($code) . "' LIMIT 1";
            $result = $this->db->query($query);
            
            if ($result->num_rows > 0) {
                $module_data = json_decode($result->row['data'], true);
                if ($module_data) {
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