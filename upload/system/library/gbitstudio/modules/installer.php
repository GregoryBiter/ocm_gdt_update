<?php

namespace Gbitstudio\Modules;

/**
 * Вспомогательный класс для установки и обновления модулей
 */
class Installer {
    private $manager;
    private $log;
    
    public function __construct($manager) {
        $this->manager = $manager;
        $this->log = $manager->log;
    }
    
    /**
     * Логирует начало загрузки модуля
     */
    public function logDownloadStart($download_url, $api_key) {
        $this->log->write('GDT Module Manager: Download URL: ' . $download_url);
        if (!empty($api_key)) {
            $this->log->write('GDT Module Manager: Using API key for authentication');
        }
    }
    
    /**
     * Форматирует ошибку загрузки
     */
    public function formatDownloadError($response_data) {
        if ($response_data['error'] === 'curl') {
            return 'Ошибка соединения: ' . $response_data['message'];
        }
        return 'HTTP ошибка: ' . $response_data['code'];
    }
    
    /**
     * Проверяет корректность ответа при загрузке
     */
    public function validateDownloadResponse($response_data) {
        if (empty($response_data['response'])) {
            $this->log->write('GDT Module Manager: Empty response from server');
            return 'Пустой ответ от сервера';
        }
        
        if (isset($response_data['content_type']) && strpos($response_data['content_type'], 'application/json') !== false) {
            $json_response = json_decode($response_data['response'], true);
            if ($json_response && isset($json_response['error'])) {
                $this->log->write('GDT Module Manager: Server returned error: ' . $json_response['error']);
                return 'Ошибка сервера: ' . $json_response['error'];
            }
        }
        
        return true;
    }
    
    /**
     * Сохраняет загруженный модуль во временный файл
     */
    public function saveDownloadedModule($response, $module, $update_info) {
        $download_dir = defined('DIR_DOWNLOAD') ? constant('DIR_DOWNLOAD') : sys_get_temp_dir() . '/';
        $temp_file = $download_dir . 'update_' . $module['code'] . '_' . $update_info['version'] . '.zip';
        
        $bytes_written = file_put_contents($temp_file, $response);
        $this->log->write('GDT Module Manager: Downloaded ' . $bytes_written . ' bytes to ' . $temp_file);
        
        if ($bytes_written === false || $bytes_written === 0) {
            return 'Ошибка при сохранении загруженного файла';
        }
        
        return $temp_file;
    }
    
    /**
     * Проверяет корректность ZIP файла
     */
    public function validateZipFile($temp_file) {
        $zip = new \ZipArchive();
        $zip_result = $zip->open($temp_file);
        
        if ($zip_result !== true) {
            $this->log->write('GDT Module Manager: Invalid ZIP file, error code: ' . $zip_result);
            return 'Загруженный файл не является корректным ZIP архивом (код ошибки: ' . $zip_result . ')';
        }
        
        $this->log->write('GDT Module Manager: ZIP archive contains ' . $zip->numFiles . ' files');
        $zip->close();
        
        return true;
    }
    
    /**
     * Извлекает архив модуля
     */
    public function extractModuleArchive($temp_file, $extract_dir) {
        if (is_dir($extract_dir)) {
            $this->manager->removeDirectory($extract_dir);
        }
        
        if (!mkdir($extract_dir, 0777, true)) {
            return 'Не удалось создать временную директорию для извлечения';
        }
        
        $zip = new \ZipArchive();
        $zip->open($temp_file);
        $extracted = $zip->extractTo($extract_dir);
        $zip->close();
        
        if (!$extracted) {
            $this->manager->removeDirectory($extract_dir);
            return 'Ошибка при извлечении архива';
        }
        
        $this->log->write('GDT Module Manager: Files extracted to ' . $extract_dir);
        return true;
    }
    
    /**
     * Определяет исходную директорию в архиве
     */
    public function determineSourceDirectory($extract_dir) {
        if (is_dir($extract_dir . '/upload')) {
            $this->log->write('GDT Module Manager: Using upload subdirectory');
            return $extract_dir . '/upload';
        }
        return $extract_dir;
    }
    
    /**
     * Получает корневую директорию OpenCart
     */
    public function getOpenCartDirectory() {
        if (!defined('DIR_APPLICATION')) {
            return false;
        }
        return realpath(constant('DIR_APPLICATION') . '../');
    }
}
