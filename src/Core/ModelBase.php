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

    public function __construct(Logger $logger, array $metadata = []) {
        $this->logger = $logger;
        $this->metadata = $metadata;
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

        if (empty($this->metadata['fields'])) {
            throw new GCException('No metadata found for model ' . static::class,
                ['model_class' => static::class]);
        }

        if (!is_array($this->metadata['fields'])) {
            throw new GCException('Model metadata missing fields definition',
                ['model_class' => static::class, 'metadata' => $this->metadata]);
        }
    }

    /**
     * Initialize fields from metadata
     */
    protected function initializeFields(): void {
        if (!isset($this->metadata['fields'])) {
            throw new GCException('Model metadata missing fields definition',
                ['model_class' => static::class, 'metadata' => $this->metadata]);
        }

        foreach ($this->metadata['fields'] as $fieldName => $fieldMeta) {
            $fieldClass = "\\Gravitycar\\Fields\\" . ($fieldMeta['type'] ?? 'Text') . "Field";
            if (!class_exists($fieldClass)) {
                $this->logger->warning("Field class $fieldClass does not exist for field $fieldName");
                continue;
            }
            // Use ServiceLocator to create field with automatic dependency injection
            $this->fields[$fieldName] = \Gravitycar\Core\ServiceLocator::createField($fieldClass, $fieldMeta);
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
     * Soft delete the model (default delete behavior)
     */
    public function delete(): bool {
        return $this->softDelete();
    }

    /**
     * Soft delete the model by setting deleted_at and deleted_by fields
     */
    public function softDelete(): bool {
        // Ensure ID field is set
        if (!$this->get('id')) {
            throw new GCException('Cannot delete model without ID field set', [
                'model_class' => static::class
            ]);
        }

        // Set soft delete audit fields
        $this->setAuditFieldsForSoftDelete();

        // Delegate to DatabaseConnector for soft delete
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
        return $dbConnector->softDelete($this);
    }

    /**
     * Hard delete the model (permanently removes from database)
     */
    public function hardDelete(): bool {
        // Ensure ID field is set
        if (!$this->get('id')) {
            throw new GCException('Cannot delete model without ID field set', [
                'model_class' => static::class
            ]);
        }

        // Delegate to DatabaseConnector for hard delete
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
        return $dbConnector->hardDelete($this);
    }

    /**
     * Restore a soft-deleted model
     */
    public function restore(): bool {
        // Ensure ID field is set
        if (!$this->get('id')) {
            throw new GCException('Cannot restore model without ID field set', [
                'model_class' => static::class
            ]);
        }

        // Clear soft delete fields
        $this->clearSoftDeleteFields();

        // Update in database
        return $this->update();
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
            throw new GCException("Field $fieldName not found in model",
                ['field_name' => $fieldName, 'model_class' => static::class, 'available_fields' => array_keys($this->fields)]);
        }
        $field->setValue($value);
    }

    /**
     * Create a new record in the database for the model
     */
    public function create(): bool {
        // Check for validation errors first
        if (!empty($this->getValidationErrors())) {
            $this->logger->error('Cannot create model with validation errors', [
                'model_class' => static::class,
                'validation_errors' => $this->getValidationErrors()
            ]);
            return false;
        }

        // Generate UUID for ID field if not set
        if (!$this->get('id')) {
            $this->set('id', $this->generateUuid());
        }

        // Set audit fields
        $this->setAuditFieldsForCreate();

        // Delegate to DatabaseConnector
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
        return $dbConnector->create($this);
    }

    /**
     * Update an existing record in the database for the model
     */
    public function update(): bool {
        // Check for validation errors first
        if (!empty($this->getValidationErrors())) {
            $this->logger->error('Cannot update model with validation errors', [
                'model_class' => static::class,
                'validation_errors' => $this->getValidationErrors()
            ]);
            return false;
        }

        // Ensure ID field is set
        if (!$this->get('id')) {
            throw new GCException('Cannot update model without ID field set', [
                'model_class' => static::class
            ]);
        }

        // Set audit fields
        $this->setAuditFieldsForUpdate();

        // Delegate to DatabaseConnector
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
        return $dbConnector->update($this);
    }

    /**
     * Find records by criteria
     */
    public static function find(array $criteria = [], array $orderBy = [], int $limit = null, int $offset = null): array {
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
        return $dbConnector->find(static::class, $criteria, $orderBy, $limit, $offset);
    }

    /**
     * Find a single record by ID
     */
    public static function findById($id) {
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
        return $dbConnector->findById(static::class, $id);
    }

    /**
     * Find the first record matching criteria
     */
    public static function findFirst(array $criteria = [], array $orderBy = []) {
        $results = static::find($criteria, $orderBy, 1);
        return empty($results) ? null : $results[0];
    }

    /**
     * Find all records
     */
    public static function findAll(array $orderBy = []): array {
        return static::find([], $orderBy);
    }

    /**
     * Get validation errors from all fields
     */
    protected function getValidationErrors(): array {
        $errors = [];
        foreach ($this->fields as $fieldName => $field) {
            if (method_exists($field, 'getValidationErrors')) {
                $fieldErrors = $field->getValidationErrors();
                if (!empty($fieldErrors)) {
                    $errors[$fieldName] = $fieldErrors;
                }
            }
        }
        return $errors;
    }

    /**
     * Set audit fields for create operation
     */
    protected function setAuditFieldsForCreate(): void {
        $currentUserId = $this->getCurrentUserId();
        $currentTimestamp = date('Y-m-d H:i:s');

        // Set created_at and updated_at
        if ($this->getField('created_at')) {
            $this->set('created_at', $currentTimestamp);
        }
        if ($this->getField('updated_at')) {
            $this->set('updated_at', $currentTimestamp);
        }

        // Set created_by and updated_by
        if ($this->getField('created_by') && $currentUserId) {
            $this->set('created_by', $currentUserId);
        }
        if ($this->getField('updated_by') && $currentUserId) {
            $this->set('updated_by', $currentUserId);
        }
    }

    /**
     * Set audit fields for update operation
     */
    protected function setAuditFieldsForUpdate(): void {
        $currentUserId = $this->getCurrentUserId();
        $currentTimestamp = date('Y-m-d H:i:s');

        // Set updated_at
        if ($this->getField('updated_at')) {
            $this->set('updated_at', $currentTimestamp);
        }

        // Set updated_by
        if ($this->getField('updated_by') && $currentUserId) {
            $this->set('updated_by', $currentUserId);
        }
    }

    /**
     * Set audit fields for soft delete operation
     */
    protected function setAuditFieldsForSoftDelete(): void {
        $currentUserId = $this->getCurrentUserId();
        $currentTimestamp = date('Y-m-d H:i:s');

        // Set deleted_at
        if ($this->getField('deleted_at')) {
            $this->set('deleted_at', $currentTimestamp);
        }

        // Set deleted_by
        if ($this->getField('deleted_by') && $currentUserId) {
            $this->set('deleted_by', $currentUserId);
        }

        // Update the internal deleted flag
        $this->deleted = true;
    }

    /**
     * Clear soft delete fields for restore operation
     */
    protected function clearSoftDeleteFields(): void {
        // Clear deleted_at
        if ($this->getField('deleted_at')) {
            $this->set('deleted_at', null);
        }

        // Clear deleted_by
        if ($this->getField('deleted_by')) {
            $this->set('deleted_by', null);
        }

        // Update the internal deleted flag
        $this->deleted = false;
        $this->deletedAt = null;
        $this->deletedBy = null;
    }

    /**
     * Check if the model is soft deleted
     */
    public function isDeleted(): bool {
        return $this->deleted || $this->get('deleted_at') !== null;
    }

    /**
     * Check if a field should be included in database operations
     */
    public function hasField(string $fieldName): bool {
        return isset($this->fields[$fieldName]);
    }

    /**
     * Generate UUID for new records
     */
    protected function generateUuid(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Get current user ID for audit fields
     * TODO: Implement proper user session management
     */
    protected function getCurrentUserId(): ?string {
        // Placeholder implementation - will be replaced with proper session management
        return null;
    }

    /**
     * Get table name from metadata
     */
    public function getTableName(): string {
        return $this->metadata['table'] ?? strtolower(static::class);
    }
}
