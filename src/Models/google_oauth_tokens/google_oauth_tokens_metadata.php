<?php

return [
    'name' => 'GoogleOauthTokens',
    'table' => 'google_oauth_tokens',
    'fields' => [
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
        'revoked_at' => [
            'name' => 'revoked_at',
            'type' => 'DateTime',
            'label' => 'Revoked At',
            'required' => false,
            'validationRules' => ['DateTime'],
        ],
        // End of model-specific fields
    ],
    'validationRules' => [],
    'relationships' => ['users_google_oauth_tokens'],
    'ui' => [
        'listFields' => ['user_id', 'scope', 'token_expires_at', 'created_at', 'revoked_at'],
        'createFields' => ['user_id', 'access_token_hash', 'refresh_token_hash', 'token_expires_at', 'scope'],
    ],
];
