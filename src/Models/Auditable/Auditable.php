<?php

namespace Gravitycar\Models\Auditable;

use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Models\ModelBase;
use Monolog\Logger;

/**
 * Example of a model that registers additional core fields
 * This demonstrates the extensibility mechanism for model-specific core fields
 */
class Auditable extends ModelBase
{
    protected string $type = 'auditable';
    /**
     * Register additional core fields specific to auditable models
     * This method should be called during application bootstrap
     */
    public static function registerAdditionalCoreFields(): void
    {
        $additionalCoreFields = [
            'audit_trail' => [
                'name' => 'audit_trail',
                'type' => 'BigTextField',
                'label' => 'Audit Trail',
                'description' => 'JSON log of all changes made to this record',
                'required' => false,
                'readOnly' => true,
                'isDBField' => true,
                'nullable' => true,
                'validation' => [
                    'type' => 'text',
                    'nullable' => true
                ]
            ],
            'version' => [
                'name' => 'version',
                'type' => 'IntegerField',
                'label' => 'Version',
                'description' => 'Version number for optimistic locking',
                'required' => false,
                'readOnly' => true,
                'isDBField' => true,
                'defaultValue' => 1,
                'validation' => [
                    'type' => 'integer',
                    'min' => 1
                ]
            ]
        ];

        $coreFieldsMetadata = \Gravitycar\Core\ServiceLocator::getCoreFieldsMetadata();
        $coreFieldsMetadata->registerModelSpecificCoreFields(static::class, $additionalCoreFields);
    }

    /**
     * Override to automatically register core fields when model is instantiated
     */
    public function __construct()
    {
        // Register additional core fields for this model class
        if (\Gravitycar\Core\ServiceLocator::hasService('core_fields_metadata')) {
            static::registerAdditionalCoreFields();
        }

        parent::__construct();
    }

    /**
     * Example of overriding a standard core field
     * This shows how models can customize core field behavior
     */
    protected function customizeCoreFields(): void
    {
        // Example: Make created_by required for auditable models
        $createdByOverrides = [
            'required' => true,
            'validation' => [
                'type' => 'integer',
                'required' => true,
                'min' => 1
            ]
        ];

        $coreFieldsMetadata = \Gravitycar\Core\ServiceLocator::getCoreFieldsMetadata();
        $customizedField = $coreFieldsMetadata->getCoreFieldWithOverrides(
            'created_by',
            static::class,
            $createdByOverrides
        );

        if ($customizedField) {
            // Apply the customized field metadata
            $this->metadata['fields']['created_by'] = $customizedField;
        }
    }
}
