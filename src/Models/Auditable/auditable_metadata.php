<?php

return [
    'name' => 'Auditable',
    'table' => 'auditable',
    'fields' => [
        'audit_reason' => [
            'name' => 'audit_reason',
            'type' => 'Text',
            'label' => 'Why did we do this?',
            'required' => true,
            'unique' => false,
            'validationRules' => [
            ],
        ],
    ],
    'relationships' => [],
];