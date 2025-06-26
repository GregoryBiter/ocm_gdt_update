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
     * Получить популярные модули
     */
    public function getPopularModules($limit = 10) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "gdt_server_modules WHERE status = 1 ORDER BY downloads DESC, rating DESC LIMIT " . (int)$limit);
        
        $modules = array();
        
        foreach ($query->rows as $module) {
            if ($module['dependencies']) {
                $module['dependencies'] = json_decode($module['dependencies'], true);
            }
            
            $modules[] = $module;
        }
        
        return $modules;
    }
    
    /**
     * Получить рекомендуемые модули
     */
    public function getFeaturedModules($limit = 10) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "gdt_server_modules WHERE status = 1 AND featured = 1 ORDER BY sort_order, name LIMIT " . (int)$limit);
        
        $modules = array();
        
        foreach ($query->rows as $module) {
            if ($module['dependencies']) {
                $module['dependencies'] = json_decode($module['dependencies'], true);
            }
            
            $modules[] = $module;
        }
        
        return $modules;
    }
}
