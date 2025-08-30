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
        'movie_id' => [
            'name' => 'movie_id',
            'type' => 'RelatedRecord',
            'label' => 'Movie',
            'required' => true,
            'validationRules' => ['Required'],
            'relatedModel' => 'Movies',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'movie_name',
            'searchable' => true,
        ],
        'movie_name' => [
          'name' => 'movie_name',
          'type' => 'Text',
          'label' => 'Movie Name',
          'description' => 'Name of the movie associated with this quote',
          'required' => false,
          'readOnly' => true,
          'isDBField' => false,
          'nullable' => true,
          'validationRules' => 
          array (
          ),
        ],
        // End of model-specific fields
    ],
    'validationRules' => [],
    'relationships' => [
        'movies_movie_quotes',
        ],
    'ui' => [
        'listFields' => ['quote', 'movie_name'],
        'createFields' => ['quote', 'movie_id'],
    ],
];

