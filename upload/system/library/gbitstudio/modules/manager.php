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
     * Получает список установленных модулей из базы данных, модификаторов OpenCart и системных файлов
     * Приоритет: OpenCart modifications > System files > gdt_modules (deprecated)
     * 
     * @return array
     */
    public function getInstalledModules() {
        $modules = [];
        $modules_by_code = [];
        $db = $this->registry->get('db');
        
        // 1. ПРИОРИТЕТ: Получаем модули из таблицы модификаторов OpenCart (основной источник)
        $opencart_modules = $this->getModulesFromOpenCartModifications();
        foreach ($opencart_modules as $module) {
            if (isset($module['code'])) {
                $modules_by_code[$module['code']] = $module;
            }
        }
        
        // 2. Получаем модули из системных файлов .ocmod.xml (средний приоритет)
        $system_modules = $this->getModulesFromSystemFiles();
        foreach ($system_modules as $module) {
            if (isset($module['code']) && !isset($modules_by_code[$module['code']])) {
                $modules_by_code[$module['code']] = $module;
            }
        }
        
        // 3. DEPRECATED: Получаем модули из собственной таблицы gdt_modules (обратная совместимость)
        // Эта таблица будет снята с поддержки, но пока сохраняется для старых модулей
        try {
            $query = "SELECT * FROM `gdt_modules`;";
            $result = $db->query($query);

            if ($result->num_rows > 0) {
                foreach ($result->rows as $row) {
                    $module_data = json_decode($row['data'], true);
                    if ($module_data && isset($module_data['code'])) {
                        // Добавляем только если модуль еще не найден в приоритетных источниках
                        if (!isset($modules_by_code[$module_data['code']])) {
                            $module_data['source'] = 'gdt_modules_deprecated';
                            $modules_by_code[$module_data['code']] = $module_data;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки таблицы gdt_modules для обратной совместимости
            if ($this->log) {
                $this->log->write('GDT Module Manager: gdt_modules table not available (deprecated): ' . $e->getMessage());
            }
        }

        // 4. Добавляем сам gdt_updater если его нет в списке
        if (!isset($modules_by_code['gdt_updater'])) {
            $gdt_updater_data = $this->getJson();
            if ($gdt_updater_data && isset($gdt_updater_data['code'])) {
                $modules_by_code[$gdt_updater_data['code']] = $gdt_updater_data;
            }
        }

        // Преобразуем ассоциативный массив обратно в индексированный
        return array_values($modules_by_code);
    }

    /**
     * Получает модуль по коду из всех источников
     * Приоритет: OpenCart modifications > System files > gdt_modules (deprecated)
     * 
     * @param string $code Код модуля
     * @return array|null
     */
    public function getModuleByCode($code) {
        // ПРИОРИТЕТ 1: Ищем в модификаторах OpenCart (основной источник)
        $module = $this->getModuleFromOpenCartModifications($code);
        if ($module) {
            return $module;
        }
        
        // ПРИОРИТЕТ 2: Ищем в системных файлах
        $module = $this->getModuleFromSystemFiles($code);
        if ($module) {
            return $module;
        }
        
        // ПРИОРИТЕТ 3 (DEPRECATED): Ищем в собственной базе данных для обратной совместимости
        try {
            $module = $this->getModuleFromDatabase($code);
            if ($module) {
                $module['source'] = 'gdt_modules_deprecated';
                return $module;
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки таблицы gdt_modules
            if ($this->log) {
                $this->log->write('GDT Module Manager: gdt_modules table not available for ' . $code . ' (deprecated)');
            }
        }
        
        return null;
    }

    /**
     * Получает модуль по коду из модификаторов OpenCart
     * 
     * @param string $code Код модуля
     * @return array|null
     */
    private function getModuleFromOpenCartModifications($code) {
        $db = $this->registry->get('db');
        
        try {
            // Проверяем существование таблицы модификаторов
            $table_exists_query = "SHOW TABLES LIKE '" . DB_PREFIX . "modification'";
            $table_result = $db->query($table_exists_query);
            
            if ($table_result->num_rows > 0) {
                $query = "SELECT * FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($code) . "' AND `status` = 1 LIMIT 1";
                $result = $db->query($query);
                
                if ($result->num_rows > 0) {
                    $row = $result->row;
                    return [
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'version' => isset($row['version']) ? $row['version'] : '1.0.0',
                        'author' => isset($row['author']) ? $row['author'] : 'Unknown',
                        'link' => isset($row['link']) ? $row['link'] : '',
                        'description' => isset($row['description']) ? $row['description'] : '',
                        'source' => 'opencart_modification',
                        'modification_id' => $row['modification_id'],
                        'date_added' => isset($row['date_added']) ? $row['date_added'] : '',
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logError('GDT Module Manager: Error getting OpenCart modification by code: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Получает модуль по коду из системных файлов
     * 
     * @param string $code Код модуля
     * @return array|null
     */
    private function getModuleFromSystemFiles($code) {
        try {
            $system_dir = constant('DIR_SYSTEM');
            $pattern = $system_dir . '*.ocmod.xml';
            $files = glob($pattern);
            
            foreach ($files as $file) {
                $module_data = $this->parseOcmodFile($file);
                if ($module_data && $module_data['code'] === $code) {
                    return $module_data;
                }
            }
        } catch (\Exception $e) {
            $this->logError('GDT Module Manager: Error getting system module by code: ' . $e->getMessage());
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
        $this->log->write('param: '. json_encode([$server_url, $module, $client_id, $api_key], JSON_PRETTY_PRINT));
        
        $api_key = $this->getApiKey($api_key);
        $url = rtrim($server_url, '/') . '/index.php?route=gdt_update_server/check';
        $post_data = $this->prepareCheckUpdateData($module, $api_key);
        
        $this->logUpdateCheck($module, $api_key);
        
        $response_data = $this->executeApiRequest($url, $post_data);
        
        if (isset($response_data['error'])) {
            return $response_data;
        }
        
        return $this->parseUpdateResponse($response_data, $module);
    }
    
    /**
     * Получает API ключ из параметров или настроек
     */
    private function getApiKey($api_key) {
        if (empty($api_key)) {
            return $this->registry->get('config')->get('module_gdt_updater_api_key');
        }
        return $api_key;
    }
    
    /**
     * Подготавливает данные для проверки обновления
     */
    private function prepareCheckUpdateData($module, $api_key) {
        $post_data = array(
            'code' => $module['code'],
            'version' => $module['version']
        );
        
        if (!empty($api_key)) {
            $post_data['api_key'] = $api_key;
        }
        
        return $post_data;
    }
    
    /**
     * Логирует информацию о проверке обновления
     */
    private function logUpdateCheck($module, $api_key) {
        if (!empty($api_key)) {
            $this->log->write('GDT Module Manager Debug: API request for module ' . $module['code'] . ' with API key');
        }
        $this->log->write('GDT Module Manager Debug: Checking update for module ' . json_encode($module));
    }
    
    /**
     * Парсит ответ сервера о наличии обновлений
     */
    private function parseUpdateResponse($response_data, $module) {
        if (isset($response_data['response'])) {
            $update_info = json_decode($response_data['response'], true);
            
            if (isset($update_info['status']) && $update_info['status'] == 'update_available') {
                return $update_info;
            }
        }
        
        $this->log->write('GDT Module Manager: No update available for module ' . $module['code']);
        return false;
    }
    
    /**
     * Выполняет API запрос к серверу
     */
    private function executeApiRequest($url, $post_data, $timeout = 10) {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: GDT-ModuleManager/1.0'
            ),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FAILONERROR => false
        ));
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        
        curl_close($curl);
        
        if ($error) {
            $this->log->write('GDT Module Manager cURL error: ' . $error);
            return array('error' => 'curl', 'message' => $error);
        }
        
        if ($http_code != 200) {
            $this->log->write('GDT Module Manager HTTP error: ' . $http_code);
            return array('error' => 'http', 'code' => $http_code);
        }
        
        return array(
            'response' => $response,
            'http_code' => $http_code,
            'content_type' => $content_type
        );
    }
    
    /**
     * Установка модуля по URL через встроенный процесс OpenCart
     * 
     * @param string $module_code Код модуля
     * @param string $download_url URL для загрузки модуля
     * @param bool $use_opencart_process Использовать встроенный процесс OpenCart (по умолчанию true)
     * @return bool|string
     */
    public function installModuleURL($module_code, $download_url, $use_opencart_process = true) {
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

            try {
                if ($use_opencart_process) {
                    // Используем встроенный процесс установки OpenCart
                    $result = $this->installViaOpenCartProcess($temp_file, $module_code);
                    
                    if ($result !== true) {
                        throw new \Exception($result);
                    }
                    
                    $this->clearCache();
                } else {
                    // Используем старый метод установки
                    $temp_dir = (defined('DIR_STORAGE') ? constant('DIR_STORAGE') : sys_get_temp_dir() . '/') . 'upload/gdt_module_' . $module_code . '_' . time();
                    mkdir($temp_dir, 0755, true);
                    
                    $this->extractAndInstallModule($temp_file, $module_code);
                    $this->clearCache();
                    
                    if (is_dir($temp_dir)) {
                        $this->removeDirectory($temp_dir);
                    }
                }
            } catch (\Exception $e) {
                $this->rollbackChanges();
                throw $e;
            } finally {
                // Очищаем временный файл только если он не был перемещен процессом OpenCart
                if (file_exists($temp_file) && !$use_opencart_process) {
                    unlink($temp_file);
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка установки модуля: ' . $e->getMessage());
            return $e->getMessage();
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
     * @param bool $use_opencart_process Использовать встроенный процесс OpenCart (по умолчанию true)
     * @return bool|string
     */
    public function downloadAndInstallUpdate($server_url, $module, $update_info, $client_id = 'default', $api_key = '', $use_opencart_process = true) {
        try {
            $this->log->write('GDT Module Manager: Starting update for module ' . $module['code']);
            
            $api_key = $this->getApiKey($api_key);
            $download_url = rtrim($server_url, '/') . '/index.php?route=gdt_update_server/download';
            
            $this->log->write('GDT Module Manager: Download URL: ' . $download_url);
            if (!empty($api_key)) {
                $this->log->write('GDT Module Manager: Using API key for authentication');
            }
            
            // Подготавливаем данные для загрузки
            $post_data = array('code' => $module['code']);
            if (!empty($api_key)) {
                $post_data['api_key'] = $api_key;
            }
            
            // Загружаем модуль с сервера
            $response_data = $this->executeApiRequest($download_url, $post_data, 300);
            
            // Проверяем ошибки загрузки
            if (isset($response_data['error'])) {
                return $this->formatDownloadError($response_data);
            }
            
            // Валидируем ответ сервера
            $validation_result = $this->validateDownloadResponse($response_data);
            if ($validation_result !== true) {
                return $validation_result;
            }
            
            // Сохраняем загруженный файл
            $temp_file = $this->saveDownloadedModule($response_data['response'], $module, $update_info);
            if (!is_string($temp_file)) {
                return $temp_file;
            }
            
            // Проверяем корректность ZIP архива
            $zip_validation = $this->validateZipFile($temp_file);
            if ($zip_validation !== true) {
                @unlink($temp_file);
                return $zip_validation;
            }
            
            // Выбираем метод установки
            if ($use_opencart_process) {
                return $this->installUpdateViaOpenCart($temp_file, $module, $update_info);
            } else {
                return $this->installUpdateViaDirectCopy($temp_file, $module, $update_info);
            }
            
        } catch (\Exception $e) {
            $this->log->write('GDT Module Manager: Exception during update: ' . $e->getMessage());
            return 'Исключение: ' . $e->getMessage();
        }
    }
    
    /**
     * Форматирует ошибку загрузки
     */
    private function formatDownloadError($response_data) {
        if ($response_data['error'] === 'curl') {
            return 'Ошибка соединения: ' . $response_data['message'];
        }
        return 'HTTP ошибка: ' . $response_data['code'];
    }
    
    /**
     * Проверяет корректность ответа при загрузке
     */
    private function validateDownloadResponse($response_data) {
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
    private function saveDownloadedModule($response, $module, $update_info) {
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
    private function validateZipFile($temp_file) {
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
     * Устанавливает обновление через встроенный процесс OpenCart
     */
    private function installUpdateViaOpenCart($temp_file, $module, $update_info) {
        $this->log->write('GDT Module Manager: Using OpenCart built-in installation process');
        
        $result = $this->installViaOpenCartProcess($temp_file, $module['code']);
        
        if ($result !== true) {
            @unlink($temp_file);
            return $result;
        }
        
        // Обновляем информацию о версии в конфигурации модуля
        $this->updateModuleVersion($module['code'], $update_info['version'], $update_info);
        
        $this->log->write('GDT Module Manager: Update completed successfully via OpenCart process for module ' . $module['code']);
        
        return true;
    }
    
    /**
     * Устанавливает обновление через прямое копирование файлов
     */
    private function installUpdateViaDirectCopy($temp_file, $module, $update_info) {
        $this->log->write('GDT Module Manager: Using direct file copy method');
        
        $download_dir = defined('DIR_DOWNLOAD') ? constant('DIR_DOWNLOAD') : sys_get_temp_dir() . '/';
        $extract_dir = $download_dir . 'update_extract_' . $module['code'];
        
        try {
            // Извлекаем архив
            $extract_result = $this->extractModuleArchive($temp_file, $extract_dir);
            if ($extract_result !== true) {
                @unlink($temp_file);
                return $extract_result;
            }
            
            // Определяем исходную директорию
            $source_dir = $this->determineSourceDirectory($extract_dir);
            
            // Получаем корневую директорию OpenCart
            $opencart_dir = $this->getOpenCartDirectory();
            if (!$opencart_dir) {
                $this->removeDirectory($extract_dir);
                @unlink($temp_file);
                return 'Не удалось определить корневую директорию OpenCart';
            }
            
            $this->log->write('GDT Module Manager: Copying files from ' . $source_dir . ' to ' . $opencart_dir);
            
            // Создаем резервную копию
            $backup_created = $this->createBackup($module);
            if ($backup_created) {
                $this->log->write('GDT Module Manager: Backup created successfully');
            }
            
            // Копируем файлы модуля
            $this->copyDirectory($source_dir, $opencart_dir);
            
            // Обрабатываем файл конфигурации
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
            if (is_dir($extract_dir)) {
                $this->removeDirectory($extract_dir);
            }
            @unlink($temp_file);
            throw $e;
        }
    }
    
    /**
     * Извлекает архив модуля
     */
    private function extractModuleArchive($temp_file, $extract_dir) {
        if (is_dir($extract_dir)) {
            $this->removeDirectory($extract_dir);
        }
        
        if (!mkdir($extract_dir, 0777, true)) {
            return 'Не удалось создать временную директорию для извлечения';
        }
        
        $zip = new \ZipArchive();
        $zip->open($temp_file);
        $extracted = $zip->extractTo($extract_dir);
        $zip->close();
        
        if (!$extracted) {
            $this->removeDirectory($extract_dir);
            return 'Ошибка при извлечении архива';
        }
        
        $this->log->write('GDT Module Manager: Files extracted to ' . $extract_dir);
        return true;
    }
    
    /**
     * Определяет исходную директорию в архиве
     */
    private function determineSourceDirectory($extract_dir) {
        if (is_dir($extract_dir . '/upload')) {
            $this->log->write('GDT Module Manager: Using upload subdirectory');
            return $extract_dir . '/upload';
        }
        return $extract_dir;
    }
    
    /**
     * Получает корневую директорию OpenCart
     */
    private function getOpenCartDirectory() {
        if (!defined('DIR_APPLICATION')) {
            return false;
        }
        return realpath(constant('DIR_APPLICATION') . '../');
    }
    
    /**
     * Обрабатывает файл конфигурации модуля при обновлении (сохранение в базу данных вместо файла)
     * 
     * @param string $extract_dir Директория с извлеченными файлами
     * @param array $module Информация о модуле
     * @param array $update_info Информация об обновлении
     * @return bool
     */
    private function handleModuleConfig($extract_dir, $module, $update_info) {
        $module_code = $module['code'];
        $root_config_path = $extract_dir . '/opencart-module.json';

        if (file_exists($root_config_path)) {
            $config_data = json_decode(file_get_contents($root_config_path), true);
            if ($config_data) {
                try {
                    $this->saveModuleToDatabase($module_code, $update_info['version'], $config_data);
                } catch (\Exception $e) {
                    $this->log->write('GDT Module Manager: Error updating module config in database for ' . $module_code . ': ' . $e->getMessage());
                }
                
                $this->log->write('GDT Module Manager: Updated module config in database for ' . $module_code);
                return true;
            }
        }

        $this->log->write('GDT Module Manager: No valid opencart-module.json found for ' . $module_code);
        return false;
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
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }

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
        if(is_file($temp_dir . '/opencart-module.json')) {
            $json = file_get_contents($temp_dir . '/opencart-module.json');
            $json_data = json_decode($json, true);
            
        }else {
            $json_data = [
                'version' => '0.0.0',
                'code' => $module_code,
            ];
        }
        $this->saveModuleToDatabase($module_code, $json_data['version'], $json_data);
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
     * Установка модуля через встроенный процесс OpenCart
     * 
     * @param string $zip_file_path Путь к ZIP файлу модуля
     * @param string $module_code Код модуля (опционально)
     * @return bool|string
     */
    public function installViaOpenCartProcess($zip_file_path, $module_code = '') {
        if ($this->log) {
            $this->log->write('GDT Module Manager: Starting OpenCart installation process for ' . $module_code);
            $this->log->write('GDT Module Manager: ZIP file path: ' . $zip_file_path);
        }

        try {
            // Проверяем существование файла
            if (!file_exists($zip_file_path)) {
                throw new \Exception('Файл модуля не найден: ' . $zip_file_path);
            }

            // Генерируем уникальный токен для сессии установки
            $install_token = substr(md5(uniqid(rand(), true)), 0, 10);
            
            // Копируем файл в директорию загрузок OpenCart
            $upload_dir = defined('DIR_UPLOAD') ? constant('DIR_UPLOAD') : (defined('DIR_STORAGE') ? constant('DIR_STORAGE') . 'upload/' : sys_get_temp_dir() . '/');
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $target_file = $upload_dir . $install_token . '.tmp';
            
            if ($this->log) {
                $this->log->write('GDT Module Manager: Copying to upload directory: ' . $target_file);
            }
            
            if (!copy($zip_file_path, $target_file)) {
                throw new \Exception('Не удалось скопировать файл в директорию загрузок');
            }

            // Создаем запись в таблице extension_install (как это делает стандартный installer)
            $this->load->model('setting/extension');
            $filename = !empty($module_code) ? $module_code . '.ocmod.zip' : basename($zip_file_path);
            $extension_install_id = $this->model_setting_extension->addExtensionInstall($filename);
            
            if ($this->log) {
                $this->log->write('GDT Module Manager: Created extension_install_id: ' . $extension_install_id);
            }

            // Сохраняем токен в сессии (как это делает стандартный installer)
            $session = $this->registry->get('session');
            $session->data['install'] = $install_token;
            
            if ($this->log) {
                $this->log->write('GDT Module Manager: Install token: ' . $install_token);
            }

            // Этап 1: Распаковка
            if ($this->log) {
                $this->log->write('GDT Module Manager: Starting unzip step');
            }
            $unzip_result = $this->executeInstallStep('marketplace/install/unzip', $install_token, $extension_install_id);
            if (isset($unzip_result['error'])) {
                throw new \Exception('Ошибка распаковки: ' . $unzip_result['error']);
            }

            // Этап 2: Перемещение файлов
            if ($this->log) {
                $this->log->write('GDT Module Manager: Starting move step');
            }
            $move_result = $this->executeInstallStep('marketplace/install/move', $install_token, $extension_install_id);
            if (isset($move_result['error'])) {
                throw new \Exception('Ошибка перемещения файлов: ' . $move_result['error']);
            }

            // Этап 3: Обработка XML модификаций
            if ($this->log) {
                $this->log->write('GDT Module Manager: Starting XML step');
            }
            $xml_result = $this->executeInstallStep('marketplace/install/xml', $install_token, $extension_install_id);
            if (isset($xml_result['error'])) {
                throw new \Exception('Ошибка обработки XML: ' . $xml_result['error']);
            }

            // Этап 4: Очистка временных файлов
            if ($this->log) {
                $this->log->write('GDT Module Manager: Starting cleanup step');
            }
            $remove_result = $this->executeInstallStep('marketplace/install/remove', $install_token, $extension_install_id);
            if (isset($remove_result['error'])) {
                $this->log->write('GDT Module Manager warning: ' . $remove_result['error']);
            }

            if ($this->log) {
                $this->log->write('GDT Module Manager: Successfully installed module via OpenCart process');
            }

            return true;
        } catch (\Exception $e) {
            $error = 'Ошибка установки через OpenCart: ' . $e->getMessage();
            if ($this->log) {
                $this->log->write('GDT Module Manager error: ' . $error);
            }
            return $error;
        }
    }

    /**
     * Выполняет этап установки через встроенный контроллер OpenCart
     * 
     * @param string $route Маршрут контроллера
     * @param string $install_token Токен установки
     * @param int $extension_install_id ID записи установки расширения
     * @return array
     */
    private function executeInstallStep($route, $install_token, $extension_install_id = 0) {
        try {
            $load = $this->registry->get('load');
            $session = $this->registry->get('session');
            $response = $this->registry->get('response');
            
            // Сохраняем токен в сессии
            $session->data['install'] = $install_token;
            
            // Создаем mock request для контроллера
            $request = $this->registry->get('request');
            $original_get = isset($request->get) ? $request->get : array();
            
            // Устанавливаем необходимые параметры
            $request->get['extension_install_id'] = (int)$extension_install_id;
            $request->get['user_token'] = $session->data['user_token']; // Добавляем user_token
            
            // Сохраняем текущий вывод response
            $original_output = $response->getOutput();
            
            // Очищаем response перед вызовом контроллера
            $response->setOutput('');
            
            // Выполняем контроллер
            $load->controller($route);
            
            // Получаем вывод контроллера
            $output = $response->getOutput();
            
            // Восстанавливаем оригинальные параметры
            $request->get = $original_get;
            $response->setOutput($original_output);
            
            // Логируем для отладки
            if ($this->log) {
                $this->log->write('GDT Module Manager: Response from ' . $route . ': ' . substr($output, 0, 500));
            }
            
            // Парсим JSON ответ
            $result = json_decode($output, true);
            
            if (!$result) {
                // Пробуем очистить вывод от возможного мусора
                $output = trim($output);
                
                // Ищем JSON в выводе
                if (preg_match('/\{.*\}/s', $output, $matches)) {
                    $result = json_decode($matches[0], true);
                }
                
                if (!$result) {
                    if ($this->log) {
                        $this->log->write('GDT Module Manager error: Invalid JSON response from ' . $route . ': ' . $output);
                    }
                    return array('error' => 'Неожиданный формат ответа от контроллера. Ответ: ' . substr($output, 0, 200));
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            if ($this->log) {
                $this->log->write('GDT Module Manager exception in executeInstallStep: ' . $e->getMessage());
            }
            return array('error' => $e->getMessage());
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
     * Удаление модуля по коду с поддержкой разных источников
     * 
     * @param string $module_code Код модуля
     * @return bool|string
     */
    public function uninstall($module_code) {
        if ($this->log) {
            $this->log->write('GDT Module Manager: Starting uninstall for module ' . $module_code);
        }

        // Получаем данные модуля из всех источников
        $module = $this->getModuleByCode($module_code);
        if (!$module) {
            $error = 'Модуль ' . $module_code . ' не найден';
            if ($this->log) {
                $this->log->write('GDT Module Manager error: ' . $error);
            }
            return $error;
        }

        try {
            // Запускаем метод деинсталляции в контроллере модуля, если он есть
            if (isset($module['controller'])) {
                $this->load->controller($module['controller'] . '/uninstall');
            }

            // Удаляем файлы модуля, если есть информация о них
            if (isset($module['files']) || isset($module['delete'])) {
                $this->removeModuleFiles($module);
            }
            
            // Удаляем модуль в зависимости от источника
            $this->removeModuleBySource($module, $module_code);
            
            if ($this->log) {
                $this->log->write('GDT Module Manager: Successfully uninstalled module ' . $module_code);
            }
            
            return true;
        } catch (\Exception $e) {
            $error = 'Ошибка при удалении модуля ' . $module_code . ': ' . $e->getMessage();
            if ($this->log) {
                $this->log->write('GDT Module Manager error: ' . $error);
            }
            return $error;
        }
    }

    /**
     * Удаляет модуль в зависимости от источника
     * 
     * @param array $module Данные модуля
     * @param string $module_code Код модуля
     * @return bool
     */
    private function removeModuleBySource($module, $module_code) {
        $source = isset($module['source']) ? $module['source'] : 'gdt_modules';
        
        switch ($source) {
            case 'opencart_modification':
                return $this->removeOpenCartModification($module);
                
            case 'system_file':
                return $this->removeSystemFile($module);
                
            case 'gdt_modules':
            default:
                // Удаляем из собственной базы данных
                $this->deleteModuleFromDatabase($module_code);
                // Удаляем файл конфигурации модуля
                $this->removeModuleConfig($module_code);
                return true;
        }
    }

    /**
     * Удаляет модификатор OpenCart
     * 
     * @param array $module Данные модуля
     * @return bool
     */
    private function removeOpenCartModification($module) {
        if (!isset($module['modification_id'])) {
            return false;
        }
        
        $db = $this->registry->get('db');
        
        try {
            // Деактивируем модификатор вместо полного удаления
            $query = "UPDATE `" . DB_PREFIX . "modification` SET `status` = 0 WHERE `modification_id` = '" . (int)$module['modification_id'] . "'";
            $db->query($query);
            
            if ($this->log) {
                $this->log->write('GDT Module Manager: Deactivated OpenCart modification with ID ' . $module['modification_id']);
            }
            
            return true;
        } catch (\Exception $e) {
            $this->logError('GDT Module Manager: Error removing OpenCart modification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаляет системный файл модификатора
     * 
     * @param array $module Данные модуля
     * @return bool
     */
    private function removeSystemFile($module) {
        if (!isset($module['file_path']) || !file_exists($module['file_path'])) {
            return false;
        }
        
        try {
            // Создаем резервную копию перед удалением
            $backup_path = $module['file_path'] . '.backup.' . date('Y-m-d_H-i-s');
            if (copy($module['file_path'], $backup_path)) {
                if ($this->log) {
                    $this->log->write('GDT Module Manager: Created backup of system file: ' . $backup_path);
                }
            }
            
            // Удаляем оригинальный файл
            if (unlink($module['file_path'])) {
                if ($this->log) {
                    $this->log->write('GDT Module Manager: Removed system file: ' . $module['file_path']);
                }
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logError('GDT Module Manager: Error removing system file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаляет файлы модуля на основе JSON-конфигурации
     * 
     * @param array $module Данные модуля из JSON
     * @return bool
     */
    private function removeModuleFiles($module) {
        if (!defined('DIR_APPLICATION')) {
            throw new \Exception('DIR_APPLICATION не определена');
        }

        $deleted_files = [];
        $errors = [];

        // Удаляем файлы из массива files
        if (isset($module['files']) && is_array($module['files'])) {
            foreach ($module['files'] as $file) {
                $file_path = rtrim(dirname(constant('DIR_APPLICATION')), '/') . '/' . ltrim($file, '/');
                
                if (file_exists($file_path)) {
                    if (unlink($file_path)) {
                        $deleted_files[] = $file;
                        if ($this->log) {
                            $this->log->write('GDT Module Manager: Deleted file ' . $file);
                        }
                    } else {
                        $errors[] = 'Не удалось удалить файл: ' . $file;
                        if ($this->log) {
                            $this->log->write('GDT Module Manager error: Failed to delete file ' . $file);
                        }
                    }
                } else {
                    if ($this->log) {
                        $this->log->write('GDT Module Manager: File not found (already deleted?): ' . $file);
                    }
                }
            }
        }

        // Удаляем файлы из массива delete (если есть)
        if (isset($module['delete']) && is_array($module['delete'])) {
            foreach ($module['delete'] as $delete_item) {
                // Поддерживаем как строки, так и объекты с path
                $delete_path = is_array($delete_item) ? $delete_item['path'] : $delete_item;
                $file_path = rtrim(dirname(constant('DIR_APPLICATION')), '/') . '/' . ltrim($delete_path, '/');
                
                if (file_exists($file_path)) {
                    if (is_dir($file_path)) {
                        // Удаляем директорию
                        if ($this->removeDirectory($file_path)) {
                            $deleted_files[] = $delete_path . ' (directory)';
                            if ($this->log) {
                                $this->log->write('GDT Module Manager: Deleted directory ' . $delete_path);
                            }
                        } else {
                            $errors[] = 'Не удалось удалить директорию: ' . $delete_path;
                            if ($this->log) {
                                $this->log->write('GDT Module Manager error: Failed to delete directory ' . $delete_path);
                            }
                        }
                    } else {
                        // Удаляем файл
                        if (unlink($file_path)) {
                            $deleted_files[] = $delete_path;
                            if ($this->log) {
                                $this->log->write('GDT Module Manager: Deleted file ' . $delete_path);
                            }
                        } else {
                            $errors[] = 'Не удалось удалить файл: ' . $delete_path;
                            if ($this->log) {
                                $this->log->write('GDT Module Manager error: Failed to delete file ' . $delete_path);
                            }
                        }
                    }
                } else {
                    if ($this->log) {
                        $this->log->write('GDT Module Manager: File/directory not found (already deleted?): ' . $delete_path);
                    }
                }
            }
        }

        // Очищаем пустые директории
        $this->cleanupEmptyDirectories($deleted_files);

        if (!empty($errors)) {
            throw new \Exception(implode('; ', $errors));
        }

        return true;
    }

    /**
     * Удаляет конфигурационный файл модуля
     * 
     * @param string $module_code Код модуля
     * @return bool
     */
    private function removeModuleConfig($module_code) {
        if (!defined('DIR_SYSTEM')) {
            return false;
        }

        $config_path = constant('DIR_SYSTEM') . 'modules/' . $module_code . '/opencart-module.json';
        
        if (file_exists($config_path)) {
            if (unlink($config_path)) {
                if ($this->log) {
                    $this->log->write('GDT Module Manager: Removed config file for module ' . $module_code);
                }
                
                // Удаляем директорию модуля, если она пустая
                $module_dir = dirname($config_path);
                if (is_dir($module_dir) && count(scandir($module_dir)) == 2) { // только . и ..
                    rmdir($module_dir);
                    if ($this->log) {
                        $this->log->write('GDT Module Manager: Removed empty module directory ' . $module_dir);
                    }
                }
                
                return true;
            }
        }

        return false;
    }

    /**
     * Очищает пустые директории после удаления файлов
     * 
     * @param array $deleted_files Список удаленных файлов
     * @return void
     */
    private function cleanupEmptyDirectories($deleted_files) {
        if (!defined('DIR_APPLICATION')) {
            return;
        }

        $base_path = rtrim(dirname(constant('DIR_APPLICATION')), '/');
        $directories_to_check = [];

        // Собираем список директорий для проверки
        foreach ($deleted_files as $file) {
            $dir = dirname($base_path . '/' . ltrim($file, '/'));
            while ($dir !== $base_path && !in_array($dir, $directories_to_check)) {
                $directories_to_check[] = $dir;
                $dir = dirname($dir);
            }
        }

        // Сортируем по глубине (самые глубокие первыми)
        usort($directories_to_check, function($a, $b) {
            return substr_count($b, '/') - substr_count($a, '/');
        });

        // Удаляем пустые директории
        foreach ($directories_to_check as $dir) {
            if (is_dir($dir) && $this->isDirectoryEmpty($dir)) {
                if (rmdir($dir)) {
                    if ($this->log) {
                        $this->log->write('GDT Module Manager: Removed empty directory ' . $dir);
                    }
                }
            }
        }
    }

    /**
     * Проверяет, пуста ли директория
     * 
     * @param string $dir Путь к директории
     * @return bool
     */
    private function isDirectoryEmpty($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
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
                $this->log->write('GDT Module Manager response: ' . $response);
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
    
    /**
     * Создает таблицу для хранения информации о модулях
     * DEPRECATED: Таблица gdt_modules устарела и будет удалена в будущих версиях
     * Сохраняется только для обратной совместимости со старыми модулями
     */
    public function createModulesTable() {
        try {
            $db = $this->registry->get('db');
            $query = "CREATE TABLE IF NOT EXISTS `gdt_modules` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `module_code` VARCHAR(255) NOT NULL,
                `version` VARCHAR(50) NOT NULL,
                `data` JSON NOT NULL,
                UNIQUE KEY `module_code` (`module_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='DEPRECATED: Use OpenCart modifications instead';";

            $db->query($query);
            
            if ($this->log) {
                $this->log->write('GDT Module Manager: Created deprecated gdt_modules table for backward compatibility');
            }
        } catch (\Exception $e) {
            if ($this->log) {
                $this->log->write('GDT Module Manager: Failed to create gdt_modules table: ' . $e->getMessage());
            }
        }
    }

    /**
     * Сохраняет информацию о модуле в базу данных
     * DEPRECATED: Метод сохраняется для обратной совместимости
     * Новые модули должны использовать OpenCart modifications
     * 
     * @param string $module_code Код модуля
     * @param string $version Версия модуля
     * @param array $data Дополнительные данные модуля
     * @return bool
     */
public function saveModuleToDatabase($module_code, $version, $data) {
    try {
        $db = $this->registry->get('db');
        
        // Проверяем, существует ли таблица gdt_modules
        $table_check = "SHOW TABLES LIKE 'gdt_modules'";
        $table_result = $db->query($table_check);
        
        if ($table_result->num_rows == 0) {
            // Таблица не существует, создаем для обратной совместимости
            $this->createModulesTable();
        }
        
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);

        // Проверяем, существует ли модуль с таким кодом
        $query_check = "SELECT COUNT(*) as count FROM `gdt_modules` WHERE `module_code` = '" . $db->escape($module_code) . "';";
        $result = $db->query($query_check);

        if ($result->row['count'] > 0) {
            // Если модуль существует, обновляем его данные
            $query_update = "UPDATE `gdt_modules` 
                             SET `version` = '" . $db->escape($version) . "', 
                                 `data` = '" . $db->escape($json_data) . "' 
                             WHERE `module_code` = '" . $db->escape($module_code) . "';";
            $db->query($query_update);
        } else {
            // Если модуля нет, добавляем его
            $query_insert = "INSERT INTO `gdt_modules` (`module_code`, `version`, `data`) 
                             VALUES ('" . $db->escape($module_code) . "', 
                                     '" . $db->escape($version) . "', 
                                     '" . $db->escape($json_data) . "');";
            $db->query($query_insert);
        }
        
        if ($this->log) {
            $this->log->write('GDT Module Manager: Module data saved to deprecated gdt_modules table for backward compatibility: ' . $module_code);
        }
        
        return true;
    } catch (\Exception $e) {
        if ($this->log) {
            $this->log->write('GDT Module Manager: Failed to save to gdt_modules (deprecated): ' . $e->getMessage());
        }
        // Не выбрасываем исключение, просто возвращаем false для обратной совместимости
        return false;
    }
}

    /**
     * Обновляет версию модуля в базе данных после успешного обновления
     * DEPRECATED: Метод сохраняется для обратной совместимости
     * Обновления теперь происходят через OpenCart modifications
     * 
     * @param string $module_code Код модуля
     * @param string $new_version Новая версия
     * @param array $update_info Информация об обновлении
     * @return bool
     */
    public function updateModuleVersion($module_code, $new_version, $update_info = array()) {
        // Получаем текущие данные модуля из любого источника (приоритет OpenCart modifications)
        $module_data = $this->getModuleByCode($module_code);
        
        if (!$module_data) {
            // Если модуль не найден, создаем минимальные данные
            $module_data = array(
                'code' => $module_code,
                'version' => $new_version,
                'name' => $update_info['name'] ?? $module_code,
                'description' => $update_info['description'] ?? '',
                'author' => $update_info['author'] ?? 'Unknown',
                'updated_at' => date('Y-m-d H:i:s')
            );
        } else {
            // Обновляем версию в существующих данных
            $module_data['version'] = $new_version;
            $module_data['updated_at'] = date('Y-m-d H:i:s');
            
            // Добавляем дополнительную информацию из update_info
            if (isset($update_info['name'])) {
                $module_data['name'] = $update_info['name'];
            }
            if (isset($update_info['description'])) {
                $module_data['description'] = $update_info['description'];
            }
            if (isset($update_info['author'])) {
                $module_data['author'] = $update_info['author'];
            }
        }
        
        // Сохраняем в базу данных (deprecated, но для обратной совместимости)
        $this->saveModuleToDatabase($module_code, $new_version, $module_data);
        
        if ($this->log) {
            $this->log->write('GDT Module Manager: Module version updated to ' . $new_version . ' for ' . $module_code);
        }
        
        return true;
    }

    /**
     * Получает информацию о модуле из базы данных
     * DEPRECATED: Используется только для обратной совместимости
     * 
     * @param string $module_code Код модуля
     * @return array|null
     */
    public function getModuleFromDatabase($module_code) {
        try {
            $db = $this->registry->get('db');
            
            // Проверяем существование таблицы
            $table_check = "SHOW TABLES LIKE 'gdt_modules'";
            $table_result = $db->query($table_check);
            
            if ($table_result->num_rows == 0) {
                return null; // Таблица не существует
            }
            
            $query = "SELECT * FROM `gdt_modules` WHERE `module_code` = '" . $db->escape($module_code) . "' LIMIT 1;";
            $result = $db->query($query);

            if ($result->num_rows > 0) {
                $json_data = json_decode($result->row['data'], true);
                if(!isset($json_data['code'])) {
                    $json_data['code'] = $module_code; // Добавляем код модуля
                }
                $json_data['source'] = 'gdt_modules_deprecated';
                return $json_data;
            }

            return null;
        } catch (\Exception $e) {
            if ($this->log) {
                $this->log->write('GDT Module Manager: Error reading from deprecated gdt_modules table: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Удаляет информацию о модуле из базы данных
     * 
     * @param string $module_code Код модуля
     * @return bool
     */
    public function deleteModuleFromDatabase($module_code) {
        $db = $this->registry->get('db');
        $query = "DELETE FROM `gdt_modules` WHERE `module_code` = '" . $db->escape($module_code) . "';";
        return $db->query($query);
    }

    public function getJson(){
        $file = constant('DIR_SYSTEM') . 'modules/gdt_updater/opencart-module.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            if ($json !== false) {
                $data = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE) {        
                    return $data;
                } else {
                    $this->logError('GDT Module Manager: JSON decode error - ' . json_last_error_msg());
                    return null;
                }
            } else {
                $this->logError('GDT Module Manager: Failed to read JSON file');
                return null;
            }
        }
    }

    /**
     * Получает модули из таблицы модификаторов OpenCart
     * 
     * @return array
     */
    private function getModulesFromOpenCartModifications() {
        $modules = [];
        $db = $this->registry->get('db');
        
        try {
            // Проверяем существование таблицы модификаторов
            $table_exists_query = "SHOW TABLES LIKE '" . DB_PREFIX . "modification'";
            $table_result = $db->query($table_exists_query);
            
            if ($table_result->num_rows > 0) {
                $query = "SELECT * FROM `" . DB_PREFIX . "modification` WHERE `status` = 1";
                $result = $db->query($query);
                
                if ($result->num_rows > 0) {
                    foreach ($result->rows as $row) {
                        $module_data = [
                            'code' => $row['code'],
                            'name' => $row['name'],
                            'version' => isset($row['version']) ? $row['version'] : '1.0.0',
                            'author' => isset($row['author']) ? $row['author'] : 'Unknown',
                            'link' => isset($row['link']) ? $row['link'] : '',
                            'description' => isset($row['description']) ? $row['description'] : '',
                            'source' => 'opencart_modification',
                            'modification_id' => $row['modification_id'],
                            'date_added' => isset($row['date_added']) ? $row['date_added'] : '',
                        ];
                        $modules[] = $module_data;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logError('GDT Module Manager: Error getting OpenCart modifications: ' . $e->getMessage());
        }
        
        return $modules;
    }

    /**
     * Получает модули из системных файлов .ocmod.xml
     * 
     * @return array
     */
    private function getModulesFromSystemFiles() {
        $modules = [];
        
        try {
            $system_dir = constant('DIR_SYSTEM');
            $pattern = $system_dir . '*.ocmod.xml';
            $files = glob($pattern);
            
            foreach ($files as $file) {
                $module_data = $this->parseOcmodFile($file);
                if ($module_data) {
                    $modules[] = $module_data;
                }
            }
        } catch (\Exception $e) {
            $this->logError('GDT Module Manager: Error getting system modules: ' . $e->getMessage());
        }
        
        return $modules;
    }

    /**
     * Парсит файл .ocmod.xml и извлекает информацию о модуле
     * 
     * @param string $file_path Путь к файлу .ocmod.xml
     * @return array|null
     */
    private function parseOcmodFile($file_path) {
        try {
            if (!file_exists($file_path)) {
                return null;
            }
            
            $xml_content = file_get_contents($file_path);
            if ($xml_content === false) {
                return null;
            }
            
            // Отключаем вывод ошибок XML для избежания предупреждений
            $old_setting = libxml_use_internal_errors(true);
            libxml_clear_errors();
            
            $xml = simplexml_load_string($xml_content);
            
            // Восстанавливаем настройки обработки ошибок
            libxml_use_internal_errors($old_setting);
            
            if ($xml === false) {
                $this->logError('GDT Module Manager: Failed to parse XML file: ' . $file_path);
                return null;
            }
            
            $module_data = [
                'code' => (string)$xml->code,
                'name' => (string)$xml->name,
                'version' => isset($xml->version) ? (string)$xml->version : '1.0.0',
                'author' => isset($xml->author) ? (string)$xml->author : 'Unknown',
                'link' => isset($xml->link) ? (string)$xml->link : '',
                'description' => isset($xml->description) ? (string)$xml->description : '',
                'source' => 'system_file',
                'file_path' => $file_path,
                'file_name' => basename($file_path),
            ];
            
            // Проверяем обязательные поля
            if (empty($module_data['code']) || empty($module_data['name'])) {
                $this->logError('GDT Module Manager: Invalid module data in file: ' . $file_path);
                return null;
            }
            
            return $module_data;
            
        } catch (\Exception $e) {
            $this->logError('GDT Module Manager: Error parsing ocmod file ' . $file_path . ': ' . $e->getMessage());
            return null;
        }
    }
}
