---
description: Architecture and Database Rules for OCM GDT Update
activation: Always on
---

# Architecture and Database Rules

This rule ensures that all development on the `ocm_gdt_update` module follows the established Service-Oriented Architecture (SOA) and database-first approach.

## 1. Registry and Database Responsibilities
- `DIR_STORAGE . 'modules.json'` is authoritative for installed module membership and versions.
- Use `ModuleRegistryService` via `ServiceFactory`. Do not discover modules from `extension`, `modification`, or loose metadata files.
- Import active `ocm_modules` records once, only when the registry is absent. Invalid JSON must produce an error without replacement.
- Keep `ocm_modules` as a compatibility mirror, `ocm_module_files` for file ownership, and `ocm_module_versions` for history.
- Package metadata has version priority; use a server version only when bound to the downloaded release. Unknown versions disable automatic updates.
- Use a stable separate lock file, reread under lock, and atomically rename a temporary file in the same storage directory.

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
