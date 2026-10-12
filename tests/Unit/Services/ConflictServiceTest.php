<?php

namespace Tests\Unit\Services;

use Gbitstudio\Modules\Services\ConflictService;
use Gbitstudio\Modules\Services\ModuleRegistryService;
use Mockery;
use Tests\BaseTestCase;

require_once __DIR__ . '/../../../upload/system/library/gbitstudio/modules/services/moduleregistryservice.php';
require_once __DIR__ . '/../../../upload/system/library/gbitstudio/modules/services/conflictservice.php';

class ConflictServiceTest extends BaseTestCase
{
    private $dir;
    private $registryPath;
    private $registryService;
    private $registry;
    private $db;
    private $config;
    private $executedQueries = [];
    private $ocmModulesRows = [];
    private $settingRows = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gdt-conflict-test-' . uniqid();
        mkdir($this->dir);
        $this->registryPath = $this->dir . '/modules.json';

        $this->executedQueries = [];
        $this->ocmModulesRows = [];
        $this->settingRows = [];

        $this->registry = Mockery::mock('Registry');

        $this->db = Mockery::mock('DB');
        $this->db->shouldReceive('escape')->andReturnUsing(function ($str) {
            return addslashes($str);
        });

        $this->db->shouldReceive('query')->andReturnUsing(function ($sql) {
            $this->executedQueries[] = $sql;
            $rows = [];

            if (strpos($sql, 'SHOW TABLES LIKE') !== false) {
                // Table existence check
                $rows = [['table' => 'exists']];
            } elseif ($sql === 'SHOW TABLES') {
                $rows = [['table' => DB_PREFIX . 'ocm_modules']];
            } elseif (strpos($sql, 'SELECT * FROM `' . DB_PREFIX . 'ocm_modules` WHERE `code` =') !== false) {
                preg_match("/`code` = '([^']+)'/", $sql, $matches);
                $code = $matches[1] ?? '';
                foreach ($this->ocmModulesRows as $row) {
                    if ($row['code'] === $code) {
                        $rows = [$row];
                        break;
                    }
                }
            } elseif (strpos($sql, 'SELECT * FROM `' . DB_PREFIX . 'ocm_modules`') !== false) {
                $rows = $this->ocmModulesRows;
            } elseif (strpos($sql, 'SELECT `value` FROM `' . DB_PREFIX . 'setting`') !== false) {
                $rows = $this->settingRows;
            }

            return (object)[
                'rows' => $rows,
                'row' => $rows[0] ?? [],
                'num_rows' => count($rows)
            ];
        });

        $this->registry->shouldReceive('get')->with('db')->andReturn($this->db);

        $this->config = Mockery::mock('Config');
        $this->config->shouldReceive('get')->andReturn(null);
        $this->config->shouldReceive('set')->andReturnNull();
        $this->registry->shouldReceive('get')->with('config')->andReturn($this->config);

        $this->registryService = new ModuleRegistryService($this->registry, $this->registryPath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->registryPath)) {
            @unlink($this->registryPath);
        }
        if (file_exists($this->registryPath . '.lock')) {
            @unlink($this->registryPath . '.lock');
        }
        if (is_dir($this->dir)) {
            @rmdir($this->dir);
        }
        parent::tearDown();
    }

    public function testNoConflictsWhenVersionsMatch()
    {
        // 1. Module in modules.json
        $this->registryService->upsert([
            'code' => 'test_module',
            'name' => 'Test Module',
            'version' => '1.0.0'
        ]);

        // 2. Matching module in ocm_modules DB
        $this->ocmModulesRows = [
            [
                'code' => 'test_module',
                'name' => 'Test Module',
                'installed_version' => '1.0.0',
                'metadata_json' => json_encode(['code' => 'test_module', 'version' => '1.0.0']),
                'status' => 1
            ]
        ];

        $service = new ConflictService($this->registry, $this->registryService);

        $this->assertFalse($service->hasConflicts());
        $this->assertEmpty($service->getConflicts());
    }

    public function testDetectsVersionMismatchBetweenFileAndDb()
    {
        // modules.json has 1.2.0
        $this->registryService->upsert([
            'code' => 'my_module',
            'name' => 'My Module',
            'version' => '1.2.0'
        ]);

        // ocm_modules DB has 1.0.0
        $this->ocmModulesRows = [
            [
                'code' => 'my_module',
                'name' => 'My Module',
                'installed_version' => '1.0.0',
                'metadata_json' => json_encode(['code' => 'my_module', 'version' => '1.0.0']),
                'status' => 1
            ]
        ];

        $service = new ConflictService($this->registry, $this->registryService);

        $this->assertTrue($service->hasConflicts());
        $conflicts = $service->getConflicts();
        $this->assertCount(1, $conflicts);
        $this->assertEquals('my_module', $conflicts[0]['code']);
        $this->assertEquals('1.2.0', $conflicts[0]['file_version']);
        $this->assertEquals('1.0.0', $conflicts[0]['db_version']);
        $this->assertEquals('version_mismatch', $conflicts[0]['type']);
    }

    public function testDetectsModuleMissingInDb()
    {
        // modules.json has module
        $this->registryService->upsert([
            'code' => 'new_module',
            'name' => 'New Module',
            'version' => '2.0.0'
        ]);

        // DB is empty
        $this->ocmModulesRows = [];

        $service = new ConflictService($this->registry, $this->registryService);

        $this->assertTrue($service->hasConflicts());
        $conflicts = $service->getConflicts();
        $this->assertCount(1, $conflicts);
        $this->assertEquals('new_module', $conflicts[0]['code']);
        $this->assertEquals('missing_in_db', $conflicts[0]['type']);
        $this->assertTrue($conflicts[0]['in_file']);
        $this->assertFalse($conflicts[0]['in_db']);
    }

    public function testDetectsModuleMissingInFile()
    {
        // modules.json has nothing (initialize empty)
        file_put_contents($this->registryPath, json_encode(['schema_version' => 1, 'modules' => new \stdClass()]));

        // DB has module
        $this->ocmModulesRows = [
            [
                'code' => 'legacy_module',
                'name' => 'Legacy Module',
                'installed_version' => '3.0.0',
                'metadata_json' => json_encode(['code' => 'legacy_module', 'version' => '3.0.0']),
                'status' => 1
            ]
        ];

        $service = new ConflictService($this->registry, $this->registryService);

        $this->assertTrue($service->hasConflicts());
        $conflicts = $service->getConflicts();
        $this->assertCount(1, $conflicts);
        $this->assertEquals('legacy_module', $conflicts[0]['code']);
        $this->assertEquals('missing_in_file', $conflicts[0]['type']);
        $this->assertFalse($conflicts[0]['in_file']);
        $this->assertTrue($conflicts[0]['in_db']);
    }

    public function testDetectsConfigVersionMismatch()
    {
        $this->registryService->upsert([
            'code' => 'cfg_module',
            'name' => 'Config Module',
            'version' => '1.0.0'
        ]);

        $this->ocmModulesRows = [
            [
                'code' => 'cfg_module',
                'name' => 'Config Module',
                'installed_version' => '1.0.0',
                'metadata_json' => json_encode(['code' => 'cfg_module', 'version' => '1.0.0']),
                'status' => 1
            ]
        ];

        // Setting in DB config has 1.5.0
        $this->settingRows = [
            ['value' => '1.5.0']
        ];

        $service = new ConflictService($this->registry, $this->registryService);

        $conflicts = $service->getConflicts();
        $this->assertCount(1, $conflicts);
        $this->assertEquals('cfg_module', $conflicts[0]['code']);
        $this->assertEquals('config_mismatch', $conflicts[0]['type']);
        $this->assertEquals('1.5.0', $conflicts[0]['config_version']);
    }

    public function testResolveConflictTrustFileUpdatesDatabase()
    {
        $this->registryService->upsert([
            'code' => 'sync_module',
            'name' => 'Sync Module',
            'version' => '2.5.0'
        ]);

        $this->ocmModulesRows = [
            [
                'code' => 'sync_module',
                'name' => 'Sync Module',
                'installed_version' => '1.0.0',
                'metadata_json' => json_encode(['code' => 'sync_module', 'version' => '1.0.0']),
                'status' => 1
            ]
        ];

        $service = new ConflictService($this->registry, $this->registryService);

        $res = $service->resolveConflict('sync_module', 'file');

        $this->assertTrue($res['success']);

        // Check that an INSERT ... ON DUPLICATE KEY UPDATE query was run on ocm_modules
        $found = false;
        foreach ($this->executedQueries as $query) {
            if (strpos($query, 'INSERT INTO `' . DB_PREFIX . 'ocm_modules`') !== false && strpos($query, '2.5.0') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected query inserting/updating ocm_modules with file version');
    }

    public function testResolveConflictTrustFileDeletesFromDbWhenAbsentInFile()
    {
        file_put_contents($this->registryPath, json_encode(['schema_version' => 1, 'modules' => new \stdClass()]));

        $this->ocmModulesRows = [
            [
                'code' => 'orphan_module',
                'name' => 'Orphan Module',
                'installed_version' => '1.0.0',
                'metadata_json' => '{}',
                'status' => 1
            ]
        ];

        $service = new ConflictService($this->registry, $this->registryService);

        $res = $service->resolveConflict('orphan_module', 'file');

        $this->assertTrue($res['success']);

        $found = false;
        foreach ($this->executedQueries as $query) {
            if (strpos($query, 'DELETE FROM `' . DB_PREFIX . 'ocm_modules` WHERE `code` = \'orphan_module\'') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected DELETE query for orphan module');
    }

    public function testResolveConflictTrustDbUpdatesModulesJson()
    {
        $this->registryService->upsert([
            'code' => 'db_trust_module',
            'name' => 'DB Trust Module',
            'version' => '1.0.0'
        ]);

        $this->ocmModulesRows = [
            [
                'code' => 'db_trust_module',
                'name' => 'DB Trust Module',
                'installed_version' => '3.2.1',
                'source' => 'gdt_updater',
                'metadata_json' => json_encode([
                    'code' => 'db_trust_module',
                    'name' => 'DB Trust Module',
                    'version' => '3.2.1'
                ]),
                'status' => 1
            ]
        ];

        $service = new ConflictService($this->registry, $this->registryService);

        $res = $service->resolveConflict('db_trust_module', 'db');

        $this->assertTrue($res['success']);

        // Check modules.json was updated with version from DB
        $updated = $this->registryService->getModuleByCode('db_trust_module');
        $this->assertNotNull($updated);
        $this->assertEquals('3.2.1', $updated['version']);
    }

    public function testResolveConflictTrustDbRemovesFromModulesJsonWhenAbsentInDb()
    {
        $this->registryService->upsert([
            'code' => 'untracked_module',
            'name' => 'Untracked Module',
            'version' => '1.0.0'
        ]);

        $this->ocmModulesRows = [];

        $service = new ConflictService($this->registry, $this->registryService);

        $res = $service->resolveConflict('untracked_module', 'db');

        $this->assertTrue($res['success']);

        $updated = $this->registryService->getModuleByCode('untracked_module');
        $this->assertNull($updated);
    }

    public function testResolveBatch()
    {
        $this->registryService->upsert([
            'code' => 'mod_a',
            'name' => 'Mod A',
            'version' => '1.0.0'
        ]);
        $this->registryService->upsert([
            'code' => 'mod_b',
            'name' => 'Mod B',
            'version' => '1.0.0'
        ]);

        $this->ocmModulesRows = [
            [
                'code' => 'mod_a',
                'name' => 'Mod A',
                'installed_version' => '2.0.0',
                'source' => 'gdt_updater',
                'metadata_json' => json_encode(['code' => 'mod_a', 'version' => '2.0.0']),
                'status' => 1
            ],
            [
                'code' => 'mod_b',
                'name' => 'Mod B',
                'installed_version' => '2.0.0',
                'source' => 'gdt_updater',
                'metadata_json' => json_encode(['code' => 'mod_b', 'version' => '2.0.0']),
                'status' => 1
            ]
        ];

        $service = new ConflictService($this->registry, $this->registryService);

        $result = $service->resolveBatch([
            'mod_a' => 'db',
            'mod_b' => 'file'
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['resolved_count']);

        // mod_a trusted db -> version in modules.json should be 2.0.0
        $modA = $this->registryService->getModuleByCode('mod_a');
        $this->assertEquals('2.0.0', $modA['version']);

        // mod_b trusted file -> query updating DB to 1.0.0 was executed
        $foundModBUpdate = false;
        foreach ($this->executedQueries as $query) {
            if (strpos($query, 'mod_b') !== false && strpos($query, '1.0.0') !== false) {
                $foundModBUpdate = true;
                break;
            }
        }
        $this->assertTrue($foundModBUpdate);
    }
}
