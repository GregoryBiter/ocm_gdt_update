<?php

namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для роботи з каталогом модулів
 * Відповідає за отримання модулів з зовнішнього сервера та їх встановлення
 */
class ModuleCatalogService {
    private $config;
    private $log;
    
    public function __construct($config, $log) {
        $this->config = $config;
        $this->log = $log;
    }
    
    /**
     * Отримує рекомендовані модулі з сервера
     * 
     * @return array
     */
    public function getFeaturedModules() {
        return $this->getModulesFromServer(['featured' => 1]);
    }
    
    /**
     * Отримує популярні модулі з сервера
     * 
     * @return array
     */
    public function getPopularModules() {
        return $this->getModulesFromServer(['popular' => 1]);
    }
    
    /**
     * Отримує найновіші модулі з сервера
     * 
     * @return array
     */
    public function getNewestModules() {
        return $this->getModulesFromServer(['newest' => 1]);
    }
    
    /**
     * Шукає модулі за параметрами
     * 
     * @param string $query
     * @param string $category
     * @param string $sort
     * @param string $price
     * @return array
     */
    public function searchModules($query, $category = '', $sort = 'relevance', $price = '') {
        if (empty($query)) {
            throw new \Exception('Search query cannot be empty');
        }
        
        $params = [
            'search' => $query,
            'category' => $category,
            'sort' => $sort,
            'price' => $price
        ];
        
        return $this->getModulesFromServer($params);
    }
    
    /**
     * Отримує всі модулі з магазину
     * 
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getStoreModules($page = 1, $limit = 20) {
        $server_url = $this->getServerUrl();
        if (empty($server_url)) {
            throw new \Exception('Server URL not configured');
        }
        
        $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
        
        $url = rtrim($server_url, '/') . '/index.php?route=gdt_update_server/modules';
        $url .= '&page=' . $page . '&limit=' . $limit;
        
        $post_data = [];
        if (!empty($api_key)) {
            $post_data['api_key'] = $api_key;
        }
        
        $response = $this->makeApiRequest($url, $post_data);
        
        if (isset($response['success']) && $response['success']) {
            return $response['modules'];
        } else {
            throw new \Exception('Error getting modules: ' . (isset($response['error']) ? $response['error'] : 'Unknown error'));
        }
    }
    
    /**
     * Отримує модулі з сервера за параметрами
     * 
     * @param array $params
     * @return array
     */
    private function getModulesFromServer($params = []) {
        $server_url = $this->getServerUrl();
        if (empty($server_url)) {
            throw new \Exception('Server URL not configured');
        }
        
        $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
        
        $url = rtrim($server_url, '/') . '/index.php?route=gdt_update_server/modules';
        
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                if (!empty($value)) {
                    $url .= '&' . $key . '=' . urlencode($value);
                }
            }
        }
        
        $post_data = [];
        if (!empty($api_key)) {
            $post_data['api_key'] = $api_key;
        }
        
        $response = $this->makeApiRequest($url, $post_data);
        
        if (isset($response['success']) && $response['success']) {
            return $response['modules'];
        } else {
            throw new \Exception('Error getting modules: ' . (isset($response['error']) ? $response['error'] : 'Unknown error'));
        }
    }
    
    /**
     * Помічає встановлені модулі в списку
     * 
     * @param array $modules
     * @param array $installed_modules
     * @return array
     */
    public function markInstalledModules($modules, $installed_modules) {
        $installed_map = [];
        foreach ($installed_modules as $installed) {
            if (isset($installed['code'])) {
                $installed_map[$installed['code']] = [
                    'version' => isset($installed['version']) ? $installed['version'] : '0.0.0',
                    'name' => isset($installed['name']) ? $installed['name'] : $installed['code']
                ];
            }
        }
        
        foreach ($modules as &$module) {
            if (isset($module['code']) && isset($installed_map[$module['code']])) {
                $module['installed'] = true;
                $module['installed_version'] = $installed_map[$module['code']]['version'];
                
                if (isset($module['version'])) {
                    $module['update_available'] = version_compare($module['version'], $installed_map[$module['code']]['version'], '>');
                } else {
                    $module['update_available'] = false;
                }
            } else {
                $module['installed'] = false;
                $module['installed_version'] = null;
                $module['update_available'] = false;
            }
        }
        
        return $modules;
    }
    
    /**
     * Виконує API запит
     * 
     * @param string $url
     * @param array $post_data
     * @return array
     */
    private function makeApiRequest($url, $post_data = []) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'GDT-ModuleManager/1.0',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]
        ]);
        
        if (!empty($post_data)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }
        
        if ($http_code !== 200) {
            throw new \Exception('HTTP error: ' . $http_code);
        }
        
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new \Exception('Invalid JSON response from server');
        }
        
        return $decoded;
    }
    
    /**
     * Отримує URL сервера
     * 
     * @return string
     */
    private function getServerUrl() {
        $server_url = $this->config->get('module_gdt_updater_server');
        
        if (empty($server_url)) {
            if (defined('HTTP_SERVER')) {
                return HTTP_SERVER . 'ocm_gdt_update_server';
            }
        }
        
        return $server_url ?: '';
    }
}
