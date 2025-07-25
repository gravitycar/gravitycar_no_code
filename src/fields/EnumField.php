<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\GCException;

/**
 * Enum field implementation
 *
 * Handles single selection from a predefined list of options.
 */
class EnumField extends FieldsBase
{
    protected string $type = 'Enum';
    protected string $phpDataType = 'string';
    protected string $databaseType = 'VARCHAR(100)';
    protected string $uiDataType = 'select';

    public function __construct(array $fieldDefinition)
    {
        // Validate that allowedValues is provided for enum fields
        if (empty($fieldDefinition['allowedValues'])) {
            throw new GCException("EnumField requires 'allowedValues' to be defined");
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        return $this->value;
    }

    public function setValueFromDB(mixed $value): void
    {
        $this->value = (string) $value;
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }

    protected function validateValue(mixed $value, \Gravitycar\Core\ModelBase $model): void
    {
        // First run standard validation
        parent::validateValue($value, $model);

        // Then check if value is in allowed values
        if (!empty($value) && !in_array($value, $this->allowedValues)) {
            $this->validationErrors[] = "Value must be one of: " . implode(', ', $this->allowedValues);
            $model->addValidationError($this->name, $this->validationErrors);
        }
    }

    public function getOptions(): array
    {
        // If options class/method is defined, use dynamic options
        if ($this->optionsClass && $this->optionsMethod) {
            if (class_exists($this->optionsClass)) {
                $optionsInstance = new $this->optionsClass();
                if (method_exists($optionsInstance, $this->optionsMethod)) {
                    return $optionsInstance->{$this->optionsMethod}();
                }
            }
        }

        // Otherwise return static allowed values
        return array_combine($this->allowedValues, $this->allowedValues);
    }
}
