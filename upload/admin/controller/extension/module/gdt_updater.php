<?php
class ControllerExtensionModuleGdtUpdater extends Controller
{
    private $error = array();
    private $manager;

    public function __construct($registry)
    {
        parent::__construct($registry);

        // Загружаем объединенный сервис управления модулями
        $this->load->library('gbitstudio/modules/manager');
        $this->manager = new \Gbitstudio\Modules\Manager($registry);
    }

    public function index()
    {
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
        $data['toggle_auto_update_url'] = html_entity_decode($this->url->link('extension/module/gdt_updater/toggleAutoUpdate', 'user_token=' . $this->session->data['user_token'], true));
        $data['delete_multiple_url'] = html_entity_decode($this->url->link('extension/module/gdt_updater/deleteMultiple', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // Ссылки на страницы установки модулей
        $data['install_modules_url'] = $this->url->link('extension/module/gdt_install_modules/store', 'user_token=' . $this->session->data['user_token'], true);
        $data['install_featured_url'] = $this->url->link('extension/module/gdt_install_modules/featured', 'user_token=' . $this->session->data['user_token'], true);
        $data['install_popular_url'] = $this->url->link('extension/module/gdt_install_modules/popular', 'user_token=' . $this->session->data['user_token'], true);
        $data['install_newest_url'] = $this->url->link('extension/module/gdt_install_modules/newest', 'user_token=' . $this->session->data['user_token'], true);
        $data['install_search_url'] = $this->url->link('extension/module/gdt_install_modules/search', 'user_token=' . $this->session->data['user_token'], true);

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
        try {
            $data['modules'] = $this->getModulesData();
        } catch (\Exception $e) {
            $this->log->write('GDT Updater error: ' . $e->getMessage());
            $data['error'] = $this->language->get('error_server');
            $data['modules'] = array();
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/gdt_updater', $data));
    }

    /**
     * Получение данных модулей для серверного рендеринга
     */
    private function getModulesData()
    {
        // Получаем список установленных модулей
        $installed_modules = $this->manager->getInstalledModules();

        if (empty($installed_modules)) {
            return array();
        }

        $modules = array();

        // Получаем URL сервера обновлений
        $server_url = $this->config->get('module_gdt_updater_server');

        if (empty($server_url)) {
            throw new \Exception('Server URL is not configured. Please set it in module settings.');
        }

        foreach ($installed_modules as $module) {
            $module_data = array(
                'name' => $module['module_name'] ?? $module['name'],
                'description' => $module['description'] ?? ' - ',
                'code' => $module['code'],
                'version' => $module['version'],
                'author' => $module['creator_name'] ?? '',
                'author_url' => $module['author_url'] ?? '',
                'has_update' => false,
                'new_version' => '',
                'update_url' => '',
                'delete_url' => '',
                'has_backup' => false,
                'settings_url' => '',
                'auto_update' => false
            );

            // Ссылка на настройки модуля (если есть)
            if (!empty($module['controller'])) {
                $settings_route = $module['controller'];
                $module_data['settings_url'] = $this->url->link($settings_route, 'user_token=' . $this->session->data['user_token'], true);
            }
            elseif (!empty($module['code'])) {
                $settings_route = 'extension/module/' . $module['code'];
                $module_data['settings_url'] = $this->url->link($settings_route, 'user_token=' . $this->session->data['user_token'], true);
                
                // Ссылка на удаление модуля
                $module_data['delete_url'] = $this->url->link('extension/module/gdt_updater/delete', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'], true);
            }

            if (!empty($server_url)) {
                // Получаем API-ключ
                $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';

                // Проверяем обновления
                $update_info = $this->manager->checkModuleUpdate($server_url, $module, '', $api_key);

                if ($update_info && !isset($update_info['error'])) {
                    $module_data['has_update'] = true;
                    $module_data['new_version'] = $update_info['version'];
                    $module_data['update_url'] = $this->url->link('extension/module/gdt_updater/update', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'], true);
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
    public function settings()
    {
        $this->load->language('extension/module/gdt_updater');

        $json = array();

        $json['module_gdt_updater_server'] = $this->config->get('module_gdt_updater_server');
        $json['module_gdt_updater_api_key'] = $this->config->get('module_gdt_updater_api_key');
        $json['module_gdt_updater_status'] = $this->config->get('module_gdt_updater_status');

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Сохранение настроек модуля
     */
    public function saveSettings()
    {
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
    public function getModules()
    {
        $this->load->language('extension/module/gdt_updater');

        $json = array();

        // Получаем список установленных модулей
        $installed_modules = $this->manager->getInstalledModules();

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
                'has_backup' => false
            );

            if (!empty($server_url)) {
                // Получаем API-ключ
                $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';

                // Проверяем обновления
                $update_info = $this->manager->checkModuleUpdate($server_url, $module, '', $api_key);

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
            }

            $json['modules'][] = $module_data;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function check()
    {
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
            $modules = $this->manager->getInstalledModules();

            if (!empty($modules)) {
                $json['modules'] = array();

                foreach ($modules as $module) {
                    // Получаем API-ключ
                    $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';

                    $update_info = $this->manager->checkModuleUpdate($server_url, $module, '', $api_key);
                    $this->log->write('GDT Updater: Checking updates for module ' . $module['code'] . ' - Current version: ' . $module['version']);
                    $this->log->write('GDT Updater: Update info: ' . json_encode($update_info), true);

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

    public function update()
    {
        $this->load->language('extension/module/gdt_updater');

        $json = array();

        if (isset($this->request->get['code'])) {
            $code = $this->request->get['code'];

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
                    $json['error'] = $this->language->get('error_server');
                    $this->response->addHeader('Content-Type: application/json');
                    $this->response->setOutput(json_encode($json));
                    return;
                }
            }

            if (empty($server_url)) {
                $json['error'] = $this->language->get('error_server');
            } else {
                // Получаем информацию о модуле
                $module = $this->manager->getModuleByCode($code);

                if ($module) {
                    // Получаем API-ключ
                    $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
                    // Проверяем обновление
                    $update_info = $this->manager->checkModuleUpdate($server_url, $module, '', $api_key);
        

                    // Проверяем на ошибки curl
                    if (is_array($update_info) && isset($update_info['error'])) {
                        if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                            $json['error'] = sprintf($this->language->get('error_curl'), $update_info['message']);
                        } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                            $json['error'] = sprintf($this->language->get('error_http'), $update_info['code']);
                        }
                    } else if ($update_info) {
                        // Логируем начало процесса обновления
                        $this->log->write('GDT Updater: Starting update for module ' . $code . ' from version ' . $module['version'] . ' to ' . $update_info['version']);
                        
                        try {
                            // Скачиваем и устанавливаем обновление
                            $result = $this->manager->downloadAndInstallUpdate($server_url, $module, $update_info, '', $api_key);

                            if ($result === true) {
                                // Очищаем все кэши OpenCart
                                $this->manager->clearCache();
                                
                                // Проверяем, что версия действительно обновилась
                                $updated_module = $this->manager->getModuleByCode($code);
                                if ($updated_module && $updated_module['version'] === $update_info['version']) {
                                    $json['success'] = sprintf($this->language->get('text_update_success'), $module['name'] ?? $module['module_name']) . ' (v' . $update_info['version'] . ')';
                                } else {
                                    $json['warning'] = 'Файлы скопированы, но версия модуля не обновилась. Возможно, требуется ручная проверка.';
                                }
                            } else {
                                $json['error'] = $result;
                            }
                        } catch (Exception $e) {
                            $json['error'] = 'Ошибка при обновлении: ' . $e->getMessage();
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

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/gdt_updater')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        // Убираем обязательную проверку сервера, так как он может быть задан по умолчанию
        // if (empty($this->request->post['module_gdt_updater_server'])) {
        //     $this->error['warning'] = $this->language->get('error_server_empty');
        // }

        return !$this->error;
    }


    /**
     * Переключение автообновления для модуля
     */
    public function toggleAutoUpdate()
    {
        $this->load->language('extension/module/gdt_updater');
        $this->load->model('setting/setting');

        $json = array();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['module_code'])) {
            $module_code = $this->request->post['module_code'];
            $auto_update = isset($this->request->post['auto_update']) ? (int) $this->request->post['auto_update'] : 0;

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

    /**
     * Удаление модуля
     */
    public function delete()
    {
        $this->load->language('extension/module/gdt_updater');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/gdt_updater')) {
            $json['error'] = $this->language->get('error_permission');
        } elseif (isset($this->request->get['code'])) {
            $code = $this->request->get['code'];

            try {
                // Проверяем, что модуль существует
                $module = $this->manager->getModuleByCode($code);
                if (!$module) {
                    $json['error'] = 'Модуль ' . $code . ' не найден';
                } else {
                    // Логируем начало процесса удаления
                    $this->log->write('GDT Updater: Starting deletion for module ' . $code);
                    
                    // Удаляем модуль
                    $result = $this->manager->uninstall($code);

                    if ($result === true) {
                        // Очищаем все кэши OpenCart
                        $this->manager->clearCache();
                        
                        $json['success'] = sprintf('Модуль %s успешно удален', $module['module_name'] ?? $module['name'] ?? $code);
                        
                        $this->log->write('GDT Updater: Successfully deleted module ' . $code);
                    } else {
                        $json['error'] = is_string($result) ? $result : 'Неизвестная ошибка при удалении модуля';
                    }
                }
            } catch (Exception $e) {
                $json['error'] = 'Ошибка при удалении модуля: ' . $e->getMessage();
                $this->log->write('GDT Updater error: ' . $e->getMessage());
            }
        } else {
            $json['error'] = 'Не указан код модуля для удаления';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Массовое удаление модулей
     */
    public function deleteMultiple()
    {
        $this->load->language('extension/module/gdt_updater');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/gdt_updater')) {
            $json['error'] = $this->language->get('error_permission');
        } elseif (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['modules']) && is_array($this->request->post['modules'])) {
            $modules = $this->request->post['modules'];
            $deleted = array();
            $errors = array();

            foreach ($modules as $code) {
                try {
                    // Проверяем, что модуль существует
                    $module = $this->manager->getModuleByCode($code);
                    if (!$module) {
                        $errors[] = 'Модуль ' . $code . ' не найден';
                        continue;
                    }

                    // Логируем начало процесса удаления
                    $this->log->write('GDT Updater: Starting deletion for module ' . $code);
                    
                    // Удаляем модуль
                    $result = $this->manager->uninstall($code);

                    if ($result === true) {
                        $deleted[] = $module['module_name'] ?? $module['name'] ?? $code;
                        $this->log->write('GDT Updater: Successfully deleted module ' . $code);
                    } else {
                        $errors[] = $code . ': ' . (is_string($result) ? $result : 'Неизвестная ошибка');
                    }
                } catch (Exception $e) {
                    $errors[] = $code . ': ' . $e->getMessage();
                    $this->log->write('GDT Updater error: ' . $e->getMessage());
                }
            }

            // Очищаем все кэши OpenCart после всех операций
            if (!empty($deleted)) {
                $this->manager->clearCache();
            }

            if (!empty($deleted) && empty($errors)) {
                $json['success'] = sprintf('Успешно удалено модулей: %d (%s)', count($deleted), implode(', ', $deleted));
            } elseif (!empty($deleted) && !empty($errors)) {
                $json['warning'] = sprintf('Удалено модулей: %d (%s). Ошибки: %s', count($deleted), implode(', ', $deleted), implode('; ', $errors));
            } else {
                $json['error'] = 'Ошибки при удалении: ' . implode('; ', $errors);
            }
        } else {
            $json['error'] = 'Не указаны модули для удаления';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }




    /**
     * Установка модуля
     */
    public function install() {
        // Создаем таблицу для хранения модулей
        $this->manager->createModulesTable();

        // Добавляем события
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent('auto_update_menu', 'admin/view/common/column_left/before', 'extension/module/gdt_updater/menuAdmin');
        //получаем json 
        $gdt_updater_json = $this->manager->getJson('gdt_updater');
        if ($gdt_updater_json) {
        $this->manager->saveModuleToDatabase(
            'gdt_updater', 
            $gdt_updater_json['version'] ?? '1.0.0', 
            $gdt_updater_json
        );
    }

        // Логируем успешную установку
        $this->log->write('GDT Updater: Таблица для хранения модулей успешно создана и события добавлены.');
    }

    public function uninstall()
    {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('auto_update_check');
        $this->model_setting_event->deleteEventByCode('auto_update_menu');

        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_gdt_updater');

        // Удаляем Dashboard модуль при деинсталляции
        $this->load->model('setting/extension');
        $this->model_setting_extension->uninstall('dashboard', 'gdt_updater');

        // Удаляем настройки Dashboard модуля
        $this->model_setting_setting->deleteSetting('dashboard_gdt_updater');

        // Переставляем все остальные dashboard модули с sort_order > 1 на одну позицию вверх
        // $this->db->query("UPDATE " . DB_PREFIX . "setting 
        //                  SET value = (CAST(value AS UNSIGNED) - 1) 
        //                  WHERE `key` LIKE '%_sort_order' 
        //                  AND `code` LIKE 'dashboard_%' 
        //                  AND `code` != 'dashboard_gdt_updater' 
        //                  AND CAST(value AS UNSIGNED) > 1");
    }



    public function checkUpdates()
    {


        $server_url = $this->config->get('module_gdt_updater_server');
        $current_version = '1.0.0'; // Текущая версия модуля

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $server_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('version' => $current_version));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['update_available']) && $data['update_available']) {
                $this->session->data['success'] = 'Доступно обновление модуля!';
            }
        }

        $this->session->data['success'] = 'Проверка обновлений завершена.';

    }

    public function menuAdmin($route, &$data, $menu_id = 0)
    {
        // Проверяем, что это админская панель
        $this->load->language('extension/module/gdt_updater', 'gdt_updater');
        $this->load->language('extension/module/gdt_install_modules', 'gdt_install_modules');
        // Добавляем ссылку на страницу обновлений в меню

        $children = array();
        //install

        $children[] = array(
            'name' => $this->language->get('gdt_install_modules')->get('heading_title'),
            'href' => $this->url->link('extension/module/gdt_install_modules', 'user_token=' . $this->session->data['user_token'], true),
            'children' => array()
        );

        $children[] = array(
            'name' => $this->language->get('gdt_updater')->get('text_update'),
            'href' => $this->url->link('extension/module/gdt_updater', 'user_token=' . $this->session->data['user_token'], true),
            'children' => array()
        );

        $data['menus'][] = array(
            'id' => 'menu-gdt-updater',
            'name' => $this->language->get('gdt_updater')->get('heading_title'),
            'href'     => '',
                'icon' => 'fa fa-refresh',
                'children' => $children,
            ); 


        foreach ($data['menus'] as &$menu) {
            if ($menu['id'] == 'menu-extension') {
                $menu['children'] = array_merge($menu['children'], $children);
                break;
            }
        }
   
    }



}
