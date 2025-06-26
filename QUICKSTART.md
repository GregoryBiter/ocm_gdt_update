# Руководство по быстрому запуску GDT Update System

## 🚀 Быстрый старт

### 1. Установка модулей
1. Загрузите все файлы из папки `upload/` в корень OpenCart
2. Войдите в админ-панель → Расширения → Модули
3. Установите оба модуля:
   - **GDT Updater** (клиент для получения обновлений)
   - **GDT Update Server** (сервер для раздачи обновлений)

### 2. Настройка сервера обновлений
1. Найдите "GDT Update Server" → Редактировать
2. Включите модуль
3. Сгенерируйте API ключ (например: `gdt_api_key_2025_secure_123456`)
4. Укажите разрешенные IP (или `*` для всех)
5. Включите логирование для отладки
6. Сохранить

### 3. Загрузка модулей на сервер
1. Нажмите "Управление модулями"
2. Загрузите ZIP архивы модулев
3. Система автоматически извлечет метаданные

### 4. Настройка клиента обновлений
1. Найдите "GDT Updater" → Редактировать  
2. Включите модуль
3. URL сервера: `https://your-site.com` (ваш же сайт)
4. API ключ: тот же что указали в сервере
5. Сохранить

### 5. Тестирование
1. Перейдите в GDT Updater
2. Увидите список установленных модулей
3. Проверьте наличие обновлений
4. Протестируйте загрузку обновлений

## 🔧 API Endpoints

После настройки будут доступны:

- `GET/POST /index.php?route=gdt_update_server/modules` - список модулей
- `POST /index.php?route=gdt_update_server/check` - проверка обновлений  
- `POST /index.php?route=gdt_update_server/download` - скачивание архивов

## 📝 Создание модуля для распространения

Архив модуля должен содержать:

```
my_module_1.0.0.zip
├── system/hook/my_module.php    # ОБЯЗАТЕЛЬНО! 
├── admin/controller/...
├── admin/view/...
└── catalog/...
```

В файле `system/hook/my_module.php` добавьте комментарии с метаданными:

```php
<?php
/**
 * Name: My Awesome Module
 * Description: This module does amazing things
 * Version: 1.0.0
 * Author: Your Name
 * Author URL: https://yoursite.com
 */

// Код хука модуля...
```

## 🛠️ Отладка

### Проверка API через curl:

```bash
# Список модулей
curl -X POST "http://your-site.com/index.php?route=gdt_update_server/modules" \
     -d "api_key=your-api-key"

# Проверка обновлений
curl -X POST "http://your-site.com/index.php?route=gdt_update_server/check" \
     -d "api_key=your-api-key&code=gdt_updater&version=1.0.0"
```

### Проверка логов:
- Включите логирование в настройках сервера
- Проверьте файлы в `system/storage/logs/`

### Частые проблемы:
1. **403/401 ошибки** - проверьте API ключ и IP
2. **404 ошибки** - убедитесь что модуль включен
3. **Не находит модули** - проверьте права на папку storage

## 🎯 Готово!

Теперь у вас работает полноценная система обновлений модулей OpenCart с современным интерфейсом и API для интеграции!
