<?php
namespace Gravitycar\Validation;

use Monolog\Logger;

/**
 * DateTimeValidation: Ensures a value is a valid date-time string.
 */
class DateTimeValidation extends ValidationRuleBase {
    public function __construct(Logger $logger) {
        parent::__construct($logger, 'DateTime', 'Invalid date-time format.');
    }

    public function validate($value): bool {
        if (empty($value)) {
            return true; // Allow empty values - use Required validation for that
        }
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $dt && $dt->format('Y-m-d H:i:s') === $value;
    }
}
