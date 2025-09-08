<?php
// Movie_Quote_Trivia_Games model metadata for Gravitycar framework
return [
    'name' => 'Movie_Quote_Trivia_Games',
    'table' => 'movie_quote_trivia_games',
    'displayColumns' => ['name', 'score', 'game_completed_at'],
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'Text',
            'label' => 'Game Name',
            'required' => true,
            'maxLength' => 255,
            'readOnly' => true, // Auto-generated: "User Name's game played on Date" or "Guest game Date/Time"
            'validationRules' => ['Required'],
        ],
        'score' => [
            'name' => 'score',
            'type' => 'Integer',
            'label' => 'Final Score',
            'required' => true,
            'minValue' => 0,
            'defaultValue' => 100,
            'readOnly' => true,
            'validationRules' => ['Required'],
        ],
        'game_started_at' => [
            'name' => 'game_started_at',
            'type' => 'DateTime',
            'label' => 'Game Started',
            'required' => true,
            'readOnly' => true,
            'validationRules' => ['Required', 'DateTime'],
        ],
        'game_completed_at' => [
            'name' => 'game_completed_at',
            'type' => 'DateTime',
            'label' => 'Game Completed',
            'required' => false,
            'readOnly' => true,
            'nullable' => true,
            'validationRules' => ['DateTime'],
        ],
    ],
    'validationRules' => [],
    'relationships' => [
        'movie_quote_trivia_games_movie_quote_trivia_questions', // Reference to relationship metadata file
    ],
    'ui' => [
        'listFields' => ['name', 'score', 'created_by_name', 'game_completed_at'],
        'createFields' => [], // Games created via special endpoint
        'editFields' => [], // Games are read-only after creation
    ],
];
