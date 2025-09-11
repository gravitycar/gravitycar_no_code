<?php
namespace Gravitycar\Exceptions;

use Exception;

/**
 * Exception class for Dependency Injection Container-related errors.
 * Used for container configuration, service resolution, and auto-wiring failures.
 */
class ContainerException extends GCException {
    
    /**
     * Constructor for container-related exceptions
     * 
     * @param string $message The exception message
     * @param array $context Additional context data for the exception
     * @param int $code The exception code (default: 0)
     * @param Exception|null $previous The previous exception (default: null)
     */
    public function __construct(string $message, array $context = [], int $code = 0, Exception $previous = null) {
        parent::__construct($message, $context, $code, $previous);
    }
}
