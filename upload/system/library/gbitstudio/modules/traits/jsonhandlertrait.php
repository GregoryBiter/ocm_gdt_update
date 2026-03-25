<?php
namespace Gbitstudio\Modules\Traits;

use Gbitstudio\Modules\Services\LoggerService;

/**
 * Трейт для роботи з JSON
 */
trait JsonHandlerTrait {
    /**
     * Декодує JSON рядок з перевіркою помилок
     * 
     * @param string $json
     * @param bool $assoc
     * @return mixed
     */
    protected function decodeJson(string $json, bool $assoc = true) {
        $data = json_decode($json, $assoc);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            LoggerService::error('JSON Decode Error: ' . json_last_error_msg());
            return $assoc ? [] : new \stdClass();
        }
        
        return $data;
    }

    /**
     * Кодує дані в JSON рядок
     * 
     * @param mixed $data
     * @return string
     */
    protected function encodeJson($data): string {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            LoggerService::error('JSON Encode Error: ' . json_last_error_msg());
            return '';
        }
        
        return $json;
    }
}
