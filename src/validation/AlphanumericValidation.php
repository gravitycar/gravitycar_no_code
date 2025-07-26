<?php
namespace Gravitycar\Validation;

use Monolog\Logger;

/**
 * AlphanumericValidation: Ensures a value contains only alphanumeric characters.
 */
class AlphanumericValidation extends ValidationRuleBase {
    public function __construct(Logger $logger) {
        parent::__construct($logger, 'Alphanumeric', 'Value must contain only letters and numbers.');
    }

    public function validate($value): bool {
        if (empty($value)) {
            return true; // Allow empty values - use Required validation for that
        }
        return ctype_alnum(str_replace(' ', '', $value));
    }
}
