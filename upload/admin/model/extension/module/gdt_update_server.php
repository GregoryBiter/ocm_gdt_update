<?php
class ModelExtensionModuleGdtUpdateServer extends Model {
    
    /**
     * Создание таблицы для хранения модулей
     */
    public function createTable() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "gdt_server_modules` (
            `module_id` int(11) NOT NULL AUTO_INCREMENT,
            `code` varchar(64) NOT NULL,
            `name` varchar(255) NOT NULL,
            `description` text,
            `version` varchar(32) NOT NULL,
            `author` varchar(255) DEFAULT NULL,
            `author_url` varchar(255) DEFAULT NULL,
            `category` varchar(64) DEFAULT 'module',
            `opencart_version` varchar(32) DEFAULT NULL,
            `dependencies` text DEFAULT NULL,
            `archive_structure` enum('opencart', 'direct') DEFAULT 'opencart',
            `file_path` varchar(500) NOT NULL,
            `file_size` int(11) DEFAULT 0,
            `file_hash` varchar(64) DEFAULT NULL,
            `image` varchar(255) DEFAULT NULL,
            `demo_url` varchar(255) DEFAULT NULL,
            `documentation_url` varchar(255) DEFAULT NULL,
            `support_url` varchar(255) DEFAULT NULL,
            `price` decimal(15,4) DEFAULT 0.0000,
            `downloads` int(11) DEFAULT 0,
            `rating` decimal(3,2) DEFAULT 0.00,
            `reviews` int(11) DEFAULT 0,
            `status` tinyint(1) DEFAULT 1,
            `featured` tinyint(1) DEFAULT 0,
            `sort_order` int(3) DEFAULT 0,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`module_id`),
            UNIQUE KEY `code` (`code`),
            KEY `status` (`status`),
            KEY `featured` (`featured`),
            KEY `category` (`category`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
    }
    
    /**
     * Удаление таблицы
     */
    public function dropTable() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "gdt_server_modules`");
    }
    
    /**
     * Добавить модуль
     */
    public function addModule($data) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "gdt_server_modules SET 
            code = '" . $this->db->escape($data['code']) . "',
            name = '" . $this->db->escape($data['name']) . "',
            description = '" . $this->db->escape(isset($data['description']) ? $data['description'] : '') . "',
            version = '" . $this->db->escape($data['version']) . "',
            author = '" . $this->db->escape(isset($data['author']) ? $data['author'] : '') . "',
            author_url = '" . $this->db->escape(isset($data['author_url']) ? $data['author_url'] : '') . "',
            category = '" . $this->db->escape(isset($data['category']) ? $data['category'] : 'module') . "',
            opencart_version = '" . $this->db->escape(isset($data['opencart_version']) ? $data['opencart_version'] : '') . "',
            dependencies = '" . $this->db->escape(isset($data['dependencies']) ? json_encode($data['dependencies']) : '') . "',
            archive_structure = '" . $this->db->escape(isset($data['archive_structure']) ? $data['archive_structure'] : 'opencart') . "',
            file_path = '" . $this->db->escape($data['file_path']) . "',
            file_size = '" . (int)(isset($data['file_size']) ? $data['file_size'] : 0) . "',
            file_hash = '" . $this->db->escape(isset($data['file_hash']) ? $data['file_hash'] : '') . "',
            image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "',
            demo_url = '" . $this->db->escape(isset($data['demo_url']) ? $data['demo_url'] : '') . "',
            documentation_url = '" . $this->db->escape(isset($data['documentation_url']) ? $data['documentation_url'] : '') . "',
            support_url = '" . $this->db->escape(isset($data['support_url']) ? $data['support_url'] : '') . "',
            price = '" . (float)(isset($data['price']) ? $data['price'] : 0) . "',
            status = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "',
            featured = '" . (int)(isset($data['featured']) ? $data['featured'] : 0) . "',
            sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "',
            date_added = NOW(),
            date_modified = NOW()");
            
        return $this->db->getLastId();
    }
    
    /**
     * Обновить модуль
     */
    public function editModule($module_id, $data) {
        $this->db->query("UPDATE " . DB_PREFIX . "gdt_server_modules SET 
            name = '" . $this->db->escape($data['name']) . "',
            description = '" . $this->db->escape(isset($data['description']) ? $data['description'] : '') . "',
            version = '" . $this->db->escape($data['version']) . "',
            author = '" . $this->db->escape(isset($data['author']) ? $data['author'] : '') . "',
            author_url = '" . $this->db->escape(isset($data['author_url']) ? $data['author_url'] : '') . "',
            category = '" . $this->db->escape(isset($data['category']) ? $data['category'] : 'module') . "',
            opencart_version = '" . $this->db->escape(isset($data['opencart_version']) ? $data['opencart_version'] : '') . "',
            dependencies = '" . $this->db->escape(isset($data['dependencies']) ? json_encode($data['dependencies']) : '') . "',
            archive_structure = '" . $this->db->escape(isset($data['archive_structure']) ? $data['archive_structure'] : 'opencart') . "',
            file_path = '" . $this->db->escape($data['file_path']) . "',
            file_size = '" . (int)(isset($data['file_size']) ? $data['file_size'] : 0) . "',
            file_hash = '" . $this->db->escape(isset($data['file_hash']) ? $data['file_hash'] : '') . "',
            image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "',
            demo_url = '" . $this->db->escape(isset($data['demo_url']) ? $data['demo_url'] : '') . "',
            documentation_url = '" . $this->db->escape(isset($data['documentation_url']) ? $data['documentation_url'] : '') . "',
            support_url = '" . $this->db->escape(isset($data['support_url']) ? $data['support_url'] : '') . "',
            price = '" . (float)(isset($data['price']) ? $data['price'] : 0) . "',
            status = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "',
            featured = '" . (int)(isset($data['featured']) ? $data['featured'] : 0) . "',
            sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "',
            date_modified = NOW()
        WHERE module_id = '" . (int)$module_id . "'");
    }
    
    /**
     * Удалить модуль
     */
    public function deleteModule($module_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "gdt_server_modules WHERE module_id = '" . (int)$module_id . "'");
    }
    
    /**
     * Удалить модуль по коду
     */
    public function deleteModuleByCode($code) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "gdt_server_modules WHERE code = '" . $this->db->escape($code) . "'");
    }
    
    /**
     * Получить модуль по ID
     */
    public function getModule($module_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "gdt_server_modules WHERE module_id = '" . (int)$module_id . "'");
        
        if ($query->num_rows) {
            $module = $query->row;
            
            // Преобразуем JSON поля
            if ($module['dependencies']) {
                $module['dependencies'] = json_decode($module['dependencies'], true);
            }
            
            return $module;
        }
        
        return false;
    }
    
    /**
     * Получить модуль по коду
     */
    public function getModuleByCode($code) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "gdt_server_modules WHERE code = '" . $this->db->escape($code) . "'");
        
        if ($query->num_rows) {
            $module = $query->row;
            
            // Преобразуем JSON поля
            if ($module['dependencies']) {
                $module['dependencies'] = json_decode($module['dependencies'], true);
            }
            
            return $module;
        }
        
        return false;
    }
    
    /**
     * Получить список модулей
     */
    public function getModules($data = array()) {
        $sql = "SELECT * FROM " . DB_PREFIX . "gdt_server_modules";
        
        $where = array();
        
        if (isset($data['filter_name']) && $data['filter_name']) {
            $where[] = "name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }
        
        if (isset($data['filter_code']) && $data['filter_code']) {
            $where[] = "code LIKE '%" . $this->db->escape($data['filter_code']) . "%'";
        }
        
        if (isset($data['filter_author']) && $data['filter_author']) {
            $where[] = "author LIKE '%" . $this->db->escape($data['filter_author']) . "%'";
        }
        
        if (isset($data['filter_category']) && $data['filter_category']) {
            $where[] = "category = '" . $this->db->escape($data['filter_category']) . "'";
        }
        
        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $where[] = "status = '" . (int)$data['filter_status'] . "'";
        }
        
        if (isset($data['filter_featured']) && $data['filter_featured'] !== '') {
            $where[] = "featured = '" . (int)$data['filter_featured'] . "'";
        }
        
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sort_data = array(
            'name',
            'code',
            'version',
            'author',
            'category',
            'date_added',
            'date_modified',
            'sort_order'
        );
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY sort_order, name";
        }
        
        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }
        
        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }
            
            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }
            
            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }
        
        $query = $this->db->query($sql);
        
        $modules = array();
        
        foreach ($query->rows as $module) {
            // Преобразуем JSON поля
            if ($module['dependencies']) {
                $module['dependencies'] = json_decode($module['dependencies'], true);
            }
            
            $modules[] = $module;
        }
        
        return $modules;
    }
    
    /**
     * Получить общее количество модулей
     */
    public function getTotalModules($data = array()) {
        $sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "gdt_server_modules";
        
        $where = array();
        
        if (isset($data['filter_name']) && $data['filter_name']) {
            $where[] = "name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }
        
        if (isset($data['filter_code']) && $data['filter_code']) {
            $where[] = "code LIKE '%" . $this->db->escape($data['filter_code']) . "%'";
        }
        
        if (isset($data['filter_author']) && $data['filter_author']) {
            $where[] = "author LIKE '%" . $this->db->escape($data['filter_author']) . "%'";
        }
        
        if (isset($data['filter_category']) && $data['filter_category']) {
            $where[] = "category = '" . $this->db->escape($data['filter_category']) . "'";
        }
        
        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $where[] = "status = '" . (int)$data['filter_status'] . "'";
        }
        
        if (isset($data['filter_featured']) && $data['filter_featured'] !== '') {
            $where[] = "featured = '" . (int)$data['filter_featured'] . "'";
        }
        
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $query = $this->db->query($sql);
        
        return $query->row['total'];
    }
    
    /**
     * Увеличить счетчик скачиваний
     */
    public function incrementDownloads($module_id) {
        $this->db->query("UPDATE " . DB_PREFIX . "gdt_server_modules SET downloads = downloads + 1 WHERE module_id = '" . (int)$module_id . "'");
    }
    
    /**
     * Увеличить счетчик скачиваний по коду
     */
    public function incrementDownloadsByCode($code) {
        $this->db->query("UPDATE " . DB_PREFIX . "gdt_server_modules SET downloads = downloads + 1 WHERE code = '" . $this->db->escape($code) . "'");
    }
    
    /**
     * Получить категории модулей
     */
    public function getCategories() {
        $query = $this->db->query("SELECT DISTINCT category FROM " . DB_PREFIX . "gdt_server_modules WHERE status = 1 ORDER BY category");
        
        return $query->rows;
    }
    
    /**
     * Получение рекомендуемых модулей для установки
     */
    public function getFeaturedModules($limit = 20) {
        // Получаем список установленных модулей из модуля updater
        $installed_codes = array();
        
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "gdt_server_modules WHERE status = 1 AND featured = 1 ORDER BY sort_order, name LIMIT " . (int)$limit);
        
        $modules = array();
        
        foreach ($query->rows as $module) {
            $module['is_installed'] = in_array($module['code'], $installed_codes);
            $module['download_url'] = $this->getDownloadUrl($module['code']);
            $module['size'] = $this->formatFileSize($module['file_size']);
            
            if ($module['dependencies']) {
                $module['dependencies'] = json_decode($module['dependencies'], true);
            }
            
            $modules[] = $module;
        }
        
        return $modules;
    }
    
    /**
     * Получение популярных модулей для установки
     */
    public function getPopularModules($limit = 20) {
        // Загружаем библиотеку updater для получения списка установленных модулей
        require_once(DIR_SYSTEM . 'library/gbitstudio/updater/service/updater.php');
        $updater = new \GbitStudio\Updater\Service\Updater($this->registry);
        $installed_modules = $updater->getInstalledModules();
        $installed_codes = array_column($installed_modules, 'code');
        
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "gdt_server_modules WHERE status = 1 ORDER BY downloads DESC, rating DESC, name LIMIT " . (int)$limit);
        
        $modules = array();
        
        foreach ($query->rows as $module) {
            $module['is_installed'] = in_array($module['code'], $installed_codes);
            $module['download_url'] = $this->getDownloadUrl($module['code']);
            $module['size'] = $this->formatFileSize($module['file_size']);
            
            if ($module['dependencies']) {
                $module['dependencies'] = json_decode($module['dependencies'], true);
            }
            
            $modules[] = $module;
        }
        
        return $modules;
    }
    
    /**
     * Получение новых модулей для установки
     */
    public function getNewestModules($limit = 20) {
        $installed_modules = $this->getInstalledModules();
        $installed_codes = array_column($installed_modules, 'code');
        
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "gdt_server_modules WHERE status = 1 ORDER BY date_added DESC, name LIMIT " . (int)$limit);
        
        $modules = array();
        
        foreach ($query->rows as $module) {
            $module['is_installed'] = in_array($module['code'], $installed_codes);
            $module['download_url'] = $this->getDownloadUrl($module['code']);
            $module['size'] = $this->formatFileSize($module['file_size']);
            
            if ($module['dependencies']) {
                $module['dependencies'] = json_decode($module['dependencies'], true);
            }
            
            $modules[] = $module;
        }
        
        return $modules;
    }
    
    /**
     * Поиск модулей
     */
    public function searchModules($query, $category = '', $sort = 'relevance', $price = '', $limit = 50) {
        $installed_modules = $this->getInstalledModules();
        $installed_codes = array_column($installed_modules, 'code');
        
        $sql = "SELECT * FROM " . DB_PREFIX . "gdt_server_modules WHERE status = 1";
        
        // Поиск по названию и описанию
        if (!empty($query)) {
            $sql .= " AND (name LIKE '%" . $this->db->escape($query) . "%' OR description LIKE '%" . $this->db->escape($query) . "%')";
        }
        
        // Фильтр по категории
        if (!empty($category)) {
            $sql .= " AND category = '" . $this->db->escape($category) . "'";
        }
        
        // Фильтр по цене
        if ($price === 'free') {
            $sql .= " AND price = 0";
        } elseif ($price === 'paid') {
            $sql .= " AND price > 0";
        }
        
        // Сортировка
        switch ($sort) {
            case 'popularity':
                $sql .= " ORDER BY downloads DESC, rating DESC";
                break;
            case 'rating':
                $sql .= " ORDER BY rating DESC, reviews DESC";
                break;
            case 'date':
                $sql .= " ORDER BY date_added DESC";
                break;
            case 'name':
                $sql .= " ORDER BY name ASC";
                break;
            default: // relevance
                $sql .= " ORDER BY featured DESC, downloads DESC, rating DESC";
                break;
        }
        
        $sql .= " LIMIT " . (int)$limit;
        
        $query_result = $this->db->query($sql);
        
        $modules = array();
        
        foreach ($query_result->rows as $module) {
            $module['is_installed'] = in_array($module['code'], $installed_codes);
            $module['download_url'] = $this->getDownloadUrl($module['code']);
            $module['size'] = $this->formatFileSize($module['file_size']);
            
            if ($module['dependencies']) {
                $module['dependencies'] = json_decode($module['dependencies'], true);
            }
            
            $modules[] = $module;
        }
        
        return $modules;
    }
    
    /**
     * Установка модуля
     */
    public function installModule($module_code, $download_url) {
        try {
            // Проверяем, не установлен ли уже модуль
            $installed_modules = $this->getInstalledModules();
            foreach ($installed_modules as $installed) {
                if ($installed['code'] === $module_code) {
                    return array(
                        'success' => false,
                        'error' => 'Модуль уже установлен'
                    );
                }
            }
            
            // Получаем информацию о модуле
            $module_info = $this->getModuleByCode($module_code);
            if (!$module_info) {
                return array(
                    'success' => false,
                    'error' => 'Модуль не найден на сервере'
                );
            }
            
            // Скачиваем модуль
            $temp_file = $this->downloadModule($download_url);
            if (!$temp_file) {
                return array(
                    'success' => false,
                    'error' => 'Не удалось скачать модуль'
                );
            }
            
            // Извлекаем и устанавливаем модуль
            $install_result = $this->extractAndInstallModule($temp_file, $module_code);
            
            // Удаляем временный файл
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            if ($install_result['success']) {
                // Увеличиваем счетчик загрузок
                $this->db->query("UPDATE " . DB_PREFIX . "gdt_server_modules SET downloads = downloads + 1 WHERE code = '" . $this->db->escape($module_code) . "'");
                
                return array(
                    'success' => true,
                    'message' => 'Модуль успешно установлен'
                );
            } else {
                return $install_result;
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Ошибка установки: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Получение URL для скачивания модуля
     */
    private function getDownloadUrl($module_code) {
        // Это должен быть реальный URL вашего сервера обновлений
        $server_url = $this->config->get('module_gdt_updater_server');
        if (empty($server_url)) {
            $server_url = 'https://your-server.com'; // замените на ваш сервер
        }
        
        return rtrim($server_url, '/') . '/download/' . $module_code;
    }
    
    /**
     * Скачивание модуля
     */
    private function downloadModule($download_url) {
        $temp_file = tempnam(sys_get_temp_dir(), 'gdt_module_');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $download_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $data !== false) {
            file_put_contents($temp_file, $data);
            return $temp_file;
        }
        
        return false;
    }
    
    /**
     * Извлечение и установка модуля
     */
    private function extractAndInstallModule($zip_file, $module_code) {
        if (!extension_loaded('zip')) {
            return array(
                'success' => false,
                'error' => 'PHP расширение ZIP не установлено'
            );
        }
        
        $zip = new ZipArchive;
        if ($zip->open($zip_file) !== TRUE) {
            return array(
                'success' => false,
                'error' => 'Не удалось открыть ZIP архив'
            );
        }
        
        // Создаем временную папку для извлечения
        $temp_dir = sys_get_temp_dir() . '/gdt_module_' . $module_code . '_' . time();
        mkdir($temp_dir, 0755, true);
        
        // Извлекаем архив
        if (!$zip->extractTo($temp_dir)) {
            $zip->close();
            $this->removeDirectory($temp_dir);
            return array(
                'success' => false,
                'error' => 'Не удалось извлечь архив'
            );
        }
        
        $zip->close();
        
        // Копируем файлы в нужные места
        $copy_result = $this->copyModuleFiles($temp_dir, $module_code);
        
        // Удаляем временную папку
        $this->removeDirectory($temp_dir);
        
        return $copy_result;
    }
    
    /**
     * Копирование файлов модуля
     */
    private function copyModuleFiles($source_dir, $module_code) {
        $opencart_root = DIR_APPLICATION . '../';
        
        // Ищем папку upload в архиве
        $upload_dir = $source_dir . '/upload';
        if (!is_dir($upload_dir)) {
            // Возможно структура другая, ищем прямо в корне
            $upload_dir = $source_dir;
        }
        
        try {
            // Копируем основные файлы модуля
            $this->copyDirectory($upload_dir, $opencart_root);
            
            // Проверяем наличие opencart-module.json в корне архива
            $module_config_source = $source_dir . '/opencart-module.json';
            if (file_exists($module_config_source)) {
                // Создаем директорию для модуля в system/modules
                $module_dir = $opencart_root . 'system/modules/' . $module_code;
                if (!is_dir($module_dir)) {
                    mkdir($module_dir, 0755, true);
                }
                
                // Копируем файл конфигурации
                $module_config_dest = $module_dir . '/opencart-module.json';
                if (copy($module_config_source, $module_config_dest)) {
                    // Логируем успешное копирование конфигурации
                    error_log('GDT Module Installer: Configuration file copied for module ' . $module_code);
                } else {
                    // Логируем ошибку копирования конфигурации
                    error_log('GDT Module Installer: Failed to copy configuration file for module ' . $module_code);
                }
            } else {
                // Логируем отсутствие файла конфигурации
                error_log('GDT Module Installer: No opencart-module.json found for module ' . $module_code);
            }
            
            return array(
                'success' => true,
                'message' => 'Файлы модуля успешно скопированы'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Ошибка копирования файлов: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Рекурсивное копирование директории
     */
    private function copyDirectory($src, $dst) {
        if (!is_dir($src)) {
            return false;
        }
        
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                $target_dir = dirname($target);
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                copy($item, $target);
            }
        }
        
        return true;
    }
    
    /**
     * Рекурсивное удаление директории
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        
        rmdir($dir);
        return true;
    }
    
    /**
     * Форматирование размера файла
     */
    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Получение списка установленных модулей из system/modules
     */
    private function getInstalledModules() {
        $modules = array();
        
        $system_modules_dir = DIR_APPLICATION . '../system/modules';
        
        if (!is_dir($system_modules_dir)) {
            return $modules;
        }
        
        // Сканируем директории модулей
        $module_dirs = scandir($system_modules_dir);
        
        foreach ($module_dirs as $module_dir) {
            if ($module_dir === '.' || $module_dir === '..') {
                continue;
            }
            
            $module_path = $system_modules_dir . '/' . $module_dir;
            if (!is_dir($module_path)) {
                continue;
            }
            
            $config_file = $module_path . '/opencart-module.json';
            if (file_exists($config_file)) {
                $config_content = file_get_contents($config_file);
                $module_config = json_decode($config_content, true);
                
                if ($module_config && isset($module_config['code'])) {
                    $modules[] = array(
                        'code' => $module_config['code'],
                        'name' => isset($module_config['module_name']) ? $module_config['module_name'] : $module_dir,
                        'version' => isset($module_config['version']) ? $module_config['version'] : '1.0.0',
                        'author' => isset($module_config['creator_name']) ? $module_config['creator_name'] : '',
                        'description' => isset($module_config['description']) ? $module_config['description'] : '',
                        'config_path' => $config_file
                    );
                }
            }
        }
        
        return $modules;
    }
    
    /**
     * Импорт модуля из файла opencart-module.json в базу данных
     */
    public function importModuleFromConfig($module_path, $zip_file_path = '') {
        $config_file = $module_path . '/opencart-module.json';
        
        if (!file_exists($config_file)) {
            return false;
        }
        
        $config_content = file_get_contents($config_file);
        $module_config = json_decode($config_content, true);
        
        if (!$module_config || !isset($module_config['code'])) {
            return false;
        }
        
        // Проверяем, существует ли уже модуль в базе
        $existing_module = $this->getModuleByCode($module_config['code']);
        
        $module_data = array(
            'code' => $module_config['code'],
            'name' => isset($module_config['module_name']) ? $module_config['module_name'] : $module_config['code'],
            'description' => isset($module_config['description']) ? $module_config['description'] : '',
            'version' => isset($module_config['version']) ? $module_config['version'] : '1.0.0',
            'author' => isset($module_config['creator_name']) ? $module_config['creator_name'] : '',
            'author_url' => isset($module_config['creator_email']) ? 'mailto:' . $module_config['creator_email'] : '',
            'category' => isset($module_config['category']) ? $module_config['category'] : 'module',
            'opencart_version' => isset($module_config['opencart_version']) ? $module_config['opencart_version'] : '3.0+',
            'dependencies' => isset($module_config['dependencies']) ? $module_config['dependencies'] : array(),
            'file_path' => $zip_file_path,
            'file_size' => !empty($zip_file_path) && file_exists($zip_file_path) ? filesize($zip_file_path) : 0,
            'file_hash' => !empty($zip_file_path) && file_exists($zip_file_path) ? md5_file($zip_file_path) : '',
            'status' => 1
        );
        
        if ($existing_module) {
            // Обновляем существующий модуль
            $this->editModule($existing_module['module_id'], $module_data);
            return $existing_module['module_id'];
        } else {
            // Добавляем новый модуль
            return $this->addModule($module_data);
        }
    }
    
    /**
     * Синхронизация модулей из system/modules с базой данных
     */
    public function syncModulesFromFileSystem() {
        $system_modules_dir = DIR_APPLICATION . '../system/modules';
        $synced_count = 0;
        
        if (!is_dir($system_modules_dir)) {
            return $synced_count;
        }
        
        $module_dirs = scandir($system_modules_dir);
        
        foreach ($module_dirs as $module_dir) {
            if ($module_dir === '.' || $module_dir === '..') {
                continue;
            }
            
            $module_path = $system_modules_dir . '/' . $module_dir;
            if (!is_dir($module_path)) {
                continue;
            }
            
            $config_file = $module_path . '/opencart-module.json';
            if (file_exists($config_file)) {
                // Ищем соответствующий ZIP файл
                $zip_file = '';
                $storage_path = defined('DIR_STORAGE') ? DIR_STORAGE : DIR_SYSTEM . 'storage/';
                $server_modules_path = $storage_path . 'gdt_update_server/modules/';
                
                if (is_dir($server_modules_path)) {
                    $zip_files = glob($server_modules_path . $module_dir . '*.zip');
                    if (!empty($zip_files)) {
                        $zip_file = $zip_files[0]; // Берем первый найденный файл
                    }
                }
                
                if ($this->importModuleFromConfig($module_path, $zip_file)) {
                    $synced_count++;
                }
            }
        }
        
        return $synced_count;
    }
}
