<?php
namespace Gbitstudio\Modules\Traits;

use Gbitstudio\Modules\Services\LoggerService;

/**
 * Трейт для виконання HTTP запитів через cURL
 */
trait HttpClientTrait {
    /**
     * Виконує POST/GET запит до серверного API
     * 
     * @param string $url
     * @param array $data
     * @param string $method POST|GET
     * @return array ['success' => bool, 'data' => mixed, 'error' => string]
     */
    protected function executeApiRequest(string $url, array $data = [], string $method = 'POST'): array {
        $curl = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'GbitStudio GDT Module Agent'
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
        } else {
            $options[CURLOPT_URL] = $url . (strpos($url, '?') !== false ? '&' : '?') . http_build_query($data);
        }

        curl_setopt_array($curl, $options);
        
        $response = curl_exec($curl);
        $error_code = curl_errno($curl);
        $error_msg = curl_error($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);

        if ($error_code !== CURLE_OK) {
            LoggerService::error("CURL API ERROR ($error_code): $error_msg | URL: $url", 'HttpClientTrait');
            return ['success' => false, 'error' => "Connection error: $error_msg"];
        }

        if ($http_status >= 400) {
            LoggerService::error("CURL API HTTP ERROR: $http_status | Response: $response | URL: $url", 'HttpClientTrait');
            return ['success' => false, 'error' => "Server error: $http_status"];
        }

        $decoded_response = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            LoggerService::error("JSON DECODE ERROR in API response: " . json_last_error_msg() . " | URL: $url", 'HttpClientTrait');
            return ['success' => false, 'error' => 'Invalid server response format'];
        }

        return [
            'success' => true,
            'data' => $decoded_response
        ];
    }
}
