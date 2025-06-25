<?php
/**
 * Конфигурационный файл сервера обновлений
 * Содержит настройки безопасности и доступа
 */

// Запрещаем прямой доступ к этому файлу
if (!defined('GDT_SERVER')) {
    http_response_code(403);
    die('Доступ запрещен');
}

// Настройки безопасности
$config = [
    // API-ключи для доступа к сервису обновлений
    // Формат: 'client_id' => 'api_key'
    'api_keys' => [
        'default' => 'your_secret_api_key_123',
        'store1' => 'store1_secure_api_key_456',
        'store2' => 'store2_secure_api_key_789',
        // Добавьте дополнительные ключи по мере необходимости
    ]
];

/**
 * Проверяет валидность API-ключа
 * 
 * @param string $client_id ID клиента
 * @param string $api_key API-ключ для проверки
 * @return bool Результат проверки
 */
function validateApiKey($client_id, $api_key) {
    global $config;
    
    // Проверяем существование клиента
    if (!isset($config['api_keys'][$client_id])) {
        return false;
    }
    
    // Проверяем совпадение ключа
    return hash_equals($config['api_keys'][$client_id], $api_key);
}

/**
 * Логирует попытки доступа с неверным API-ключом
 * 
 * @param string $api_key Неверный API-ключ
 * @param string $ip IP-адрес клиента
 * @param string $endpoint Эндпоинт, к которому был запрос
 * @return void
 */
function logInvalidApiKeyAttempt($api_key, $ip, $endpoint) {
    $log_file = __DIR__ . '/logs/auth_failed.log';
    $log_dir = dirname($log_file);
    
    // Создаем директорию для логов, если она не существует
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Формируем сообщение лога
    $log_message = date('Y-m-d H:i:s') . ' | ' . $ip . ' | ' . 
                   $endpoint . ' | Invalid API key: ' . $api_key . PHP_EOL;
    
    // Записываем в лог
    file_put_contents($log_file, $log_message, FILE_APPEND);

    // лог в консоль (для отладки)
    if (defined('STDERR')) {
        fwrite(STDERR, $log_message);
    } else {
        error_log($log_message);
    }
}
