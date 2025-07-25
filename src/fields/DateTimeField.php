<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * DateTime field implementation
 *
 * Handles date and time values with proper formatting and validation.
 */
class DateTimeField extends FieldsBase
{
    protected string $type = 'DateTime';
    protected string $phpDataType = 'string';
    protected string $databaseType = 'DATETIME';
    protected string $uiDataType = 'datetime-local';

    public function __construct(array $fieldDefinition)
    {
        // Set default value to current timestamp if specified
        if (isset($fieldDefinition['defaultValue']) && $fieldDefinition['defaultValue'] === 'NOW()') {
            $fieldDefinition['defaultValue'] = date('Y-m-d H:i:s');
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        if ($this->value && $this->value instanceof \DateTime) {
            return $this->value->format('Y-m-d H:i:s');
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

    public function set(string $fieldName, mixed $value, \Gravitycar\Core\ModelBase $model): void
    {
        // Handle automatic timestamp updates
        if ($this->name === 'updated_at' || $this->name === 'modified_at') {
            $value = new \DateTime();
        }

        // Convert string dates to DateTime objects
        if (is_string($value) && !empty($value)) {
            $value = new \DateTime($value);
        }

        parent::set($fieldName, $value, $model);
    }
}
