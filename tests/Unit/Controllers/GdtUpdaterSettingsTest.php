<?php

namespace Tests\Unit\Controllers;

use Mockery;
use Tests\BaseTestCase;

require_once __DIR__ . '/../../../upload/admin/controller/extension/module/gdt_updater.php';

class GdtUpdaterSettingsTest extends BaseTestCase
{
    private $registry;
    private $model;
    private $responseData;
    private $request;

    protected function setUp(): void
    {
        $this->registry = Mockery::mock('Registry');
        $load = Mockery::mock('Loader');
        $load->shouldReceive('language')->andReturn([]);
        $load->shouldReceive('model')->with('setting/setting')->andReturnNull();
        $this->model = Mockery::mock('Model');
        $this->registry->shouldReceive('get')->with('load')->andReturn($load);
        $this->registry->shouldReceive('get')->with('model_setting_setting')->andReturn($this->model);
        $config = Mockery::mock('Config');
        $config->shouldReceive('get')->andReturnUsing(function ($key) {
            return $key === 'module_gdt_updater_auto_modules' ? ['existing'] : null;
        });
        $this->registry->shouldReceive('get')->with('config')->andReturn($config);
        $language = Mockery::mock('Language');
        $language->shouldReceive('get')->andReturnUsing(function ($key) {
            return $key;
        });
        $this->registry->shouldReceive('get')->with('language')->andReturn($language);
        $user = Mockery::mock('User');
        $user->shouldReceive('hasPermission')->andReturn(true);
        $this->registry->shouldReceive('get')->with('user')->andReturn($user);
        $response = Mockery::mock('Response');
        $response->shouldReceive('addHeader')->with('Content-Type: application/json')->once();
        $response->shouldReceive('setOutput')->once()->andReturnUsing(function ($output) {
            $this->responseData = json_decode($output, true);
        });
        $this->registry->shouldReceive('get')->with('response')->andReturn($response);
        $this->request = (object)['server' => ['REQUEST_METHOD' => 'POST'], 'post' => [
            'module_gdt_updater_server_type' => 'opencart',
            'module_gdt_updater_server' => 'https://example.test/',
            'module_gdt_updater_status' => '1',
        ]];
        $this->registry->shouldReceive('get')->with('request')->andReturn($this->request);
    }

    public function testSaveLoadsModelThroughRegistryAndPreservesExistingSettings()
    {
        $this->model->shouldReceive('editSetting')->once()->with('module_gdt_updater', Mockery::on(function ($settings) {
            return $settings['module_gdt_updater_server_type'] === 'opencart'
                && $settings['module_gdt_updater_server'] === 'https://example.test/'
                && $settings['module_gdt_updater_auto_modules'] === ['existing'];
        }));
        (new \ControllerExtensionModuleGdtUpdater($this->registry))->saveSettings();
        $this->assertArrayHasKey('success', $this->responseData);
        $this->assertArrayNotHasKey('error', $this->responseData);
    }

    public function testPhpErrorsBecomeJsonResponses()
    {
        $this->model->shouldReceive('editSetting')->once()->andThrow(new \Error('database write failed'));
        (new \ControllerExtensionModuleGdtUpdater($this->registry))->saveSettings();
        $this->assertSame(['error' => 'database write failed'], $this->responseData);
    }

    public function testInvalidServerTypeCannotBeSaved()
    {
        $this->request->post['module_gdt_updater_server_type'] = 'invalid';
        $this->model->shouldNotReceive('editSetting');
        (new \ControllerExtensionModuleGdtUpdater($this->registry))->saveSettings();
        $this->assertSame(['error' => 'Unknown update server type'], $this->responseData);
    }
}
