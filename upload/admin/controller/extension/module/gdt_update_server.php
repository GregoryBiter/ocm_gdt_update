<?php
use GbitStudio\Updater\Tools;
class ControllerExtensionModuleGdtUpdateServer extends Controller {
    private $error = array();
    private $t;

    public function __construct(\Registry $registry){
        parent::__construct($registry);
        $this->t = new Tools($registry);
    }

    private function getBreadCrumbs(){
        $breadcrumbs = array();

        $breadcrumbs[] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $breadcrumbs[] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        
       $breadcrumbs[] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/gdt_update_server', 'user_token=' . $this->session->data['user_token'], true)
        );

        return $breadcrumbs;

    }
    
    public function index() {
        $this->load->language('extension/module/gdt_update_server');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->load->model('setting/setting');
        $this->load->model('extension/module/gdt_update_server');
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_gdt_update_server', $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['error_warning'] = $this->error['warning'] ?? '';

        $data['error_api_key'] = $this->error['api_key'] ?? '';
        
        $data['breadcrumbs'] = $this->getBreadCrumbs();
        
        $data['action'] = $this->url->link('extension/module/gdt_update_server', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        
        // Настройки модуля

        $settings = [
            "module_gdt_update_server_status",
            "module_gdt_update_server_api_key",
            "module_gdt_update_server_allowed_ips",
            "module_gdt_update_server_log_enabled"
        ];

        foreach ($settings as $setting) {
            if (isset($this->request->post[$setting])) {
                $data[$setting] = $this->request->post[$setting];
            } else {
                $data[$setting] = $this->config->get($setting);
            }
        }
        
        // URLs для API
        $data['api_check_url'] = HTTP_CATALOG . 'index.php?route=gdt_update_server/check';
        $data['api_download_url'] = HTTP_CATALOG . 'index.php?route=gdt_update_server/download';
        $data['api_modules_url'] = HTTP_CATALOG . 'index.php?route=gdt_update_server/modules';
        
        // Переменные для шаблона
        $data['user_token'] = $this->session->data['user_token'];
        
        $this->t->getLayout($data);
        
        $this->t->view('extension/module/gdt_update_server', $data);
    }
    
    /**
     * Управление модулями сервера
     */
    public function modules() {
        $this->load->language('extension/module/gdt_update_server');
        $this->load->model('extension/module/gdt_update_server');
        
        $this->document->setTitle($this->language->get('heading_title_modules'));
        
        // Получаем список модулей из базы данных
        $data['modules'] = $this->model_extension_module_gdt_update_server->getModules();
        
        $data['breadcrumbs'] = $this->getBreadCrumbs();
        
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
        $this->load->model('extension/module/gdt_update_server');
        
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
                            
                            // Добавляем информацию о файле
                            $module_info['file_path'] = $final_destination;
                            $module_info['file_size'] = filesize($final_destination);
                            $module_info['file_hash'] = md5_file($final_destination);
                            
                            // Проверяем, существует ли модуль в базе данных
                            $existing_module = $this->model_extension_module_gdt_update_server->getModuleByCode($module_info['code']);
                            
                            if ($existing_module) {
                                // Обновляем существующий модуль
                                $this->model_extension_module_gdt_update_server->editModule($existing_module['module_id'], $module_info);
                                $json['success'] = $this->language->get('text_update_success');
                            } else {
                                // Добавляем новый модуль
                                $this->model_extension_module_gdt_update_server->addModule($module_info);
                                $json['success'] = $this->language->get('text_upload_success');
                            }
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
     * Извлечь информацию о модуле из архива
     */
    private function extractModuleInfo($archive_path) {
        $zip = new ZipArchive();
        
        if ($zip->open($archive_path) === true) {
            // Сначала ищем opencart-module.json в корне архива
            $config_content = $zip->getFromName('opencart-module.json');
            
            if ($config_content === false) {
                // Если не найден в корне, ищем в system/modules/*/opencart-module.json
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    
                    // Проверяем паттерн system/modules/*/opencart-module.json
                    if (preg_match('/^(upload\/)?system\/modules\/([^\/]+)\/opencart-module\.json$/', $filename, $matches)) {
                        $config_content = $zip->getFromIndex($i);
                        break;
                    }
                }
            }
            
            $zip->close();
            
            // Если найден файл конфигурации, парсим его
            if ($config_content !== false) {
                $module_config = json_decode($config_content, true);
                
                if ($module_config && isset($module_config['code']) && isset($module_config['version'])) {
                    return array(
                        'code' => $module_config['code'],
                        'name' => isset($module_config['module_name']) ? $module_config['module_name'] : $module_config['code'],
                        'description' => isset($module_config['description']) ? $module_config['description'] : '',
                        'version' => $module_config['version'],
                        'author' => isset($module_config['creator_name']) ? $module_config['creator_name'] : '',
                        'author_url' => isset($module_config['creator_email']) ? 'mailto:' . $module_config['creator_email'] : '',
                        'category' => isset($module_config['category']) ? $module_config['category'] : 'module',
                        'opencart_version' => isset($module_config['opencart_version']) ? $module_config['opencart_version'] : '3.0+',
                        'dependencies' => isset($module_config['dependencies']) ? $module_config['dependencies'] : array(),
                        'upload_date' => date('Y-m-d H:i:s'),
                        'archive_structure' => 'opencart'
                    );
                }
            }
            
            // Fallback: ищем информацию в старом формате (комментарии в hook-файлах)
            return $this->extractModuleInfoLegacy($archive_path);
        }
        
        return false;
    }
    
    /**
     * Извлечь информацию о модуле из архива (старый формат с комментариями)
     */
    private function extractModuleInfoLegacy($archive_path) {
        $zip = new ZipArchive();
        
        if ($zip->open($archive_path) === true) {
            // Ищем файл с информацией о модуле в разных возможных местах
            $possible_paths = array(
                'system/hook/',               // Прямо в корне архива
                'upload/system/hook/'         // В структуре OpenCart с папкой upload
            );
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // Проверяем все возможные пути
                foreach ($possible_paths as $path) {
                    if (strpos($filename, $path) !== false && substr($filename, -4) == '.php') {
                        $content = $zip->getFromIndex($i);
                        
                        // Извлекаем информацию из комментариев
                        preg_match('/name:\s*([^\n]+)/i', $content, $name_match);
                        preg_match('/description:\s*([^\n]+)/i', $content, $description_match);
                        preg_match('/version:\s*([^\n]+)/i', $content, $version_match);
                        preg_match('/author:\s*([^\n]+)/i', $content, $author_match);
                        preg_match('/author[_\s]*url:\s*([^\n]+)/i', $content, $author_url_match);
                        
                        if (isset($name_match[1]) && isset($version_match[1])) {
                            // Получаем код модуля из имени файла
                            $code = basename($filename, '.php');
                            
                            $zip->close();
                            
                            return array(
                                'code' => $code,
                                'name' => trim($name_match[1]),
                                'description' => isset($description_match[1]) ? trim($description_match[1]) : '',
                                'version' => trim($version_match[1]),
                                'author' => isset($author_match[1]) ? trim($author_match[1]) : '',
                                'author_url' => isset($author_url_match[1]) ? trim($author_url_match[1]) : '',
                                'category' => 'module',
                                'opencart_version' => '3.0+',
                                'dependencies' => array(),
                                'upload_date' => date('Y-m-d H:i:s'),
                                'archive_structure' => strpos($filename, 'upload/') === 0 ? 'opencart' : 'direct'
                            );
                        }
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
        $this->load->model('extension/module/gdt_update_server');
        
        $json = array();
        
        if (!$this->user->hasPermission('modify', 'extension/module/gdt_update_server')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            if (isset($this->request->post['code'])) {
                $code = $this->request->post['code'];
                
                // Получаем информацию о модуле из базы данных
                $module = $this->model_extension_module_gdt_update_server->getModuleByCode($code);
                
                if ($module) {
                    // Удаляем файл модуля
                    if (file_exists($module['file_path'])) {
                        unlink($module['file_path']);
                    }
                    
                    // Удаляем директорию модуля
                    $module_dir = dirname($module['file_path']);
                    if (is_dir($module_dir)) {
                        $this->deleteDirectory($module_dir);
                    }
                    
                    // Удаляем запись из базы данных
                    $this->model_extension_module_gdt_update_server->deleteModule($module['module_id']);
                    
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
        $this->load->model('extension/module/gdt_update_server');
        
        $json = array();
        
        if (isset($this->request->post['code'])) {
            $code = $this->request->post['code'];
            
            $module = $this->model_extension_module_gdt_update_server->getModuleByCode($code);
            
            if ($module) {
                $json = $module;
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
     * Синхронизация модулей из файловой системы
     */
    public function sync() {
        $this->load->language('extension/module/gdt_update_server');
        $this->load->model('extension/module/gdt_update_server');
        
        $json = array();
        
        if (!$this->user->hasPermission('modify', 'extension/module/gdt_update_server')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            // Выполняем синхронизацию
            $synced_count = $this->model_extension_module_gdt_update_server->syncModulesFromFileSystem();
            
            $json['success'] = $this->language->get('text_sync_success');
            $json['synced_modules'] = $synced_count;
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Сканирование модулей в файловой системе
     */
    public function scan() {
        $this->load->language('extension/module/gdt_update_server');
        $this->load->model('extension/module/gdt_update_server');
        
        $json = array();
        
        if (!$this->user->hasPermission('modify', 'extension/module/gdt_update_server')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
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
                                'path' => $module_path,
                                'config_file' => $config_file
                            );
                        }
                    }
                }
            }
            
            $json['success'] = true;
            $json['modules'] = $modules;
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
        $this->load->model('extension/module/gdt_update_server');
        
        // Создаем таблицу для хранения модулей
        $this->model_extension_module_gdt_update_server->createTable();
        
        // Создаем необходимые директории
        $modules_path = $this->getServerModulesPath();
        if (!is_dir($modules_path)) {
            mkdir($modules_path, 0777, true);
        }
    }
    
    public function uninstall() {
        $this->load->model('setting/setting');
        $this->load->model('extension/module/gdt_update_server');
        
        // Удаляем настройки
        $this->model_setting_setting->deleteSetting('module_gdt_update_server');
        
        // Удаляем таблицу (по желанию - можно оставить данные)
        // $this->model_extension_module_gdt_update_server->dropTable();
    }
}
