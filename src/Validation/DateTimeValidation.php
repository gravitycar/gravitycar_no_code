<?php
namespace Gravitycar\Validation;

/**
 * DateTimeValidation: Ensures a value is a valid date-time string.
 */
class DateTimeValidation extends ValidationRuleBase {
    public function __construct() {
        parent::__construct('DateTime', 'Invalid date-time format.');
    }

    /**
     * Override shouldValidateValue to allow null but not empty strings
     * Null represents "no date provided" which is valid
     * Empty string represents "invalid date input" which should be validated and fail
     */
    protected function shouldValidateValue($value): bool {
        // Skip validation if value is empty and skipIfEmpty is true
        if (empty($value) && $this->skipIfEmpty) {
            return false;
        }

        // Allow null to skip validation (null = no date provided)
        if ($value === null) {
            return false;
        }

        // Always validate empty strings and all other values
        return true;
    }

    public function validate($value, $model = null): bool {
        if (!$this->shouldValidateValue($value)) {
            return true;
        }

        // Ensure value is a string before attempting DateTime parsing
        if (!is_string($value)) {
            return false;
        }

        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $dt && $dt->format('Y-m-d H:i:s') === $value;
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        return "
        function validateDateTime(value, fieldName) {
            // Allow empty values - use Required validation for that (same as PHP)
            if (!value || value === '') {
                return { valid: true };
            }
            
            // Check if value matches Y-m-d H:i:s format (same logic as PHP)
            const dateTimeRegex = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/;
            if (!dateTimeRegex.test(value)) {
                return { 
                    valid: false, 
                    message: 'Invalid date-time format.' 
                };
            }
            
            // Parse the date and validate it's a real date
            const parts = value.split(' ');
            const dateParts = parts[0].split('-');
            const timeParts = parts[1].split(':');
            
            const year = parseInt(dateParts[0]);
            const month = parseInt(dateParts[1]) - 1; // JavaScript months are 0-based
            const day = parseInt(dateParts[2]);
            const hour = parseInt(timeParts[0]);
            const minute = parseInt(timeParts[1]);
            const second = parseInt(timeParts[2]);
            
            const date = new Date(year, month, day, hour, minute, second);
            
            // Check if the date is valid and matches the input values
            if (date.getFullYear() !== year || 
                date.getMonth() !== month || 
                date.getDate() !== day ||
                date.getHours() !== hour ||
                date.getMinutes() !== minute ||
                date.getSeconds() !== second) {
                return { 
                    valid: false, 
                    message: 'Invalid date-time format.' 
                };
            }
            
            return { valid: true };
        }";
    }
}
