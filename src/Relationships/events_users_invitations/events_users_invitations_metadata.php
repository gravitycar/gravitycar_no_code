<?php
// EventInvitations: ManyToMany relationship between Events and Users
// Tracks which users are invited to which events, with invitation metadata.
return [
    'name' => 'events_users_invitations',
    'type' => 'ManyToMany',
    'modelA' => 'Events',
    'modelB' => 'Users',
    'constraints' => [],
    'additionalFields' => [
        'invited_at' => [
            'name' => 'invited_at',
            'type' => 'DateTime',
            'label' => 'Invited At',
            'required' => false,
            'validationRules' => [],
        ],
        'invited_by' => [
            'name' => 'invited_by',
            'type' => 'ID',
            'label' => 'Invited By User ID',
            'required' => false,
            'validationRules' => [],
        ],
    ],
];
