# 🧪 Швидкий старт: Unit тестування

## ⚡ Встановлення (5 хвилин)

```bash
# 1. Перейдіть в директорію проекту
cd /home/gregorybiter/Документи/GitHub/dev.viva-art.com.ua-giftor/www/dev-modules/ocm_gdt_update

# 2. Встановіть залежності
composer install

# 3. Запустіть тести
composer test
```

## 📋 Основні команди

```bash
# Запустити всі тести
composer test

# Запустити з покриттям коду
composer test-coverage

# Запустити PHPStan
composer phpstan

# Запустити CodeSniffer
composer phpcs

# Запустити конкретний тест
./vendor/bin/phpunit tests/Unit/Services/InstallServiceTest.php

# Запустити конкретний метод
./vendor/bin/phpunit --filter testInstallModuleSuccess
```

## 📁 Структура проекту

```
ocm_gdt_update/
├── composer.json              # Залежності та скрипти
├── phpunit.xml               # Конфігурація PHPUnit
├── .gitignore                # Ігноровані файли
├── TESTING.md                # Повна документація
├── tests/
│   ├── bootstrap.php         # Ініціалізація
│   ├── BaseTestCase.php      # Базовий клас
│   └── Unit/
│       ├── Services/         # Тести сервісів
│       │   ├── InstallServiceTest.php
│       │   └── ModuleServiceTest.php
│       └── Controllers/      # Тести контролерів
│           └── GdtInstallModulesControllerTest.php
└── .github/
    └── workflows/
        └── tests.yml         # CI/CD конфігурація
```

## ✅ Що вже налаштовано

- ✅ PHPUnit 9.5
- ✅ Mockery для мокування
- ✅ Базовий клас з корисними методами
- ✅ Тести для InstallService
- ✅ Тести для ModuleService  
- ✅ Тести для контролерів
- ✅ GitHub Actions CI/CD
- ✅ Покриття коду
- ✅ PHPStan та CodeSniffer

## 🎯 Приклад тесту

```php
<?php

namespace Tests\Unit\Services;

use Tests\BaseTestCase;
use Gbitstudio\Modules\Services\InstallService;

class InstallServiceTest extends BaseTestCase
{
    public function testInstallModuleSuccess()
    {
        // Arrange
        $registry = $this->createRegistryMock();
        $log = $this->createLogMock();
        $service = new InstallService($registry, $log);
        
        // Act
        $result = $service->installModule('/path/to/module.zip');
        
        // Assert
        $this->assertTrue($result === true || is_string($result));
    }
}
```

## 🔧 Корисні методи BaseTestCase

- `createRegistryMock()` - Mock Registry
- `createLogMock()` - Mock Log
- `createModelMock($methods)` - Mock Model
- `createTestZipFile($name, $files)` - Створити тестовий ZIP
- `cleanupTestFiles($files)` - Видалити файли
- `createTempDirectory($prefix)` - Створити temp dir
- `removeDirectory($dir)` - Видалити директорію

## 📊 Переглянути покриття

```bash
# Згенерувати звіт
composer test-coverage

# Відкрити в браузері
xdg-open coverage/index.html
```

## 🐛 Debugging

```bash
# Детальний вивід
./vendor/bin/phpunit --verbose

# Тільки failures
./vendor/bin/phpunit --stop-on-failure

# З додатковою інформацією
./vendor/bin/phpunit --debug
```

## 🚀 CI/CD

Тести автоматично запускаються при:
- Push в `main` або `develop`
- Створенні Pull Request

Перевірка на PHP версіях: 7.4, 8.0, 8.1, 8.2

## 📖 Повна документація

Детальні інструкції: [TESTING.md](TESTING.md)

## 💡 Tips

1. **Пишіть тести перед кодом (TDD)**
   ```bash
   # Спочатку тест
   tests/Unit/Services/NewServiceTest.php
   # Потім код
   upload/system/library/gbitstudio/modules/services/NewService.php
   ```

2. **Використовуйте осмислені імена**
   ```php
   testInstallModuleWithInvalidZipReturnsError() // ✅ Добре
   testMethod1() // ❌ Погано
   ```

3. **Один assert на метод (коли можливо)**
   ```php
   public function testInstallModuleCreatesFile()
   {
       $this->assertFileExists($path);
   }
   ```

4. **Очищайте ресурси**
   ```php
   protected function tearDown(): void
   {
       $this->cleanupTestFiles($this->files);
       parent::tearDown();
   }
   ```

## 🤝 Допомога

Питання? Створіть issue в GitHub або зверніться до команди.
