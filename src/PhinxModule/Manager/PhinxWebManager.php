<?php
/**
 * ZF2 Phinx Module
 *
 * @link      https://github.com/valorin/zf2-phinx-module
 * @copyright Copyright (c) 2012-2013 Stephen Rees-Carter <http://stephen.rees-carter.net/>
 * @license   See LICENCE.txt - New BSD License
 */

namespace PhinxModule\Manager;

use Symfony\Component\Yaml\Yaml;
use Zend\Config\Config;
use Zend\Config\Writer\PhpArray;
use Zend\Console\Adapter\AbstractAdapter as ConsoleAdapter;
use Zend\Console\ColorInterface;
use Zend\Console\Prompt;
use Zend\Db\Adapter\Adapter;

class PhinxWebManager
{
    /**
     * @var string Path to the phinx command, relative to __DIR__
     */
    const PHINX_PHP = '/../../../../../robmorgan/phinx/app/phinx.php';

    /**
     * @var array
     */
    protected $config;

    /**
     * @var DbAdapter | false
     */
    protected $clientslistdbAdapter;

    /**
     * Constructor
     *
     * @param  ConsoleAdapter $console
     * @param  array $config
     *
     * @throws RuntimeException
     */
    public function __construct($config = array(), $clientslistdbAdapter)
    {
        $this->config = $config;
        $this->clientslistdbAdapter = $clientslistdbAdapter;
    }

    public function getStatus($migration_client,$responce)
    {
        $headers = $responce->getHeaders();
        $status =  $this->getPhinxWrapper()->getStatus($migration_client);
        $result = array();
        preg_match('/\[{\s*"migration_status"\s*:\s*(.+?)\s*,\s*"migration_id":\s*(.+?)\s*,\s*"migration_name"\s*:\s*(.+?)\s*\}]/',$status,$result);
        if(!empty($result))
        {
            $headers->addHeaderLine('Content-Type','application/json');
            $status = $result[0];
        }
        $responce->setContent($status);
        return $responce;
    }

    public function groupMigrate()
    {

    }

    protected function getPhinxWrapper()
    {
        $app = require_once __DIR__ . self::PHINX_PHP;

        if (isset($this->config['phinx-module']['phinx-name']))
        {
            $app->setName($this->config['phinx-module']['phinx-name']);
        }

        $phinx_config = $this->config['phinx-module']['phinx-config'];
        $appenv = $this->config['phinx-module']['phinx-env'];

        $phix_config = dirname($phinx_config) . DIRECTORY_SEPARATOR . $appenv . '-' . basename($phinx_config);
        $wrap = new \Phinx\Wrapper\TextWrapper($app);
        $wrap->setOption('configuration',$phix_config);
        $wrap->setOption('parser','yaml');
/*
 *
 * / \[{\s*"migration_status"\s*:\s*(.+?)\s*,\s*"migration_id":\s*(.+?)\s*,\s*"migration_name"\s*:\s*(.+?)\s*\}] /g
 *
 * */
        return $wrap;
    }

    protected function getPhinxConfig()
    {
        $env =  $this->config['phinx-module']['phinx-env'];
        $this->config['phinx-module']['phinx-config'];
    }
    /**
     * Writes the Phinx config file with the specified details
     *
     * @param $phinx_config_path
     * @param $adapter
     * @param $migrations
     * @param array $db_config_array
     */
    protected function writePhinxConfig($phinx_config_path, $adapter, $migrations, array $db_config_array)
    {
        $phinx_env = array(
            'default_migration_table' => 'phinxlog',
            'default_database' => 'dev_db_migration',
        );
        foreach ($db_config_array['db']['adapters'] as $key => $value)
        {
            $phinx_env[$key] = array(
                'adapter' => $adapter,
                'host' => $value['hostname'],
                'name' => $value['database'],
                'user' => $value['username'],
                'pass' => $value['password'],
                'port' => $value['port'],
            );
        }
        /**
         * Build phinx config
         */
        $phinx = Array(
            'paths' => Array('migrations' => $migrations),
            'environments' => $phinx_env,
        );

        /**
         * Write YAML
         */
        $yaml = Yaml::dump($phinx);

        file_put_contents($phinx_config_path, $yaml);
    }

    /**
     *
     * @param $appenv
     *
     * @return array
     */
    protected function preparePhinxConfig($appenv)
    {
        $phinxConfig = array();
        switch ($appenv)
        {
            case 'prod':
                if ($this->clientslistdbAdapter === false)
                {
                    $config = new Config(
                        array(
                            'db' => array(
                                'adapters' => array(
                                    'prod-db-clients-lsit' => $this->config['phinx-module']['prod-db-clients-lsit']
                                ),
                            ),
                        )
                    );
                    $writer = new PhpArray();
                    $writer->toFile($this->config['phinx-module']['prod-db-clients-lsit-config'], $config);

                    $this->clientslistdbAdapter = new Adapter($this->config['phinx-module']['prod-db-clients-lsit']);
                }
                break;
        }
        $zf2_config_phinx = $this->config['phinx-module']['zf2-config'];
        $phinx_config = $this->config['phinx-module']['phinx-config'];
        //$migrations = $this->config['phinx-module']['migrations'];

        $phinxConfig['zf2-config'] = dirname($zf2_config_phinx) . DIRECTORY_SEPARATOR . $appenv . '-' . basename($zf2_config_phinx);
        $phinxConfig['phinx-config'] = dirname($phinx_config) . DIRECTORY_SEPARATOR . $appenv . '-' . basename($phinx_config);
        $phinxConfig['migrations'] = $this->config['phinx-module']['migrations']; //dirname($migrations) . DIRECTORY_SEPARATOR . $appenv . '-' . basename($migrations);
        $phinxConfig['error_log'] = $this->config['phinx-module']['migrations_error_log'];
        $phinxConfig['success_log'] = $this->config['phinx-module']['migrations_success_log'];

        if (!is_dir($phinxConfig['error_log']))
        {
            mkdir($phinxConfig['error_log'], 0777, true);
        }
        if (!is_dir($phinxConfig['success_log']))
        {
            mkdir($phinxConfig['success_log'], 0777, true);
        }


        return $phinxConfig;
    }

}
