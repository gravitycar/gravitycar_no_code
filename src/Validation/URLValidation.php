<?php
namespace Gravitycar\Validation;

/**
 * URLValidation: Ensures a value is a valid URL.
 */
class URLValidation extends ValidationRuleBase {
    public function __construct() {
        parent::__construct('URL', 'Invalid URL format.');
    }

    public function validate($value): bool {
        // Skip validation for empty values (let Required rule handle that)
        if (empty($value)) {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        return "
        function validateURL(value, fieldName) {
            // Skip validation for empty values (let Required rule handle that)
            if (!value || value === '') {
                return { valid: true };
            }
            
            // Use JavaScript URL validation regex
            const urlRegex = /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i;
            
            if (urlRegex.test(value)) {
                return { valid: true };
            }
            
            return { 
                valid: false, 
                message: 'Invalid URL format.' 
            };
        }";
    }
}
