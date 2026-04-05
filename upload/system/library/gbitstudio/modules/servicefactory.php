<?php

namespace Gbitstudio\Modules;

use Gbitstudio\Modules\Services\ModuleService;
use Gbitstudio\Modules\Services\UpdateService;
use Gbitstudio\Modules\Services\InstallService;
use Gbitstudio\Modules\Services\DashboardService;
use Gbitstudio\Modules\Services\AutoUpdateService;
use Gbitstudio\Modules\Services\ModuleCatalogService;
use Gbitstudio\Modules\Services\LoggerService;

/**
 * Фабрика для створення та управління сервісами
 * Централізоване сховище сервісів в registry
 */
class ServiceFactory {
    private $registry;
    private $services = [];
    private static $instance = null;
    
    public function __construct($registry) {
        $this->registry = $registry;
    }
    
    /**
     * Отримує singleton instance фабрики
     * 
     * @param \Registry $registry
     * @return ServiceFactory
     */
    public static function getInstance($registry) {
        if (self::$instance === null) {
            self::$instance = new self($registry);
        }
        return self::$instance;
    }
    
    /**
     * Отримує сервіс автооновлень
     * 
     * @return ModuleService
     */
    public function getModuleService() {
        if (!isset($this->services['module'])) {
            $this->services['module'] = new ModuleService($this->registry);
        }
        
        return $this->services['module'];
    }
    
    /**
     * Отримує сервіс оновлень
     * 
     * @return UpdateService
     */
    public function getUpdateService() {
        if (!isset($this->services['update'])) {
            $this->services['update'] = new UpdateService($this->registry);
        }
        
        return $this->services['update'];
    }
    
    /**
     * Отримує сервіс встановлення
     * 
     * @return InstallService
     */
    public function getInstallService() {
        if (!isset($this->services['install'])) {
            $this->services['install'] = new InstallService($this->registry);
        }
        
        return $this->services['install'];
    }
    
    /**
     * Отримує сервіс dashboard
     * 
     * @return DashboardService
     */
    public function getDashboardService() {
        if (!isset($this->services['dashboard'])) {
            $moduleService = $this->getModuleService();
            $updateService = $this->getUpdateService();
            $config = $this->registry->get('config');
            
            $this->services['dashboard'] = new DashboardService(
                $moduleService,
                $updateService,
                $config
            );
        }
        
        return $this->services['dashboard'];
    }
    
    /**
     * Отримує сервіс автооновлень
     * 
     * @return AutoUpdateService
     */
    public function getAutoUpdateService() {
        if (!isset($this->services['autoupdate'])) {
            $moduleService = $this->getModuleService();
            $updateService = $this->getUpdateService();
            $installService = $this->getInstallService();
            $config = $this->registry->get('config');
            $cache = $this->registry->get('cache');
            
            $this->services['autoupdate'] = new AutoUpdateService(
                $moduleService,
                $updateService,
                $installService,
                $config,
                $cache
            );
        }
        
        return $this->services['autoupdate'];
    }
    
    /**
     * Отримує сервіс каталогу модулів
     * 
     * @return ModuleCatalogService
     */
    public function getModuleCatalogService() {
        if (!isset($this->services['catalog'])) {
            $config = $this->registry->get('config');
            
            $this->services['catalog'] = new ModuleCatalogService($config);
        }
        
        return $this->services['catalog'];
    }
    
    /**
     * Очищає кеш сервісів (при необхідності)
     */
    public function clearCache() {
        $this->services = [];
    }
}
