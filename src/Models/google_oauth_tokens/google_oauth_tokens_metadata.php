<?php

return [
    'name' => 'GoogleOauthTokens',
    'table' => 'google_oauth_tokens',
    'fields' => [
        'id' => [
            'name' => 'id',
            'type' => 'ID',
            'label' => 'Token ID',
            'required' => true,
            'readOnly' => true,
            'unique' => true,
        ],
        'user_id' => [
            'name' => 'user_id',
            'type' => 'ID',
            'label' => 'User ID',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'access_token_hash' => [
            'name' => 'access_token_hash',
            'type' => 'Text',
            'label' => 'Access Token Hash',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'refresh_token_hash' => [
            'name' => 'refresh_token_hash',
            'type' => 'Text',
            'label' => 'Refresh Token Hash',
            'required' => false,
            'validationRules' => [],
        ],
        'token_expires_at' => [
            'name' => 'token_expires_at',
            'type' => 'DateTime',
            'label' => 'Token Expires At',
            'required' => true,
            'validationRules' => ['Required', 'DateTime'],
        ],
        'scope' => [
            'name' => 'scope',
            'type' => 'Text',
            'label' => 'OAuth Scope',
            'required' => true,
            'validationRules' => ['Required'],
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
        'revoked_at' => [
            'name' => 'revoked_at',
            'type' => 'DateTime',
            'label' => 'Revoked At',
            'required' => false,
            'validationRules' => ['DateTime'],
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
        'listFields' => ['user_id', 'scope', 'token_expires_at', 'created_at', 'revoked_at'],
        'createFields' => ['user_id', 'access_token_hash', 'refresh_token_hash', 'token_expires_at', 'scope'],
    ],
];
