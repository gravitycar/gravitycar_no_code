<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * Float field implementation
 *
 * Handles floating point numbers with decimal validation.
 */
class FloatField extends FieldsBase
{
    protected string $type = 'Float';
    protected string $phpDataType = 'float';
    protected string $databaseType = 'DECIMAL(10,2)';
    protected string $uiDataType = 'number';

    public function __construct(array $fieldDefinition)
    {
        // Add float validation by default
        if (!isset($fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'] = [];
        }

        if (!in_array('Float', $fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'][] = 'Float';
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        return $this->value !== null ? (float) $this->value : null;
    }

    public function setValueFromDB(mixed $value): void
    {
        $this->value = $value !== null ? (float) $value : null;
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }
}
