<?php
/**
 * GDT Update Client - Пример интеграции с сервером обновлений
 * 
 * Этот файл демонстрирует, как клиентские сайты могут интегрироваться
 * с сервером обновлений для проверки и получения обновлений модулей.
 */

class GdtUpdateClient {
    
    private $server_url;
    private $api_key;
    private $timeout;
    
    public function __construct($server_url, $api_key, $timeout = 30) {
        $this->server_url = rtrim($server_url, '/');
        $this->api_key = $api_key;
        $this->timeout = $timeout;
    }
    
    /**
     * Получить список всех доступных модулей на сервере
     */
    public function getAvailableModules() {
        $url = $this->server_url . '/index.php?route=gdt_update_server/modules';
        
        $data = array(
            'api_key' => $this->api_key
        );
        
        $response = $this->makeRequest($url, $data);
        
        if ($response && isset($response['success']) && $response['success']) {
            return $response['modules'];
        }
        
        return false;
    }
    
    /**
     * Проверить наличие обновлений для модуля
     */
    public function checkUpdate($module_code, $current_version) {
        $url = $this->server_url . '/index.php?route=gdt_update_server/check';
        
        $data = array(
            'api_key' => $this->api_key,
            'module_code' => $module_code,
            'current_version' => $current_version
        );
        
        $response = $this->makeRequest($url, $data);
        
        if ($response && isset($response['success']) && $response['success']) {
            return $response;
        }
        
        return false;
    }
    
    /**
     * Скачать обновление модуля
     */
    public function downloadUpdate($module_code, $version = null) {
        $url = $this->server_url . '/index.php?route=gdt_update_server/download';
        
        $data = array(
            'api_key' => $this->api_key,
            'module_code' => $module_code
        );
        
        if ($version) {
            $data['version'] = $version;
        }
        
        return $this->downloadFile($url, $data);
    }
    
    /**
     * Выполнить HTTP запрос
     */
    private function makeRequest($url, $data) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'GDT Update Client 1.0');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Скачать файл
     */
    private function downloadFile($url, $data) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'GDT Update Client 1.0');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            if (strpos($content_type, 'application/zip') !== false || 
                strpos($content_type, 'application/octet-stream') !== false) {
                return $response; // Возвращаем бинарные данные архива
            } else {
                // Возможно, это JSON с ошибкой
                $json = json_decode($response, true);
                if ($json && isset($json['error'])) {
                    return false;
                }
            }
        }
        
        return false;
    }
}

// Пример использования:
/*
$client = new GdtUpdateClient('https://your-update-server.com', 'your-api-key');

// Получить список всех модулей
$modules = $client->getAvailableModules();
if ($modules) {
    foreach ($modules as $module) {
        echo "Модуль: {$module['name']} ({$module['code']}) v{$module['version']}\n";
    }
} else {
    echo "Ошибка: не удалось получить список модулей\n";
}

// Проверить обновление для конкретного модуля
$update_info = $client->checkUpdate('gdt_updater', '1.0.0');
if ($update_info && $update_info['status'] == 'update_available') {
    echo "Доступно обновление до версии {$update_info['version']}\n";
    
    // Скачать обновление
    $archive = $client->downloadUpdate('gdt_updater');
    if ($archive) {
        file_put_contents('gdt_updater_update.zip', $archive);
        echo "Обновление скачано в gdt_updater_update.zip\n";
    } else {
        echo "Ошибка: не удалось скачать обновление\n";
    }
} elseif ($update_info && $update_info['status'] == 'up_to_date') {
    echo "Модуль уже обновлен до последней версии\n";
} else {
    echo "Ошибка: не удалось проверить обновление\n";
}
*/
