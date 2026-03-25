<?php
namespace Gbitstudio\Modules;

/**
 * Централізовані константи для GDT модуля
 */
class GdtConstants {
    // Шляхи
    public const DIR_MODULES = DIR_SYSTEM . 'modules/';
    public const FILE_MODULE_INDEX = DIR_SYSTEM . 'module-index.json';
    public const FILE_LOG = 'gdt_modules.log';

    // Конфігурація OpenCart
    public const CONFIG_SERVER_URL = 'module_gdt_updater_server';
    public const CONFIG_API_KEY = 'module_gdt_updater_api_key';

    // Джерела даних
    public const SOURCE_JSON = 'opencart_module_json';
    public const SOURCE_MODIFICATION = 'opencart_modification';

    // Відповіді API
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
}
