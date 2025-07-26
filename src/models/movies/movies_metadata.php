<?php
// Movies model metadata for Gravitycar framework
return [
    'name' => 'Movies',
    'table' => 'movies',
    'fields' => [
        'id' => [
            'name' => 'id',
            'type' => 'ID',
            'label' => 'Movie ID',
            'required' => true,
            'readOnly' => true,
            'unique' => true,
        ],
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
            'nonDb' => true,
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
    'relationships' => [],
    'ui' => [
        'listFields' => ['name', 'poster', 'synopsis'],
        'createFields' => ['name'],
    ],
];

