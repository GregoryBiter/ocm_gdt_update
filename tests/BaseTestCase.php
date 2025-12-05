<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Базовий клас для всіх тестів
 * Надає спільну функціональність та допоміжні методи
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Очищення після кожного тесту
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Створює mock об'єкт Registry OpenCart
     * 
     * @return Mockery\MockInterface
     */
    protected function createRegistryMock()
    {
        $registry = Mockery::mock('Registry');
        
        // Створюємо mock для load
        $load = Mockery::mock('Loader');
        $load->shouldReceive('model')->andReturnUsing(function ($path) use ($registry) {
            $modelName = 'model_' . str_replace('/', '_', $path);
            $model = Mockery::mock('Model');
            $registry->shouldReceive('get')->with($modelName)->andReturn($model);
            return $model;
        });
        
        $registry->shouldReceive('get')->with('load')->andReturn($load);
        
        // Додаємо mock для log
        $log = $this->createLogMock();
        $registry->shouldReceive('get')->with('log')->andReturn($log);
        
        return $registry;
    }

    /**
     * Створює mock об'єкт Log OpenCart
     * 
     * @return Mockery\MockInterface
     */
    protected function createLogMock()
    {
        $log = Mockery::mock('Log');
        $log->shouldReceive('write')->andReturn(null);
        return $log;
    }

    /**
     * Створює mock об'єкт Model з методами
     * 
     * @param array $methods Масив методів та їх повернення
     * @return Mockery\MockInterface
     */
    protected function createModelMock(array $methods = [])
    {
        $model = Mockery::mock('Model');
        
        foreach ($methods as $method => $return) {
            if (is_callable($return)) {
                $model->shouldReceive($method)->andReturnUsing($return);
            } else {
                $model->shouldReceive($method)->andReturn($return);
            }
        }
        
        return $model;
    }

    /**
     * Створює тимчасовий ZIP файл для тестування
     * 
     * @param string $filename Ім'я файлу
     * @param array $files Файли для додавання до архіву [шлях => вміст]
     * @return string Шлях до створеного файлу
     */
    protected function createTestZipFile(string $filename, array $files = [])
    {
        $zipPath = sys_get_temp_dir() . '/' . $filename;
        $zip = new \ZipArchive();
        
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create ZIP file: ' . $zipPath);
        }

        foreach ($files as $path => $content) {
            $zip->addFromString($path, $content);
        }

        $zip->close();
        
        return $zipPath;
    }

    /**
     * Видаляє тимчасові файли після тестування
     * 
     * @param array $files Масив шляхів до файлів
     */
    protected function cleanupTestFiles(array $files)
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Створює тимчасову директорію для тестування
     * 
     * @param string $prefix Префікс імені директорії
     * @return string Шлях до створеної директорії
     */
    protected function createTempDirectory(string $prefix = 'phpunit_')
    {
        $tempDir = sys_get_temp_dir() . '/' . $prefix . uniqid();
        mkdir($tempDir, 0777, true);
        return $tempDir;
    }

    /**
     * Рекурсивно видаляє директорію
     * 
     * @param string $dir Шлях до директорії
     */
    protected function removeDirectory(string $dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        
        @rmdir($dir);
    }

    /**
     * Асертить що масив містить ключі
     * 
     * @param array $keys Очікувані ключі
     * @param array $array Масив для перевірки
     * @param string $message Повідомлення про помилку
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = '')
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array should have key: $key");
        }
    }
}
