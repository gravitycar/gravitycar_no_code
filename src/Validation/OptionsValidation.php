<?php
namespace Gravitycar\Validation;

use Monolog\Logger;

/**
 * OptionsValidation: Ensures a value is one of the allowed options.
 */
class OptionsValidation extends ValidationRuleBase {
    protected array $options;

    public function __construct(Logger $logger, array $options = []) {
        parent::__construct($logger, 'Options', 'Value is not in the allowed options.');
        $this->options = $options;
    }

    public function validate($value): bool {
        if (empty($this->options)) {
            return true; // If no options defined, allow any value
        }
        return in_array($value, array_keys($this->options), true);
    }

    public function setOptions(array $options): void {
        $this->options = $options;
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        return "
        function validateOptions(value, fieldName, options) {
            // If no options defined, allow any value (same as PHP logic)
            if (!options || Object.keys(options).length === 0) {
                return { valid: true };
            }
            
            // Check if value is one of the allowed option keys (strict comparison like PHP)
            const allowedKeys = Object.keys(options);
            if (allowedKeys.includes(String(value))) {
                return { valid: true };
            }
            
            return { 
                valid: false, 
                message: 'Value is not in the allowed options.' 
            };
        }";
    }
}
