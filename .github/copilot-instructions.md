# GitHub Copilot Instructions for OCM GDT Update

This repository is an OpenCart module for managing and updating other modules. 

## Architectural Context
- **Framework**: OpenCart 3.0+.
- **Pattern**: Service-Oriented Architecture (SOA).
- **Core Library**: `upload/system/library/gbitstudio/modules/`.
- **Primary Data Source**: Database table `{DB_PREFIX}gdt_modules`.
- **JSON Metadata**: Stored within the `data` and `paths` columns of the `gdt_modules` table.

## Code Generation Guidelines
1. **Dependency Injection**: Use `ServiceFactory` to access services. For example:
   ```php
   $service = $this->getServiceFactory()->getModuleService();
   ```
2. **Database Queries**: Always wrap table names in `{DB_PREFIX}`.
3. **Naming**: Use `PascalCase` for classes and `camelCase` for methods/properties.
4. **Logging**: Use `LoggerService::info()` or `LoggerService::error()` for all logging.
5. **Metadata Handling**: When generating functions related to module data, remember that metadata (from `opencart-module.json`) is stored in the `data` JSON column in the database.
6. **File Paths**: Use `constant('DIR_SYSTEM')`, `constant('DIR_APPLICATION')`, etc. for system paths.

## Key Files
- `system/library/gbitstudio/modules/servicefactory.php`: Main service manager.
- `system/library/gbitstudio/modules/services/moduleservice.php`: Module management service.
- `admin/model/extension/module/gdt_updater.php`: Model for DB operations.
- `admin/controller/extension/module/gdt_updater.php`: Main controller.
