<?php
class ControllerExtensionModuleGdtUpdateServer extends Controller {
    private $error = array();
    
    public function index() {
        $this->load->language('extension/module/gdt_update_server');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->load->model('setting/setting');
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_gdt_update_server', $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }
        
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        
        if (isset($this->error['api_key'])) {
            $data['error_api_key'] = $this->error['api_key'];
        } else {
            $data['error_api_key'] = '';
        }
        
        $data['breadcrumbs'] = array();
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/gdt_update_server', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['action'] = $this->url->link('extension/module/gdt_update_server', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        
        // Настройки модуля
        if (isset($this->request->post['module_gdt_update_server_status'])) {
            $data['module_gdt_update_server_status'] = $this->request->post['module_gdt_update_server_status'];
        } else {
            $data['module_gdt_update_server_status'] = $this->config->get('module_gdt_update_server_status');
        }
        
        if (isset($this->request->post['module_gdt_update_server_api_key'])) {
            $data['module_gdt_update_server_api_key'] = $this->request->post['module_gdt_update_server_api_key'];
        } else {
            $data['module_gdt_update_server_api_key'] = $this->config->get('module_gdt_update_server_api_key');
        }
        
        if (isset($this->request->post['module_gdt_update_server_allowed_ips'])) {
            $data['module_gdt_update_server_allowed_ips'] = $this->request->post['module_gdt_update_server_allowed_ips'];
        } else {
            $data['module_gdt_update_server_allowed_ips'] = $this->config->get('module_gdt_update_server_allowed_ips');
        }
        
        if (isset($this->request->post['module_gdt_update_server_log_enabled'])) {
            $data['module_gdt_update_server_log_enabled'] = $this->request->post['module_gdt_update_server_log_enabled'];
        } else {
            $data['module_gdt_update_server_log_enabled'] = $this->config->get('module_gdt_update_server_log_enabled');
        }
        
        // URLs для API
        $data['api_check_url'] = HTTP_CATALOG . 'index.php?route=gdt_update_server/check';
        $data['api_download_url'] = HTTP_CATALOG . 'index.php?route=gdt_update_server/download';
        $data['api_modules_url'] = HTTP_CATALOG . 'index.php?route=gdt_update_server/modules';
        
        // Переменные для шаблона
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/module/gdt_update_server', $data));
    }
    
    /**
     * Управление модулями сервера
     */
    public function modules() {
        $this->load->language('extension/module/gdt_update_server');
        
        $this->document->setTitle($this->language->get('heading_title_modules'));
        
        // Получаем список модулей
        $data['modules'] = $this->getServerModules();
        
        $data['breadcrumbs'] = array();
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/gdt_update_server', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title_modules'),
            'href' => $this->url->link('extension/module/gdt_update_server/modules', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['cancel'] = $this->url->link('extension/module/gdt_update_server', 'user_token=' . $this->session->data['user_token'], true);
        $data['upload_url'] = $this->url->link('extension/module/gdt_update_server/upload', 'user_token=' . $this->session->data['user_token'], true);

        $data['upload_url'] = html_entity_decode($data['upload_url'], ENT_QUOTES, 'UTF-8');
        
        // Передаем user_token в шаблон
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/module/gdt_update_server_modules', $data));
    }
    
    /**
     * Загрузка модуля на сервер
     */
    public function upload() {
        $this->load->language('extension/module/gdt_update_server');
        
        $json = array();
        
        if (!$this->user->hasPermission('modify', 'extension/module/gdt_update_server')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            if (isset($this->request->files['file']['name'])) {
                if (substr($this->request->files['file']['name'], -4) != '.zip') {
                    $json['error'] = $this->language->get('error_file_type');
                } else {
                    $upload_path = $this->getServerModulesPath();
                    
                    if (!is_dir($upload_path)) {
                        mkdir($upload_path, 0777, true);
                    }
                    
                    $filename = $this->request->files['file']['name'];
                    $destination = $upload_path . '/' . $filename;
                    
                    if (move_uploaded_file($this->request->files['file']['tmp_name'], $destination)) {
                        // Извлекаем информацию о модуле из архива
                        $module_info = $this->extractModuleInfo($destination);
                        
                        if ($module_info) {
                            // Создаем директорию для модуля
                            $module_dir = $upload_path . '/' . $module_info['code'];
                            if (!is_dir($module_dir)) {
                                mkdir($module_dir, 0777, true);
                            }
                            
                            // Перемещаем архив в директорию модуля
                            $final_destination = $module_dir . '/' . $module_info['code'] . '_' . $module_info['version'] . '.zip';
                            rename($destination, $final_destination);
                            
                            // Сохраняем информацию о модуле
                            $info_file = $module_dir . '/info.json';
                            file_put_contents($info_file, json_encode($module_info, JSON_PRETTY_PRINT));
                            
                            $json['success'] = $this->language->get('text_upload_success');
                        } else {
                            unlink($destination);
                            $json['error'] = $this->language->get('error_module_info');
                        }
                    } else {
                        $json['error'] = $this->language->get('error_upload');
                    }
                }
            } else {
                $json['error'] = $this->language->get('error_no_file');
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Получить список модулей на сервере
     */
    private function getServerModules() {
        $modules = array();
        $modules_path = $this->getServerModulesPath();
        
        if (is_dir($modules_path)) {
            $module_dirs = glob($modules_path . '/*', GLOB_ONLYDIR);
            
            foreach ($module_dirs as $module_dir) {
                $info_file = $module_dir . '/info.json';
                
                if (file_exists($info_file)) {
                    $info = json_decode(file_get_contents($info_file), true);
                    
                    if ($info) {
                        $modules[] = $info;
                    }
                }
            }
        }
        
        return $modules;
    }
    
    /**
     * Извлечь информацию о модуле из архива
     */
    private function extractModuleInfo($archive_path) {
        $zip = new ZipArchive();
        
        if ($zip->open($archive_path) === true) {
            // Ищем файл с информацией о модуле
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                if (strpos($filename, 'system/hook/') !== false && substr($filename, -4) == '.php') {
                    $content = $zip->getFromIndex($i);
                    
                    // Извлекаем информацию из комментариев
                    preg_match('/name:\s*([^\n]+)/i', $content, $name_match);
                    preg_match('/description:\s*([^\n]+)/i', $content, $description_match);
                    preg_match('/version:\s*([^\n]+)/i', $content, $version_match);
                    preg_match('/author:\s*([^\n]+)/i', $content, $author_match);
                    
                    if (isset($name_match[1]) && isset($version_match[1])) {
                        $code = basename($filename, '.php');
                        
                        $zip->close();
                        
                        return array(
                            'code' => $code,
                            'name' => trim($name_match[1]),
                            'description' => isset($description_match[1]) ? trim($description_match[1]) : '',
                            'version' => trim($version_match[1]),
                            'author' => isset($author_match[1]) ? trim($author_match[1]) : '',
                            'upload_date' => date('Y-m-d H:i:s')
                        );
                    }
                }
            }
            
            $zip->close();
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
    
    /**
     * Удаление модуля с сервера
     */
    public function delete() {
        $this->load->language('extension/module/gdt_update_server');
        
        $json = array();
        
        if (!$this->user->hasPermission('modify', 'extension/module/gdt_update_server')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            if (isset($this->request->post['code'])) {
                $code = $this->request->post['code'];
                $modules_path = $this->getServerModulesPath();
                $module_dir = $modules_path . '/' . $code;
                
                if (is_dir($module_dir)) {
                    $this->deleteDirectory($module_dir);
                    $json['success'] = $this->language->get('text_delete_success');
                } else {
                    $json['error'] = $this->language->get('error_module_not_found');
                }
            } else {
                $json['error'] = $this->language->get('error_module_code');
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Получение информации о модуле
     */
    public function info() {
        $this->load->language('extension/module/gdt_update_server');
        
        $json = array();
        
        if (isset($this->request->post['code'])) {
            $code = $this->request->post['code'];
            $modules_path = $this->getServerModulesPath();
            $info_file = $modules_path . '/' . $code . '/info.json';
            
            if (file_exists($info_file)) {
                $info = json_decode(file_get_contents($info_file), true);
                
                if ($info) {
                    $json = $info;
                } else {
                    $json['error'] = $this->language->get('error_module_info');
                }
            } else {
                $json['error'] = $this->language->get('error_module_not_found');
            }
        } else {
            $json['error'] = $this->language->get('error_module_code');
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Рекурсивное удаление директории
     */
    private function deleteDirectory($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                
                if (is_dir($path)) {
                    $this->deleteDirectory($path);
                } else {
                    unlink($path);
                }
            }
            
            rmdir($dir);
        }
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/gdt_update_server')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        if (empty($this->request->post['module_gdt_update_server_api_key'])) {
            $this->error['api_key'] = $this->language->get('error_api_key');
        }
        
        return !$this->error;
    }
    
    public function install() {
        // Создаем необходимые директории
        $modules_path = $this->getServerModulesPath();
        if (!is_dir($modules_path)) {
            mkdir($modules_path, 0777, true);
        }
    }
    
    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_gdt_update_server');
    }
}
