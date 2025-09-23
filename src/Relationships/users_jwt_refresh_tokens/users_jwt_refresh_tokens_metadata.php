<?php

return [
    'name' => 'users_jwt_refresh_tokens',
    'type' => 'OneToMany',
    'modelOne' => 'Users',
    'modelMany' => 'JwtRefreshTokens',
    'constraints' => [],
    'additionalFields' => []
];