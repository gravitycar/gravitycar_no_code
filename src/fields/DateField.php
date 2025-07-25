<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * Date field implementation
 *
 * Handles date values with proper formatting and validation.
 */
class DateField extends FieldsBase
{
    protected string $type = 'Date';
    protected string $phpDataType = 'string';
    protected string $databaseType = 'DATE';
    protected string $uiDataType = 'date';

    public function __construct(array $fieldDefinition)
    {
        // Add date validation by default
        if (!isset($fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'] = [];
        }

        if (!in_array('Date', $fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'][] = 'Date';
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        if ($this->value && $this->value instanceof \DateTime) {
            return $this->value->format('Y-m-d');
        }
        return $this->value;
    }

    public function setValueFromDB(mixed $value): void
    {
        if ($value) {
            $this->value = new \DateTime($value);
        } else {
            $this->value = null;
        }
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }
}
