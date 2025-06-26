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
        
        // Базовые данные для шаблона
        $data = array();
        
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
    
        // URL-адреса для AJAX запросов
        $data['settings_url'] = $this->url->link('extension/module/gdt_updater/settings', 'user_token=' . $this->session->data['user_token'], true);
        $data['save_settings_url'] = $this->url->link('extension/module/gdt_updater/saveSettings', 'user_token=' . $this->session->data['user_token'], true);
        $data['check_updates'] = $this->url->link('extension/module/gdt_updater/check', 'user_token=' . $this->session->data['user_token'], true);
        $data['toggle_auto_update_url'] = $this->url->link('extension/module/gdt_updater/toggleAutoUpdate', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $decode = [
            'settings_url',
            'save_settings_url',
            'modules_url',
            'check_updates',
            'cancel'
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $decode)) {
                $data[$key] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            }
        }
        
        $data['user_token'] = $this->session->data['user_token'];
        
        // Получаем данные модулей для серверного рендеринга
        $data['modules'] = $this->getModulesData();
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/module/gdt_updater', $data));
    }
    
    /**
     * Получение данных модулей для серверного рендеринга
     */
    private function getModulesData() {
        // Получаем список установленных модулей
        $installed_modules = $this->updater->getInstalledModules();
        
        if (empty($installed_modules)) {
            return array();
        }
        
        $modules = array();
        
        // Получаем URL сервера обновлений
        $server_url = $this->config->get('module_gdt_updater_server');
        
        if (empty($server_url)) {
            $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
        }
        
        foreach ($installed_modules as $module) {
            $module_data = array(
                'name' => $module['name'],
                'description' => $module['description'] ?? 'Описание отсутствует',
                'code' => $module['code'],
                'version' => $module['version'],
                'author' => $module['author'] ?? '',
                'author_url' => $module['author_url'] ?? '',
                'has_update' => false,
                'new_version' => '',
                'update_url' => '',
                'has_backup' => false,
                'restore_url' => '',
                'settings_url' => '',
                'auto_update' => false
            );
            
            // Ссылка на настройки модуля (если есть)
            if (!empty($module['code'])) {
                $settings_route = 'extension/module/' . $module['code'];
                $module_data['settings_url'] = $this->url->link($settings_route, 'user_token=' . $this->session->data['user_token'], true);
            }
            
            if (!empty($server_url)) {
                // Получаем API-ключ и client_id
                $client_id = $this->config->get('module_gdt_updater_client_id') ?: 'default';
                $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
                
                // Проверяем обновления
                $update_info = $this->updater->checkModuleUpdate($server_url, $module, $client_id, $api_key);
                
                if ($update_info && !isset($update_info['error'])) {
                    $module_data['has_update'] = true;
                    $module_data['new_version'] = $update_info['version'];
                    $module_data['update_url'] = $this->url->link('extension/module/gdt_updater/update', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'], true);
                }
                
                // Проверяем наличие резервной копии
                $backups = $this->updater->getModuleBackups($module['code']);
                if (!empty($backups)) {
                    $module_data['has_backup'] = true;
                    $backup_dir = $backups[0]['dir'];
                    $module_data['restore_url'] = $this->url->link('extension/module/gdt_updater/restore', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'] . '&backup=' . $backup_dir, true);
                }
            }
            
            // Получаем настройки автообновления (можно добавить в конфиг)
            $module_data['auto_update'] = $this->config->get('module_gdt_updater_auto_' . $module['code']) ? true : false;
            
            $modules[] = $module_data;
        }
        
        return $modules;
    }
    
    /**
     * Получение настроек модуля
     */
    public function settings() {
        $this->load->language('extension/module/gdt_updater');
        
        $json = array();
        
        $json['module_gdt_updater_server'] = $this->config->get('module_gdt_updater_server');
        $json['module_gdt_updater_client_id'] = $this->config->get('module_gdt_updater_client_id');
        $json['module_gdt_updater_api_key'] = $this->config->get('module_gdt_updater_api_key');
        $json['module_gdt_updater_status'] = $this->config->get('module_gdt_updater_status');
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Сохранение настроек модуля
     */
    public function saveSettings() {
        $this->load->language('extension/module/gdt_updater');
        $this->load->model('setting/setting');
        
        $json = array();
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_gdt_updater', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        } else {
            $json['error'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Получение списка модулей с информацией об обновлениях
     */
    public function getModules() {
        $this->load->language('extension/module/gdt_updater');
        
        $json = array();
        
        // Получаем список установленных модулей
        $installed_modules = $this->updater->getInstalledModules();
        
        if (empty($installed_modules)) {
            $json['error'] = $this->language->get('error_no_modules');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        
        $json['modules'] = array();
        
        // Получаем URL сервера обновлений
        $server_url = $this->config->get('module_gdt_updater_server');
        
        if (empty($server_url)) {
            $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
        }
        
        foreach ($installed_modules as $module) {
            $module_data = array(
                'name' => $module['name'],
                'description' => $module['description'],
                'code' => $module['code'],
                'version' => $module['version'],
                'has_update' => false,
                'new_version' => '',
                'update_url' => '',
                'has_backup' => false,
                'restore_url' => ''
            );
            
            if (!empty($server_url)) {
                // Получаем API-ключ и client_id
                $client_id = $this->config->get('module_gdt_updater_client_id') ?: 'default';
                $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
                
                // Проверяем обновления
                $update_info = $this->updater->checkModuleUpdate($server_url, $module, $client_id, $api_key);
                
                if (is_array($update_info) && isset($update_info['error'])) {
                    // Обрабатываем ошибки curl/http
                    if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                        $json['error_curl'] = sprintf($this->language->get('error_curl'), $update_info['message']);
                    } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                        $json['error_http'] = sprintf($this->language->get('error_http'), $update_info['code']);
                    }
                } else if ($update_info) {
                    $module_data['has_update'] = true;
                    $module_data['new_version'] = $update_info['version'];
                    $module_data['update_url'] = $this->url->link('extension/module/gdt_updater/update', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'], true);
                }
                
                // Проверяем наличие резервной копии
                $backups = $this->updater->getModuleBackups($module['code']);
                if (!empty($backups)) {
                    $module_data['has_backup'] = true;
                    $backup_dir = $backups[0]['dir'];
                    $module_data['restore_url'] = $this->url->link('extension/module/gdt_updater/restore', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'] . '&backup=' . $backup_dir, true);
                }
            }
            
            $json['modules'][] = $module_data;
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
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
                            // Не прерываем цикл, чтобы попробовать проверить другие модули
                        } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                            $json['error'] = sprintf($this->language->get('error_http'), $update_info['code']);
                            // Не прерываем цикл, чтобы попробовать проверить другие модули
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
    
    /**
     * Переключение автообновления для модуля
     */
    public function toggleAutoUpdate() {
        $this->load->language('extension/module/gdt_updater');
        $this->load->model('setting/setting');
        
        $json = array();
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['module_code'])) {
            $module_code = $this->request->post['module_code'];
            $auto_update = isset($this->request->post['auto_update']) ? (int)$this->request->post['auto_update'] : 0;
            
            // Сохраняем настройку автообновления для конкретного модуля
            $setting_key = 'module_gdt_updater_auto_' . $module_code;
            $this->model_setting_setting->editSetting('module_gdt_updater', array($setting_key => $auto_update));
            
            $json['success'] = 'Настройки автообновления сохранены';
        } else {
            $json['error'] = 'Неверные параметры запроса';
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
