<?php

return [
    'name' => 'Roles',
    'table' => 'roles',
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'Text',
            'label' => 'Role Name',
            'required' => true,
            'unique' => true,
            'validationRules' => ['Required', 'Unique'],
        ],
        'description' => [
            'name' => 'description',
            'type' => 'BigText',
            'label' => 'Description',
            'required' => false,
            'validationRules' => [],
        ],
        'is_oauth_default' => [
            'name' => 'is_oauth_default',
            'type' => 'Boolean',
            'label' => 'Default OAuth Role',
            'required' => true,
            'defaultValue' => false,
            'validationRules' => ['Required'],
        ],
        'is_system_role' => [
            'name' => 'is_system_role',
            'type' => 'Boolean',
            'label' => 'System Role',
            'required' => true,
            'defaultValue' => false,
            'validationRules' => ['Required'],
        ],
        // End of model-specific fields  
    ],
    'validationRules' => [],
    'relationships' => [
        'permissions' => [
            'type' => 'ManyToMany',
            'model' => 'Permissions',
            'through' => 'role_permissions',
            'foreignKey' => 'role_id',
            'otherKey' => 'permission_id',
        ],
        'users' => [
            'type' => 'ManyToMany',
            'model' => 'Users',
            'through' => 'user_roles',
            'foreignKey' => 'role_id',
            'otherKey' => 'user_id',
        ],
    ],
    'ui' => [
        'listFields' => ['name', 'description', 'is_oauth_default', 'is_system_role', 'created_at'],
        'createFields' => ['name', 'description', 'is_oauth_default', 'is_system_role'],
    ],
];
