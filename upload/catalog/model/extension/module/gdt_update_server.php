<?php
class ModelExtensionModuleGdtUpdateServer extends Model {
    
    /**
     * Получить модуль по коду
     */
    public function getModuleByCode($code) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "gdt_server_modules WHERE code = '" . $this->db->escape($code) . "' AND status = 1");
        
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
            'sort_order',
            'downloads',
            'rating'
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
