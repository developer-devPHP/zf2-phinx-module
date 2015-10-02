<?php

namespace PhinxModule\Factory;


use OAuth2\Request;
use PhinxModule\Controller\MigrateController;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use OAuth2\Server;
class MigrateControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $controllers)
    {
        $services = $controllers->getServiceLocator()->get('ServiceManager');
        $oauth2Server = $services->get('phinxOauth2ServerInstance');
        $config = $services->get('config');
        $permissionConfig = null;
        if(isset($config['phinx-module']['permissions']))
        {
            $permissionConfig = $config['phinx-module']['permissions'];
        }
        else
        {
            throw new \Exception('phinx module permissions not set in config file');
        }

        $migrateController = new MigrateController($oauth2Server,$permissionConfig);
        return $migrateController;
    }
}
