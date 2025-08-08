<?php
// MovieQuotes model metadata for Gravitycar framework
return [
    'name' => 'MovieQuotes',
    'table' => 'movie_quotes',
    'fields' => [
        'id' => [
            'name' => 'id',
            'type' => 'ID',
            'label' => 'Quote ID',
            'required' => true,
            'readOnly' => true,
            'unique' => true,
        ],
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
        // Core fields
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
        'deleted_at' => [
            'name' => 'deleted_at',
            'type' => 'DateTime',
            'label' => 'Deleted At',
            'readOnly' => true,
        ],
        'created_by' => [
            'name' => 'created_by',
            'type' => 'ID',
            'label' => 'Created By',
            'readOnly' => true,
        ],
        'updated_by' => [
            'name' => 'updated_by',
            'type' => 'ID',
            'label' => 'Updated By',
            'readOnly' => true,
        ],
        'deleted_by' => [
            'name' => 'deleted_by',
            'type' => 'ID',
            'label' => 'Deleted By',
            'readOnly' => true,
        ],
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

