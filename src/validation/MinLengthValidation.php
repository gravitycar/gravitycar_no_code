<?php

namespace Gravitycar\Validation;

use Gravitycar\Core\ValidationRuleBase;
use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\ModelBase;

/**
 * Minimum length validation rule
 *
 * Validates that a field value meets the minimum length requirement.
 */
class MinLengthValidation extends ValidationRuleBase
{
    protected string $name = 'MinLength';
    protected string $errorMessage = 'This field must be at least {minLength} characters long';

    public function validate(mixed $value, FieldsBase $field, ModelBase $model = null): bool
    {
        if (empty($value)) {
            return true; // Let Required validation handle empty values
        }

        $minLength = $field->getMinLength();
        if ($minLength === null) {
            return true;
        }

        $length = is_string($value) ? strlen($value) : 0;

        if ($length < $minLength) {
            $this->setErrorMessage($this->formatErrorMessage($this->errorMessage, [
                'minLength' => $minLength
            ]));
            return false;
        }

        return true;
    }
}
