<?php

namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для перевірки та завантаження оновлень
 */
class UpdateService {
    private $registry;
    
    public function __construct(\Registry $registry) {
        $this->registry = $registry;
    }
    
    /**
     * Перевіряє наявність оновлень для модуля
     * 
     * @param string $server_url URL сервера оновлень
     * @param array $module Інформація про модуль
     * @param string $api_key Ключ API
     * @return array|bool
     */
    public function checkModuleUpdate($server_url, $module, $api_key = '') {
        LoggerService::info('Checking update for ' . $module['code'], 'UpdateService');
        
        $url = rtrim($server_url, '/') . '/api/v1/modules/check';
        $post_data = [
            'code' => $module['code'],
            'version' => $module['version']
        ];
        
        $response_data = $this->executeApiRequest($url, $post_data, 10, $api_key);
        
        if (isset($response_data['error'])) {
            return $response_data;
        }
        
        return $this->parseUpdateResponse($response_data, $module);
    }
    
    /**
     * Завантажує модуль з сервера
     * 
     * @param string $server_url URL сервера
     * @param string $module_code Код модуля
     * @param string $version Версія модуля
     * @param string $api_key Ключ API
     * @return array ['success' => bool, 'file_path' => string|null, 'error' => string|null]
     */
    public function downloadModule($server_url, $module_code, $version, $api_key = '') {
        try {
            $download_url = rtrim($server_url, '/') . '/api/v1/modules/' . $module_code . '/download';
            
            $post_data = [
                'current_version' => $version
            ];
            
            $temp_file = $this->downloadFile($download_url, $post_data, $api_key);
            
            if ($temp_file) {
                LoggerService::info('Downloaded module to ' . $temp_file, 'UpdateService');
                return [
                    'success' => true,
                    'file_path' => $temp_file,
                    'error' => null
                ];
            } else {
                return [
                    'success' => false,
                    'file_path' => null,
                    'error' => 'Failed to download module'
                ];
            }
        } catch (\Exception $e) {
            LoggerService::error('Download error: ' . $e->getMessage(), 'UpdateService');
            return [
                'success' => false,
                'file_path' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Завантажує файл з сервера (внутрішній метод)
     * 
     * @param string $download_url URL для завантаження
     * @param array $post_data POST дані
     * @return string|false Шлях до тимчасового файлу або false при помилці
     */
    /**
     * Завантажує файл з сервера (внутрішній метод)
     * 
     * @param string $download_url URL для завантаження
     * @param array $post_data POST дані
     * @return string|false Шлях до тимчасового файлу або false при помилці
     */
    private function downloadFile($download_url, $post_data, $api_key = '') {
        $temp_file = tempnam(sys_get_temp_dir(), 'gdt_module_');
        
        $headers = [
            'User-Agent: GDT-ModuleManager/1.0'
        ];
        
        if (!empty($api_key)) {
            $headers[] = 'X-API-Key: ' . $api_key;
        }
        
        $is_logging = $this->registry->get('config')->get('module_gdt_updater_api_log');
        
        if ($is_logging) {
            $masked_key = !empty($api_key) ? substr($api_key, 0, 4) . '...' . substr($api_key, -4) : 'none';
            LoggerService::debug("File Download Request: " . $download_url, 'UpdateService');
            LoggerService::debug("File Download Headers: X-API-Key: " . $masked_key, 'UpdateService');
            LoggerService::debug("File Download Payload: " . json_encode($post_data), 'UpdateService');
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $download_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FAILONERROR => false
        ]);
        
        if (!empty($post_data)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        }

        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if ($is_logging) {
            LoggerService::debug("File Download Response Code: " . $http_code, 'UpdateService');
            if ($error) {
                LoggerService::debug("File Download Error: " . $error, 'UpdateService');
            }
        }
        
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }

        if ($http_code === 200 && $data !== false) {
            $decoded = json_decode($data, true);
            if ($decoded !== null && isset($decoded['error'])) {
                throw new \Exception('Server error: ' . $decoded['error']);
            }
            
            file_put_contents($temp_file, $data);
            return $temp_file;
        } else {
            throw new \Exception('HTTP error: ' . $http_code);
        }
    }
    
    /**
     * Виконує API запит до сервера
     * 
     * @param string $url
     * @param array $post_data
     * @param int $timeout
     * @return array
     */
    private function executeApiRequest($url, $post_data, $timeout = 10, $api_key = '') {
        $is_logging = $this->registry->get('config')->get('module_gdt_updater_api_log');
        
        if ($is_logging) {
            $masked_key = !empty($api_key) ? substr($api_key, 0, 4) . '...' . substr($api_key, -4) : 'none';
            LoggerService::debug("API Request: POST " . $url, 'UpdateService');
            LoggerService::debug("API Headers: Content-Type: application/x-www-form-urlencoded, X-API-Key: " . $masked_key, 'UpdateService');
            LoggerService::debug("API Payload: " . json_encode($post_data), 'UpdateService');
        }

        $curl = curl_init();
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: GDT-ModuleManager/1.0',
            'Accept: application/json'
        ];
        
        if (!empty($api_key)) {
            $headers[] = 'X-API-Key: ' . $api_key;
        }
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FAILONERROR => false
        ]);
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        
        if ($is_logging) {
            LoggerService::debug("API Response Code: " . $http_code, 'UpdateService');
            LoggerService::debug("API Response Body: " . (strlen($response) > 1000 ? substr($response, 0, 1000) . '...' : (string)$response), 'UpdateService');
        }
        
        curl_close($curl);
        
        if ($error) {
            LoggerService::error('cURL error: ' . $error, 'UpdateService');
            return ['error' => 'curl', 'message' => $error];
        }
        
        if ($http_code != 200) {
            LoggerService::error('HTTP error: ' . $http_code, 'UpdateService');
            return ['error' => 'http', 'code' => $http_code];
        }
        
        return [
            'response' => $response,
            'http_code' => $http_code,
            'content_type' => $content_type
        ];
    }
    
    /**
     * Парсить відповідь сервера про оновлення
     * 
     * @param array $response_data
     * @param array $module
     * @return array|bool
     */
    private function parseUpdateResponse($response_data, $module) {
        if (isset($response_data['response'])) {
            $data = json_decode($response_data['response'], true);
            
            if (isset($data['success']) && $data['success'] && isset($data['result'])) {
                $update_info = $data['result'];
                if (isset($update_info['status']) && $update_info['status'] == 'update_available') {
                    return $update_info;
                }
            }
        }
        
        LoggerService::info('No update available for module ' . $module['code'], 'UpdateService');
        return false;
    }
}
