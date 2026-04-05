---
description: Architecture and Database Rules for OCM GDT Update
activation: Always on
---

# Architecture and Database Rules

This rule ensures that all development on the `ocm_gdt_update` module follows the established Service-Oriented Architecture (SOA) and database-first approach.

## 1. Database-First Principle
- **CRITICAL**: Do NOT use `opencart-module.json` files in `DIR_SYSTEM . "modules/"` for module discovery or data storage for installed modules.
- Always use the `{DB_PREFIX}gdt_modules` table.
- Metadata is stored in the `data` (JSON) column.
- Installed file paths are stored in the `paths` (JSON array) column.

## 2. Service-Oriented Architecture
- Logic must be encapsulated in Services located in `upload/system/library/gbitstudio/modules/services/`.
- Services must be managed by the `ServiceFactory`.
- Use Dependency Injection via the constructor for services (e.g., passing `$db`, `$log`).
- Access services through the `gb_modules` registry key or `ServiceFactory`.

## 3. Naming and Conventions
- **Classes**: `PascalCase` (e.g., `ModuleService`).
- **Methods**: `camelCase` (e.g., `getInstalledModules`).
- **Database**: Always use `DB_PREFIX` for table names.
- **Logging**: Use `LoggerService` for all application logging.

## 4. Path Safety
- When working with file operations (e.g., `InstallService`), ensure paths are validated against a whitelist of allowed directories (`admin/`, `catalog/`, `image/`, `system/`, and project root if necessary).
- Use `constant('DIR_...')` for base paths.
