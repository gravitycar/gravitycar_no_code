<?php

namespace Gravitycar\Exceptions;

use Gravitycar\Exceptions\BadRequestException;

/**
 * Parameter Validation Exception for aggregating multiple validation errors
 * 
 * Used by the Router to collect and return all parameter validation errors
 * in a single comprehensive response.
 */
class ParameterValidationException extends BadRequestException
{
    private array $errors = [];
    private array $suggestions = [];
    
    /**
     * Create a new parameter validation exception
     * 
     * @param string $message Main error message
     * @param array $errors Array of specific validation errors
     * @param array $suggestions Array of helpful suggestions
     */
    public function __construct(string $message = 'Parameter validation failed', array $errors = [], array $suggestions = [])
    {
        $this->errors = $errors;
        $this->suggestions = $suggestions;
        
        parent::__construct($message, [
            'validation_errors' => $errors,
            'suggestions' => $suggestions,
            'error_count' => count($errors)
        ]);
    }
    
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
        
        // Update the context with new errors
        $context = $this->getContext();
        $context['validation_errors'] = $this->errors;
        $context['error_count'] = count($this->errors);
        $this->updateContext($context);
    }
    
    /**
     * Add a helpful suggestion
     * 
     * @param string $suggestion Suggestion text
     */
    public function addSuggestion(string $suggestion): void
    {
        $this->suggestions[] = $suggestion;
        
        // Update the context with new suggestions
        $context = $this->getContext();
        $context['suggestions'] = $this->suggestions;
        $this->updateContext($context);
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
     * Get comprehensive error response for API
     * 
     * @return array Comprehensive error response structure
     */
    public function getApiResponse(): array
    {
        return [
            'success' => false,
            'error' => $this->getMessage(),
            'code' => $this->getCode(),
            'validation_errors' => $this->errors,
            'suggestions' => $this->suggestions,
            'error_count' => count($this->errors),
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Update the exception context (protected method in GCException)
     * 
     * @param array $context New context data
     */
    private function updateContext(array $context): void
    {
        // Access the protected property through reflection since GCException doesn't expose a setter
        $reflection = new \ReflectionClass($this);
        $contextProperty = $reflection->getProperty('context');
        $contextProperty->setAccessible(true);
        $contextProperty->setValue($this, $context);
    }
}
