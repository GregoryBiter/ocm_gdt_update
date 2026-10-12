<?php

namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для роботи з модулями
 * Читає метадані з modules.json, шляхи файлів з ocm_module_files.
 */
class ModuleService
{
    private $db;
    private $registry;
    private $moduleRegistry;

    public function __construct($registry, $moduleRegistry = null)
    {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->moduleRegistry = $moduleRegistry ?: new ModuleRegistryService($registry);
    }

    public function getInstalledModules()
    {
        $modules = [];
        foreach ($this->moduleRegistry->getInstalledModules() as $record) {
            $record = (array)$record;
            $record['paths'] = $this->getModulePaths($record['code'], ($record['source'] ?? '') === 'legacy_gdt');
            $modules[] = $this->formatModuleData($record, $record['code']);
        }
        return $modules;
    }

    public function getModuleByCode($code)
    {
        $record = $this->moduleRegistry->getModuleByCode($code);
        if ($record === null) {
            return null;
        }
        $record['paths'] = $this->getModulePaths($record['code'], ($record['source'] ?? '') === 'legacy_gdt');
        return $this->formatModuleData($record, $record['code']);
    }

    private function getModulePaths($code, $legacy = false)
    {
        $paths = [];

        try {
            $table_query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "ocm_module_files'");
            if ($table_query->num_rows > 0) {
                $result = $this->db->query("SELECT `file_path` FROM `" . DB_PREFIX . "ocm_module_files` WHERE `module_code` = '" . $this->db->escape($code) . "' AND `removed_at` IS NULL");
                foreach ($result->rows as $row) {
                    $paths[] = $row['file_path'];
                }
            }
            if (empty($paths) && $legacy) {
                $result = $this->db->query("SELECT `paths` FROM `" . DB_PREFIX . "gdt_modules` WHERE `code` = '" . $this->db->escape($code) . "' LIMIT 1");
                $paths = json_decode($result->row['paths'] ?? '[]', true) ?: [];
            }
        } catch (\Exception $e) {
            LoggerService::warning('Error reading module files: ' . $e->getMessage(), 'ModuleService');
        }

        return $paths;
    }

    private function formatModuleData($data, $code)
    {
        $version = $data['version'] ?? '0.0.0';

        return [
            'code' => $data['code'] ?? $code,
            'aliases' => $data['aliases'] ?? [],
            'server_code' => $data['server_code'] ?? $data['code'] ?? $code,
            'name' => $data['module_name'] ?? $data['name'] ?? $code,
            'module_name' => $data['module_name'] ?? $data['name'] ?? $code,
            'version' => $version,
            'author' => $data['creator_name'] ?? $data['author'] ?? 'Unknown',
            'creator_name' => $data['creator_name'] ?? $data['author'] ?? 'Unknown',
            'author_url' => $data['author_url'] ?? ($data['link'] ?? ''),
            'link' => $data['link'] ?? ($data['author_url'] ?? ''),
            'description' => $data['description'] ?? '',
            'controller' => $data['controller'] ?? '',
            'provider' => $data['provider'] ?? '',
            'files' => [],
            'paths' => $data['paths'] ?? [],
            'source' => $data['source'] ?? 'database'
        ];
    }
}
