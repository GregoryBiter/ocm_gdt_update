<?php
use \Gbitstudio\Modules\Services\LoggerService;
class ControllerExtensionModuleGdtInstallModules extends Controller {
    private $error = array();

    /**
     * Отримує фабрику сервісів
     * 
     * @return \Gbitstudio\Modules\ServiceFactory
     */
    private function getServiceFactory() {
        if (!$this->registry->has('gb_modules')) {
            $this->registry->set('gb_modules', new \Gbitstudio\Modules\ServiceFactory($this->registry));
        }
        
        return $this->registry->get('gb_modules');
    }

    public function index() {
        // Перенаправляем на страницу магазина модулей по умолчанию
        $this->response->redirect($this->url->link('extension/module/gdt_install_modules/store', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Страница магазина модулей (по умолчанию)
     */
    public function store() {
        $this->load->language('extension/module/gdt_install_modules');
        
        $this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->language->get('text_store'));
        
        $data = $this->getCommonData();
        $data['current_page'] = 'store';
        $data['page_title'] = $this->language->get('text_store');
        $data['page_description'] = $this->language->get('text_store_desc');
        
        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/getStoreModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['search_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/searchStoreModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['install_module_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/installModule', 'user_token=' . $this->session->data['user_token'], true));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/gdt_install_modules', $data));
    }

    /**
     * Страница рекомендуемых модулей
     */
    public function featured() {
        $this->load->language('extension/module/gdt_install_modules');
        
        $this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->language->get('text_featured'));
        
        $data = $this->getCommonData();
        $data['current_page'] = 'featured';
        $data['page_title'] = $this->language->get('text_featured');
        $data['page_description'] = $this->language->get('text_featured_desc');
        
        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/getFeaturedModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['install_module_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/installModule', 'user_token=' . $this->session->data['user_token'], true));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/gdt_install_modules', $data));
    }

    /**
     * Страница популярных модулей
     */
    public function popular() {
        $this->load->language('extension/module/gdt_install_modules');
        
        $this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->language->get('text_popular'));
        
        $data = $this->getCommonData();
        $data['current_page'] = 'popular';
        $data['page_title'] = $this->language->get('text_popular');
        $data['page_description'] = $this->language->get('text_popular_desc');
        
        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/getPopularModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['install_module_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/installModule', 'user_token=' . $this->session->data['user_token'], true));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/gdt_install_modules', $data));
    }

    /**
     * Страница новых модулей
     */
    public function newest() {
        $this->load->language('extension/module/gdt_install_modules');
        
        $this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->language->get('text_newest'));
        
        $data = $this->getCommonData();
        $data['current_page'] = 'newest';
        $data['page_title'] = $this->language->get('text_newest');
        $data['page_description'] = $this->language->get('text_newest_desc');
        
        // AJAX URLs для этой страницы
        $data['get_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/getNewestModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['install_module_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/installModule', 'user_token=' . $this->session->data['user_token'], true));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/gdt_install_modules', $data));
    }

    /**
     * Страница поиска модулей
     */
    public function search() {
        $this->load->language('extension/module/gdt_install_modules');
        
        $this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->language->get('text_search'));
        
        $data = $this->getCommonData();
        $data['current_page'] = 'search';
        $data['page_title'] = $this->language->get('text_search');
        $data['page_description'] = $this->language->get('text_search_desc');
        
        // AJAX URLs для этой страницы
        $data['search_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/searchModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['install_module_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/installModule', 'user_token=' . $this->session->data['user_token'], true));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/gdt_install_modules', $data));
    }

    /**
     * Получение общих данных для всех страниц
     */
    private function getCommonData() {
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
            'text' => 'GDT Module Updater',
            'href' => $this->url->link('extension/module/gdt_updater', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/gdt_install_modules/store', 'user_token=' . $this->session->data['user_token'], true)
        );

        // URL для возврата
        $data['back'] = $this->url->link('extension/module/gdt_updater', 'user_token=' . $this->session->data['user_token'], true);
        
        // Навигационные ссылки между страницами
        $data['store_url'] = $this->url->link('extension/module/gdt_install_modules/store', 'user_token=' . $this->session->data['user_token'], true);
        $data['featured_url'] = $this->url->link('extension/module/gdt_install_modules/featured', 'user_token=' . $this->session->data['user_token'], true);
        $data['popular_url'] = $this->url->link('extension/module/gdt_install_modules/popular', 'user_token=' . $this->session->data['user_token'], true);
        $data['newest_url'] = $this->url->link('extension/module/gdt_install_modules/newest', 'user_token=' . $this->session->data['user_token'], true);
        $data['search_url'] = $this->url->link('extension/module/gdt_install_modules/search', 'user_token=' . $this->session->data['user_token'], true);

        $data['user_token'] = $this->session->data['user_token'];

        return $data;
    }
    
    /**
     * Получение рекомендуемых модулей
     */
    public function getFeaturedModules() {
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
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Получение популярных модулей
     */
    public function getPopularModules() {
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
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Получение новых модулей
     */
    public function getNewestModules() {
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
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Поиск модулей
     */
    public function searchModules() {
        $query = isset($this->request->post['query']) ? $this->request->post['query'] : '';
        $category = isset($this->request->post['category']) ? $this->request->post['category'] : '';
        $sort = isset($this->request->post['sort']) ? $this->request->post['sort'] : 'relevance';
        $price = isset($this->request->post['price']) ? $this->request->post['price'] : '';
        
        if (empty($query)) {
            $json = array(
                'error' => $this->language->get('error_query_empty')
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
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Получение модулей из внешнего магазина
     */
    public function getStoreModules() {
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
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Поиск модулей в внешнем магазине
     */
    public function searchStoreModules() {
        $query = isset($this->request->post['query']) ? trim($this->request->post['query']) : '';
        $category = isset($this->request->post['category']) ? $this->request->post['category'] : '';
        $featured = isset($this->request->post['featured']) ? (int)$this->request->post['featured'] : 0;
        
        if (empty($query)) {
            $json = array(
                'error' => $this->language->get('error_query_empty')
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
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Установка модуля
     */
    public function installModule() {
        $this->load->language('extension/module/gdt_install_modules');
        
        $json = array();
        
        if ($this->request->server['REQUEST_METHOD'] != 'POST') {
            $json['error'] = $this->language->get('error_method_invalid');
        } elseif (!isset($this->request->post['module_code']) || empty($this->request->post['module_code'])) {
            $json['error'] = $this->language->get('error_code_not_found');
        } else {
            $module_code = $this->request->post['module_code'];
            $version = isset($this->request->post['version']) ? $this->request->post['version'] : 'latest';
            
            try {
                // Получаем URL сервера
                $server_url = $this->config->get('module_gdt_updater_server');
                if (empty($server_url)) {
                    $server_url = getenv('GDT_UPDATE_SERVER');
                    if (empty($server_url)) {
                        $server_url = defined('HTTP_SERVER') ? HTTP_SERVER . 'ocm_gdt_update/server' : '';
                    }
                }
                
                if (empty($server_url)) {
                    $json['error'] = $this->language->get('error_server_not_configured');
                } else {
                    $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
                    
                    // Скачиваем модуль
                    $updateService = $this->getServiceFactory()->getUpdateService();
                    $download_result = $updateService->downloadModule($server_url, $module_code, $version, $api_key);
                    
                    if (!$download_result['success']) {
                        $json['error'] = sprintf($this->language->get('error_download_failed'), ($download_result['error'] ?? 'Unknown error'));
                    } else {
                        // Устанавливаем модуль
                        $installService = $this->getServiceFactory()->getInstallService();
                        $install_result = $installService->installModule($download_result['file_path'], $module_code);
                        
                        if ($install_result === true) {
                            $json['success'] = sprintf($this->language->get('text_install_success'), $module_code);
                            
                            // Очищаем кеш
                            $this->cache->delete('*');
                            
                            LoggerService::write('GDT Install Modules: Successfully installed module ' . $module_code);
                        } else {
                            $json['error'] = is_string($install_result) ? $install_result : $this->language->get('error_install_failed');
                            LoggerService::write('GDT Install Modules: Error installing module ' . $module_code . ': ' . $json['error']);
                        }
                        
                        // Удаляем временный файл
                        if (file_exists($download_result['file_path'])) {
                            @unlink($download_result['file_path']);
                        }
                    }
                }
            } catch (Exception $e) {
                $json['error'] = sprintf($this->language->get('error_general'), $e->getMessage());
                LoggerService::write('GDT Install Modules error: ' . $e->getMessage());
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
