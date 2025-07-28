<?php
namespace Gravitycar\Validation;

use Monolog\Logger;

/**
 * RequiredValidation: Ensures a value is present and not empty.
 */
class RequiredValidation extends ValidationRuleBase {
    public function __construct(Logger $logger) {
        parent::__construct($logger, 'Required', 'This field is required.');
    }

    public function validate($value): bool {
        return !empty($value) || $value === '0' || $value === 0;
    }
}
