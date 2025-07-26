<?php
namespace Gravitycar\Validation;

use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Abstract base class for all validation rules in Gravitycar.
 * Handles validation logic, error messages, and logging.
 */
abstract class ValidationRuleBase {
    /** @var string */
    protected string $name;
    /** @var string */
    protected string $errorMessage;
    /** @var Logger */
    protected Logger $logger;

    public function __construct(Logger $logger, string $name = '', string $errorMessage = '') {
        $this->logger = $logger;
        $this->name = $name ?: static::class;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Validate a value. Returns true if valid, false otherwise.
     * @param mixed $value
     * @return bool
     */
    abstract public function validate($value): bool;

    /**
     * Get the error message for failed validation.
     * @return string
     */
    public function getErrorMessage(): string {
        return $this->errorMessage;
    }
}
