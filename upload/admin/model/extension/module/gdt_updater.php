<?php

use Gbitstudio\Modules\Services\LoggerService;

class ModelExtensionModuleGdtUpdater extends Model
{
    /**
     * Отримує фабрику сервісів
     *
     * @return \Gbitstudio\Modules\ServiceFactory
     */
    private function getServiceFactory()
    {
        if (!$this->registry->has('gb_modules')) {
            $this->registry->set('gb_modules', new \Gbitstudio\Modules\ServiceFactory($this->registry));
        }

        return $this->registry->get('gb_modules');
    }

    /**
     * Створює необхідні таблиці в базі даних
     */
    public function createTables()
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_modules` (
                `module_id` INT(11) NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(64) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `type` VARCHAR(32) NOT NULL DEFAULT 'module',
                `installed_version` VARCHAR(32) NOT NULL DEFAULT '0.0.0',
                `source` VARCHAR(32) NOT NULL DEFAULT 'gdt_updater',
                `metadata_json` MEDIUMTEXT NOT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `installed_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`module_id`),
                UNIQUE KEY `code` (`code`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_module_files` (
                `file_id` INT(11) NOT NULL AUTO_INCREMENT,
                `module_code` VARCHAR(64) NOT NULL,
                `file_path` VARCHAR(500) NOT NULL,
                `file_hash` VARCHAR(64) NOT NULL DEFAULT '',
                `installed_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `removed_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`file_id`),
                KEY `module_code` (`module_code`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ocm_module_versions` (
                `version_id` INT(11) NOT NULL AUTO_INCREMENT,
                `module_code` VARCHAR(64) NOT NULL,
                `version` VARCHAR(32) NOT NULL,
                `package_hash` VARCHAR(64) NOT NULL DEFAULT '',
                `index_hash` VARCHAR(64) NOT NULL DEFAULT '',
                `changelog` TEXT NOT NULL,
                `source` VARCHAR(32) NOT NULL DEFAULT 'gdt_updater',
                `published_at` DATETIME NOT NULL,
                `applied_at` DATETIME NOT NULL,
                PRIMARY KEY (`version_id`),
                KEY `module_code` (`module_code`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");
    }

    /**
     * Видаляє таблиці модуля
     */
    public function dropTables()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ocm_module_versions`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ocm_module_files`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ocm_modules`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "gdt_modules`");
    }

    /**
     * Отримує модулі з бази даних
     *
     * @return array
     */
    public function getModulesFromDatabase()
    {
        return $this->getServiceFactory()->getModuleService()->getInstalledModules();
    }

    /** Compatibility method: installed metadata now comes from modules.json. */
    public function getModuleFromDatabase($code)
    {
        return $this->getServiceFactory()->getModuleService()->getModuleByCode($code);
    }

    /**
     * Витягує опис з XML
     *
     * @param string $xml
     * @return string
     */
    private function extractDescription($xml)
    {
        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->loadXml($xml);
            $description = $dom->getElementsByTagName('description')->item(0);
            return $description ? $description->nodeValue : '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Автоматическая проверка и запуск обновления модулей при загрузке dashboard
     */
    public function autoCheckUpdate()
    {
        $autoUpdateService = $this->getServiceFactory()->getAutoUpdateService();
        $result = $autoUpdateService->autoCheckAndUpdate();

        $this->load->language('extension/module/gdt_updater');

        if (!empty($result['updated'])) {
            $this->session->data['success'] = sprintf($this->language->get('text_auto_updated'), implode(', ', $result['updated']));
        }

        if (!empty($result['errors'])) {
            $this->session->data['warning'] = sprintf($this->language->get('error_auto_update_failed'), implode('; ', $result['errors']));
        }
    }

    /**
     * Проверка наличия обновлений для конкретного модуля
     */
    public function checkForUpdate($module_code = null)
    {
        if (!$module_code) {
            return ['available' => false];
        }

        $autoUpdateService = $this->getServiceFactory()->getAutoUpdateService();
        return $autoUpdateService->checkForUpdate($module_code);
    }

    /**
     * Выполнение обновления для конкретного модуля
     */
    public function performUpdate($module_code, $update_info = null)
    {
        if (!$module_code) {
            return 'Не указан код модуля';
        }

        $autoUpdateService = $this->getServiceFactory()->getAutoUpdateService();
        return $autoUpdateService->performModuleUpdate($module_code, $update_info);
    }
}
