<?php
class ControllerGdtUpdateServerModules extends Controller {
    
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
        
        // Логируем запрос
        $this->logRequest('modules', array(
            'ip' => $this->request->server['REMOTE_ADDR'],
            'user_agent' => isset($this->request->server['HTTP_USER_AGENT']) ? $this->request->server['HTTP_USER_AGENT'] : ''
        ));
        
        // Получаем список всех модулей
        $modules = $this->getAvailableModules();
        
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
     * Получить список доступных модулей
     */
    private function getAvailableModules() {
        $modules = array();
        $modules_path = $this->getServerModulesPath();
        
        if (is_dir($modules_path)) {
            $module_dirs = glob($modules_path . '/*', GLOB_ONLYDIR);
            
            foreach ($module_dirs as $module_dir) {
                $info_file = $module_dir . '/info.json';
                
                if (file_exists($info_file)) {
                    $info = json_decode(file_get_contents($info_file), true);
                    
                    if ($info) {
                        // Возвращаем только публичную информацию
                        $modules[] = array(
                            'code' => $info['code'],
                            'name' => $info['name'],
                            'description' => isset($info['description']) ? $info['description'] : '',
                            'version' => $info['version'],
                            'author' => isset($info['author']) ? $info['author'] : '',
                            'author_url' => isset($info['author_url']) ? $info['author_url'] : ''
                        );
                    }
                }
            }
        }
        
        return $modules;
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
     * Получить путь к директории модулей сервера
     */
    private function getServerModulesPath() {
        $upload_path = defined('DIR_STORAGE') ? DIR_STORAGE : DIR_SYSTEM . 'storage/';
        return $upload_path . 'gdt_update_server/modules';
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
