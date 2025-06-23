<?php
/**
 * Скрипт проверки наличия обновлений
 * 
 * Принимает POST-запрос с параметрами:
 * - code: код модуля
 * - version: текущая версия модуля
 * 
 * Возвращает JSON-ответ:
 * - status: 'up_to_date' или 'update_available'
 * - version: новая версия (если доступна)
 * - description: описание обновления (если доступно)
 */

// Настройки
header('Content-Type: application/json');

// Проверяем, что запрос пришел методом POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Проверяем наличие обязательных параметров
if (!isset($_POST['code']) || !isset($_POST['version'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Получаем параметры запроса
$code = $_POST['code'];
$current_version = $_POST['version'];

// Путь к каталогу с модулями
$modules_dir = __DIR__ . '/modules/';

// Проверяем, существует ли файл с информацией о модуле
$module_info_file = $modules_dir . $code . '/info.json';

if (!file_exists($module_info_file)) {
    echo json_encode(['status' => 'up_to_date']);
    exit;
}

// Получаем информацию о модуле
$module_info = json_decode(file_get_contents($module_info_file), true);

if (!$module_info || !isset($module_info['version'])) {
    echo json_encode(['status' => 'up_to_date']);
    exit;
}

// Сравниваем версии
if (version_compare($module_info['version'], $current_version, '>')) {
    // Доступно обновление
    echo json_encode([
        'status' => 'update_available',
        'version' => $module_info['version'],
        'description' => isset($module_info['description']) ? $module_info['description'] : ''
    ]);
} else {
    // Обновление не требуется
    echo json_encode(['status' => 'up_to_date']);
}
