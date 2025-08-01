<?php
// Users model metadata for Gravitycar framework
return [
    'name' => 'Users',
    'table' => 'users',
    'fields' => [
        'id' => [
            'name' => 'id',
            'type' => 'ID',
            'label' => 'User ID',
            'required' => true,
            'readOnly' => true,
            'unique' => true,
        ],
        'username' => [
            'name' => 'username',
            'type' => 'Email',
            'label' => 'Username',
            'required' => true,
            'unique' => true,
            'validationRules' => ['Email', 'Required', 'Unique'],
        ],
        'password' => [
            'name' => 'password',
            'type' => 'Password',
            'label' => 'Password',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'email' => [
            'name' => 'email',
            'type' => 'Email',
            'label' => 'Email',
            'required' => true,
            'unique' => true,
            'validationRules' => ['Email', 'Required', 'Unique'],
        ],
        'first_name' => [
            'name' => 'first_name',
            'type' => 'Text',
            'label' => 'First Name',
            'required' => false,
            'validationRules' => ['Alphanumeric'],
        ],
        'last_name' => [
            'name' => 'last_name',
            'type' => 'Text',
            'label' => 'Last Name',
            'required' => true,
            'validationRules' => ['Alphanumeric', 'Required'],
        ],
        'last_login' => [
            'name' => 'last_login',
            'type' => 'DateTime',
            'label' => 'Last Login',
            'required' => false,
            'readOnly' => true,
            'validationRules' => ['DateTime'],
        ],
        'user_type' => [
            'name' => 'user_type',
            'type' => 'Enum',
            'label' => 'User Type',
            'required' => true,
            'defaultValue' => 'user',
            'options' => [
                'admin' => 'Admin',
                'manager' => 'Manager',
                'user' => 'User',
            ],
            'validationRules' => ['Required', 'Options'],
        ],
        'user_timezone' => [
            'name' => 'user_timezone',
            'type' => 'Enum',
            'label' => 'Timezone',
            'required' => true,
            'defaultValue' => 'UTC',
            'optionsClass' => '\Gravitycar\Utils\Timezone',
            'optionsMethod' => 'getTimezones',
            'validationRules' => ['Required', 'Options'],
        ],
        // Core fields
        'created_at' => [
            'name' => 'created_at',
            'type' => 'DateTime',
            'label' => 'Created At',
            'readOnly' => true,
        ],
        'updated_at' => [
            'name' => 'updated_at',
            'type' => 'DateTime',
            'label' => 'Updated At',
            'readOnly' => true,
        ],
        'deleted_at' => [
            'name' => 'deleted_at',
            'type' => 'DateTime',
            'label' => 'Deleted At',
            'readOnly' => true,
        ],
        'created_by' => [
            'name' => 'created_by',
            'type' => 'ID',
            'label' => 'Created By',
            'readOnly' => true,
        ],
        'updated_by' => [
            'name' => 'updated_by',
            'type' => 'ID',
            'label' => 'Updated By',
            'readOnly' => true,
        ],
        'deleted_by' => [
            'name' => 'deleted_by',
            'type' => 'ID',
            'label' => 'Deleted By',
            'readOnly' => true,
        ],
    ],
    'validationRules' => [],
    'relationships' => [],
    'ui' => [
        'listFields' => ['username', 'email', 'user_type', 'last_login'],
        'createFields' => ['username', 'password', 'email', 'first_name', 'last_name', 'user_type', 'user_timezone'],
    ],
];
