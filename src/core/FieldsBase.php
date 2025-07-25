<?php

namespace Gravitycar\Core;

use Gravitycar\Core\GCException;

/**
 * Base class for all field types in the Gravitycar framework
 *
 * This class provides the common properties and methods that all field types
 * must implement. It handles field validation, value setting/getting, and
 * database interaction.
 */
abstract class FieldsBase
{
    protected string $name = '';
    protected string $label = '';
    protected string $type = '';
    protected string $phpDataType = 'string';
    protected string $databaseType = 'VARCHAR';
    protected string $uiDataType = 'text';
    protected mixed $value = null;
    protected mixed $originalValue = null;
    protected mixed $defaultValue = null;
    protected array $validationRules = [];
    protected bool $required = false;
    protected bool $unique = false;
    protected ?int $maxLength = null;
    protected ?int $minLength = null;
    protected mixed $minValue = null;
    protected mixed $maxValue = null;
    protected bool $readOnly = false;
    protected ?string $requiredUserType = null;
    protected bool $searchable = true;
    protected bool $isDbField = true;
    protected bool $isPrimaryKey = false;
    protected bool $isIndexed = false;
    protected array $allowedValues = [];
    protected array $forbiddenValues = [];
    protected ?string $optionsClass = null;
    protected ?string $optionsMethod = null;
    protected string $placeholder = '';
    protected string $description = '';
    protected string $helpText = '';
    protected bool $showInList = true;
    protected bool $showInForm = true;
    protected array $metadata = [];
    protected array $validationErrors = [];
    protected bool $hasChanged = false;

    public function __construct(array $fieldDefinition)
    {
        $this->ingestFieldDefinitions($fieldDefinition);
        $this->setupValidationRules($this->validationRules);
    }

    public function get(string $fieldName): mixed
    {
        return $this->value ?? $this->defaultValue;
    }

    public function set(string $fieldName, mixed $value, ModelBase $model): void
    {
        if ($this->name !== $fieldName) {
            throw new GCException("Field name mismatch: expected {$this->name}, got {$fieldName}");
        }

        if ($this->readOnly) {
            throw new GCException("Cannot set value on read-only field: {$fieldName}");
        }

        // Validate the value
        $this->validateValue($value, $model);

        // Check if value has changed
        if ($this->value !== $value) {
            $this->hasChanged = true;
        }

        $this->value = $value;
    }

    public function getValueForApi(): mixed
    {
        return $this->value;
    }

    public function setValueFromDB(mixed $value): void
    {
        $this->value = $value;
        $this->originalValue = $value;
        $this->hasChanged = false;
    }

    public function ingestFieldDefinitions(array $fieldDefinitions): void
    {
        foreach ($fieldDefinitions as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Validate required properties
        if (empty($this->name)) {
            throw new GCException("Field name is required");
        }

        if (empty($this->label)) {
            $this->label = $this->name;
        }
    }

    public function setupValidationRules(array $rules): void
    {
        $validationRuleInstances = [];

        foreach ($rules as $ruleName) {
            if (is_string($ruleName)) {
                $className = "Gravitycar\\Validation\\{$ruleName}Validation";

                if (!class_exists($className)) {
                    throw new GCException("Validation rule class not found: {$className}");
                }

                $validationRuleInstances[] = new $className();
            } else {
                $validationRuleInstances[] = $ruleName;
            }
        }

        $this->validationRules = $validationRuleInstances;
    }

    protected function validateValue(mixed $value, ModelBase $model): void
    {
        $this->validationErrors = [];

        foreach ($this->validationRules as $rule) {
            if (is_object($rule) && method_exists($rule, 'validate')) {
                if (!$rule->validate($value, $this, $model)) {
                    $this->validationErrors[] = $rule->getErrorMessage();
                }
            }
        }

        if (!empty($this->validationErrors)) {
            $model->addValidationError($this->name, $this->validationErrors);
        }
    }

    // Getter methods
    public function getName(): string { return $this->name; }
    public function getLabel(): string { return $this->label; }
    public function getType(): string { return $this->type; }
    public function getPhpDataType(): string { return $this->phpDataType; }
    public function getDatabaseType(): string { return $this->databaseType; }
    public function getUiDataType(): string { return $this->uiDataType; }
    public function getDefaultValue(): mixed { return $this->defaultValue; }
    public function isRequired(): bool { return $this->required; }
    public function isUnique(): bool { return $this->unique; }
    public function getMaxLength(): ?int { return $this->maxLength; }
    public function getMinLength(): ?int { return $this->minLength; }
    public function getMinValue(): mixed { return $this->minValue; }
    public function getMaxValue(): mixed { return $this->maxValue; }
    public function isReadOnly(): bool { return $this->readOnly; }
    public function getRequiredUserType(): ?string { return $this->requiredUserType; }
    public function isSearchable(): bool { return $this->searchable; }
    public function isDbField(): bool { return $this->isDbField; }
    public function isPrimaryKey(): bool { return $this->isPrimaryKey; }
    public function isIndexed(): bool { return $this->isIndexed; }
    public function getAllowedValues(): array { return $this->allowedValues; }
    public function getForbiddenValues(): array { return $this->forbiddenValues; }
    public function getOptionsClass(): ?string { return $this->optionsClass; }
    public function getOptionsMethod(): ?string { return $this->optionsMethod; }
    public function getPlaceholder(): string { return $this->placeholder; }
    public function getDescription(): string { return $this->description; }
    public function getHelpText(): string { return $this->helpText; }
    public function shouldShowInList(): bool { return $this->showInList; }
    public function shouldShowInForm(): bool { return $this->showInForm; }
    public function getMetadata(): array { return $this->metadata; }
    public function getValidationErrors(): array { return $this->validationErrors; }
    public function hasChanged(): bool { return $this->hasChanged; }
}
