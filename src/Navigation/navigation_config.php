<?php

return [
    // Custom pages that don't correspond to models
    // NOTE: Navigation elements will be displayed in source-code order
    'custom_pages' => [
        [
            'key' => 'dashboard',
            'title' => 'Dashboard',
            'url' => '/dashboard',
            'icon' => '📊',
            'roles' => ['*'] // All roles
        ],
        [
            'key' => 'trivia',
            'title' => 'Movie Trivia',
            'url' => '/trivia',
            'icon' => '🎬',
            'roles' => ['admin', 'user']
        ]
    ],

    // Navigation section configuration
    'navigation_sections' => [
        [
            'key' => 'main',
            'title' => 'Main Navigation'
        ],
        [
            'key' => 'models',
            'title' => 'Data Management'
        ],
        [
            'key' => 'tools',
            'title' => 'Tools & Utilities'
        ]
    ]
];
