---
name: add-service
description: Step-by-step skill for creating, registering, and wiring a new SOA service in ocm_gdt_update
---

# Skill: Add Service (`add-service`)

This skill provides step-by-step guidance for adding a new logical domain service to the Service-Oriented Architecture (SOA) of `ocm_gdt_update`.

## When to Use
Use this skill whenever introducing new business logic (e.g. cache management, validation, event handling, or analytics) to keep controllers/models thin and maintain testability.

---

## Detailed Procedure

### 1. Define Service Scope & Domain
- Identify domain boundaries (e.g., `ValidationService`, `BackupService`, `EventService`).
- Define public interface methods and needed dependencies (e.g., `$db`, `$log`, `$config`).

### 2. Implement the Service Class
- Create file in `upload/system/library/gbitstudio/modules/services/[servicename].php`.
- Class naming should be `PascalCase` within the `Gbitstudio\Modules\Services` namespace.
- Inject dependencies through the constructor.
- Use `LoggerService` for internal logging.

```php
<?php
namespace Gbitstudio\Modules\Services;

class YourServiceName {
    /** @var \DB */
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Example method
     *
     * @return mixed
     */
    public function doSomething() {
        LoggerService::info("YourServiceName: executed doSomething");
        // Business logic here
    }
}
```

### 3. Register in `ServiceFactory`
- Edit `upload/system/library/gbitstudio/modules/servicefactory.php`.
- Add a private property for the service instance:
  ```php
  private $yourService = null;
  ```
- Add a getter method following lazy initialization / singleton pattern:
  ```php
  public function getYourService(): Services\YourServiceName {
      if ($this->yourService === null) {
          require_once __DIR__ . '/services/yourservicename.php';
          $this->yourService = new Services\YourServiceName($this->db);
      }
      return $this->yourService;
  }
  ```

### 4. Provide Backward Compatibility (Optional)
- If legacy code or external consumers rely on `upload/system/library/gbitstudio/modules/manager.php`, add a proxy method delegating to the new service via `ServiceFactory`.

### 5. Add Unit Tests
- Create a test class under `tests/Unit/Services/YourServiceNameTest.php`.
- Extend `BaseTestCase` and mock OpenCart dependencies where necessary.

### 6. Verify Code
- Check syntax: `php -l upload/system/library/gbitstudio/modules/services/yourservicename.php`.
- Run tests: `vendor/bin/phpunit tests/Unit/Services/YourServiceNameTest.php`.
