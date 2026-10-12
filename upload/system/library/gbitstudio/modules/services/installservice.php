<?php

namespace Gbitstudio\Modules\Services;

use Gbitstudio\Modules\Services\LoggerService;

/**
 * Сервіс для установки модулів
 * Реалізує логіку встановлення як у OpenCart admin/controller/marketplace/install.php
 */
class InstallService
{
    private $registry;
    private $db;
    private $moduleRegistry;
    private $pendingModule;
    private $packageMetadata;

    public function __construct(\Registry $registry, $moduleRegistry = null)
    {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->moduleRegistry = $moduleRegistry ?: new ModuleRegistryService($registry);
    }

    /**
     * Встановлює модуль з ZIP файлу
     *
     * @param string $zip_file_path Шлях до ZIP файлу
     * @param string $module_code Код модуля
     * @param array $server_module_info Метаданные модуля с сервера обновлений
     * @return bool|string true при успіху, рядок з помилкою при невдачі
     */
    public function installModule($zip_file_path, $module_code = '', $server_module_info = array())
    {
        LoggerService::info('Starting installation for ' . $module_code, 'InstallService');
        $backup_dir = null;
        $this->pendingModule = null;
        $maintenanceStarted = false;
        $extract_dir = null;
        $temp_file = null;
        $extension_install_id = null;
        $newInstallation = false;
        $installationLock = null;
        $extract_base = null;

        try {
            $this->moduleRegistry->assertWritable();
            $installationLock = fopen($this->getUploadDirectory() . '.gdt_install.lock', 'c');
            if (!$installationLock || !flock($installationLock, LOCK_EX | LOCK_NB)) {
                throw new \RuntimeException('Another module installation is already running');
            }
            $this->setMaintenanceMode(true);
            $maintenanceStarted = true;
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

            // Read identity and version before moving upload/ or running package scripts.
            $extract_dir = $this->unzipModule($temp_file, $install_token, $upload_dir);
            $extract_base = $extract_dir;
            $extract_dir = $this->packageRoot($extract_dir);
            $this->packageMetadata = $this->readPackageMetadata($extract_dir, $module_code, $server_module_info);
            $module_code = $this->packageMetadata['code'];
            $registration = $this->findExtensionInstalls($this->packageMetadata, $extract_dir);
            $existing = $this->moduleRegistry->getModuleByCode($module_code);
            if ($existing === null) {
                foreach ($this->packageMetadata['aliases'] as $alias) {
                    $existing = $this->moduleRegistry->getModuleByCode($alias);
                    if ($existing !== null) {
                        break;
                    }
                }
            }
            if ($existing === null && !empty($this->packageMetadata['controller'])) {
                $table = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "ocm_module_files'");
                if ($table->num_rows > 0) {
                    $controllerPath = 'admin/controller/' . $this->packageMetadata['controller'] . '.php';
                    $owners = $this->db->query("SELECT DISTINCT `module_code` FROM `" . DB_PREFIX . "ocm_module_files`
                        WHERE `file_path` = '" . $this->db->escape($controllerPath) . "' AND `removed_at` IS NULL");
                    if (count($owners->rows) > 1) {
                        throw new \RuntimeException('Several registered modules own the package controller; resolve the registry conflict first');
                    }
                    if ($owners->rows) {
                        $existing = $this->moduleRegistry->getModuleByCode($owners->rows[0]['module_code']);
                    }
                }
            }
            $isUpdate = $existing !== null || !empty($registration);
            if ($existing !== null) {
                $module_code = $existing['code'];
                $this->packageMetadata['code'] = $module_code;
                $this->packageMetadata['aliases'] = array_values(array_unique(array_merge(
                    $existing['aliases'] ?? [],
                    $this->packageMetadata['aliases']
                )));
            }
            $newInstallation = empty($registration);
            $extension_install_id = $newInstallation
                ? $model->addExtensionInstall($module_code . '.ocmod.zip')
                : (int)$registration[0]['extension_install_id'];
            $this->packageMetadata['extension_install_id'] = $extension_install_id;
            $this->packageMetadata['_package_hash'] = sha1_file($zip_file_path);
            LoggerService::info('Using extension_install_id: ' . $extension_install_id, 'InstallService');

            // Етап 2: Створення тимчасового бэкапу існуючих файлів для відкату (Rollback)
            $backup_dir = $this->backupExistingFiles($extract_dir, $module_code ?: basename($zip_file_path));

            // Етап 3: Переміщення файлів
            $move_result = $this->moveModuleFiles($extract_dir, $extension_install_id);
            if (!is_array($move_result)) {
                throw new \Exception('Move error: ' . $move_result);
            }

            $installed_paths = $move_result;
            if ($registration) {
                $ids = array_map(function ($row) {
                    return (int)$row['extension_install_id'];
                }, $registration);
                $previousPaths = $this->db->query("SELECT DISTINCT `path` FROM `" . DB_PREFIX . "extension_path` WHERE `extension_install_id` IN (" . implode(',', $ids) . ")");
                foreach ($previousPaths->rows as $row) {
                    $installed_paths[] = $row['path'];
                }
                $installed_paths = array_values(array_unique($installed_paths));
            }
            if ($existing !== null && ($existing['source'] ?? '') === 'legacy_gdt') {
                $legacy = $this->db->query("SELECT `paths` FROM `" . DB_PREFIX . "gdt_modules` WHERE `code` = '" . $this->db->escape($module_code) . "' LIMIT 1");
                $installed_paths = array_values(array_unique(array_merge($installed_paths, json_decode($legacy->row['paths'] ?? '[]', true) ?: [])));
            }

            // Етап 4: Обробка XML
            $xml_result = $this->processModuleXml($extract_dir, $extension_install_id);
            if ($xml_result !== true && $xml_result !== false) {
                throw new \Exception('XML processing error: ' . $xml_result);
            }

            // Етап 5: Запуск скрипта оновлення/встановлення (upgrade.php / install.php)
            if ($existing === null || !version_compare($existing['version'], $this->packageMetadata['version'], '==')) {
                $this->executeModuleScript($extract_dir, $module_code, $isUpdate);
            }

            // Етап 6: Збереження в базу даних
            $save_result = $this->saveModuleToDatabase($extract_dir, $module_code, $installed_paths, $server_module_info, $temp_file);
            if ($save_result !== true) {
                throw new \Exception((string)$save_result);
            }

            // Етап 7: Автоматичне оновлення кэшу модифікаторів OpenCart (OCMOD Refresh)
            $this->refreshModifications();

            // Етап 8: Очищення тимчасових файлів
            $this->cleanupInstallation($extract_dir, $temp_file);

            $this->consolidateExtensionInstalls($extension_install_id, $registration, $installed_paths);
            $this->moduleRegistry->upsert($this->pendingModule);
            LoggerService::info('Successfully installed module ' . $module_code, 'InstallService');

            return true;
        } catch (\Throwable $e) {
            if ($backup_dir) {
                $this->restoreBackup($backup_dir);
            }
            if ($newInstallation && $extension_install_id !== null) {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "extension_path` WHERE `extension_install_id` = " . (int)$extension_install_id);
                $model->deleteExtensionInstall($extension_install_id);
            }
            $error = 'Installation error: ' . $e->getMessage();
            LoggerService::write('GDT Install Service error: ' . $error);
            return $error;
        } finally {
            if ($extract_base !== null || $extract_dir !== null) {
                $this->cleanupInstallation($extract_base ?: $extract_dir, $temp_file ?: '');
            } elseif ($temp_file && is_file($temp_file)) {
                unlink($temp_file);
            }
            if ($maintenanceStarted) {
                $this->setMaintenanceMode(false);
            }
            if (is_resource($installationLock)) {
                flock($installationLock, LOCK_UN);
                fclose($installationLock);
            }
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
    private function unzipModule($temp_file, $install_token, $upload_dir)
    {
        if (!file_exists($temp_file)) {
            throw new \RuntimeException('Module file not found');
        }

        $zip = new \ZipArchive();
        if ($zip->open($temp_file) === true) {
            $extract_dir = $upload_dir . 'tmp-' . $install_token;
            if (!$zip->extractTo($extract_dir)) {
                $zip->close();
                throw new \RuntimeException('Cannot extract module archive');
            }
            $zip->close();

                LoggerService::write('GDT Install Service: Module extracted to ' . $extract_dir);
        } else {
            throw new \RuntimeException('Failed to open ZIP archive');
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
    private function moveModuleFiles($extract_dir, $extension_install_id)
    {
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

            // Path Traversal Security Check
            if (strpos($destination, '..') !== false || strpos($destination, './') === 0 || strpos($destination, '/') === 0) {
                LoggerService::error('Security risk: Invalid file path detected: ' . $destination, 'InstallService');
                return 'Invalid file path in archive: ' . $destination;
            }

            $path = $this->getDestinationPath($destination);

            if (is_dir($file) && !is_dir($path)) {
                if (!$path || !mkdir($path, 0777, true)) {
                    throw new \RuntimeException('Cannot create module directory: ' . $destination);
                }
                {
                    $installed_paths[] = $destination;
                        LoggerService::write('GDT Install Service: Created directory: ' . $destination);
                }
            }

            if (is_file($file)) {
                if (!$path || !rename($file, $path)) {
                    throw new \RuntimeException('Cannot install module file: ' . $destination);
                }
                {
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
    private function processModuleXml($extract_dir, $extension_install_id)
    {
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

            $code = trim($code_node->nodeValue);
            if ($code === '') {
                return 'XML modification code cannot be empty';
            }

            // Перевіряємо чи модифікація вже встановлена
            $modification_info = $model->getModificationByCode($code);
            if ($modification_info) {
                // Old databases may already contain several rows with this code.
                $this->db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $this->db->escape($code) . "'");
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
                'status' => $modification_info['status'] ?? 1
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
    private function cleanupInstallation($extract_dir, $temp_file)
    {
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

    private function getUploadDirectory()
    {
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
    private function saveModuleToDatabase($extract_dir, $module_code, $paths = [], $server_module_info = array(), $temp_file = '')
    {
        try {
            $this->ensureCoreTables();

            $module_data = $this->packageMetadata;
            $code = $module_data['code'];
            $version = $module_data['version'];
            $name = $module_data['module_name'] ?? $module_data['name'] ?? $code;
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

            $previousPaths = $this->db->query("SELECT `file_path` FROM `" . DB_PREFIX . "ocm_module_files` WHERE `module_code` = '" . $this->db->escape($code) . "' AND `removed_at` IS NULL");
            foreach ($previousPaths->rows as $row) {
                $paths[] = $row['file_path'];
            }
            $paths = array_values(array_unique($paths));
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
            $package_hash = $module_data['_package_hash'] ?? '';
            unset($module_data['_package_hash']);

            $this->db->query("INSERT INTO `" . DB_PREFIX . "ocm_module_versions` SET
                `module_code` = '" . $this->db->escape($code) . "',
                `version` = '" . $this->db->escape($version) . "',
                `package_hash` = '" . $this->db->escape($package_hash ?: '') . "',
                `index_hash` = '" . $this->db->escape($index_hash ?: '') . "',
                `changelog` = '',
                `source` = 'gdt_updater',
                `published_at` = NOW(),
                `applied_at` = NOW()");

            $module_data['name'] = $name;
            $module_data['source'] = 'gdt_updater';
            $this->pendingModule = $module_data;
            LoggerService::info('Saved module ' . $code . ' to database', 'InstallService');
            return true;
        } catch (\Exception $e) {
            LoggerService::error('Error saving module to database: ' . $e->getMessage(), 'InstallService');
            return 'Error saving module to database: ' . $e->getMessage();
        }
    }

    /**
     * Видаляє модуль
     *
     * @param string $code
     * @return bool|string
     */
    public function uninstallModule($code)
    {
        LoggerService::info('Starting uninstallation for ' . $code, 'InstallService');

        try {
            $this->moduleRegistry->assertWritable();
            $registered = $this->moduleRegistry->getModuleByCode($code);
            if (!$registered) {
                throw new \RuntimeException('Module is not registered: ' . $code);
            }
            $code = $registered['code'];
            // 1. Отримуємо файли модуля з таблиці ocm_module_files або ocm_modules
            $paths = [];
            $files_query = $this->db->query("SELECT `file_path` FROM `" . DB_PREFIX . "ocm_module_files` WHERE `module_code` = '" . $this->db->escape($code) . "'");
            if ($files_query->num_rows > 0) {
                foreach ($files_query->rows as $r) {
                    $paths[] = $r['file_path'];
                }
            } else {
                // Fallback check legacy gdt_modules
                $table_check = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "gdt_modules'");
                if ($table_check->num_rows > 0) {
                    $legacy_query = $this->db->query("SELECT `paths` FROM `" . DB_PREFIX . "gdt_modules` WHERE `code` = '" . $this->db->escape($code) . "' LIMIT 1");
                    if ($legacy_query->num_rows > 0) {
                        $paths = json_decode($legacy_query->row['paths'], true) ?: [];
                    }
                }
            }

            // 2. Видаляємо файли
            if (is_array($paths)) {
                $files_to_delete = [];
                $dirs_to_delete = [];

                foreach ($paths as $path) {
                    $full_path = $this->getDestinationPath($path);
                    if ($full_path && file_exists($full_path)) {
                        if (is_dir($full_path)) {
                            $dirs_to_delete[] = $full_path;
                        } else {
                            $files_to_delete[] = $full_path;
                        }
                    }
                }

                // Спочатку видаляємо файли
                foreach ($files_to_delete as $file) {
                    if (is_file($file)) {
                        if (!unlink($file)) {
                            throw new \RuntimeException('Cannot delete module file: ' . $file);
                        }
                        LoggerService::write('GDT Install Service: Deleted file: ' . $file);
                    }
                }

                // Потім видаляємо папки (в зворотному порядку, щоб видалити підпапки першими)
                rsort($dirs_to_delete);
                foreach ($dirs_to_delete as $dir) {
                    if (is_dir($dir)) {
                        $items = @scandir($dir);
                        if ($items !== false) {
                            $items = array_diff($items, array('.', '..'));
                            if (empty($items)) {
                                @rmdir($dir);
                                LoggerService::write('GDT Install Service: Removed empty directory: ' . $dir);
                            }
                        }
                    }
                }
            }

            // 3. Видаляємо модифікацію (якщо є)
            $this->registry->get('load')->model('setting/modification');
            $modification_info = $this->registry->get('model_setting_modification')->getModificationByCode(($registered['modification_code'] ?? '') ?: $code);
            if ($modification_info) {
                $this->registry->get('model_setting_modification')->deleteModification($modification_info['modification_id']);
                LoggerService::write('GDT Install Service: Deleted modification: ' . $code);
            }

            // 4. Видаляємо інформацію про встановлення з бази OC
            $this->registry->get('load')->model('setting/extension');
            $this->db->query("DELETE FROM " . DB_PREFIX . "extension_install WHERE filename LIKE '" . $this->db->escape($code) . ".ocmod.zip'");
            if (!empty($registered['extension_install_id'])) {
                $id = (int)$registered['extension_install_id'];
                $this->db->query("DELETE FROM `" . DB_PREFIX . "extension_path` WHERE `extension_install_id` = " . $id);
                $this->registry->get('model_setting_extension')->deleteExtensionInstall($id);
            }

            // 5. Видаляємо записи з OCM таблиць
            $this->db->query("DELETE FROM `" . DB_PREFIX . "ocm_modules` WHERE `code` = '" . $this->db->escape($code) . "'");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "ocm_module_files` WHERE `module_code` = '" . $this->db->escape($code) . "'");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "ocm_module_versions` WHERE `module_code` = '" . $this->db->escape($code) . "'");

            // 6. Оновлюємо модифікатори OpenCart (OCMOD Refresh)
            $this->refreshModifications();

            $this->removeAutoUpdateSetting($code);
            $this->moduleRegistry->remove($code);
            LoggerService::info('Successfully uninstalled module ' . $code, 'InstallService');

            return true;
        } catch (\Exception $e) {
            $error_msg = 'Uninstallation error for module ' . $code . ': ' . $e->getMessage();
            LoggerService::error($error_msg, 'InstallService');
            return $error_msg;
        }
    }

    /**
     * Масове видалення модулів
     *
     * @param array $codes
     * @return array
     */
    public function deleteMultipleModules(array $codes)
    {
        $results = ['success' => [], 'errors' => []];
        foreach ($codes as $code) {
            $result = $this->uninstallModule($code);
            if ($result === true) {
                $results['success'][] = $code;
            } else {
                $results['errors'][] = "Failed to delete $code: " . (is_string($result) ? $result : 'Unknown error');
            }
        }
        return $results;
    }

    private function getDestinationPath($path)
    {
        $full_path = '';

        if (substr($path, 0, 5) == 'admin') {
            $full_path = DIR_APPLICATION . substr($path, 6);
        } elseif (substr($path, 0, 7) == 'catalog') {
            $full_path = DIR_CATALOG . substr($path, 8);
        } elseif (substr($path, 0, 5) == 'image') {
            $full_path = DIR_IMAGE . substr($path, 6);
        } elseif (substr($path, 0, 6) == 'system') {
            $full_path = DIR_SYSTEM . substr($path, 7);
        } else {
            // Файли в корені - визначаємо корінь через DIR_APPLICATION
            $root = dirname(rtrim(DIR_APPLICATION, '/')) . '/';
            $full_path = $root . $path;
        }

        return $full_path;
    }

    private function getXmlValue($dom, $tagName, $default = '')
    {
        $node = $dom->getElementsByTagName($tagName)->item(0);
        return $node ? $node->nodeValue : $default;
    }

    private function removeAutoUpdateSetting($code)
    {
        $config = $this->registry->get('config');
        $auto = $config->get('module_gdt_updater_auto_modules') ?: [];
        $auto = array_values(array_diff($auto, [$code]));
        $this->registry->get('load')->model('setting/setting');
        $this->registry->get('model_setting_setting')->editSettingValue(
            'module_gdt_updater',
            'module_gdt_updater_auto_modules',
            $auto
        );
        $config->set('module_gdt_updater_auto_modules', $auto);
    }

    private function packageRoot($directory)
    {
        if (
            is_dir($directory . '/upload') || is_file($directory . '/install.xml')
            || is_file($directory . '/opencart-module.json') || is_file($directory . '/install.php')
            || is_file($directory . '/upgrade.php')
        ) {
            return $directory;
        }
        $entries = array_values(array_filter(glob($directory . '/*') ?: [], function ($path) {
            return basename($path) !== '__MACOSX';
        }));
        if (count($entries) === 1 && is_dir($entries[0])) {
            return $this->packageRoot($entries[0]);
        }
        throw new \RuntimeException('Archive does not contain an OpenCart module');
    }

    private function readJsonMetadata($path)
    {
        $contents = file_get_contents($path);
        $metadata = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $contents), true);
        if (!is_array($metadata) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid module metadata: ' . basename($path) . ' (' . json_last_error_msg() . ')');
        }
        return $metadata;
    }

    private function readPackageMetadata($directory, $requestedCode, array $serverMetadata)
    {
        // Package fields override server fields. Read upload/ metadata before rename().
        $metadata = $serverMetadata;
        foreach (['upload/install.json', 'install.json', 'upload/opencart-module.json', 'opencart-module.json'] as $file) {
            if (is_file($directory . '/' . $file)) {
                foreach ($this->readJsonMetadata($directory . '/' . $file) as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $metadata[$key] = $value;
                    }
                }
            }
        }
        $packageCode = $metadata['code'] ?? '';
        $code = $requestedCode ?: $packageCode;
        if (!is_string($code) || !preg_match('/^[a-zA-Z0-9_\-]+$/D', $code)) {
            throw new \RuntimeException('A valid module code is required');
        }
        $metadata['code'] = $code;
        $metadata['server_code'] = $requestedCode ?: $code;
        $metadata['aliases'] = array_values(array_unique(array_filter([$code, $packageCode], 'is_string')));
        if (is_file($directory . '/install.xml')) {
            $dom = new \DOMDocument();
            if (!@$dom->loadXML(file_get_contents($directory . '/install.xml'))) {
                throw new \RuntimeException('Invalid install.xml');
            }
            $metadata['modification_code'] = trim($this->getXmlValue($dom, 'code', ''));
        }
        $metadata['version'] = $this->resolveVersion($metadata, $directory, $code, $serverMetadata);
        LoggerService::info('Package ' . $code . ': installed version ' . $metadata['version'], 'InstallService');
        return $metadata;
    }

    private function findExtensionInstalls(array $metadata, $directory)
    {
        $ids = [];
        $codes = $metadata['aliases'] ?? [$metadata['code']];
        foreach ($codes as $code) {
            $registered = $this->moduleRegistry->getModuleByCode($code);
            if ($registered) {
                $codes = array_merge($codes, [$registered['code']], $registered['aliases'] ?? []);
                if (!empty($registered['extension_install_id'])) {
                    $ids[] = (int)$registered['extension_install_id'];
                }
            }
        }
        if (is_file($directory . '/install.xml')) {
            $dom = new \DOMDocument();
            if (@$dom->loadXML(file_get_contents($directory . '/install.xml'))) {
                $xmlCode = trim($this->getXmlValue($dom, 'code', ''));
                if ($xmlCode !== '') {
                    $this->registry->get('load')->model('setting/modification');
                    $modification = $this->registry->get('model_setting_modification')->getModificationByCode($xmlCode);
                    if (!empty($modification['extension_install_id'])) {
                        $ids[] = (int)$modification['extension_install_id'];
                    }
                    $codes[] = $xmlCode;
                }
            }
        }
        // A declared controller is stable even when an old archive filename differs from code.
        $controller = $metadata['controller'] ?? '';
        if (is_string($controller) && preg_match('~^[a-zA-Z0-9_/]+$~D', $controller)) {
            $controllerPath = 'admin/controller/' . $controller . '.php';
            if (is_file($directory . '/upload/' . $controllerPath) || ($directory === '' && is_file(DIR_APPLICATION . 'controller/' . $controller . '.php'))) {
                $result = $this->db->query("SELECT DISTINCT `extension_install_id` FROM `" . DB_PREFIX . "extension_path` WHERE `path` = '" . $this->db->escape($controllerPath) . "'");
                foreach ($result->rows as $row) {
                    $ids[] = (int)$row['extension_install_id'];
                }
            }
        }
        $matches = [];
        $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "extension_install` ORDER BY `extension_install_id` ASC");
        foreach ($result->rows as $row) {
            $matchesCode = false;
            foreach (array_unique($codes) as $code) {
                $pattern = '/^' . preg_quote($code, '/') . '(?:[-_]v?\d+(?:\.\d+)*(?:[-+][a-zA-Z0-9.-]+)?)?\.(?:ocmod\.)?zip$/iD';
                if (preg_match($pattern, basename($row['filename']))) {
                    $matchesCode = true;
                    break;
                }
            }
            if ($matchesCode || in_array((int)$row['extension_install_id'], $ids, true)) {
                $matches[] = $row;
            }
        }
        return $matches;
    }

    private function consolidateExtensionInstalls($keepId, array $registrations, array $paths)
    {
        $ids = array_unique(array_merge([(int)$keepId], array_map(function ($row) {
            return (int)$row['extension_install_id'];
        }, $registrations)));
        $idList = implode(',', $ids);
        $existing = $this->db->query("SELECT DISTINCT `path` FROM `" . DB_PREFIX . "extension_path` WHERE `extension_install_id` IN (" . $idList . ")");
        foreach ($existing->rows as $row) {
            $paths[] = $row['path'];
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "extension_path` WHERE `extension_install_id` IN (" . $idList . ")");
        $model = $this->registry->get('model_setting_extension');
        foreach (array_unique($paths) as $path) {
            $model->addExtensionPath($keepId, $path);
        }
        foreach ($ids as $id) {
            if ($id !== (int)$keepId) {
                $this->db->query("UPDATE `" . DB_PREFIX . "modification` SET `extension_install_id` = " . (int)$keepId . " WHERE `extension_install_id` = " . $id);
                $model->deleteExtensionInstall($id);
            }
        }
    }

    /** Repair historical duplicate installer records without running package scripts. */
    public function repairInstallationRecords($code, array $installedMetadata = [])
    {
        $record = $this->moduleRegistry->getModuleByCode($code);
        if ($record === null) {
            throw new \RuntimeException('Module is not registered: ' . $code);
        }
        $metadata = array_merge($record, $installedMetadata);
        $metadata['code'] = $record['code'];
        $metadata['server_code'] = $record['server_code'] ?? $record['code'];
        $metadata['aliases'] = array_values(array_unique(array_merge(
            $record['aliases'] ?? [],
            [$record['code']],
            isset($installedMetadata['code']) ? [$installedMetadata['code']] : []
        )));
        $registrations = $this->findExtensionInstalls($metadata, '');
        if (!$registrations) {
            return ['code' => $record['code'], 'merged' => 0];
        }
        $keepId = (int)$registrations[0]['extension_install_id'];
        $this->consolidateExtensionInstalls($keepId, $registrations, []);
        $metadata['extension_install_id'] = $keepId;
        $this->ensureCoreTables();
        $paths = $this->db->query("SELECT `path` FROM `" . DB_PREFIX . "extension_path` WHERE `extension_install_id` = " . $keepId);
        foreach ($paths->rows as $row) {
            $path = $this->db->escape($row['path']);
            $this->db->query("INSERT INTO `" . DB_PREFIX . "ocm_module_files` (`module_code`, `file_path`, `file_hash`, `installed_at`, `updated_at`)
                SELECT '" . $this->db->escape($record['code']) . "', '" . $path . "', '', NOW(), NOW() FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "ocm_module_files`
                    WHERE `module_code` = '" . $this->db->escape($record['code']) . "' AND `file_path` = '" . $path . "' AND `removed_at` IS NULL)");
        }
        $this->db->query("UPDATE `" . DB_PREFIX . "ocm_modules` SET `installed_version` = '" . $this->db->escape($metadata['version']) . "',
            `metadata_json` = '" . $this->db->escape(json_encode($metadata, JSON_UNESCAPED_UNICODE)) . "', `updated_at` = NOW()
            WHERE `code` = '" . $this->db->escape($record['code']) . "'");
        $this->moduleRegistry->upsert($metadata);
        return ['code' => $record['code'], 'merged' => count($registrations) - 1, 'extension_install_id' => $keepId];
    }

    private function resolveVersion(array $module_data, $extract_dir, $code, $server_module_info = array())
    {
        foreach (['opencart-module.json', 'upload/opencart-module.json', 'install.json', 'upload/install.json'] as $filename) {
            $path = $extract_dir . '/' . $filename;
            $metadata = is_file($path) ? $this->readJsonMetadata($path) : null;
            if (is_array($metadata) && isset($metadata['version']) && $metadata['version'] !== '') {
                if ((string)$metadata['version'] === '0.0.0') {
                    continue;
                }
                if (!$this->knownVersion($metadata['version'])) {
                    throw new \RuntimeException('Invalid package version in ' . $filename);
                }
                return ltrim(trim((string)$metadata['version']), 'vV');
            }
        }
        $path = $extract_dir . '/install.xml';
        if (is_file($path)) {
            $dom = new \DOMDocument();
            if (@$dom->loadXML(file_get_contents($path))) {
                $node = $dom->getElementsByTagName('version')->item(0);
                if ($node && $this->knownVersion($node->nodeValue)) {
                    return ltrim(trim($node->nodeValue), 'vV');
                }
            }
        }
        // Only a version explicitly bound to the downloaded release is a valid fallback.
        $version = $server_module_info['_downloaded_version'] ?? '';
        return $this->knownVersion($version) ? ltrim(trim($version), 'vV') : '0.0.0';
    }

    private function knownVersion($version)
    {
        return (is_string($version) || is_int($version) || is_float($version))
            && preg_match('/^v?\d+(?:\.\d+)*(?:[-+][0-9A-Za-z.-]+)?$/i', trim((string)$version))
            && ltrim(trim((string)$version), 'vV') !== '0.0.0';
    }

    private function getOpenCartPath($relative_path)
    {
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

        $root = dirname(rtrim(DIR_APPLICATION, '/')) . '/';
        return $root . $destination;
    }

    private function ensureCoreTables()
    {
        try {
            $this->registry->get('load')->model('extension/module/gdt_updater');
            if ($this->registry->has('model_extension_module_gdt_updater')) {
                $this->registry->get('model_extension_module_gdt_updater')->createTables();
                return;
            }
        } catch (\Throwable $e) {
            // fallback to direct SQL
        }

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

    public function refreshModifications(): void
    {
        try {
            $this->registry->get('load')->controller('marketplace/modification/refresh');
            LoggerService::info('Triggered marketplace/modification/refresh', 'InstallService');
        } catch (\Throwable $e) {
            LoggerService::warning('Could not run modification controller refresh directly: ' . $e->getMessage(), 'InstallService');
            throw $e;
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function setMaintenanceMode(bool $active): void
    {
        $lock_file = $this->getMaintenanceLockFile();
        if ($active) {
            @file_put_contents($lock_file, json_encode(['time' => time(), 'process' => 'ocm_updater']));
        } else {
            if (file_exists($lock_file)) {
                @unlink($lock_file);
            }
        }
    }

    private function getMaintenanceLockFile(): string
    {
        $root = defined('DIR_STORAGE') ? DIR_STORAGE : sys_get_temp_dir() . '/';
        return rtrim($root, '/') . '/.ocm_maintenance';
    }

    private function backupExistingFiles(string $extract_dir, string $module_code): ?string
    {
        $upload_dir = $extract_dir . '/upload/';
        if (!is_dir($upload_dir)) {
            return null;
        }

        $backup_root = defined('DIR_STORAGE') ? DIR_STORAGE . 'backup/ocm_backups/' : sys_get_temp_dir() . '/ocm_backups/';
        $backup_dir = $backup_root . preg_replace('/[^a-zA-Z0-9_-]/', '', $module_code) . '_' . date('Ymd_His') . '_' . uniqid() . '/';

        $files = [];
        $path = [$upload_dir . '*'];
        while (count($path) != 0) {
            $next = array_shift($path);
            foreach ((array)glob($next) as $file) {
                if (is_dir($file)) {
                    $path[] = $file . '/*';
                } else {
                    $files[] = $file;
                }
            }
        }

        $backed_up = 0;
        foreach ($files as $file) {
            $relative = substr($file, strlen($upload_dir));
            $destination = $this->getDestinationPath($relative);
            if (file_exists($destination) && is_file($destination)) {
                $target = $backup_dir . $relative;
                $target_dir = dirname($target);
                if (!is_dir($target_dir)) {
                    @mkdir($target_dir, 0777, true);
                }
                if (@copy($destination, $target)) {
                    $backed_up++;
                }
            }
        }

        if ($backed_up > 0) {
            LoggerService::info("Backed up {$backed_up} existing files to {$backup_dir}", 'InstallService');
            return $backup_dir;
        }

        return null;
    }

    private function restoreBackup(?string $backup_dir): void
    {
        if (!$backup_dir || !is_dir($backup_dir)) {
            return;
        }

        LoggerService::warning("Restoring backup from {$backup_dir}", 'InstallService');

        $files = [];
        $path = [$backup_dir . '*'];
        while (count($path) != 0) {
            $next = array_shift($path);
            foreach ((array)glob($next) as $file) {
                if (is_dir($file)) {
                    $path[] = $file . '/*';
                } else {
                    $files[] = $file;
                }
            }
        }

        foreach ($files as $file) {
            $relative = substr($file, strlen($backup_dir));
            $destination = $this->getDestinationPath($relative);
            $dest_dir = dirname($destination);
            if (!is_dir($dest_dir)) {
                @mkdir($dest_dir, 0777, true);
            }
            @copy($file, $destination);
        }

        LoggerService::info("Backup restoration completed", 'InstallService');
    }

    private function executeModuleScript(string $extract_dir, string $module_code, bool $isUpdate = false): void
    {
        $upgrade_script = $extract_dir . '/upgrade.php';
        $install_script = $extract_dir . '/install.php';

        $candidate = $isUpdate ? $upgrade_script : $install_script;
        $script = is_file($candidate) ? $candidate : null;

        if ($script) {
            LoggerService::info("Executing module script: " . basename($script), 'InstallService');
            try {
                $runner = function ($scriptPath, $registry, $db) {
                    include $scriptPath;
                };
                $runner($script, $this->registry, $this->db);
            } catch (\Throwable $e) {
                LoggerService::error("Error executing script " . basename($script) . ": " . $e->getMessage(), 'InstallService');
                throw $e;
            }
        }
    }
}
