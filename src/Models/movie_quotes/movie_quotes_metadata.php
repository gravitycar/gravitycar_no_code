<?php
// Movie_Quotes model metadata for Gravitycar framework
return [
    'name' => 'Movie_Quotes',
    'table' => 'movie_quotes',
    'fields' => [
        'quote' => [
            'name' => 'quote',
            'type' => 'BigText',
            'label' => 'Quote',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'movie_id' => [
            'name' => 'movie_id',
            'type' => 'ID',
            'label' => 'Movie',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'movie' => [
            'name' => 'movie',
            'type' => 'Text',
            'label' => 'Movie Title',
            'nonDb' => true,
            'readOnly' => true,
        ],
        'movie_poster' => [
            'name' => 'movie_poster',
            'type' => 'Text',
            'label' => 'Movie Poster',
            'nonDb' => true,
            'readOnly' => true,
        ],
        // End of model-specific fields
    ],
    'validationRules' => [],
    'relationships' => [
        'movies_movie_quotes',
        ],
    'ui' => [
        'listFields' => ['quote', 'movie', 'movie_poster'],
        'createFields' => ['quote', 'movie_id'],
    ],
];

