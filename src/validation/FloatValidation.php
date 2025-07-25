<?php

namespace Gravitycar\Validation;

use Gravitycar\Core\ValidationRuleBase;
use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\ModelBase;

/**
 * Float validation rule
 *
 * Validates that a field contains a valid floating point number.
 */
class FloatValidation extends ValidationRuleBase
{
    protected string $name = 'Float';
    protected string $errorMessage = 'This field must be a valid number';

    public function validate(mixed $value, FieldsBase $field, ModelBase $model = null): bool
    {
        if (empty($value) && $value !== 0 && $value !== '0' && $value !== 0.0) {
            return true; // Let Required validation handle empty values
        }

        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }
}
