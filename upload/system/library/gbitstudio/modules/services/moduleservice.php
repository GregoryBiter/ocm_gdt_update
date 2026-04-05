namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для роботи з модулями
 * Відповідає за бізнес-логіку роботи з модулями
 * Читає дані тільки з таблиці gdt_modules
 */
class ModuleService {
    private $db;
    private $registry;
    
    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
    }
    
    /**
     * Отримує список встановлених модулів
     * Читає з бази даних
     * 
     * @return array
     */
    public function getInstalledModules() {
        return $this->getModulesFromDatabase();
    }
    
    /**
     * Отримує модуль за кодом
     * 
     * @param string $code Код модуля
     * @return array|null
     */
    public function getModuleByCode($code) {
        return $this->getModuleFromDatabase($code);
    }
    
    /**
     * Отримує модулі з бази даних
     * 
     * @return array
     */
    private function getModulesFromDatabase() {
        $modules = [];
        
        try {
            // Перевіряємо чи існує таблиця
            $table_query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "gdt_modules'");
            if ($table_query->num_rows == 0) {
                return $modules;
            }
            
            $query = "SELECT * FROM `" . DB_PREFIX . "gdt_modules` ORDER BY `date_added` DESC";
            $result = $this->db->query($query);
            
            if ($result->num_rows > 0) {
                foreach ($result->rows as $row) {
                    $module_data = json_decode($row['data'], true);
                    if ($module_data && (isset($module_data['code']) || isset($row['code']))) {
                        // Об'єднуємо метадані та шляхи
                        $module_data['code'] = $row['code'];
                        $module_data['paths'] = json_decode($row['paths'], true) ?: [];
                        $module_data['source'] = 'database';
                        
                        $modules[] = $this->formatModuleData($module_data, $row['code']);
                    }
                }
            }
        } catch (\Exception $e) {
            LoggerService::error('Error getting modules from database: ' . $e->getMessage(), 'ModuleService');
        }
        
        return $modules;
    }
    
    /**
     * Отримує модуль з бази даних за кодом
     * 
     * @param string $code
     * @return array|null
     */
    private function getModuleFromDatabase($code) {
        try {
            $query = "SELECT * FROM `" . DB_PREFIX . "gdt_modules` WHERE `code` = '" . $this->db->escape($code) . "' LIMIT 1";
            $result = $this->db->query($query);
            
            if ($result->num_rows > 0) {
                $row = $result->row;
                $module_data = json_decode($row['data'], true);
                if ($module_data) {
                    $module_data['code'] = $row['code'];
                    $module_data['paths'] = json_decode($row['paths'], true) ?: [];
                    $module_data['source'] = 'database';
                    
                    return $this->formatModuleData($module_data, $row['code']);
                }
            }
        } catch (\Exception $e) {
            LoggerService::error('Error getting module from database: ' . $e->getMessage(), 'ModuleService');
        }
        
        return null;
    }
    
    /**
     * Форматує дані модуля для однотипного використання
     * 
     * @param array $data
     * @param string $code
     * @return array
     */
    private function formatModuleData($data, $code) {
        return [
            'code' => $data['code'] ?? $code,
            'name' => $data['module_name'] ?? $data['name'] ?? $code,
            'module_name' => $data['module_name'] ?? $data['name'] ?? $code,
            'version' => $data['version'] ?? '1.0.0',
            'author' => $data['creator_name'] ?? $data['author'] ?? 'Unknown',
            'creator_name' => $data['creator_name'] ?? $data['author'] ?? 'Unknown',
            'author_url' => $data['author_url'] ?? ($data['link'] ?? ''),
            'link' => $data['link'] ?? ($data['author_url'] ?? ''),
            'description' => $data['description'] ?? '',
            'controller' => $data['controller'] ?? '',
            'provider' => $data['provider'] ?? '',
            'files' => $data['files'] ?? [],
            'paths' => $data['paths'] ?? [],
            'source' => $data['source'] ?? 'database'
        ];
    }
}
