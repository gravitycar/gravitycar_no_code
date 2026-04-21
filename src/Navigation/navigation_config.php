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
            'title' => 'Movie Quote Trivia Game',
            'url' => '/trivia',
            'icon' => '🎬',
            'roles' => ['admin', 'user']
        ],
        [
            'key' => 'dndchat',
            'title' => 'D&D Chat',
            'url' => '/dnd-chat',
            'icon' => '⚔️',
            'roles' => ['admin', 'user']
        ],
        [
            'key' => 'events',
            'title' => 'Events',
            'url' => '/events',
            'icon' => '📅',
            'roles' => ['*'] // All roles can see Events
        ],
        [
            'key' => 'events_create',
            'title' => 'Create Event',
            'url' => '/events?action=create',
            'icon' => '➕',
            'roles' => ['admin'] // Admin only
        ],
        [
            'key' => 'events_list',
            'title' => 'List Events',
            'url' => '/events',
            'icon' => '📋',
            'roles' => ['*'] // All roles
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
