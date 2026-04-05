# Antigravity Instructions for OCM GDT Update Module

This document provides architectural context and guidelines for Antigravity AI when working on the `ocm_gdt_update` OpenCart module.

## Core Architecture

The module follows a **Service-Oriented Architecture (SOA)** with a **Facade** pattern via `ServiceFactory`.

### Key Components

- **ServiceFactory** (`upload/system/library/gbitstudio/modules/servicefactory.php`): Centralized manager for all services. Injected into the OpenCart registry as `gb_modules`.
- **ModuleService** (`upload/system/library/gbitstudio/modules/services/moduleservice.php`): Handles retrieval of installed modules. 
- **InstallService** (`upload/system/library/gbitstudio/modules/services/installservice.php`): Handles ZIP extraction, file moving, and registration.
- **UpdateService** (`upload/system/library/gbitstudio/modules/services/updateservice.php`): Interacts with external update servers.
- **ModelExtensionModuleGdtUpdater** (`upload/admin/model/extension/module/gdt_updater.php`): Handles DB table creation and low-level migrations.

## Database Storage (CRITICAL)

As of the latest refactoring, the module **no longer uses JSON files** (`opencart-module.json`) in `DIR_SYSTEM . 'modules/'` for storing installed module metadata and paths. All data is now kept in the database.

### Table: `{DB_PREFIX}gdt_modules`

- `module_id`: Primary Key.
- `code`: Unique module code (e.g., `ocm_test`). 
- `name`, `version`: Short metadata fields for quick access.
- `data`: Complete metadata from `opencart-module.json`, stored as JSON.
- `paths`: A JSON array of all file paths installed by the module.
- `date_added`: Timestamp.

## Development Guidelines

1. **Database-First**: Always read/write module data using `ModuleService` or the Model, which target the database. Do not attempt to find `opencart-module.json` files on the filesystem for installed modules.
2. **Service Factory**: Use `$this->getServiceFactory()->getModuleService()` (in Controllers/Models) or the `gb_modules` registry key to access services.
3. **Paths Management**: When installing a new module, ensure all moved files are collected and saved into the `paths` column of the `{DB_PREFIX}gdt_modules` table.
4. **Naming Conventions**:
    - Services should follow the `PascalCase` naming for classes and `camelCase` for methods.
    - Database tables MUST use `DB_PREFIX`.
5. **OpenCart Integration**:
    - Prefer using the `Registry` to share services.
    - Use `LoggerService` for all logging needs.
    - Maintain backward compatibility with OpenCart's standard `modification` and `extension` tables where applicable.

## Common Tasks

- **Adding a new Service**: Add a getter in `ServiceFactory.php` and implement the service logic in `upload/system/library/gbitstudio/modules/services/`.
- **Schema Updates**: Update `createTables()` in `ModelExtensionModuleGdtUpdater.php` and ensure `install()` in the controller handles the update.
