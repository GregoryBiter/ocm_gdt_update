<?php

namespace Tests;

use Gbitstudio\OpenCartTestUtils\BaseOpenCartTestCase;
use Mockery;

/**
 * Базовий клас для всіх тестів
 * Надає спільну функціональність та допоміжні методи
 */
abstract class BaseTestCase extends BaseOpenCartTestCase
{
    /**
     * Создает временный ZIP файл для тестирования
     * 
     * @param string $filename Имя файла
     * @param array $files Файлы для добавления в архив [путь => содержимое]
     * @return string Путь к созданному файлу
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

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
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
