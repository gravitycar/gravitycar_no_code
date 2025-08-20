<?php
namespace Gravitycar\Fields;

use Gravitycar\Validation\ValidationRuleBase;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Abstract base class for all field types in Gravitycar.
 * Handles value, validation, metadata, and logging.
 */
abstract class FieldBase {
    /** @var string */
    protected string $name;
    /** @var mixed */
    protected $value;
    /** @var mixed */
    protected $originalValue;
    /** @var array */
    protected array $metadata;
    /** @var ValidationRuleBase[] */
    protected array $validationRules = [];
    /** @var array */
    protected array $validationErrors = [];
    /** @var Logger */
    protected Logger $logger;
    /** @var string */
    protected string $tableName = '';
    
    /** @var array Supported filter operators for this field type */
    protected array $operators = ['equals', 'notEquals', 'isNull', 'isNotNull'];
    
    /** @var string React component name for rendering this field type */
    protected string $reactComponent = 'TextInput';

    public function __construct(array $metadata) {
        if (empty($metadata['name'])) {
            throw new GCException('Field metadata missing name',
                ['metadata' => $metadata]);
        }
        $this->logger = ServiceLocator::getLogger();

        // Use ingestMetadata to automatically populate properties from metadata
        $this->ingestMetadata($metadata);

        // Sync current property values back to metadata to include defaults
        $this->syncPropertiesToMetadata();

        // Set up validation rules after metadata ingestion
        $this->setUpValidationRules();

        // Set default value from trusted source (metadata) without validation
        $defaultValue = $metadata['defaultValue'] ?? null;
        $this->setValueFromTrustedSource($defaultValue);
        $this->originalValue = $this->value;
    }




    public function getName(): string {
        return $this->name;
    }

    public function getValue() {
        return $this->value;
    }

    public function setValue($value): void {
        // Store the original value before attempting to set new value
        $originalValue = $this->value;
        
        // Temporarily set the new value for validation
        $this->value = $value;
        
        // Validate the new value
        if ($this->validate()) {
            // If validation passes, keep the new value and update originalValue
            $this->originalValue = $originalValue;
        } else {
            // If validation fails, revert to the original value
            $this->value = $originalValue;
            
            // Log the validation failure for debugging
            $this->logger->warning("setValue failed validation for field '{$this->name}'", [
                'field_name' => $this->name,
                'attempted_value' => $value,
                'validation_errors' => $this->validationErrors,
                'current_value' => $this->value
            ]);
        }
    }

    /**
     * Set field value from a trusted source (e.g., database) without validation
     * Use this method when loading data from trusted sources where validation
     * has already been performed or is not needed (like database records)
     */
    public function setValueFromTrustedSource($value): void {
        $this->originalValue = $this->value;
        $this->value = $value;
        // No validation performed - data is from trusted source
    }

    /**
     * Set the table name for this field
     */
    public function setTableName(string $tableName): void {
        $this->tableName = $tableName;
    }

    /**
     * Get the table name for this field
     */
    public function getTableName(): string {
        return $this->tableName;
    }

    /**
     * Get the React component name for this field type
     */
    public function getReactComponent(): string {
        return $this->reactComponent;
    }

    /**
     * Set up validation rules by converting rule names to instantiated objects
     */
    protected function setUpValidationRules(): void {
        if (empty($this->validationRules)) {
            return;
        }

        $validationRuleFactory = \Gravitycar\Core\ServiceLocator::getValidationRuleFactory();
        $instantiatedRules = [];

        foreach ($this->validationRules as $index => $rule) {
            // Skip if already an object (already instantiated)
            if (is_object($rule)) {
                $instantiatedRules[$index] = $rule;
                continue;
            }

            // Convert string rule name to instantiated validation rule object
            if (is_string($rule)) {
                try {
                    $ruleObject = $validationRuleFactory->createValidationRule($rule);
                    
                    // Set field context on the validation rule
                    $ruleObject->setField($this);
                    
                    $instantiatedRules[$index] = $ruleObject;
                    
                    $this->logger->debug("Validation rule '{$rule}' instantiated successfully for field '{$this->name}'");
                } catch (\Exception $e) {
                    $this->logger->error("Failed to instantiate validation rule '{$rule}' for field '{$this->name}': " . $e->getMessage(), [
                        'field_name' => $this->name,
                        'rule_name' => $rule,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Continue with other rules even if one fails
                    continue;
                }
            }
        }

        // Replace the validationRules array with instantiated objects
        $this->validationRules = $instantiatedRules;
    }

    /**
     * Ingest metadata into field properties using reflection for type safety
     */
    protected function ingestMetadata(array $metadata): void {
        $this->metadata = $metadata;
        $reflection = new \ReflectionClass($this);

        foreach ($metadata as $key => $value) {
            // Skip if property doesn't exist
            if (!property_exists($this, $key)) {
                continue;
            }

            try {
                $property = $reflection->getProperty($key);
                $type = $property->getType();

                if (!$type) {
                    // No type hint, set directly
                    $this->$key = $value;
                    continue;
                }

                $expectedType = $type->getName();

                // Handle nullable types
                if ($value === null && $type->allowsNull()) {
                    $this->$key = null;
                    continue;
                }

                // Type validation and conversion
                if ($this->isCompatibleType($value, $expectedType)) {
                    $this->$key = $this->convertToType($value, $expectedType);
                } else {
                    $this->logger->warning("Type mismatch for property {$key}", [
                        'field_name' => $this->name ?? 'unknown',
                        'expected_type' => $expectedType,
                        'actual_type' => gettype($value),
                        'value' => $value
                    ]);
                }

            } catch (\ReflectionException $e) {
                $this->logger->error("Reflection error for property {$key}: " . $e->getMessage(), [
                    'field_name' => $this->name ?? 'unknown',
                    'property' => $key,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Sync current property values back to metadata after ingestion
     */
    protected function syncPropertiesToMetadata(): void {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            // Skip certain internal properties that shouldn't be in metadata
            if (in_array($propertyName, ['value', 'originalValue', 'metadata', 'validationRules', 'validationErrors', 'logger', 'tableName'])) {
                continue;
            }

            try {
                $property->setAccessible(true);

                // Check if the property is initialized before trying to access it
                // This prevents errors with typed properties that haven't been set
                if (!$property->isInitialized($this)) {
                    // For uninitialized properties, only add to metadata if it was explicitly provided
                    if (array_key_exists($propertyName, $this->metadata)) {
                        // The metadata value is already there, no need to sync
                        continue;
                    } else {
                        // Property is uninitialized and not in metadata, skip it
                        continue;
                    }
                }

                $currentValue = $property->getValue($this);

                // Always sync the property value to metadata
                // This ensures default values from field classes are included
                $this->metadata[$propertyName] = $currentValue;

            } catch (\ReflectionException $e) {
                $this->logger->error("Reflection error for property {$propertyName} during sync: " . $e->getMessage(), [
                    'field_name' => $this->name ?? 'unknown',
                    'property' => $propertyName,
                    'error' => $e->getMessage()
                ]);
            } catch (\Error $e) {
                // Handle uninitialized typed property errors
                $this->logger->debug("Property {$propertyName} is uninitialized, skipping sync", [
                    'field_name' => $this->name ?? 'unknown',
                    'property' => $propertyName
                ]);
            }
        }
    }

    /**
     * Check if a value is compatible with the expected type
     */
    private function isCompatibleType($value, string $expectedType): bool {
        $actualType = gettype($value);

        // Direct type matches
        $typeMap = [
            'string' => 'string',
            'integer' => 'int',
            'double' => 'float',
            'boolean' => 'bool',
            'array' => 'array'
        ];

        $mappedType = $typeMap[$actualType] ?? $actualType;

        if ($mappedType === $expectedType) {
            return true;
        }

        // Check if value can be converted
        switch ($expectedType) {
            case 'string':
                return is_scalar($value);
            case 'int':
                return is_numeric($value);
            case 'float':
                return is_numeric($value);
            case 'bool':
                return is_scalar($value);
            case 'array':
                return is_array($value) || is_object($value);
            default:
                // For class types, check with instanceof
                return is_object($value) && is_a($value, $expectedType);
        }
    }

    /**
     * Convert a value to the specified type
     */
    private function convertToType($value, string $expectedType) {
        switch ($expectedType) {
            case 'string':
                return (string) $value;
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return (bool) $value;
            case 'array':
                return is_array($value) ? $value : (array) $value;
            default:
                return $value; // Return as-is for object types
        }
    }

    /**
     * Register a validation error for this field
     */
    public function registerValidationError(string $error): void {
        $this->validationErrors[] = $error;
    }

    /**
     * Get all validation errors for this field
     */
    public function getValidationErrors(): array {
        return $this->validationErrors;
    }

    public function validate(): bool {
        // Clear previous validation errors
        $this->validationErrors = [];

        $isValid = true;

        foreach ($this->validationRules as $rule) {
            // Ensure the rule is a ValidationRuleBase instance
            if (!$rule instanceof \Gravitycar\Validation\ValidationRuleBase) {
                $this->logger->warning("Invalid validation rule type found for field '{$this->name}'", [
                    'rule_type' => gettype($rule),
                    'rule_class' => is_object($rule) ? get_class($rule) : 'not_object'
                ]);
                continue;
            }

            // Call the rule's validate method with the field's current value
            if (!$rule->validate($this->getValue())) {
                // Get the formatted error message from the rule
                $errorMessage = $rule->getFormatErrorMessage();

                // Register the validation error
                $this->registerValidationError($errorMessage);

                $isValid = false;
            }
        }

        return $isValid;
    }

    /**
     * Get the complete metadata array
     */
    public function getMetadata(): array {
        return $this->metadata;
    }

    /**
     * Get a specific metadata value by key
     */
    public function getMetadataValue(string $key, $default = null) {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if a metadata key exists
     */
    public function hasMetadata(string $key): bool {
        return isset($this->metadata[$key]);
    }

    /**
     * Check if a metadata key has a specific value
     */
    public function metadataEquals(string $key, $expectedValue): bool {
        return ($this->metadata[$key] ?? null) === $expectedValue;
    }

    /**
     * Check if a metadata key is set to true (boolean or truthy)
     */
    public function metadataIsTrue(string $key): bool {
        return !empty($this->metadata[$key]);
    }

    /**
     * Check if a metadata key is set to false (boolean false or falsy)
     */
    public function metadataIsFalse(string $key): bool {
        return empty($this->metadata[$key]);
    }

    /**
     * Check if this field should be stored in the database
     * Convenience method for the common 'isDBField' check
     */
    public function isDBField(): bool {
        // Default to true if not specified, false only if explicitly set to false
        return $this->getMetadataValue('isDBField', true) !== false;
    }

    /**
     * Check if this field is required
     */
    public function isRequired(): bool {
        return $this->metadataIsTrue('required');
    }

    /**
     * Check if this field is readonly
     */
    public function isReadonly(): bool {
        return $this->metadataIsTrue('readonly');
    }

    /**
     * Check if this field is unique
     */
    public function isUnique(): bool {
        return $this->metadataIsTrue('unique');
    }

    /**
     * Get supported filter operators for this field
     * Operators can be overridden in metadata via 'operators' key
     */
    public function getSupportedOperators(): array {
        // Allow metadata to override default operators
        return $this->getMetadataValue('operators', $this->operators);
    }

    /**
     * Check if a specific operator is supported by this field
     */
    public function supportsOperator(string $operator): bool {
        return in_array($operator, $this->getSupportedOperators(), true);
    }

    /**
     * Get human-readable description of supported operators
     */
    public function getOperatorDescriptions(): array {
        $descriptions = [
            'equals' => 'Exact match',
            'notEquals' => 'Not equal to',
            'contains' => 'Text contains value',
            'startsWith' => 'Text starts with value',
            'endsWith' => 'Text ends with value',
            'in' => 'Value is in list',
            'notIn' => 'Value is not in list',
            'greaterThan' => 'Greater than',
            'greaterThanOrEqual' => 'Greater than or equal to',
            'lessThan' => 'Less than',
            'lessThanOrEqual' => 'Less than or equal to',
            'between' => 'Between two values',
            'isNull' => 'Field is empty/null',
            'isNotNull' => 'Field is not empty/null',
            'overlap' => 'Array values overlap',
            'containsAll' => 'Array contains all values',
            'containsNone' => 'Array contains none of the values'
        ];

        $supportedOperators = $this->getSupportedOperators();
        $result = [];
        foreach ($supportedOperators as $operator) {
            $result[$operator] = $descriptions[$operator] ?? 'Custom operator';
        }
        return $result;
    }

    /**
     * Generate OpenAPI schema for this field type
     * Subclasses can override this for field-specific schema generation
     */
    public function generateOpenAPISchema(): array {
        $schema = ['type' => 'string']; // Default to string
        
        // Add description if available
        if (isset($this->metadata['description'])) {
            $schema['description'] = $this->metadata['description'];
        }
        
        // Add validation constraints
        if (isset($this->metadata['maxLength'])) {
            $schema['maxLength'] = $this->metadata['maxLength'];
        }
        
        if (isset($this->metadata['required']) && $this->metadata['required']) {
            // Required is handled at the parent schema level, not field level
        }
        
        // Add example if available
        if (isset($this->metadata['example'])) {
            $schema['example'] = $this->metadata['example'];
        }
        
        return $schema;
    }
}
