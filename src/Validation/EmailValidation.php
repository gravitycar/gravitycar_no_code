<?php
namespace Gravitycar\Validation;

use Monolog\Logger;

/**
 * EmailValidation: Ensures a value is a valid email address.
 */
class EmailValidation extends ValidationRuleBase {
    public function __construct(Logger $logger) {
        parent::__construct($logger, 'Email', 'Invalid email address.');
    }

    public function validate($value): bool {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
