<?php

/**
 * PHPUnit Bootstrap файл
 * Ініціалізує необхідне оточення для тестування
 */

// Підключення автозавантажувача Composer
require_once __DIR__ . '/../vendor/autoload.php';

use Gbitstudio\OpenCartTestUtils\OpenCartEnv;

// Ініціалізація оточення OpenCart
OpenCartEnv::initConstants();
OpenCartEnv::initBaseClasses();
if (!defined('DIR_SYSTEM')) {
    define('DIR_SYSTEM', sys_get_temp_dir() . '/opencart/system/');
}
if (!defined('DIR_STORAGE')) {
    define('DIR_STORAGE', sys_get_temp_dir() . '/opencart/storage/');
}
if (!defined('DIR_UPLOAD')) {
    define('DIR_UPLOAD', sys_get_temp_dir() . '/opencart/storage/upload/');
}

// Створення необхідних директорій для тестування
$directories = [
    DIR_APPLICATION,
    DIR_CATALOG,
    DIR_IMAGE,
    DIR_SYSTEM,
    DIR_STORAGE,
    DIR_UPLOAD
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Завантаження Mockery для підтримки tearDown
if (class_exists('Mockery')) {
    // Реєстрація глобального слухача для закриття Mockery після кожного тесту
    // Це буде виконано в BaseTestCase::tearDown()
}

echo "PHPUnit bootstrap completed\n";
