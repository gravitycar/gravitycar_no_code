<?php

namespace Gravitycar\Api;

use Gravitycar\Exceptions\ParameterValidationException;

/**
 * Parameter Validation Result for collecting validation errors and suggestions
 * 
 * This class is used to accumulate validation errors during parameter processing
 * without instantiating an Exception until errors need to be reported.
 */
class ParameterValidationResult
{
    private array $errors = [];
    private array $suggestions = [];
    
    /**
     * Add a validation error
     * 
     * @param string $field Field name that failed validation
     * @param string $error Error message
     * @param mixed $value The invalid value
     */
    public function addError(string $field, string $error, $value = null): void
    {
        $this->errors[] = [
            'field' => $field,
            'error' => $error,
            'value' => $value,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Add a helpful suggestion
     * 
     * @param string $suggestion Suggestion text
     */
    public function addSuggestion(string $suggestion): void
    {
        $this->suggestions[] = $suggestion;
    }
    
    /**
     * Get all validation errors
     * 
     * @return array Array of validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get all suggestions
     * 
     * @return array Array of suggestions
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }
    
    /**
     * Check if there are any errors
     * 
     * @return bool True if there are validation errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    /**
     * Get error count
     * 
     * @return int Number of validation errors
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }
    
    /**
     * Create a ParameterValidationException from this result
     * 
     * @param string $message Main error message
     * @return ParameterValidationException
     */
    public function createException(string $message = 'Parameter validation failed'): ParameterValidationException
    {
        return new ParameterValidationException($message, $this->errors, $this->suggestions);
    }
    
    /**
     * Throw a ParameterValidationException if there are errors
     * 
     * @param string $message Main error message
     * @throws ParameterValidationException
     */
    public function throwIfHasErrors(string $message = 'Parameter validation failed'): void
    {
        if ($this->hasErrors()) {
            throw $this->createException($message);
        }
    }
}
