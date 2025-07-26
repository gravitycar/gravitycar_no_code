<?php
namespace Gravitycar\Core;

use Gravitycar\Core\FieldBase;
use Gravitycar\Core\ValidationRuleBase;
use Gravitycar\Core\RelationshipBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Abstract base class for all models in Gravitycar.
 * Handles dynamic fields, relationships, validation, and soft deletes.
 */
abstract class ModelBase {
    /** @var string */
    protected string $id;
    /** @var string */
    protected string $name;
    /** @var array<string, FieldBase> */
    protected array $fields = [];
    /** @var array<string, RelationshipBase> */
    protected array $relationships = [];
    /** @var array<string, ValidationRuleBase> */
    protected array $validationRules = [];
    /** @var Logger */
    protected Logger $logger;
    /** @var array */
    protected array $metadata;
    /** @var bool */
    protected bool $deleted = false;
    /** @var string|null */
    protected ?string $deletedAt = null;
    /** @var string|null */
    protected ?string $deletedBy = null;

    public function __construct() {
        $this->logger = new Logger(static::class);
        $this->ingestMetadata();
        $this->initializeFields();
        $this->initializeRelationships();
        $this->initializeValidationRules();
    }

    /**
     * Get metadata file paths for this model
     */
    protected function getMetaDataFilePaths(): array {
        $modelName = strtolower(static::class);
        $modelName = basename(str_replace('\\', '/', $modelName));
        return [
            "src/models/{$modelName}/{$modelName}_metadata.php"
        ];
    }

    /**
     * Ingest metadata from files
     */
    protected function ingestMetadata(): void {
        $metadataFiles = $this->getMetaDataFilePaths();
        $this->metadata = [];

        foreach ($metadataFiles as $file) {
            if (file_exists($file)) {
                $data = include $file;
                if (is_array($data)) {
                    $this->metadata = array_merge_recursive($this->metadata, $data);
                }
            }
        }

        if (empty($this->metadata)) {
            throw new GCException('No metadata found for model ' . static::class, $this->logger);
        }
    }

    /**
     * Initialize fields from metadata
     */
    protected function initializeFields(): void {
        if (!isset($this->metadata['fields'])) {
            throw new GCException('Model metadata missing fields definition', $this->logger);
        }

        foreach ($this->metadata['fields'] as $fieldName => $fieldMeta) {
            $fieldClass = "\\Gravitycar\\Fields\\" . ($fieldMeta['type'] ?? 'Text') . "Field";
            if (!class_exists($fieldClass)) {
                $this->logger->warning("Field class $fieldClass does not exist for field $fieldName");
                continue;
            }
            $this->fields[$fieldName] = new $fieldClass($fieldMeta, $this->logger);
        }
    }

    /**
     * Initialize relationships from metadata
     */
    protected function initializeRelationships(): void {
        if (!isset($this->metadata['relationships'])) {
            return;
        }

        foreach ($this->metadata['relationships'] as $relName => $relMeta) {
            $relClass = "\\Gravitycar\\Relationships\\" . ($relMeta['type'] ?? 'RelationshipBase');
            if (!class_exists($relClass)) {
                $this->logger->warning("Relationship class $relClass does not exist for relationship $relName");
                continue;
            }
            $this->relationships[$relName] = new $relClass($relMeta, $this->logger);
        }
    }

    /**
     * Initialize validation rules from metadata
     */
    protected function initializeValidationRules(): void {
        if (!isset($this->metadata['validationRules'])) {
            return;
        }

        foreach ($this->metadata['validationRules'] as $ruleName) {
            $ruleClass = "\\Gravitycar\\Validation\\" . $ruleName . "ValidationRule";
            if (!class_exists($ruleClass)) {
                $this->logger->warning("Validation rule class $ruleClass does not exist");
                continue;
            }
            $this->validationRules[$ruleName] = new $ruleClass($this->logger);
        }
    }

    /**
     * Validate all fields and relationships
     */
    public function validate(): bool {
        foreach ($this->fields as $field) {
            if (!$field->validate()) {
                $this->logger->error("Validation failed for field {$field->getName()}");
                return false;
            }
        }

        foreach ($this->relationships as $relationship) {
            if (!$relationship->validate()) {
                $this->logger->error("Validation failed for relationship {$relationship->getName()}");
                return false;
            }
        }

        foreach ($this->validationRules as $rule) {
            if (!$rule->validate($this)) {
                $this->logger->error("Model-level validation failed for rule " . get_class($rule));
                return false;
            }
        }

        return true;
    }

    /**
     * Soft delete the model
     */
    public function softDelete(string $userId): void {
        $this->deleted = true;
        $this->deletedAt = date('Y-m-d H:i:s');
        $this->deletedBy = $userId;
        $this->logger->info("Model {$this->name} soft-deleted by user $userId");
    }

    /**
     * Restore a soft-deleted model
     */
    public function restore(): void {
        $this->deleted = false;
        $this->deletedAt = null;
        $this->deletedBy = null;
        $this->logger->info("Model {$this->name} restored");
    }

    /**
     * Get human-readable name for the model
     */
    public function getDisplayName(): string {
        return $this->metadata['name'] ?? $this->name;
    }

    /**
     * Get all fields
     */
    public function getFields(): array {
        return $this->fields;
    }

    /**
     * Get a specific field
     */
    public function getField(string $fieldName): ?FieldBase {
        return $this->fields[$fieldName] ?? null;
    }

    /**
     * Get field value
     */
    public function get(string $fieldName) {
        $field = $this->getField($fieldName);
        return $field ? $field->getValue() : null;
    }

    /**
     * Set field value
     */
    public function set(string $fieldName, $value): void {
        $field = $this->getField($fieldName);
        if (!$field) {
            throw new GCException("Field $fieldName not found in model", $this->logger);
        }
        $field->setValue($value);
    }
}
