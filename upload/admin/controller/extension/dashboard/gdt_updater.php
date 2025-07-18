<?php
/**
 * GDT Updater Dashboard Controller
 * 
 * @property object $load
 * @property object $language  
 * @property object $document
 * @property object $session
 * @property object $response
 * @property object $url
 * @property object $config
 * @property object $user
 * @property object $registry
 * @property object $request
 * @property object $model_setting_setting
 */
class ControllerExtensionDashboardGdtUpdater extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/dashboard/gdt_updater');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('dashboard_gdt_updater', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true));
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
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/dashboard/gdt_updater', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/dashboard/gdt_updater', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true);

        if (isset($this->request->post['dashboard_gdt_updater_width'])) {
            $data['dashboard_gdt_updater_width'] = $this->request->post['dashboard_gdt_updater_width'];
        } else {
            $data['dashboard_gdt_updater_width'] = $this->config->get('dashboard_gdt_updater_width');
        }

        $data['columns'] = array();

        for ($i = 3; $i <= 12; $i++) {
            $data['columns'][] = $i;
        }

        if (isset($this->request->post['dashboard_gdt_updater_status'])) {
            $data['dashboard_gdt_updater_status'] = $this->request->post['dashboard_gdt_updater_status'];
        } else {
            $data['dashboard_gdt_updater_status'] = $this->config->get('dashboard_gdt_updater_status');
        }

        if (isset($this->request->post['dashboard_gdt_updater_sort_order'])) {
            $data['dashboard_gdt_updater_sort_order'] = $this->request->post['dashboard_gdt_updater_sort_order'];
        } else {
            $data['dashboard_gdt_updater_sort_order'] = $this->config->get('dashboard_gdt_updater_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/dashboard/gdt_updater_form', $data));
    }

    public function dashboard() {
        $this->load->language('extension/dashboard/gdt_updater');
        
        $data = array();
        
        // Базовые данные
        $data['user_token'] = isset($this->session->data['user_token']) ? $this->session->data['user_token'] : '';
        $data['updates'] = array();
        $data['error_message'] = '';
        $data['success_message'] = '';
        $data['no_updates'] = true;
        $data['total_updates'] = 0;
        $data['auto_updates_enabled'] = false;
        $data['auto_update_performed'] = false;
        
        // Ссылки
        $data['href_check_updates'] = $this->url->link('extension/module/gdt_updater', 'user_token=' . $data['user_token'], true);
        $data['href_settings'] = $this->url->link('extension/module/gdt_updater', 'user_token=' . $data['user_token'], true);

        try {
            // Загружаем менеджер модулей
            $this->load->library('gbitstudio/modules/manager');
            $manager = new \Gbitstudio\Modules\Manager($this->registry);
            
            // Получаем список установленных модулей
            $installed_modules = $manager->getInstalledModules();
            
            if (empty($installed_modules)) {
                $data['error_message'] = $this->language->get('error_no_modules') ?: 'Нет установленных модулей';
                return $this->load->view('extension/dashboard/gdt_updater_info', $data);
            }
            
            // Получаем URL сервера обновлений
            $server_url = $this->config->get('module_gdt_updater_server');
            
            if (empty($server_url)) {
                // Проверяем переменную окружения для Docker
                $docker_server_url = getenv('GDT_UPDATE_SERVER');
                if ($docker_server_url) {
                    $server_url = $docker_server_url;
                } elseif (defined('HTTP_SERVER')) {
                    $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
                } else {
                    $server_url = 'https://example.com/server';
                }
            }
            
            if (empty($server_url)) {
                $data['error_message'] = $this->language->get('error_server') ?: 'Не настроен сервер обновлений';
                return $this->load->view('extension/dashboard/gdt_updater_info', $data);
            }
            
            // Получаем API-ключ
            $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
            
            $available_updates = array();
            $auto_update_modules = array();
            
            // Проверяем обновления для каждого модуля
            foreach ($installed_modules as $module) {
                $update_info = $manager->checkModuleUpdate($server_url, $module, '', $api_key);
                
                // Проверяем на ошибки
                if (is_array($update_info) && isset($update_info['error'])) {
                    if ($update_info['error'] == 'curl') {
                        $data['error_message'] = 'Ошибка cURL: ' . ($update_info['message'] ?: 'Неизвестная ошибка сети');
                    } elseif ($update_info['error'] == 'http') {
                        $data['error_message'] = 'Ошибка HTTP: ' . ($update_info['code'] ?: 'Неизвестная ошибка сервера');
                    }
                    continue;
                }
                
                // Если есть обновление
                if ($update_info && isset($update_info['status']) && $update_info['status'] == 'update_available') {
                    $module_update = array(
                        'name' => $module['module_name'] ?? $module['name'],
                        'code' => $module['code'],
                        'current_version' => $module['version'],
                        'new_version' => $update_info['version'],
                        'description' => $update_info['description'] ?? '',
                        'auto_update' => false
                    );
                    
                    // Проверяем настройку автообновления для модуля
                    $auto_update_setting = $this->config->get('module_gdt_updater_auto_' . $module['code']);
                    if ($auto_update_setting) {
                        $module_update['auto_update'] = true;
                        $auto_update_modules[] = $module_update;
                        $data['auto_updates_enabled'] = true;
                    }
                    
                    $available_updates[] = $module_update;
                }
            }
            
            // Устанавливаем данные об обновлениях
            $data['updates'] = $available_updates;
            $data['total_updates'] = count($available_updates);
            $data['no_updates'] = ($data['total_updates'] == 0);
            
            // Выполняем автообновления если включены
            if (!empty($auto_update_modules) && $this->config->get('module_gdt_updater_status')) {
                $auto_updated = array();
                $auto_update_errors = array();
                
                foreach ($auto_update_modules as $module_update) {
                    // Получаем полную информацию о модуле
                    $module = $manager->getModuleByCode($module_update['code']);
                    if ($module) {
                        // Получаем информацию об обновлении еще раз для получения download_url
                        $update_info = $manager->checkModuleUpdate($server_url, $module, '', $api_key);
                        
                        if ($update_info && isset($update_info['status']) && $update_info['status'] == 'update_available') {
                            // Используем метод downloadAndInstallUpdate для обновления
                            if (method_exists($manager, 'downloadAndInstallUpdate')) {
                                $update_result = $manager->downloadAndInstallUpdate($server_url, $module, $update_info, '', $api_key);
                                
                                if ($update_result === true) {
                                    $auto_updated[] = $module_update['name'];
                                } else {
                                    $auto_update_errors[] = $module_update['name'] . ': ' . ($update_result ?: 'Неизвестная ошибка');
                                }
                            } else {
                                // Если метода downloadAndInstallUpdate нет, просто отмечаем как ошибку
                                $auto_update_errors[] = $module_update['name'] . ': Метод обновления недоступен';
                            }
                        }
                    }
                }
                
                if (!empty($auto_updated)) {
                    $data['auto_update_performed'] = true;
                    $data['success_message'] = 'Автообновления выполнены для модулей: ' . implode(', ', $auto_updated);
                    
                    // Обновляем список доступных обновлений после автообновления
                    $data['updates'] = array_filter($data['updates'], function($update) use ($auto_updated) {
                        return !in_array($update['name'], $auto_updated);
                    });
                    $data['total_updates'] = count($data['updates']);
                    $data['no_updates'] = ($data['total_updates'] == 0);
                }
                
                if (!empty($auto_update_errors)) {
                    $data['error_message'] = 'Ошибки автообновления: ' . implode('; ', $auto_update_errors);
                }
            }
            
            // Устанавливаем сообщения в зависимости от результата
            if ($data['no_updates'] && empty($data['error_message']) && empty($data['success_message'])) {
                $data['success_message'] = $this->language->get('text_no_updates') ?: 'Все модули обновлены до последних версий';
            } elseif ($data['total_updates'] > 0 && empty($data['error_message']) && empty($data['success_message'])) {
                $data['success_message'] = sprintf('Найдено %d обновлений', $data['total_updates']);
            }
            
        } catch (Exception $e) {
            $data['error_message'] = 'Ошибка: ' . $e->getMessage();
        }

        return $this->load->view('extension/dashboard/gdt_updater_info', $data);
    }

    /**
     * Проверка обновлений для Dashboard через AJAX
     */
    public function checkUpdates() {
        $this->load->language('extension/dashboard/gdt_updater');
        
        $json = array();
        
        try {
            // Загружаем менеджер модулей
            $this->load->library('gbitstudio/modules/manager');
            $manager = new \Gbitstudio\Modules\Manager($this->registry);
            
            // Получаем список установленных модулей
            $installed_modules = $manager->getInstalledModules();
            
            if (empty($installed_modules)) {
                $json['error'] = 'Нет установленных модулей';
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
                return;
            }
            
            // Получаем URL сервера обновлений
            $server_url = $this->config->get('module_gdt_updater_server');
            
            if (empty($server_url)) {
                $docker_server_url = getenv('GDT_UPDATE_SERVER');
                if ($docker_server_url) {
                    $server_url = $docker_server_url;
                } elseif (defined('HTTP_SERVER')) {
                    $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
                } else {
                    $server_url = 'https://example.com/server';
                }
            }
            
            if (empty($server_url)) {
                $json['error'] = 'Не настроен сервер обновлений';
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
                return;
            }
            
            // Получаем API-ключ
            $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
            
            $available_updates = array();
            
            // Проверяем обновления для каждого модуля
            foreach ($installed_modules as $module) {
                $update_info = $manager->checkModuleUpdate($server_url, $module, '', $api_key);
                
                // Проверяем на ошибки
                if (is_array($update_info) && isset($update_info['error'])) {
                    continue;
                }
                
                // Если есть обновление
                if ($update_info && isset($update_info['status']) && $update_info['status'] == 'update_available') {
                    $available_updates[] = array(
                        'name' => $module['module_name'] ?? $module['name'],
                        'code' => $module['code'],
                        'current_version' => $module['version'],
                        'new_version' => $update_info['version']
                    );
                }
            }
            
            $json['updates'] = $available_updates;
            $json['total_updates'] = count($available_updates);
            $json['success'] = count($available_updates) > 0 ? 
                sprintf('Найдено %d обновлений', count($available_updates)) : 
                'Все модули обновлены до последних версий';
                
        } catch (Exception $e) {
            $json['error'] = 'Ошибка: ' . $e->getMessage();
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/dashboard/gdt_updater')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
