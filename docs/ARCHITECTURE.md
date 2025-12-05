# GDT Module Manager - Структура та архітектура

## Структура сервісів

Модуль розділено на логічні сервіси для кращої організації та підтримки коду:

### 1. ModuleService
**Розташування:** `system/library/gbitstudio/modules/services/ModuleService.php`

**Відповідальність:** Робота з модулями (отримання інформації)

**Основні методи:**
- `getInstalledModules()` - отримує список всіх встановлених модулів з усіх джерел
- `getModuleByCode($code)` - отримує конкретний модуль за кодом
- Приватні методи для роботи з різними джерелами:
  - OpenCart modifications (пріоритет 1)
  - System files .ocmod.xml (пріоритет 2)
  - Database gdt_modules (deprecated, пріоритет 3)

### 2. UpdateService
**Розташування:** `system/library/gbitstudio/modules/services/UpdateService.php`

**Відповідальність:** Перевірка та завантаження оновлень

**Основні методи:**
- `checkModuleUpdate($server_url, $module, $api_key)` - перевіряє наявність оновлень
- `downloadModule($download_url, $module_code, $api_key)` - завантажує модуль з сервера
- Приватні методи для роботи з API:
  - `executeApiRequest()` - виконує HTTP запити
  - `parseUpdateResponse()` - парсить відповідь сервера

### 3. InstallService
**Розташування:** `system/library/gbitstudio/modules/services/InstallService.php`

**Відповідальність:** Встановлення та оновлення модулів

**Основні методи:**
- `installModule($zip_file_path, $module_code)` - встановлює модуль з ZIP файлу
- Приватні методи для процесу встановлення:
  - `unzipModule()` - розпаковує архів
  - `moveModuleFiles()` - переміщує файли в правильні директорії
  - `processModuleXml()` - обробляє XML модифікації
  - `cleanupInstallation()` - очищає тимчасові файли

**Реалізація:** Повністю реплікує логіку з `admin/controller/marketplace/install.php` OpenCart

### 4. Manager (Facade)
**Розташування:** `system/library/gbitstudio/modules/manager.php`

**Відповідальність:** Фасад для всіх сервісів, забезпечує зворотню сумісність

**Використання в контролері:**
```php
$this->load->library('gbitstudio/modules/manager');
$manager = new \Gbitstudio\Modules\Manager($registry);

// Отримати модулі
$modules = $manager->getInstalledModules();

// Перевірити оновлення
$update_info = $manager->checkModuleUpdate($server_url, $module, '', $api_key);

// Встановити модуль
$result = $manager->installViaOpenCartProcess($zip_file, $module_code);
```

## Переваги нової структури

### 1. Розділення відповідальності (SRP)
- Кожен сервіс відповідає за свою область
- Легше знайти та виправити баги
- Простіше писати тести

### 2. Модульність
- Сервіси можна використовувати незалежно
- Легко додавати нову функціональність
- Можливість повторного використання

### 3. Зрозумілість коду
- Чітка структура папок
- Зрозумілі назви класів та методів
- Логічне групування функцій

### 4. Підтримка
- Простіше онбордити нових розробників
- Легше рефакторити окремі частини
- Менше конфліктів при роботі в команді

## Структура папок

```
system/library/gbitstudio/modules/
├── services/
│   ├── ModuleService.php      # Робота з модулями
│   ├── UpdateService.php      # Оновлення
│   └── InstallService.php     # Встановлення
├── manager.php                # Головний фасад
├── tools.php                  # Допоміжні утиліти
└── installer.php              # (deprecated, буде видалено)
```

## Міграція з старого коду

Старий код в `manager.php` залишається для зворотної сумісності, але тепер він делегує роботу відповідним сервісам:

```php
// Старий спосіб (все ще працює)
$manager = new \Gbitstudio\Modules\Manager($registry);
$modules = $manager->getInstalledModules();

// Новий спосіб (прямо через сервіс)
$moduleService = new \Gbitstudio\Modules\Services\ModuleService($db, $log);
$modules = $moduleService->getInstalledModules();
```

## Рекомендації для розробників

1. **Використовуйте Manager як фасад** у контролерах для простоти
2. **Використовуйте сервіси напряму** якщо потрібна тільки одна функція
3. **Не змішуйте бізнес-логіку** з логікою представлення
4. **Логуйте всі важливі операції** через $this->log
5. **Обробляйте винятки** на рівні контролера, не в сервісах

## Плани на майбутнє

- [ ] Видалити deprecated таблицю `gdt_modules`
- [ ] Додати кешування списку модулів
- [ ] Реалізувати систему подій (events)
- [ ] Додати можливість rollback при невдалому оновленні
- [ ] Інтеграція з Composer для залежностей
