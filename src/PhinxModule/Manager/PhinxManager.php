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

class PhinxManager implements ColorInterface
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
     * @var Console
     */
    protected $console;

    /**
     * @var DbAdapter | false
     */
    protected $clientslistdbAdapter;

    protected $globalDBAdapter;

    /**
     * Constructor
     *
     * @param  ConsoleAdapter $console
     * @param  array $config
     *
     * @throws RuntimeException
     */
    public function __construct(ConsoleAdapter $console, $config = array(), $clientslistdbAdapter, Adapter $globalDBAdapter)
    {
        $this->config = $config;
        $this->console = $console;
        $this->clientslistdbAdapter = $clientslistdbAdapter;
        $this->globalDBAdapter = $globalDBAdapter;
    }

    /**
     * Interactive database setup.
     * WILL OUTPUT VIA CONSOLE!
     *
     * @param  boolean $overwrite Overwrite existing config
     *
     * @return string
     */
    public function setup($overwrite = false)
    {
        /**
         * Output console usage
         */
        if (!$this->console)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        $phix_config = $this->preparePhinxConfig();
        $appenv = $phix_config['env'];

        if (!$this->is_appEnv($appenv))
        {
            $this->console->writeLine('This "' . $appenv . '" environment not supported ', self::LIGHT_RED);
            return;
        }
        /**
         * Check for existing config
         */
        $zfConfig = $phix_config['zf2-config'];
        $phinxConfig = $phix_config['phinx-config'];
        if (!$overwrite && (file_exists($zfConfig) || file_exists($phinxConfig)))
        {
            if (file_exists($zfConfig) && !file_exists($phinxConfig))
            {
                $this->sync();
                $this->console->writeLine("ZF2 Config found but Phinx config missing => Config Synced.", self::LIGHT_GREEN);
            }
            else
            {
                $this->console->writeLine("Existing config file(s) found, unable to continue!", self::LIGHT_RED);
                $this->console->writeLine("Use the --overwrite flag to replace existing config.", self::LIGHT_RED);
            }

            return;
        }

        /**
         * Ask questions
         */
        if ($appenv !== 'prod')
        {
            $loop = true;
            while ($loop)
            {
                $this->console->writeLine("MySQL Database connection details:");
                $host = Prompt\Line::prompt("Hostname? [localhost] ", true) ?: 'localhost';
                $port = Prompt\Line::prompt("Port? [3306] ", true) ?: 3306;
                $user = Prompt\Line::prompt("Username? ");
                $pass = Prompt\Line::prompt("Password? ");
                $dbname = Prompt\Line::prompt("Database name? ");

                $loop = !Prompt\Confirm::prompt("Save these details? [y/n]");
            }

            /**
             * Build config
             */
            $config = new Config(
                array(
                    'db' => array(
                        'adapters' => array(
                            $appenv . '_db_migration' => array(
                                'charset' => 'utf-8',
                                'database' => $dbname,
                                'driver' => 'PDO_Mysql',
                                'hostname' => $host,
                                'username' => $user,
                                'password' => $pass,
                                'port' => $port,
                            ),
                        ),
                    ),
                )
            );
        }
        else
        {
            $clients_adapters = array();
            $clients_congig_array = $this->getClientsConfig();
            foreach ($clients_congig_array as $value)
            {
                $clients_adapters[$value['instance_name']] = array(
                    'charset' => 'utf-8',
                    'database' => $value['db_name'],
                    'driver' => 'PDO_Mysql',
                    'hostname' => '127.0.0.1',
                    'username' => $value['db_username'],
                    'password' => self::passwordDecrypt($value['db_password']),
                    'port' => 3306,
                );
            }
            $config = new Config(
                array(
                    'db' => array(
                        'adapters' => $clients_adapters
                    )
                )
            );
        }
        $writer = new PhpArray();
        $writer->toFile($zfConfig, $config);
        $this->console->writeLine();
        $this->console->writeLine("ZF2 Config file written: {$zfConfig}", self::LIGHT_GREEN);

        /**
         * Write Phinx config
         */
        $migrations = $phix_config['migrations'];

        $this->writePhinxConfig($phinxConfig, 'mysql', $migrations, $config->toArray());

        $this->console->writeLine("Phinx Config file written: {$phinxConfig}", self::LIGHT_GREEN);
    }

    /**
     * Sync database credentials with phinx.yml config
     *
     * @return string
     * @throws RuntimeException
     */
    public function sync()
    {

        $phix_config = $this->preparePhinxConfig();
        $appenv = $phix_config['env'];
        if (!$this->is_appEnv($appenv))
        {
            $this->console->writeLine('This "' . $appenv . '" environment not supported ', self::LIGHT_RED);
            return;
        }

        /**
         * Check for db config section
         */
        $config_path = getcwd() . '/config/autoload/' . $appenv . '-phinx.global.php';
        if (!file_exists($config_path))
        {
            $this->console->writeLine("Cannot find config file '{$config_path}', unable to sync Phinx config!", self::LIGHT_RED);
            return;
        }

        $config_array = require $config_path;

        /**
         * Load variables
         */
        $migrations = $phix_config['migrations'];
        //$port = isset($this->config['db']['port']) ? $this->config['db']['port'] : 3306;

        /**
         * Write Phinx Config
         */
        $this->writePhinxConfig($phix_config['phinx-config'], 'mysql', $migrations, $config_array);

        $this->console->writeLine("Phinx config file written: {$phix_config['phinx-config']}", self::LIGHT_GREEN);
        return;
    }

    /**
     * @param $server_ip
     */
    public function groupmigrate($server_ip)
    {
        $phix_config = $this->preparePhinxConfig();
        $appenv = $phix_config['env'];

        if ($appenv != 'prod')
        {
            $this->console->writeLine('This "' . $appenv . '" environment not supported groupmigration', self::LIGHT_RED);
            return;
        }
        $IpValidation = new \Zend\Validator\Ip();
        if (!$IpValidation->isValid($server_ip))
        {
            $this->console->writeLine("'{$server_ip}' is incorrect IP format", self::LIGHT_RED);
            return;
        }
        $clients_list = $this->getClientsBySeverIp($server_ip);
        if (empty($clients_list))
        {
            $this->console->writeLine("'{$server_ip}' not found in Servers List", self::LIGHT_RED);
            return;
        }

        $this->setup(true);

        $this->console->writeLine("Migration starts for '{$clients_list[0]['server_name']}' Server", self::LIGHT_CYAN);

        $config_path = getcwd() . '/config/autoload/' . $appenv . '-phinx.global.php';
        if (!file_exists($config_path))
        {
            $this->console->writeLine("Cannot find config file '{$config_path}', unable to sync Phinx config!", self::LIGHT_RED);
            return;
        }
        $this->console->writeLine();
        $clients_count = count($clients_list);

        $is_error = false;

        $error_log = $phix_config['error_log'];
        $success_log = $phix_config['success_log'];

        $i = 0;
        $percent = 0;
        $percent_array = array();
        $current_process_id = exec('echo $PPID;');
        $this->setMigrationState($current_process_id,$error_log);
        while ($i < $clients_count)
        {
            $instance_name = $clients_list[$i]['instance_name'];

            $command_for_exec = 'cd ' . getcwd() . " && php public/index.php phinx migrate -e {$instance_name} 2>&1"; // '> /dev/null 2>/dev/null &' means run in background
            $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
                1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
//                2 => array("file", getcwd() . "/data/migrations/logs/error/{$instance_name}-{$curent_date}.log", "a") // stderr is a file to write to
            );
            $pipes = array();
            $cmd_process = proc_open($command_for_exec, $descriptorspec, $pipes);

            $output = stream_get_contents($pipes[1]);
            $return_value = proc_close($cmd_process);

            if ($return_value != 0)
            {
                $is_error = true;
                $log_path = $error_log . DIRECTORY_SEPARATOR . "/{$instance_name}.log";
                file_put_contents($log_path, '---START---' . PHP_EOL . date("d-M-Y H:i:s") . PHP_EOL, FILE_APPEND | LOCK_EX);
                file_put_contents($log_path, $output . PHP_EOL, FILE_APPEND | LOCK_EX);
                file_put_contents($log_path, ' ---FINISH---' . PHP_EOL . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
            else
            {
                $log_path = $success_log . DIRECTORY_SEPARATOR . "{$instance_name}.log";
                file_put_contents($log_path, '---START---' . PHP_EOL . date("d-M-Y H:i:s") . PHP_EOL, FILE_APPEND | LOCK_EX);
                file_put_contents($log_path, $output . PHP_EOL, FILE_APPEND | LOCK_EX);
                file_put_contents($log_path, ' ---FINISH---' . PHP_EOL . PHP_EOL, FILE_APPEND | LOCK_EX);
            }

            $percent = round(($i + 1) / ($clients_count) * 100, 2);
            $this->console->write("\r" . str_repeat(" ", $this->console->getWidth() - 1) . "\r");
            $this->console->write($percent . '%', self::LIGHT_YELLOW);
            $int_percent = (int)$percent;
            if (($int_percent % 10) == 0 && !in_array($int_percent, $percent_array))
            {
                $percent_array[] = $int_percent;
                $this->console->writeLine($int_percent, self::LIGHT_RED);
                $this->updateMigrationState($current_process_id, $percent);
            }

            $i++;

        }

        if ($is_error === true)
        {
            $this->console->writeLine();
            $this->console->writeLine();
            $this->console->writeLine("Some of migration have an error ", self::LIGHT_RED);
            $this->console->writeLine("Please check log directory '" . getcwd() . "/data/migrations/logs/error/'", self::LIGHT_YELLOW);
        }

        $this->console->writeLine();
        $this->console->writeLine();
        $this->console->writeLine("Migration done for '{$clients_list[0]['server_name']}' ({$server_ip}) Server ", self::LIGHT_MAGENTA);
        $this->console->writeLine();
    }

    /**
     * Command pass-through
     *
     */
    public function command()
    {
        $phix_config = $this->preparePhinxConfig();
        $appenv = $phix_config['env'];
        if (!$this->is_appEnv($appenv))
        {
            $this->console->writeLine('This "' . $appenv . '" environment not supported ', self::LIGHT_RED);
            return;
        }
        $phix_config = $phix_config['phinx-config'];
        /**
         * Update argv's
         */
        $argv = $_SERVER['argv'];
        array_shift($_SERVER['argv']);
        //unset($_SERVER['argv'][1]);
        //$_SERVER['argv'] = array_values($_SERVER['argv']);

        /**
         * Add config param as required
         */
        if (isset($_SERVER['argv'][1]) && !in_array($_SERVER['argv'][1], Array('init', 'list')))
        {
            $_SERVER['argv'][] = "--configuration={$phix_config}";
        }

        /**
         * Run Phinx
         */
//        include __DIR__ . self::PHINX_CMD;
        $app = require_once __DIR__ . self::PHINX_PHP;

        if (isset($this->config['phinx-module']['phinx-name']))
        {
            $app->setName($this->config['phinx-module']['phinx-name']);
        }
        $app->run();

        /**
         * Shift argv's
         */
        $_SERVER['argv'] = $argv;
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
    protected function preparePhinxConfig()
    {
        $phinxConfig = array();
        $appenv = $this->config['phinx-module']['phinx-env'];
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

        $curent_date = date("d-M-Y");
        $phinxConfig['zf2-config'] = dirname($zf2_config_phinx) . DIRECTORY_SEPARATOR . $appenv . '-' . basename($zf2_config_phinx);
        $phinxConfig['phinx-config'] = dirname($phinx_config) . DIRECTORY_SEPARATOR . $appenv . '-' . basename($phinx_config);
        $phinxConfig['migrations'] = $this->config['phinx-module']['migrations']; //dirname($migrations) . DIRECTORY_SEPARATOR . $appenv . '-' . basename($migrations);
        $phinxConfig['error_log'] = $this->config['phinx-module']['migrations_error_log'] . DIRECTORY_SEPARATOR . $curent_date;
        $phinxConfig['success_log'] = $this->config['phinx-module']['migrations_success_log'] . DIRECTORY_SEPARATOR . $curent_date;
        $phinxConfig['env'] = $appenv;

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

    protected function is_appEnv($appenv)
    {
        switch ($appenv)
        {
            case 'prod':
            case 'dev':
            case 'test':
                return true;
                break;
            default:
                return false;
        }
    }

    protected function getClientsConfig()
    {
        $sql = "SELECT instance_name, db_name, db_username, db_password FROM instance_manager_mas ORDER BY server_id";
        $result = $this->clientslistdbAdapter->query($sql, array());
        return $result->toArray();
    }

    protected function getClientsBySeverIp($server_ip)
    {
        $sql = "SELECT serv_mng.`server_ip`, serv_mng.`server_name`,`instance_name`,inst_mng.`db_name`,inst_mng.`db_username`,inst_mng.`db_password` FROM `server_manager_mas` AS serv_mng
                INNER JOIN `instance_manager_mas` AS inst_mng ON (inst_mng.`server_id` = `serv_mng`.`server_id`)
                where `serv_mng`.`server_ip` = ? ORDER BY serv_mng.`server_name`";
        $result = $this->clientslistdbAdapter->query($sql, array($server_ip));
        return $result->toArray();
    }

    protected function setMigrationState($process_id, $error_log_path)
    {
        $sql = "INSERT INTO group_migration_state (process_id,client_list_error)
                VALUES (?,?)
                ";
        $this->globalDBAdapter->createStatement($sql, array($process_id,$error_log_path))->execute();
    }

    protected function updateMigrationState($process_id, $percent)
    {
        $sql = "UPDATE group_migration_state
                SET percent=?
                WHERE process_id=?";
        $this->globalDBAdapter->createStatement($sql, array($percent, $process_id))->execute();
    }
    protected static function passwordDecrypt($str, $passw = null)
    {

        $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $a = str_split('+/=' . $abc);
        $b = strrev('-_=' . $abc);
        if ($passw) {
            $b = self::_mixing_passw($b, $passw);
        } else {
            $r = mb_substr($str, -2);
            $str = mb_substr($str, 0, -2);
            $b = @mb_substr($b, $r) . @mb_substr($b, 0, $r);
        }
        $s = '';
        $b = str_split($b);
        $str = str_split($str);
        $lens = count($str);
        $lenb = count($b);
        for ($i = 0; $i < $lens; $i++) {
            for ($j = 0; $j < $lenb; $j++) {
                if ($str[$i] == $b[$j]) {
                    $s .= $a[$j];
                }
            };
        };
        $s = base64_decode($s);
        if ($passw && substr($s, 0, 16) == substr(md5($passw), 0, 16)) {
            return substr($s, 16);
        } else {
            return $s;
        }
    }
}
