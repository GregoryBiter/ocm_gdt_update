<?php
class ControllerGdtUpdateServerCheck extends Controller {
    
    public function index() {
        $this->load->language('extension/module/gdt_update_server');
        $this->load->model('extension/module/gdt_update_server');
        
        // Проверяем, включен ли модуль сервера обновлений
        if (!$this->config->get('module_gdt_update_server_status')) {
            $this->response->addHeader('HTTP/1.1 503 Service Unavailable');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Service unavailable')));
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
        $code = null;
        $current_version = null;
        
        // Поддерживаем оба варианта названий параметров для совместимости
        if (isset($this->request->post['code'])) {
            $code = $this->request->post['code'];
        } elseif (isset($this->request->post['module_code'])) {
            $code = $this->request->post['module_code'];
        }
        
        if (isset($this->request->post['version'])) {
            $current_version = $this->request->post['version'];
        } elseif (isset($this->request->post['current_version'])) {
            $current_version = $this->request->post['current_version'];
        }
        
        if (empty($code) || empty($current_version)) {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Missing required parameters: code and version')));
            return;
        }
        
        // Проверяем аутентификацию
        if (!$this->validateAuth()) {
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Unauthorized')));
            return;
        }
        
        // Логирование запроса если включено
        if ($this->config->get('module_gdt_update_server_log_enabled')) {
            $this->log->write('GDT Update Server: Check request for module ' . $code . ' version ' . $current_version);
        }
        
        // Получаем информацию о модуле из базы данных
        $module = $this->model_extension_module_gdt_update_server->getModuleByCode($code);
        
        if (!$module) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('status' => 'not_found')));
            return;
        }
        
        // Сравниваем версии
        if (version_compare($module['version'], $current_version, '>')) {
            $response = array(
                'status' => 'update_available',
                'version' => $module['version'],
                'name' => $module['name'],
                'description' => $module['description'],
                'author' => $module['author'],
                'author_url' => $module['author_url'],
                'category' => $module['category'],
                'opencart_version' => $module['opencart_version'],
                'dependencies' => $module['dependencies'],
                'file_size' => $module['file_size'],
                'date_modified' => $module['date_modified']
            );
        } else {
            $response = array('status' => 'up_to_date');
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
    }
    
    /**
     * Проверка аутентификации
     */
    private function validateAuth() {
        $api_key = $this->config->get('module_gdt_update_server_api_key');
        $allowed_ips = $this->config->get('module_gdt_update_server_allowed_ips');
        
        // Проверяем API ключ
        if (!empty($api_key)) {
            $request_api_key = isset($this->request->post['api_key']) ? $this->request->post['api_key'] : '';
            
            if ($request_api_key !== $api_key) {
                return false;
            }
        }
        
        // Проверяем IP адрес
        if (!empty($allowed_ips)) {
            $client_ip = $this->request->server['REMOTE_ADDR'];
            $allowed_ips_array = array_map('trim', explode(',', $allowed_ips));
            
            if (!in_array($client_ip, $allowed_ips_array) && !in_array('*', $allowed_ips_array)) {
                return false;
            }
        }
        
        return true;
    }
}
