<?php

namespace Gbitstudio\Modules;

require_once(__DIR__ . '/gdtconstants.php');
require_once(__DIR__ . '/traits/jsonhandlertrait.php');
require_once(__DIR__ . '/traits/serverurltrait.php');
require_once(__DIR__ . '/traits/httpclienttrait.php');
require_once(__DIR__ . '/services/baseservice.php');

use Gbitstudio\Modules\Services\ModuleService;
use Gbitstudio\Modules\Services\UpdateService;
use Gbitstudio\Modules\Services\ModuleIndexService;
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
     * Отримує сервіс індексу модулів
     * 
     * @return ModuleIndexService
     */
    public function getModuleIndexService() {
        if (!isset($this->services['index'])) {
            require_once(__DIR__ . '/services/moduleindexservice.php');
            $this->services['index'] = new ModuleIndexService();
        }
        
        return $this->services['index'];
    }

    /**
     * Отримує сервіс модулів
     * 
     * @return ModuleService
     */
    public function getModuleService() {
        if (!isset($this->services['module'])) {
            require_once(__DIR__ . '/services/moduleservice.php');
            $this->services['module'] = new ModuleService($this->getModuleIndexService());
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
            require_once(__DIR__ . '/services/updateservice.php');
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
            require_once(__DIR__ . '/services/installservice.php');
            $this->services['install'] = new InstallService(
                $this->registry, 
                $this->getModuleService(),
                $this->getModuleIndexService()
            );
        }
        
        return $this->services['install'];
    }
    
    /**
     * Отримує сервіс дашборда
     * 
     * @return DashboardService
     */
    public function getDashboardService() {
        if (!isset($this->services['dashboard'])) {
            require_once(__DIR__ . '/services/dashboardservice.php');
            $this->services['dashboard'] = new DashboardService(
                $this->getModuleService(),
                $this->getUpdateService(),
                $this->registry->get('config')
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
            require_once(__DIR__ . '/services/autoupdateservice.php');
            $this->services['autoupdate'] = new AutoUpdateService(
                $this->getModuleService(),
                $this->getUpdateService(),
                $this->getInstallService(),
                $this->registry->get('config'),
                $this->registry->get('cache')
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
            require_once(__DIR__ . '/services/modulecatalogservice.php');
            $this->services['catalog'] = new ModuleCatalogService($this->registry);
        }
        
        return $this->services['catalog'];
    }
    
    /**
     * Очищає кеш сервісів
     */
    public function clearCache() {
        $this->services = [];
    }
}
