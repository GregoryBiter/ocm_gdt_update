<?php
/**
 * Тестовий скрипт для перевірки міграції модулів
 */

// Симуляція структури даних модуля
$test_module_data = [
    'code' => 'test_module',
    'module_name' => 'Test Module',
    'version' => '1.2.3',
    'creator_name' => 'Test Author',
    'creator_email' => 'test@example.com',
    'description' => 'Test module for migration',
    'controller' => 'extension/module/test_module',
    'provider' => 'provider/module/test_module/boot',
    'author_url' => 'https://example.com',
    'files' => [
        'admin/controller/extension/module/test_module.php',
        'admin/model/extension/module/test_module.php',
        'system/library/test/module.php'
    ]
];

// Створюємо тестовий JSON файл
$test_dir = __DIR__ . '/test_output/module/test_module/';
if (!is_dir($test_dir)) {
    mkdir($test_dir, 0755, true);
}

$json_file = $test_dir . 'opencart-module.json';
$json_content = json_encode($test_module_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (file_put_contents($json_file, $json_content)) {
    echo "✅ Тестовий JSON файл створено: {$json_file}\n";
    echo "\n📄 Вміст файлу:\n";
    echo $json_content . "\n";
    
    // Перевіряємо читання
    $read_content = file_get_contents($json_file);
    $read_data = json_decode($read_content, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "\n✅ JSON валідний і може бути прочитаний\n";
        echo "\n📦 Дані модуля:\n";
        echo "   Код: {$read_data['code']}\n";
        echo "   Назва: {$read_data['module_name']}\n";
        echo "   Версія: {$read_data['version']}\n";
        echo "   Автор: {$read_data['creator_name']}\n";
        echo "   Файлів: " . count($read_data['files']) . "\n";
    } else {
        echo "\n❌ Помилка JSON: " . json_last_error_msg() . "\n";
    }
} else {
    echo "❌ Не вдалося створити файл\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Тест міграції завершено\n";
echo str_repeat('=', 50) . "\n";
