<?php

return [
    'name' => 'Permissions',
    'table' => 'permissions',
    'fields' => [
        'id' => [
            'name' => 'id',
            'type' => 'ID',
            'label' => 'Permission ID',
            'required' => true,
            'readOnly' => true,
            'unique' => true,
        ],
        'action' => [
            'name' => 'action',
            'type' => 'Text',
            'label' => 'Action/Operation',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'model' => [
            'name' => 'model',
            'type' => 'Text',
            'label' => 'Model Name',
            'required' => false,
            'defaultValue' => '',
            'validationRules' => [],
        ],
        'description' => [
            'name' => 'description',
            'type' => 'BigText',
            'label' => 'Description',
            'required' => false,
            'validationRules' => [],
        ],
        'allowed_roles' => [
            'name' => 'allowed_roles',
            'type' => 'BigText',
            'label' => 'Allowed Roles (JSON)',
            'required' => false,
            'validationRules' => [],
        ],
        'is_route_permission' => [
            'name' => 'is_route_permission',
            'type' => 'Boolean',
            'label' => 'Route Permission',
            'required' => true,
            'defaultValue' => false,
            'validationRules' => ['Required'],
        ],
        'route_pattern' => [
            'name' => 'route_pattern',
            'type' => 'Text',
            'label' => 'Route Pattern',
            'required' => false,
            'validationRules' => [],
        ],
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
    ],
    'validationRules' => [],
    'relationships' => [
        'roles' => [
            'type' => 'ManyToMany',
            'model' => 'Roles',
            'through' => 'role_permissions',
            'foreignKey' => 'permission_id',
            'otherKey' => 'role_id',
        ],
        'users' => [
            'type' => 'ManyToMany',
            'model' => 'Users',
            'through' => 'user_permissions',
            'foreignKey' => 'permission_id',
            'otherKey' => 'user_id',
        ],
    ],
    'ui' => [
        'listFields' => ['action', 'model', 'description', 'is_route_permission', 'created_at'],
        'createFields' => ['action', 'model', 'description', 'allowed_roles', 'is_route_permission', 'route_pattern'],
    ],
];
