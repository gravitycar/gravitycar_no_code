<?php
// Installer model metadata for Gravitycar framework
return [
    'name' => 'Installer',
    'nonDb' => true, // This model doesn't need a database table
    'fields' => [
        'host' => [
            'name' => 'host',
            'type' => 'Text',
            'label' => 'Database Host',
            'required' => true,
            'isDBField' => false,
        ],
        'port' => [
            'name' => 'port',
            'type' => 'Integer',
            'label' => 'Database Port',
            'required' => true,
            'defaultValue' => 3306,
            'isDBField' => false,
        ],
        'dbname' => [
            'name' => 'dbname',
            'type' => 'Text',
            'label' => 'Database Name',
            'required' => true,
            'isDBField' => false,
        ],
        'username' => [
            'name' => 'username',
            'type' => 'Text',
            'label' => 'Database Username',
            'required' => true,
            'isDBField' => false,
        ],
        'password' => [
            'name' => 'password',
            'type' => 'Password',
            'label' => 'Database Password',
            'required' => true,
            'isDBField' => false,
        ],
        'admin_username' => [
            'name' => 'admin_username',
            'type' => 'Text',
            'label' => 'Admin Username',
            'required' => true,
            'isDBField' => false,
        ],
        'admin_password' => [
            'name' => 'admin_password',
            'type' => 'Password',
            'label' => 'Admin Password',
            'required' => true,
            'isDBField' => false,
        ],
    ],
    'ui' => [
        'createFields' => ['host', 'port', 'dbname', 'username', 'password', 'admin_username', 'admin_password'],
    ],
];
