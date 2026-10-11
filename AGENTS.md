# AGENTS.md — AI Agent Guidelines for OCM GDT Update

This document provides universal instructions, architectural guidelines, and operating rules for AI coding assistants and autonomous agents (such as Antigravity, Claude, ChatGPT/Codex, Cursor, GitHub Copilot, etc.) working on the `ocm_gdt_update` repository.

---

## 1. Project Overview

`ocm_gdt_update` is an OpenCart 3.0+ extension designed to manage and update other modules in OpenCart stores.
It provides automatic update checks against an external update server, archive extraction, file installation, database migrations, and module tracking.

- **Target Framework**: OpenCart 3.0+
- **Architecture**: Service-Oriented Architecture (SOA) with a Facade pattern via `ServiceFactory`
- **Core Library Path**: `upload/system/library/gbitstudio/modules/`
- **Admin Controller**: `upload/admin/controller/extension/module/gdt_updater.php`
- **Admin Model**: `upload/admin/model/extension/module/gdt_updater.php`

---

## 2. Core Architectural Principles

### 2.1. Database-First Principle (CRITICAL)
- **NO FILESYSTEM JSON STORAGE FOR INSTALLED MODULES**: Do **NOT** use or search for `opencart-module.json` files in `DIR_SYSTEM . "modules/"` for discovering or reading metadata of installed modules.
- Installed module records and tracking files are stored exclusively in the database tables:
  - `{DB_PREFIX}ocm_modules`: (`module_id`, `code`, `name`, `type`, `installed_version`, `source`, `metadata_json`, `status`, `installed_at`, `updated_at`).
  - `{DB_PREFIX}ocm_module_files`: (`file_id`, `module_code`, `file_path`, `file_hash`, `installed_at`, `updated_at`, `removed_at`).
  - `{DB_PREFIX}ocm_module_versions`: (`version_id`, `module_code`, `version`, `package_hash`, `index_hash`, `changelog`, `source`, `published_at`, `applied_at`).
  *(Legacy compatibility table: `{DB_PREFIX}gdt_modules`)*

### 2.2. Service-Oriented Architecture (SOA)
- Business logic is strictly separated from OpenCart controllers and models, encapsulated in domain services located in `upload/system/library/gbitstudio/modules/services/`.
- **Services Directory**: `upload/system/library/gbitstudio/modules/services/`
  - `ModuleService`: Retrieval and management of installed modules from DB.
  - `InstallService`: ZIP archive extraction, validation, filesystem copying, and DB registration.
  - `UpdateService`: Remote communication with update servers, version checks, and download management.
  - `LoggerService`: Centralized logging.
- **ServiceFactory** (`upload/system/library/gbitstudio/modules/servicefactory.php`):
  - Singleton-style provider for all services with Dependency Injection.
  - Injected into OpenCart's `Registry` under the key `gb_modules`.
  - In controllers/models, access via `$this->getServiceFactory()->getModuleService()` or `$this->registry->get('gb_modules')->...`.

---

## 3. Coding Standards & Conventions

1. **Naming Conventions**:
   - **Classes**: `PascalCase` (e.g., `ModuleService`, `InstallService`).
   - **Methods & Properties**: `camelCase` (e.g., `getInstalledModules()`, `createModule()`).
   - **Files**: All lowercase or matching class conventions per OpenCart autoloading (e.g. `moduleservice.php`).
2. **Database Queries**:
   - Always wrap table names with `{DB_PREFIX}` or `DB_PREFIX . 'gdt_modules'`.
   - Escape values using `$this->db->escape()`.
3. **Logging**:
   - Never use raw `error_log` or `var_dump` in production code.
   - Use `LoggerService::info()` and `LoggerService::error()`.
4. **Filesystem & Path Safety**:
   - Always use defined OpenCart constants (`constant('DIR_SYSTEM')`, `constant('DIR_APPLICATION')`, `DIR_CATALOG`, etc.).
   - Validate destination paths against allowed directories (`admin/`, `catalog/`, `image/`, `system/`) before moving files.

---

## 4. Skills & Agent Extensions

Project-specific workflows and capabilities are documented as Agent Skills in [`.agents/skills/`](file:///.agents/skills/):

- [`.agents/skills/add-service/SKILL.md`](file:///.agents/skills/add-service/SKILL.md): Step-by-step workflow for creating, registering, and exposing a new SOA service.

Rules and additional documentation:
- [`.agents/rules/architecture.md`](file:///.agents/rules/architecture.md): Strict architectural and database constraints.
- [`docs/ARCHITECTURE.md`](file:///docs/ARCHITECTURE.md): Detailed architectural design.
- [`docs/TESTING.md`](file:///docs/TESTING.md) and [`TESTING_QUICKSTART.md`](file:///TESTING_QUICKSTART.md): Testing guidelines.

---

## 5. Verification & Testing

When adding or modifying features:
1. **Unit Tests**:
   - Place tests under `tests/Unit/Services/` or `tests/Unit/Controllers/`.
   - Run tests using PHPUnit:
     ```bash
     vendor/bin/phpunit
     ```
2. **Coding Standards**:
   - Follow PHPCS configuration in `phpcs.xml`:
     ```bash
     vendor/bin/phpcs
     ```
3. **Syntax Checking**:
   - Ensure all modified PHP files have no syntax errors:
     ```bash
     php -l path/to/file.php
     ```
