<?php
namespace Gravitycar\Exceptions;

use Exception;

/**
 * Exception class for Dependency Injection Container-related errors.
 * Used for container configuration, service resolution, and auto-wiring failures.
 * Overrides logging to prevent circular dependencies during container bootstrap.
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

    /**
     * Override logException to prevent circular dependency during container bootstrap.
     * Uses error_log() directly instead of attempting to get logger from container.
     */
    protected function logException(): void {
        // Use direct error_log to avoid circular dependency with ServiceLocator/Container
        $contextStr = !empty($this->context) ? ' | Context: ' . json_encode($this->context) : '';
        $codeStr = $this->getCode() !== 0 ? " | Code: {$this->getCode()}" : '';
        $traceStr = " | Trace: " . str_replace("\n", " | ", $this->getTraceAsString());
        
        error_log("ContainerException: {$this->getMessage()}{$contextStr}{$codeStr}{$traceStr}");
    }
}
