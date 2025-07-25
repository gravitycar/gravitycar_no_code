<?php

namespace Gravitycar\Validation;

use Gravitycar\Core\ValidationRuleBase;
use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\ModelBase;

/**
 * Email validation rule
 *
 * Validates that a field contains a valid email address format.
 */
class EmailValidation extends ValidationRuleBase
{
    protected string $name = 'Email';
    protected string $errorMessage = 'Please enter a valid email address';

    public function validate(mixed $value, FieldsBase $field, ModelBase $model = null): bool
    {
        if (empty($value)) {
            return true; // Let Required validation handle empty values
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
