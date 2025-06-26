<?php
/**
 * Тестовый скрипт для проверки API сервера обновлений
 * 
 * Используйте этот скрипт для тестирования работы вашего сервера обновлений
 */

// Настройки сервера
$server_url = 'http://localhost/your-opencart-site'; // Замените на URL вашего сайта
$api_key = 'your-api-key-here'; // Замените на ваш API ключ

echo "=== Тест API сервера обновлений GDT ===\n\n";

// Тест 1: Получение списка модулей
echo "1. Тестируем получение списка модулей...\n";
$response = makeRequest($server_url . '/index.php?route=gdt_update_server/modules', array(
    'api_key' => $api_key
));

if ($response) {
    echo "✓ Успешно получен список модулей:\n";
    if (isset($response['modules'])) {
        foreach ($response['modules'] as $module) {
            echo "  - {$module['name']} ({$module['code']}) v{$module['version']}\n";
        }
    }
} else {
    echo "✗ Ошибка получения списка модулей\n";
}

echo "\n";

// Тест 2: Проверка обновлений
echo "2. Тестируем проверку обновлений...\n";
$response = makeRequest($server_url . '/index.php?route=gdt_update_server/check', array(
    'api_key' => $api_key,
    'code' => 'gdt_updater',
    'version' => '1.0.0'
));

if ($response) {
    echo "✓ Ответ сервера:\n";
    echo "  Статус: " . ($response['status'] ?? 'неизвестно') . "\n";
    if (isset($response['version'])) {
        echo "  Доступная версия: {$response['version']}\n";
    }
} else {
    echo "✗ Ошибка проверки обновлений\n";
}

echo "\n";

// Тест 3: Попытка скачивания (только проверяем ответ, не сохраняем файл)
echo "3. Тестируем endpoint загрузки...\n";
$headers = makeRequestHeaders($server_url . '/index.php?route=gdt_update_server/download', array(
    'api_key' => $api_key,
    'code' => 'gdt_updater'
));

if ($headers) {
    echo "✓ Endpoint загрузки отвечает\n";
    echo "  HTTP код: " . $headers['http_code'] . "\n";
    echo "  Content-Type: " . ($headers['content_type'] ?? 'неизвестно') . "\n";
} else {
    echo "✗ Ошибка обращения к endpoint загрузки\n";
}

echo "\n=== Тест завершен ===\n";

/**
 * Выполнить HTTP запрос и получить JSON ответ
 */
function makeRequest($url, $data) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GDT Update Test Script');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        echo "CURL ошибка: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        } else {
            echo "Ошибка парсинга JSON: " . json_last_error_msg() . "\n";
            echo "Ответ сервера: " . substr($response, 0, 200) . "...\n";
        }
    } else {
        echo "HTTP ошибка: $http_code\n";
        if ($response) {
            echo "Ответ: " . substr($response, 0, 200) . "...\n";
        }
    }
    
    return false;
}

/**
 * Получить только заголовки ответа
 */
function makeRequestHeaders($url, $data) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GDT Update Test Script');
    
    curl_exec($ch);
    
    $info = array(
        'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'content_type' => curl_getinfo($ch, CURLINFO_CONTENT_TYPE)
    );
    
    if (curl_error($ch)) {
        echo "CURL ошибка: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    return $info;
}
