# Система обновления модулей для OpenCart (GDT Update)

Современная система автоматического обновления модулей OpenCart с красивым интерфейсом, вдохновленным WordPress, и полноценным сервером обновлений с хранением в базе данных.

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
- ✅ **Хранение модулей в базе данных для лучшей производительности**
- ✅ **Расширенная система каталогизации модулей**
- ✅ **Статистика скачиваний и рейтинги модулей**
- ✅ **API для интеграции с каталогом модулей**
- ✅ API для проверки и загрузки обновлений
- ✅ Загрузка и управление модулями через админ-панель
- ✅ Автоматический парсинг метаданных из архивов
- ✅ Безопасность: API ключи, ограничение по IP, логирование
- ✅ Мультиязычная поддержка (EN/RU/UA)
- ✅ **Миграция существующих модулей из JSON в базу данных**

## Новые возможности версии 2.0

### База данных вместо JSON файлов
- Улучшенная производительность при большом количестве модулей
- Расширенные возможности поиска и фильтрации
- Статистика использования модулей
- Система рейтингов и отзывов

### API каталога модулей
- Получение списка модулей с фильтрацией
- Поиск модулей по категориям
- Информация о совместимости с версиями OpenCart
- Статистика скачиваний

### Расширенные метаданные
- Категоризация модулей
- Информация о зависимостях
- Ссылки на демо, документацию и поддержку
- Цены и коммерческая информация

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

## Безопасность

- **API ключи:** Обязательно установите сильный API ключ
- **IP фильтрация:** Ограничьте доступ по IP адресам
- **Логирование:** Включите логирование для мониторинга
- **HTTPS:** Используйте HTTPS для передачи данных

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
