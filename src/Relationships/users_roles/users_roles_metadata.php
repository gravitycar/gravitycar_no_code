<?php

return [
    'name' => 'users_roles',
    'type' => 'ManyToMany',
    'modelA' => 'Users',
    'modelB' => 'Roles',
    'constraints' => [],
    'additionalFields' => [
        'assigned_at' => [
            'name' => 'assigned_at',
            'type' => 'DateTime',
            'label' => 'Assigned At',
            'required' => false,
            'defaultValue' => 'CURRENT_TIMESTAMP',
            'validationRules' => ['DateTime'],
        ],
        'assigned_by' => [
            'name' => 'assigned_by',
            'type' => 'ID',
            'label' => 'Assigned By User ID',
            'required' => false,
            'validationRules' => [],
        ],
    ],
];
