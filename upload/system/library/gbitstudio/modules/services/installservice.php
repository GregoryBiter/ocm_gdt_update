<?php
namespace Gbitstudio\Modules\Services;

use \Gbitstudio\Modules\Services\LoggerService;
/**
 * Сервіс для установки модулів
 * Реалізує логіку встановлення як у OpenCart admin/controller/marketplace/install.php
 */
class InstallService {
    private $registry;
    private $db;
    
    public function __construct(\Registry $registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
    }
    
    /**
     * Встановлює модуль з ZIP файлу
     * 
     * @param string $zip_file_path Шлях до ZIP файлу
     * @param string $module_code Код модуля
     * @param array $server_module_info Метаданные модуля с сервера обновлений
     * @return bool|string true при успіху, рядок з помилкою при невдачі
     */
    public function installModule($zip_file_path, $module_code = '', $server_module_info = array()) {
        LoggerService::info('Starting installation for ' . $module_code, 'InstallService');

        try {
            if (!file_exists($zip_file_path)) {
                throw new \Exception('Module file not found: ' . $zip_file_path);
            }

            $install_token = substr(md5(uniqid(rand(), true)), 0, 10);
            $upload_dir = $this->getUploadDirectory();
            $temp_file = $upload_dir . $install_token . '.tmp';
            
            if (!copy($zip_file_path, $temp_file)) {
                throw new \Exception('Failed to copy file to upload directory');
            }

            $this->registry->get('load')->model('setting/extension');
            $model = $this->registry->get('model_setting_extension');
            
            $filename = !empty($module_code) ? $module_code . '.ocmod.zip' : basename($zip_file_path);
            $extension_install_id = $model->addExtensionInstall($filename);
            
                LoggerService::write('GDT Install Service: Created extension_install_id: ' . $extension_install_id);

            // Етап 1: Розпакування
            $extract_dir = $this->unzipModule($temp_file, $install_token, $upload_dir);
            if (!is_string($extract_dir)) {
                throw new \Exception('Unzip error: ' . $extract_dir);
            }

            // Етап 2: Переміщення файлів
            $move_result = $this->moveModuleFiles($extract_dir, $extension_install_id);
            if (!is_array($move_result)) {
                throw new \Exception('Move error: ' . $move_result);
            }
            
            $installed_paths = $move_result;

            // Етап 3: Обробка XML
            $xml_result = $this->processModuleXml($extract_dir, $extension_install_id);
            if ($xml_result !== true && $xml_result !== false) {
                throw new \Exception('XML processing error: ' . $xml_result);
            }
            
            // Етап 4: Збереження в базу даних
            $save_result = $this->saveModuleToDatabase($extract_dir, $module_code, $installed_paths, $server_module_info, $temp_file);
            if ($save_result !== true) {
                throw new \Exception((string)$save_result);
            }

            // Етап 5: Очищення
            $this->cleanupInstallation($extract_dir, $temp_file);

            LoggerService::info('Successfully installed module', 'InstallService');

            return true;
        } catch (\Exception $e) {
            $error = 'Installation error: ' . $e->getMessage();
                LoggerService::write('GDT Install Service error: ' . $error);
            return $error;
        }
    }
    
    /**
     * Розпаковує ZIP архів
     * 
     * @param string $temp_file
     * @param string $install_token
     * @param string $upload_dir
     * @return string|false
     */
    private function unzipModule($temp_file, $install_token, $upload_dir) {
        if (!file_exists($temp_file)) {
            return 'Module file not found';
        }

        $zip = new \ZipArchive();
        if ($zip->open($temp_file)) {
            $extract_dir = $upload_dir . 'tmp-' . $install_token;
            $zip->extractTo($extract_dir);
            $zip->close();
            
                LoggerService::write('GDT Install Service: Module extracted to ' . $extract_dir);
        } else {
            return 'Failed to open ZIP archive';
        }

        @unlink($temp_file);
        return $extract_dir;
    }
    
    /**
     * Переміщує файли модуля
     * Реалізація як в OpenCart marketplace/install::move()
     * 
     * @param string $extract_dir
     * @param int $extension_install_id
     * @return array|string Список переміщених файлів або помилка
     */
    private function moveModuleFiles($extract_dir, $extension_install_id) {
        $directory = $extract_dir . '/';
        $installed_paths = [];
        
        // Перевіряємо наявність папки upload/
        if (!is_dir($directory . 'upload/')) {
            LoggerService::info('No upload/ directory found', 'InstallService');
            return $installed_paths;
        }

        // Отримуємо список всіх файлів для завантаження
        $files = array();
        $path = array($directory . 'upload/*');

        while (count($path) != 0) {
            $next = array_shift($path);

            foreach ((array)glob($next) as $file) {
                if (is_dir($file)) {
                    $path[] = $file . '/*';
                }

                $files[] = $file;
            }
        }

        // Переміщуємо файли
        $this->registry->get('load')->model('setting/extension');
        $model = $this->registry->get('model_setting_extension');

        foreach ($files as $file) {
            $destination = str_replace('\\', '/', substr($file, strlen($directory . 'upload/')));

            $path = '';

            if (substr($destination, 0, 5) == 'admin') {
                $path = DIR_APPLICATION . substr($destination, 6);
            } elseif (substr($destination, 0, 7) == 'catalog') {
                $path = DIR_CATALOG . substr($destination, 8);
            } elseif (substr($destination, 0, 5) == 'image') {
                $path = DIR_IMAGE . substr($destination, 6);
            } elseif (substr($destination, 0, 6) == 'system') {
                $path = DIR_SYSTEM . substr($destination, 7);
            } else {
                // Файли в корені - визначаємо корінь через DIR_APPLICATION
                $root = rtrim(str_replace('admin/', '', rtrim(DIR_APPLICATION, '/')), '/') . '/';
                $path = $root . $destination;
            }

            if (is_dir($file) && !is_dir($path)) {
                if (mkdir($path, 0777)) {
                    $model->addExtensionPath($extension_install_id, $destination);
                    $installed_paths[] = $destination;
                        LoggerService::write('GDT Install Service: Created directory: ' . $destination);
                }
            }

            if (is_file($file)) {
                if (rename($file, $path)) {
                    $model->addExtensionPath($extension_install_id, $destination);
                    $installed_paths[] = $destination;
                        LoggerService::write('GDT Install Service: Moved file: ' . $destination);
                }
            }
        }

        return $installed_paths;
    }
    
    /**
     * Обробляє XML модифікації
     * 
     * @param string $extract_dir
     * @param int $extension_install_id
     * @return bool|string
     */
    private function processModuleXml($extract_dir, $extension_install_id) {
        $xml_file = $extract_dir . '/install.xml';

        if (!is_file($xml_file)) {
            return false;
        }

        $this->registry->get('load')->model('setting/modification');
        $model = $this->registry->get('model_setting_modification');
        
        $xml = file_get_contents($xml_file);
        if (!$xml) {
            return 'Failed to read XML file';
        }

        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadXml($xml);

            $code_node = $dom->getElementsByTagName('code')->item(0);
            if (!$code_node) {
                return 'XML does not contain required <code> element';
            }
            
            $code = $code_node->nodeValue;

            // Перевіряємо чи модифікація вже встановлена
            $modification_info = $model->getModificationByCode($code);
            if ($modification_info) {
                $model->deleteModification($modification_info['modification_id']);
            }

            $name = $this->getXmlValue($dom, 'name', '');
            $author = $this->getXmlValue($dom, 'author', '');
            $version = $this->getXmlValue($dom, 'version', '');
            $link = $this->getXmlValue($dom, 'link', '');

            $modification_data = [
                'extension_install_id' => $extension_install_id,
                'name' => $name,
                'code' => $code,
                'author' => $author,
                'version' => $version,
                'link' => $link,
                'xml' => $xml,
                'status' => 1
            ];

            $model->addModification($modification_data);
            
                LoggerService::write('GDT Install Service: Added modification ' . $name . ' (' . $code . ')');

            return true;
        } catch (\Exception $e) {
            return 'XML parsing error: ' . $e->getMessage();
        }
    }
    
    /**
     * Очищує тимчасові файли
     * 
     * @param string $extract_dir
     * @param string $temp_file
     */
    private function cleanupInstallation($extract_dir, $temp_file) {
        if (is_dir($extract_dir)) {
            $files = [];
            $path = [$extract_dir];

            while (count($path) != 0) {
                $next = array_shift($path);
                foreach (array_diff(scandir($next), ['.', '..']) as $file) {
                    $file = $next . '/' . $file;
                    if (is_dir($file)) {
                        $path[] = $file;
                    }
                    $files[] = $file;
                }
            }

            rsort($files);
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                } elseif (is_dir($file)) {
                    @rmdir($file);
                }
            }

            if (is_dir($extract_dir)) {
                @rmdir($extract_dir);
            }
        }

        if (is_file($temp_file)) {
            @unlink($temp_file);
        }
    }
    
    /**
     * Допоміжні методи
     */
    
    private function getUploadDirectory() {
        return defined('DIR_UPLOAD') ? constant('DIR_UPLOAD') : 
               (defined('DIR_STORAGE') ? constant('DIR_STORAGE') . 'upload/' : sys_get_temp_dir() . '/');
    }
    
    /**
     * Зберігає дані модуля в базу даних
     * 
     * @param string $extract_dir
     * @param string $module_code
     * @param array $paths
     * @param array $server_module_info
     * @param string $temp_file
     * @return bool|string
     */
    private function saveModuleToDatabase($extract_dir, $module_code, $paths = [], $server_module_info = array(), $temp_file = '') {
        try {
            $this->ensureCoreTables();

            $module_data = array();

            if (is_array($server_module_info)) {
                foreach ($server_module_info as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $module_data[$key] = $value;
                    }
                }
            }

            $code = $module_data['code'] ?? $module_code ?? '';
            if (empty($code)) {
                return 'Module code is required';
            }
            
            // Записуємо в базу даних
            $name = $module_data['module_name'] ?? $module_data['name'] ?? $code;
            $version = $this->resolveVersion($module_data, $extract_dir, $code, $server_module_info);
            $module_type = $module_data['type'] ?? 'module';

            $this->db->query("INSERT INTO `" . DB_PREFIX . "ocm_modules` SET 
                `code` = '" . $this->db->escape($code) . "',
                `name` = '" . $this->db->escape($name) . "',
                `type` = '" . $this->db->escape($module_type) . "',
                `installed_version` = '" . $this->db->escape($version) . "',
                `source` = 'gdt_updater',
                `metadata_json` = '" . $this->db->escape(json_encode($module_data, JSON_UNESCAPED_UNICODE)) . "',
                `status` = 1,
                `installed_at` = NOW(),
                `updated_at` = NOW()
                ON DUPLICATE KEY UPDATE
                `name` = VALUES(`name`),
                `type` = VALUES(`type`),
                `installed_version` = VALUES(`installed_version`),
                `source` = 'gdt_updater',
                `metadata_json` = VALUES(`metadata_json`),
                `status` = 1,
                `updated_at` = NOW()");

            $this->db->query("DELETE FROM `" . DB_PREFIX . "ocm_module_files` WHERE `module_code` = '" . $this->db->escape($code) . "'");

            foreach ($paths as $path) {
                $absolute_path = $this->getOpenCartPath($path);
                $file_hash = (is_file($absolute_path) && is_readable($absolute_path)) ? sha1_file($absolute_path) : '';

                $this->db->query("INSERT INTO `" . DB_PREFIX . "ocm_module_files` SET
                    `module_code` = '" . $this->db->escape($code) . "',
                    `file_path` = '" . $this->db->escape($path) . "',
                    `file_hash` = '" . $this->db->escape($file_hash ?: '') . "',
                    `installed_at` = NOW(),
                    `updated_at` = NOW(),
                    `removed_at` = NULL");
            }

            $install_xml_file = $extract_dir . '/install.xml';
            $index_hash = is_file($install_xml_file) ? sha1_file($install_xml_file) : '';
            $package_hash = is_file($temp_file) ? sha1_file($temp_file) : '';

            $this->db->query("INSERT INTO `" . DB_PREFIX . "ocm_module_versions` SET
                `module_code` = '" . $this->db->escape($code) . "',
                `version` = '" . $this->db->escape($version) . "',
                `package_hash` = '" . $this->db->escape($package_hash ?: '') . "',
                `index_hash` = '" . $this->db->escape($index_hash ?: '') . "',
                `changelog` = '',
                `source` = 'gdt_updater',
                `published_at` = NOW(),
                `applied_at` = NOW()");
            
            LoggerService::info('Saved module ' . $code . ' to database', 'InstallService');
            return true;
            
        } catch (\Exception $e) {
            LoggerService::error('Error saving module to database: ' . $e->getMessage(), 'InstallService');
            return 'Error saving module to database: ' . $e->getMessage();
        }
    }
    
    private function getXmlValue($dom, $tagName, $default = '') {
        $node = $dom->getElementsByTagName($tagName)->item(0);
        return $node ? $node->nodeValue : $default;
    }

    private function resolveVersion(array $module_data, $extract_dir, $code, $server_module_info = array()) {
        if (is_array($server_module_info) && !empty($server_module_info['version'])) {
            return (string)$server_module_info['version'];
        }

        $install_xml = $extract_dir . '/install.xml';
        if (is_file($install_xml)) {
            $content = file_get_contents($install_xml);
            if ($content) {
                $dom = new \DOMDocument('1.0', 'UTF-8');
                if (@$dom->loadXML($content)) {
                    $node = $dom->getElementsByTagName('version')->item(0);
                    if ($node && trim($node->nodeValue) !== '') {
                        return trim($node->nodeValue);
                    }
                }
            }
        }

        if (!empty($module_data['version'])) {
            return (string)$module_data['version'];
        }

        $query = $this->db->query("SELECT `version` FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $this->db->escape($code) . "' LIMIT 1");
        if ($query->num_rows && !empty($query->row['version'])) {
            return (string)$query->row['version'];
        }

        return '0.0.0';
    }

    private function getOpenCartPath($relative_path) {
        $destination = str_replace('\\', '/', ltrim($relative_path, '/'));

        if (substr($destination, 0, 5) == 'admin') {
            return DIR_APPLICATION . substr($destination, 6);
        }
        if (substr($destination, 0, 7) == 'catalog') {
            return DIR_CATALOG . substr($destination, 8);
        }
        if (substr($destination, 0, 5) == 'image') {
            return DIR_IMAGE . substr($destination, 6);
        }
        if (substr($destination, 0, 6) == 'system') {
            return DIR_SYSTEM . substr($destination, 7);
        }

        $root = rtrim(str_replace('admin/', '', rtrim(DIR_APPLICATION, '/')), '/') . '/';
        return $root . $destination;
    }

    private function ensureCoreTables() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_modules` (
                `module_id` INT(11) NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(64) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `type` VARCHAR(32) NOT NULL DEFAULT 'module',
                `installed_version` VARCHAR(32) NOT NULL DEFAULT '0.0.0',
                `source` VARCHAR(32) NOT NULL DEFAULT 'gdt_updater',
                `metadata_json` MEDIUMTEXT NOT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `installed_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`module_id`),
                UNIQUE KEY `code` (`code`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_module_files` (
                `file_id` INT(11) NOT NULL AUTO_INCREMENT,
                `module_code` VARCHAR(64) NOT NULL,
                `file_path` VARCHAR(500) NOT NULL,
                `file_hash` VARCHAR(64) NOT NULL DEFAULT '',
                `installed_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `removed_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`file_id`),
                KEY `module_code` (`module_code`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_module_versions` (
                `version_id` INT(11) NOT NULL AUTO_INCREMENT,
                `module_code` VARCHAR(64) NOT NULL,
                `version` VARCHAR(32) NOT NULL,
                `package_hash` VARCHAR(64) NOT NULL DEFAULT '',
                `index_hash` VARCHAR(64) NOT NULL DEFAULT '',
                `changelog` TEXT NOT NULL,
                `source` VARCHAR(32) NOT NULL DEFAULT 'gdt_updater',
                `published_at` DATETIME NOT NULL,
                `applied_at` DATETIME NOT NULL,
                PRIMARY KEY (`version_id`),
                KEY `module_code` (`module_code`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");
    }
}
