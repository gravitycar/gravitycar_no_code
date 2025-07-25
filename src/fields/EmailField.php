<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * Email field implementation
 *
 * Handles email input with built-in email format validation.
 */
class EmailField extends FieldsBase
{
    protected string $type = 'Email';
    protected string $phpDataType = 'string';
    protected string $databaseType = 'VARCHAR(255)';
    protected string $uiDataType = 'email';

    public function __construct(array $fieldDefinition)
    {
        // Add email validation by default
        if (!isset($fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'] = [];
        }

        if (!in_array('Email', $fieldDefinition['validationRules'])) {
            $fieldDefinition['validationRules'][] = 'Email';
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        return strtolower(trim($this->value ?? ''));
    }
}
