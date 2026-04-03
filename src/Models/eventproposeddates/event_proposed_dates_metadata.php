<?php
// EventProposedDates model metadata for Gravitycar framework
return [
    'name' => 'EventProposedDates',
    'table' => 'event_proposed_dates',
    'displayColumns' => ['proposed_date'],
    'fields' => [
        'event_id' => [
            'name' => 'event_id',
            'type' => 'RelatedRecord',
            'label' => 'Event',
            'required' => true,
            'relatedModel' => 'Events',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'event_display',
            'description' => 'The parent event this proposed date belongs to',
            'validationRules' => ['Required'],
        ],
        'event_display' => [
            'name' => 'event_display',
            'type' => 'Text',
            'label' => 'Event Name',
            'readOnly' => true,
            'isDBField' => false,
            'description' => 'Display name of the parent event',
            'validationRules' => [],
        ],
        'proposed_date' => [
            'name' => 'proposed_date',
            'type' => 'DateTime',
            'label' => 'Proposed Date',
            'required' => true,
            'description' => 'The candidate date and time for this event',
            'validationRules' => ['Required', 'DateTime'],
        ],
    ],
    'rolesAndActions' => [
        'admin' => ['*'],
        'user' => ['list', 'read'],
        'guest' => ['list', 'read'],
    ],
    'validationRules' => [],
    'relationships' => [],
    'apiRoutes' => [],
    'ui' => [
        'listFields' => ['event_display', 'proposed_date'],
        'createFields' => ['event_id', 'proposed_date'],
        'editFields' => ['event_id', 'proposed_date'],
    ],
];
