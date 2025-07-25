<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * Boolean field implementation
 *
 * Handles boolean input with true/false validation.
 */
class BooleanField extends FieldsBase
{
    protected string $type = 'Boolean';
    protected string $phpDataType = 'bool';
    protected string $databaseType = 'TINYINT(1)';
    protected string $uiDataType = 'checkbox';

    public function getValueForApi(): mixed
    {
        return $this->value !== null ? (bool) $this->value : false;
    }

    public function setValueFromDB(mixed $value): void
    {
        $this->value = (bool) $value;
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }
}
