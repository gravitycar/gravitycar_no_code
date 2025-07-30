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

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        return "
        function validateRequired(value, fieldName) {
            // Same logic as PHP validate() method
            if (value !== null && value !== undefined && value !== '' && value !== false && value !== []) {
                return { valid: true };
            }
            
            // Handle string '0' and number 0 as valid (same as PHP)
            if (value === '0' || value === 0) {
                return { valid: true };
            }
            
            return { 
                valid: false, 
                message: 'This field is required.' 
            };
        }";
    }
}
