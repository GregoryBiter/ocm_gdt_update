<?php
class ControllerExtensionModuleGdtInstallModules extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/gdt_install_modules');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
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
            'href' => $this->url->link('extension/module/gdt_install_modules', 'user_token=' . $this->session->data['user_token'], true)
        );

        // URL для возврата
        $data['back'] = $this->url->link('extension/module/gdt_updater', 'user_token=' . $this->session->data['user_token'], true);
        
        // AJAX URLs
        $data['get_featured_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/getFeaturedModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['get_popular_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/getPopularModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['get_newest_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/getNewestModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['search_modules_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/searchModules', 'user_token=' . $this->session->data['user_token'], true));
        $data['install_module_url'] = html_entity_decode($this->url->link('extension/module/gdt_install_modules/installModule', 'user_token=' . $this->session->data['user_token'], true));

        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/gdt_install_modules', $data));
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
