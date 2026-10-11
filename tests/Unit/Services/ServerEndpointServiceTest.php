<?php

namespace Tests\Unit\Services;

use Gbitstudio\Modules\Services\ServerEndpointService;
use Mockery;
use Tests\BaseTestCase;

require_once __DIR__ . '/../../../upload/system/library/gbitstudio/modules/services/serverendpointservice.php';

class ServerEndpointServiceTest extends BaseTestCase
{
    private function service($type)
    {
        $config = Mockery::mock('Config');
        $config->shouldReceive('get')->with('module_gdt_updater_server_type')->andReturn($type);
        return new ServerEndpointService($config);
    }

    public function testExistingLaravelSettingsKeepTheirEndpoints()
    {
        $service = $this->service(null);
        $this->assertSame('https://example.com/updates/api/v1/modules/check', $service->build('https://example.com/updates/', 'check'));
        $this->assertSame('https://example.com/api/v1/modules/fixture/download', $service->build('https://example.com', 'download', 'fixture'));
    }

    public function testOpenCartSupportsCatalogSubdirectoriesAndExistingQuery()
    {
        $service = $this->service('opencart');
        $this->assertSame('https://example.com/shop/index.php?route=gdt_update_server%2Fapi%2FcheckBatch', $service->build('https://example.com/shop/', 'check-batch'));
        $url = $service->build('https://example.com:8443/shop/index.php?route=old&store_id=2', 'download', 'fixture');
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertSame('gdt_update_server/api/download', $params['route']);
        $this->assertSame('2', $params['store_id']);
        $this->assertSame('fixture', $params['code']);
        $this->assertSame(8443, parse_url($url, PHP_URL_PORT));
        $this->assertSame('/shop/index.php', parse_url($url, PHP_URL_PATH));
    }

    public function testInvalidOpenCartBaseIsRejected()
    {
        $this->expectException(\RuntimeException::class);
        $this->service('opencart')->build('file:///tmp/server', 'check');
    }
}
