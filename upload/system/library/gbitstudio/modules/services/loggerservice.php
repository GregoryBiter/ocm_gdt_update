<?php

namespace Gbitstudio\Modules\Services;

/**
 * Сервіс для логування
 * Створює окремий лог-файл для GDT модулів
 * Повністю статичний клас
 */
class LoggerService {
    private static $log = null;
    
    /**
     * Ініціалізує логер
     * 
     * @return \Log
     */
    private static function getLog() {
        if (self::$log === null) {
            self::$log = new \Log('gdt_modules.log');
        }
        return self::$log;
    }
    
    /**
     * Записує повідомлення в лог
     * 
     * @param string $message
     * @param string $prefix
     */
    public static function write($message, $prefix = 'GDT') {
        self::getLog()->write($prefix . ': ' . $message);
    }
    
    /**
     * Записує помилку в лог
     * 
     * @param string $message
     * @param string $context
     */
    public static function error($message, $context = '') {
        $prefix = $context ? 'GDT Error [' . $context . ']' : 'GDT Error';
        self::getLog()->write($prefix . ': ' . $message);
    }
    
    /**
     * Записує інформаційне повідомлення
     * 
     * @param string $message
     * @param string $context
     */
    public static function info($message, $context = '') {
        $prefix = $context ? 'GDT Info [' . $context . ']' : 'GDT Info';
        self::getLog()->write($prefix . ': ' . $message);
    }
    
    /**
     * Записує попередження
     * 
     * @param string $message
     * @param string $context
     */
    public static function warning($message, $context = '') {
        $prefix = $context ? 'GDT Warning [' . $context . ']' : 'GDT Warning';
        self::getLog()->write($prefix . ': ' . $message);
    }
    
    /**
     * Записує debug повідомлення
     * 
     * @param string $message
     * @param string $context
     */
    public static function debug($message, $context = '') {
        $prefix = $context ? 'GDT Debug [' . $context . ']' : 'GDT Debug';
        self::getLog()->write($prefix . ': ' . $message);
    }
    
    /**
     * Заборона створення екземплярів
     */
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize static class");
    }
}
