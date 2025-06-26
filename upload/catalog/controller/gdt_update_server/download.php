<?php
class ControllerGdtUpdateServerDownload extends Controller {
    
    public function index() {
        $this->load->language('extension/module/gdt_update_server');
        
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
        
        // Поддерживаем оба варианта названий параметров для совместимости
        if (isset($this->request->post['code'])) {
            $code = $this->request->post['code'];
        } elseif (isset($this->request->post['module_code'])) {
            $code = $this->request->post['module_code'];
        }
        
        if (empty($code)) {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Missing required parameter: code')));
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
            $this->log->write('GDT Update Server: Download request for module ' . $code);
        }
        
        // Получаем путь к файлу модуля
        $file_path = $this->getModuleFilePath($code);
        
        if (!$file_path || !file_exists($file_path)) {
            $this->response->addHeader('HTTP/1.1 404 Not Found');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Module not found')));
            return;
        }
        
        // Отправляем файл
        $this->response->addHeader('Content-Type: application/zip');
        $this->response->addHeader('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        $this->response->addHeader('Content-Length: ' . filesize($file_path));
        $this->response->setOutput(file_get_contents($file_path));
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
    
    /**
     * Получить путь к файлу модуля
     */
    private function getModuleFilePath($code, $version = null) {
        $modules_path = $this->getServerModulesPath();
        $module_dir = $modules_path . '/' . $code;
        
        // Если версия указана, ищем файл с нужной версией
        if ($version) {
            $file_path = $module_dir . '/' . $code . '_' . $version . '.zip';
            
            if (file_exists($file_path)) {
                return $file_path;
            }
        }
        
        // Если точная версия не найдена или не указана, ищем последнюю доступную
        $files = glob($module_dir . '/' . $code . '_*.zip');
        
        if (!empty($files)) {
            // Сортируем по версии и возвращаем последнюю
            usort($files, function($a, $b) {
                preg_match('/_([\d\.]+)\.zip$/', $a, $matches_a);
                preg_match('/_([\d\.]+)\.zip$/', $b, $matches_b);
                
                $version_a = isset($matches_a[1]) ? $matches_a[1] : '0';
                $version_b = isset($matches_b[1]) ? $matches_b[1] : '0';
                
                return version_compare($version_b, $version_a);
            });
            
            return $files[0];
        }
        
        return false;
    }
    
    /**
     * Получить путь к директории модулей сервера
     */
    private function getServerModulesPath() {
        $upload_path = defined('DIR_STORAGE') ? DIR_STORAGE : DIR_SYSTEM . 'storage/';
        return $upload_path . 'gdt_update_server/modules';
    }
}
