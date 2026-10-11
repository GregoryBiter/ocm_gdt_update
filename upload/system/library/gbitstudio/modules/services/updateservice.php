<?php

namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для перевірки та завантаження оновлень
 */
class UpdateService
{
    private $registry;

    public function __construct(\Registry $registry)
    {
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
    public function checkModuleUpdate($server_url, $module, $api_key = '')
    {
        LoggerService::info('Checking update for ' . $module['code'], 'UpdateService');

        $url = (new ServerEndpointService($this->registry->get('config')))->build($server_url, 'check');
        $post_data = [
            'code' => $module['code'],
            'version' => ltrim($module['version'], 'v')
        ];

        $response_data = $this->executeApiRequest($url, $post_data, 10, $api_key);

        if (isset($response_data['error'])) {
            return $response_data;
        }

        return $this->parseUpdateResponse($response_data, $module);
    }

    /**
     * Пакетна перевірка наявності оновлень для списку модулів (як у WordPress)
     *
     * @param string $server_url
     * @param array $modules
     * @param string $api_key
     * @param bool $force_refresh
     * @return array [module_code => update_info|false]
     */
    public function checkBatchUpdates($server_url, array $modules, $api_key = '', $force_refresh = false)
    {
        if (empty($modules) || empty($server_url)) {
            return [];
        }

        $cache = $this->registry->get('cache');
        $cache_key = 'gdt_updates_batch.' . hash('sha256', json_encode([$server_url,
            $this->registry->get('config')->get('module_gdt_updater_server_type') ?: 'laravel', $api_key, $modules]));

        if (!$force_refresh && $cache) {
            $cached = $cache->get($cache_key);
            if (is_array($cached)) {
                LoggerService::info('Loaded batch updates from cache', 'UpdateService');
                return $cached;
            }
        }

        LoggerService::info('Performing batch update check for ' . count($modules) . ' modules', 'UpdateService');

        $url = (new ServerEndpointService($this->registry->get('config')))->build($server_url, 'check-batch');
        $post_modules = [];
        foreach ($modules as $m) {
            if (!empty($m['code'])) {
                $post_modules[] = [
                    'code' => $m['code'],
                    'version' => ltrim($m['version'] ?? '0.0.0', 'v'),
                ];
            }
        }

        $response_data = $this->executeApiRequest($url, ['modules' => $post_modules], 15, $api_key);

        $results = [];

        if (isset($response_data['response'])) {
            $json = json_decode($response_data['response'], true);
            if (!empty($json['success']) && isset($json['results']) && is_array($json['results'])) {
                foreach ($json['results'] as $code => $info) {
                    if (isset($info['status']) && $info['status'] === 'update_available') {
                        $results[$code] = $info;
                    } else {
                        $results[$code] = false;
                    }
                }
            }
        }

        // Fallback: if server doesn't support batch, run individual checks
        if (empty($results) && isset($response_data['error'])) {
            LoggerService::warning('Batch check unsupported or failed, falling back to individual checks', 'UpdateService');
            foreach ($modules as $m) {
                $results[$m['code']] = $this->checkModuleUpdate($server_url, $m, $api_key);
            }
        }

        if ($cache && !empty($results)) {
            $cache->set($cache_key, $results);
        }

        return $results;
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
    public function downloadModule($server_url, $module_code, $version, $api_key = '')
    {
        try {
            $download_url = (new ServerEndpointService($this->registry->get('config')))->build($server_url, 'download', $module_code);

            $post_data = [
                'version' => $version,
                'current_version' => $version
            ];

            $download = $this->downloadFile($download_url, $post_data, $api_key);
            $temp_file = $download['file_path'];

            if ($temp_file) {
                LoggerService::info('Downloaded module to ' . $temp_file, 'UpdateService');
                return [
                    'success' => true,
                    'file_path' => $temp_file,
                    'version' => $this->downloadedVersion($download['disposition'], $module_code),
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
     * @return array Downloaded file path and response Content-Disposition; throws on failure
     */
    /**
     * Завантажує файл з сервера (внутрішній метод)
     *
     * @param string $download_url URL для завантаження
     * @param array $post_data POST дані
     * @return array Downloaded file path and response Content-Disposition; throws on failure
     */
    private function downloadedVersion($disposition, $code)
    {
        if (!preg_match('/filename="?([^";]+)"?/i', $disposition, $match)) {
            return '';
        }
        $filename = basename(trim($match[1]));
        $pattern = '/^' . preg_quote($code, '/') . '_(v?\d+(?:\.\d+)*(?:[-+][0-9A-Za-z.-]+)?)\.zip$/i';
        return preg_match($pattern, $filename, $match) ? ltrim($match[1], 'vV') : '';
    }

    private function downloadFile($download_url, $post_data, $api_key = '')
    {
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

        $disposition = '';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $download_url,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$disposition) {
                if (stripos($header, 'HTTP/') === 0) {
                    $disposition = '';
                } elseif (stripos($header, 'Content-Disposition:') === 0) {
                    $disposition = trim(substr($header, strlen('Content-Disposition:')));
                }
                return strlen($header);
            },
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
            return ['file_path' => $temp_file, 'disposition' => $disposition];
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
    private function executeApiRequest($url, $post_data, $timeout = 10, $api_key = '')
    {
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
    private function parseUpdateResponse($response_data, $module)
    {
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
