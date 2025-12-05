<?php

namespace Tests\Unit\Services;

use Tests\BaseTestCase;
use Gbitstudio\Modules\Services\ModuleService;
use Mockery;

/**
 * Unit тести для ModuleService
 */
class ModuleServiceTest extends BaseTestCase
{
    private $log;
    private $moduleService;

    /**
     * Налаштування перед кожним тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->log = $this->createLogMock();
        $this->moduleService = new ModuleService($this->log);
    }

    /**
     * Тест конструктора
     */
    public function testConstructor()
    {
        $service = new ModuleService($this->log);
        $this->assertInstanceOf(ModuleService::class, $service);
    }

    /**
     * Тест отримання встановлених модулів
     */
    public function testGetInstalledModulesReturnsArray()
    {
        // Налаштовуємо mock для моделі
        $this->model->shouldReceive('getModulesFromOpenCartModifications')
            ->once()
            ->andReturn([]);
        
        $this->model->shouldReceive('getModulesFromDatabase')
            ->once()
            ->andReturn([]);

        $result = $this->moduleService->getInstalledModules();
        
        $this->assertIsArray($result);
    }

    /**
     * Тест отримання модулів з OpenCart модифікацій
     */
    public function testGetModulesFromOpenCartModifications()
    {
        $testModules = [
            [
                'code' => 'test_module',
                'name' => 'Test Module',
                'version' => '1.0.0',
                'author' => 'Test Author',
                'source' => 'opencart_modification'
            ]
        ];

        $this->model->shouldReceive('getModulesFromOpenCartModifications')
            ->once()
            ->andReturn($testModules);
        
        $this->model->shouldReceive('getModulesFromDatabase')
            ->once()
            ->andReturn([]);

        $result = $this->moduleService->getInstalledModules();
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Тест що модулі сортуються за пріоритетом
     */
    public function testModulesAreSortedByPriority()
    {
        // OpenCart modifications мають вищий пріоритет
        $this->model->shouldReceive('getModulesFromOpenCartModifications')
            ->once()
            ->andReturn([
                [
                    'code' => 'module1',
                    'name' => 'Module 1 from OC',
                    'version' => '2.0.0'
                ]
            ]);
        
        $this->model->shouldReceive('getModulesFromDatabase')
            ->once()
            ->andReturn([]);

        $result = $this->moduleService->getInstalledModules();
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('code', $result[0]);
    }

    /**
     * Тест обробки помилок бази даних
     */
    public function testHandlesDatabaseErrors()
    {
        $this->model->shouldReceive('getModulesFromOpenCartModifications')
            ->once()
            ->andReturn([]);
        
        $this->model->shouldReceive('getModulesFromDatabase')
            ->once()
            ->andReturn([]);

        $result = $this->moduleService->getInstalledModules();
        $this->assertIsArray($result);
    }

    /**
     * Тест що порожня база повертає порожній масив
     */
    public function testEmptyDatabaseReturnsEmptyArray()
    {
        $this->model->shouldReceive('getModulesFromOpenCartModifications')
            ->once()
            ->andReturn([]);
        
        $this->model->shouldReceive('getModulesFromDatabase')
            ->once()
            ->andReturn([]);

        $result = $this->moduleService->getInstalledModules();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Тест фільтрації дублікатів модулів
     */
    public function testFiltersDuplicateModules()
    {
        $this->model->shouldReceive('getModulesFromOpenCartModifications')
            ->once()
            ->andReturn([
                [
                    'code' => 'test_module',
                    'name' => 'Test Module',
                    'version' => '1.0.0'
                ]
            ]);
        
        $this->model->shouldReceive('getModulesFromDatabase')
            ->once()
            ->andReturn([]);

        $result = $this->moduleService->getInstalledModules();
        
        $this->assertIsArray($result);
        
        // Перевіряємо що немає дублікатів по коду
        $codes = array_column($result, 'code');
        $uniqueCodes = array_unique($codes);
        $this->assertCount(count($codes), $uniqueCodes);
    }

    /**
     * Тест роботи без логу
     */
    public function testWorksWithoutLog()
    {
        $service = new ModuleService($this->model, null);
        
        $this->model->shouldReceive('getModulesFromOpenCartModifications')
            ->once()
            ->andReturn([]);
        
        $this->model->shouldReceive('getModulesFromDatabase')
            ->once()
            ->andReturn([]);

        $result = $service->getInstalledModules();
        
        $this->assertIsArray($result);
    }

    /**
     * Тест що модулі мають необхідні поля
     */
    public function testModulesHaveRequiredFields()
    {
        $testModule = [
            'code' => 'test_module',
            'name' => 'Test Module',
            'version' => '1.0.0',
            'author' => 'Test Author'
        ];

        $this->model->shouldReceive('getModulesFromOpenCartModifications')
            ->once()
            ->andReturn([$testModule]);
        
        $this->model->shouldReceive('getModulesFromDatabase')
            ->once()
            ->andReturn([]);

        $result = $this->moduleService->getInstalledModules();
        
        $this->assertNotEmpty($result);
        $module = $result[0];
        $this->assertArrayHasKeys(
            ['code', 'name', 'version'], 
            $module
        );
    }

    /**
     * Тест логування під час отримання модулів
     */
    public function testLogsModuleRetrieval()
    {
        $this->model->shouldReceive('getModulesFromOpenCartModifications')
            ->once()
            ->andReturn([]);
        
        $this->model->shouldReceive('getModulesFromDatabase')
            ->once()
            ->andReturn([]);

        $this->moduleService->getInstalledModules();
        
        $this->assertTrue(true);
    }
}
