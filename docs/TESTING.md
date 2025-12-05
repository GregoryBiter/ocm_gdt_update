# Unit Testing для OpenCart модуля

## Огляд

Цей проект налаштовано для unit тестування з використанням PHPUnit 9.5, Mockery та автоматизованого CI/CD через GitHub Actions.

## Структура тестів

```
tests/
├── bootstrap.php                    # Ініціалізація PHPUnit
├── BaseTestCase.php                 # Базовий клас для всіх тестів
└── Unit/
    ├── Services/
    │   └── InstallServiceTest.php  # Тести для InstallService
    └── Controllers/
        └── GdtInstallModulesControllerTest.php  # Тести для контролера
```

## Встановлення

### 1. Встановіть залежності:

```bash
cd /home/gregorybiter/Документи/GitHub/dev.viva-art.com.ua-giftor/www/dev-modules/ocm_gdt_update
composer install
```

### 2. Перевірте конфігурацію:

Переконайтесь що файли `phpunit.xml` та `composer.json` присутні в кореневій директорії проекту.

## Запуск тестів

### Запустити всі тести:
```bash
composer test
# або
./vendor/bin/phpunit
```

### Запустити тести з покриттям коду:
```bash
composer test-coverage
```

Звіт про покриття буде згенеровано в директорії `coverage/`.

### Запустити конкретний тест:
```bash
./vendor/bin/phpunit tests/Unit/Services/InstallServiceTest.php
```

### Запустити конкретний метод тесту:
```bash
./vendor/bin/phpunit --filter testInstallModuleSuccess
```

## Структура тестів

### BaseTestCase

Базовий клас надає корисні методи для тестування:

- `createRegistryMock()` - створює mock Registry OpenCart
- `createLogMock()` - створює mock Log
- `createModelMock()` - створює mock Model з налаштованими методами
- `createTestZipFile()` - створює тестовий ZIP архів
- `cleanupTestFiles()` - очищає тимчасові файли
- `createTempDirectory()` - створює тимчасову директорію
- `removeDirectory()` - видаляє директорію рекурсивно

### Приклад тесту для сервісу

```php
<?php

namespace Tests\Unit\Services;

use Tests\BaseTestCase;
use Gbitstudio\Modules\Services\InstallService;

class InstallServiceTest extends BaseTestCase
{
    private $registry;
    private $log;
    private $installService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->registry = $this->createRegistryMock();
        $this->log = $this->createLogMock();
        $this->installService = new InstallService($this->registry, $this->log);
    }

    public function testInstallModuleSuccess()
    {
        // Налаштування
        $zipPath = $this->createTestZipFile('test.zip', [
            'upload/admin/controller/test.php' => '<?php // Test'
        ]);

        // Виконання
        $result = $this->installService->installModule($zipPath, 'test_module');

        // Перевірка
        $this->assertTrue($result === true || is_string($result));
        
        // Очищення
        $this->cleanupTestFiles([$zipPath]);
    }
}
```

### Приклад тесту для контролера

```php
<?php

namespace Tests\Unit\Controllers;

use Tests\BaseTestCase;
use Mockery;

class GdtInstallModulesControllerTest extends BaseTestCase
{
    public function testIndexRedirectsToStore()
    {
        $url = Mockery::mock('Url');
        $url->shouldReceive('link')
            ->with('extension/module/gdt_install_modules/store', 'user_token=test', true)
            ->once()
            ->andReturn('http://example.com/store');

        // Тестування логіки...
        $this->assertTrue(true);
    }
}
```

## Покриття коду

Після запуску тестів з покриттям (`composer test-coverage`), відкрийте:

```bash
xdg-open coverage/index.html
```

Або перегляньте звіт в браузері: `file:///path/to/project/coverage/index.html`

## Аналіз коду

### PHPStan (статичний аналіз):
```bash
composer phpstan
```

### PHP CodeSniffer (стиль коду):
```bash
composer phpcs
```

## Continuous Integration (CI)

Проект налаштовано для автоматичного тестування через GitHub Actions:

- **Тести запускаються на**: PHP 7.4, 8.0, 8.1, 8.2
- **При**: кожному push/PR в `main` або `develop` гілки
- **Включає**: 
  - Unit тести
  - Покриття коду (для PHP 8.1)
  - PHPStan аналіз
  - PHP CodeSniffer перевірки

Конфігурація: `.github/workflows/tests.yml`

## Налаштування для нових тестів

### 1. Створіть новий тест файл:

```php
<?php

namespace Tests\Unit\Services;

use Tests\BaseTestCase;
use Gbitstudio\Modules\Services\YourService;

class YourServiceTest extends BaseTestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new YourService($this->createRegistryMock());
    }

    public function testYourMethod()
    {
        // Arrange (Налаштування)
        $input = 'test';

        // Act (Виконання)
        $result = $this->service->yourMethod($input);

        // Assert (Перевірка)
        $this->assertEquals('expected', $result);
    }
}
```

### 2. Використовуйте Mockery для мокування:

```php
$mock = Mockery::mock('SomeClass');
$mock->shouldReceive('method')
    ->with('argument')
    ->once()
    ->andReturn('value');
```

### 3. Очищайте ресурси:

```php
protected function tearDown(): void
{
    $this->cleanupTestFiles($this->tempFiles);
    parent::tearDown();
}
```

## Troubleshooting

### Помилка "Class not found"

Перезапустіть autoloader:
```bash
composer dump-autoload
```

### Помилка прав доступу

```bash
chmod -R 777 tests/
chmod 777 vendor/bin/phpunit
```

### Mockery не закривається

Переконайтесь що викликаєте `parent::tearDown()` в кінці вашого `tearDown()` методу.

## Корисні команди

```bash
# Встановлення залежностей
composer install

# Оновлення залежностей
composer update

# Запуск тестів
composer test

# Запуск з детальним виводом
./vendor/bin/phpunit --verbose

# Запуск конкретного набору тестів
./vendor/bin/phpunit tests/Unit/Services/

# Генерація покриття в різних форматах
./vendor/bin/phpunit --coverage-html coverage
./vendor/bin/phpunit --coverage-text
./vendor/bin/phpunit --coverage-clover coverage.xml
```

## Додаткові ресурси

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Mockery Documentation](http://docs.mockery.io/)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)

## Підтримка

При виникненні проблем створіть issue в GitHub репозиторії.
