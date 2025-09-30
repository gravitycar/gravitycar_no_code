<?php
// Users model metadata for Gravitycar framework
return [
    'name' => 'Users',
    'table' => 'users',
    'displayColumns' => ['first_name', 'last_name', 'username'],
    'fields' => [
        'username' => [
            'name' => 'username',
            'type' => 'Text',
            'label' => 'Username',
            'required' => true,
            'unique' => true,
            'validationRules' => ['Required', 'Unique'],
        ],
        'password' => [
            'name' => 'password',
            'type' => 'Password',
            'label' => 'Password',
            'required' => false, // Made optional for OAuth users
            'validationRules' => [],
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
        'google_id' => [
            'name' => 'google_id',
            'type' => 'Text',
            'label' => 'Google ID',
            'required' => false,
            'unique' => true,
            'validationRules' => ['Unique'],
        ],
        'auth_provider' => [
            'name' => 'auth_provider',
            'type' => 'Enum',
            'label' => 'Authentication Provider',
            'required' => true,
            'defaultValue' => 'local',
            'options' => [
                'local' => 'Local Authentication',
                'google' => 'Google OAuth',
                'hybrid' => 'Both Local and Google',
            ],
            'validationRules' => ['Required', 'Options'],
        ],
        'last_login_method' => [
            'name' => 'last_login_method',
            'type' => 'Enum',
            'label' => 'Last Login Method',
            'required' => false,
            'options' => [
                'local' => 'Username/Password',
                'google' => 'Google OAuth',
            ],
            'validationRules' => ['Options'],
        ],
        'email_verified_at' => [
            'name' => 'email_verified_at',
            'type' => 'DateTime',
            'label' => 'Email Verified At',
            'required' => false,
            'readOnly' => false,
            'validationRules' => ['DateTime'],
        ],
        'profile_picture_url' => [
            'name' => 'profile_picture_url',
            'type' => 'Image',
            'label' => 'Profile Picture URL',
            'required' => false,
            'width' => 150,
            'height' => 150,
            'validationRules' => ['URL'],
        ],
        'last_google_sync' => [
            'name' => 'last_google_sync',
            'type' => 'DateTime',
            'label' => 'Last Google Profile Sync',
            'required' => false,
            'readOnly' => true,
            'validationRules' => ['DateTime'],
        ],
        'is_active' => [
            'name' => 'is_active',
            'type' => 'Boolean',
            'label' => 'Active Status',
            'required' => true,
            'defaultValue' => true,
            'validationRules' => ['Required'],
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
        // End of model-specific fields
    ],
    
    // NEW: Override default permissions for user management
    'rolesAndActions' => [
        'admin' => ['*'], // Admin keeps full access
        'manager' => ['list', 'read'], // Managers can view users but not modify
        'user' => ['read'], // Users can only view their own data
        'guest' => [] // Guests have no access to user data
    ],
    
    'validationRules' => [],
    'relationships' => ['users_roles', 'users_permissions', 'users_jwt_refresh_tokens', 'users_google_oauth_tokens'],
    'apiRoutes' => [
    ],
    'ui' => [
        'listFields' => ['username', 'first_name', 'last_name', 'user_type', 'is_active', 'last_login'],
        'createFields' => ['username', 'password', 'email', 'first_name', 'last_name', 'user_type', 'user_timezone', 'is_active'],
    ],
];
