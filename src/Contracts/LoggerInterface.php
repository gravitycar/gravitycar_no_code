<?php
namespace Gravitycar\Contracts;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Extended Logger interface for Gravitycar framework.
 * Provides additional methods beyond the PSR-3 standard.
 */
interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * Log a validation error with context
     */
    public function logValidationError(string $message, array $context = []): void;
    
    /**
     * Log a database operation with timing
     */
    public function logDatabaseOperation(string $operation, float $duration, array $context = []): void;
    
    /**
     * Log API request/response cycle
     */
    public function logApiActivity(string $method, string $endpoint, int $responseCode, array $context = []): void;
}
