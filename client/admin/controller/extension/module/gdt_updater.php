<?php
class ControllerExtensionModuleGdtUpdater extends Controller {
    private $error = array();
    
    public function index() {
        $this->load->language('extension/module/gdt_updater');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->load->model('setting/setting');
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_gdt_updater', $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }
        
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
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
            'href' => $this->url->link('extension/module/gdt_updater', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['action'] = $this->url->link('extension/module/gdt_updater', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        
        if (isset($this->request->post['module_gdt_updater_server'])) {
            $data['module_gdt_updater_server'] = $this->request->post['module_gdt_updater_server'];
        } else {
            $data['module_gdt_updater_server'] = $this->config->get('module_gdt_updater_server');
        }
        
        if (isset($this->request->post['module_gdt_updater_status'])) {
            $data['module_gdt_updater_status'] = $this->request->post['module_gdt_updater_status'];
        } else {
            $data['module_gdt_updater_status'] = $this->config->get('module_gdt_updater_status');
        }
        
        $data['check_updates'] = $this->url->link('extension/module/gdt_updater/check', 'user_token=' . $this->session->data['user_token'], true);
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/module/gdt_updater', $data));
    }
    
    public function check() {
        $this->load->language('extension/module/gdt_updater');
        
        $json = array();
        
        // Получаем URL сервера обновлений
        $server_url = $this->config->get('module_gdt_updater_server');
        
        if (empty($server_url)) {
            $json['error'] = $this->language->get('error_server');
        } else {
            // Получаем список установленных модулей
            $modules = $this->getInstalledModules();
            
            if (!empty($modules)) {
                $json['modules'] = array();
                
                foreach ($modules as $module) {
                    $update_info = $this->checkModuleUpdate($server_url, $module);
                    
                    if ($update_info) {
                        $json['modules'][] = array(
                            'name' => $module['name'],
                            'code' => $module['code'],
                            'current_version' => $module['version'],
                            'new_version' => $update_info['version'],
                            'update_url' => $this->url->link('extension/module/gdt_updater/update', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'], true)
                        );
                    }
                }
                
                if (empty($json['modules'])) {
                    $json['success'] = $this->language->get('text_no_updates');
                } else {
                    $json['success'] = sprintf($this->language->get('text_updates_found'), count($json['modules']));
                }
            } else {
                $json['error'] = $this->language->get('error_no_modules');
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function update() {
        $this->load->language('extension/module/gdt_updater');
        
        $json = array();
        
        if (isset($this->request->get['code'])) {
            $code = $this->request->get['code'];
            
            // Получаем URL сервера обновлений
            $server_url = $this->config->get('module_gdt_updater_server');
            
            if (empty($server_url)) {
                $json['error'] = $this->language->get('error_server');
            } else {
                // Получаем информацию о модуле
                $module = $this->getModuleByCode($code);
                
                if ($module) {
                    // Проверяем обновление
                    $update_info = $this->checkModuleUpdate($server_url, $module);
                    
                    if ($update_info) {
                        // Скачиваем и устанавливаем обновление
                        $result = $this->downloadAndInstallUpdate($server_url, $module, $update_info);
                        
                        if ($result === true) {
                            $json['success'] = sprintf($this->language->get('text_update_success'), $module['name']);
                        } else {
                            $json['error'] = $result;
                        }
                    } else {
                        $json['error'] = $this->language->get('error_no_update');
                    }
                } else {
                    $json['error'] = $this->language->get('error_module_not_found');
                }
            }
        } else {
            $json['error'] = $this->language->get('error_code');
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/gdt_updater')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        if (empty($this->request->post['module_gdt_updater_server'])) {
            $this->error['warning'] = $this->language->get('error_server_empty');
        }
        
        return !$this->error;
    }
    
    protected function getInstalledModules() {
        $modules = array();
        
        // Ищем файлы в директории system/hook
        $hook_files = glob(DIR_SYSTEM . 'hook/*.php');
        
        if ($hook_files) {
            foreach ($hook_files as $file) {
                $content = file_get_contents($file);
                
                // Извлекаем информацию о модуле из комментариев
                preg_match('/name:\s*([^\n]+)/i', $content, $name_match);
                preg_match('/description:\s*([^\n]+)/i', $content, $description_match);
                preg_match('/code:\s*([^\n]+)/i', $content, $code_match);
                preg_match('/version:\s*([^\n]+)/i', $content, $version_match);
                
                if (isset($name_match[1]) && isset($code_match[1]) && isset($version_match[1])) {
                    $modules[] = array(
                        'name' => trim($name_match[1]),
                        'description' => isset($description_match[1]) ? trim($description_match[1]) : '',
                        'code' => trim($code_match[1]),
                        'version' => trim($version_match[1]),
                        'file_path' => $file
                    );
                }
            }
        }
        
        return $modules;
    }
    
    protected function getModuleByCode($code) {
        $modules = $this->getInstalledModules();
        
        foreach ($modules as $module) {
            if ($module['code'] == $code) {
                return $module;
            }
        }
        
        return null;
    }
    
    protected function checkModuleUpdate($server_url, $module) {
        $url = rtrim($server_url, '/') . '/check.php';
        
        $post_data = array(
            'code' => $module['code'],
            'version' => $module['version']
        );
        
        $options = array(
            'header' => array(
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: GDT-Updater/1.0'
            ),
            'method' => 'POST',
            'content' => http_build_query($post_data)
        );
        
        $context = stream_context_create(array('http' => $options));
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $update_info = json_decode($response, true);
            
            if (isset($update_info['status']) && $update_info['status'] == 'update_available') {
                return $update_info;
            }
        }
        
        return false;
    }
    
    protected function downloadAndInstallUpdate($server_url, $module, $update_info) {
        try {
            // URL для загрузки обновления
            $download_url = rtrim($server_url, '/') . '/download.php';
            
            $post_data = array(
                'code' => $module['code'],
                'version' => $update_info['version']
            );
            
            $options = array(
                'header' => array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: GDT-Updater/1.0'
                ),
                'method' => 'POST',
                'content' => http_build_query($post_data)
            );
            
            $context = stream_context_create(array('http' => $options));
            
            // Временный файл для загрузки
            $temp_file = DIR_DOWNLOAD . 'update_' . $module['code'] . '_' . $update_info['version'] . '.zip';
            
            // Загружаем файл обновления
            $response = @file_get_contents($download_url, false, $context);
            
            if ($response === false) {
                return $this->language->get('error_download');
            }
            
            // Сохраняем во временный файл
            file_put_contents($temp_file, $response);
            
            // Проверяем, является ли файл действительным архивом ZIP
            $zip = new ZipArchive();
            if ($zip->open($temp_file) !== true) {
                @unlink($temp_file);
                return $this->language->get('error_zip');
            }
            
            // Извлекаем архив
            $zip->extractTo(DIR_DOWNLOAD . 'update_extract_' . $module['code']);
            $zip->close();
            
            $extract_dir = DIR_DOWNLOAD . 'update_extract_' . $module['code'];
            
            // Применяем обновление - копируем файлы в нужные директории
            $this->copyDirectory($extract_dir, DIR_OPENCART);
            
            // Обновляем версию в файле хука
            $hook_content = file_get_contents($module['file_path']);
            $new_hook_content = preg_replace('/version:\s*([^\n]+)/i', 'version: ' . $update_info['version'], $hook_content);
            file_put_contents($module['file_path'], $new_hook_content);
            
            // Очищаем временные файлы
            $this->removeDirectory($extract_dir);
            @unlink($temp_file);
            
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    
    protected function copyDirectory($source, $destination) {
        if (is_dir($source)) {
            if (!is_dir($destination)) {
                mkdir($destination, 0777, true);
            }
            
            $directory = opendir($source);
            
            while (($file = readdir($directory)) !== false) {
                if ($file != '.' && $file != '..') {
                    $this->copyDirectory($source . '/' . $file, $destination . '/' . $file);
                }
            }
            
            closedir($directory);
        } elseif (file_exists($source)) {
            copy($source, $destination);
        }
    }
    
    protected function removeDirectory($directory) {
        if (is_dir($directory)) {
            $objects = scandir($directory);
            
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (is_dir($directory . '/' . $object)) {
                        $this->removeDirectory($directory . '/' . $object);
                    } else {
                        unlink($directory . '/' . $object);
                    }
                }
            }
            
            rmdir($directory);
        }
    }
    
    public function install() {
        // Создаем необходимые директории, если их нет
        if (!is_dir(DIR_SYSTEM . 'hook')) {
            mkdir(DIR_SYSTEM . 'hook', 0777, true);
        }
    }
    
    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_gdt_updater');
    }
}
