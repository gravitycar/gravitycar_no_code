<?php
namespace Gravitycar\Validation;

/**
 * RequiredValidation: Ensures a value is present and not empty.
 */
class RequiredValidation extends ValidationRuleBase {
    /** @var string Human-readable description */
    protected static string $description = 'Ensures a value is present and not empty';

    public function __construct() {
        parent::__construct('Required', 'This field is required.');
    }

    public function validate($value, $model = null): bool {
        // Handle null explicitly
        if ($value === null) {
            return false;
        }

        // Handle empty string
        if ($value === '') {
            return false;
        }

        // Handle empty arrays
        if (is_array($value) && empty($value)) {
            return false;
        }

        // Special cases: string '0', number 0, and boolean false should be valid
        // Boolean false is a legitimate value for boolean fields
        if ($value === '0' || $value === 0 || $value === 0.0 || $value === false) {
            return true;
        }

        // Everything else is valid
        return true;
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        return "
        function validateRequired(value, fieldName) {
            // Handle null and undefined
            if (value === null || value === undefined) {
                return { 
                    valid: false, 
                    message: 'This field is required.' 
                };
            }
            
            // Handle empty string
            if (value === '') {
                return { 
                    valid: false, 
                    message: 'This field is required.' 
                };
            }
            
            // Handle empty arrays
            if (Array.isArray(value) && value.length === 0) {
                return { 
                    valid: false, 
                    message: 'This field is required.' 
                };
            }
            
            // Handle string '0', number 0, and boolean false as valid (same as PHP)
            if (value === '0' || value === 0 || value === false) {
                return { valid: true };
            }
            
            // Everything else is valid
            return { valid: true };
        }";
    }
}
