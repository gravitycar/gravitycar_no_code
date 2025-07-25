<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;

/**
 * ID field implementation
 *
 * Handles primary key fields with auto-increment functionality.
 */
class IDField extends FieldsBase
{
    protected string $type = 'ID';
    protected string $phpDataType = 'int';
    protected string $databaseType = 'INT AUTO_INCREMENT PRIMARY KEY';
    protected string $uiDataType = 'hidden';
    protected bool $isPrimaryKey = true;
    protected bool $readOnly = true;
    protected bool $showInForm = false;

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
