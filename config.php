<?php
return [
    'database' => [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'gravitycar_nc',
        'user' => 'mike',
        'password' => 'mike',
        'charset' => 'utf8mb4',
    ],
    'installed' => false,
    'app' => [
        'name' => 'Gravitycar Framework',
        'version' => '1.0.0',
        'debug' => true,
    ],
    'logging' => [
        'level' => 'info',
        'file' => 'logs/gravitycar.log',
    ],
    'site_name' => 'GravitycarAI',
    'open_imdb_api_key' => '19a9f496',
    'graviton_list_limit' => 20,
];

