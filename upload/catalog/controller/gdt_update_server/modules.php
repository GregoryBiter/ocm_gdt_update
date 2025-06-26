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
        
        // Загружаем модель
        $this->load->model('extension/module/gdt_update_server');
        
        // Получаем параметры фильтрации
        $filter_data = array();
        
        if (isset($this->request->get['category'])) {
            $filter_data['filter_category'] = $this->request->get['category'];
        }
        
        if (isset($this->request->get['featured'])) {
            $filter_data['filter_featured'] = (int)$this->request->get['featured'];
        }
        
        if (isset($this->request->get['search'])) {
            $filter_data['filter_name'] = $this->request->get['search'];
        }
        
        if (isset($this->request->get['limit'])) {
            $filter_data['limit'] = (int)$this->request->get['limit'];
        } else {
            $filter_data['limit'] = 20;
        }
        
        if (isset($this->request->get['page'])) {
            $filter_data['start'] = ((int)$this->request->get['page'] - 1) * $filter_data['limit'];
        } else {
            $filter_data['start'] = 0;
        }
        
        // Только активные модули
        $filter_data['filter_status'] = 1;
        
        // Получаем список модулей из базы данных
        $modules = $this->model_extension_module_gdt_update_server->getModules($filter_data);
        $total = $this->model_extension_module_gdt_update_server->getTotalModules($filter_data);
        
        // Обрабатываем данные модулей для API
        $result_modules = array();
        foreach ($modules as $module) {
            $result_modules[] = array(
                'code' => $module['code'],
                'name' => $module['name'],
                'description' => $module['description'],
                'version' => $module['version'],
                'author' => $module['author'],
                'author_url' => $module['author_url'],
                'category' => $module['category'],
                'opencart_version' => $module['opencart_version'],
                'dependencies' => $module['dependencies'],
                'image' => $module['image'],
                'demo_url' => $module['demo_url'],
                'documentation_url' => $module['documentation_url'],
                'support_url' => $module['support_url'],
                'price' => (float)$module['price'],
                'downloads' => (int)$module['downloads'],
                'rating' => (float)$module['rating'],
                'reviews' => (int)$module['reviews'],
                'featured' => (bool)$module['featured'],
                'date_added' => $module['date_added']
            );
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(array(
            'success' => true,
            'modules' => $result_modules,
            'total' => $total,
            'page' => isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1,
            'limit' => $filter_data['limit']
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
