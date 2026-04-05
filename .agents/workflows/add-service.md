---
description: Workflow for adding a new service to the OCM GDT Update module.
---

# /add-service

This workflow guides you through the process of adding a new logical service to the module's service-oriented architecture.

## Steps:

1. **Research and Define Service Scope**:
   - Determine the specific domain (e.g., `Cache`, `Validation`, `Event`).
   - Define public methods for the service.

2. **Create the Service File**:
   - Create a new PHP file in `upload/system/library/gbitstudio/modules/services/[ServiceName].php`.
   - Implement the class inside the `Gbitstudio\Modules\Services` namespace.
   - Use `LoggerService` for internal logging.
   ```php
   namespace Gbitstudio\Modules\Services;
   class YourServiceName {
       private $db;
       public function __construct($db) { $this->db = $db; }
       // Add methods
   }
   ```

3. **Update ServiceFactory**:
   - Modify `upload/system/library/gbitstudio/modules/servicefactory.php`.
   - Add a private property to hold the service instance.
   - Add a public getter method (e.g., `getYourService()`) following the singleton pattern.
   - Ensure necessary dependencies (like `$db`, `$log`) are passed in the constructor.

4. **Update the Main Manager Facade (Optional)**:
   - If the service should be accessible via the standard `Manager` class for backward compatibility, add a delegating method to `upload/system/library/gbitstudio/modules/manager.php`.

5. **Verify and Document**:
   - Add documentation to the service file.
   - Update `docs/REFACTORING_REPORT.md` if significant.
