<?php

return [
    'name' => 'users_google_oauth_tokens',
    'type' => 'OneToMany',
    'modelOne' => 'Users',
    'modelMany' => 'GoogleOauthTokens',
    'constraints' => [],
    'additionalFields' => []
];