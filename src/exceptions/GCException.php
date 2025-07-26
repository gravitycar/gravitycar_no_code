<?php
namespace Gravitycar\Exceptions;

use Monolog\Logger;
use Exception;

/**
 * Base exception class for Gravitycar framework.
 * Provides logging and context for all framework exceptions.
 */
class GCException extends Exception {
    /** @var Logger */
    protected Logger $logger;
    /** @var array */
    protected array $context;

    public function __construct(string $message, Logger $logger, array $context = [], int $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->logger = $logger;
        $this->context = $context;
        $this->logException();
    }

    /**
     * Log the exception using Monolog
     */
    protected function logException(): void {
        $this->logger->error($this->getMessage(), [
            'exception' => static::class,
            'code' => $this->getCode(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString(),
        ]);
    }

    /**
     * Get context array
     */
    public function getContext(): array {
        return $this->context;
    }
}
