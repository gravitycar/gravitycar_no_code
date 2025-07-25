<?php

namespace Gravitycar\Core;

use Gravitycar\Core\GCException;
use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\ModelBase;

/**
 * Base class for all validation rules in the Gravitycar framework
 *
 * This class provides the common functionality for validation rules including
 * parameter handling, error message management, and validation logic.
 */
abstract class ValidationRuleBase
{
    protected string $name = '';
    protected string $errorMessage = '';
    protected array $parameters = [];
    protected bool $isEnabled = true;
    protected int $priority = 0;
    protected bool $stopOnFailure = false;
    protected bool $skipIfEmpty = false;
    protected bool $contextSensitive = false;
    protected ?string $customErrorMessage = null;
    protected array $conditionalRules = [];
    protected array $metadata = [];

    public function __construct(array $ruleDefinition = [])
    {
        $this->ingestRuleDefinitions($ruleDefinition);
    }

    abstract public function validate(mixed $value, FieldsBase $field, ModelBase $model = null): bool;

    public function getErrorMessage(): string
    {
        return $this->customErrorMessage ?? $this->errorMessage;
    }

    public function setErrorMessage(string $message): void
    {
        $this->customErrorMessage = $message;
    }

    public function setParameters(array $parameters): void
    {
        if ($this->validateParameters($parameters)) {
            $this->parameters = $parameters;
        }
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function isApplicable(mixed $value, FieldsBase $field, ModelBase $model = null): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        if ($this->skipIfEmpty && (empty($value) && $value !== 0 && $value !== '0')) {
            return false;
        }

        // Check conditional rules
        foreach ($this->conditionalRules as $condition) {
            if (!$this->evaluateCondition($condition, $value, $field, $model)) {
                return false;
            }
        }

        return true;
    }

    public function ingestRuleDefinitions(array $ruleDefinitions): void
    {
        foreach ($ruleDefinitions as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function formatErrorMessage(string $template, array $replacements = []): string
    {
        $message = $template;
        foreach ($replacements as $placeholder => $value) {
            $message = str_replace("{{$placeholder}}", $value, $message);
        }
        return $message;
    }

    public function validateParameters(array $parameters): bool
    {
        // Override in child classes for specific parameter validation
        return true;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function shouldStopOnFailure(): bool
    {
        return $this->stopOnFailure;
    }

    public function __clone(): ValidationRuleBase
    {
        return new static($this->metadata);
    }

    protected function evaluateCondition(array $condition, mixed $value, FieldsBase $field, ModelBase $model = null): bool
    {
        // Basic condition evaluation - can be extended
        if (isset($condition['field']) && $model) {
            $conditionField = $condition['field'];
            $conditionValue = $condition['value'] ?? null;
            $operator = $condition['operator'] ?? '=';

            $fieldValue = $model->get($conditionField);

            switch ($operator) {
                case '=':
                case '==':
                    return $fieldValue == $conditionValue;
                case '!=':
                    return $fieldValue != $conditionValue;
                case '>':
                    return $fieldValue > $conditionValue;
                case '<':
                    return $fieldValue < $conditionValue;
                case '>=':
                    return $fieldValue >= $conditionValue;
                case '<=':
                    return $fieldValue <= $conditionValue;
                default:
                    return true;
            }
        }

        return true;
    }
}
