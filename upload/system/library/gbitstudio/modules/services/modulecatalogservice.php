<?php

namespace Gbitstudio\Modules\Services;

use Gbitstudio\Modules\Traits\HttpClientTrait;
use Gbitstudio\Modules\Traits\ServerUrlTrait;

/**
 * Сервіс для роботи з каталогом модулів
 * Відповідає за отримання модулів з зовнішнього сервера та їх встановлення
 */
class ModuleCatalogService extends BaseService {
    use HttpClientTrait, ServerUrlTrait;
    
    public function __construct(\Registry $registry) {
        parent::__construct($registry);
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
            return $this->error('Search query cannot be empty');
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
        try {
            $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
            
            $url = $this->getServerUrl() . 'index.php?route=gdt_update_server/modules';
            $url .= '&page=' . $page . '&limit=' . $limit;
            
            $post_data = [];
            if (!empty($api_key)) {
                $post_data['api_key'] = $api_key;
            }
            
            $result = $this->executeApiRequest($url, $post_data);
            
            if ($result['success'] && isset($result['data']['success']) && $result['data']['success']) {
                return $this->success($result['data']['modules']);
            } else {
                $error = $result['error'] ?? $result['data']['error'] ?? 'Unknown error';
                return $this->error('Error getting modules: ' . $error);
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Отримує модулі з сервера за параметрами (внутрішній метод)
     */
    private function getModulesFromServer($params) {
        try {
            $api_key = $this->config->get('module_gdt_updater_api_key') ?: '';
            
            $url = $this->getServerUrl() . 'index.php?route=gdt_update_server/modules';
            
            $post_data = $params;
            if (!empty($api_key)) {
                $post_data['api_key'] = $api_key;
            }
            
            $result = $this->executeApiRequest($url, $post_data);
            
            if ($result['success'] && isset($result['data']['success']) && $result['data']['success']) {
                return $this->success($result['data']['modules']);
            } else {
                return $this->error('Error getting modules from server');
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
