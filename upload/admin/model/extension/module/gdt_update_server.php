<?php
class ModelExtensionModuleGdtUpdateServer extends Model {
    
    /**
     * Получение рекомендуемых модулей
     */
    public function getFeaturedModules() {
        // Заглушка - возвращаем тестовые данные
        return array(
            array(
                'code' => 'test_module_1',
                'name' => 'Тестовый модуль 1',
                'description' => 'Описание тестового модуля 1',
                'version' => '1.0.0',
                'author' => 'GDT Studio',
                'price' => 0,
                'downloads' => 1500,
                'rating' => 4.5,
                'rating_count' => 25,
                'is_installed' => false,
                'featured' => true,
                'download_url' => 'https://example.com/modules/test_module_1.zip',
                'demo_url' => 'https://demo.example.com/test_module_1',
                'info_url' => 'https://info.example.com/test_module_1'
            ),
            array(
                'code' => 'test_module_2',
                'name' => 'Тестовый модуль 2',
                'description' => 'Описание тестового модуля 2',
                'version' => '2.1.0',
                'author' => 'GDT Studio',
                'price' => 29.99,
                'downloads' => 850,
                'rating' => 4.8,
                'rating_count' => 12,
                'is_installed' => true,
                'featured' => true,
                'download_url' => 'https://example.com/modules/test_module_2.zip',
                'demo_url' => 'https://demo.example.com/test_module_2',
                'info_url' => 'https://info.example.com/test_module_2'
            ),
            array(
                'code' => 'test_module_3',
                'name' => 'Тестовый модуль 3',
                'description' => 'Описание тестового модуля 3 с более длинным текстом для демонстрации отображения',
                'version' => '1.5.2',
                'author' => 'GDT Studio',
                'price' => 0,
                'downloads' => 3200,
                'rating' => 4.2,
                'rating_count' => 68,
                'is_installed' => false,
                'featured' => true,
                'download_url' => 'https://example.com/modules/test_module_3.zip',
                'info_url' => 'https://info.example.com/test_module_3'
            )
        );
    }
    
    /**
     * Получение популярных модулей
     */
    public function getPopularModules() {
        return array(
            array(
                'code' => 'popular_module_1',
                'name' => 'Популярный модуль 1',
                'description' => 'Самый популярный модуль среди пользователей',
                'version' => '3.0.1',
                'author' => 'GDT Studio',
                'price' => 0,
                'downloads' => 15000,
                'rating' => 4.9,
                'rating_count' => 450,
                'is_installed' => false,
                'featured' => false,
                'download_url' => 'https://example.com/modules/popular_module_1.zip',
                'demo_url' => 'https://demo.example.com/popular_module_1'
            ),
            array(
                'code' => 'popular_module_2',
                'name' => 'Популярный модуль 2',
                'description' => 'Второй по популярности модуль',
                'version' => '2.8.0',
                'author' => 'GDT Studio',
                'price' => 49.99,
                'downloads' => 12500,
                'rating' => 4.7,
                'rating_count' => 320,
                'is_installed' => false,
                'featured' => false,
                'download_url' => 'https://example.com/modules/popular_module_2.zip',
                'demo_url' => 'https://demo.example.com/popular_module_2',
                'info_url' => 'https://info.example.com/popular_module_2'
            )
        );
    }
    
    /**
     * Получение новых модулей
     */
    public function getNewestModules() {
        return array(
            array(
                'code' => 'new_module_1',
                'name' => 'Новый модуль 1',
                'description' => 'Только что добавленный модуль',
                'version' => '1.0.0',
                'author' => 'GDT Studio',
                'price' => 0,
                'downloads' => 150,
                'rating' => 5.0,
                'rating_count' => 3,
                'is_installed' => false,
                'featured' => false,
                'download_url' => 'https://example.com/modules/new_module_1.zip',
                'demo_url' => 'https://demo.example.com/new_module_1'
            ),
            array(
                'code' => 'new_module_2',
                'name' => 'Новый модуль 2',
                'description' => 'Свежий модуль с инновационным функционалом',
                'version' => '1.1.0',
                'author' => 'GDT Studio',
                'price' => 19.99,
                'downloads' => 85,
                'rating' => 4.6,
                'rating_count' => 8,
                'is_installed' => false,
                'featured' => false,
                'download_url' => 'https://example.com/modules/new_module_2.zip',
                'info_url' => 'https://info.example.com/new_module_2'
            )
        );
    }
    
    /**
     * Поиск модулей
     */
    public function searchModules($query, $category = '', $sort = '', $price = '') {
        // Заглушка для поиска - возвращаем результаты в зависимости от запроса
        $all_modules = array_merge(
            $this->getFeaturedModules(),
            $this->getPopularModules(),
            $this->getNewestModules()
        );
        
        $results = array();
        
        foreach ($all_modules as $module) {
            // Простой поиск по названию и описанию
            if (stripos($module['name'], $query) !== false || 
                stripos($module['description'], $query) !== false) {
                $results[] = $module;
            }
        }
        
        // Фильтрация по цене
        if ($price === 'free') {
            $results = array_filter($results, function($module) {
                return $module['price'] == 0;
            });
        } elseif ($price === 'paid') {
            $results = array_filter($results, function($module) {
                return $module['price'] > 0;
            });
        }
        
        // Сортировка
        if ($sort === 'name') {
            usort($results, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        } elseif ($sort === 'downloads') {
            usort($results, function($a, $b) {
                return $b['downloads'] - $a['downloads'];
            });
        } elseif ($sort === 'rating') {
            usort($results, function($a, $b) {
                return $b['rating'] <=> $a['rating'];
            });
        } elseif ($sort === 'price') {
            usort($results, function($a, $b) {
                return $a['price'] <=> $b['price'];
            });
        }
        
        return $results;
    }
    
    /**
     * Получение информации о модуле
     */
    public function getModuleInfo($module_code) {
        $all_modules = array_merge(
            $this->getFeaturedModules(),
            $this->getPopularModules(),
            $this->getNewestModules()
        );
        
        foreach ($all_modules as $module) {
            if ($module['code'] === $module_code) {
                return $module;
            }
        }
        
        return null;
    }
    
    /**
     * Проверка доступности сервера
     */
    public function checkServerConnection() {
        // Заглушка - всегда возвращаем true
        return true;
    }
    
    /**
     * Получение статистики сервера
     */
    public function getServerStats() {
        return array(
            'total_modules' => 150,
            'featured_modules' => 25,
            'free_modules' => 95,
            'paid_modules' => 55,
            'last_updated' => date('Y-m-d H:i:s')
        );
    }
}
