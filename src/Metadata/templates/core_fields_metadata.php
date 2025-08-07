<?php

/**
 * Core Fields Metadata Template
 *
 * This file defines the standard core fields that all models should have.
 * It returns an array of field metadata definitions that will be automatically
 * included in all models unless overridden.
 *
 * @return array Core fields metadata array
 */

return [
    'id' => [
        'name' => 'id',
        'type' => 'IDField',
        'label' => 'ID',
        'description' => 'Unique identifier for the record',
        'required' => true,
        'readOnly' => true,
        'isDBField' => true,
        'isPrimaryKey' => true,
        'validationRules' => [
            'Required',
        ]
    ],
    'created_at' => [
        'name' => 'created_at',
        'type' => 'DateTimeField',
        'label' => 'Created At',
        'description' => 'When the record was created',
        'required' => false,
        'readOnly' => true,
        'isDBField' => true,
        'validationRules' => [
            'type' => 'datetime'
        ]
    ],
    'updated_at' => [
        'name' => 'updated_at',
        'type' => 'DateTimeField',
        'label' => 'Updated At',
        'description' => 'When the record was last updated',
        'required' => false,
        'readOnly' => true,
        'isDBField' => true,
        'validationRules' => [
            'type' => 'datetime'
        ]
    ],
    'deleted_at' => [
        'name' => 'deleted_at',
        'type' => 'DateTimeField',
        'label' => 'Deleted At',
        'description' => 'When the record was soft deleted (null if not deleted)',
        'required' => false,
        'readOnly' => true,
        'isDBField' => true,
        'nullable' => true,
        'validationRules' => [
            'type' => 'datetime'
        ]
    ],
    'created_by' => [
        'name' => 'created_by',
        'type' => 'RelatedRecordField',
        'label' => 'Created By',
        'description' => 'User who created this record',
        'required' => false,
        'readOnly' => true,
        'isDBField' => true,
        'nullable' => true,
        'relatedModel' => 'Users',
        'relatedFieldName' => 'id',
        'displayFieldName' => 'created_by_name',
        'validationRules' => [
        ],
    ],
    'created_by_name' => [
        'name' => 'created_by_name',
        'type' => 'Text',
        'label' => 'Created By Name',
        'description' => 'Display name of the user who created this record',
        'required' => false,
        'readOnly' => true,
        'isDBField' => false,
        'nullable' => true,
        'validationRules' => [
        ]
    ],
    'updated_by' => [
        'name' => 'updated_by',
        'type' => 'RelatedRecordField',
        'label' => 'Updated By',
        'description' => 'User who last updated this record',
        'required' => false,
        'readOnly' => true,
        'isDBField' => true,
        'nullable' => true,
        'relatedModel' => 'Users',
        'relatedFieldName' => 'id',
        'displayFieldName' => 'updated_by_name',
        'validationRules' => [
        ]
    ],
    'updated_by_name' => [
        'name' => 'updated_by_name',
        'type' => 'Text',
        'label' => 'Updated By Name',
        'description' => 'Display name of the user who last updated this record',
        'required' => false,
        'readOnly' => true,
        'isDBField' => false,
        'nullable' => true,
        'validationRules' => [
        ]
    ],
    'deleted_by' => [
        'name' => 'deleted_by',
        'type' => 'RelatedRecordField',
        'label' => 'Deleted By',
        'description' => 'User who soft deleted this record',
        'required' => false,
        'readOnly' => true,
        'isDBField' => true,
        'nullable' => true,
        'relatedModel' => 'Users',
        'relatedFieldName' => 'id',
        'displayFieldName' => 'deleted_by_name',
        'validation' => [
            'type' => 'integer',
            'nullable' => true,
            'min' => 1
        ]
    ],
    'deleted_by_name' => [
        'name' => 'deleted_by_name',
        'type' => 'Text',
        'label' => 'Deleted By Name',
        'description' => 'Display name of the user who deleted this record',
        'required' => false,
        'readOnly' => true,
        'isDBField' => false,
        'nullable' => true,
        'validationRules' => [
        ]
    ],
];
