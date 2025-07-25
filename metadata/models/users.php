<?php

return [
    'table_name' => 'users',
    'primary_key' => 'id',
    'fields' => [
        'id' => [
            'type' => 'ID',
            'label' => 'User ID',
            'required' => true,
            'isPrimaryKey' => true,
            'showInForm' => false
        ],
        'username' => [
            'type' => 'Text',
            'label' => 'Username',
            'required' => true,
            'unique' => true,
            'maxLength' => 50,
            'minLength' => 3,
            'validationRules' => ['Required', 'MinLength'],
            'placeholder' => 'Enter username'
        ],
        'email' => [
            'type' => 'Email',
            'label' => 'Email Address',
            'required' => true,
            'unique' => true,
            'maxLength' => 255,
            'validationRules' => ['Required', 'Email'],
            'placeholder' => 'Enter email address'
        ],
        'password' => [
            'type' => 'Password',
            'label' => 'Password',
            'required' => true,
            'minLength' => 8,
            'validationRules' => ['Required', 'MinLength'],
            'placeholder' => 'Enter password'
        ],
        'first_name' => [
            'type' => 'Text',
            'label' => 'First Name',
            'required' => true,
            'maxLength' => 100,
            'validationRules' => ['Required'],
            'placeholder' => 'Enter first name'
        ],
        'last_name' => [
            'type' => 'Text',
            'label' => 'Last Name',
            'required' => true,
            'maxLength' => 100,
            'validationRules' => ['Required'],
            'placeholder' => 'Enter last name'
        ],
        'is_active' => [
            'type' => 'Boolean',
            'label' => 'Active',
            'defaultValue' => true,
            'showInList' => true
        ],
        'created_at' => [
            'type' => 'DateTime',
            'label' => 'Created At',
            'readOnly' => true,
            'showInForm' => false,
            'defaultValue' => 'NOW()'
        ],
        'updated_at' => [
            'type' => 'DateTime',
            'label' => 'Updated At',
            'readOnly' => true,
            'showInForm' => false
        ]
    ]
];
