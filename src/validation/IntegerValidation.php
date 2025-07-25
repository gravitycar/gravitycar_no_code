<?php

namespace Gravitycar\Validation;

use Gravitycar\Core\ValidationRuleBase;
use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\ModelBase;

/**
 * Integer validation rule
 *
 * Validates that a field contains a valid integer value.
 */
class IntegerValidation extends ValidationRuleBase
{
    protected string $name = 'Integer';
    protected string $errorMessage = 'This field must be a valid integer';

    public function validate(mixed $value, FieldsBase $field, ModelBase $model = null): bool
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return true; // Let Required validation handle empty values
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
}
