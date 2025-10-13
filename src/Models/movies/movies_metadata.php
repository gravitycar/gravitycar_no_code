<?php
// Movies model metadata for Gravitycar framework
return [
    'name' => 'Movies',
    'table' => 'movies',
    'displayColumns' => ['name', 'release_year'],
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'Text',
            'label' => 'Title',
            'required' => true,
            'validationRules' => ['Required'],
            // Will be set to readOnly dynamically after save
        ],
        'tmdb_id' => [
            'name' => 'tmdb_id',
            'type' => 'Integer',
            'label' => 'TMDB ID',
            'readOnly' => true,
            'nullable' => true,
            'unique' => true,
            'description' => 'The Movie Database ID for external data linking',
            'validationRules' => ['TMDBID_Unique'],
        ],
        'synopsis' => [
            'name' => 'synopsis',
            'type' => 'BigText',
            'label' => 'Synopsis',
            'maxLength' => 5000,
            'readOnly' => false, // Allow manual entry if no TMDB match
        ],
        'poster_url' => [
            'name' => 'poster_url',
            'type' => 'Image',
            'label' => 'Movie Poster',
            'width' => 300,
            'height' => 450,
            'maxLength' => 1000,
            'allowRemote' => true,
            'allowLocal' => false,
            'altText' => 'Movie poster image',
        ],
        'trailer_url' => [
            'name' => 'trailer_url',
            'type' => 'Video',
            'label' => 'Movie Trailer',
            'width' => 560,
            'height' => 315,
            'showControls' => true,
            'nullable' => true,
            'maxLength' => 500,
            'validationRules' => ['VideoURL'],
        ],
        'obscurity_score' => [
            'name' => 'obscurity_score',
            'type' => 'Integer',
            'label' => 'Obscurity Score',
            'minValue' => 1,
            'maxValue' => 5,
            'readOnly' => true,
            'nullable' => true,
            'description' => 'Film obscurity: 1=Very Popular, 5=Very Obscure',
        ],
        'release_year' => [
            'name' => 'release_year',
            'type' => 'Integer',
            'label' => 'Release Year',
            'minValue' => 1800,
            'maxValue' => 2100,
            'readOnly' => true,
            'nullable' => true,
        ],
        // Legacy field for backwards compatibility
        'poster' => [
            'name' => 'poster',
            'type' => 'Text',
            'label' => 'Poster (Legacy)',
            'isDBField' => false,
        ],
        // End of model-specific fields
    ],
    
    // NEW: Allow broader access to movie content
    'rolesAndActions' => [
        'admin' => ['*'], // Admin keeps full access
        'manager' => ['*'], // Managers can fully manage movies
        'user' => ['*'], // Users can browse and view movies
        'guest' => ['list', 'read'], // Guests can browse and view movies
    ],
    
    'validationRules' => [],
    'relationships' => ['movies_movie_quotes'],
    'ui' => [
        'listFields' => ['poster_url', 'name', 'release_year', 'obscurity_score'],
        'createFields' => ['name'], // Only title during creation, rest populated via TMDB
        'editFields' => ['name', 'release_year', 'obscurity_score', 'synopsis', 'poster_url', 'trailer_url'],
        'relatedItemsSections' => [
            'quotes' => [
                'title' => 'Movie Quotes',
                'relationship' => 'movies_movie_quotes',
                'mode' => 'children_management',
                'relatedModel' => 'Movie_Quotes',
                'displayColumns' => ['quote'],
                'actions' => ['create', 'edit', 'delete'],
                'allowInlineCreate' => true,
                'allowInlineEdit' => true,
                'createFields' => ['quote'],
                'editFields' => ['quote'],
            ]
        ],
        'createButtons' => [
            [
                'name' => 'tmdb_search',
                'label' => 'Search YOUR TMDB',
                'type' => 'tmdb_search',
                'variant' => 'secondary',
                'description' => 'Search TMDB to find and select a movie match'
            ]
        ],
        'editButtons' => [
            [
                'name' => 'tmdb_search',
                'label' => 'Choose TMDB Match',
                'type' => 'tmdb_search',
                'variant' => 'secondary',
                'showWhen' => [
                    'field' => 'name',
                    'condition' => 'has_value'
                ],
                'description' => 'Search TMDB to find and select a different movie match'
            ],
            [
                'name' => 'clear_tmdb',
                'label' => 'Clear TMDB Data',
                'type' => 'tmdb_clear',
                'variant' => 'danger',
                'showWhen' => [
                    'field' => 'tmdb_id',
                    'condition' => 'has_value'
                ],
                'description' => 'Remove TMDB association and auto-populated data'
            ]
        ],
    ],
];

