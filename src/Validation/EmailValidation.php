<?php
namespace Gravitycar\Validation;

/**
 * EmailValidation: Ensures a value is a valid email address.
 */
class EmailValidation extends ValidationRuleBase {
    /** @var string Human-readable description */
    protected static string $description = 'Validates that the value is a properly formatted email address';

    public function __construct() {
        parent::__construct('Email', 'Invalid email address.');
    }

    public function validate($value): bool {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        return "
        function validateEmail(value, fieldName) {
            // Skip validation for empty values (let Required rule handle that)
            if (!value || value === '') {
                return { valid: true };
            }
            
            // Use JavaScript email validation regex that closely matches PHP's FILTER_VALIDATE_EMAIL
            const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
            
            if (emailRegex.test(value)) {
                return { valid: true };
            }
            
            return { 
                valid: false, 
                message: 'Invalid email address.' 
            };
        }";
    }
}
