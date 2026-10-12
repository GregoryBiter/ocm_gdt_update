<?php

namespace Gbitstudio\Modules\Services;

/**
 * Сервіс порівняння та вирішення конфліктів між modules.json та базою даних / config параметрами.
 */
class ConflictService
{
    /** @var \Registry */
    private $registry;

    /** @var \DB */
    private $db;

    /** @var \Config */
    private $config;

    /** @var ModuleRegistryService */
    private $moduleRegistry;

    public function __construct($registry, $moduleRegistry = null)
    {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');
        $this->moduleRegistry = $moduleRegistry ?: new ModuleRegistryService($registry);
    }

    /**
     * Отримує список конфліктів/невідповідностей між modules.json та базою даних / config.
     *
     * @return array
     */
    public function getConflicts(): array
    {
        $conflicts = [];

        // 1. Модулі з файлу modules.json
        $file_modules = [];
        try {
            foreach ($this->moduleRegistry->getInstalledModules() as $item) {
                $item = (array)$item;
                if (!empty($item['code'])) {
                    $file_modules[$item['code']] = $item;
                }
            }
        } catch (\Throwable $e) {
            LoggerService::error('Error loading modules.json in ConflictService: ' . $e->getMessage(), 'ConflictService');
        }

        // 2. Модулі з таблиці ocm_modules в базі даних
        $db_modules = [];
        if ($this->tableExists(DB_PREFIX . 'ocm_modules')) {
            try {
                $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocm_modules`");
                foreach ($query->rows as $row) {
                    $db_modules[$row['code']] = $row;
                }
            } catch (\Throwable $e) {
                LoggerService::error('Error querying ocm_modules in ConflictService: ' . $e->getMessage(), 'ConflictService');
            }
        }

        // 3. Збираємо всі унікальні коди модулів
        $all_codes = array_unique(array_merge(array_keys($file_modules), array_keys($db_modules)));

        foreach ($all_codes as $code) {
            $file_item = $file_modules[$code] ?? null;
            $db_item = $db_modules[$code] ?? null;

            $file_version = $file_item['version'] ?? null;
            $db_version = $db_item['installed_version'] ?? null;

            // Перевіряємо конфіг/налаштування OpenCart для цього модуля
            $config_version = $this->getConfigVersion($code);

            // Визначаємо ефективну версію в базі/конфігу
            $effective_db_version = $db_version ?: $config_version;

            $name = $file_item['name'] ?? ($db_item['name'] ?? $code);

            // Визначаємо тип конфлікту
            $conflict = null;

            if ($file_item !== null && $db_item !== null) {
                // Модуль є в обох джерелах
                if ($file_version !== $db_version) {
                    $conflict = [
                        'code' => $code,
                        'name' => $name,
                        'file_version' => $file_version,
                        'db_version' => $db_version,
                        'config_version' => $config_version,
                        'type' => 'version_mismatch',
                        'type_label' => 'Несовпадение версий',
                        'description' => "Версия в modules.json ({$file_version}) не совпадает с версией в БД ({$db_version})",
                        'in_file' => true,
                        'in_db' => true,
                    ];
                } elseif ($config_version !== null && $config_version !== '' && $config_version !== $file_version) {
                    $conflict = [
                        'code' => $code,
                        'name' => $name,
                        'file_version' => $file_version,
                        'db_version' => $db_version,
                        'config_version' => $config_version,
                        'type' => 'config_mismatch',
                        'type_label' => 'Несовпадение с config',
                        'description' => "Версия в config параметрах ({$config_version}) отличается от modules.json ({$file_version})",
                        'in_file' => true,
                        'in_db' => true,
                    ];
                }
            } elseif ($file_item !== null && $db_item === null) {
                // Модуль є в modules.json, але відсутній в ocm_modules
                if ($config_version !== null && $config_version !== '' && $config_version !== $file_version) {
                    $conflict = [
                        'code' => $code,
                        'name' => $name,
                        'file_version' => $file_version,
                        'db_version' => $config_version,
                        'config_version' => $config_version,
                        'type' => 'config_mismatch',
                        'type_label' => 'Несовпадение с config',
                        'description' => "Отсутствует в ocm_modules, но в config ({$config_version}) отличается от modules.json ({$file_version})",
                        'in_file' => true,
                        'in_db' => false,
                    ];
                } else {
                    $conflict = [
                        'code' => $code,
                        'name' => $name,
                        'file_version' => $file_version,
                        'db_version' => $config_version ?: null,
                        'config_version' => $config_version,
                        'type' => 'missing_in_db',
                        'type_label' => 'Отсутствует в БД',
                        'description' => "Модуль зарегистрирован в modules.json ({$file_version}), но отсутствует в таблице ocm_modules",
                        'in_file' => true,
                        'in_db' => false,
                    ];
                }
            } elseif ($file_item === null && $db_item !== null) {
                // Модуль є в ocm_modules, але відсутній в modules.json
                $conflict = [
                    'code' => $code,
                    'name' => $name,
                    'file_version' => null,
                    'db_version' => $db_version,
                    'config_version' => $config_version,
                    'type' => 'missing_in_file',
                    'type_label' => 'Отсутствует в modules.json',
                    'description' => "Модуль присутствует в базе данных ({$db_version}), но отсутствует в файле modules.json",
                    'in_file' => false,
                    'in_db' => true,
                ];
            }

            if ($conflict !== null) {
                $conflicts[] = $conflict;
            }
        }

        return $conflicts;
    }

    /**
     * Перевіряє наявність конфліктів.
     *
     * @return bool
     */
    public function hasConflicts(): bool
    {
        return !empty($this->getConflicts());
    }

    /**
     * Отримує версію модуля з OpenCart config або таблиці setting.
     *
     * @param string $code
     * @return string|null
     */
    public function getConfigVersion(string $code): ?string
    {
        // 1. Перевіряємо OpenCart Config
        if ($this->config) {
            $val = $this->config->get('module_' . $code . '_version');
            if ($val !== null && $val !== '') {
                return (string)$val;
            }
            $val = $this->config->get($code . '_version');
            if ($val !== null && $val !== '') {
                return (string)$val;
            }
        }

        // 2. Перевіряємо таблицю {DB_PREFIX}setting
        if ($this->tableExists(DB_PREFIX . 'setting')) {
            try {
                $escaped = $this->db->escape($code);
                $query = $this->db->query(
                    "SELECT `value` FROM `" . DB_PREFIX . "setting` " .
                    "WHERE (`code` = 'module_" . $escaped . "' OR `code` = '" . $escaped . "') " .
                    "AND (`key` = 'module_" . $escaped . "_version' OR `key` = '" . $escaped . "_version' OR `key` = 'version') " .
                    "LIMIT 1"
                );
                if ($query->num_rows > 0 && !empty($query->row['value'])) {
                    return (string)$query->row['value'];
                }
            } catch (\Throwable $e) {
                // Ігноруємо помилки читання налаштувань
            }
        }

        return null;
    }

    /**
     * Вирішує конфлікт для конкретного модуля.
     *
     * @param string $code Код модуля
     * @param string $trust 'file' (довіряти modules.json) або 'db' (довіряти базі даних / config)
     * @return array ['success' => bool, 'message' => string]
     * @throws \InvalidArgumentException
     */
    public function resolveConflict(string $code, string $trust): array
    {
        if (!in_array($trust, ['file', 'db'], true)) {
            throw new \InvalidArgumentException("Invalid trust choice: {$trust}. Must be 'file' or 'db'.");
        }

        if ($trust === 'file') {
            return $this->resolveTrustFile($code);
        } else {
            return $this->resolveTrustDb($code);
        }
    }

    /**
     * Вирішення конфлікту на користь modules.json.
     * Оновлює або додає запис у БД, або видаляє з БД якщо в файлі відсутній.
     *
     * @param string $code
     * @return array
     */
    private function resolveTrustFile(string $code): array
    {
        $file_module = $this->moduleRegistry->getModuleByCode($code);

        if ($file_module !== null) {
            // Модуль є у файлі — синхронізуємо базу даних
            $version = (string)($file_module['version'] ?? '0.0.0');
            $name = (string)($file_module['name'] ?? $code);
            $type = (string)($file_module['type'] ?? 'module');
            $metadata_json = json_encode($file_module, JSON_UNESCAPED_UNICODE);

            if ($this->tableExists(DB_PREFIX . 'ocm_modules')) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "ocm_modules` SET
                    `code` = '" . $this->db->escape($code) . "',
                    `name` = '" . $this->db->escape($name) . "',
                    `type` = '" . $this->db->escape($type) . "',
                    `installed_version` = '" . $this->db->escape($version) . "',
                    `source` = 'gdt_updater',
                    `metadata_json` = '" . $this->db->escape($metadata_json) . "',
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
            }

            // Також синхронізуємо config/setting таблицю якщо такі ключі існують
            $this->syncConfigSetting($code, $version);

            LoggerService::info("Resolved conflict for module '{$code}' trusting modules.json (version {$version})", 'ConflictService');

            return [
                'success' => true,
                'message' => "База данных обновлена версией {$version} из modules.json для модуля {$name} ({$code})"
            ];
        } else {
            // Модуль відсутній у файлі — видаляємо з бази даних
            if ($this->tableExists(DB_PREFIX . 'ocm_modules')) {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "ocm_modules` WHERE `code` = '" . $this->db->escape($code) . "'");
            }

            LoggerService::info("Resolved conflict for module '{$code}' trusting modules.json: removed from database", 'ConflictService');

            return [
                'success' => true,
                'message' => "Модуль {$code} удален из базы данных согласно файлу modules.json"
            ];
        }
    }

    /**
     * Вирішення конфлікту на користь бази даних / config.
     * Оновлює modules.json значенням з БД або видаляє з modules.json якщо в БД відсутній.
     *
     * @param string $code
     * @return array
     */
    private function resolveTrustDb(string $code): array
    {
        $db_row = null;
        if ($this->tableExists(DB_PREFIX . 'ocm_modules')) {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocm_modules` WHERE `code` = '" . $this->db->escape($code) . "'");
            if ($query->num_rows > 0) {
                $db_row = $query->row;
            }
        }

        $config_version = $this->getConfigVersion($code);

        if ($db_row !== null) {
            // Модуль є в таблиці ocm_modules
            $metadata = !empty($db_row['metadata_json']) ? json_decode($db_row['metadata_json'], true) : [];
            $metadata = is_array($metadata) ? $metadata : [];

            $metadata['code'] = $code;
            $metadata['name'] = $db_row['name'] ?: ($metadata['name'] ?? $code);
            $metadata['version'] = $db_row['installed_version'] ?: ($config_version ?: '0.0.0');
            $metadata['source'] = $db_row['source'] ?: ($metadata['source'] ?? 'gdt_updater');

            $this->moduleRegistry->upsert($metadata);

            LoggerService::info("Resolved conflict for module '{$code}' trusting database (version {$metadata['version']})", 'ConflictService');

            return [
                'success' => true,
                'message' => "Файл modules.json обновлен версией {$metadata['version']} из базы данных для модуля {$code}"
            ];
        } elseif ($config_version !== null && $config_version !== '') {
            // Модуль є в config/setting
            $existing = $this->moduleRegistry->getModuleByCode($code) ?: [];
            $existing['code'] = $code;
            $existing['version'] = $config_version;
            $existing['name'] = $existing['name'] ?? $code;

            $this->moduleRegistry->upsert($existing);

            LoggerService::info("Resolved conflict for module '{$code}' trusting config (version {$config_version})", 'ConflictService');

            return [
                'success' => true,
                'message' => "Файл modules.json обновлен версией {$config_version} из config для модуля {$code}"
            ];
        } else {
            // В базі та конфігу модуль відсутній — видаляємо з modules.json
            try {
                $this->moduleRegistry->remove($code);
            } catch (\Throwable $e) {
                // Вже видалений
            }

            LoggerService::info("Resolved conflict for module '{$code}' trusting database: removed from modules.json", 'ConflictService');

            return [
                'success' => true,
                'message' => "Модуль {$code} удален из modules.json согласно базе данных"
            ];
        }
    }

    /**
     * Масове вирішення конфліктів.
     *
     * @param array $resolutions Асоціативний масив [$code => 'file'|'db'] або список [['code' => ..., 'trust' => ...]]
     * @return array
     */
    public function resolveBatch(array $resolutions): array
    {
        $resolved_count = 0;
        $errors = [];

        foreach ($resolutions as $key => $value) {
            $code = is_array($value) ? ($value['code'] ?? '') : $key;
            $trust = is_array($value) ? ($value['trust'] ?? '') : $value;

            if (empty($code) || empty($trust)) {
                continue;
            }

            try {
                $res = $this->resolveConflict($code, $trust);
                if (!empty($res['success'])) {
                    $resolved_count++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Ошибка для модуля {$code}: " . $e->getMessage();
            }
        }

        return [
            'success' => empty($errors),
            'resolved_count' => $resolved_count,
            'errors' => $errors
        ];
    }

    /**
     * Оновлює значення версії в таблиці setting та об'єкті config якщо такі ключі існують.
     *
     * @param string $code
     * @param string $version
     */
    private function syncConfigSetting(string $code, string $version): void
    {
        if ($this->config) {
            $this->config->set('module_' . $code . '_version', $version);
            $this->config->set($code . '_version', $version);
        }

        if ($this->tableExists(DB_PREFIX . 'setting')) {
            try {
                $escaped_code = $this->db->escape($code);
                $escaped_version = $this->db->escape($version);

                $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '{$escaped_version}' 
                    WHERE (`code` = 'module_{$escaped_code}' OR `code` = '{$escaped_code}') 
                    AND (`key` = 'module_{$escaped_code}_version' OR `key` = '{$escaped_code}_version' OR `key` = 'version')");
            } catch (\Throwable $e) {
                // Ігноруємо помилки оновлення setting
            }
        }
    }

    /**
     * Перевіряє існування таблиці в базі даних.
     *
     * @param string $table
     * @return bool
     */
    private function tableExists(string $table): bool
    {
        try {
            $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");
            return $query->num_rows > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
