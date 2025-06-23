<?php
/**
 * Скрипт загрузки обновлений
 * 
 * Принимает POST-запрос с параметрами:
 * - code: код модуля
 * - version: запрашиваемая версия модуля
 * - client_id: идентификатор клиента
 * - api_key: ключ API для аутентификации
 * 
 * Возвращает ZIP-архив с обновлением или JSON-ответ с ошибкой
 */

// Определяем константу для доступа к конфигурации
define('GDT_SERVER', true);

// Подключаем конфигурационный файл
require_once __DIR__ . '/config.php';

// Проверяем, что запрос пришел методом POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Проверяем наличие обязательных параметров
if (!isset($_POST['code']) || !isset($_POST['version'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Проверяем параметры аутентификации
if (!isset($_POST['client_id']) || !isset($_POST['api_key'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication failed: Missing credentials']);
    exit;
}

// Проверяем валидность API-ключа
if (!validateApiKey($_POST['client_id'], $_POST['api_key'])) {
    // Логируем неудачную попытку аутентификации
    $client_ip = $_SERVER['REMOTE_ADDR'];
    logInvalidApiKeyAttempt($_POST['api_key'], $client_ip, 'download.php');
    
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication failed: Invalid credentials']);
    exit;
}

// Получаем параметры запроса
$code = $_POST['code'];
$version = $_POST['version'];

// Проверяем параметры на безопасность (защита от path traversal)
if (preg_match('/\.\.|\/|\\\\/', $code) || preg_match('/\.\.|\/|\\\\/', $version)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

// Дополнительная проверка: код и версия должны содержать только разрешенные символы
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $code) || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $version)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid characters in parameters']);
    exit;
}

// Путь к каталогу с модулями
$modules_dir = __DIR__ . '/modules/';

// Проверяем, существует ли директория модуля
$module_dir = $modules_dir . $code;
if (!is_dir($module_dir)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Module not found']);
    exit;
}

// Получаем информацию о модуле из info.json
$module_info_file = $module_dir . '/info.json';
if (!file_exists($module_info_file)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Module information not found']);
    exit;
}

// Получаем информацию о модуле
$module_info = json_decode(file_get_contents($module_info_file), true);

if (!$module_info || !isset($module_info['file'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Update file information not found']);
    exit;
}

// Проверяем, соответствует ли запрашиваемая версия версии в info.json
if (isset($module_info['version']) && $version !== $module_info['version']) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Requested version does not match available version']);
    exit;
}

// Используем имя файла из info.json
$update_filename = $module_info['file'];
$update_file = $module_dir . '/' . $update_filename;

// Проверяем, находится ли запрашиваемый файл внутри директории модулей
$real_update_path = realpath($update_file);
$real_modules_dir = realpath($modules_dir);

if (!$real_update_path || strpos($real_update_path, $real_modules_dir) !== 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid update file path']);
    exit;
}

if (!file_exists($update_file)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Update file not found']);
    exit;
}

// Отправляем ZIP-архив клиенту
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $update_filename . '"');
header('Content-Length: ' . filesize($update_file));
header('Pragma: no-cache');
header('Expires: 0');

readfile($update_file);
