<?php

namespace Gbitstudio\Modules\Services;

/** Installed metadata lives in storage; file ownership and history remain in the database. */
class ModuleRegistryService
{
    private $db;
    private $path;

    public function __construct($registry, $path = null)
    {
        $this->db = $registry->get('db');
        $this->path = $path ?: DIR_STORAGE . 'modules.json';
    }

    public function getInstalledModules()
    {
        return array_map(function ($record) {
            return (array)$record;
        }, array_values($this->withLock(function (&$data) {
            return (array)$data->modules;
        })));
    }

    public function getModuleByCode($code)
    {
        return $this->withLock(function (&$data) use ($code) {
            return isset($data->modules->{$code}) ? (array)$data->modules->{$code} : null;
        });
    }

    public function assertWritable()
    {
        $this->withLock(function (&$data) {
            $temporary = $this->temporaryFile();
            unlink($temporary);
        });
    }

    public function upsert(array $module)
    {
        return $this->withLock(function (&$data) use ($module) {
            $code = $module['code'] ?? '';
            if (!is_string($code) || trim($code) === '') {
                throw new \RuntimeException('Module code is required');
            }
            $previous = isset($data->modules->{$code}) ? (array)$data->modules->{$code} : [];
            $record = $this->normalize($module);
            $record['installed_at'] = $previous['installed_at'] ?? $record['installed_at'];
            $record['updated_at'] = date('Y-m-d H:i:s');
            $data->modules->{$code} = (object)$record;
            $this->write($data);
            return $record;
        });
    }

    public function remove($code)
    {
        $this->withLock(function (&$data) use ($code) {
            if (!isset($data->modules->{$code})) {
                throw new \RuntimeException('Module is not registered: ' . $code);
            }
            unset($data->modules->{$code});
            $this->write($data);
        });
    }

    private function withLock(callable $operation)
    {
        $lock = null;
        try {
            if (!is_dir(dirname($this->path)) || !is_writable(dirname($this->path))) {
                throw new \RuntimeException('Module registry storage is not writable');
            }
            $lock = @fopen($this->path . '.lock', 'c');
            if (!$lock || !flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock module registry');
            }
            clearstatcache(true, $this->path);
            if (file_exists($this->path)) {
                $data = $this->read();
            } else {
                $data = $this->import();
                $this->write($data);
            }
            return $operation($data);
        } catch (\Throwable $e) {
            LoggerService::error('Module registry: ' . $e->getMessage(), 'ModuleRegistryService');
            throw $e;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private function read()
    {
        $contents = @file_get_contents($this->path);
        $data = $contents === false ? null : json_decode($contents);
        if (!is_object($data) || ($data->schema_version ?? null) !== 1 || !is_object($data->modules ?? null)) {
            throw new \RuntimeException('Invalid modules.json: restore a valid registry from backup');
        }
        foreach ($data->modules as $code => $record) {
            if (
                !is_object($record) || ($record->code ?? null) !== (string)$code
                || !is_string($record->version ?? null) || $record->version === ''
                || !is_string($record->name ?? null)
            ) {
                throw new \RuntimeException('Invalid module record in modules.json: ' . $code);
            }
        }
        return $data;
    }

    private function import()
    {
        $data = (object)['schema_version' => 1, 'modules' => new \stdClass()];
        // Exact table lookup: LIKE would treat underscores in the prefix as wildcards.
        $tables = $this->db->query("SHOW TABLES");
        $exists = false;
        foreach ($tables->rows as $row) {
            if (in_array(DB_PREFIX . 'ocm_modules', $row, true)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            return $data;
        }
        $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ocm_modules` WHERE `status` = 1");
        foreach ($result->rows as $row) {
            $metadata = json_decode($row['metadata_json'], true);
            $metadata = is_array($metadata) ? $metadata : [];
            foreach (['code', 'name', 'source', 'installed_at', 'updated_at'] as $field) {
                $metadata[$field] = $row[$field];
            }
            $metadata['version'] = $row['installed_version'];
            $record = $this->normalize($metadata);
            if ($record['code'] === '') {
                throw new \RuntimeException('Cannot import a module without a code');
            }
            $data->modules->{$record['code']} = (object)$record;
        }
        return $data;
    }

    private function normalize(array $module)
    {
        $code = (string)($module['code'] ?? '');
        return [
            'code' => $code,
            'name' => (string)($module['name'] ?? $module['module_name'] ?? $code),
            'version' => (string)(($module['version'] ?? '') ?: '0.0.0'),
            'description' => (string)($module['description'] ?? ''),
            'creator_name' => (string)($module['creator_name'] ?? $module['author'] ?? ''),
            'author_url' => (string)($module['author_url'] ?? $module['link'] ?? ''),
            'controller' => (string)($module['controller'] ?? ''),
            'provider' => (string)($module['provider'] ?? ''),
            'source' => (string)($module['source'] ?? 'gdt_updater'),
            'installed_at' => (string)($module['installed_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => (string)($module['updated_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    private function temporaryFile()
    {
        $temporary = @tempnam(dirname($this->path), '.modules-');
        if ($temporary === false || dirname($temporary) !== realpath(dirname($this->path))) {
            if ($temporary !== false) {
                unlink($temporary);
            }
            throw new \RuntimeException('Cannot create temporary module registry');
        }
        return $temporary;
    }

    private function write($data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Cannot encode module registry: ' . json_last_error_msg());
        }
        $temporary = $this->temporaryFile();
        try {
            $contents = $json . "\n";
            if (
                @file_put_contents($temporary, $contents) !== strlen($contents)
                || !@rename($temporary, $this->path)
            ) {
                throw new \RuntimeException('Cannot save modules.json; previous registry was preserved');
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }
}
