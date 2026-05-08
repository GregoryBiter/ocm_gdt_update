<?php
require_once DIR_SYSTEM . 'library/gbitstudio/modules/controllers/GDTBaseController.php';

use Gbitstudio\Modules\Services\LoggerService;
use Gbitstudio\Modules\Controllers\GDTBaseController;

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
class ControllerExtensionModuleGdtUpdater extends GDTBaseController
{

    private const MODULE_CODE = 'gdt_updater';
    private const MODULE_PATH = 'extension/module/gdt_updater';

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
        response()->redirect(route('extension/module/gdt_install_modules/installed'));
    }

    /**
     * Получение настроек модуля
     */
    public function settings()
    {
        $json = array(
            'module_gdt_updater_server' => config('module_gdt_updater_server'),
            'module_gdt_updater_api_key' => config('module_gdt_updater_api_key'),
            'module_gdt_updater_status' => config('module_gdt_updater_status'),
            'module_gdt_updater_api_log' => config('module_gdt_updater_api_log'),
            'module_gdt_updater_auto_modules' => config('module_gdt_updater_auto_modules', [])
        );

        // response()->json($json);
        response()->json($json);
    }

    /**
     * Сохранение настроек модуля
     */
    public function saveSettings()
    {

        try {
            $this->validatePermission();

            if (request()->isMethod('post') && $this->validate()) {
                model('setting/setting')->editSetting('module_gdt_updater', $this->request->post);
                response()->json(['success' => __(self::MODULE_PATH . '.text_success')]);
            } else {
                $error = isset($this->error['warning']) ? $this->error['warning'] : __(self::MODULE_PATH . '.text_validation_failed');
                response()->json(['error' => $error]);
            }
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Получение списка модулей с информацией об обновлениях
     */
    public function getModules()
    {
        $json = array();

        // Получаем список установленных модулей
        $installed_modules = $this->getServiceFactory()->getModuleService()->getInstalledModules();

        if (empty($installed_modules)) {
            $json['error'] = __(self::MODULE_PATH . '.error_no_modules');
            response()->json($json);
            return;
        }

        $json['modules'] = array();

        // Получаем URL сервера обновлений
        $server_url = config('module_gdt_updater_server');

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
                $api_key = config('module_gdt_updater_api_key') ?: '';

                // Проверяем обновления
                $update_info = $this->getServiceFactory()->getUpdateService()->checkModuleUpdate($server_url, $module, $api_key);

                if (is_array($update_info) && isset($update_info['error'])) {
                    // Обрабатываем ошибки curl/http
                    if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                        $json['error_curl'] = sprintf(__(self::MODULE_PATH . '.error_curl'), $update_info['message']);
                    } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                        $json['error_http'] = sprintf(__(self::MODULE_PATH . '.error_http'), $update_info['code']);
                    }
                } elseif ($update_info) {
                    $module_data['has_update'] = true;
                    $module_data['new_version'] = $update_info['version'];
                    $module_data['update_url'] = route(self::MODULE_PATH . '/update', ['code' => $module['code']], true);
                }
            }

            $json['modules'][] = $module_data;
        }

        response()->json($json);
    }

    public function check()
    {
        $this->load->language(self::MODULE_PATH . '');

        $json = array();

        // Получаем URL сервера обновлений
        $server_url = config('module_gdt_updater_server');

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
                // $server_url = 'https://example.com/server';
                throw new Exception(__('error_server'));
            }
        }

        if (empty($server_url)) {
            $json['error'] = __('error_server');
        } else {
            // Получаем список установленных модулей
            $modules = $this->getServiceFactory()->getModuleService()->getInstalledModules();

            if (!empty($modules)) {
                $json['modules'] = array();

                foreach ($modules as $module) {
                    // Получаем API-ключ
                    $api_key = config('module_gdt_updater_api_key') ?: '';

                    $update_info = $this->getServiceFactory()->getUpdateService()->checkModuleUpdate($server_url, $module, $api_key);
                    LoggerService::write('GDT Updater: Checking updates for module ' . $module['code'] . ' - Current version: ' . $module['version']);
                    LoggerService::write('GDT Updater: Update info: ' . json_encode($update_info), true);

                    // Проверяем на ошибки curl
                    if (is_array($update_info) && isset($update_info['error'])) {
                        if ($update_info['error'] == 'curl' && !empty($update_info['message'])) {
                            $json['error'] = sprintf(__(self::MODULE_PATH . '.error_curl'), $update_info['message']);
                            // Не прерываем цикл, чтобы попробовать проверить другие модули
                        } elseif ($update_info['error'] == 'http' && !empty($update_info['code'])) {
                            $json['error'] = sprintf(__(self::MODULE_PATH . '.error_http'), $update_info['code']);
                            // Не прерываем цикл, чтобы попробовать проверить другие модули
                        }
                    } elseif ($update_info) {
                        $json['modules'][] = array(
                            'name' => $module['name'],
                            'code' => $module['code'],
                            'current_version' => $module['version'],
                            'new_version' => $update_info['version'],
                            'update_url' => route(self::MODULE_PATH . '/update', ['code' => $module['code']], true)
                        );
                    }
                }

                if (empty($json['modules'])) {
                    $json['success'] = __(self::MODULE_PATH . '.text_no_updates');
                } else {
                    $json['success'] = sprintf(__(self::MODULE_PATH . '.text_updates_found'), count($json['modules']));
                }
            } else {
                $json['error'] = __(self::MODULE_PATH . '.error_no_modules');
            }
        }

        response()->json($json);
    }

    public function update()
    {
        $json = [];
        $code = $this->request->get['code'] ?? null;

        // 1. Валидация
        if (!$code) {
            response()->json(['error' => __(self::MODULE_PATH . '.error_code')]);
            return;
        }

        try {
            // 2. Инициализация параметров
            $server_url = $this->getServerUrl();
            if (empty($server_url)) {
                throw new Exception(__(self::MODULE_PATH . '.error_server'));
            }

            $module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);
            if (!$module) {
                throw new Exception(__(self::MODULE_PATH . '.error_module_not_found'));
            }

            $api_key = config('module_gdt_updater_api_key') ?: '';

            // 3. Проверка обновлений
            $update_info = $this->getServiceFactory()->getUpdateService()->checkModuleUpdate($server_url, $module, $api_key);

            if (isset($update_info['error'])) {
                $error_type = $update_info['error'] === 'curl' ? '.error_curl' : '.error_http';
                $msg = $update_info['message'] ?? ($update_info['code'] ?? 'Unknown error');
                throw new Exception(sprintf(__(self::MODULE_PATH . $error_type), $msg));
            }

            if (!$update_info) {
                throw new Exception(__(self::MODULE_PATH . '.error_no_update'));
            }

            // 4. Процесс загрузки и установки
            LoggerService::write("GDT Updater: Starting update for {$code} (v{$module['version']} -> v{$update_info['version']})");

            $download_result = $this->getServiceFactory()->getUpdateService()->downloadModule($server_url, $module['code'], $update_info['version'], $api_key);

            if (empty($download_result['success'])) {
                throw new Exception($download_result['error'] ?? 'Ошибка загрузки модуля');
            }

            $result = $this->getServiceFactory()->getInstallService()->installModule($download_result['file_path'], $module['code'], $update_info);

            if ($result !== true) {
                throw new Exception($result);
            }

            // 5. Завершение и верификация
            $this->cache->delete('*');
            $updated_module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);

            if ($updated_module && $updated_module['version'] === $update_info['version']) {
                $module_name = $module['name'] ?? $module['module_name'];
                $json['success'] = sprintf(__(self::MODULE_PATH . '.text_update_success'), $module_name) . " (v{$update_info['version']})";
            } else {
                $json['warning'] = __(self::MODULE_PATH . '.warning_version_not_updated');
            }

        } catch (Exception $e) {
            $json['error'] = $e->getMessage();
        }

        response()->json($json);
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', self::MODULE_PATH)) {
            $this->error['warning'] = __(self::MODULE_PATH . '.error_permission');
        }

        // Убираем обязательную проверку сервера, так как он может быть задан по умолчанию
        // if (empty($this->request->post['module_gdt_updater_server'])) {
        //     $this->error['warning'] = __(self::MODULE_PATH . '.error_server_empty');
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
        $current_settings['module_gdt_updater_server'] = config('module_gdt_updater_server') ?: '';
        $current_settings['module_gdt_updater_api_key'] = config('module_gdt_updater_api_key') ?: '';
        $current_settings['module_gdt_updater_status'] = config('module_gdt_updater_status') ?: 0;
        $current_settings['module_gdt_updater_api_log'] = config('module_gdt_updater_api_log') ?: 0;
        $current_settings['module_gdt_updater_auto_modules'] = config('module_gdt_updater_auto_modules') ?: array();

        // Объединяем с новыми настройками
        $final_settings = array_merge($current_settings, $new_settings);

        // Сохраняем
        model('setting/setting')->editSetting('module_gdt_updater', $final_settings);
    }


    /**
     * Переключение автообновления для модуля
     */
    public function toggleAutoUpdate()
    {
        $this->load->language(self::MODULE_PATH . '');
        $this->load->model('setting/setting');

        $json = array();

        if (request()->isMethod('post') && request()->post('module_code')) {
            $module_code = request()->post('module_code');
            $auto_update = request()->post('auto_update') ? (int) request()->post('auto_update') : 0;

            // Получаем текущий массив модулей с автообновлением
            $auto_update_modules = config('module_gdt_updater_auto_modules') ?: array();

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

            $json['success'] = __(self::MODULE_PATH . '.text_auto_update_saved');
        } else {
            $json['error'] = __(self::MODULE_PATH . '.error_invalid_request');
        }

        response()->json($json);
    }

    /**
     * Массовое переключение автообновления для модулей
     */
    public function toggleMultipleAutoUpdate()
    {
        $this->load->model('setting/setting');

        $json = array();

        if (request()->isMethod('post') && request()->post('modules') && is_array(request()->post('modules'))) {
            $module_codes = request()->post('modules');
            $auto_update = request()->post('auto_update') ? (int) request()->post('auto_update') : 0;

            // Получаем текущий массив модулей с автообновлением
            $auto_update_modules = config('module_gdt_updater_auto_modules') ?: array();

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

            $action = $auto_update ? __(self::MODULE_PATH . '.text_enabled') : __(self::MODULE_PATH . '.text_disabled');
            $json['success'] = sprintf(__(self::MODULE_PATH . '.text_auto_update_multiple'), $action, count($module_codes));
        } else {
            $json['error'] = __(self::MODULE_PATH . '.error_invalid_request');
        }

        response()->json($json);
    }

    /**
     * Удаление модуля
     */
    public function delete()
    {
        try {
            $this->validatePermission();

            $code = isset($this->request->get['code']) ? $this->request->get['code'] : '';
            if (empty($code)) {
                response()->json(['error' => __(self::MODULE_PATH . '.error_code')]);
                return;
            }

            $module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);
            if (!$module) {
                response()->json(['error' => sprintf(__(self::MODULE_PATH . '.error_module_not_found_code'), $code)]);
                return;
            }

            $result = $this->getServiceFactory()->getInstallService()->uninstallModule($code);

            if ($result === true) {
                $this->cache->delete('*');
                $name = $module['module_name'] ?? $module['name'] ?? $code;
                response()->json(['success' => sprintf(__(self::MODULE_PATH . '.text_delete_success'), $name)]);
            } else {
                $error = is_string($result) ? $result : __(self::MODULE_PATH . '.error_delete_failed');
                response()->json(['error' => $error]);
            }
        } catch (Exception $e) {
            LoggerService::write('GDT Updater error: ' . $e->getMessage());
            $this->handleException($e);
        }
    }

    /**
     * Массовое удаление модулей
     */
    public function deleteMultiple()
    {
        try {
            $this->validatePermission();

            if (!request()->isMethod('post')) {
                response()->json(['error' => __(self::MODULE_PATH . '.error_invalid_request')]);
                return;
            }

            $modules = $this->getPostData('modules');
            if (!$modules || !is_array($modules)) {
                response()->json(['error' => __(self::MODULE_PATH . '.error_no_modules_specified')]);
                return;
            }

            $result = $this->getServiceFactory()->getInstallService()->deleteMultipleModules($modules);

            if (!empty($result['success'])) {
                $this->cache->delete('*');
            }

            if (!empty($result['success']) && empty($result['errors'])) {
                response()->json(['success' => sprintf(__(self::MODULE_PATH . '.text_delete_multiple_success'), count($result['success']), implode(', ', $result['success']))]);
            } elseif (!empty($result['success']) && !empty($result['errors'])) {
                response()->json(['warning' => sprintf(__(self::MODULE_PATH . '.text_delete_partial_success'), count($result['success']), implode(', ', $result['success']), implode('; ', $result['errors']))]);
            } else {
                response()->json(['error' => sprintf(__(self::MODULE_PATH . '.error_delete_multiple'), implode('; ', $result['errors']))]);
            }
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }




    /**
     * Установка модуля
     */
    public function install()
    {
        // Створюємо нову таблицю
        model(self::MODULE_PATH)->createTables();

        // Добавляем события
        model('setting/event')->addEvent('gdt_updater_auto_check', 'admin/controller/common/dashboard/before', self::MODULE_PATH . '/autoCheckEvent');
        model('setting/event')->addEvent('auto_update_menu', 'admin/view/common/column_left/before', self::MODULE_PATH . '/menuAdmin');

        // Логируем успешную установку
        LoggerService::write('GDT Updater: Events added, table created, module installed.');
    }

    public function uninstall()
    {
        model('setting/event')->deleteEventByCode('gdt_updater_auto_check');
        model('setting/event')->deleteEventByCode('auto_update_menu');

        model('setting/setting')->deleteSetting('module_gdt_updater');

        // Удаляем Dashboard модуль при деинсталляции
        model('setting/extension')->uninstall('dashboard', 'gdt_updater');

        // Удаляем настройки Dashboard модуля
        model('setting/setting')->deleteSetting('dashboard_gdt_updater');

        // Очищаем массив автообновления
        $this->saveModuleSettings(array('module_gdt_updater_auto_modules' => array()));

        // Legacy cleanup
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "gdt_modules` ");
    }



    public function checkUpdates()
    {


        $server_url = config('module_gdt_updater_server');
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
                session('success', __('text_update_available'));
            }
        }

        session('success', __('text_check_completed'));
    }

    public function menuAdmin($route, array &$data, $menu_id = 0)
    {

        $children = array();
        //install

        $children[] = array(
            'name' => __('extension/module/gdt_install_modules.heading_title'),
            'href' => route(self::MODULE_PATH),
            'children' => array()
        );

        // $children[] = array(
        //     'name' => __(self::MODULE_PATH . '.text_update'),
        //     'href' => route(self::MODULE_PATH . ''),
        //     'children' => array()
        // );

        $data['menus'][] = array(
            'id' => 'menu-gdt-updater',
            'name' => __(self::MODULE_PATH . '.heading_title'),
            'href' => route(self::MODULE_PATH),
            'icon' => 'fa fa-refresh',
            'children' => []
        );


        foreach ($data['menus'] as &$menu) {
            if ($menu['id'] == 'menu-extension') {
                $menu['children'] = array_merge($children, $menu['children']);
                break;
            }
        }
    }

    /**
     * Событие автопроверки обновлений при загрузке dashboard
     */
    public function autoCheckEvent($route, &$data, &$output)
    {
        // Запускаем автопроверку и обновление
        model(self::MODULE_PATH)->autoCheckUpdate();
    }
}
