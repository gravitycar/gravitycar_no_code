<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * Big Text field implementation
 *
 * Handles large text content like descriptions, articles, or long form text.
 */
class BigTextField extends FieldsBase
{
    protected string $type = 'BigText';
    protected string $phpDataType = 'string';
    protected string $databaseType = 'TEXT';
    protected string $uiDataType = 'textarea';

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
