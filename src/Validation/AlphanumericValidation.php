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
        if (!$this->shouldValidateValue($value)) {
            return true;
        }

        // Convert value to string for validation, handling edge cases
        if (is_object($value)) {
            // Check if object has __toString method
            if (method_exists($value, '__toString')) {
                $value = (string) $value;
            } else {
                // Objects without __toString are not alphanumeric
                return false;
            }
        } elseif (is_array($value)) {
            // Arrays are not alphanumeric
            return false;
        } elseif (!is_string($value)) {
            $value = (string) $value;
        }

        return ctype_alnum(str_replace(' ', '', $value));
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        return "
        function validateAlphanumeric(value, fieldName) {
            // Allow empty values - use Required validation for that (same as PHP)
            if (!value || value === '') {
                return { valid: true };
            }
            
            // Remove spaces and check if remaining characters are alphanumeric (same logic as PHP)
            const valueWithoutSpaces = value.replace(/\s/g, '');
            const alphanumericRegex = /^[a-zA-Z0-9]*$/;
            
            if (alphanumericRegex.test(valueWithoutSpaces)) {
                return { valid: true };
            }
            
            return { 
                valid: false, 
                message: 'Value must contain only letters and numbers.' 
            };
        }";
    }
}
