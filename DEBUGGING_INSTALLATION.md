# Отладка проблем с установкой модулей

## Проблема: "Неожиданный формат ответа от контроллера"

Эта ошибка возникает, когда встроенный контроллер OpenCart возвращает не JSON или пустой ответ.

### Шаг 1: Проверьте логи

Логи находятся в:
- `system/storage/logs/error.log`
- Или в папке, указанной в конфигурации

Ищите записи с префиксом `GDT Module Manager:`

```
GDT Module Manager: Starting OpenCart installation process for module_code
GDT Module Manager: ZIP file path: /path/to/file.zip
GDT Module Manager: Copying to upload directory: /path/to/upload/token.tmp
GDT Module Manager: Install token: abc1234567
GDT Module Manager: Starting unzip step
GDT Module Manager: Response from marketplace/install/unzip: {...}
```

### Шаг 2: Проверьте структуру ZIP-архива

Убедитесь, что ZIP-архив модуля имеет правильную структуру:

```
module.ocmod.zip
├── upload/                      # Обязательно!
│   ├── admin/
│   ├── catalog/
│   └── system/
└── install.xml                  # Опционально
```

**Важно:** Директория `upload/` должна быть в корне ZIP-архива!

### Шаг 3: Проверьте права доступа

Убедитесь, что директории имеют правильные права:

```bash
# Директория загрузок
chmod 755 system/storage/upload/

# Если используется старая версия OpenCart
chmod 755 system/upload/
```

### Шаг 4: Проверьте права пользователя

Контроллеры OpenCart проверяют права пользователя. Убедитесь, что:

1. Вы вошли в админ-панель
2. У пользователя есть права на установку расширений
3. Сессия активна (не истекла)

### Шаг 5: Проверьте наличие user_token

В логах должна быть запись:

```
GDT Module Manager: Generated temporary user_token for installation
```

Если её нет, проверьте что вызов происходит из админ-панели.

### Шаг 6: Отладочный режим

Добавьте в начало файла `manager.php` временный код для отладки:

```php
// В методе executeInstallStep, после получения $output
if ($this->log) {
    $this->log->write('GDT Module Manager DEBUG: Full response: ' . $output);
    $this->log->write('GDT Module Manager DEBUG: Response length: ' . strlen($output));
    $this->log->write('GDT Module Manager DEBUG: JSON error: ' . json_last_error_msg());
}
```

### Шаг 7: Проверьте Response объект

Возможные проблемы:
1. Response уже содержит данные до вызова контроллера
2. Контроллер не вызывает `setOutput()`
3. Вывод перехватывается другим обработчиком

### Шаг 8: Альтернативное решение - прямая установка

Если встроенный процесс не работает, используйте прямую установку:

```php
// Отключите использование встроенного процесса
$manager->installModuleURL($module_code, $download_url, false);
```

## Распространенные ошибки

### Ошибка: "Файл модуля не найден"

**Причина:** ZIP-файл не был скачан или удален

**Решение:**
1. Проверьте URL скачивания
2. Проверьте доступность сервера
3. Проверьте права на запись в temp директорию

### Ошибка: "Не удалось скопировать файл в директорию загрузок"

**Причина:** Нет прав на запись

**Решение:**
```bash
chmod 755 system/storage/upload/
chown www-data:www-data system/storage/upload/
```

### Ошибка: "Ошибка распаковки"

**Причина:** 
- Поврежденный ZIP-архив
- Неправильная структура архива
- Недостаточно места на диске

**Решение:**
1. Проверьте архив локально
2. Убедитесь что есть директория `upload/`
3. Проверьте свободное место: `df -h`

### Ошибка: "Ошибка перемещения файлов"

**Причина:**
- Файлы уже существуют
- Нет прав на запись в целевые директории
- Недопустимые пути файлов

**Решение:**
1. Проверьте права на директории `admin/`, `catalog/`, `system/`
2. Проверьте что файлы не заблокированы другим процессом
3. Убедитесь что пути в архиве корректны

### Ошибка: "Ошибка обработки XML"

**Причина:**
- Некорректный XML в `install.xml`
- XML содержит ошибки синтаксиса

**Решение:**
1. Проверьте `install.xml` на валидность
2. Используйте XML валидатор
3. Убедитесь что все теги закрыты

## Проверка системных требований

```php
// Проверьте что расширения PHP доступны
if (!class_exists('ZipArchive')) {
    echo 'Ошибка: расширение ZIP не установлено';
}

// Проверьте версию PHP
if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    echo 'Ошибка: требуется PHP 7.0 или выше';
}

// Проверьте доступность директорий
$dirs = [
    'system/storage/upload/',
    'admin/',
    'catalog/',
    'system/'
];

foreach ($dirs as $dir) {
    if (!is_writable($dir)) {
        echo 'Ошибка: нет прав на запись в ' . $dir;
    }
}
```

## Получение расширенной информации из логов

Для получения подробной информации выполните:

```bash
# Смотрим последние 100 строк лога
tail -n 100 system/storage/logs/error.log | grep "GDT Module Manager"

# Следим за логом в реальном времени
tail -f system/storage/logs/error.log | grep "GDT Module Manager"

# Ищем конкретную ошибку
grep "Неожиданный формат" system/storage/logs/error.log
```

## Контакты для поддержки

Если проблема не решена:

1. Соберите логи установки
2. Сохраните структуру ZIP-архива
3. Опишите точную последовательность действий
4. Укажите версию OpenCart и PHP

## Успешная установка

При успешной установке в логах будет:

```
GDT Module Manager: Starting OpenCart installation process for my_module
GDT Module Manager: ZIP file path: /tmp/module.zip
GDT Module Manager: Copying to upload directory: /system/storage/upload/abc1234567.tmp
GDT Module Manager: Install token: abc1234567
GDT Module Manager: Starting unzip step
GDT Module Manager: Response from marketplace/install/unzip: {"text":"...","next":"..."}
GDT Module Manager: Starting move step
GDT Module Manager: Response from marketplace/install/move: {"text":"...","next":"..."}
GDT Module Manager: Starting XML step
GDT Module Manager: Response from marketplace/install/xml: {"text":"...","next":"..."}
GDT Module Manager: Starting cleanup step
GDT Module Manager: Response from marketplace/install/remove: {"success":"..."}
GDT Module Manager: Successfully installed module via OpenCart process
```

Все записи должны быть без слова "error"!
