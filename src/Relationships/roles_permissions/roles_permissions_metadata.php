<?php

return [
    'name' => 'roles_permissions',
    'type' => 'ManyToMany',
    'modelA' => 'Roles',
    'modelB' => 'Permissions',
    'constraints' => [],
    'additionalFields' => [
        'granted_at' => [
            'name' => 'granted_at',
            'type' => 'DateTime',
            'label' => 'Granted At',
            'required' => false,
            'validationRules' => ['DateTime'],
        ],
        'granted_by' => [
            'name' => 'granted_by',
            'type' => 'ID',
            'label' => 'Granted By User ID',
            'required' => false,
            'validationRules' => [],
        ],
    ],
];
