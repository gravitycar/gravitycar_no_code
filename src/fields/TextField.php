<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * Text field implementation
 *
 * Handles standard text input with length validation and basic text processing.
 */
class TextField extends FieldsBase
{
    protected string $type = 'Text';
    protected string $phpDataType = 'string';
    protected string $databaseType = 'VARCHAR(255)';
    protected string $uiDataType = 'text';

    public function __construct(array $fieldDefinition)
    {
        // Set default max length for text fields
        if (!isset($fieldDefinition['maxLength'])) {
            $fieldDefinition['maxLength'] = 255;
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        return (string) ($this->value ?? '');
    }

    public function setValueFromDB(mixed $value): void
    {
        $this->value = (string) $value;
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }
}
