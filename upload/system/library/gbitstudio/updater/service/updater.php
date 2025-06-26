<?php

namespace Gbitstudio\Updater\Service;

class Updater {
    private $registry;
    private $log;
    private $language;
    
    /**
     * Конструктор
     * 
     * @param object $registry
     */
    public function __construct($registry) {
        $this->registry = $registry;
        $this->log = $registry->get('log');
        $this->language = $registry->get('language');
    }
    
    /**
     * Получает список установленных модулей
     * 
     * @return array
     */
    public function getInstalledModules() {
        $modules = array();
        
        // Проверяем, определена ли константа DIR_SYSTEM
        if (!defined('DIR_SYSTEM')) {
            if ($this->log) {
                $this->log->write('GDT Updater error: DIR_SYSTEM constant is not defined');
            }
            return $modules;
        }
        
        // Ищем файлы в директории system/hook
        $hook_files = glob(DIR_SYSTEM . 'hook/*.php');
        
        if ($hook_files) {
            foreach ($hook_files as $file) {
                $content = file_get_contents($file);
                
                // Получаем код модуля из имени файла (без расширения .php)
                $filename = basename($file, '.php');
                
                // Извлекаем информацию о модуле из комментариев
                preg_match('/name:\s*([^\n]+)/i', $content, $name_match);
                preg_match('/description:\s*([^\n]+)/i', $content, $description_match);
                preg_match('/version:\s*([^\n]+)/i', $content, $version_match);
                preg_match('/author:\s*([^\n]+)/i', $content, $author_match);
                preg_match('/author_url:\s*([^\n]+)/i', $content, $author_url_match);
                
                if (isset($name_match[1]) && isset($version_match[1])) {
                    $modules[] = array(
                        'name' => trim($name_match[1]),
                        'description' => isset($description_match[1]) ? trim($description_match[1]) : '',
                        'code' => $filename, // Используем имя файла как код модуля
                        'version' => trim($version_match[1]),
                        'author' => isset($author_match[1]) ? trim($author_match[1]) : '',
                        'author_url' => isset($author_url_match[1]) ? trim($author_url_match[1]) : '',
                        'file_path' => $file
                    );
                }
            }
        }
        
        return $modules;
    }
    
    /**
     * Получает модуль по коду
     * 
     * @param string $code Код модуля
     * @return array|null
     */
    public function getModuleByCode($code) {
        $modules = $this->getInstalledModules();
        
        foreach ($modules as $module) {
            if ($module['code'] == $code) {
                return $module;
            }
        }
        
        return null;
    }
    
    /**
     * Проверяет наличие обновлений для модуля
     * 
     * @param string $server_url URL сервера обновлений
     * @param array $module Информация о модуле
     * @param string $client_id Идентификатор клиента
     * @param string $api_key Ключ API для аутентификации
     * @return array|bool
     */
    public function checkModuleUpdate($server_url, $module, $client_id = 'default', $api_key = '') {
        $url = rtrim($server_url, '/') . '/index.php?route=gdt_update_server/check';
        
        // Получаем API ключ из настроек, если не передан
        if (empty($api_key)) {
            $api_key = $this->registry->get('config')->get('module_gdt_updater_api_key');
        }
        
        // Логируем для отладки
        if (!empty($api_key)) {
            $this->log->write('GDT Updater Debug: API request for module ' . $module['code'] . ' with API key');
        }
        
        // Данные в соответствии с серверной авторизацией
        $post_data = array(
            'code' => $module['code'],
            'version' => $module['version']
        );
        
        // Добавляем API ключ только если он есть
        if (!empty($api_key)) {
            $post_data['api_key'] = $api_key;
        }
        
        // Инициализация cURL сессии
        $curl = curl_init();
        
        // Настройка параметров cURL
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: GDT-Updater/1.0'
            ),
            CURLOPT_TIMEOUT => 10, // Уменьшаем таймаут
            CURLOPT_CONNECTTIMEOUT => 5, // Добавляем таймаут на соединение
            CURLOPT_SSL_VERIFYPEER => false, // Для разработки, в продакшне лучше установить true
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FAILONERROR => false // Не считать HTTP ошибки за сбой curl
        ));
        
        // Выполнение запроса
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        // Закрытие cURL сессии
        curl_close($curl);
        
        if ($error) {
            // Логирование ошибки, если объект $log доступен
            $this->log->write('GDT Updater cURL error: ' . $error);
            // Возвращаем ошибку curl вместо false
            return array('error' => 'curl', 'message' => $error);
        }
        
        if ($http_code != 200) {
            // Логирование ошибки HTTP, если объект $log доступен
            $this->log->write('GDT Updater HTTP error: ' . $http_code);
            // Возвращаем ошибку HTTP вместо false только если код действительно ошибочный
            if ($http_code >= 400) {
                return array('error' => 'http', 'code' => $http_code);
            }
        }
        
        if ($response) {
            $update_info = json_decode($response, true);
            
            if (isset($update_info['status']) && $update_info['status'] == 'update_available') {
                return $update_info;
            }
        }
        
        return false;
    }
    
    /**
     * Скачивает и устанавливает обновление модуля
     * 
     * @param string $server_url URL сервера обновлений
     * @param array $module Информация о модуле
     * @param array $update_info Информация об обновлении
     * @param string $client_id Идентификатор клиента
     * @param string $api_key Ключ API для аутентификации
     * @return bool|string
     */
    public function downloadAndInstallUpdate($server_url, $module, $update_info, $client_id = 'default', $api_key = '') {
        try {
            // URL для загрузки обновления
            $download_url = rtrim($server_url, '/') . '/index.php?route=gdt_update_server/download';
            
            // Получаем API ключ из настроек, если не передан
            if (empty($api_key)) {
                $api_key = $this->registry->get('config')->get('module_gdt_updater_api_key');
            }
            
            // Логируем для отладки
            if (!empty($api_key)) {
                $this->log->write('GDT Updater Debug: Download request for module ' . $module['code'] . ' with API key');
            }
            
            // Данные в соответствии с серверной авторизацией
            $post_data = array(
                'code' => $module['code']
            );
            
            // Добавляем API ключ только если он есть
            if (!empty($api_key)) {
                $post_data['api_key'] = $api_key;
            }
            
            // Инициализация cURL сессии
            $curl = curl_init();
            
            // Настройка параметров cURL
            curl_setopt_array($curl, array(
                CURLOPT_URL => $download_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($post_data),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: GDT-Updater/1.0'
                ),
                CURLOPT_TIMEOUT => 60, // Увеличенный таймаут для загрузки файлов
                CURLOPT_CONNECTTIMEOUT => 10, // Таймаут на соединение
                CURLOPT_SSL_VERIFYPEER => false, // Для разработки, в продакшне лучше установить true
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FAILONERROR => false // Не считать HTTP ошибки за сбой curl
            ));
            
            // Выполнение запроса
            $response = curl_exec($curl);
            $error = curl_error($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            // Закрытие cURL сессии
            curl_close($curl);
            
            if ($error) {
                // Логирование ошибки
                $this->log->write('GDT Updater cURL error: ' . $error);
                return sprintf($this->language->get('error_curl'), $error);
            }
            
            if ($http_code != 200) {
                // Логирование ошибки HTTP
                $this->log->write('GDT Updater HTTP error: ' . $http_code);
                // Возвращаем ошибку HTTP только для кодов ошибок (4xx и 5xx)
                if ($http_code >= 400) {
                    return sprintf($this->language->get('error_http'), $http_code);
                }
            }
            
            if (empty($response)) {
                return $this->language->get('error_download');
            }
            
            // Проверяем, определена ли константа DIR_DOWNLOAD
            if (!defined('DIR_DOWNLOAD')) {
                // Используем временную директорию системы, если DIR_DOWNLOAD не определена
                $download_dir = sys_get_temp_dir() . '/opencart_download/';
                if (!is_dir($download_dir)) {
                    mkdir($download_dir, 0777, true);
                }
            } else {
                $download_dir = DIR_DOWNLOAD;
            }
            
            // Временный файл для загрузки
            $temp_file = $download_dir . 'update_' . $module['code'] . '_' . $update_info['version'] . '.zip';
            
            // Сохраняем во временный файл
            file_put_contents($temp_file, $response);
            
            // Проверяем, является ли файл действительным архивом ZIP
            $zip = new \ZipArchive();
            if ($zip->open($temp_file) !== true) {
                @unlink($temp_file);
                return $this->language->get('error_zip');
            }
            
            // Извлекаем архив
            $extract_dir = $download_dir . 'update_extract_' . $module['code'];
            
            // Очистим директорию, если она уже существует
            if (is_dir($extract_dir)) {
                $this->removeDirectory($extract_dir);
            }
            
            // Создаем директорию заново
            mkdir($extract_dir, 0777, true);
            
            $zip->extractTo($extract_dir);
            $zip->close();
            
            // Проверяем структуру архива и определяем исходную директорию
            $source_dir = $extract_dir;
            
            // Если архив имеет структуру OpenCart с папкой upload
            if (is_dir($extract_dir . '/upload')) {
                $source_dir = $extract_dir . '/upload';
            }
            
            // Проверяем, определена ли константа DIR_APPLICATION
            if (!defined('DIR_APPLICATION')) {
                if ($this->log) {
                    $this->log->write('GDT Updater error: DIR_APPLICATION constant is not defined');
                }
                return $this->language->get('error_constants');
            }
            
            // Применяем обновление - копируем файлы в нужные директории
            // получаем путь к директории OpenCart
            $opencart_dir = realpath(DIR_APPLICATION . '../');
            
            $this->copyDirectory($source_dir, $opencart_dir);
            
            // Обновляем версию в файле хука
            $hook_content = file_get_contents($module['file_path']);
            
            // Проверяем, не соответствует ли текущая версия новой версии
            preg_match('/version:\s*([^\n]+)/i', $hook_content, $current_version_match);
            $current_version = isset($current_version_match[1]) ? trim($current_version_match[1]) : '';
            
            // Обновляем только если версии отличаются
            if ($current_version !== $update_info['version']) {
                $new_hook_content = preg_replace('/version:\s*([^\n]+)/i', 'version: ' . $update_info['version'], $hook_content);
                file_put_contents($module['file_path'], $new_hook_content);
            }
            
            // Очищаем временные файлы
            $this->removeDirectory($extract_dir);
            @unlink($temp_file);
            
            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    
    /**
     * Рекурсивно сканирует директорию и вызывает callback-функцию для каждого файла
     * 
     * @param string $directory Директория для сканирования
     * @param callable $callback Функция обратного вызова
     */
    private function scanDirectory($directory, $callback) {
        $files = scandir($directory);
        
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $directory . '/' . $file;
                
                if (is_dir($path)) {
                    $this->scanDirectory($path, $callback);
                } else {
                    call_user_func($callback, $path);
                }
            }
        }
    }
    
    /**
     * Копирует директорию рекурсивно
     * 
     * @param string $source Исходная директория
     * @param string $destination Целевая директория
     */
    public function copyDirectory($source, $destination) {
        if (is_dir($source)) {
            if (!is_dir($destination)) {
                mkdir($destination, 0777, true);
            }
            
            $directory = opendir($source);
            
            while (($file = readdir($directory)) !== false) {
                if ($file != '.' && $file != '..') {
                    $this->copyDirectory($source . '/' . $file, $destination . '/' . $file);
                }
            }
            
            closedir($directory);
        } elseif (file_exists($source)) {
            copy($source, $destination);
        }
    }
    
    /**
     * Удаляет директорию рекурсивно
     * 
     * @param string $directory Директория для удаления
     */
    public function removeDirectory($directory) {
        if (is_dir($directory)) {
            $objects = scandir($directory);
            
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (is_dir($directory . '/' . $object)) {
                        $this->removeDirectory($directory . '/' . $object);
                    } else {
                        unlink($directory . '/' . $object);
                    }
                }
            }
            
            rmdir($directory);
        }
    }
}