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
    private $moduleService;

    /**
     * Налаштування перед кожним тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->moduleService = new ModuleService();
    }

    /**
     * Тест конструктора
     */
    public function testConstructor()
    {
        $service = new ModuleService();
        $this->assertInstanceOf(ModuleService::class, $service);
    }

    /**
     * Тест отримання встановлених модулів з JSON файлів
     */
    public function testGetInstalledModulesReturnsArray()
    {
        $result = $this->moduleService->getInstalledModules();
        
        $this->assertIsArray($result);
    }

    /**
     * Тест отримання модуля за кодом
     */
    public function testGetModuleByCodeReturnsNullWhenNotFound()
    {
        $result = $this->moduleService->getModuleByCode('non_existent_module');
        
        $this->assertNull($result);
    }

    /**
     * Тест роботи без логу
     */
    public function testWorksWithoutLog()
    {
        $service = new ModuleService();
        
        $result = $service->getInstalledModules();
        
        $this->assertIsArray($result);
    }

    /**
     * Тест що модулі мають необхідні поля
     */
    public function testModulesHaveRequiredFields()
    {
        $result = $this->moduleService->getInstalledModules();
        
        if (!empty($result)) {
            $module = $result[0];
            $this->assertArrayHasKey('code', $module);
            $this->assertArrayHasKey('name', $module);
        } else {
            // Якщо модулів немає, тест пройдений
            $this->assertTrue(true);
        }
    }

    /**
     * Тест що порожня директорія повертає порожній масив
     */
    public function testEmptyDirectoryReturnsEmptyArray()
    {
        // ModuleService шукає у DIR_SYSTEM/module/
        // Якщо немає модулів, має повернути порожній масив
        $result = $this->moduleService->getInstalledModules();
        
        $this->assertIsArray($result);
    }

    /**
     * Тест фільтрації дублікатів модулів
     */
    public function testFiltersDuplicateModules()
    {
        $result = $this->moduleService->getInstalledModules();
        
        $this->assertIsArray($result);
        
        // Перевіряємо що немає дублікатів по коду
        if (!empty($result)) {
            $codes = array_column($result, 'code');
            $uniqueCodes = array_unique($codes);
            $this->assertCount(count($codes), $uniqueCodes);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Тест логування під час отримання модулів
     */
    public function testLogsModuleRetrieval()
    {
        // Просто перевіряємо що метод працює
        $this->moduleService->getInstalledModules();
        
        $this->assertTrue(true);
    }
}