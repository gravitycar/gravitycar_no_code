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
        'daily_rotation' => true,
        'max_files' => 30, // Keep 30 days of logs
        'date_format' => 'Y-m-d', // Daily rotation format
    ],
    'site_name' => 'GravitycarAI',
    'open_imdb_api_key' => '19a9f496',
    'default_page_size' => 20,
];

