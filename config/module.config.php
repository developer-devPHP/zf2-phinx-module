<?php
/**
 * ZF2 Phinx Module
 *
 * @link      https://github.com/valorin/zf2-phinx-module
 * @copyright Copyright (c) 2012-2013 Stephen Rees-Carter <http://stephen.rees-carter.net/>
 * @license   See LICENCE.txt - New BSD License
 */
return array(
    'controllers' => array(
        'invokables' => array(
            'PhinxModule\Controller\Console' => 'PhinxModule\Controller\ConsoleController',
        ),
        'factories' => array(
            'PhinxModule\Controller\Migrate' => 'PhinxModule\Factory\MigrateControllerFactory',
        ),
    ),
    'router' => array(
        'routes' => array(
            'dbmigrate' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/dbmigration',
                    'defaults' => array(
                        'controller' => 'PhinxModule\Controller\Migrate',
                        'action'     => 'status',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'status' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => '/status/:migration_client',
                            'constraints' => array(
                                'migration_client' => '[a-zA-Z0-9_-]+',
                            ),
                            'defaults' => array(
                                'action' => 'status',
                            ),
                        ),
                    ),
                   'groupmigrate' => array(
                        'type' => 'Zend\Mvc\Router\Http\Literal',
                        'options' => array(
                            'route' => '/groupmigrate',
                            'defaults' => array(
                                'action' => 'groupmigrate',
                            ),
                        ),
                    ),
                    'test' => array(
                        'type' => 'Zend\Mvc\Router\Http\Literal',
                        'options' => array(
                            'route' => '/test',
                            'defaults' => array(
                                'action' => 'test',
                            ),
                        ),
                    ),
                 /*   'code' => array(
                        'type' => 'Zend\Mvc\Router\Http\Literal',
                        'options' => array(
                            'route' => '/receivecode',
                            'defaults' => array(
                                'action' => 'receiveCode',
                            ),
                        ),
                    ),*/
                ),
            ),
        )
    ),
    'console' => Array(
        'router' => array(
            'routes' => array(
                'phinx-command' => array(
                    'options' => array(
                        'route' => 'phinx [<c1>] [<c2>] [<c3>] [<c4>] [<c5>] [--help|-h] [--quiet|-q] [--verbose|-v] [--version|-V] [--ansi] [--no-ansi] [--no-interaction|-n] [--configuration|-c] [--xml] [--raw] [--environment|-e] [--target|-t]',
                        'defaults' => array(
                            'controller' => 'PhinxModule\Controller\Console',
                            'action' => 'command'
                        ),
                    ),
                ),
                'phinx_group_migrate' => array(
                    'options' => array(
                        'route' => 'phinx groupmigrate --serverip=',
                        'defaults' => array(
                            'controller' => 'PhinxModule\Controller\Console',
                            'action' => 'groupmigrate',
                        ),
                    ),
                ),
                'phinx-sync' => array(
                    'options' => array(
                        'route' => 'phinx sync',
                        'defaults' => array(
                            'controller' => 'PhinxModule\Controller\Console',
                            'action' => 'sync'
                        ),
                    ),
                ),
                'phinx_setup' => array(
                    'options' => array(
                        'route' => 'phinx setup [--overwrite]',
                        'defaults' => array(
                            'controller' => 'PhinxModule\Controller\Console',
                            'action' => 'setup',
                        ),
                    ),
                ),
                'phinx-init' => array(
                    'options' => array(
                        'route' => 'phinx init',
                        'defaults' => array(
                            'controller' => 'PhinxModule\Controller\Console',
                            'action' => 'init'
                        ),
                    ),
                ),
            ),
        ),
    ),
    'phinx-module' => Array(
        'zf2-config' => getcwd() . '/config/autoload/phinx.global.php',
        'phinx-env' => 'prod',
        'phinx-config' => getcwd() . '/config/phinx.yml',
        'migrations' => getcwd() . '/data/migrations',
        'migrations_error_log' => getcwd() . '/data/migrations/logs/error',
        'migrations_success_log' => getcwd() . '/data/migrations/logs/success',
        'phinx-name'=> 'Phinx core for Microbiz',
        'prod-db-clients-lsit-config' => getcwd() . '/config/autoload/prod_db_clients_lsit_config.global.php',
        'prod-db-clients-lsit' => array(
            'charset' => 'utf8',
            'database' => 'test_clients',
            'driver' => 'PDO_Mysql',
            'hostname' => '127.0.0.1',
            'port' => '3306',
            'username' => 'root',
            'password' => 'root',
        ),
        'permissions'=>array(
            'superadmin'=>'dbmigration:superadmin',
            'getstatus'=>'dbmigration:getstatus',
        )
    ),
);
