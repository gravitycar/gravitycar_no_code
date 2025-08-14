<?php
namespace Gravitycar\Exceptions;

use Exception;

/**
 * Abstract base exception class for API-specific errors.
 * Provides HTTP status code mapping and inherits logging from GCException.
 */
abstract class APIException extends GCException {
    /** @var int HTTP status code for this exception type */
    protected int $httpStatusCode;

    /**
     * Create a new API exception instance
     * 
     * @param string $message Exception message (uses default if empty)
     * @param array $context Additional context for logging and debugging
     * @param Exception|null $previous Previous exception for chaining
     */
    public function __construct(string $message = '', array $context = [], ?Exception $previous = null) {
        // Use default message if none provided
        if (empty($message)) {
            $message = $this->getDefaultMessage();
        }
        
        // Set the HTTP status code as the exception code for consistency
        $code = $this->httpStatusCode;
        
        // Call parent GCException constructor which handles logging
        parent::__construct($message, $context, $code, $previous);
    }

    /**
     * Get the HTTP status code for this exception type
     * 
     * @return int HTTP status code
     */
    public function getHttpStatusCode(): int {
        return $this->httpStatusCode;
    }

    /**
     * Get the default error message for this exception type
     * Must be implemented by concrete exception classes
     * 
     * @return string Default error message
     */
    abstract public function getDefaultMessage(): string;

    /**
     * Get a human-readable error type name for API responses
     * 
     * @return string Error type name
     */
    public function getErrorType(): string {
        // Convert class name to readable format
        // e.g., "NotFoundException" -> "Not Found"
        $className = basename(str_replace('\\', '/', static::class));
        $typeName = preg_replace('/Exception$/', '', $className);
        return preg_replace('/([A-Z])/', ' $1', $typeName);
    }
}
