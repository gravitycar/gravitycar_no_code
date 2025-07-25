<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\GCException;

/**
 * Multi-Enum field implementation
 *
 * Handles multiple selections from a predefined list of options.
 */
class MultiEnumField extends FieldsBase
{
    protected string $type = 'MultiEnum';
    protected string $phpDataType = 'array';
    protected string $databaseType = 'JSON';
    protected string $uiDataType = 'multiselect';

    public function __construct(array $fieldDefinition)
    {
        // Validate that allowedValues is provided for multi-enum fields
        if (empty($fieldDefinition['allowedValues'])) {
            throw new GCException("MultiEnumField requires 'allowedValues' to be defined");
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        return is_array($this->value) ? $this->value : [];
    }

    public function setValueFromDB(mixed $value): void
    {
        if (is_string($value)) {
            $this->value = json_decode($value, true) ?? [];
        } else {
            $this->value = is_array($value) ? $value : [];
        }
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }

    public function set(string $fieldName, mixed $value, \Gravitycar\Core\ModelBase $model): void
    {
        // Ensure value is an array
        if (!is_array($value)) {
            $value = $value ? [$value] : [];
        }

        parent::set($fieldName, $value, $model);
    }

    protected function validateValue(mixed $value, \Gravitycar\Core\ModelBase $model): void
    {
        // First run standard validation
        parent::validateValue($value, $model);

        // Then check if all values are in allowed values
        if (is_array($value)) {
            $invalidValues = array_diff($value, $this->allowedValues);
            if (!empty($invalidValues)) {
                $this->validationErrors[] = "Invalid values: " . implode(', ', $invalidValues) .
                    ". Must be one of: " . implode(', ', $this->allowedValues);
                $model->addValidationError($this->name, $this->validationErrors);
            }
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
