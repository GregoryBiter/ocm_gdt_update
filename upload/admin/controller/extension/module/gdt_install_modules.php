<?php
class ControllerExtensionModuleGdtInstallModules extends Controller {
    private $error = array();

    public function index() {
        // Перенаправляем на страницу магазина модулей по умолчанию
        $this->response->redirect($this->url->link('extension/module/gdt_install_modules/store', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Страница магазина модулей (по умолчанию)
     */
    public function store() {
        $this->load->language('extension/module/gdt_install_modules');
        
        $this->document->setTitle($this->language->get('heading_title') . ' - Магазин модулей');
        
        $data = $this->getCommonData();
        $data['current_page'] = 'store';
        $data['page_title'] = 'Магазин модулей';
        $data['page_description'] = 'Найдите и установите модули из официального каталога';
        
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
        
        $this->document->setTitle($this->language->get('heading_title') . ' - Рекомендуемые модули');
        
        $data = $this->getCommonData();
        $data['current_page'] = 'featured';
        $data['page_title'] = 'Рекомендуемые модули';
        $data['page_description'] = 'Модули, рекомендованные нашими экспертами';
        
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
        
        $this->document->setTitle($this->language->get('heading_title') . ' - Популярные модули');
        
        $data = $this->getCommonData();
        $data['current_page'] = 'popular';
        $data['page_title'] = 'Популярные модули';
        $data['page_description'] = 'Самые популярные модули среди пользователей';
        
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
        
        $this->document->setTitle($this->language->get('heading_title') . ' - Новые модули');
        
        $data = $this->getCommonData();
        $data['current_page'] = 'newest';
        $data['page_title'] = 'Новые модули';
        $data['page_description'] = 'Последние добавленные модули';
        
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
        
        $this->document->setTitle($this->language->get('heading_title') . ' - Поиск модулей');
        
        $data = $this->getCommonData();
        $data['current_page'] = 'search';
        $data['page_title'] = 'Поиск модулей';
        $data['page_description'] = 'Найдите нужный модуль по ключевым словам';
        
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
        $this->load->model('extension/module/gdt_update_server');
        
        try {
            $modules = $this->model_extension_module_gdt_update_server->getFeaturedModules();
            
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
        $this->load->model('extension/module/gdt_update_server');
        
        try {
            $modules = $this->model_extension_module_gdt_update_server->getPopularModules();
            
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
        $this->load->model('extension/module/gdt_update_server');
        
        try {
            $modules = $this->model_extension_module_gdt_update_server->getNewestModules();
            
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
        $this->load->model('extension/module/gdt_update_server');
        
        $query = isset($this->request->post['query']) ? $this->request->post['query'] : '';
        $category = isset($this->request->post['category']) ? $this->request->post['category'] : '';
        $sort = isset($this->request->post['sort']) ? $this->request->post['sort'] : 'relevance';
        $price = isset($this->request->post['price']) ? $this->request->post['price'] : '';
        
        if (empty($query)) {
            $json = array(
                'error' => 'Поисковый запрос не может быть пустым'
            );
        } else {
            try {
                $modules = $this->model_extension_module_gdt_update_server->searchModules($query, $category, $sort, $price);
                
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
            $modules = $this->getModulesFromExternalApi();
            
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
                'error' => 'Поисковый запрос не может быть пустым'
            );
        } else {
            try {
                $modules = $this->searchModulesInExternalApi($query, $category, $featured);
                
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
     * Получение модулей из внешнего API
     */
    private function getModulesFromExternalApi($page = 1, $limit = 20) {
        // URL внешнего API сервера модулей
        $api_url = $this->config->get('module_gdt_updater_server');
        if (empty($api_url)) {
            $api_url = 'https://your-modules-server.com'; // Замените на реальный URL
        }
        
        $api_key = $this->config->get('module_gdt_updater_api_key');
        
        $url = rtrim($api_url, '/') . '/index.php?route=gdt_update_server/modules';
        $url .= '&page=' . $page . '&limit=' . $limit;
        
        $post_data = array();
        if (!empty($api_key)) {
            $post_data['api_key'] = $api_key;
        }
        
        $response = $this->makeApiRequest($url, $post_data);
        
        if (isset($response['success']) && $response['success']) {
            return $response['modules'];
        } else {
            throw new Exception('Ошибка получения модулей: ' . (isset($response['error']) ? $response['error'] : 'Неизвестная ошибка'));
        }
    }
    
    /**
     * Поиск модулей во внешнем API
     */
    private function searchModulesInExternalApi($query, $category = '', $featured = 0) {
        $api_url = $this->config->get('module_gdt_updater_server');
        if (empty($api_url)) {
            $api_url = 'https://your-modules-server.com'; // Замените на реальный URL
        }
        
        $api_key = $this->config->get('module_gdt_updater_api_key');
        
        $url = rtrim($api_url, '/') . '/index.php?route=gdt_update_server/modules';
        $url .= '&search=' . urlencode($query);
        
        if (!empty($category)) {
            $url .= '&category=' . urlencode($category);
        }
        
        if ($featured) {
            $url .= '&featured=1';
        }
        
        $post_data = array();
        if (!empty($api_key)) {
            $post_data['api_key'] = $api_key;
        }
        
        $response = $this->makeApiRequest($url, $post_data);
        
        if (isset($response['success']) && $response['success']) {
            return $response['modules'];
        } else {
            throw new Exception('Ошибка поиска модулей: ' . (isset($response['error']) ? $response['error'] : 'Неизвестная ошибка'));
        }
    }
    
    /**
     * Выполнение API запроса
     */
    private function makeApiRequest($url, $post_data = array()) {
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'GDT-ModuleManager/1.0',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            )
        ));
        
        if (!empty($post_data)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL ошибка: ' . $error);
        }
        
        if ($http_code !== 200) {
            throw new Exception('HTTP ошибка: ' . $http_code);
        }
        
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new Exception('Некорректный JSON ответ от сервера');
        }
        
        return $decoded;
    }
    
    /**
     * Установка модуля
     */
    public function installModule() {
        $this->load->library('gbitstudio/modules/manager');
        $manager = new \Gbitstudio\Modules\Manager($this->registry);
        
        $module_code = isset($this->request->post['module_code']) ? $this->request->post['module_code'] : '';
        $download_url = isset($this->request->post['download_url']) ? $this->request->post['download_url'] : '';
        
        if (empty($module_code) || empty($download_url)) {
            $json = array(
                'error' => 'Недостаточно данных для установки модуля'
            );
        } else {
            try {
                $result = $manager->installModuleURL($module_code, $download_url);
                if ($result) {
                    $json = array(
                        'success' => 'Модуль "' . $module_code . '" успешно установлен'
                    );
                }
            } catch (Exception $e) {
                $json = array(
                    'error' => $e->getMessage()
                );
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
