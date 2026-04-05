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
     * @return bool|string true при успіху, рядок з помилкою при невдачі
     */
    public function installModule($zip_file_path, $module_code = '') {
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
            $this->saveModuleToDatabase($extract_dir, $module_code, $installed_paths);

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
     * @return void
     */
    private function saveModuleToDatabase($extract_dir, $module_code, $paths = []) {
        try {
            // Шукаємо opencart-module.json у розпакованому архіві
            $json_source = $extract_dir . '/opencart-module.json';
            if (!file_exists($json_source)) {
                LoggerService::info('No opencart-module.json found in module package', 'InstallService');
                return;
            }
            
            $json_content = file_get_contents($json_source);
            $module_data = json_decode($json_content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                LoggerService::error('Invalid JSON in opencart-module.json: ' . json_last_error_msg(), 'InstallService');
                return;
            }
            
            $code = $module_data['code'] ?? $module_code;
            if (empty($code)) {
                LoggerService::info('Module code not found in JSON', 'InstallService');
                return;
            }
            
            // Записуємо в базу даних
            $name = $module_data['module_name'] ?? $module_data['name'] ?? $code;
            $version = $module_data['version'] ?? '1.0.0';
            
            // Видаляємо стару інформацію про цей модуль якщо є
            $this->db->query("DELETE FROM `" . DB_PREFIX . "gdt_modules` WHERE `code` = '" . $this->db->escape($code) . "'");
            
            // Вставляємо нову
            $this->db->query("INSERT INTO `" . DB_PREFIX . "gdt_modules` SET 
                `code` = '" . $this->db->escape($code) . "',
                `name` = '" . $this->db->escape($name) . "',
                `version` = '" . $this->db->escape($version) . "',
                `data` = '" . $this->db->escape(json_encode($module_data, JSON_UNESCAPED_UNICODE)) . "',
                `paths` = '" . $this->db->escape(json_encode($paths, JSON_UNESCAPED_UNICODE)) . "',
                `date_added` = NOW()");
            
            LoggerService::info('Saved module ' . $code . ' to database', 'InstallService');
            
        } catch (\Exception $e) {
            LoggerService::error('Error saving module to database: ' . $e->getMessage(), 'InstallService');
        }
    }
    
    private function getXmlValue($dom, $tagName, $default = '') {
        $node = $dom->getElementsByTagName($tagName)->item(0);
        return $node ? $node->nodeValue : $default;
    }
}
