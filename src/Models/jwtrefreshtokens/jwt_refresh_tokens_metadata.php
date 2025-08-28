<?php

return [
    'name' => 'JwtRefreshTokens',
    'table' => 'jwt_refresh_tokens',
    'fields' => [
        'user_id' => [
            'name' => 'user_id',
            'type' => 'ID',
            'label' => 'User ID',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'token_hash' => [
            'name' => 'token_hash',
            'type' => 'Text',
            'label' => 'Token Hash',
            'required' => true,
            'unique' => true,
            'validationRules' => ['Required', 'Unique'],
        ],
        'expires_at' => [
            'name' => 'expires_at',
            'type' => 'DateTime',
            'label' => 'Expires At',
            'required' => true,
            'validationRules' => ['Required', 'DateTime'],
        ],
        'is_revoked' => [
            'name' => 'is_revoked',
            'type' => 'Boolean',
            'label' => 'Is Revoked',
            'required' => true,
            'defaultValue' => false,
            'validationRules' => ['Required'],
        ],
        'revoked_at' => [
            'name' => 'revoked_at',
            'type' => 'DateTime',
            'label' => 'Revoked At',
            'required' => false,
            'validationRules' => ['DateTime'],
        ],
        'device_info' => [
            'name' => 'device_info',
            'type' => 'Text',
            'label' => 'Device Information',
            'required' => false,
            'validationRules' => [],
        ],
    ],
    'validationRules' => [],
    'relationships' => [
        'user' => [
            'type' => 'BelongsTo',
            'model' => 'Users',
            'foreignKey' => 'user_id',
            'localKey' => 'id',
        ],
    ],
    'ui' => [
        'listFields' => ['user_id', 'expires_at', 'created_at', 'revoked_at'],
        'createFields' => ['user_id', 'token_hash', 'expires_at', 'device_info'],
    ],
];
