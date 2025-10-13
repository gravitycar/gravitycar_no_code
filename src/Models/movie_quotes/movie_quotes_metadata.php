<?php
// Movie_Quotes model metadata for Gravitycar framework
return [
    'name' => 'Movie_Quotes',
    'table' => 'movie_quotes',
    'fields' => [
        'quote' => [
            'name' => 'quote',
            'type' => 'Text',
            'label' => 'Quote',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        // End of model-specific fields
    ],
    'validationRules' => [],
    'relationships' => [
        'movies_movie_quotes',
    ],
    'ui' => [
        'listFields' => ['quote'],
        'createFields' => ['quote'],
        'editFields' => ['quote'],
        // NEW: Relationship-driven UI configuration
        'relationshipFields' => [
            'movie_selection' => [
                'type' => 'RelationshipSelector',
                'relationship' => 'movies_movie_quotes',
                'mode' => 'parent_selection',  // This quote belongs to one movie
                'required' => true,
                'label' => 'Movie',
                'relatedModel' => 'Movies',
                'displayField' => 'name',
                'allowCreate' => true,
                'searchable' => true,
            ],
        ],
    ],
    'rolesAndActions' => [
        'admin' => ['*'],
        'manager' => ['*'],
        'user' => ['*'],
        'guest' => ['list', 'read'],
    ]
];

