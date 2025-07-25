<?php

namespace Gravitycar\Validation;

use Gravitycar\Core\ValidationRuleBase;
use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\ModelBase;

/**
 * Date validation rule
 *
 * Validates that a field contains a valid date format.
 */
class DateValidation extends ValidationRuleBase
{
    protected string $name = 'Date';
    protected string $errorMessage = 'This field must be a valid date';

    public function validate(mixed $value, FieldsBase $field, ModelBase $model = null): bool
    {
        if (empty($value)) {
            return true; // Let Required validation handle empty values
        }

        if ($value instanceof \DateTime) {
            return true;
        }

        // Try to parse the date string
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return false;
        }

        // Verify it's a valid date
        $dateArray = getdate($timestamp);
        return checkdate($dateArray['mon'], $dateArray['mday'], $dateArray['year']);
    }
}
