# Changelog - Интеграция с встроенным процессом OpenCart

## Версия 2.0.0 (2025-10-18)

### Новые возможности

#### ✨ Интеграция с встроенным процессом установки OpenCart

Модуль теперь использует стандартный механизм установки OpenCart (`marketplace/install`) вместо прямого копирования файлов.

**Преимущества:**
- ✅ Полная совместимость со стандартами OpenCart
- ✅ Автоматическая обработка XML модификаторов
- ✅ Регистрация установок в базе данных OpenCart
- ✅ Безопасная валидация файлов
- ✅ Встроенная поддержка отката при ошибках

### Изменения в коде

#### Новые методы в `Manager` класса

1. **`installViaOpenCartProcess($zip_file_path, $module_code)`**
   - Устанавливает модуль через встроенный процесс OpenCart
   - Выполняет все 4 этапа: распаковка, перемещение, обработка XML, очистка

2. **`executeInstallStep($route, $install_token)`**
   - Внутренний метод для выполнения каждого этапа установки
   - Имитирует вызовы контроллеров OpenCart программно

3. **`updateModuleVersion($module_code, $new_version, $update_info)`**
   - Обновляет версию модуля в базе данных после успешной установки
   - Сохраняет дополнительную информацию (имя, описание, автор)

#### Обновленные методы

1. **`installModuleURL($module_code, $download_url, $use_opencart_process = true)`**
   - Добавлен параметр `$use_opencart_process` (по умолчанию `true`)
   - Поддерживает оба режима: новый (OpenCart) и старый (прямое копирование)

2. **`downloadAndInstallUpdate($server_url, $module, $update_info, $client_id, $api_key, $use_opencart_process = true)`**
   - Добавлен параметр `$use_opencart_process` (по умолчанию `true`)
   - Автоматически обновляет версию после успешной установки

### Изменения в контроллерах

#### `gdt_updater.php`

```php
// Обновление модуля теперь использует OpenCart процесс
$result = $this->manager->downloadAndInstallUpdate(
    $server_url, 
    $module, 
    $update_info, 
    '', 
    $api_key, 
    true  // ← новый параметр
);
```

#### `gdt_install_modules.php`

```php
// Установка модуля через OpenCart процесс
$result = $manager->installModuleURL($module_code, $download_url, true);
```

#### `gdt_updater.php` (модель)

```php
// Автообновление также использует OpenCart процесс
$result = $manager->downloadAndInstallUpdate(
    $server_url, 
    $module, 
    $update_info, 
    '', 
    $api_key, 
    true
);
```

### Процесс установки

**Старый процесс (до версии 2.0.0):**
1. Скачивание ZIP → Распаковка → Прямое копирование файлов → Очистка

**Новый процесс (версия 2.0.0+):**
1. Скачивание ZIP
2. Копирование в `DIR_UPLOAD`
3. Генерация токена сессии
4. **OpenCart процесс:**
   - Распаковка (`marketplace/install/unzip`)
   - Перемещение файлов (`marketplace/install/move`)
   - Обработка XML (`marketplace/install/xml`)
   - Очистка (`marketplace/install/remove`)
5. Обновление версии в БД
6. Очистка кэша

### Обратная совместимость

Сохранена полная обратная совместимость:

```php
// Новый способ (рекомендуется)
$manager->installModuleURL($code, $url, true);

// Старый способ (по-прежнему работает)
$manager->installModuleURL($code, $url, false);
```

### Требуемые изменения в XML модификаторах

XML файлы модулей теперь обрабатываются автоматически. Убедитесь, что ваш `install.xml` содержит:

```xml
<?xml version="1.0" encoding="utf-8"?>
<modification>
    <name>Module Name</name>
    <code>module_code</code>
    <version>1.0.0</version>
    <author>Author Name</author>
    <!-- ... операции модификации ... -->
</modification>
```

### Миграция

Для существующих установок никаких изменений не требуется. Все новые установки и обновления будут автоматически использовать новый процесс.

### Логирование

Расширено логирование для отслеживания процесса установки:

```
GDT Module Manager: Starting OpenCart installation process for module_code
GDT Module Manager: Using OpenCart built-in installation process
GDT Module Manager: Successfully installed module via OpenCart process
GDT Module Manager: Update completed successfully via OpenCart process
```

### Тестирование

Рекомендуется протестировать обновление на тестовой копии сайта перед применением на продакшене.

**Протестированные сценарии:**
- ✅ Установка нового модуля
- ✅ Обновление существующего модуля
- ✅ Автообновление модулей
- ✅ Установка модулей с XML модификаторами
- ✅ Откат при ошибках
- ✅ Множественная установка модулей

### Известные ограничения

1. Требуется OpenCart 2.x или 3.x
2. Необходимы права на запись в `DIR_UPLOAD`
3. ZIP-архив должен иметь стандартную структуру OpenCart

### Благодарности

Спасибо сообществу OpenCart за стабильный и надежный процесс установки модулей!

---

**Версия:** 2.0.0  
**Дата:** 18 октября 2025  
**Автор:** GDT Studio
