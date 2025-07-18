<?php

namespace Gbitstudio\Modules;

class Manager {
    private $registry;
    private $log;
    private $language;
    private $copied_files = [];
    
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
     * Магический метод для доступа к свойствам registry
     */
    public function __get($name) {
        return $this->registry->get($name);
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
                $this->log->write('GDT Module Manager error: DIR_SYSTEM constant is not defined');
            }
            return $modules;
        }
        
        // Ищем файлы opencart-module.json в директории system/modules/*/
        $modules_dirs = glob(constant('DIR_SYSTEM') . 'modules/*', GLOB_ONLYDIR);
        
        if ($modules_dirs) {
            foreach ($modules_dirs as $module_dir) {
                $module_config_file = $module_dir . '/opencart-module.json';
                
                // Проверяем, существует ли файл конфигурации модуля
                if (file_exists($module_config_file)) {
                    $module_data = json_decode(file_get_contents($module_config_file), true);
                    
                    if ($module_data && is_array($module_data)) {
                        // Добавляем путь к файлу конфигурации
                        $module_data['config_path'] = $module_config_file;
                        $modules[] = $module_data;
                        
                        if ($this->log) {
                            $this->log->write('GDT Module Manager: Found module ' . ($module_data['name'] ?? 'unknown') . ' in ' . $module_dir);
                        }
                    }
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
        $file_oc = glob(constant('DIR_SYSTEM') . 'modules/*/opencart-module.json');

        foreach ($file_oc as $file) {
            $module_data = json_decode(file_get_contents($file), true);
            if (isset($module_data['code']) && $module_data['code'] === $code) {
                return $module_data;
            }
        }

        // или по имени папки
        $module_dir = constant('DIR_SYSTEM') . 'modules/' . $code;
        if (is_dir($module_dir) && is_file($module_dir . '/opencart-module.json')) {
            $module_data = json_decode(file_get_contents($module_dir . '/opencart-module.json'), true);
            if (isset($module_data['code']) && $module_data['code'] === $code) {
                return $module_data;
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
            $this->log->write('GDT Module Manager Debug: API request for module ' . $module['code'] . ' with API key');
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
                'User-Agent: GDT-ModuleManager/1.0'
            ),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FAILONERROR => false
        ));
        
        // Выполнение запроса
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        // Закрытие cURL сессии
        curl_close($curl);
        
        if ($error) {
            $this->log->write('GDT Module Manager cURL error: ' . $error);
            return array('error' => 'curl', 'message' => $error);
        }
        
        if ($http_code != 200) {
            $this->log->write('GDT Module Manager HTTP error: ' . $http_code);
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
     * Установка модуля по URL
     * 
     * @param string $module_code Код модуля
     * @param string $download_url URL для скачивания
     * @return bool
     */
    public function installModuleURL($module_code, $download_url) {
        try {
            $installed_modules = $this->getInstalledModules();
            foreach ($installed_modules as $installed) {
                if ($installed['code'] === $module_code) {
                    throw new \Exception('Модуль уже установлен');
                }
            }

            $module_info = $this->getModuleByCode($module_code);
            if (!$module_info) {
                throw new \Exception('Модуль не найден на сервере');
            }

            $this->checkCompatibility($module_info);

            $temp_file = $this->downloadModule($download_url);
            if (!$temp_file) {
                throw new \Exception('Не удалось скачать модуль');
            }

            $temp_dir = (defined('DIR_STORAGE') ? constant('DIR_STORAGE') : sys_get_temp_dir() . '/') . 'upload/gdt_module_' . $module_code . '_' . time();
            mkdir($temp_dir, 0755, true);

            try {
                $this->extractAndInstallModule($temp_file, $module_code);
                $this->clearCache();
            } catch (\Exception $e) {
                $this->rollbackChanges();
                throw $e;
            } finally {
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
                if (is_dir($temp_dir)) {
                    $this->removeDirectory($temp_dir);
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка установки модуля: ' . $e->getMessage());
            throw $e;
        }
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
                $this->log->write('GDT Module Manager Debug: Download request for module ' . $module['code'] . ' with API key');
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
                    'User-Agent: GDT-ModuleManager/1.0'
                ),
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FAILONERROR => false
            ));
            
            // Выполнение запроса
            $response = curl_exec($curl);
            $error = curl_error($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            // Закрытие cURL сессии
            curl_close($curl);
            
            if ($error) {
                $this->log->write('GDT Module Manager cURL error: ' . $error);
                return 'Ошибка соединения: ' . $error;
            }
            
            if ($http_code != 200) {
                $this->log->write('GDT Module Manager HTTP error: ' . $http_code);
                return 'HTTP ошибка: ' . $http_code;
            }
            
            if (empty($response)) {
                return 'Пустой ответ от сервера';
            }
            
            // Проверяем, определена ли константа DIR_DOWNLOAD
            if (!defined('DIR_DOWNLOAD')) {
                $download_dir = sys_get_temp_dir() . '/';
            } else {
                $download_dir = constant('DIR_DOWNLOAD');
            }
            
            // Временный файл для загрузки
            $temp_file = $download_dir . 'update_' . $module['code'] . '_' . $update_info['version'] . '.zip';
            
            // Сохраняем во временный файл
            file_put_contents($temp_file, $response);
            
            // Проверяем, является ли файл действительным архивом ZIP
            $zip = new \ZipArchive();
            if ($zip->open($temp_file) !== true) {
                @unlink($temp_file);
                return 'Загруженный файл не является корректным ZIP архивом';
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
                return 'Константа DIR_APPLICATION не определена';
            }
            
            // Применяем обновление - копируем файлы в нужные директории
            // получаем путь к директории OpenCart
            $opencart_dir = realpath(constant('DIR_APPLICATION') . '../');
            
            $this->copyDirectory($source_dir, $opencart_dir);
            
            // Обновляем версию в файле конфигурации модуля
            if (isset($module['config_path']) && file_exists($module['config_path'])) {
                $config_data = json_decode(file_get_contents($module['config_path']), true);
                if ($config_data) {
                    $config_data['version'] = $update_info['version'];
                    file_put_contents($module['config_path'], json_encode($config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
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
     * Скачивание модуля
     * 
     * @param string $download_url URL для скачивания
     * @return string|false Путь к временному файлу или false при ошибке
     */
    private function downloadModule($download_url) {
        $temp_file = tempnam(sys_get_temp_dir(), 'gdt_module_');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $download_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $data !== false) {
            file_put_contents($temp_file, $data);
            return $temp_file;
        }

        return false;
    }
    
    /**
     * Извлечение и установка модуля
     * 
     * @param string $zip_file Путь к ZIP файлу
     * @param string $module_code Код модуля
     */
    private function extractAndInstallModule($zip_file, $module_code) {
        if (!extension_loaded('zip')) {
            throw new \Exception('PHP расширение ZIP не установлено');
        }

        $zip = new \ZipArchive;
        if ($zip->open($zip_file) !== TRUE) {
            throw new \Exception('Не удалось открыть ZIP архив');
        }

        // Создаем временную папку для извлечения
        $temp_dir = (defined('DIR_STORAGE') ? constant('DIR_STORAGE') : sys_get_temp_dir() . '/') . 'upload/gdt_module_' . $module_code . '_' . time();
        mkdir($temp_dir, 0755, true);

        // Извлекаем архив
        if (!$zip->extractTo($temp_dir)) {
            $zip->close();
            $this->removeDirectory($temp_dir);
            throw new \Exception('Не удалось извлечь архив');
        }

        $zip->close();

        try {
            // Копируем файлы в нужные места
            $this->copyModuleFiles($temp_dir, $module_code);
        } catch (\Exception $e) {
            $this->removeDirectory($temp_dir);
            throw new \Exception('Ошибка копирования файлов: ' . $e->getMessage());
        }

        // Удаляем временную папку
        $this->removeDirectory($temp_dir);
    }
    
    /**
     * Копирование файлов модуля
     * 
     * @param string $source_dir Исходная директория
     * @param string $module_code Код модуля
     * @return array
     */
    private function copyModuleFiles($source_dir, $module_code) {
        $opencart_root = constant('DIR_APPLICATION') . '../';

        // Ищем папку upload в архиве
        $upload_dir = $source_dir . '/upload';
        if (!is_dir($upload_dir)) {
            // Возможно структура другая, ищем прямо в корне
            $upload_dir = $source_dir;
        }

        try {
            // Копируем основные файлы модуля
            $this->copyDirectory($upload_dir, $opencart_root);

            // Проверяем наличие opencart-module.json в корне архива
            $module_config_source = $source_dir . '/opencart-module.json';
            if (file_exists($module_config_source)) {
                // Создаем директорию для модуля в system/modules
                $module_dir = $opencart_root . 'system/modules/' . $module_code;
                if (!is_dir($module_dir)) {
                    mkdir($module_dir, 0755, true);
                }

                // Копируем файл конфигурации
                $module_config_dest = $module_dir . '/opencart-module.json';
                if (copy($module_config_source, $module_config_dest)) {
                    // Логируем успешное копирование конфигурации
                    if ($this->log) {
                        $this->log->write('GDT Module Manager: Configuration file copied for module ' . $module_code);
                    }
                } else {
                    // Логируем ошибку копирования конфигурации
                    if ($this->log) {
                        $this->log->write('GDT Module Manager: Failed to copy configuration file for module ' . $module_code);
                    }
                }
            } else {
                // Логируем отсутствие файла конфигурации
                if ($this->log) {
                    $this->log->write('GDT Module Manager: No opencart-module.json found for module ' . $module_code);
                }
            }

            return array(
                'success' => true,
                'message' => 'Файлы модуля успешно скопированы'
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => 'Ошибка копирования файлов: ' . $e->getMessage()
            );
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
                mkdir($destination, 0755, true);
            }
            
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

                if ($item->isDir()) {
                    if (!is_dir($target)) {
                        mkdir($target, 0755, true);
                    }
                } else {
                    $target_dir = dirname($target);
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    copy($item, $target);
                }
            }
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
        if (!is_dir($directory)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
        return true;
    }
    
    /**
     * Создает модуль в новой структуре (для тестирования и миграции)
     * 
     * @param array $module_data Данные модуля
     * @return bool
     */
    public function createModuleStructure($module_data) {
        if (!defined('DIR_SYSTEM')) {
            if ($this->log) {
                $this->log->write('GDT Module Manager error: DIR_SYSTEM constant is not defined');
            }
            return false;
        }
        
        $modules_dir = rtrim(constant('DIR_SYSTEM'), '/') . '/modules';
        $module_dir = $modules_dir . '/' . $module_data['code'];
        
        // Создаем директорию модулей, если не существует
        if (!is_dir($modules_dir)) {
            mkdir($modules_dir, 0755, true);
        }
        
        // Создаем директорию конкретного модуля
        if (!is_dir($module_dir)) {
            mkdir($module_dir, 0755, true);
        }
        
        // Создаем файл конфигурации opencart-module.json
        $config_file = $module_dir . '/opencart-module.json';
        $config_content = json_encode($module_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($config_file, $config_content)) {
            if ($this->log) {
                $this->log->write('GDT Module Manager: Module structure created for ' . $module_data['code']);
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Проверка совместимости модуля
     * 
     * @param array $module_info Информация о модуле
     */
    private function checkCompatibility($module_info) {
        $current_version = constant('VERSION'); // Версия OpenCart
        if (isset($module_info['min_version']) && version_compare($current_version, $module_info['min_version'], '<')) {
            throw new \Exception('Модуль не совместим с текущей версией OpenCart (требуется версия ' . $module_info['min_version'] . ' или выше)');
        }
        if (isset($module_info['max_version']) && version_compare($current_version, $module_info['max_version'], '>')) {
            throw new \Exception('Модуль не совместим с текущей версией OpenCart (требуется версия ' . $module_info['max_version'] . ' или ниже)');
        }
    }
    
    /**
     * Логирование ошибок
     * 
     * @param string $message Сообщение об ошибке
     */
    private function logError($message) {
        if ($this->log) {
            $this->log->write($message);
        }
    }
    
    /**
     * Очистка кэша
     */
    private function clearCache() {
        if ($this->cache) {
            $this->cache->delete('*');
        }
    }
    
    /**
     * Откат изменений при ошибке
     */
    private function rollbackChanges() {
        foreach ($this->copied_files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Установка модуля по коду
     * 
     * @param string $module_code Код модуля
     * @return bool
     */
    public function install($module_code) {
        // Логика установки модуля
        if ($this->log) {
            $this->log->write('GDT Module Manager: Installing module ' . $module_code);
        }
        return true;
    }
    
    /**
     * Удаление модуля
     * 
     * @param string $module_code Код модуля
     * @return bool
     */
    public function uninstall($module_code) {
        // Логика удаления модуля
        if ($this->log) {
            $this->log->write('GDT Module Manager: Uninstalling module ' . $module_code);
        }
        return true;
    }
}
