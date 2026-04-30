<?php
namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для роботи з модулями
 * Читає дані з таблиць ocm_*.
 */
class ModuleService {
    private $db;
    private $registry;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
    }

    public function getInstalledModules() {
        return $this->getModulesFromDatabase();
    }

    public function getModuleByCode($code) {
        return $this->getModuleFromDatabase($code);
    }

    private function getModulesFromDatabase() {
        $modules = [];

        try {
            $table_query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "ocm_modules'");
            if ($table_query->num_rows == 0) {
                return $modules;
            }

            $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocm_modules` ORDER BY `updated_at` DESC");

            foreach ($result->rows as $row) {
                $metadata = json_decode($row['metadata_json'], true) ?: [];
                $metadata['code'] = $row['code'];
                $metadata['name'] = $row['name'];
                $metadata['version'] = $row['installed_version'];
                $metadata['source'] = $row['source'];
                $metadata['paths'] = $this->getModulePaths($row['code']);

                $modules[] = $this->formatModuleData($metadata, $row['code']);
            }
        } catch (\Exception $e) {
            LoggerService::error('Error getting modules from database: ' . $e->getMessage(), 'ModuleService');
        }

        return $modules;
    }

    private function getModuleFromDatabase($code) {
        try {
            $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocm_modules` WHERE `code` = '" . $this->db->escape($code) . "' LIMIT 1");

            if ($result->num_rows > 0) {
                $row = $result->row;
                $metadata = json_decode($row['metadata_json'], true) ?: [];
                $metadata['code'] = $row['code'];
                $metadata['name'] = $row['name'];
                $metadata['version'] = $row['installed_version'];
                $metadata['source'] = $row['source'];
                $metadata['paths'] = $this->getModulePaths($row['code']);

                return $this->formatModuleData($metadata, $row['code']);
            }
        } catch (\Exception $e) {
            LoggerService::error('Error getting module from database: ' . $e->getMessage(), 'ModuleService');
        }

        return null;
    }

    private function getModulePaths($code) {
        $paths = [];

        try {
            $table_query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "ocm_module_files'");
            if ($table_query->num_rows == 0) {
                return $paths;
            }

            $result = $this->db->query("SELECT `file_path` FROM `" . DB_PREFIX . "ocm_module_files` WHERE `module_code` = '" . $this->db->escape($code) . "' AND `removed_at` IS NULL");
            foreach ($result->rows as $row) {
                $paths[] = $row['file_path'];
            }
        } catch (\Exception $e) {
            LoggerService::warning('Error reading module files: ' . $e->getMessage(), 'ModuleService');
        }

        return $paths;
    }

    private function formatModuleData($data, $code) {
        return [
            'code' => $data['code'] ?? $code,
            'name' => $data['module_name'] ?? $data['name'] ?? $code,
            'module_name' => $data['module_name'] ?? $data['name'] ?? $code,
            'version' => $data['version'] ?? '0.0.0',
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
