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
}
