<?php
include_once "config.php";
return
    [
        'paths' => [
            'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
            'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
        ],
        'environments' => [
            'default_migration_table' => 'phinxlog',
            'default_environment' => 'development',
            'development' => [
                'adapter' => 'mysql',
                'host' => env('db_host'),
                'name' => env('db_database'),
                'user' => env('db_user'),
                'pass' => env('db_password'),
                'port' => env('db_port'),
                'charset' => 'utf8',
            ]
        ],
        'version_order' => 'creation'
    ];
