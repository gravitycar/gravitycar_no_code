<?php
namespace Gravitycar\Core;

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
    /** @var array */
    protected array $validationRules = [];
    /** @var Logger */
    protected Logger $logger;

    public function __construct(array $metadata, Logger $logger) {
        if (empty($metadata['name'])) {
            throw new GCException('Field metadata missing name',
                ['metadata' => $metadata]);
        }
        $this->name = $metadata['name'];
        $this->metadata = $metadata;
        $this->logger = $logger;
        $this->value = $metadata['defaultValue'] ?? null;
        $this->originalValue = $this->value;
        $this->validationRules = $metadata['validationRules'] ?? [];
    }

    public function getName(): string {
        return $this->name;
    }

    public function getValue() {
        return $this->value;
    }

    public function setValue($value): void {
        $this->originalValue = $this->value;
        $this->value = $value;
        $this->validate();
    }

    public function validate(): bool {
        foreach ($this->validationRules as $ruleName) {
            $ruleClass = "\\Gravitycar\\Validation\\" . $ruleName . "Validation";
            if (!class_exists($ruleClass)) {
                $this->logger->warning("Validation rule class $ruleClass does not exist for field {$this->name}");
                continue;
            }
            $rule = new $ruleClass($this->logger);
            if (!$rule->validate($this->value)) {
                $this->logger->error("Validation failed for field {$this->name} with rule $ruleName");
                return false;
            }
        }
        return true;
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
}
