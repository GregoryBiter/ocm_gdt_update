<?php

require_once DIR_SYSTEM . 'library/gbitstudio/modules/controllers/GDTBaseController.php';

use Gbitstudio\Modules\Controllers\GDTBaseController;
use Gbitstudio\Modules\Services\LoggerService;

class ControllerExtensionModuleGdtInstallModules extends GDTBaseController
{
    public const PATH_INSTALL = 'extension/module/gdt_install_modules';
    public const PATH_UPDATER = 'extension/module/gdt_updater';
    private $error = array();

    /**
     * Отримує фабрику сервісів
     *
     * @return \Gbitstudio\Modules\ServiceFactory
     */
    private function getServiceFactory()
    {
        if (!$this->registry->has('gb_modules')) {
            $this->registry->set('gb_modules', new \Gbitstudio\Modules\ServiceFactory($this->registry));
        }

        return $this->registry->get('gb_modules');
    }

    public function index()
    {
        // Перенаправляем на страницу магазина модулей по умолчанию
        $this->redirect($this->buildUrl(self::PATH_INSTALL . '/store'));
    }

    private function layout()
    {
        return [
            'header' => $this->load->controller('common/header'),
            'column_left' => $this->load->controller('common/column_left'),
            'footer' => $this->load->controller('common/footer'),
        ];
    }

    /**
     * Страница магазина модулей (по умолчанию)
     */
    public function store()
    {
        $this->load->language(self::PATH_INSTALL);

        $this->document->setTitle($this->trans('heading_title') . ' - ' . $this->trans(self::PATH_INSTALL . '.text_store'));

        $data = $this->getCommonData();
        $data['current_page'] = 'store';
        $data['page_title'] = $this->trans(self::PATH_INSTALL . '.text_store');
        $data['page_description'] = $this->trans(self::PATH_INSTALL . '.text_store_desc');

        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/getStoreModules'));
        $data['search_modules_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/searchStoreModules'));
        $data['install_module_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        $this->render(self::PATH_INSTALL, $data);
    }

    /**
     * Страница рекомендуемых модулей
     */
    public function featured()
    {
        $this->load->language(self::PATH_INSTALL);

        $this->document->setTitle($this->trans('heading_title') . ' - ' . $this->trans(self::PATH_INSTALL . '.text_featured'));

        $data = $this->getCommonData();
        $data['current_page'] = 'featured';
        $data['page_title'] = $this->trans(self::PATH_INSTALL . '.text_featured');
        $data['page_description'] = $this->trans(self::PATH_INSTALL . '.text_featured_desc');

        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/getFeaturedModules'));
        $data['install_module_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        $this->render(self::PATH_INSTALL, $data);
    }

    /**
     * Страница популярных модулей
     */
    public function popular()
    {
        $this->load->language(self::PATH_INSTALL);

        $this->document->setTitle($this->trans('heading_title') . ' - ' . $this->trans(self::PATH_INSTALL . '.text_popular'));

        $data = $this->getCommonData();
        $data['current_page'] = 'popular';
        $data['page_title'] = $this->trans(self::PATH_INSTALL . '.text_popular');
        $data['page_description'] = $this->trans(self::PATH_INSTALL . '.text_popular_desc');

        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/getPopularModules'));
        $data['install_module_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        $this->render(self::PATH_INSTALL, $data);
    }

    /**
     * Страница новых модулей
     */
    public function newest()
    {
        $this->load->language(self::PATH_INSTALL);

        $this->document->setTitle($this->trans('heading_title') . ' - ' . $this->trans(self::PATH_INSTALL . '.text_newest'));

        $data = $this->getCommonData();
        $data['current_page'] = 'newest';
        $data['page_title'] = $this->trans(self::PATH_INSTALL . '.text_newest');
        $data['page_description'] = $this->trans(self::PATH_INSTALL . '.text_newest_desc');

        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/getNewestModules'));
        $data['install_module_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        $this->render(self::PATH_INSTALL, $data);
    }

    /**
     * Страница поиска модулей
     */
    public function search()
    {
        $this->load->language(self::PATH_INSTALL);

        $this->document->setTitle($this->trans('heading_title') . ' - ' . $this->trans(self::PATH_INSTALL . '.text_search'));

        $data = $this->getCommonData();
        $data['current_page'] = 'search';
        $data['page_title'] = $this->trans(self::PATH_INSTALL . '.text_search');
        $data['page_description'] = $this->trans(self::PATH_INSTALL . '.text_search_desc');

        // AJAX URLs для этой страницы
        $data['search_modules_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/searchModules'));
        $data['install_module_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        $this->render(self::PATH_INSTALL, $data);
    }

    /**
     * Получение общих данных для всех страниц
     */
    private function getCommonData()
    {
        $this->load->language(self::PATH_INSTALL);

        $data = array();

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->trans('text_home'),
            'href' => $this->buildUrl('common/dashboard')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->trans('text_extension'),
            'href' => $this->buildUrl('marketplace/extension', ['type' => 'module'])
        );

        $data['breadcrumbs'][] = array(
            'text' => 'GDT Module Updater',
            'href' => $this->buildUrl(self::PATH_UPDATER)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->trans('heading_title'),
            'href' => $this->buildUrl(self::PATH_INSTALL . '/store')
        );

        // URL для возврата
        $data['back'] = $this->buildUrl(self::PATH_UPDATER);

        // Навигационные ссылки между страницами
        $data['store_url'] = $this->buildUrl(self::PATH_INSTALL . '/store');
        $data['featured_url'] = $this->buildUrl(self::PATH_INSTALL . '/featured');
        $data['popular_url'] = $this->buildUrl(self::PATH_INSTALL . '/popular');
        $data['newest_url'] = $this->buildUrl(self::PATH_INSTALL . '/newest');
        $data['search_url'] = $this->buildUrl(self::PATH_INSTALL . '/search');
        $data['installed_url'] = $this->buildUrl(self::PATH_INSTALL . '/installed');
        $data['conflicts_url'] = $this->buildUrl(self::PATH_INSTALL . '/conflicts');

        // Проверка конфликтов версий между modules.json и БД/config
        try {
            $conflictService = $this->getServiceFactory()->getConflictService();
            $conflicts = $conflictService->getConflicts();
            $data['conflicts_count'] = count($conflicts);
            $data['has_conflicts'] = !empty($conflicts);
        } catch (\Throwable $e) {
            $data['conflicts_count'] = 0;
            $data['has_conflicts'] = false;
        }

        $data['user_token'] = $this->session->data['user_token'] ?? '';

        return $data;
    }

    /**
     * Страница установленных модулей (объединённый макет)
     */
    public function installed()
    {
        $this->load->language(self::PATH_UPDATER);

        $this->document->setTitle($this->trans(self::PATH_UPDATER . '.heading_title'));

        $data = $this->getCommonData();
        $data['current_page'] = 'installed';
        $data['page_title'] = 'Установленные модули';

        // Для установленных модулей кнопка «Назад» ведёт в marketplace
        $data['back'] = $this->buildUrl('marketplace/extension', ['type' => 'module']);

        // AJAX URL-адреса (делегируем к gdt_updater)
        $data['settings_url'] = html_entity_decode($this->buildUrl(self::PATH_UPDATER . '/settings'));
        $data['save_settings_url'] = html_entity_decode($this->buildUrl(self::PATH_UPDATER . '/saveSettings'));
        $data['check_updates_url'] = html_entity_decode($this->buildUrl(self::PATH_UPDATER . '/check'));
        $data['toggle_auto_update_url'] = html_entity_decode($this->buildUrl(self::PATH_UPDATER . '/toggleAutoUpdate'));
        $data['delete_multiple_url'] = html_entity_decode($this->buildUrl(self::PATH_UPDATER . '/deleteMultiple'));
        $data['install_module_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/installModule'));

        // Данные установленных модулей с проверкой обновлений
        try {
            $data['modules'] = $this->getInstalledModulesData();
        } catch (\Exception $e) {
            LoggerService::write('GDT installed() error: ' . $e->getMessage());
            $data['modules'] = array();
            $data['registry_error'] = $e->getMessage();
        }

        $data = array_merge($data, $this->layout());

        $this->render(self::PATH_INSTALL, $data);
    }

    /**
     * Получение данных установленных модулей с проверкой обновлений.
     * Логика аналогична gdt_updater::getModulesData().
     */
    private function getInstalledModulesData()
    {
        $installed_modules = $this->getServiceFactory()->getModuleService()->getInstalledModules();

        if (empty($installed_modules)) {
            return array();
        }

        $modules = array();
        $server_url = $this->getConfig('module_gdt_updater_server');
        $api_key = $this->getConfig('module_gdt_updater_api_key') ?: '';

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
                'settings_url' => '',
                'auto_update' => false,
                'version_known' => $module['version'] !== '0.0.0',
            );

            // Ссылка на настройки модуля
            if (!empty($module['controller'])) {
                $module_data['settings_url'] = $this->buildUrl($module['controller']);
            } elseif (!empty($module['code'])) {
                $module_data['settings_url'] = $this->buildUrl('extension/module/' . $module['code']);
                $module_data['delete_url'] = $this->buildUrl(self::PATH_UPDATER . '/delete', ['code' => $module['code']]);
            }

            // Проверка обновлений
            if (!empty($server_url)) {
                $update_info = $this->getServiceFactory()->getUpdateService()->checkModuleUpdate($server_url, $module, $api_key);
                if ($update_info && !isset($update_info['error'])) {
                    $module_data['has_update'] = true;
                    $module_data['new_version'] = $update_info['version'];
                    $module_data['update_url'] = $this->buildUrl(self::PATH_UPDATER . '/update', ['code' => $module['code']]);
                }
            }

            // Настройка автообновления
            $auto_modules = $this->getConfig('module_gdt_updater_auto_modules') ?: array();
            $module_data['auto_update'] = $module['version'] !== '0.0.0' && in_array($module['code'], $auto_modules);

            $modules[] = $module_data;
        }

        return $modules;
    }

    /**
     * Получение рекомендуемых модулей
     */
    public function getFeaturedModules()
    {
        try {
            $catalogService = $this->getServiceFactory()->getModuleCatalogService();
            $moduleService = $this->getServiceFactory()->getModuleService();

            $modules = $catalogService->getFeaturedModules();
            $installed_modules = $moduleService->getInstalledModules();
            $modules = $catalogService->markInstalledModules($modules, $installed_modules);

            $json = array(
                'success' => true,
                'modules' => $modules
            );
        } catch (Exception $e) {
            $json = array(
                'error' => $e->getMessage()
            );
        }

        $this->jsonResponse($json);
    }

    /**
     * Получение популярных модулей
     */
    public function getPopularModules()
    {
        try {
            $catalogService = $this->getServiceFactory()->getModuleCatalogService();
            $moduleService = $this->getServiceFactory()->getModuleService();

            $modules = $catalogService->getPopularModules();
            $installed_modules = $moduleService->getInstalledModules();
            $modules = $catalogService->markInstalledModules($modules, $installed_modules);

            $json = array(
                'success' => true,
                'modules' => $modules
            );
        } catch (Exception $e) {
            $json = array(
                'error' => $e->getMessage()
            );
        }

        $this->jsonResponse($json);
    }

    /**
     * Получение новых модулей
     */
    public function getNewestModules()
    {
        try {
            $catalogService = $this->getServiceFactory()->getModuleCatalogService();
            $moduleService = $this->getServiceFactory()->getModuleService();

            $modules = $catalogService->getNewestModules();
            $installed_modules = $moduleService->getInstalledModules();
            $modules = $catalogService->markInstalledModules($modules, $installed_modules);

            $json = array(
                'success' => true,
                'modules' => $modules
            );
        } catch (Exception $e) {
            $json = array(
                'error' => $e->getMessage()
            );
        }

        $this->jsonResponse($json);
    }

    /**
     * Поиск модулей
     */
    public function searchModules()
    {
        $this->load->language(self::PATH_INSTALL);

        $query = $this->getPostData('query', '');
        $category = $this->getPostData('category', '');
        $sort = $this->getPostData('sort', 'relevance');
        $price = $this->getPostData('price', '');

        if (empty($query)) {
            $json = array(
                'error' => $this->trans('error_query_empty')
            );
        } else {
            try {
                $catalogService = $this->getServiceFactory()->getModuleCatalogService();
                $moduleService = $this->getServiceFactory()->getModuleService();

                $modules = $catalogService->searchModules($query, $category, $sort, $price);
                $installed_modules = $moduleService->getInstalledModules();
                $modules = $catalogService->markInstalledModules($modules, $installed_modules);

                $json = array(
                    'success' => true,
                    'modules' => $modules
                );
            } catch (Exception $e) {
                $json = array(
                    'error' => $e->getMessage()
                );
            }
        }

        $this->jsonResponse($json);
    }

    /**
     * Получение модулей из внешнего магазина
     */
    public function getStoreModules()
    {
        try {
            $catalogService = $this->getServiceFactory()->getModuleCatalogService();
            $moduleService = $this->getServiceFactory()->getModuleService();

            $modules = $catalogService->getStoreModules();
            $installed_modules = $moduleService->getInstalledModules();
            $modules = $catalogService->markInstalledModules($modules, $installed_modules);

            $json = array(
                'success' => true,
                'modules' => $modules
            );
        } catch (Exception $e) {
            $json = array(
                'error' => $e->getMessage()
            );
        }

        $this->jsonResponse($json);
    }

    /**
     * Поиск модулей в внешнем магазине
     */
    public function searchStoreModules()
    {
        $this->load->language(self::PATH_INSTALL);

        $query = $this->getPostData('query', '');
        $category = $this->getPostData('category', '');
        $featured = $this->getPostData('featured', 0);

        if (empty($query)) {
            $json = array(
                'error' => $this->trans('error_query_empty')
            );
        } else {
            try {
                $catalogService = $this->getServiceFactory()->getModuleCatalogService();
                $moduleService = $this->getServiceFactory()->getModuleService();

                $modules = $catalogService->searchModules($query, $category);
                $installed_modules = $moduleService->getInstalledModules();
                $modules = $catalogService->markInstalledModules($modules, $installed_modules);

                $json = array(
                    'success' => true,
                    'modules' => $modules
                );
            } catch (Exception $e) {
                $json = array(
                    'error' => $e->getMessage()
                );
            }
        }

        $this->jsonResponse($json);
    }

    /**
     * Вспомогательный метод для получения URL сервера
     */
    private function getServerUrl(): string
    {
        return $this->getConfig('module_gdt_updater_server')
            ?: getenv('GDT_UPDATE_SERVER')
            ?: (defined('HTTP_SERVER') ? HTTP_SERVER . 'ocm_gdt_update/server' : '');
    }

    /**
     * Вспомогательный метод для парсинга инфо о модуле
     */
    private function parseServerModuleInfo($info): array
    {
        if (is_array($info)) {
            return $info;
        }
        if (is_string($info)) {
            $decoded = json_decode(html_entity_decode($info, ENT_QUOTES, 'UTF-8'), true);
            if (!is_array($decoded)) {
                $decoded = json_decode($info, true);
            }
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Установка модуля
     */
    public function installModule()
    {
        $this->load->language(self::PATH_INSTALL);

        $json = array();

        if (!$this->isPost()) {
            $this->jsonResponse(['error' => $this->trans('error_method_invalid')]);
            return;
        }

        $module_code = $this->getPostData('module_code');
        if (empty($module_code)) {
            $this->jsonResponse(['error' => $this->trans('error_code_not_found')]);
            return;
        }

        // 2. Подготовка данных модуля
        $server_module_info = $this->parseServerModuleInfo($this->getPostData('server_module_info'));
        $server_module_info['code'] = $server_module_info['code'] ?? $module_code;

        if (empty($server_module_info['code'])) {
            $server_module_info['code'] = $module_code;
        }

        $version = $this->getPostData('version', 'latest');
        if (empty($server_module_info['version']) && !empty($version) && $version !== 'latest') {
            $server_module_info['version'] = $version;
        }

        try {
            // 3. Получение URL сервера
            $this->validatePermission();
            $server_url = $this->getServerUrl();
            if (empty($server_url)) {
                throw new Exception($this->trans('error_server_not_configured'));
            }

            $api_key = $this->getConfig('module_gdt_updater_api_key') ?: '';

            // Если метаданные с сервера неполные (нет версии или нет названия), запрашиваем у сервера напрямую
            if (empty($server_module_info['version']) || $server_module_info['version'] === '0.0.0' || empty($server_module_info['name'])) {
                try {
                    $updateService = $this->getServiceFactory()->getUpdateService();
                    $check_info = $updateService->checkModuleUpdate($server_url, ['code' => $module_code, 'version' => '0.0.0'], $api_key);
                    if ($check_info && is_array($check_info) && !isset($check_info['error'])) {
                        foreach ($check_info as $k => $v) {
                            if (empty($server_module_info[$k]) && $v !== null && $v !== '') {
                                $server_module_info[$k] = $v;
                            }
                        }
                        if (($version === 'latest' || empty($version)) && !empty($check_info['version'])) {
                            $server_module_info['version'] = $check_info['version'];
                        }
                    }
                } catch (\Throwable $e) {
                    LoggerService::warning("Could not pre-fetch module info for {$module_code}: " . $e->getMessage(), 'InstallModules');
                }
            }

            $version_module = ($version && $version !== 'latest') ? $version
                : (!empty($server_module_info['version']) ? $server_module_info['version'] : 'latest');

            // 4. Скачивание
            $updateService = $this->getServiceFactory()->getUpdateService();
            $download_result = $updateService->downloadModule($server_url, $module_code, $version_module, $api_key);

            if (empty($download_result['success'])) {
                throw new Exception(sprintf($this->trans('error_download_failed'), ($download_result['error'] ?? 'Unknown error')));
            }

            $file_path = $download_result['file_path'];

            // 5. Установка
            $installService = $this->getServiceFactory()->getInstallService();
            $server_module_info['_downloaded_version'] = $download_result['version'] ?? '';
            $install_result = $installService->installModule($file_path, $module_code, $server_module_info);

            // Удаляем временный файл сразу после попытки установки
            if (file_exists($file_path)) {
                @unlink($file_path);
            }

            if ($install_result !== true) {
                $error_msg = is_string($install_result) ? $install_result : $this->trans('error_install_failed');
                throw new Exception($error_msg);
            }

            // 6. Успешное завершение
            $this->cache->delete('*');
            LoggerService::write("GDT Install Modules: Successfully installed module {$module_code}");

            $json['success'] = sprintf($this->trans('text_install_success'), $module_code);
            $installed = $this->getServiceFactory()->getModuleService()->getModuleByCode($module_code);
            $json['installed_version'] = $installed['version'];
            $json['update_available'] = !empty($server_module_info['version']) && version_compare(
                ltrim($server_module_info['version'], 'vV'),
                $installed['version'],
                '>'
            );
            if ($installed['version'] === '0.0.0') {
                $json['warning'] = $this->trans(self::PATH_UPDATER . '.warning_version_unknown');
            }
        } catch (\Throwable $e) {
            $json['error'] = $e->getMessage();
            LoggerService::write("GDT Install Modules error: " . $e->getMessage());
        }

        $this->jsonResponse($json);
        return;
    }

    /**
     * Страница решения конфликтов версий между modules.json и БД/config
     */
    public function conflicts()
    {
        $this->load->language(self::PATH_INSTALL);

        $this->document->setTitle($this->trans('heading_title') . ' - ' . $this->trans('text_conflict_resolution'));

        $data = $this->getCommonData();
        $data['current_page'] = 'conflicts';
        $data['page_title'] = $this->trans('text_conflict_resolution');
        $data['page_description'] = $this->trans('text_conflict_page_desc');

        // AJAX URLs для страницы конфликтов
        $data['resolve_conflict_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/resolveConflict'));
        $data['resolve_all_conflicts_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/resolveAllConflicts'));
        $data['get_conflicts_url'] = html_entity_decode($this->buildUrl(self::PATH_INSTALL . '/getConflictsJson'));

        try {
            $conflictService = $this->getServiceFactory()->getConflictService();
            $data['conflicts'] = $conflictService->getConflicts();
        } catch (\Throwable $e) {
            LoggerService::error('Error loading conflicts: ' . $e->getMessage(), 'ControllerExtensionModuleGdtInstallModules');
            $data['conflicts'] = [];
            $data['conflict_error'] = $e->getMessage();
        }

        $data = array_merge($data, $this->layout());

        $this->render(self::PATH_INSTALL, $data);
    }

    /**
     * AJAX: Получение актуального списка конфликтов
     */
    public function getConflictsJson()
    {
        try {
            $conflictService = $this->getServiceFactory()->getConflictService();
            $conflicts = $conflictService->getConflicts();
            $this->jsonResponse([
                'success' => true,
                'conflicts' => $conflicts,
                'count' => count($conflicts)
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Решение одного конфликта
     */
    public function resolveConflict()
    {
        try {
            $hasPermission = $this->user->hasPermission('modify', self::PATH_INSTALL) || $this->user->hasPermission('modify', self::PATH_UPDATER);
            if (!$hasPermission) {
                throw new Exception($this->trans('error_permission'));
            }

            $code = $this->getPostData('code');
            $trust = $this->getPostData('trust'); // 'file' или 'db'

            if (empty($code)) {
                $this->jsonResponse(['error' => $this->trans('error_code_not_found')]);
                return;
            }

            if (!in_array($trust, ['file', 'db'], true)) {
                $this->jsonResponse(['error' => 'Неверный выбор источника: укажите file или db']);
                return;
            }

            $conflictService = $this->getServiceFactory()->getConflictService();
            $result = $conflictService->resolveConflict($code, $trust);

            // Получаем обновленный список конфликтов
            $remaining = $conflictService->getConflicts();
            $result['remaining_count'] = count($remaining);
            $result['conflicts'] = $remaining;

            $this->jsonResponse($result);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Массовое решение конфликтов
     */
    public function resolveAllConflicts()
    {
        try {
            $hasPermission = $this->user->hasPermission('modify', self::PATH_INSTALL) || $this->user->hasPermission('modify', self::PATH_UPDATER);
            if (!$hasPermission) {
                throw new Exception($this->trans('error_permission'));
            }

            $trust = $this->getPostData('trust'); // 'file' или 'db'
            $resolutions = $this->getPostData('resolutions');

            $conflictService = $this->getServiceFactory()->getConflictService();

            if (!empty($trust) && in_array($trust, ['file', 'db'], true)) {
                $conflicts = $conflictService->getConflicts();
                $items = [];
                foreach ($conflicts as $c) {
                    $items[$c['code']] = $trust;
                }
                $result = $conflictService->resolveBatch($items);
            } elseif (!empty($resolutions) && is_array($resolutions)) {
                $result = $conflictService->resolveBatch($resolutions);
            } else {
                $this->jsonResponse(['error' => 'Параметры для решения конфликтов не переданы']);
                return;
            }

            $remaining = $conflictService->getConflicts();
            $result['remaining_count'] = count($remaining);
            $result['conflicts'] = $remaining;

            $this->jsonResponse($result);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => $e->getMessage()]);
        }
    }
}
