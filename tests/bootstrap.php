<?php

/**
 * PHPUnit Bootstrap файл
 * Ініціалізує необхідне оточення для тестування
 */

// Підключення автозавантажувача Composer
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../upload/system/library/gbitstudio/modules/services/moduleregistryservice.php';

// Встановлення констант OpenCart, якщо вони ще не визначені
if (!defined('DIR_APPLICATION')) {
    define('DIR_APPLICATION', sys_get_temp_dir() . '/opencart/admin/');
}
if (!defined('DIR_CATALOG')) {
    define('DIR_CATALOG', sys_get_temp_dir() . '/opencart/catalog/');
}
if (!defined('DIR_IMAGE')) {
    define('DIR_IMAGE', sys_get_temp_dir() . '/opencart/image/');
}
if (!defined('DIR_SYSTEM')) {
    define('DIR_SYSTEM', sys_get_temp_dir() . '/opencart/system/');
}
if (!defined('DIR_STORAGE')) {
    define('DIR_STORAGE', sys_get_temp_dir() . '/opencart/storage/');
}
if (!defined('DIR_UPLOAD')) {
    define('DIR_UPLOAD', sys_get_temp_dir() . '/opencart/storage/upload/');
}
if (!defined('DB_PREFIX')) {
    define('DB_PREFIX', 'oc_');
}

if (!class_exists('Log')) {
    class Log {
        public function __construct($filename = '') {}
        public function write($message) {}
    }
}

if (!class_exists('Loader')) {
    class Loader {
        public function controller($route, $data = []) {}
        public function model($route) {}
        public function view($route, $data = []) {}
        public function language($route) {}
    }
}

if (!class_exists('Controller')) {
    abstract class Controller {
        protected $registry;
        public function __construct($registry = null) {
            $this->registry = $registry;
        }
        public function __get($key) {
            return $this->registry ? $this->registry->get($key) : null;
        }
    }
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

@mkdir(DIR_SYSTEM . 'library', 0777, true);
if (!file_exists(DIR_SYSTEM . 'library/gbitstudio')) {
    @symlink(realpath(__DIR__ . '/../upload/system/library/gbitstudio'), DIR_SYSTEM . 'library/gbitstudio');
}

require_once __DIR__ . '/../upload/system/library/gbitstudio/modules/controllers/GDTBaseController.php';
require_once __DIR__ . '/../upload/admin/controller/extension/module/gdt_install_modules.php';

// Завантаження Mockery для підтримки tearDown
if (class_exists('Mockery')) {
    // Реєстрація глобального слухача для закриття Mockery після кожного тесту
    // Це буде виконано в BaseTestCase::tearDown()
}

echo "PHPUnit bootstrap completed\n";
