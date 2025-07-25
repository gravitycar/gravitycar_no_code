<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * Integer field implementation
 *
 * Handles integer input with numeric validation and range checking.
 */
class IntegerField extends FieldsBase
{
    protected string $type = 'Integer';
    protected string $phpDataType = 'int';
    protected string $databaseType = 'INT';
    protected string $uiDataType = 'number';

    public function __construct(array $fieldDefinition)
    {
        // Add integer validation by default
        if (!isset($fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'] = [];
        }

        if (!in_array('Integer', $fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'][] = 'Integer';
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        return $this->value !== null ? (int) $this->value : null;
    }

    public function setValueFromDB(mixed $value): void
    {
        $this->value = $value !== null ? (int) $value : null;
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }
}
