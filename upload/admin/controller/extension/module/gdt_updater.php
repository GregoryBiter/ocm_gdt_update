<?php

use Gbitstudio\Modules\Services\LoggerService;

/**
 * @property Cart\Session $session
 * @property Response $response
 * @property Loader $load
 * @property Request $request
 * @property Document $document
 * @property Config $config
 * @property Language $language
 * @property DB $db
 * @property Cart\Cache $cache
 * @property Cart\Url $url
 * @property Cart\User $user
 * @property Registry $registry
 * @property ModelSettingSetting $model_setting_setting
 * @property ModelSettingEvent $model_setting_event
 * @property ModelExtensionModuleGdtUpdater $model_extension_module_gdt_updater
 * @property ModelSettingExtension $model_setting_extension
 */
class ControllerExtensionModuleGdtUpdater extends Controller
{
    /** @var array */
    private $error = array();

    /**
     * Отримує фабрику сервісів
     *
     * @return \Gbitstudio\Modules\ServiceFactory
     */
    private function getServiceFactory(): \Gbitstudio\Modules\ServiceFactory
    {
        if (!$this->registry->has('gb_modules')) {
            $this->registry->set('gb_modules', new \Gbitstudio\Modules\ServiceFactory($this->registry));
        }
        return $this->registry->get('gb_modules');
    }

    public function index()
    {
        $this->response->redirect($this->url->link('extension/module/gdt_install_modules/installed', 'user_token=' . $this->session->data['user_token'], true));
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
        $json['module_gdt_updater_api_log'] = $this->config->get('module_gdt_updater_api_log');
        $json['module_gdt_updater_auto_modules'] = $this->config->get('module_gdt_updater_auto_modules') ?: array();

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
        $installed_modules = $this->getServiceFactory()->getModuleService()->getInstalledModules();

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
                $update_info = $this->getServiceFactory()->getUpdateService()->checkModuleUpdate($server_url, $module, $api_key);

                if (is_array($update_info) && isset($update_info['error'])) {
                    // Обрабатываем ошибки curl/http
                    if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                        $json['error_curl'] = sprintf($this->language->get('error_curl'), $update_info['message']);
                    } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                        $json['error_http'] = sprintf($this->language->get('error_http'), $update_info['code']);
                    }
                } elseif ($update_info) {
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
            } elseif (defined('HTTP_SERVER')) {
                // Если переменной окружения нет, используем HTTP_SERVER
                $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
            } else {
                // Если ничего не подошло, используем значение по умолчанию
                $server_url = 'https://example.com/server';
            }
        }

        if (empty($server_url)) {
            $json['error'] = $this->language->get('error_server');
        } else {
            // Получаем список установленных модулей
            $modules = $this->getServiceFactory()->getModuleService()->getInstalledModules();

            if (!empty($modules)) {
                $json['modules'] = array();

                foreach ($modules as $module) {
                    // Получаем API-ключ
                    $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';

                    $update_info = $this->getServiceFactory()->getUpdateService()->checkModuleUpdate($server_url, $module, $api_key);
                    LoggerService::write('GDT Updater: Checking updates for module ' . $module['code'] . ' - Current version: ' . $module['version']);
                    LoggerService::write('GDT Updater: Update info: ' . json_encode($update_info), true);

                    // Проверяем на ошибки curl
                    if (is_array($update_info) && isset($update_info['error'])) {
                        if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                            $json['error'] = sprintf($this->language->get('error_curl'), $update_info['message']);
                            // Не прерываем цикл, чтобы попробовать проверить другие модули
                        } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                            $json['error'] = sprintf($this->language->get('error_http'), $update_info['code']);
                            // Не прерываем цикл, чтобы попробовать проверить другие модули
                        }
                    } elseif ($update_info) {
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
                } elseif (defined('HTTP_SERVER')) {
                    // Если переменной окружения нет, используем HTTP_SERVER
                    $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
                } else {
                    // Если ничего не подошло, используем значение по умолчанию
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
                $module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);

                if ($module) {
                    // Получаем API-ключ
                    $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
                    // Проверяем обновление
                    $update_info = $this->getServiceFactory()->getUpdateService()->checkModuleUpdate($server_url, $module, $api_key);


                    // Проверяем на ошибки curl
                    if (is_array($update_info) && isset($update_info['error'])) {
                        if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                            $json['error'] = sprintf($this->language->get('error_curl'), $update_info['message']);
                        } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                            $json['error'] = sprintf($this->language->get('error_http'), $update_info['code']);
                        }
                    } elseif ($update_info) {
                        // Логируем начало процесса обновления
                        LoggerService::write('GDT Updater: Starting update for module ' . $code . ' from version ' . $module['version'] . ' to ' . $update_info['version']);

                        try {
                            // Скачиваем и устанавливаем обновление через встроенный процесс OpenCart
                            $download_result = $this->getServiceFactory()->getUpdateService()->downloadModule($server_url, $module['code'], $update_info['version'], $api_key);
                            if ($download_result['success']) {
                                $result = $this->getServiceFactory()->getInstallService()->installModule($download_result['file_path'], $module['code'], $update_info);
                            } else {
                                $result = $download_result['error'] ?? 'Ошибка загрузки модуля';
                            }

                            if ($result === true) {
                                // Очищаем все кэши OpenCart
                                $this->cache->delete('*');

                                // Проверяем, что версия действительно обновилась
                                $updated_module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);
                                if ($updated_module && $updated_module['version'] === $update_info['version']) {
                                    $json['success'] = sprintf($this->language->get('text_update_success'), $module['name'] ?? $module['module_name']) . ' (v' . $update_info['version'] . ') через встроенный процесс OpenCart';
                                } else {
                                    $json['warning'] = 'Файлы установлены через OpenCart, но версия модуля не обновилась. Возможно, требуется ручная проверка.';
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
     * Сохранение настроек с сохранением существующих значений
     */
    private function saveModuleSettings(array $new_settings = array())
    {
        // Получаем все текущие настройки модуля
        $current_settings = array();
        $current_settings['module_gdt_updater_server'] = $this->config->get('module_gdt_updater_server') ?: '';
        $current_settings['module_gdt_updater_api_key'] = $this->config->get('module_gdt_updater_api_key') ?: '';
        $current_settings['module_gdt_updater_status'] = $this->config->get('module_gdt_updater_status') ?: 0;
        $current_settings['module_gdt_updater_api_log'] = $this->config->get('module_gdt_updater_api_log') ?: 0;
        $current_settings['module_gdt_updater_auto_modules'] = $this->config->get('module_gdt_updater_auto_modules') ?: array();

        // Объединяем с новыми настройками
        $final_settings = array_merge($current_settings, $new_settings);

        // Сохраняем
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_gdt_updater', $final_settings);
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

            // Получаем текущий массив модулей с автообновлением
            $auto_update_modules = $this->config->get('module_gdt_updater_auto_modules') ?: array();

            if ($auto_update) {
                // Добавляем модуль в массив автообновления (если еще нет)
                if (!in_array($module_code, $auto_update_modules)) {
                    $auto_update_modules[] = $module_code;
                }
            } else {
                // Удаляем модуль из массива автообновления
                $auto_update_modules = array_diff($auto_update_modules, array($module_code));
                $auto_update_modules = array_values($auto_update_modules); // Переиндексируем массив
            }

            // Сохраняем обновленный массив, сохраняя остальные настройки
            $this->saveModuleSettings(array('module_gdt_updater_auto_modules' => $auto_update_modules));

            $json['success'] = 'Настройки автообновления сохранены';
        } else {
            $json['error'] = 'Неверные параметры запроса';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Массовое переключение автообновления для модулей
     */
    public function toggleMultipleAutoUpdate()
    {
        $this->load->language('extension/module/gdt_updater');
        $this->load->model('setting/setting');

        $json = array();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['modules']) && is_array($this->request->post['modules'])) {
            $module_codes = $this->request->post['modules'];
            $auto_update = isset($this->request->post['auto_update']) ? (int) $this->request->post['auto_update'] : 0;

            // Получаем текущий массив модулей с автообновлением
            $auto_update_modules = $this->config->get('module_gdt_updater_auto_modules') ?: array();

            if ($auto_update) {
                // Добавляем модули в массив автообновления
                foreach ($module_codes as $module_code) {
                    if (!in_array($module_code, $auto_update_modules)) {
                        $auto_update_modules[] = $module_code;
                    }
                }
            } else {
                // Удаляем модули из массива автообновления
                $auto_update_modules = array_diff($auto_update_modules, $module_codes);
                $auto_update_modules = array_values($auto_update_modules); // Переиндексируем массив
            }

            // Сохраняем обновленный массив, сохраняя остальные настройки
            $this->saveModuleSettings(array('module_gdt_updater_auto_modules' => $auto_update_modules));

            $action = $auto_update ? 'включено' : 'выключено';
            $json['success'] = sprintf('Автообновление %s для %d модулей', $action, count($module_codes));
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
                $module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);
                if (!$module) {
                    $json['error'] = sprintf($this->language->get('error_module_not_found_code'), $code);
                } else {
                    // Логируем начало процесса удаления
                    LoggerService::write('GDT Updater: Starting deletion for module ' . $code);

                    // Удаляем модуль
                    $result = $this->getServiceFactory()->getInstallService()->uninstallModule($code);

                    if ($result === true) {
                        // Очищаем все кэши OpenCart
                        $this->cache->delete('*');

                        $json['success'] = sprintf($this->language->get('text_delete_success'), $module['module_name'] ?? $module['name'] ?? $code);

                        LoggerService::write('GDT Updater: Successfully deleted module ' . $code);
                    } else {
                        $json['error'] = is_string($result) ? $result : $this->language->get('error_delete_failed');
                    }
                }
            } catch (Exception $e) {
                $json['error'] = sprintf($this->language->get('error_delete_failed_msg'), $e->getMessage());
                LoggerService::write('GDT Updater error: ' . $e->getMessage());
            }
        } else {
            $json['error'] = $this->language->get('error_code');
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
                    $module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);
                    if (!$module) {
                        $errors[] = sprintf($this->language->get('error_module_not_found_code'), $code);
                        continue;
                    }

                    // Логируем начало процесса удаления
                    LoggerService::write('GDT Updater: Starting deletion for module ' . $code);

                    // Удаляем модуль
                    $result = $this->getServiceFactory()->getInstallService()->uninstallModule($code);

                    if ($result === true) {
                        $deleted[] = $module['module_name'] ?? $module['name'] ?? $code;
                        LoggerService::write('GDT Updater: Successfully deleted module ' . $code);
                    } else {
                        $errors[] = $code . ': ' . (is_string($result) ? $result : $this->language->get('error_unknown'));
                    }
                } catch (Exception $e) {
                    $errors[] = $code . ': ' . $e->getMessage();
                    LoggerService::write('GDT Updater error: ' . $e->getMessage());
                }
            }

            // Очищаем все кэши OpenCart после всех операций
            if (!empty($deleted)) {
                $this->cache->delete('*');
            }

            if (!empty($deleted) && empty($errors)) {
                $json['success'] = sprintf($this->language->get('text_delete_multiple_success'), count($deleted), implode(', ', $deleted));
            } elseif (!empty($deleted) && !empty($errors)) {
                $json['warning'] = sprintf($this->language->get('text_delete_partial_success'), count($deleted), implode(', ', $deleted), implode('; ', $errors));
            } else {
                $json['error'] = sprintf($this->language->get('error_delete_multiple'), implode('; ', $errors));
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
    public function install()
    {
        $this->load->model('extension/module/gdt_updater');

        // Створюємо нову таблицю
        $this->model_extension_module_gdt_updater->createTables();

        // Добавляем события
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent('gdt_updater_auto_check', 'admin/controller/common/dashboard/before', 'extension/module/gdt_updater/autoCheckEvent');
        $this->model_setting_event->addEvent('auto_update_menu', 'admin/view/common/column_left/before', 'extension/module/gdt_updater/menuAdmin');

        // Логируем успешную установку
        LoggerService::write('GDT Updater: Events added, table created, module installed.');
    }

    public function uninstall()
    {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('gdt_updater_auto_check');
        $this->model_setting_event->deleteEventByCode('auto_update_menu');

        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_gdt_updater');

        // Удаляем Dashboard модуль при деинсталляции
        $this->load->model('setting/extension');
        $this->model_setting_extension->uninstall('dashboard', 'gdt_updater');

        // Удаляем настройки Dashboard модуля
        $this->model_setting_setting->deleteSetting('dashboard_gdt_updater');

        // Очищаем массив автообновления
        $this->saveModuleSettings(array('module_gdt_updater_auto_modules' => array()));

        // Legacy cleanup
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "gdt_modules` ");
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
                $this->session->data['success'] = $this->language->get('text_update_available');
            }
        }

        $this->session->data['success'] = $this->language->get('text_check_completed');
    }

    public function menuAdmin($route, array &$data, $menu_id = 0)
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
            'href' => '',
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

    /**
     * Событие автопроверки обновлений при загрузке dashboard
     */
    public function autoCheckEvent($route, &$data, &$output)
    {
        // Загружаем модель автообновления
        $this->load->model('extension/module/gdt_updater');

        // Запускаем автопроверку и обновление
        $this->model_extension_module_gdt_updater->autoCheckUpdate();
    }
}
