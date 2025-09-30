<?php
// Books model metadata for Gravitycar framework
return [
    'name' => 'Books',
    'table' => 'books',
    'displayColumns' => ['title', 'authors', 'publication_date'],
    'fields' => [
        'title' => [
            'type' => 'Text',
            'name' => 'title',
            'label' => 'Title',
            'required' => true,
            'maxLength' => 500,
            'validationRules' => ['Required']
        ],
        'subtitle' => [
            'type' => 'Text',
            'name' => 'subtitle',
            'label' => 'Subtitle',
            'nullable' => true,
            'maxLength' => 500
        ],
        'authors' => [
            'type' => 'Text',
            'name' => 'authors',
            'label' => 'Authors',
            'nullable' => true,
            'maxLength' => 1000,
            'description' => 'Comma-separated list of authors (some books may not have individual authors)',
            'validationRules' => []
        ],
        'google_books_id' => [
            'type' => 'Text',
            'name' => 'google_books_id',
            'label' => 'Google Books ID',
            'readOnly' => true,
            'nullable' => true,
            'unique' => true,
            'maxLength' => 50,
            'description' => 'Google Books API volume ID',
            'validationRules' => ['GoogleBooksID_Unique']
        ],
        'isbn_13' => [
            'type' => 'Text',
            'name' => 'isbn_13',
            'label' => 'ISBN-13',
            'nullable' => true,
            'unique' => true,
            'maxLength' => 17,
            'validationRules' => ['ISBN13_Format', 'ISBN_Unique']
        ],
        'isbn_10' => [
            'type' => 'Text',
            'name' => 'isbn_10',
            'label' => 'ISBN-10',
            'nullable' => true,
            'unique' => true,
            'maxLength' => 13,
            'validationRules' => ['ISBN10_Format', 'ISBN_Unique']
        ],
        'synopsis' => [
            'type' => 'BigText',
            'name' => 'synopsis',
            'label' => 'Synopsis',
            'nullable' => true,
            'maxLength' => 5000
        ],
        'cover_image_url' => [
            'type' => 'Image',
            'name' => 'cover_image_url',
            'label' => 'Cover Image',
            'nullable' => true,
            'allowRemote' => true,
            'allowLocal' => false,
            'maxLength' => 1000,
            'altText' => 'Book cover image'
        ],
        'publisher' => [
            'type' => 'Text',
            'name' => 'publisher',
            'label' => 'Publisher',
            'nullable' => true,
            'maxLength' => 200
        ],
        'publication_date' => [
            'name' => 'publication_date',
            'type' => 'Date',
            'label' => 'Publication Date',
            'nullable' => true
        ],
        'page_count' => [
            'name' => 'page_count',
            'type' => 'Integer',
            'label' => 'Page Count',
            'nullable' => true,
            'minValue' => 1,
            'maxValue' => 10000
        ],
        'genres' => [
            'type' => 'Text',
            'name' => 'genres',
            'label' => 'Genres',
            'nullable' => true,
            'maxLength' => 500,
            'description' => 'Comma-separated list of genres'
        ],
        'language' => [
            'type' => 'Text',
            'name' => 'language',
            'label' => 'Language',
            'nullable' => true,
            'maxLength' => 10,
            'defaultValue' => 'en'
        ],
        'average_rating' => [
            'name' => 'average_rating',
            'type' => 'Float',
            'label' => 'Average Rating',
            'nullable' => true,
            'minValue' => 0.0,
            'maxValue' => 5.0,
            'readOnly' => true
        ],
        'ratings_count' => [
            'type' => 'Integer',
            'name' => 'ratings_count',
            'label' => 'Ratings Count',
            'nullable' => true,
            'minValue' => 0,
            'readOnly' => true
        ],
        'maturity_rating' => [
            'type' => 'Enum',
            'name' => 'maturity_rating',
            'label' => 'Maturity Rating',
            'nullable' => true,
            'options' => ['NOT_MATURE', 'MATURE'],
            'readOnly' => true
        ]
    ],
    'validationRules' => [],
    'relationships' => [],
    'ui' => [
        'listFields' => ['cover_image_url', 'title', 'authors', 'publication_date'],
        'createFields' => ['title'],
        'editFields' => ['title', 'subtitle', 'authors', 'publisher', 'publication_date', 'page_count', 'genres', 'language', 'synopsis', 'cover_image_url', 'isbn_13', 'isbn_10'],
        'createButtons' => [
            [
                'name' => 'google_books_search',
                'label' => 'Find Google Books Match',
                'type' => 'google_books_search',
                'variant' => 'secondary',
                'description' => 'Search Google Books to find and select a book match'
            ]
        ],
        'editButtons' => [
            [
                'name' => 'google_books_search',
                'label' => 'Find Google Books Match',
                'type' => 'google_books_search',
                'variant' => 'secondary',
                'showWhen' => [
                    'field' => 'title',
                    'condition' => 'has_value'
                ],
                'description' => 'Search Google Books to find and select a book match'
            ],
            [
                'name' => 'clear_google_books',
                'label' => 'Clear Google Books Data',
                'type' => 'google_books_clear',
                'variant' => 'danger',
                'showWhen' => [
                    'field' => 'google_books_id',
                    'condition' => 'has_value'
                ],
                'description' => 'Remove Google Books association and auto-populated data'
            ]
        ]
    ]
];
