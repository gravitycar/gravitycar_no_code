<?php
// Movies model metadata for Gravitycar framework
return [
    'name' => 'Movies',
    'table' => 'movies',
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'Text',
            'label' => 'Title',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'synopsis' => [
            'name' => 'synopsis',
            'type' => 'BigText',
            'label' => 'Synopsis',
            'readOnly' => true,
        ],
        'poster_url' => [
            'name' => 'poster_url',
            'type' => 'Text',
            'label' => 'Poster URL',
            'readOnly' => true,
        ],
        'poster' => [
            'name' => 'poster',
            'type' => 'Text',
            'label' => 'Poster',
            'isDBField' => false,
        ],
        // End of model-specific fields
    ],
    'validationRules' => [],
    'relationships' => ['movies_movie_quotes'],
    'ui' => [
        'listFields' => ['name'],
        'createFields' => ['name', 'poster', 'synopsis'],
    ],
];

