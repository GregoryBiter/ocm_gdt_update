<?php
class ControllerGdtUpdateServerSync extends Controller {
    
    public function index() {
        // Проверяем API ключ
        if (!$this->validateApiAccess()) {
            http_response_code(401);
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array(
                'error' => 'Unauthorized access'
            )));
            return;
        }
        
        // Проверяем метод запроса
        if ($this->request->server['REQUEST_METHOD'] != 'POST') {
            $this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Method not allowed')));
            return;
        }
        
        // Загружаем модель
        $this->load->model('extension/module/gdt_update_server');
        
        // Логируем запрос
        $this->logRequest('sync', array(
            'ip' => $this->request->server['REMOTE_ADDR'],
            'user_agent' => isset($this->request->server['HTTP_USER_AGENT']) ? $this->request->server['HTTP_USER_AGENT'] : ''
        ));
        
        // Выполняем синхронизацию
        $synced_count = $this->model_extension_module_gdt_update_server->syncModulesFromFileSystem();
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(array(
            'success' => true,
            'message' => 'Synchronization completed',
            'synced_modules' => $synced_count
        )));
    }
    
    /**
     * Синхронизация конкретного модуля
     */
    public function module() {
        // Проверяем API ключ
        if (!$this->validateApiAccess()) {
            http_response_code(401);
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array(
                'error' => 'Unauthorized access'
            )));
            return;
        }
        
        // Проверяем метод запроса
        if ($this->request->server['REQUEST_METHOD'] != 'POST') {
            $this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Method not allowed')));
            return;
        }
        
        // Проверяем обязательные параметры
        $module_code = isset($this->request->post['code']) ? $this->request->post['code'] : '';
        
        if (empty($module_code)) {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Missing required parameter: code')));
            return;
        }
        
        // Загружаем модель
        $this->load->model('extension/module/gdt_update_server');
        
        // Путь к модулю
        $opencart_root = DIR_APPLICATION . '../';
        $module_path = $opencart_root . 'system/modules/' . $module_code;
        
        if (!is_dir($module_path)) {
            $this->response->addHeader('HTTP/1.1 404 Not Found');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Module not found')));
            return;
        }
        
        // Ищем ZIP файл модуля
        $zip_file = '';
        if (isset($this->request->post['zip_file'])) {
            $zip_file = $this->request->post['zip_file'];
        }
        
        // Импортируем модуль
        $module_id = $this->model_extension_module_gdt_update_server->importModuleFromConfig($module_path, $zip_file);
        
        if ($module_id) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array(
                'success' => true,
                'message' => 'Module synchronized successfully',
                'module_id' => $module_id
            )));
        } else {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Failed to synchronize module')));
        }
    }
    
    /**
     * Получение списка модулей из файловой системы
     */
    public function scan() {
        // Проверяем API ключ
        if (!$this->validateApiAccess()) {
            http_response_code(401);
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array(
                'error' => 'Unauthorized access'
            )));
            return;
        }
        
        $opencart_root = DIR_APPLICATION . '../';
        $system_modules_dir = $opencart_root . 'system/modules';
        $modules = array();
        
        if (is_dir($system_modules_dir)) {
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
                            'path' => $module_path
                        );
                    }
                }
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(array(
            'success' => true,
            'modules' => $modules
        )));
    }
    
    /**
     * Валидация доступа к API
     */
    private function validateApiAccess() {
        // Проверяем, включен ли модуль
        if (!$this->config->get('module_gdt_update_server_status')) {
            return false;
        }
        
        // Проверяем API ключ
        $api_key = $this->config->get('module_gdt_update_server_api_key');
        $request_api_key = '';
        
        // Ключ может быть передан в заголовке или POST данных
        if (isset($this->request->server['HTTP_X_API_KEY'])) {
            $request_api_key = $this->request->server['HTTP_X_API_KEY'];
        } elseif (isset($this->request->post['api_key'])) {
            $request_api_key = $this->request->post['api_key'];
        } elseif (isset($this->request->get['api_key'])) {
            $request_api_key = $this->request->get['api_key'];
        }
        
        if (empty($api_key) || $api_key !== $request_api_key) {
            return false;
        }
        
        // Проверяем разрешенные IP
        $allowed_ips = $this->config->get('module_gdt_update_server_allowed_ips');
        if (!empty($allowed_ips)) {
            $client_ip = $this->request->server['REMOTE_ADDR'];
            $allowed_ips_array = array_map('trim', explode(',', $allowed_ips));
            
            if (!in_array($client_ip, $allowed_ips_array) && !in_array('*', $allowed_ips_array)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Логирование запросов
     */
    private function logRequest($action, $data = array()) {
        if (!$this->config->get('module_gdt_update_server_log_enabled')) {
            return;
        }
        
        $log_data = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'ip' => $this->request->server['REMOTE_ADDR'],
            'user_agent' => isset($this->request->server['HTTP_USER_AGENT']) ? $this->request->server['HTTP_USER_AGENT'] : '',
            'data' => $data
        );
        
        $log_file = $this->getLogPath() . '/gdt_update_server_' . date('Y-m-d') . '.log';
        $log_line = date('Y-m-d H:i:s') . ' - ' . $action . ' - ' . $this->request->server['REMOTE_ADDR'] . ' - ' . json_encode($data) . PHP_EOL;
        
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Получить путь к логам
     */
    private function getLogPath() {
        $log_path = defined('DIR_LOGS') ? DIR_LOGS : DIR_SYSTEM . 'storage/logs/';
        
        if (!is_dir($log_path)) {
            mkdir($log_path, 0777, true);
        }
        
        return $log_path;
    }
}
