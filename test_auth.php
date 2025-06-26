<?php
/**
 * Тестовый скрипт для проверки авторизации на сервере обновлений
 * 
 * Используйте этот скрипт для проверки правильности настройки авторизации
 */

// Настройки сервера
$server_url = 'http://localhost/opencart'; // Замените на URL вашего сайта OpenCart
$api_key = 'your_secret_api_key_123'; // Замените на ваш API ключ, заданный в настройках сервера

echo "=== Тест авторизации сервера обновлений GDT ===\n\n";

echo "Сервер: $server_url\n";
echo "API ключ: $api_key\n\n";

// Тест 1: Проверка обновлений без API ключа
echo "1. Тестируем запрос без API ключа (должен вернуть ошибку 401)...\n";
$response = makeRequest($server_url . '/index.php?route=gdt_update_server/check', array(
    'code' => 'test_module',
    'version' => '1.0.0'
));

if ($response === false) {
    echo "✓ Сервер корректно отклонил запрос без API ключа\n";
} else {
    echo "✗ Сервер принял запрос без API ключа (некорректно)\n";
    print_r($response);
}

echo "\n";

// Тест 2: Проверка обновлений с неправильным API ключом
echo "2. Тестируем запрос с неправильным API ключом (должен вернуть ошибку 401)...\n";
$response = makeRequest($server_url . '/index.php?route=gdt_update_server/check', array(
    'api_key' => 'wrong_api_key',
    'code' => 'test_module',
    'version' => '1.0.0'
));

if ($response === false) {
    echo "✓ Сервер корректно отклонил запрос с неправильным API ключом\n";
} else {
    echo "✗ Сервер принял запрос с неправильным API ключом (некорректно)\n";
    print_r($response);
}

echo "\n";

// Тест 3: Проверка обновлений с правильным API ключом
echo "3. Тестируем запрос с правильным API ключом...\n";
$response = makeRequest($server_url . '/index.php?route=gdt_update_server/check', array(
    'api_key' => $api_key,
    'code' => 'test_module',
    'version' => '1.0.0'
));

if ($response !== false) {
    echo "✓ Сервер принял запрос с правильным API ключом\n";
    echo "  Статус: " . ($response['status'] ?? 'неизвестно') . "\n";
    if (isset($response['version'])) {
        echo "  Доступная версия: {$response['version']}\n";
    }
} else {
    echo "✗ Сервер отклонил запрос с правильным API ключом\n";
}

echo "\n";

// Тест 4: Тест загрузки модуля с правильным API ключом
echo "4. Тестируем загрузку модуля с правильным API ключом...\n";
$response = makeRequest($server_url . '/index.php?route=gdt_update_server/download', array(
    'api_key' => $api_key,
    'code' => 'test_module'
));

if ($response !== false) {
    echo "✓ Сервер ответил на запрос загрузки\n";
    // Проверяем, является ли ответ файлом или JSON ошибкой
    if (is_string($response) && substr($response, 0, 1) === '{') {
        $json_response = json_decode($response, true);
        if (isset($json_response['error'])) {
            echo "  Ошибка: " . $json_response['error'] . "\n";
        }
    } else {
        echo "  Получен файл (размер: " . strlen($response) . " байт)\n";
    }
} else {
    echo "✗ Сервер отклонил запрос загрузки\n";
}

echo "\n";

// Тест 5: Получение списка модулей
echo "5. Тестируем получение списка модулей...\n";
$response = makeRequest($server_url . '/index.php?route=gdt_update_server/modules', array(
    'api_key' => $api_key
));

if ($response !== false) {
    echo "✓ Сервер ответил на запрос списка модулей\n";
    if (isset($response['modules'])) {
        echo "  Найдено модулей: " . count($response['modules']) . "\n";
        foreach ($response['modules'] as $module) {
            echo "  - {$module['name']} ({$module['code']}) v{$module['version']}\n";
        }
    } else if (isset($response['error'])) {
        echo "  Ошибка: " . $response['error'] . "\n";
    }
} else {
    echo "✗ Сервер отклонил запрос списка модулей\n";
}

echo "\n=== Тест завершен ===\n";

/**
 * Выполняет HTTP запрос к серверу
 */
function makeRequest($url, $data = array()) {
    $ch = curl_init();
    
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: GDT-Auth-Test/1.0'
        ),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FAILONERROR => false
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        echo "  CURL ошибка: $error\n";
        return false;
    }
    
    echo "  HTTP код: $http_code\n";
    
    if ($http_code == 401) {
        echo "  Авторизация не пройдена (401 Unauthorized)\n";
        return false;
    }
    
    if ($http_code == 403) {
        echo "  Доступ запрещен (403 Forbidden)\n";
        return false;
    }
    
    if ($http_code == 503) {
        echo "  Сервис недоступен (503 Service Unavailable)\n";
        return false;
    }
    
    if ($http_code != 200) {
        echo "  Неожиданный HTTP код: $http_code\n";
        return false;
    }
    
    // Пытаемся декодировать JSON ответ
    $json_response = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json_response;
    }
    
    // Если это не JSON, возвращаем как есть (например, для файлов)
    return $response;
}
