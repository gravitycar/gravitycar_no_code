<?php

return [
    'name' => 'Permissions',
    'table' => 'permissions',
    'fields' => [
        'action' => [
            'name' => 'action',
            'type' => 'Text',
            'label' => 'Action/Operation',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'component' => [
            'name' => 'component',
            'type' => 'Text',
            'label' => 'Application Component',
            'required' => false,
            'defaultValue' => '',
            'description' => 'Application component (model name or controller class) this permission applies to',
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
        // End of model-specific fields
    ],
    // NEW: Permissions management is admin-only
    'rolesAndActions' => [
        'admin' => ['*'], // Only admins can manage permissions
        // All other roles inherit default: no access
    ],
    
    'validationRules' => [],
    'relationships' => ['roles_permissions', 'users_permissions'],
    'ui' => [
        'listFields' => ['action', 'component', 'description', 'is_route_permission', 'created_at'],
        'createFields' => ['action', 'component', 'description', 'allowed_roles', 'is_route_permission', 'route_pattern'],
        'editFields' => ['action', 'component', 'description', 'allowed_roles', 'is_route_permission', 'route_pattern'],
    ],
];
