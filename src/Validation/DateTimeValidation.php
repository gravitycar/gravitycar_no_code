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
        if (!$this->shouldValidateValue($value)) {
            return true;
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
