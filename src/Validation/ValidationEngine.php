<?php

namespace Gravitycar\Validation;

use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * Simple validation engine for coordinating multiple validation rules.
 * Used primarily for testing integration scenarios.
 */
class ValidationEngine
{
    private Logger $logger;
    private array $rules = [];

    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
    }

    /**
     * Add a validation rule for a specific field.
     */
    public function addRule(string $field, ValidationRuleBase $rule): void
    {
        if (!isset($this->rules[$field])) {
            $this->rules[$field] = [];
        }
        $this->rules[$field][] = $rule;
    }

    /**
     * Validate data against all registered rules.
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];
        $isValid = true;

        foreach ($this->rules as $field => $fieldRules) {
            $fieldValue = $data[$field] ?? null;
            $fieldErrors = [];

            foreach ($fieldRules as $rule) {
                if (!$rule->validate($fieldValue)) {
                    $fieldErrors[] = $rule->getErrorMessage();
                    $isValid = false;
                }
            }

            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
            }
        }

        return new ValidationResult($isValid, $errors);
    }
}

/**
 * Simple validation result container.
 */
class ValidationResult
{
    private bool $isValid;
    private array $errors;

    public function __construct(bool $isValid, array $errors = [])
    {
        $this->isValid = $isValid;
        $this->errors = $errors;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
