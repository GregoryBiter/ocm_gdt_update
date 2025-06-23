<?php
/**
 * GDT Update Server
 * Главная страница сервера обновлений
 */

// Запрещаем прямой доступ к этому файлу
if (!defined('GDT_SERVER')) {
    define('GDT_SERVER', true);
}

// Список модулей
$modules = [];

// Сканируем директорию с модулями
$modules_dir = __DIR__ . '/modules/';
$module_folders = glob($modules_dir . '*', GLOB_ONLYDIR);

foreach ($module_folders as $folder) {
    // Получаем код модуля из имени папки
    $code = basename($folder);
    
    // Проверяем наличие файла info.json
    $info_file = $folder . '/info.json';
    
    if (file_exists($info_file)) {
        $info = json_decode(file_get_contents($info_file), true);
        
        if ($info && isset($info['name'], $info['version'])) {
            $modules[] = [
                'code' => $code,
                'name' => $info['name'],
                'version' => $info['version'],
                'description' => isset($info['description']) ? $info['description'] : ''
            ];
        }
    }
}

// Выводим HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GDT Update Server</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        h1, h2 {
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .info {
            background-color: #e7f3fe;
            border-left: 6px solid #2196F3;
            padding: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>GDT Update Server</h1>
        
        <div class="info">
            <p>Это сервер обновлений для модулей GDT OpenCart. Он предоставляет API для проверки и загрузки обновлений.</p>
        </div>
        
        <h2>Доступные модули</h2>
        
        <?php if (empty($modules)): ?>
            <p>На сервере нет доступных модулей.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Модуль</th>
                        <th>Код</th>
                        <th>Версия</th>
                        <th>Описание</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $module): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($module['name']); ?></td>
                        <td><?php echo htmlspecialchars($module['code']); ?></td>
                        <td><?php echo htmlspecialchars($module['version']); ?></td>
                        <td><?php echo htmlspecialchars($module['description']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h2>API</h2>
        <p>Сервер предоставляет следующие API-методы:</p>
        
        <table>
            <thead>
                <tr>
                    <th>Метод</th>
                    <th>Описание</th>
                    <th>Параметры</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>check.php (POST)</td>
                    <td>Проверка наличия обновлений</td>
                    <td>code, version</td>
                </tr>
                <tr>
                    <td>download.php (POST)</td>
                    <td>Загрузка обновления</td>
                    <td>code, version</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
