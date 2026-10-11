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
        $this->redirect($this->buildUrl('extension/module/gdt_install_modules/installed'));
    }

    /**
     * Получение настроек модуля
     */
    public function settings()
    {
        $json = array(
            'module_gdt_updater_server' => $this->getConfig('module_gdt_updater_server'),
            'module_gdt_updater_server_type' => $this->getConfig('module_gdt_updater_server_type') ?: 'laravel',
            'module_gdt_updater_api_key' => $this->getConfig('module_gdt_updater_api_key'),
            'module_gdt_updater_status' => $this->getConfig('module_gdt_updater_status'),
            'module_gdt_updater_api_log' => $this->getConfig('module_gdt_updater_api_log'),
            'module_gdt_updater_auto_modules' => $this->getConfig('module_gdt_updater_auto_modules', [])
        );

        $this->jsonResponse($json);
    }

    /**
     * Сохранение настроек модуля
     */
    public function saveSettings()
    {
        try {
            $this->validatePermission();

            if ($this->isPost() && $this->validate()) {
                if (!in_array($this->request->post['module_gdt_updater_server_type'] ?? 'laravel', ['laravel', 'opencart'], true)) {
                    throw new Exception('Unknown update server type');
                }
                $this->saveModuleSettings($this->request->post);
                $this->jsonResponse(['success' => $this->trans(self::MODULE_PATH . '.text_success')]);
            } else {
                $error = isset($this->error['warning']) ? $this->error['warning'] : $this->trans(self::MODULE_PATH . '.text_validation_failed');
                $this->jsonResponse(['error' => $error]);
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

        // Installed metadata is authoritative in modules.json.
        try {
            $installed_modules = $this->getServiceFactory()->getModuleService()->getInstalledModules();
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()]);
            return;
        }

        if (empty($installed_modules)) {
            $json['error'] = $this->trans(self::MODULE_PATH . '.error_no_modules');
            $this->jsonResponse($json);
            return;
        }

        $json['modules'] = array();

        // Получаем URL сервера обновлений
        $server_url = $this->getConfig('module_gdt_updater_server');
        if (empty($server_url)) {
            $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
        }
        $api_key = $this->getConfig('module_gdt_updater_api_key') ?: '';

        // Batch check updates with caching
        $batch_updates = $this->getServiceFactory()->getUpdateService()->checkBatchUpdates($server_url, $installed_modules, $api_key, false);

        foreach ($installed_modules as $module) {
            $code = $module['code'];
            $update_info = $batch_updates[$code] ?? false;

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

            if ($update_info && is_array($update_info) && isset($update_info['status']) && $update_info['status'] === 'update_available') {
                $module_data['has_update'] = true;
                $module_data['new_version'] = $update_info['version'];
                $module_data['update_url'] = $this->buildUrl(self::MODULE_PATH . '/update', ['code' => $module['code']]);
            }

            $json['modules'][] = $module_data;
        }

        $this->jsonResponse($json);
    }

    public function check()
    {
        $this->load->language(self::MODULE_PATH);

        $json = array();

        // Получаем URL сервера обновлений
        $server_url = $this->getConfig('module_gdt_updater_server');

        // Проверяем, не пустой ли URL и устанавливаем значение по умолчанию, если нужно
        if (empty($server_url)) {
            $docker_server_url = getenv('GDT_UPDATE_SERVER');
            if ($docker_server_url) {
                $server_url = $docker_server_url;
            } elseif (defined('HTTP_SERVER')) {
                $server_url = HTTP_SERVER . 'ocm_gdt_update/server';
            } else {
                throw new Exception($this->trans('error_server'));
            }
        }

        if (empty($server_url)) {
            $json['error'] = $this->trans('error_server');
        } else {
            // Получаем список установленных модулей
            $modules = $this->getServiceFactory()->getModuleService()->getInstalledModules();

            if (!empty($modules)) {
                $json['modules'] = array();
                $api_key = $this->getConfig('module_gdt_updater_api_key') ?: '';

                // Принудительная свежая проверка (force_refresh = true)
                $batch_updates = $this->getServiceFactory()->getUpdateService()->checkBatchUpdates($server_url, $modules, $api_key, true);

                foreach ($modules as $module) {
                    $code = $module['code'];
                    $update_info = $batch_updates[$code] ?? false;

                    LoggerService::write('GDT Updater: Checking updates for module ' . $code . ' - Current version: ' . $module['version']);

                    if ($update_info && is_array($update_info) && isset($update_info['status']) && $update_info['status'] === 'update_available') {
                        $json['modules'][] = array(
                            'name' => $module['name'],
                            'code' => $module['code'],
                            'current_version' => $module['version'],
                            'new_version' => $update_info['version'],
                            'update_url' => $this->buildUrl(self::MODULE_PATH . '/update', ['code' => $module['code']])
                        );
                    }
                }

                if (empty($json['modules'])) {
                    $json['success'] = $this->trans(self::MODULE_PATH . '.text_no_updates');
                } else {
                    $json['success'] = sprintf($this->trans(self::MODULE_PATH . '.text_updates_found'), count($json['modules']));
                }
            } else {
                $json['error'] = $this->trans(self::MODULE_PATH . '.error_no_modules');
            }
        }

        $this->jsonResponse($json);
    }

    private function getServerUrl(): string
    {
        return $this->getConfig('module_gdt_updater_server')
            ?: getenv('GDT_UPDATE_SERVER')
            ?: (defined('HTTP_SERVER') ? HTTP_SERVER . 'ocm_gdt_update/server' : '');
    }

    public function update()
    {
        $json = [];
        $code = $this->request->get['code'] ?? null;

        // 1. Валидация
        if (!$code) {
            $this->jsonResponse(['error' => $this->trans(self::MODULE_PATH . '.error_code')]);
            return;
        }

        try {
            $this->validatePermission('modify', self::MODULE_PATH);

            // 2. Инициализация параметров
            $server_url = $this->getServerUrl();
            if (empty($server_url)) {
                throw new Exception($this->trans(self::MODULE_PATH . '.error_server'));
            }

            $module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);
            if (!$module) {
                throw new Exception($this->trans(self::MODULE_PATH . '.error_module_not_found'));
            }

            $api_key = $this->getConfig('module_gdt_updater_api_key') ?: '';

            // 3. Проверка обновлений
            $update_info = $this->getServiceFactory()->getUpdateService()->checkModuleUpdate($server_url, $module, $api_key);

            if (isset($update_info['error'])) {
                $error_type = $update_info['error'] === 'curl' ? '.error_curl' : '.error_http';
                $msg = $update_info['message'] ?? ($update_info['code'] ?? 'Unknown error');
                throw new Exception(sprintf($this->trans(self::MODULE_PATH . $error_type), $msg));
            }

            if (!$update_info) {
                throw new Exception($this->trans(self::MODULE_PATH . '.error_no_update'));
            }

            // 4. Процесс загрузки и установки
            LoggerService::write("GDT Updater: Starting update for {$code} (v{$module['version']} -> v{$update_info['version']})");

            $download_result = $this->getServiceFactory()->getUpdateService()->downloadModule($server_url, $module['code'], $update_info['version'], $api_key);

            if (empty($download_result['success'])) {
                throw new Exception($download_result['error'] ?? 'Ошибка загрузки модуля');
            }

            $update_info['_downloaded_version'] = $download_result['version'] ?? '';
            $result = $this->getServiceFactory()->getInstallService()->installModule($download_result['file_path'], $module['code'], $update_info);

            if ($result !== true) {
                throw new Exception($result);
            }

            // 5. Завершение и верификация
            $this->cache->delete('*');
            $updated_module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);

            if ($updated_module && $updated_module['version'] === $update_info['version']) {
                $module_name = $module['name'] ?? $module['module_name'];
                $json['success'] = sprintf($this->trans(self::MODULE_PATH . '.text_update_success'), $module_name) . " (v{$update_info['version']})";
            } else {
                $json['warning'] = $this->trans(self::MODULE_PATH . '.warning_version_not_updated');
            }
        } catch (Exception $e) {
            $json['error'] = $e->getMessage();
        }

        $this->jsonResponse($json);
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', self::MODULE_PATH)) {
            $this->error['warning'] = $this->trans(self::MODULE_PATH . '.error_permission');
        }

        return !$this->error;
    }

    /**
     * Сохранение настроек с сохранением существующих значений
     */
    private function saveModuleSettings(array $new_settings = array())
    {
        // Получаем все текущие настройки модуля
        $current_settings = array();
        $current_settings['module_gdt_updater_server_type'] = $this->getConfig('module_gdt_updater_server_type') ?: 'laravel';
        $current_settings['module_gdt_updater_server'] = $this->getConfig('module_gdt_updater_server') ?: '';
        $current_settings['module_gdt_updater_api_key'] = $this->getConfig('module_gdt_updater_api_key') ?: '';
        $current_settings['module_gdt_updater_status'] = $this->getConfig('module_gdt_updater_status') ?: 0;
        $current_settings['module_gdt_updater_api_log'] = $this->getConfig('module_gdt_updater_api_log') ?: 0;
        $current_settings['module_gdt_updater_auto_modules'] = $this->getConfig('module_gdt_updater_auto_modules') ?: array();

        // Объединяем с новыми настройками
        $final_settings = array_merge($current_settings, $new_settings);

        // Сохраняем
        $this->getModel('setting/setting')->editSetting('module_gdt_updater', $final_settings);
    }

    /**
     * Переключение автообновления для модуля
     */
    public function toggleAutoUpdate()
    {
        $this->load->language(self::MODULE_PATH);
        $this->load->model('setting/setting');

        $json = array();
        try {
            $this->validatePermission();

            if ($this->isPost() && $this->getPostData('module_code')) {
                $module_code = $this->getPostData('module_code');
                $auto_update = $this->getPostData('auto_update') ? (int) $this->getPostData('auto_update') : 0;

                // Получаем текущий массив модулей с автообновлением
                $auto_update_modules = $this->getConfig('module_gdt_updater_auto_modules') ?: array();

                if ($auto_update) {
                    foreach ([$module_code] as $candidate) {
                        $module = $this->getServiceFactory()->getModuleService()->getModuleByCode($candidate);
                        if (!$module || $module['version'] === '0.0.0') {
                            throw new \RuntimeException('Автообновление недоступно: модуль отсутствует в реестре или версия неизвестна');
                        }
                    }
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

                $json['success'] = $this->trans(self::MODULE_PATH . '.text_auto_update_saved');
            } else {
                $json['error'] = $this->trans(self::MODULE_PATH . '.error_invalid_request');
            }
        } catch (\Exception $e) {
            $json = ['error' => $e->getMessage()];
        }
        $this->jsonResponse($json);
    }

    /**
     * Массовое переключение автообновления для модулей
     */
    public function toggleMultipleAutoUpdate()
    {
        $this->load->language(self::MODULE_PATH);
        $this->load->model('setting/setting');

        $json = array();
        try {
            $this->validatePermission();

            if ($this->isPost() && $this->getPostData('modules') && is_array($this->getPostData('modules'))) {
                $module_codes = $this->getPostData('modules');
                $auto_update = $this->getPostData('auto_update') ? (int) $this->getPostData('auto_update') : 0;

                // Получаем текущий массив модулей с автообновлением
                $auto_update_modules = $this->getConfig('module_gdt_updater_auto_modules') ?: array();

                if ($auto_update) {
                    foreach ($module_codes as $candidate) {
                        $module = $this->getServiceFactory()->getModuleService()->getModuleByCode($candidate);
                        if (!$module || $module['version'] === '0.0.0') {
                            throw new \RuntimeException('Автообновление недоступно: модуль отсутствует в реестре или версия неизвестна');
                        }
                    }
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

                $action = $auto_update ? $this->trans(self::MODULE_PATH . '.text_enabled') : $this->trans(self::MODULE_PATH . '.text_disabled');
                $json['success'] = sprintf($this->trans(self::MODULE_PATH . '.text_auto_update_multiple'), $action, count($module_codes));
            } else {
                $json['error'] = $this->trans(self::MODULE_PATH . '.error_invalid_request');
            }
        } catch (\Exception $e) {
            $json = ['error' => $e->getMessage()];
        }
        $this->jsonResponse($json);
    }

    /**
     * Удаление модуля
     */
    public function delete()
    {
        $this->load->language(self::MODULE_PATH);

        try {
            $this->validatePermission();

            $code = isset($this->request->get['code']) ? $this->request->get['code'] : '';
            if (empty($code)) {
                $this->jsonResponse(['error' => $this->trans(self::MODULE_PATH . '.error_code')]);
                return;
            }

            $module = $this->getServiceFactory()->getModuleService()->getModuleByCode($code);
            if (!$module) {
                $this->jsonResponse(['error' => sprintf($this->trans(self::MODULE_PATH . '.error_module_not_found_code'), $code)]);
                return;
            }

            $result = $this->getServiceFactory()->getInstallService()->uninstallModule($code);

            if ($result === true) {
                $this->cache->delete('*');
                $name = $module['module_name'] ?? $module['name'] ?? $code;
                $this->jsonResponse(['success' => sprintf($this->trans(self::MODULE_PATH . '.text_delete_success'), $name)]);
            } else {
                $error = is_string($result) ? $result : $this->trans(self::MODULE_PATH . '.error_delete_failed');
                $this->jsonResponse(['error' => $error]);
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
        $this->load->language(self::MODULE_PATH);

        try {
            $this->validatePermission();

            if (!$this->isPost()) {
                $this->jsonResponse(['error' => $this->trans(self::MODULE_PATH . '.error_invalid_request')]);
                return;
            }

            $modules = $this->getPostData('modules');
            if (!$modules || !is_array($modules)) {
                $this->jsonResponse(['error' => $this->trans(self::MODULE_PATH . '.error_no_modules_specified')]);
                return;
            }

            $result = $this->getServiceFactory()->getInstallService()->deleteMultipleModules($modules);

            if (!empty($result['success'])) {
                $this->cache->delete('*');
            }

            if (!empty($result['success']) && empty($result['errors'])) {
                $this->jsonResponse(['success' => sprintf($this->trans(self::MODULE_PATH . '.text_delete_multiple_success'), count($result['success']), implode(', ', $result['success']))]);
            } elseif (!empty($result['success']) && !empty($result['errors'])) {
                $this->jsonResponse(['warning' => sprintf(
                    $this->trans(self::MODULE_PATH . '.text_delete_partial_success'),
                    count($result['success']),
                    implode(', ', $result['success']),
                    implode('; ', $result['errors'])
                )]);
            } else {
                $this->jsonResponse(['error' => sprintf($this->trans(self::MODULE_PATH . '.error_delete_multiple'), implode('; ', $result['errors']))]);
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
        // Создаем таблицы
        $this->getModel(self::MODULE_PATH)->createTables();

        // Добавляем события
        $this->getModel('setting/event')->addEvent('gdt_updater_auto_check', 'admin/controller/common/dashboard/before', self::MODULE_PATH . '/autoCheckEvent');
        $this->getModel('setting/event')->addEvent('auto_update_menu', 'admin/view/common/column_left/before', self::MODULE_PATH . '/menuAdmin');

        LoggerService::write('GDT Updater: Events added, table created, module installed.');
    }

    public function uninstall()
    {
        $this->getModel('setting/event')->deleteEventByCode('gdt_updater_auto_check');
        $this->getModel('setting/event')->deleteEventByCode('auto_update_menu');

        $this->getModel('setting/setting')->deleteSetting('module_gdt_updater');

        // Удаляем Dashboard модуль при деинсталляции
        $this->getModel('setting/extension')->uninstall('dashboard', 'gdt_updater');
        $this->getModel('setting/setting')->deleteSetting('dashboard_gdt_updater');

        // Очищаем массив автообновления
        $this->saveModuleSettings(array('module_gdt_updater_auto_modules' => array()));

        // Clean up module tables
        $this->getModel(self::MODULE_PATH)->dropTables();
    }

    public function menuAdmin($route, array &$data, $menu_id = 0)
    {
        $children = array();

        $children[] = array(
            'name' => $this->trans('extension/module/gdt_install_modules.heading_title'),
            'href' => $this->buildUrl(self::MODULE_PATH),
            'children' => array()
        );

        $data['menus'][] = array(
            'id' => 'menu-gdt-updater',
            'name' => $this->trans(self::MODULE_PATH . '.heading_title'),
            'href' => $this->buildUrl(self::MODULE_PATH),
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
        $this->getModel(self::MODULE_PATH)->autoCheckUpdate();
    }
}
