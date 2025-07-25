<?php

namespace Gravitycar\Core;

use Exception;

/**
 * Custom exception class for the Gravitycar framework
 *
 * This exception class is used throughout the framework to handle
 * framework-specific errors and provide consistent error reporting.
 */
class GCException extends Exception
{
    protected string $context;
    protected array $metadata;

    public function __construct(
        string $message = "",
        int $code = 0,
        Exception $previous = null,
        string $context = "",
        array $metadata = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->metadata = $metadata;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();
        if (!empty($this->context)) {
            $message = "[{$this->context}] {$message}";
        }
        return $message;
    }
}
