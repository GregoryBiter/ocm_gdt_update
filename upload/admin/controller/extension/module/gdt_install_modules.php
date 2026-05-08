<?php
use \Gbitstudio\Modules\Services\LoggerService;
class ControllerExtensionModuleGdtInstallModules extends Controller
{
    const PATH_INSTALL = 'extension/module/gdt_install_modules';
    const PATH_UPDATER = 'extension/module/gdt_updater';
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
        response()->redirect(route(self::PATH_INSTALL . '/store'));
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
        $this->document->setTitle(__('heading_title') . ' - ' . __(self::PATH_INSTALL . '.text_store'));

        $data = $this->getCommonData();
        $data['current_page'] = 'store';
        $data['page_title'] = __(self::PATH_INSTALL . '.text_store');
        $data['page_description'] = __(self::PATH_INSTALL . '.text_store_desc');

        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode(route(self::PATH_INSTALL . '/getStoreModules'));
        $data['search_modules_url'] = html_entity_decode(route(self::PATH_INSTALL . '/searchStoreModules'));
        $data['install_module_url'] = html_entity_decode(route(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        response(view(self::PATH_INSTALL . '', $data));
    }

    /**
     * Страница рекомендуемых модулей
     */
    public function featured()
    {
        $this->load->language(self::PATH_INSTALL . '');

        $this->document->setTitle(__('heading_title') . ' - ' . __(self::PATH_INSTALL . '.text_featured'));

        $data = $this->getCommonData();
        $data['current_page'] = 'featured';
        $data['page_title'] = __(self::PATH_INSTALL . '.text_featured');
        $data['page_description'] = __(self::PATH_INSTALL . '.text_featured_desc');

        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode(route(self::PATH_INSTALL . '/getFeaturedModules'));
        $data['install_module_url'] = html_entity_decode(route(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        response(view(self::PATH_INSTALL . '', $data));
    }

    /**
     * Страница популярных модулей
     */
    public function popular()
    {
        $this->load->language(self::PATH_INSTALL . '');

        $this->document->setTitle(__('heading_title') . ' - ' . __(self::PATH_INSTALL . '.text_popular'));

        $data = $this->getCommonData();
        $data['current_page'] = 'popular';
        $data['page_title'] = __(self::PATH_INSTALL . '.text_popular');
        $data['page_description'] = __(self::PATH_INSTALL . '.text_popular_desc');

        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode(route(self::PATH_INSTALL . '/getPopularModules'));
        $data['install_module_url'] = html_entity_decode(route(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        response(view(self::PATH_INSTALL . '', $data));
    }

    /**
     * Страница новых модулей
     */
    public function newest()
    {
        $this->load->language(self::PATH_INSTALL . '');

        $this->document->setTitle(__('heading_title') . ' - ' . __(self::PATH_INSTALL . '.text_newest'));

        $data = $this->getCommonData();
        $data['current_page'] = 'newest';
        $data['page_title'] = __(self::PATH_INSTALL . '.text_newest');
        $data['page_description'] = __(self::PATH_INSTALL . '.text_newest_desc');

        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode(route(self::PATH_INSTALL . '/getNewestModules'));
        $data['install_module_url'] = html_entity_decode(route(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        response(view(self::PATH_INSTALL . '', $data));
    }

    /**
     * Страница поиска модулей
     */
    public function search()
    {
        $this->load->language(self::PATH_INSTALL . '');

        $this->document->setTitle(__('heading_title') . ' - ' . __(self::PATH_INSTALL . '.text_search'));

        $data = $this->getCommonData();
        $data['current_page'] = 'search';
        $data['page_title'] = __(self::PATH_INSTALL . '.text_search');
        $data['page_description'] = __(self::PATH_INSTALL . '.text_search_desc');

        // AJAX URLs для этой страницы
        $data['search_modules_url'] = html_entity_decode(route(self::PATH_INSTALL . '/searchModules'));
        $data['install_module_url'] = html_entity_decode(route(self::PATH_INSTALL . '/installModule'));

        $data = array_merge($data, $this->layout());

        response(view(self::PATH_INSTALL . '', $data));
    }

    /**
     * Получение общих данных для всех страниц
     */
    private function getCommonData()
    {
        $data = array();

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => __('text_home'),
            'href' => route('common/dashboard')
        );

        $data['breadcrumbs'][] = array(
            'text' => __('text_extension'),
            'href' => route('marketplace/extension', ['type' => 'module'])
        );

        $data['breadcrumbs'][] = array(
            'text' => 'GDT Module Updater',
            'href' => route(self::PATH_UPDATER . '')
        );

        $data['breadcrumbs'][] = array(
            'text' => __('heading_title'),
            'href' => route(self::PATH_INSTALL . '/store')
        );

        // URL для возврата
        $data['back'] = route(self::PATH_UPDATER . '');

        // Навигационные ссылки между страницами
        $data['store_url'] = route(self::PATH_INSTALL . '/store');
        $data['featured_url'] = route(self::PATH_INSTALL . '/featured');
        $data['popular_url'] = route(self::PATH_INSTALL . '/popular');
        $data['newest_url'] = route(self::PATH_INSTALL . '/newest');
        $data['search_url'] = route(self::PATH_INSTALL . '/search');
        $data['installed_url'] = route(self::PATH_INSTALL . '/installed');

        $data['user_token'] = $this->session->data['user_token'];

        return $data;
    }

    /**
     * Страница установленных модулей (объединённый макет)
     */
    public function installed()
    {
        $this->document->setTitle(__(self::PATH_UPDATER . '.heading_title'));

        $data = $this->getCommonData();
        $data['current_page'] = 'installed';
        $data['page_title'] = 'Установленные модули';

        // Для установленных модулей кнопка «Назад» ведёт в marketplace
        $data['back'] = route('marketplace/extension', ['type' => 'module']);

        // AJAX URL-адреса (делегируем к gdt_updater)
        $data['settings_url'] = html_entity_decode(route(self::PATH_UPDATER . '/settings'));
        $data['save_settings_url'] = html_entity_decode(route(self::PATH_UPDATER . '/saveSettings'));
        $data['check_updates_url'] = html_entity_decode(route(self::PATH_UPDATER . '/check'));
        $data['toggle_auto_update_url'] = html_entity_decode(route(self::PATH_UPDATER . '/toggleAutoUpdate'));
        $data['delete_multiple_url'] = html_entity_decode(route(self::PATH_UPDATER . '/deleteMultiple'));
        $data['install_module_url'] = html_entity_decode(route(self::PATH_INSTALL . '/installModule'));

        // Данные установленных модулей с проверкой обновлений
        try {
            $data['modules'] = $this->getInstalledModulesData();
        } catch (\Exception $e) {
            LoggerService::write('GDT installed() error: ' . $e->getMessage());
            $data['modules'] = array();
        }

        $data = array_merge($data, $this->layout());

        response(view(self::PATH_INSTALL . '', $data));
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
        $server_url = config('module_gdt_updater_server');
        $api_key = config('module_gdt_updater_api_key') ?: '';

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
            );

            // Ссылка на настройки модуля
            if (!empty($module['controller'])) {
                $module_data['settings_url'] = $this->url->link($module['controller'], 'user_token=' . $this->session->data['user_token'], true);
            } elseif (!empty($module['code'])) {
                $module_data['settings_url'] = $this->url->link('extension/module/' . $module['code'], 'user_token=' . $this->session->data['user_token'], true);
                $module_data['delete_url'] = $this->url->link(self::PATH_UPDATER . '/delete', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'], true);
            }

            // Проверка обновлений
            if (!empty($server_url)) {
                $update_info = $this->getServiceFactory()->getUpdateService()->checkModuleUpdate($server_url, $module, $api_key);
                if ($update_info && !isset($update_info['error'])) {
                    $module_data['has_update'] = true;
                    $module_data['new_version'] = $update_info['version'];
                    $module_data['update_url'] = $this->url->link(self::PATH_UPDATER . '/update', 'user_token=' . $this->session->data['user_token'] . '&code=' . $module['code'], true);
                }
            }

            // Настройка автообновления
            $auto_modules = config('module_gdt_updater_auto_modules') ?: array();
            $module_data['auto_update'] = in_array($module['code'], $auto_modules);

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

        response()->json($json);
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

        response()->json($json);
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

        response()->json($json);
    }

    /**
     * Поиск модулей
     */
    public function searchModules()
    {
        $query = request()->post('query', '');
        $category = request()->post('category', '');
        $sort = request()->post('sort', 'relevance');
        $price = request()->post('price', '');

        if (empty($query)) {
            $json = array(
                'error' => __('error_query_empty')
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

        response()->json($json);
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

        response()->json($json);
    }

    /**
     * Поиск модулей в внешнем магазине
     */
    public function searchStoreModules()
    {
        $query = request()->post('query', '');
        $category = request()->post('category', '');
        $featured = request()->post('featured', 0);

        if (empty($query)) {
            $json = array(
                'error' => __('error_query_empty')
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

        response()->json($json);
    }

    /**
     * Установка модуля
     */
    public function installModule()
    {
        $this->load->language(self::PATH_INSTALL . '');

        $json = array();

        if (!request()->isMethod('post')) {
            $json['error'] = __('error_method_invalid');
        } elseif (!isset($this->request->post['module_code']) || empty($this->request->post['module_code'])) {
            $json['error'] = __('error_code_not_found');
        } else {
            $module_code = request()->post('module_code');
            $version = request()->post('version', 'latest');
            $server_module_info = array();
            if (request()->post('server_module_info')) {
                if (is_string(request()->post('server_module_info'))) {
                    $decoded = json_decode(request()->post('server_module_info'), true);
                    if (is_array($decoded)) {
                        $server_module_info = $decoded;
                    }
                } elseif (is_array(request()->post('server_module_info'))) {
                    $server_module_info = request()->post('server_module_info');
                }
            }

            if (empty($server_module_info['code'])) {
                $server_module_info['code'] = $module_code;
            }

            $version_module = isset($server_module_info['version']) && $server_module_info['version'] !== ''
                ? $server_module_info['version']
                : $version;

            try {
                // Получаем URL сервера
                $server_url = config('module_gdt_updater_server');
                if (empty($server_url)) {
                    $server_url = getenv('GDT_UPDATE_SERVER');
                    if (empty($server_url)) {
                        $server_url = defined('HTTP_SERVER') ? HTTP_SERVER . 'ocm_gdt_update/server' : '';
                    }
                }

                if (empty($server_url)) {
                    $json['error'] = __('error_server_not_configured');
                } else {
                    $api_key = config('module_gdt_updater_api_key') ?: '';

                    // Скачиваем модуль
                    $updateService = $this->getServiceFactory()->getUpdateService();
                    $download_result = $updateService->downloadModule($server_url, $module_code, $version_module, $api_key);

                    if (!$download_result['success']) {
                        $json['error'] = sprintf(__('error_download_failed'), ($download_result['error'] ?? 'Unknown error'));
                    } else {
                        // Устанавливаем модуль
                        $installService = $this->getServiceFactory()->getInstallService();
                        $install_result = $installService->installModule($download_result['file_path'], $module_code, $server_module_info);

                        if ($install_result === true) {
                            $json['success'] = sprintf(__('text_install_success'), $module_code);

                            // Очищаем кеш
                            $this->cache->delete('*');

                            LoggerService::write('GDT Install Modules: Successfully installed module ' . $module_code);
                        } else {
                            $json['error'] = is_string($install_result) ? $install_result : __('error_install_failed');
                            LoggerService::write('GDT Install Modules: Error installing module ' . $module_code . ': ' . $json['error']);
                        }

                        // Удаляем временный файл
                        if (file_exists($download_result['file_path'])) {
                            @unlink($download_result['file_path']);
                        }
                    }
                }
            } catch (Exception $e) {
                $json['error'] = sprintf(__('error_general'), $e->getMessage());
                LoggerService::write('GDT Install Modules error: ' . $e->getMessage());
            }
        }

        response()->json($json);
    }
}
