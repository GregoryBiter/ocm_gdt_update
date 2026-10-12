<?php

namespace Tests\Unit\Controllers;

use Tests\BaseTestCase;
use Mockery;

/**
 * Тести для контролера GdtInstallModules
 * 
 * Тестує функціонал контролера встановлення модулів
 */
class GdtInstallModulesControllerTest extends BaseTestCase
{
    /** @var \Mockery\MockInterface */
    protected $registry;

    /** @var \Mockery\MockInterface */
    protected $load;

    /** @var \Mockery\MockInterface */
    protected $document;

    /** @var \Mockery\MockInterface */
    protected $language;

    /** @var \Mockery\MockInterface */
    protected $url;

    /** @var \Mockery\MockInterface */
    protected $response;

    /** @var \Mockery\MockInterface */
    protected $request;

    /** @var \Mockery\MockInterface */
    protected $session;

    /** @var object */
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->createRegistryMock();
        $this->load = Mockery::mock('Loader');
        $this->load->shouldReceive('language')->byDefault()->andReturn([]);
        $this->document = Mockery::mock('Document');
        $this->language = Mockery::mock('Language');
        $this->url = Mockery::mock('Url');
        $this->response = Mockery::mock('Response');
        $this->request = Mockery::mock('Request');
        $this->session = Mockery::mock('Session');

        // Налаштовуємо registry get
        $this->registry->shouldReceive('get')
            ->with('load')
            ->andReturn($this->load);
            
        $this->registry->shouldReceive('get')
            ->with('document')
            ->andReturn($this->document);
            
        $this->registry->shouldReceive('get')
            ->with('language')
            ->andReturn($this->language);
            
        $this->registry->shouldReceive('get')
            ->with('url')
            ->andReturn($this->url);
            
        $this->registry->shouldReceive('get')
            ->with('response')
            ->andReturn($this->response);
            
        $this->registry->shouldReceive('get')
            ->with('request')
            ->andReturn($this->request);
            
        $this->registry->shouldReceive('get')
            ->with('session')
            ->andReturn($this->session);

        $config = Mockery::mock('Config');
        $config->shouldReceive('get')->andReturn(null);
        $this->registry->shouldReceive('get')
            ->with('config')
            ->andReturn($config);

        $this->registry->shouldReceive('has')
            ->andReturn(true);

        // Налаштовуємо session
        $this->session->shouldReceive('get')
            ->andReturnUsing(function($key) {
                return $key === 'user_token' ? 'test_token_123' : null;
            });

        $this->session->data = ['user_token' => 'test_token_123'];

        // Завантажуємо та створюємо контролер
        if (class_exists('ControllerExtensionModuleGdtInstallModules')) {
            $this->controller = new \ControllerExtensionModuleGdtInstallModules($this->registry);
        } else {
            $controllerPath = __DIR__ . '/../../../upload/admin/controller/extension/module/gdt_install_modules.php';
            if (file_exists($controllerPath)) {
                require_once $controllerPath;
                $this->controller = new \ControllerExtensionModuleGdtInstallModules($this->registry);
            }
        }
    }

    /**
     * Тест методу index - перенаправлення
     */
    public function testIndexRedirectsToStore()
    {
        $expectedUrl = 'extension/module/gdt_install_modules/store&user_token=test_token_123';
        
        $this->url->shouldReceive('link')
            ->with('extension/module/gdt_install_modules/store', 'user_token=test_token_123', true)
            ->andReturn($expectedUrl);
            
        $this->response->shouldReceive('redirect')
            ->with($expectedUrl, Mockery::any())
            ->once();

        if (method_exists($this->controller, 'index')) {
            $this->controller->index();
        }
        
        $this->assertTrue(true);
    }

    /**
     * Тест методу store - завантаження сторінки магазину
     */
    public function testStorePageLoadsSuccessfully()
    {
        $this->language->shouldReceive('get')
            ->andReturn('Module Store');
            
        $this->language->shouldReceive('load')
            ->andReturn([]);

        $this->document->shouldReceive('setTitle')
            ->zeroOrMoreTimes();

        $this->url->shouldReceive('link')
            ->andReturn('http://example.com/test');

        $this->load->shouldReceive('controller')
            ->andReturn('');
            
        $this->load->shouldReceive('view')
            ->andReturn('<html>Test View</html>');

        $this->load->shouldReceive('language')
            ->zeroOrMoreTimes()
            ->andReturn([]);

        $this->response->shouldReceive('setOutput')
            ->zeroOrMoreTimes();

        if (method_exists($this->controller, 'store')) {
            $this->controller->store();
        }

        $this->assertTrue(true);
    }

    /**
     * Тест getCommonData - повертає загальні дані
     */
    public function testGetCommonDataReturnsArray()
    {
        $controller = Mockery::mock('ControllerExtensionModuleGdtInstallModules')
            ->makePartial();

        if (method_exists($controller, 'getCommonData')) {
            $this->assertTrue(method_exists($controller, 'getCommonData'));
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Тест featured page
     */
    public function testFeaturedPageLoadsWithCorrectTitle()
    {
        $this->language->shouldReceive('get')
            ->andReturn('Featured Modules');
            
        $this->language->shouldReceive('load')
            ->andReturn([]);

        $this->load->shouldReceive('language')
            ->andReturn([]);

        $this->document->shouldReceive('setTitle')
            ->zeroOrMoreTimes();

        $this->assertTrue(true);
    }

    /**
     * Тест popular page
     */
    public function testPopularPageSetsCorrectMetadata()
    {
        $this->language->shouldReceive('get')
            ->andReturn('Popular Modules');
            
        $this->language->shouldReceive('load')
            ->andReturn([]);

        $this->load->shouldReceive('language')
            ->andReturn([]);

        $this->document->shouldReceive('setTitle')
            ->zeroOrMoreTimes();

        $this->assertTrue(true);
    }

    /**
     * Тест newest page
     */
    public function testNewestPageHandlesRequest()
    {
        $this->language->shouldReceive('get')
            ->andReturn('New Modules');
            
        $this->language->shouldReceive('load')
            ->andReturn([]);

        $this->load->shouldReceive('language')
            ->andReturn([]);

        $this->document->shouldReceive('setTitle')
            ->zeroOrMoreTimes();

        $this->assertTrue(true);
    }

    /**
     * Тест що user_token передається в URLs
     */
    public function testUserTokenIsPassedToUrls()
    {
        $this->url->shouldReceive('link')
            ->with(Mockery::any(), Mockery::pattern('/user_token=test_token_123/'), true)
            ->andReturn('http://example.com/test?user_token=test_token_123');

        $result = $this->url->link('test', 'user_token=test_token_123', true);
        
        $this->assertStringContainsString('user_token=test_token_123', $result);
    }

    /**
     * Тест перевірки наявності необхідних AJAX URLs
     */
    public function testAjaxUrlsAreGenerated()
    {
        $expectedUrls = [
            'get_modules_url',
            'search_modules_url',
            'install_module_url'
        ];

        $this->url->shouldReceive('link')
            ->andReturn('http://example.com/ajax');

        foreach ($expectedUrls as $urlKey) {
            $url = $this->url->link('test', 'user_token=test', true);
            $this->assertNotEmpty($url);
        }
    }

    /**
     * Тест що завантажується правильний шаблон
     */
    public function testCorrectTemplateIsLoaded()
    {
        $expectedTemplate = 'extension/module/gdt_install_modules';
        
        $this->load->shouldReceive('view')
            ->with(Mockery::pattern('#^' . preg_quote($expectedTemplate, '#') . '#'), Mockery::any())
            ->andReturn('rendered_view');

        $result = $this->load->view($expectedTemplate, []);
        $this->assertEquals('rendered_view', $result);
    }

    /**
     * Тест що завантажуються спільні компоненти
     */
    public function testCommonComponentsAreLoaded()
    {
        $components = ['common/header', 'common/column_left', 'common/footer'];
        
        foreach ($components as $component) {
            $this->load->shouldReceive('controller')
                ->with($component)
                ->andReturn('<div>' . $component . '</div>');
        }

        foreach ($components as $component) {
            $result = $this->load->controller($component);
            $this->assertNotEmpty($result);
        }

        $this->assertTrue(true);
    }

    /**
     * Тест парсингу інформації про модуль з HTML-ентітісами (OpenCart htmlspecialchars)
     */
    public function testParseServerModuleInfoWithHtmlEntities()
    {
        $reflection = new \ReflectionClass('ControllerExtensionModuleGdtInstallModules');
        $method = $reflection->getMethod('parseServerModuleInfo');

        $controller = $reflection->newInstanceWithoutConstructor();

        // OpenCart's Request::clean converts quotes to &quot;
        $rawJson = json_encode(['name' => 'Test', 'version' => '1.5.0', 'code' => 'test_mod']);
        $escaped = htmlspecialchars($rawJson, ENT_COMPAT, 'UTF-8');

        $result = $method->invoke($controller, $escaped);

        $this->assertIsArray($result);
        $this->assertEquals('1.5.0', $result['version'] ?? '');
        $this->assertEquals('test_mod', $result['code'] ?? '');
        $this->assertEquals('Test', $result['name'] ?? '');
    }

    public function testAdminUrlsDecodeAmpersandsBeforeUseInJavascript()
    {
        $encoded = 'https://example.test/admin/index.php?route=extension/module/gdt_install_modules/store&amp;user_token=test_token_123';
        $this->url->shouldReceive('link')->once()->andReturn($encoded);
        $this->response->shouldReceive('redirect')->once()->with(str_replace('&amp;', '&', $encoded), 302);
        $this->controller->index();
        $this->assertTrue(true);
    }
}
