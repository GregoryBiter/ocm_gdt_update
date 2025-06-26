# Система обновления модулей для OpenCart (GDT Update)

Современная система автоматического обновления модулей OpenCart с красивым интерфейсом, вдохновленным WordPress, и полноценным сервером обновлений.

## Возможности

### Клиентская часть (GDT Updater):
- ✅ Современный интерфейс в стиле WordPress 
- ✅ Серверный рендеринг списка модулей
- ✅ AJAX обновления без перезагрузки страницы
- ✅ Переключатель автообновлений
- ✅ Резервное копирование перед обновлением
- ✅ Восстановление из резервных копий
- ✅ Автоматическое определение кода модуля по имени файла

### Серверная часть (GDT Update Server):
- ✅ Полноценный модуль OpenCart для управления сервером
- ✅ API для проверки и загрузки обновлений
- ✅ Загрузка и управление модулями через админ-панель
- ✅ Автоматический парсинг метаданных из архивов
- ✅ Безопасность: API ключи, ограничение по IP, логирование
- ✅ Мультиязычная поддержка (EN/RU/UA)

## Структура проекта

```
ocm_gdt_update/
├── upload/                     # Файлы для установки в OpenCart
│   ├── admin/                  # Админ-панель
│   │   ├── controller/extension/module/
│   │   │   ├── gdt_updater.php       # Клиент обновлений
│   │   │   └── gdt_update_server.php # Сервер обновлений
│   │   ├── language/           # Переводы
│   │   └── view/template/      # Шаблоны
│   ├── catalog/controller/gdt_update_server/  # API сервера
│   │   ├── check.php           # Проверка обновлений
│   │   ├── download.php        # Скачивание модулей
│   │   └── modules.php         # Список модулей
│   └── system/
│       ├── hook/               # Файлы-хуки модулей
│       └── library/gbitstudio/updater/  # Библиотека обновления
├── examples/
│   └── client_integration.php  # Пример интеграции с клиентом
├── server/                     # Автономный сервер (опционально)
└── README.md
```

## Установка

### 1. Установка клиента обновлений (обязательно)

1. Скопируйте содержимое `upload/` в корень OpenCart
2. Войдите в админ-панель → Расширения → Модули  
3. Найдите "GDT Updater" → Установить → Редактировать
4. Настройте URL сервера обновлений и включите модуль

### 2. Установка сервера обновлений (если хотите раздавать обновления)

1. В той же админ-панели найдите "GDT Update Server" → Установить → Редактировать
2. Настройте API ключ, разрешенные IP адреса
3. Включите модуль и сохраните настройки
4. Перейдите в "Управление модулями" для загрузки модулей

## API сервера обновлений

### Endpoints:

#### GET/POST `/index.php?route=gdt_update_server/modules`
Получить список всех доступных модулей
```json
{
  "success": true,
  "modules": [
    {
      "code": "gdt_updater",
      "name": "GDT Updater",
      "version": "1.0.1",
      "description": "Module update system",
      "author": "GBit Studio"
    }
  ]
}
```

#### POST `/gdt_update_server/check`
Проверить наличие обновлений
```json
{
  "api_key": "your-api-key",
  "module_code": "gdt_updater", 
  "current_version": "1.0.0"
}
```

Ответ:
```json
{
  "success": true,
  "update_available": true,
  "latest_version": "1.0.1",
  "download_url": "https://server.com/gdt_update_server/download"
}
```

#### POST `/gdt_update_server/download`
Скачать архив модуля
```json
{
  "api_key": "your-api-key",
  "module_code": "gdt_updater"
}
```

Возвращает ZIP-архив модуля.

## Создание модуля для распространения

1. **Структура архива модуля:**
```
my_module_1.0.0.zip
├── system/hook/my_module.php    # Обязательно! Файл с метаданными
├── admin/controller/...
├── admin/view/...
└── catalog/...
```

2. **Метаданные в файле хука (`system/hook/my_module.php`):**
```php
<?php
/**
 * Name: My Awesome Module
 * Description: This module does amazing things
 * Version: 1.0.0
 * Author: Your Name
 * Author URL: https://yoursite.com
 */

// Код хука модуля
class Hook_MyModule {
    // ...
}
```

3. **Загрузка на сервер:**
   - Войдите в админ-панель → GDT Update Server → Управление модулями
   - Нажмите "Загрузить модуль" и выберите ZIP файл
   - Система автоматически извлечет метаданные и сохранит модуль

## Интеграция с клиентскими сайтами

Используйте пример класса `GdtUpdateClient` из `examples/client_integration.php`:

```php
$client = new GdtUpdateClient('https://your-server.com', 'api-key');

// Получить список модулей
$modules = $client->getAvailableModules();

// Проверить обновления
$update = $client->checkUpdate('module_code', '1.0.0');

// Скачать обновление
$archive = $client->downloadUpdate('module_code');
```

## Безопасность

- **API ключи:** Обязательно установите сильный API ключ
- **IP фильтрация:** Ограничьте доступ по IP адресам
- **Логирование:** Включите логирование для мониторинга
- **HTTPS:** Используйте HTTPS для передачи данных

## Тестирование

### Быстрый тест API:
1. Отредактируйте `examples/test_api.php`
2. Укажите URL вашего сайта и API ключ
3. Запустите: `php examples/test_api.php`

### Ручное тестирование:
1. Установите оба модуля в OpenCart
2. Настройте сервер обновлений с API ключом
3. Загрузите тестовый модуль через админ-панель
4. Протестируйте клиент обновлений

## Многоязычность

Поддерживаются языки:
- English (en-gb)
- Русский (ru-ru) 
- Українська (uk-ua)

## Требования

- OpenCart 3.x
- PHP 7.2+
- cURL extension
- ZipArchive extension
- Права на запись в system/storage/

## Лицензия

Этот проект распространяется под лицензией MIT.
3. Создайте ZIP-архив с обновлением и поместите его в директорию модуля с именем `my_module_1.0.0.zip`.

## Идентификация модулей в OpenCart

Для того чтобы система обновления могла определить ваш модуль, создайте файл в директории `system/hook/` вашего сайта OpenCart с содержимым:

```php
<?php
/*
name: Название модуля
description: Описание модуля
code: код_модуля
version: 1.0.0
*/
```

## Пример использования API сервера обновлений

### Проверка наличия обновлений

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://ваш-сервер/check.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'code' => 'gdt_system',
    'version' => '1.0.0'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$update_info = json_decode($response, true);
if (isset($update_info['status']) && $update_info['status'] == 'update_available') {
    echo "Доступно обновление до версии {$update_info['version']}";
}
```

### Загрузка обновления

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://ваш-сервер/download.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'code' => 'gdt_system',
    'version' => '1.0.1'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

file_put_contents('update.zip', $response);
```
