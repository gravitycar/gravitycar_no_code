<?php

return [
    'name' => 'users_permissions',
    'type' => 'ManyToMany',
    'modelA' => 'Users',
    'modelB' => 'Permissions',
    'constraints' => [],
    'additionalFields' => [
        'granted_at' => [
            'name' => 'granted_at',
            'type' => 'DateTime',
            'label' => 'Granted At',
            'required' => false,
            'defaultValue' => 'CURRENT_TIMESTAMP',
            'validationRules' => ['DateTime'],
        ],
        'granted_by' => [
            'name' => 'granted_by',
            'type' => 'ID',
            'label' => 'Granted By User ID',
            'required' => false,
            'validationRules' => [],
        ],
        'expires_at' => [
            'name' => 'expires_at',
            'type' => 'DateTime',
            'label' => 'Expires At',
            'required' => false,
            'validationRules' => ['DateTime'],
        ],
    ],
];
