<?php
namespace Gravitycar\Validation;

/**
 * OptionsValidation: Ensures a value is one of the allowed options.
 */
class OptionsValidation extends ValidationRuleBase {
    protected array $options;

    public function __construct(array $options = []) {
        parent::__construct('Options', 'Value is not in the allowed options.');
        $this->options = $options;
    }

    public function validate($value, $model = null): bool {
        if (empty($this->options)) {
            return true; // If no options defined, allow any value
        }

        // For OptionsValidation, we need to validate ALL values including null and empty string
        // Don't use shouldValidateValue() as it would skip null/empty values

        // Only validate scalar values and null - arrays/objects can't be array keys
        if (!is_scalar($value) && !is_null($value)) {
            return false;
        }

        // Use array_key_exists for proper type-strict checking
        // This will return false for null if null is not explicitly an option key
        return array_key_exists($value, $this->options);
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
