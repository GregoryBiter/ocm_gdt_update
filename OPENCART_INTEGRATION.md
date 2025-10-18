# Интеграция с встроенным процессом установки OpenCart

## Обзор

Модуль GDT Updater теперь использует встроенный процесс установки модулей OpenCart (`marketplace/install`) для обеспечения максимальной совместимости и надежности.

## Преимущества интеграции

1. **Стандартный процесс установки** - используется тот же механизм, что и при ручной установке через админ-панель OpenCart
2. **Автоматическая обработка XML модификаторов** - встроенный процесс корректно обрабатывает файлы `install.xml`
3. **Запись в базу данных** - все установки регистрируются в таблицах `oc_extension_install` и `oc_extension_path`
4. **Безопасность** - использует проверенный механизм валидации файлов OpenCart
5. **Откат изменений** - встроенная поддержка отката при ошибках

## Как это работает

### Процесс установки/обновления модуля

1. **Загрузка модуля** - модуль скачивается с сервера обновлений
2. **Подготовка** - ZIP-файл копируется во временную директорию `DIR_UPLOAD`
3. **Генерация токена** - создается уникальный токен сессии для отслеживания установки
4. **Этапы установки через OpenCart**:
   - `marketplace/install/unzip` - распаковка архива
   - `marketplace/install/move` - перемещение файлов в нужные директории
   - `marketplace/install/xml` - обработка XML модификаторов
   - `marketplace/install/remove` - очистка временных файлов

### Основные методы

#### Manager::installViaOpenCartProcess()

```php
public function installViaOpenCartProcess($zip_file_path, $module_code = '')
```

Выполняет установку модуля через встроенный процесс OpenCart.

**Параметры:**
- `$zip_file_path` - путь к ZIP-файлу модуля
- `$module_code` - код модуля (опционально)

**Возвращает:** `true` при успехе или строку с ошибкой

#### Manager::installModuleURL()

```php
public function installModuleURL($module_code, $download_url, $use_opencart_process = true)
```

Установка модуля по URL.

**Параметры:**
- `$module_code` - код модуля
- `$download_url` - URL для скачивания
- `$use_opencart_process` - использовать встроенный процесс (по умолчанию `true`)

#### Manager::downloadAndInstallUpdate()

```php
public function downloadAndInstallUpdate($server_url, $module, $update_info, $client_id = 'default', $api_key = '', $use_opencart_process = true)
```

Скачивает и устанавливает обновление модуля.

**Параметры:**
- `$server_url` - URL сервера обновлений
- `$module` - информация о модуле
- `$update_info` - информация об обновлении
- `$client_id` - идентификатор клиента
- `$api_key` - ключ API
- `$use_opencart_process` - использовать встроенный процесс (по умолчанию `true`)

## Пример использования

### Установка модуля

```php
$this->load->library('gbitstudio/modules/manager');
$manager = new \Gbitstudio\Modules\Manager($this->registry);

// Установка через встроенный процесс OpenCart
$result = $manager->installModuleURL('my_module', 'https://example.com/module.zip', true);

if ($result === true) {
    echo "Модуль успешно установлен!";
} else {
    echo "Ошибка: " . $result;
}
```

### Обновление модуля

```php
$this->load->library('gbitstudio/modules/manager');
$manager = new \Gbitstudio\Modules\Manager($this->registry);

$server_url = 'https://updates.example.com';
$module = $manager->getModuleByCode('my_module');
$update_info = $manager->checkModuleUpdate($server_url, $module);

if ($update_info && !isset($update_info['error'])) {
    // Обновление через встроенный процесс OpenCart
    $result = $manager->downloadAndInstallUpdate($server_url, $module, $update_info, '', '', true);
    
    if ($result === true) {
        $manager->clearCache();
        echo "Модуль успешно обновлен!";
    }
}
```

## Структура ZIP-архива модуля

Для корректной работы ZIP-архив модуля должен иметь следующую структуру:

```
module.ocmod.zip
├── upload/                      # Директория с файлами для установки
│   ├── admin/
│   │   └── controller/
│   │       └── extension/
│   │           └── module/
│   ├── catalog/
│   └── system/
└── install.xml                  # XML модификатор (опционально)
```

## Обработка XML модификаторов

XML файл `install.xml` обрабатывается автоматически встроенным процессом OpenCart:

```xml
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <name>My Module</name>
    <code>my_module</code>
    <version>1.0.0</version>
    <author>Author Name</author>
    <link>https://example.com</link>
    
    <file path="catalog/controller/common/header.php">
        <operation>
            <search><![CDATA[
                // Existing code
            ]]></search>
            <add position="after"><![CDATA[
                // New code
            ]]></add>
        </operation>
    </file>
</modification>
```

## Информация о версии модуля

После успешной установки/обновления версия модуля сохраняется:

1. В базе данных в таблице `gdt_modules`
2. В файле `system/modules/{module_code}/opencart-module.json` (если доступен)
3. В таблице модификаторов OpenCart (если есть XML)

## Обратная совместимость

Модуль поддерживает два режима работы:

1. **Новый режим** (`use_opencart_process = true`) - через встроенный процесс OpenCart
2. **Старый режим** (`use_opencart_process = false`) - прямое копирование файлов

По умолчанию используется новый режим для всех новых установок и обновлений.

## Логирование

Все операции логируются в системный лог OpenCart:

```
GDT Module Manager: Starting OpenCart installation process for my_module
GDT Module Manager: Using OpenCart built-in installation process
GDT Module Manager: Successfully installed module via OpenCart process
```

## Устранение неполадок

### Проблема: "Ошибка распаковки"
**Решение:** Проверьте, что ZIP-файл валидный и содержит директорию `upload/`

### Проблема: "Ошибка перемещения файлов"
**Решение:** Убедитесь, что все директории имеют правильные права доступа (755)

### Проблема: "Версия модуля не обновилась"
**Решение:** Проверьте наличие файла `opencart-module.json` или записи в таблице `gdt_modules`

## Требования

- OpenCart 2.x / 3.x
- PHP 7.0+
- Расширение ZipArchive
- Права на запись в директории OpenCart

## Лицензия

MIT License
