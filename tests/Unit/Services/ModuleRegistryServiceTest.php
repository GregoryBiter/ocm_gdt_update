<?php

namespace Tests\Unit\Services;

use Gbitstudio\Modules\Services\ModuleRegistryService;
use Gbitstudio\Modules\Services\ModuleService;
use Gbitstudio\Modules\Services\InstallService;
use Gbitstudio\Modules\Services\AutoUpdateService;
use Mockery;
use Tests\BaseTestCase;

require_once __DIR__ . '/../../../upload/system/library/gbitstudio/modules/services/moduleregistryservice.php';

class ModuleRegistryServiceTest extends BaseTestCase
{
    private $dir;
    private $registry;
    private $service;
    private $queries = [];
    private $rows = [];
    private $table = false;
    private $failImport = false;
    private $savedAuto;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gdt-registry-' . uniqid();
        mkdir($this->dir);
        $this->registry = Mockery::mock('Registry');
        $db = Mockery::mock('DB');
        $db->shouldReceive('escape')->andReturnUsing('addslashes');
        $db->shouldReceive('query')->andReturnUsing(function ($sql) {
            $this->queries[] = $sql;
            $rows = [];
            if ($sql === 'SHOW TABLES') {
                $rows = $this->table ? [['table' => DB_PREFIX . 'ocm_modules']] : [];
            } elseif (strpos($sql, 'SELECT * FROM `' . DB_PREFIX . 'ocm_modules`') === 0) {
                if ($this->failImport) {
                    throw new \RuntimeException('database unavailable');
                }
                $rows = $this->rows;
            } elseif (strpos($sql, 'SELECT `file_path`') === 0) {
                $rows = [['file_path' => 'system/gdt_registry_fixture.txt']];
            }
            return (object)['rows' => $rows, 'row' => $rows[0] ?? [], 'num_rows' => count($rows)];
        });
        $this->registry->shouldReceive('get')->with('db')->andReturn($db);
        $load = Mockery::mock('Loader');
        $load->shouldReceive('model')->andReturnNull();
        $load->shouldReceive('controller')->andReturnNull();
        $this->registry->shouldReceive('get')->with('load')->andReturn($load);
        $config = Mockery::mock('Config');
        $config->shouldReceive('get')->andReturnUsing(function ($key) {
            return $key === 'module_gdt_updater_auto_modules' ? ['fixture', 'other'] : null;
        });
        $config->shouldReceive('set')->andReturnNull();
        $this->registry->shouldReceive('get')->with('config')->andReturn($config);
        $extension = Mockery::mock('Model');
        $extension->shouldReceive('addExtensionInstall')->andReturn(1);
        $extension->shouldReceive('addExtensionPath')->andReturnNull();
        $this->registry->shouldReceive('get')->with('model_setting_extension')->andReturn($extension);
        $modification = Mockery::mock('Model');
        $modification->shouldReceive('getModificationByCode')->andReturnNull();
        $this->registry->shouldReceive('get')->with('model_setting_modification')->andReturn($modification);
        $settings = Mockery::mock('Model');
        $settings->shouldReceive('editSettingValue')->andReturnUsing(function ($group, $key, $value) {
            $this->savedAuto = $value;
        });
        $this->registry->shouldReceive('get')->with('model_setting_setting')->andReturn($settings);
        $this->registry->shouldReceive('get')->with('model_extension_module_gdt_updater')->andReturn(null);
        $this->service = new ModuleRegistryService($this->registry, $this->path());
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        @unlink(DIR_SYSTEM . 'gdt_registry_fixture.txt');
        parent::tearDown();
    }

    private function path()
    {
        return $this->dir . '/modules.json';
    }

    private function removeDir($path)
    {
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            $child = $path . '/' . $entry;
            is_dir($child) ? $this->removeDir($child) : unlink($child);
        }
        rmdir($path);
    }

    private function zip(array $files)
    {
        $path = $this->dir . '/fixture.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        return $path;
    }

    public function testEmptyObjectNeverReimports()
    {
        $this->assertSame([], $this->service->getInstalledModules());
        $this->assertInstanceOf(\stdClass::class, json_decode(file_get_contents($this->path()))->modules);
        $this->table = true;
        $this->failImport = true;
        $this->assertSame([], (new ModuleRegistryService($this->registry, $this->path()))->getInstalledModules());
        $this->assertSame(['SHOW TABLES'], $this->queries);
    }

    public function testImportUsesDatabaseVersionAndOnlyActiveRecords()
    {
        $this->table = true;
        $this->rows = [['code' => 'fixture', 'name' => 'Fixture', 'installed_version' => '2.3.4',
            'metadata_json' => '{"version":"99.0.0","creator_name":"Автор"}', 'source' => 'gdt_updater',
            'installed_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-02-01 00:00:00']];
        $record = $this->service->getModuleByCode('fixture');
        $this->assertSame('2.3.4', $record['version']);
        $this->assertSame('Автор', $record['creator_name']);
        $this->assertStringContainsString('WHERE `status` = 1', $this->queries[1]);
        $this->rows = [];
        $this->assertSame($record, $this->service->getModuleByCode('fixture'));
        $this->assertCount(2, $this->queries);
    }

    public function testFailedImportDoesNotCreateFile()
    {
        $this->table = $this->failImport = true;
        try {
            $this->service->getInstalledModules();
            $this->fail('Import must fail');
        } catch (\RuntimeException $e) {
            $this->assertSame('database unavailable', $e->getMessage());
            $this->assertFileDoesNotExist($this->path());
        }
    }

    public function testCorruptionNeverOverwritesRegistry()
    {
        foreach (
            ['{broken', '{"schema_version":2,"modules":{}}', '{"schema_version":1,"modules":[]}',
            '{"schema_version":1,"modules":{"fixture":{"code":"other","name":"Fixture","version":"1"}}}'] as $contents
        ) {
            file_put_contents($this->path(), $contents);
            try {
                $this->service->upsert(['code' => 'fixture', 'version' => '1.0.0']);
                $this->fail('Invalid JSON must fail');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Invalid', $e->getMessage());
                $this->assertSame($contents, file_get_contents($this->path()));
            }
        }
        $this->assertSame([], $this->queries);
    }

    public function testUpdatesPreserveOtherRecordsAndInstallDate()
    {
        $this->service->upsert(['code' => 'fixture', 'version' => '1.0.0', 'installed_at' => '2025-01-01 00:00:00']);
        $this->service->upsert(['code' => 'other', 'version' => '3.0.0']);
        $this->service->upsert(['code' => 'fixture', 'version' => '2.0.0']);
        $this->assertSame('2025-01-01 00:00:00', $this->service->getModuleByCode('fixture')['installed_at']);
        $this->assertSame('2.0.0', $this->service->getModuleByCode('fixture')['version']);
        $this->service->remove('fixture');
        $this->assertNull($this->service->getModuleByCode('fixture'));
        $this->assertSame('3.0.0', $this->service->getModuleByCode('other')['version']);
    }

    public function testUnavailableStorageRejectedBeforeInstallation()
    {
        $service = new ModuleRegistryService($this->registry, $this->dir . '/missing/modules.json');
        $result = (new InstallService($this->registry, $service))->installModule('/nonexistent.zip', 'fixture');
        $this->assertStringContainsString('not writable', $result);
        $this->assertSame([], $this->queries);
    }

    public function testFailedPublicationPreservesPreviousFile()
    {
        $this->service->upsert(['code' => 'fixture', 'version' => '1.0.0']);
        $before = file_get_contents($this->path());
        try {
            $this->service->upsert(['code' => 'fixture', 'version' => '2.0.0', 'name' => "\xB1\x31"]);
            $this->fail('Encoding must fail');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cannot encode', $e->getMessage());
            $this->assertSame($before, file_get_contents($this->path()));
        }
    }

    public function testNoDiscoveryOrVersionHealing()
    {
        $this->service->upsert(['code' => 'fixture', 'version' => '0.0.0']);
        $this->queries = [];
        $service = new ModuleService($this->registry, $this->service);
        $this->assertSame('0.0.0', $service->getInstalledModules()[0]['version']);
        $this->assertNull($service->getModuleByCode('banner'));
        $this->assertCount(1, $this->queries);
        $this->assertStringContainsString('ocm_module_files', $this->queries[0]);
    }

    public function testConcurrentWritersKeepAllRecords()
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires pcntl');
        }
        $this->service->getInstalledModules();
        $children = [];
        for ($i = 0; $i < 4; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    for ($j = 0; $j < 10; $j++) {
                        $this->service->upsert(['code' => 'worker_' . $i . '_' . $j, 'version' => '1.0.0']);
                    }
                    exit(0);
                } catch (\Throwable $e) {
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        $this->assertCount(40, $this->service->getInstalledModules());
    }

    public function testInstallUpdateDeleteLifecycle()
    {
        $installer = new InstallService($this->registry, $this->service);
        foreach (['1.2.3', '2.0.0'] as $version) {
            $archive = $this->zip(['opencart-module.json' => json_encode(['code' => 'fixture', 'name' => 'Fixture', 'version' => $version]),
                'upload/system/gdt_registry_fixture.txt' => $version]);
            $this->assertTrue($installer->installModule($archive, 'fixture', ['version' => '9.0.0', '_downloaded_version' => '9.0.0']));
            $this->assertSame($version, $this->service->getModuleByCode('fixture')['version']);
            $this->assertSame($version, file_get_contents(DIR_SYSTEM . 'gdt_registry_fixture.txt'));
        }
        $this->assertTrue($installer->uninstallModule('fixture'));
        $this->assertNull($this->service->getModuleByCode('fixture'));
        $this->assertFileDoesNotExist(DIR_SYSTEM . 'gdt_registry_fixture.txt');
        $this->assertSame(['other'], $this->savedAuto);
    }

    public function testFailedInstallScriptKeepsPreviousRegistry()
    {
        $this->service->upsert(['code' => 'fixture', 'version' => '1.0.0']);
        $before = file_get_contents($this->path());
        $archive = $this->zip(['install.php' => '<?php throw new \\RuntimeException("fixture script failed");']);
        $result = (new InstallService($this->registry, $this->service))->installModule($archive, 'fixture');
        $this->assertStringContainsString('fixture script failed', $result);
        $this->assertSame($before, file_get_contents($this->path()));
    }

    public function testUnknownCodeCannotBeDeleted()
    {
        $result = (new InstallService($this->registry, $this->service))->uninstallModule('unknown');
        $this->assertStringContainsString('not registered', $result);
        $this->assertSame(['SHOW TABLES'], $this->queries);
    }

    public function testVersionFallbackRequiresDownloadedRelease()
    {
        $installer = new InstallService($this->registry, $this->service);
        $method = new \ReflectionMethod($installer, 'resolveVersion');
        $this->assertSame('0.0.0', $method->invoke($installer, ['version' => '99.0.0'], $this->dir, 'fixture', ['version' => '99.0.0']));
        $this->assertSame('2.5.0', $method->invoke($installer, [], $this->dir, 'fixture', ['_downloaded_version' => 'v2.5.0']));
        file_put_contents($this->dir . '/install.xml', '<modification><version>1.3.0</version></modification>');
        $this->assertSame('1.3.0', $method->invoke($installer, [], $this->dir, 'fixture', ['_downloaded_version' => '2.5.0']));
    }

    public function testUnknownVersionNeverAutoUpdates()
    {
        $this->service->upsert(['code' => 'fixture', 'version' => '0.0.0']);
        $update = Mockery::mock();
        $update->shouldNotReceive('checkModuleUpdate');
        $installer = Mockery::mock();
        $installer->shouldNotReceive('installModule');
        $config = Mockery::mock();
        $config->shouldReceive('get')->with('module_gdt_updater_server')->andReturn('https://example.test');
        $config->shouldReceive('get')->with('module_gdt_updater_api_key')->andReturn('');
        $config->shouldReceive('get')->with('module_gdt_updater_auto_modules')->andReturn(['fixture']);
        $cache = Mockery::mock();
        $cache->shouldReceive('set')->once();
        $service = new AutoUpdateService(new ModuleService($this->registry, $this->service), $update, $installer, $config, $cache);
        $this->assertSame(['updated' => [], 'errors' => []], $service->autoCheckAndUpdate());
    }
    public function testFinalPublicationFailureKeepsOldVersionAndRestoresFile()
    {
        $this->service->upsert(['code' => 'fixture', 'version' => '1.0.0']);
        $before = file_get_contents($this->path());
        file_put_contents(DIR_SYSTEM . 'gdt_registry_fixture.txt', 'old');
        $failing = Mockery::mock(ModuleRegistryService::class);
        $failing->shouldReceive('assertWritable')->once();
        $failing->shouldReceive('upsert')->once()->andThrow(new \RuntimeException('cannot publish registry'));
        $archive = $this->zip(['opencart-module.json' => '{"code":"fixture","version":"2.0.0"}',
            'upload/system/gdt_registry_fixture.txt' => 'new']);
        $result = (new InstallService($this->registry, $failing))->installModule($archive, 'fixture');
        $this->assertStringContainsString('cannot publish registry', $result);
        $this->assertSame($before, file_get_contents($this->path()));
        $this->assertSame('old', file_get_contents(DIR_SYSTEM . 'gdt_registry_fixture.txt'));
    }

    public function testStoragePermissionsAndLockFailure()
    {
        $this->service->getInstalledModules();
        $before = file_get_contents($this->path());
        chmod($this->dir, 0555);
        clearstatcache();
        if (!is_writable($this->dir)) {
            try {
                $this->service->assertWritable();
                $this->fail('Read-only storage must fail');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('not writable', $e->getMessage());
            } finally {
                chmod($this->dir, 0755);
            }
        } else {
            chmod($this->dir, 0755);
        }
        unlink($this->path() . '.lock');
        mkdir($this->path() . '.lock');
        try {
            $this->service->upsert(['code' => 'other', 'version' => '1.0.0']);
            $this->fail('Invalid lock path must fail');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cannot lock', $e->getMessage());
            $this->assertSame($before, file_get_contents($this->path()));
        }
    }

    public function testDownloadVersionComesFromResponseFilename()
    {
        $service = new \Gbitstudio\Modules\Services\UpdateService($this->registry);
        $method = new \ReflectionMethod($service, 'downloadedVersion');
        $this->assertSame('2.5.0', $method->invoke($service, 'attachment; filename="fixture_2.5.0.zip"', 'fixture'));
        $this->assertSame('', $method->invoke($service, 'attachment; filename="other_2.5.0.zip"', 'fixture'));
        $this->assertSame('', $method->invoke($service, 'attachment; filename="fixture.zip"', 'fixture'));
        $this->assertSame('', $method->invoke($service, '', 'fixture'));
    }
    public function testStoreBadgesUseRegistryMembershipAndVersion()
    {
        $this->service->upsert(['code' => 'fixture', 'version' => '1.2.3']);
        $catalog = new \Gbitstudio\Modules\Services\ModuleCatalogService($this->registry->get('config'));
        $modules = $catalog->markInstalledModules([
            ['code' => 'fixture', 'version' => '2.0.0'], ['code' => 'banner', 'version' => '1.0.0']
        ], (new ModuleService($this->registry, $this->service))->getInstalledModules());
        $this->assertTrue($modules[0]['installed']);
        $this->assertSame('1.2.3', $modules[0]['installed_version']);
        $this->assertTrue($modules[0]['update_available']);
        $this->assertFalse($modules[1]['installed']);
    }
}
