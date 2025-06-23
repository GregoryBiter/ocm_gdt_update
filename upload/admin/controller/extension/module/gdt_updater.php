<?php
class ControllerExtensionModuleGdtUpdater extends Controller {
    private $error = array();
    private $updater;
    
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Загружаем сервис обновления
        $this->load->library('gbitstudio/updater/service/updater');
        $this->updater = new \Gbitstudio\Updater\Service\Updater($registry);
    }
    
    public function index() {
        $this->load->language('extension/module/gdt_updater');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->load->model('setting/setting');
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_gdt_updater', $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            
            //$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }
        
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }
        
        if (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
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
        
        if (isset($this->request->post['module_gdt_updater_client_id'])) {
            $data['module_gdt_updater_client_id'] = $this->request->post['module_gdt_updater_client_id'];
        } else {
            $data['module_gdt_updater_client_id'] = $this->config->get('module_gdt_updater_client_id');
        }
        
        if (isset($this->request->post['module_gdt_updater_api_key'])) {
            $data['module_gdt_updater_api_key'] = $this->request->post['module_gdt_updater_api_key'];
        } else {
            $data['module_gdt_updater_api_key'] = $this->config->get('module_gdt_updater_api_key');
        }
        
        if (isset($this->request->post['module_gdt_updater_status'])) {
            $data['module_gdt_updater_status'] = $this->request->post['module_gdt_updater_status'];
        } else {
            $data['module_gdt_updater_status'] = $this->config->get('module_gdt_updater_status');
        }
        
        // Получаем список установленных модулей
        $data['installed_modules'] = $this->updater->getInstalledModules();
        
        // Проверяем обновления для модулей
        $data['module_updates'] = array();
        $data['module_backups'] = array();
        $data['module_backups'] = array();
        
        // Получаем URL сервера обновлений
        $server_url = $this->config->get('module_gdt_updater_server');
        
        // Проверяем, не пустой ли URL и устанавливаем значение по умолчанию, если нужно
        if (empty($server_url)) {
            // Для локальной разработки используем путь к локальному серверу
            $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
        }
        
        if (!empty($server_url) && !empty($data['installed_modules'])) {
            foreach ($data['installed_modules'] as $module) {
                $update_info = $this->updater->checkModuleUpdate($server_url, $module);
                
                // Проверяем наличие резервной копии для модуля
                $backups = $this->updater->getModuleBackups($module['code']);
                $has_backup = !empty($backups);
                $backup_dir = '';
                
                if ($has_backup && isset($backups[0]['dir'])) {
                    $backup_dir = $backups[0]['dir'];
                }
                
                // Сохраняем информацию о резервной копии
                $data['module_backups'][$module['code']] = array(
                    'has_backup' => $has_backup,
                    'backup_dir' => $backup_dir,
                    'restore_url' => $has_backup ? $this->url->link('extension/module/gdt_updater/restore', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'] . '&backup=' . $backup_dir, true) : ''
                );
                
                // Проверяем наличие резервной копии для модуля
                $backups = $this->updater->getModuleBackups($module['code']);
                $has_backup = !empty($backups);
                $backup_dir = '';
                
                if ($has_backup && isset($backups[0]['dir'])) {
                    $backup_dir = $backups[0]['dir'];
                }
                
                // Сохраняем информацию о резервной копии
                $data['module_backups'][$module['code']] = array(
                    'has_backup' => $has_backup,
                    'backup_dir' => $backup_dir,
                    'restore_url' => $has_backup ? $this->url->link('extension/module/gdt_updater/restore', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'] . '&backup=' . $backup_dir, true) : ''
                );
                
                // Обрабатываем возможные ошибки curl
                if (is_array($update_info) && isset($update_info['error'])) {
                    if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                        $data['error_curl'] = sprintf($this->language->get('error_curl'), $update_info['message']);
                    } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                        $data['error_http'] = sprintf($this->language->get('error_http'), $update_info['code']);
                    }
                    // Прекращаем цикл при первой ошибке
                    break;
                } else {
                    // Добавляем информацию об обновлении
                    $data['module_updates'][$module['code']] = array(
                        'has_update' => $update_info ? true : false,
                        'new_version' => $update_info ? $update_info['version'] : '',
                        'update_url' => $update_info ? $this->url->link('extension/module/gdt_updater/update', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'], true) : ''
                    );
                }
            }
        }
        
        $data['check_updates'] = $this->url->link('extension/module/gdt_updater/check', 'user_token=' . $this->session->data['user_token'], true);
        $data['user_token'] = $this->session->data['user_token'];
        
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
        
        // Проверяем, не пустой ли URL и устанавливаем значение по умолчанию, если нужно
        if (empty($server_url)) {
            // Проверяем наличие переменной окружения для Docker
            $docker_server_url = getenv('GDT_UPDATE_SERVER');
            if ($docker_server_url) {
                $server_url = $docker_server_url;
            } 
            // Если переменной окружения нет, используем HTTP_SERVER
            elseif (defined('HTTP_SERVER')) {
                $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
            }
            // Если ничего не подошло, используем значение по умолчанию
            else {
                $server_url = 'https://example.com/server';
            }
        }
        
        if (empty($server_url)) {
            $json['error'] = $this->language->get('error_server');
        } else {
            // Получаем список установленных модулей
            $modules = $this->updater->getInstalledModules();
            
            if (!empty($modules)) {
                $json['modules'] = array();
                
                foreach ($modules as $module) {
                    // Получаем API-ключ и client_id
                    $client_id = $this->config->get('module_gdt_updater_client_id') ?: 'default';
                    $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
                    
                    $update_info = $this->updater->checkModuleUpdate($server_url, $module, $client_id, $api_key);
                    
                    // Проверяем на ошибки curl
                    if (is_array($update_info) && isset($update_info['error'])) {
                        if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                            $json['error'] = sprintf($this->language->get('error_curl'), $update_info['message']);
                            break;
                        } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                            $json['error'] = sprintf($this->language->get('error_http'), $update_info['code']);
                            break;
                        }
                    } else if ($update_info) {
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
            
            // Проверяем, не пустой ли URL и устанавливаем значение по умолчанию, если нужно
            if (empty($server_url)) {
                // Для локальной разработки используем путь к локальному серверу
                $server_url = 'https://example.com/server';
                
                // Если есть определенная константа HTTP_SERVER, используем ее
                if (defined('HTTP_SERVER')) {
                    $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
                }
            }
            
            if (empty($server_url)) {
                $json['error'] = $this->language->get('error_server');
            } else {
                // Получаем информацию о модуле
                $module = $this->updater->getModuleByCode($code);
                
                if ($module) {
                    // Получаем API-ключ и client_id
                    $client_id = $this->config->get('module_gdt_updater_client_id') ?: 'default';
                    $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
                    
                    // Проверяем обновление
                    $update_info = $this->updater->checkModuleUpdate($server_url, $module, $client_id, $api_key);
                    
                    // Проверяем на ошибки curl
                    if (is_array($update_info) && isset($update_info['error'])) {
                        if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                            $json['error'] = sprintf($this->language->get('error_curl'), $update_info['message']);
                        } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                            $json['error'] = sprintf($this->language->get('error_http'), $update_info['code']);
                        }
                    } else if ($update_info) {
                        // Получаем API-ключ и client_id
                        $client_id = $this->config->get('module_gdt_updater_client_id') ?: 'default';
                        $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
                        
                        // Скачиваем и устанавливаем обновление
                        $result = $this->updater->downloadAndInstallUpdate($server_url, $module, $update_info, $client_id, $api_key);
                        
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
    
    /**
     * Страница просмотра резервных копий модуля
     */
    /**
     * Обработка восстановления модуля из резервной копии
     */
    public function restore() {
        $this->load->language('extension/module/gdt_updater');
        
        $json = array();
        
        // Проверяем наличие необходимых параметров
        if (isset($this->request->get['code']) && isset($this->request->get['backup'])) {
            $code = $this->request->get['code'];
            $backup = $this->request->get['backup'];
            
            // Проверяем, что модуль существует
            $module = $this->updater->getModuleByCode($code);
            
            if ($module) {
                // Восстанавливаем модуль из резервной копии
                $result = $this->updater->restoreModuleFromBackup($code, $backup);
                
                if ($result === true) {
                    // Получаем информацию о резервной копии для сообщения
                    $backups = $this->updater->getModuleBackups($code);
                    $backup_info = null;
                    
                    foreach ($backups as $backup_item) {
                        if ($backup_item['dir'] === $backup) {
                            $backup_info = $backup_item['info'];
                            break;
                        }
                    }
                    
                    if ($backup_info) {
                        $json['success'] = sprintf($this->language->get('text_restore_success'), $module['name'], $backup_info['from_version']);
                    } else {
                        $json['success'] = sprintf($this->language->get('text_restore_success'), $module['name'], '');
                    }
                } else {
                    $json['error'] = sprintf($this->language->get('error_restore'), $result);
                }
            } else {
                $json['error'] = $this->language->get('error_module_not_found');
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
