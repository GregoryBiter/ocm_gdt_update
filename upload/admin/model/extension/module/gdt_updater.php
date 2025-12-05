<?php
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
     * Мігрує дані з старої таблиці gdt_modules в opencart-module.json файли
     * 
     * @return array ['migrated' => int, 'errors' => array]
     */
    public function migrateFromDatabaseToJson() {
        $result = ['migrated' => 0, 'errors' => []];
        
        try {
            // Перевіряємо чи існує таблиця
            $table_check = $this->db->query("SHOW TABLES LIKE 'gdt_modules'");
            if ($table_check->num_rows == 0) {
                $this->log->write('GDT Module Manager: gdt_modules table does not exist, migration not needed');
                return $result;
            }
            
            // Отримуємо всі модулі з таблиці
            $query = "SELECT * FROM `gdt_modules`";
            $modules_result = $this->db->query($query);
            
            if ($modules_result->num_rows == 0) {
                $this->log->write('GDT Module Manager: No modules to migrate');
                // Видаляємо порожню таблицю
                $this->db->query("DROP TABLE IF EXISTS `gdt_modules`");
                return $result;
            }
            
            foreach ($modules_result->rows as $row) {
                try {
                    $module_data = json_decode($row['data'], true);
                    if (!$module_data || !isset($module_data['code'])) {
                        $result['errors'][] = 'Invalid module data in row ' . $row['id'];
                        continue;
                    }
                    
                    $code = $module_data['code'];
                    $module_dir = DIR_SYSTEM . 'module/' . $code . '/';
                    
                    // Створюємо папку якщо не існує
                    if (!is_dir($module_dir)) {
                        mkdir($module_dir, 0755, true);
                    }
                    
                    // Готуємо дані для JSON
                    $json_data = [
                        'code' => $code,
                        'module_name' => $module_data['module_name'] ?? $module_data['name'] ?? $code,
                        'version' => $module_data['version'] ?? '1.0.0',
                        'creator_name' => $module_data['creator_name'] ?? $module_data['author'] ?? 'Unknown',
                        'creator_email' => $module_data['creator_email'] ?? '',
                        'description' => $module_data['description'] ?? '',
                        'controller' => $module_data['controller'] ?? '',
                        'provider' => $module_data['provider'] ?? '',
                        'author_url' => $module_data['author_url'] ?? $module_data['link'] ?? '',
                        'files' => $module_data['files'] ?? []
                    ];
                    
                    // Зберігаємо в JSON файл
                    $json_file = $module_dir . 'opencart-module.json';
                    $json_content = json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    
                    if (file_put_contents($json_file, $json_content)) {
                        $result['migrated']++;
                        $this->log->write('GDT Module Manager: Migrated module ' . $code . ' to ' . $json_file);
                    } else {
                        $result['errors'][] = 'Failed to write JSON file for module ' . $code;
                    }
                } catch (Exception $e) {
                    $result['errors'][] = 'Error migrating module: ' . $e->getMessage();
                }
            }
            
            // Після успішної міграції видаляємо таблицю
            if (empty($result['errors'])) {
                $this->db->query("DROP TABLE IF EXISTS `gdt_modules`");
                $this->log->write('GDT Module Manager: Dropped gdt_modules table after successful migration');
            } else {
                $this->log->write('GDT Module Manager: Migration completed with errors, table not dropped');
            }
            
        } catch (Exception $e) {
            $result['errors'][] = 'Migration error: ' . $e->getMessage();
            $this->log->write('GDT Module Manager: Migration error: ' . $e->getMessage());
        }
        
        return $result;
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
            $this->log->write('GDT Module Manager: Error getting OpenCart modifications: ' . $e->getMessage());
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
            $this->log->write('GDT Module Manager: Error getting OpenCart modification by code: ' . $e->getMessage());
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
            $this->log->write('GDT Module Manager: gdt_modules table not available (deprecated): ' . $e->getMessage());
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
            $this->log->write('GDT Module Manager: Error getting module from database: ' . $e->getMessage());
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