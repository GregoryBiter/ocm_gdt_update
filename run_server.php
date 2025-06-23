<?php
/**
 * Простой PHP-сервер для запуска и тестирования системы обновлений
 * 
 * Запуск: php -S localhost:8000 -t server/
 * 
 * После запуска сервер будет доступен по адресу http://localhost:8000/
 */

// Текущая директория
$current_dir = __DIR__;

// Путь к серверу обновлений
$server_dir = $current_dir . '/server';

echo "=================================================================\n";
echo "| Простой PHP-сервер для системы обновлений модулей OpenCart   |\n";
echo "=================================================================\n";
echo "\n";
echo "Инструкции по запуску:\n";
echo "1. Выполните следующую команду в терминале:\n";
echo "   php -S localhost:8000 -t server/\n";
echo "\n";
echo "2. Откройте в браузере: http://localhost:8000/\n";
echo "\n";
echo "3. Для тестирования API вы можете использовать следующие URL:\n";
echo "   - Проверка обновлений: http://localhost:8000/check.php\n";
echo "   - Загрузка обновлений: http://localhost:8000/download.php\n";
echo "\n";
echo "4. Для остановки сервера нажмите Ctrl+C\n";
echo "\n";
echo "=================================================================\n";

// Проверяем наличие директории server
if (!is_dir($server_dir)) {
    echo "ОШИБКА: Директория сервера не найдена: $server_dir\n";
    exit(1);
}

// Проверяем наличие основных файлов
$required_files = ['index.php', 'check.php', 'download.php'];
foreach ($required_files as $file) {
    if (!file_exists($server_dir . '/' . $file)) {
        echo "ОШИБКА: Файл не найден: $file\n";
        exit(1);
    }
}

echo "Все необходимые файлы найдены. Сервер готов к запуску.\n";
echo "\n";
echo "Запустите команду: php -S localhost:8000 -t server/\n";
