<?php
namespace Gbitstudio\Modules\Traits;

use Gbitstudio\Modules\GdtConstants;

/**
 * Трейт для визначення URL сервера GDT
 */
trait ServerUrlTrait {
    /** @var \Config */
    protected $config;

    /**
     * Отримує базовий URL сервера для API запитів
     * 
     * @return string
     */
    protected function getServerUrl(): string {
        $server_url = $this->config->get(GdtConstants::CONFIG_SERVER_URL);
        
        if (empty($server_url)) {
            $server_url = 'https://api.gbitstudio.com/'; // Дефолтне значення
        }
        
        return rtrim($server_url, '/') . '/';
    }
}
