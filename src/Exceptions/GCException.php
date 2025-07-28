<?php
namespace Gravitycar\Exceptions;

use Exception;
use Gravitycar\Core\ServiceLocator;

/**
 * Base exception class for Gravitycar framework.
 * Provides automatic logging and context for all framework exceptions.
 * Uses ServiceLocator to automatically obtain logger instance.
 */
class GCException extends Exception {
    /** @var array */
    protected array $context;

    public function __construct(string $message, array $context = [], int $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->logException();
    }

    /**
     * Log the exception using Monolog via ServiceLocator
     */
    protected function logException(): void {
        try {
            $logger = ServiceLocator::getLogger();
            $logger->error($this->getMessage(), [
                'exception' => static::class,
                'code' => $this->getCode(),
                'context' => $this->context,
                'trace' => $this->getTraceAsString(),
            ]);
        } catch (Exception $e) {
            // Fallback: if we can't get logger, use error_log
            error_log("GCException (failed to get logger): " . $this->getMessage() . " | Original error: " . $e->getMessage());
        }
    }

    /**
     * Get context array
     */
    public function getContext(): array {
        return $this->context;
    }
}
