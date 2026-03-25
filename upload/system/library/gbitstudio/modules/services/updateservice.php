<?php

namespace Gbitstudio\Modules\Services;

use Gbitstudio\Modules\Traits\HttpClientTrait;
use Gbitstudio\Modules\Traits\ServerUrlTrait;

/**
 * Сервіс для перевірки та завантаження оновлень
 */
class UpdateService extends BaseService {
    use HttpClientTrait, ServerUrlTrait;
    
    public function __construct(\Registry $registry) {
        parent::__construct($registry);
    }
    
    /**
     * Перевіряє наявність оновлень для модуля
     * 
     * @param array $module Інформація про модуль
     * @param string $api_key Ключ API
     * @return array
     */
    public function checkModuleUpdate($module, $api_key = '') {
        $this->logInfo('Checking update for ' . $module['code']);
        
        $url = $this->getServerUrl() . 'index.php?route=gdt_update_server/check';
        $post_data = [
            'code' => $module['code'],
            'version' => $module['version']
        ];
        
        if (!empty($api_key)) {
            $post_data['api_key'] = $api_key;
        }
        
        $response = $this->executeApiRequest($url, $post_data);
        
        if (!$response['success']) {
            return $response;
        }
        
        $parse_result = $this->parseUpdateResponse($response['data'], $module);
        
        return $this->success($parse_result);
    }
    
    /**
     * Завантажує модуль з сервера
     * 
     * @param string $module_code Код модуля
     * @param string $version Версія модуля
     * @param string $api_key Ключ API
     * @return array ['success' => bool, 'file_path' => string|null, 'error' => string|null]
     */
    public function downloadModule($module_code, $version, $api_key = '') {
        try {
            $download_url = $this->getServerUrl() . 'index.php?route=gdt_update_server/download';
            
            $post_data = [
                'code' => $module_code,
                'version' => $version
            ];
            
            if (!empty($api_key)) {
                $post_data['api_key'] = $api_key;
            }
            
            $temp_file = $this->downloadFile($download_url, $post_data);
            
            if ($temp_file) {
                $this->logInfo('Downloaded module to ' . $temp_file);
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
            $this->logError('Download error: ' . $e->getMessage());
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
    private function downloadFile($download_url, $post_data) {
        $curl = curl_init($download_url);
        
        $temp_file = tempnam(sys_get_temp_dir(), 'gdt_');
        $fp = fopen($temp_file, 'w');
        
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($curl);
        $error = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);
        fclose($fp);
        
        if (!$result || $http_code >= 400) {
            $this->logError('File download error: ' . $error . ' | HTTP Code: ' . $http_code);
            @unlink($temp_file);
            return false;
        }
        
        return $temp_file;
    }
    
    /**
     * Парсить відповідь сервера про оновлення
     * 
     * @param array $response_data Дані від сервера
     * @param array $module Поточний модуль
     * @return array|bool
     */
    private function parseUpdateResponse($response_data, $module) {
        if (!isset($response_data['version'])) {
            return false;
        }
        
        if (version_compare($response_data['version'], $module['version'], '>')) {
            return [
                'code' => $module['code'],
                'current_version' => $module['version'],
                'new_version' => $response_data['version'],
                'download_url' => isset($response_data['download_url']) ? $response_data['download_url'] : '',
                'changelog' => isset($response_data['changelog']) ? $response_data['changelog'] : '',
                'date' => isset($response_data['date']) ? $response_data['date'] : ''
            ];
        }
        
        return false;
    }
}
