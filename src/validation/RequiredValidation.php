<?php

namespace Gravitycar\Validation;

use Gravitycar\Core\ValidationRuleBase;
use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\ModelBase;

/**
 * Required field validation rule
 *
 * Ensures that a field has a non-empty value.
 */
class RequiredValidation extends ValidationRuleBase
{
    protected string $name = 'Required';
    protected string $errorMessage = 'This field is required';

    public function validate(mixed $value, FieldsBase $field, ModelBase $model = null): bool
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return false;
        }

        return true;
    }
}
