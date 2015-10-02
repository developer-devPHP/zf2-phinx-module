<?php
/**
 * ZF2 Phinx Module
 *
 * @link      https://github.com/valorin/zf2-phinx-module
 * @copyright Copyright (c) 2012-2013 Stephen Rees-Carter <http://stephen.rees-carter.net/>
 * @license   See LICENCE.txt - New BSD License
 */

namespace PhinxModule;

use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Db\Adapter\Adapter;
use Zend\EventManager\EventInterface as Event;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use OAuth2\Server as OAuth2Server;

class Module implements
    AutoloaderProviderInterface,
    ConfigProviderInterface,
    ConsoleBannerProviderInterface,
    ConsoleUsageProviderInterface
{
    /**
     * @var string
     */
    const VERSION = '0.1.1';

    /**
     * Generates the Console Banner text
     *
     * @param  Console $console
     *
     * @return String
     */
    public function getConsoleBanner(Console $console)
    {
        /**
         * Work out Phinx version
         */
        $file = false;
        $version = "unknown";
        if (file_exists(getcwd() . "/vendor/bin/phinx"))
        {
            $file = file_get_contents(getcwd() . "/vendor/bin/phinx");
        }

        if ($file && preg_match("/(\d+\.\d+\.\d+)/", $file, $match))
        {
            $version = "v" . $match[1];
        }

        /**
         * Output version
         */
        $status = "=> Phinx {$version}\n"
            . "=> Phinx module v" . self::VERSION;

        return $status;
    }

    /**
     * Define Console Help text
     *
     * @param  Console $console
     *
     * @return String
     */
    public function getConsoleUsage(Console $console)
    {
        return array(
            'Phinx module commands',
            'phinx setup [--overwrite]' => "Interactive Phinx setup wizard - will create both config files for you.",
            'phinx sync ' => "Sync application database credentials with Phinx.",
            'phinx groupmigrate --serverip=' => "Group Migration with selected server IP",
            'phinx' => "List the Phinx console usage information.",
            'phinx <phinx commands>' => "Run the specified Phinx command (run 'phinx' for the commands list).",

            Array('--overwrite', "Will force the setup tool to run and overwrite any existing configuration."),
            Array('<phinx commands>', "Any support Phinx commands - will be passed through to Phinx as-is."),
        );
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__,
                ),
            ),
        );
    }

    public function getConfig($env = null)
    {
        return include __DIR__ . '/../../config/module.config.php';
    }

    /*public function onBootstrap(Event $e)
    {

        var_dump('asd');exit;
    }*/

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'prod-db-clients' => function ($sm)
                {
                    if ($sm->has('prod-db-clients-lsit') && $sm->get('prod-db-clients-lsit') instanceof Adapter)
                    {
                        return $sm->get('prod-db-clients-lsit');
                    }
                    else
                    {
                        return false;
                    }
                },
                'phinxOauth2ServerInstance'=>function($services)
                {
                    $oauth2ServerFactory = $services->get('ZF\OAuth2\Service\OAuth2Server');
                    if ($oauth2ServerFactory instanceof OAuth2Server) {
                        $oauth2Server = $oauth2ServerFactory;
                        $oauth2ServerFactory = function () use ($oauth2Server) {
                            return $oauth2Server;
                        };
                    }
                    $config = $services->get('config');
                    if(isset($config['zf-mvc-auth']['authentication']['adapters']['oauth_db']['storage']['route']))
                    {
                        $oauth2_route = $config['zf-mvc-auth']['authentication']['adapters']['oauth_db']['storage']['route'];
                        return call_user_func($oauth2ServerFactory, $oauth2_route); // object of OAuth2\Server
                    }
                    else
                    {
                        throw new \Exception('Oauth2 Storage not set or Oauth2 adappter name not "oauth_db"',404);
                    }
                }
            )
        );
    }
}
