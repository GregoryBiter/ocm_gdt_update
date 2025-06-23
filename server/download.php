<?php
/**
 * Скрипт загрузки обновлений
 * 
 * Принимает POST-запрос с параметрами:
 * - code: код модуля
 * - version: запрашиваемая версия модуля
 * 
 * Возвращает ZIP-архив с обновлением или JSON-ответ с ошибкой
 */

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

// Получаем параметры запроса
$code = $_POST['code'];
$version = $_POST['version'];

// Путь к каталогу с модулями
$modules_dir = __DIR__ . '/modules/';

// Проверяем, существует ли ZIP-файл с обновлением
$update_file = $modules_dir . $code . '/' . $code . '_' . $version . '.zip';

if (!file_exists($update_file)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Update file not found']);
    exit;
}

// Отправляем ZIP-архив клиенту
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $code . '_' . $version . '.zip"');
header('Content-Length: ' . filesize($update_file));
header('Pragma: no-cache');
header('Expires: 0');

readfile($update_file);
