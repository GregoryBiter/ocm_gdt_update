<?php
namespace Gbitstudio\Modules\Services;

/**
 * Базовий клас для всіх сервісів модуля GDT
 */
abstract class BaseService {
    /** @var \Registry */
    protected $registry;
    
    /** @var \Config */
    protected $config;

    /**
     * Конструктор з реєстрами
     * 
     * @param \Registry $registry
     */
    public function __construct(\Registry $registry) {
        $this->registry = $registry;
        $this->config = $registry->get('config');
    }

    /**
     * Логування інформації від сервісу
     * 
     * @param string $message
     */
    protected function logInfo(string $message): void {
        LoggerService::info($message, get_class($this));
    }

    /**
     * Логування помилок від сервісу
     * 
     * @param string $message
     */
    protected function logError(string $message): void {
        LoggerService::error($message, get_class($this));
    }

    /**
     * Стандартизована відповідь з успіхом
     * 
     * @param mixed $data
     * @return array
     */
    protected function success($data = null): array {
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Стандартизована відповідь з помилкою
     * 
     * @param string $error
     * @param mixed $data
     * @return array
     */
    protected function error(string $error, $data = null): array {
        return [
            'success' => false,
            'error' => $error,
            'data' => $data
        ];
    }
}
