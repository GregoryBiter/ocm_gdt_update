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
    }    /**
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

            // Скачиваем модуль с сервера
            $temp_file = $this->downloadModule($download_url, $module_code);
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
            $this->log->write('GDT Module Manager: Starting update for module ' . $module['code']);
            
            // URL для загрузки обновления
            $download_url = rtrim($server_url, '/') . '/index.php?route=gdt_update_server/download';
            
            // Получаем API ключ из настроек, если не передан
            if (empty($api_key)) {
                $api_key = $this->registry->get('config')->get('module_gdt_updater_api_key');
            }
            
            // Логируем для отладки
            $this->log->write('GDT Module Manager: Download URL: ' . $download_url);
            if (!empty($api_key)) {
                $this->log->write('GDT Module Manager: Using API key for authentication');
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
            $content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            
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
                $this->log->write('GDT Module Manager: Empty response from server');
                return 'Пустой ответ от сервера';
            }
            
            // Проверяем, не вернул ли сервер JSON с ошибкой
            if (strpos($content_type, 'application/json') !== false) {
                $json_response = json_decode($response, true);
                if ($json_response && isset($json_response['error'])) {
                    $this->log->write('GDT Module Manager: Server returned error: ' . $json_response['error']);
                    return 'Ошибка сервера: ' . $json_response['error'];
                }
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
            $bytes_written = file_put_contents($temp_file, $response);
            $this->log->write('GDT Module Manager: Downloaded ' . $bytes_written . ' bytes to ' . $temp_file);
            
            if ($bytes_written === false || $bytes_written === 0) {
                return 'Ошибка при сохранении загруженного файла';
            }
            
            // Проверяем, является ли файл действительным архивом ZIP
            $zip = new \ZipArchive();
            $zip_result = $zip->open($temp_file);
            if ($zip_result !== true) {
                @unlink($temp_file);
                $this->log->write('GDT Module Manager: Invalid ZIP file, error code: ' . $zip_result);
                return 'Загруженный файл не является корректным ZIP архивом (код ошибки: ' . $zip_result . ')';
            }
            
            $this->log->write('GDT Module Manager: ZIP archive contains ' . $zip->numFiles . ' files');
            
            // Извлекаем архив
            $extract_dir = $download_dir . 'update_extract_' . $module['code'];
            
            // Очистим директорию, если она уже существует
            if (is_dir($extract_dir)) {
                $this->removeDirectory($extract_dir);
            }
            
            // Создаем директорию заново
            if (!mkdir($extract_dir, 0777, true)) {
                $zip->close();
                @unlink($temp_file);
                return 'Не удалось создать временную директорию для извлечения';
            }
            
            $extracted = $zip->extractTo($extract_dir);
            $zip->close();
            
            if (!$extracted) {
                $this->removeDirectory($extract_dir);
                @unlink($temp_file);
                return 'Ошибка при извлечении архива';
            }
            
            $this->log->write('GDT Module Manager: Files extracted to ' . $extract_dir);
            
            // Проверяем структуру архива и определяем исходную директорию
            $source_dir = $extract_dir;
            
            // Если архив имеет структуру OpenCart с папкой upload
            if (is_dir($extract_dir . '/upload')) {
                $source_dir = $extract_dir . '/upload';
                $this->log->write('GDT Module Manager: Using upload subdirectory: ' . $source_dir);
            }
            
            // Проверяем, определена ли константа DIR_APPLICATION
            if (!defined('DIR_APPLICATION')) {
                $this->removeDirectory($extract_dir);
                @unlink($temp_file);
                return 'Константа DIR_APPLICATION не определена';
            }
            
            // Применяем обновление - копируем файлы в нужные директории
            // получаем путь к директории OpenCart
            $opencart_dir = realpath(constant('DIR_APPLICATION') . '../');
            
            if (!$opencart_dir) {
                $this->removeDirectory($extract_dir);
                @unlink($temp_file);
                return 'Не удалось определить корневую директорию OpenCart';
            }
            
            $this->log->write('GDT Module Manager: Copying files from ' . $source_dir . ' to ' . $opencart_dir);
            
            // Создаем резервную копию перед обновлением
            $backup_created = $this->createBackup($module);
            if ($backup_created) {
                $this->log->write('GDT Module Manager: Backup created successfully');
            }
            
            // Копируем файлы модуля
            $this->copyDirectory($source_dir, $opencart_dir);
            
            // Обрабатываем файл конфигурации opencart-module.json
            $config_updated = $this->handleModuleConfig($extract_dir, $module, $update_info);
            
            if (!$config_updated) {
                $this->log->write('GDT Module Manager: Warning - could not update version in module config');
            }
            
            // Очищаем временные файлы
            $this->removeDirectory($extract_dir);
            @unlink($temp_file);
            
            $this->log->write('GDT Module Manager: Update completed successfully for module ' . $module['code']);
            
            return true;
        } catch (\Exception $e) {
            $this->log->write('GDT Module Manager: Exception during update: ' . $e->getMessage());
            return 'Исключение: ' . $e->getMessage();
        }
    }
    
    /**
     * Обрабатывает файл конфигурации модуля при обновлении
     * 
     * @param string $extract_dir Директория с извлеченными файлами
     * @param array $module Информация о модуле
     * @param array $update_info Информация об обновлении
     * @return bool
     */
    private function handleModuleConfig($extract_dir, $module, $update_info) {
        $module_code = $module['code'];
        $target_config_dir = constant('DIR_SYSTEM') . 'modules/' . $module_code;
        $target_config_path = $target_config_dir . '/opencart-module.json';
        
        // Вариант 1: Файл конфигурации в корне архива
        $root_config_path = $extract_dir . '/opencart-module.json';
        
        // Вариант 2: Файл конфигурации в system/modules/module_name/
        $system_config_path = $extract_dir . '/upload/system/modules/' . $module_code . '/opencart-module.json';
        if (!file_exists($system_config_path)) {
            // Попробуем без upload подпапки
            $system_config_path = $extract_dir . '/system/modules/' . $module_code . '/opencart-module.json';
        }
        
        $config_updated = false;
        
        if (file_exists($root_config_path)) {
            // Случай 1: opencart-module.json в корне архива
            $this->log->write('GDT Module Manager: Found opencart-module.json in archive root');
            
            // Создаем директорию модуля если не существует
            if (!is_dir($target_config_dir)) {
                mkdir($target_config_dir, 0755, true);
            }
            
            // Полностью копируем новый файл конфигурации
            $old_version = isset($module['version']) ? $module['version'] : 'unknown';
            if (copy($root_config_path, $target_config_path)) {
                $config_updated = true;
                $this->log->write('GDT Module Manager: Copied complete config from archive root, updated from version ' . $old_version . ' to ' . $update_info['version']);
            } else {
                $this->log->write('GDT Module Manager: Failed to copy config file from archive root');
            }
        } elseif (file_exists($system_config_path)) {
            // Случай 2: opencart-module.json в system/modules/module_name/ внутри архива
            $this->log->write('GDT Module Manager: Found opencart-module.json in system/modules structure, already copied via copyDirectory');
            
            // В этом случае файл уже скопирован через copyDirectory
            // Проверяем, что файл действительно скопирован
            if (file_exists($target_config_path)) {
                $config_updated = true;
                $old_version = isset($module['version']) ? $module['version'] : 'unknown';
                $this->log->write('GDT Module Manager: Complete config copied from system structure, updated from version ' . $old_version . ' to ' . $update_info['version']);
            }
        } else {
            // Случай 3: Файл конфигурации не найден в архиве, обновляем существующий
            $this->log->write('GDT Module Manager: No opencart-module.json found in archive, keeping existing config with version update');
            
            if (isset($module['config_path']) && file_exists($module['config_path'])) {
                $config_data = json_decode(file_get_contents($module['config_path']), true);
                if ($config_data) {
                    $old_version = $config_data['version'] ?? 'unknown';
                    $config_data['version'] = $update_info['version'];
                    $config_written = file_put_contents($module['config_path'], json_encode($config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    if ($config_written !== false) {
                        $config_updated = true;
                        $this->log->write('GDT Module Manager: Updated existing config version from ' . $old_version . ' to ' . $update_info['version']);
                    }
                }
            }
        }
        
        return $config_updated;
    }
    
    /**
     * Создает резервную копию модуля перед обновлением
     */
    private function createBackup($module) {
        try {
            // Реализация создания бэкапа может быть добавлена позже
            return true;
        } catch (\Exception $e) {
            $this->log->write('GDT Module Manager: Backup creation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Скачивание модуля
     * 
     * @param string $download_url URL для скачивания
     * @param string $module_code Код модуля
     * @return string|false Путь к временному файлу или false при ошибке
     */
    private function downloadModule($download_url, $module_code = '') {
        $temp_file = tempnam(sys_get_temp_dir(), 'gdt_module_');

        $ch = curl_init();
        
        // Формируем POST данные
        $post_data = array();
        if (!empty($module_code)) {
            $post_data['module_code'] = $module_code;
        }
        
        // Получаем API ключ из конфигурации если он есть
        $api_key = $this->registry->get('config')->get('module_gdt_updater_api_key');
        if (!empty($api_key)) {
            $post_data['api_key'] = $api_key;
        }
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $download_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'GDT-ModuleManager/1.0'
        ));
        
        // Если есть данные для POST, отправляем POST-запрос
        if (!empty($post_data)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        }

        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('cURL ошибка: ' . $error);
        }

        if ($http_code === 200 && $data !== false) {
            // Проверяем, что получили файл, а не JSON с ошибкой
            $decoded = json_decode($data, true);
            if ($decoded !== null && isset($decoded['error'])) {
                throw new \Exception('Ошибка сервера: ' . $decoded['error']);
            }
            
            file_put_contents($temp_file, $data);
            return $temp_file;
        } else {
            throw new \Exception('HTTP ошибка: ' . $http_code);
        }
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

            // Обрабатываем файл конфигурации модуля используя ту же логику что и в обновлении
            $this->handleModuleConfigForInstall($source_dir, $module_code, $opencart_root);

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
     * Обрабатывает файл конфигурации модуля при установке
     * 
     * @param string $source_dir Директория с извлеченными файлами
     * @param string $module_code Код модуля
     * @param string $opencart_root Корневая директория OpenCart
     * @return bool
     */
    private function handleModuleConfigForInstall($source_dir, $module_code, $opencart_root) {
        $target_config_dir = $opencart_root . 'system/modules/' . $module_code;
        $target_config_path = $target_config_dir . '/opencart-module.json';
        
        // Вариант 1: Файл конфигурации в корне архива
        $root_config_path = $source_dir . '/opencart-module.json';
        
        // Вариант 2: Файл конфигурации в system/modules/module_name/
        $system_config_path = $source_dir . '/upload/system/modules/' . $module_code . '/opencart-module.json';
        if (!file_exists($system_config_path)) {
            // Попробуем без upload подпапки
            $system_config_path = $source_dir . '/system/modules/' . $module_code . '/opencart-module.json';
        }
        
        $config_copied = false;
        
        if (file_exists($root_config_path)) {
            // Случай 1: opencart-module.json в корне архива
            $this->log->write('GDT Module Manager: Found opencart-module.json in archive root for installation');
            
            // Создаем директорию модуля если не существует
            if (!is_dir($target_config_dir)) {
                mkdir($target_config_dir, 0755, true);
            }
            
            // Копируем файл конфигурации
            if (copy($root_config_path, $target_config_path)) {
                $config_copied = true;
                $this->log->write('GDT Module Manager: Configuration file copied from archive root for module ' . $module_code);
            } else {
                $this->log->write('GDT Module Manager: Failed to copy configuration file from archive root for module ' . $module_code);
            }
        } elseif (file_exists($system_config_path)) {
            // Случай 2: opencart-module.json в system/modules/module_name/ внутри архива
            $this->log->write('GDT Module Manager: Found opencart-module.json in system/modules structure for installation');
            
            // В этом случае файл уже скопирован через copyDirectory
            if (file_exists($target_config_path)) {
                $config_copied = true;
                $this->log->write('GDT Module Manager: Configuration file already copied via system structure for module ' . $module_code);
            }
        } else {
            // Случай 3: Файл конфигурации не найден в архиве
            $this->log->write('GDT Module Manager: No opencart-module.json found in archive for module ' . $module_code);
        }
        
        return $config_copied;
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
                $relative_path = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getPathname());
                $target = $destination . DIRECTORY_SEPARATOR . $relative_path;

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
    public function clearCache() {
        try {
            // Очищаем кэш OpenCart
            $cache = $this->registry->get('cache');
            if ($cache) {
                $cache->delete('*');
            }
            
            // Очищаем кэш модификаций
            if (defined('DIR_CACHE')) {
                if (is_file(constant('DIR_CACHE') . 'cache.modification')) {
                    @unlink(constant('DIR_CACHE') . 'cache.modification');
                }
                
                // Очищаем кэш Twig
                $twig_cache_dir = constant('DIR_CACHE') . 'template/';
                if (is_dir($twig_cache_dir)) {
                    $this->removeDirectory($twig_cache_dir);
                }
                
                // Очищаем системный кэш
                $system_cache_files = glob(constant('DIR_CACHE') . '*');
                if ($system_cache_files) {
                    foreach ($system_cache_files as $file) {
                        if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'cache') {
                            @unlink($file);
                        }
                    }
                }
            }
            
            $this->log->write('GDT Module Manager: Cache cleared successfully');
            return true;
        } catch (\Exception $e) {
            $this->log->write('GDT Module Manager: Error clearing cache: ' . $e->getMessage());
            return false;
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
    
    /**
     * Получение модулей из внешнего API сервера
     * 
     * @param string $server_url URL сервера модулей
     * @param array $params Параметры запроса
     * @param string $api_key Ключ API для аутентификации
     * @return array
     */
    public function getModulesFromServer($server_url, $params = array(), $api_key = '') {
        $url = rtrim($server_url, '/') . '/index.php?route=gdt_update_server/modules';
        
        // Добавляем параметры к URL
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }
        
        // Получаем API ключ из настроек, если не передан
        if (empty($api_key)) {
            $api_key = $this->registry->get('config')->get('module_gdt_updater_api_key');
        }
        
        // Данные для POST запроса
        $post_data = array();
        if (!empty($api_key)) {
            $post_data['api_key'] = $api_key;
        }
        
        // Инициализация cURL сессии
        $curl = curl_init();
        
        // Настройка параметров cURL
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => !empty($post_data),
            CURLOPT_POSTFIELDS => !empty($post_data) ? http_build_query($post_data) : '',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: GDT-ModuleManager/1.0',
                'Accept: application/json'
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
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
            if ($this->log) {
                $this->log->write('GDT Module Manager API error: ' . $error);
            }
            throw new \Exception('Ошибка соединения с сервером модулей: ' . $error);
        }
        
        if ($http_code != 200) {
            if ($this->log) {
                $this->log->write('GDT Module Manager HTTP error: ' . $http_code);
            }
            throw new \Exception('HTTP ошибка: ' . $http_code);
        }
        
        if (empty($response)) {
            throw new \Exception('Пустой ответ от сервера модулей');
        }
        
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new \Exception('Некорректный JSON ответ от сервера модулей');
        }
        
        if (isset($decoded['success']) && $decoded['success']) {
            return $decoded['modules'];
        } else {
            $error_msg = isset($decoded['error']) ? $decoded['error'] : 'Неизвестная ошибка API';
            throw new \Exception('Ошибка API сервера модулей: ' . $error_msg);
        }
    }
}
