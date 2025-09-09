<?php
namespace Gravitycar\Validation;

use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Models\ModelBase;

/**
 * Abstract base class for all validation rules in Gravitycar.
 * Handles validation logic, error messages, and logging.
 */
abstract class ValidationRuleBase {
    /** @var string */
    protected string $name;
    /** @var FieldBase|null */
    protected ?FieldBase $field = null;
    /** @var ModelBase|null */
    protected ?ModelBase $model = null;
    /** @var string */
    protected string $errorMessage;
    /** @var mixed */
    protected $value = null;
    /** @var bool */
    protected bool $isEnabled = true;
    /** @var int */
    protected int $priority = 0;
    /** @var bool */
    protected bool $stopOnFailure = false;
    /** @var bool */
    protected bool $skipIfEmpty = false;
    /** @var bool */
    protected bool $contextSensitive = false;
    /** @var array */
    protected array $conditionalRules = [];
    /** @var Logger */
    protected Logger $logger;

    public function __construct(string $name = '', string $errorMessage = '') {
        $this->logger = $this->getLogger();
        $this->name = $name ?: static::class;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Get logger instance lazily to avoid circular dependencies
     */
    protected function getLogger(): Logger {
        return \Gravitycar\Core\ServiceLocator::getLogger();
    }

    /**
     * Validate a value. Returns true if valid, false otherwise.
     * @param mixed $value
     * @param \Gravitycar\Models\ModelBase|null $model Optional model context for validation
     * @return bool
     */
    abstract public function validate($value, $model = null): bool;

    /**
     * Get the error message for failed validation.
     * @return string
     */
    public function getErrorMessage(): string {
        return $this->errorMessage;
    }

    /**
     * Set the value to be validated
     */
    public function setValue($value): void {
        $this->value = $value;
    }

    /**
     * Set the field object this validation rule is associated with
     */
    public function setField(FieldBase $field): void {
        $this->field = $field;
    }

    /**
     * Set the model object this validation rule is associated with
     */
    public function setModel(ModelBase $model): void {
        $this->model = $model;
    }

    /**
     * Format error message with field-specific values
     */
    public function getFormatErrorMessage(): string {
        $message = $this->getErrorMessage();

        if ($this->field) {
            $message = str_replace('{fieldName}', $this->field->getName(), $message);
        }

        if ($this->value !== null) {
            $message = str_replace('{value}', (string)$this->value, $message);
        }

        return $message;
    }

    /**
     * Determine whether this validation rule should be applied
     */
    public function isApplicable($value, FieldBase $field, ModelBase $model = null): bool {
        // Check if validation is enabled
        if (!$this->isEnabled) {
            return false;
        }

        // Skip if empty and skipIfEmpty is true
        if ($this->skipIfEmpty && (empty($value) || $value === null || $value === '')) {
            return false;
        }

        // Check conditional rules if any exist
        if (!empty($this->conditionalRules)) {
            foreach ($this->conditionalRules as $condition) {
                // Implementation of conditional logic would go here
                // For now, we'll assume all conditions pass
            }
        }

        return true;
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        // Default implementation returns empty string
        // Subclasses should override this if they support client-side validation
        return '';
    }

    /**
     * Determine if the value should be validated based on common criteria
     * This method checks skipIfEmpty setting and null/empty values
     */
    protected function shouldValidateValue($value): bool {
        // Skip validation if value is empty and skipIfEmpty is true
        if (empty($value) && $this->skipIfEmpty) {
            return false;
        }

        // Skip validation if no value provided
        if ($value === null || $value === '') {
            return false;
        }

        return true;
    }
}
