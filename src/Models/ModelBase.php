<?php
namespace Gravitycar\Models;

use Gravitycar\Factories\FieldFactory;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Validation\ValidationRuleBase;
use Gravitycar\Relationships\RelationshipBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Fields\PasswordField;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Metadata\MetadataEngine;
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
    /** @var MetadataEngine */
    protected MetadataEngine $metadataEngine;
    /** @var bool */
    protected bool $metadataLoaded = false;
    /** @var bool */
    protected bool $fieldsInitialized = false;
    /** @var bool */
    protected bool $relationshipsInitialized = false;
    /** @var bool */
    protected bool $deleted = false;
    /** @var string|null */
    protected ?string $deletedAt = null;
    /** @var string|null */
    protected ?string $deletedBy = null;

    public function __construct() {
        $this->logger = ServiceLocator::getLogger();
        $this->metadataEngine = ServiceLocator::getMetadataEngine();
        
        // Load metadata immediately during construction
        $this->loadMetadata();
        
        // Keep fields/relationships lazy since they depend on metadata
        $this->metadata = $this->metadata ?? []; // Ensure array is set
    }

    /**
     * Initialize all model components in the correct order (lazy loading pattern)
     */
    protected function initializeModel(): void {
        if (!$this->metadataLoaded) {
            $this->loadMetadata();
        }
        if (!$this->fieldsInitialized) {
            $this->initializeFields();
        }
        if (!$this->relationshipsInitialized) {
            $this->initializeRelationships();
        }
        $this->initializeValidationRules();
    }

    /**
     * Load metadata using MetadataEngine (called during construction)
     */
    protected function loadMetadata(): void {
        $modelName = $this->metadataEngine->resolveModelName(static::class);
        $this->metadata = $this->metadataEngine->getModelMetadata($modelName);
        $this->validateMetadata($this->metadata);
        $this->metadataLoaded = true;
    }

    /**
     * Get metadata file paths for this model (kept for backward compatibility)
     */
    protected function getMetaDataFilePaths(): array {
        $modelName = $this->metadataEngine->resolveModelName(static::class);
        return [$this->metadataEngine->buildModelMetadataPath($modelName)];
    }

    /**
     * Ingest metadata from files (updated to use MetadataEngine)
     */
    protected function ingestMetadata(): void {
        $this->loadMetadata();
    }

    /**
     * Validate that required metadata is present and correctly formatted
     */
    protected function validateMetadata(array $metadata): void {
        if (empty($metadata['fields'])) {
            throw new GCException('No metadata found for model ' . static::class,
                ['model_class' => static::class]);
        }

        if (!is_array($metadata['fields'])) {
            throw new GCException('Model metadata missing fields definition',
                ['model_class' => static::class, 'metadata' => $metadata]);
        }
    }

    /**
     * Initialize fields from metadata (lazy loading for fields only)
     */
    protected function initializeFields(): void {
        if ($this->fieldsInitialized) {
            return;
        }

        // Metadata is already loaded during construction
        if (!isset($this->metadata['fields'])) {
            throw new GCException('Model metadata missing fields definition',
                ['model_class' => static::class, 'metadata' => $this->metadata]);
        }

        // Try to get FieldFactory from container first, fall back to creating new one
        $fieldFactory = null;
        if (\Gravitycar\Core\ServiceLocator::hasService('field_factory')) {
            $fieldFactory = \Gravitycar\Core\ServiceLocator::get('field_factory');
        } else {
            // Create FieldFactory instance using DI system
            $fieldFactory = \Gravitycar\Core\ServiceLocator::createFieldFactory($this);
        }

        foreach ($this->metadata['fields'] as $fieldName => $fieldMeta) {
            $field = $this->createSingleField($fieldName, $fieldMeta, $fieldFactory);
            if ($field !== null) {
                $this->fields[$fieldName] = $field;
            }
        }

        $this->fieldsInitialized = true;
    }

    /**
     * Create a single field with error handling
     */
    protected function createSingleField(string $fieldName, array $fieldMeta, FieldFactory $fieldFactory): ?FieldBase {
        $preparedMetadata = $this->prepareFieldMetadata($fieldName, $fieldMeta);

        try {
            // Use FieldFactory to create field - it will automatically set table name
            return $fieldFactory->createField($preparedMetadata);
        } catch (\Exception $e) {
            $this->logger->warning("Failed to create field $fieldName: " . $e->getMessage(), [
                'field_name' => $fieldName,
                'field_metadata' => $fieldMeta,
                'model_class' => static::class,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Prepare field metadata with required field name
     */
    protected function prepareFieldMetadata(string $fieldName, array $fieldMeta): array {
        // Ensure field name is included in metadata
        $fieldMeta['name'] = $fieldName;
        return $fieldMeta;
    }

    /**
     * Initialize relationships from metadata
     */
    /**
     * Initialize relationships from metadata (lazy loading for relationships only)
     */
    protected function initializeRelationships(): void {
        if ($this->relationshipsInitialized) {
            return;
        }

        // Metadata is already loaded during construction
        if (!isset($this->metadata['relationships'])) {
            $this->relationshipsInitialized = true;
            return;
        }

        // Create RelationshipFactory instance using DI system
        $relationshipFactory = ServiceLocator::createRelationshipFactory($this);

        foreach ($this->metadata['relationships'] as $relName => $relConfig) {
            try {
                // Use RelationshipFactory to create relationship with validation
                $relationship = $relationshipFactory->createRelationship($relName);
                $this->relationships[$relName] = $relationship;
                
                $this->logger->debug('Relationship initialized successfully', [
                    'relationship_name' => $relName,
                ]);
                
            } catch (\Exception $e) {
                $this->logger->error("Failed to create relationship $relName: " . $e->getMessage(), [
                    'relationship_name' => $relName,
                    'model_class' => static::class,
                    'error' => $e->getMessage()
                ]);
                // Continue with other relationships instead of failing completely
            }
        }

        $this->relationshipsInitialized = true;
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
    /**
     * Get all fields (lazy loading)
     */
    public function getFields(): array {
        if (!$this->fieldsInitialized) {
            $this->initializeFields();
        }
        return $this->fields;
    }

    /**
     * Get a specific field (lazy loading)
     */
    public function getField(string $fieldName): ?FieldBase {
        if (!$this->fieldsInitialized) {
            $this->initializeFields();
        }
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
        $field->setValue($value, $this);
    }

    /**
     * Populate model fields from API request data
     * Only sets fields that exist on the model, ignoring unknown fields
     * 
     * @param array $data Associative array of field names and values from API request
     * @return void
     */
    public function populateFromAPI(array $data): void {
        foreach ($data as $field => $value) {
            if ($this->hasField($field)) {
                $this->set($field, $value);
            }
        }
    }

    /**
     * Convert model to array representation
     * 
     * @return array Associative array of field names and values
     */
    public function toArray(): array {
        $data = [];
        foreach ($this->getFields() as $fieldName => $field) {
            if (is_a($field, PasswordField::class)) {
                continue;
            }
            $data[$fieldName] = $field->getValue();
        }
        return $data;
    }

    /**
     * Create a new record in the database for the model
     */
    public function create(): bool {
        if (!$this->validateForPersistence()) {
            return false;
        }

        $this->prepareIdForCreate();
        $this->setAuditFieldsForCreate();

        return $this->persistToDatabase('create');
    }

    /**
     * Update an existing record in the database for the model
     */
    public function update(): bool {
        if (!$this->validateForPersistence()) {
            return false;
        }

        // Ensure ID field is set for updates
        if (!$this->get('id')) {
            throw new GCException('Cannot update model without ID field set', [
                'model_class' => static::class
            ]);
        }

        $this->setAuditFieldsForUpdate();

        return $this->persistToDatabase('update');
    }

    /**
     * Validate model before persistence operations
     */
    protected function validateForPersistence(): bool {
        $validationErrors = $this->getValidationErrors();

        if (!empty($validationErrors)) {
            $this->logger->error('Cannot persist model with validation errors', [
                'model_class' => static::class,
                'validation_errors' => $validationErrors
            ]);
            return false;
        }

        return true;
    }

    /**
     * Prepare ID field for create operation
     */
    protected function prepareIdForCreate(): void {
        // Generate UUID for ID field if not set
        if (!$this->get('id')) {
            $this->set('id', $this->generateUuid());
        }
    }

    /**
     * Persist model to database using the specified operation
     */
    protected function persistToDatabase(string $operation): bool {
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();

        return match($operation) {
            'create' => $dbConnector->create($this),
            'update' => $dbConnector->update($this),
            default => throw new GCException("Unknown persistence operation: $operation", [
                'operation' => $operation,
                'model_class' => static::class
            ])
        };
    }

    /**
     * Find records by criteria using this model instance (performance optimized)
     */
    public function find(array $criteria = [], array $fields = [], array $parameters = []): array {
        $dbConnector = ServiceLocator::getDatabaseConnector();
        $rows = $dbConnector->find($this, $criteria, $fields, $parameters);
        return $this->fromRows($rows);
    }

    /**
     * Find a single record by ID and populate this instance (performance optimized)
     */
    public function findById($id, array $fields = []) {
        $dbConnector = ServiceLocator::getDatabaseConnector();
        $rows = $dbConnector->find($this, ['id' => $id], $fields, ['limit' => 1]);
        if (empty($rows)) {
            return null;
        }
        $this->populateFromRow($rows[0]);
        return $this;
    }

    /**
     * Find the first record matching criteria and populate this instance
     */
    public function findFirst(array $criteria = [], array $fields = [], array $orderBy = []) {
        $parameters = ['limit' => 1];
        if (!empty($orderBy)) {
            $parameters['orderBy'] = $orderBy;
        }
        $rows = $this->findRaw($criteria, $fields, $parameters);
        if (empty($rows)) {
            return null;
        }
        $this->populateFromRow($rows[0]);
        return $this;
    }

    /**
     * Find all records
     */
    public function findAll(array $fields = [], array $orderBy = []): array {
        $parameters = [];
        if (!empty($orderBy)) {
            $parameters['orderBy'] = $orderBy;
        }
        return $this->find([], $fields, $parameters);
    }

    /**
     * Find records by criteria and return raw database rows (performance optimized)
     */
    public function findRaw(array $criteria = [], array $fields = [], array $parameters = []): array {
        $dbConnector = ServiceLocator::getDatabaseConnector();
        return $dbConnector->find($this, $criteria, $fields, $parameters);
    }

    /**
     * Get validation errors from all fields
     */
    public function getValidationErrors(): array {
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
     * 
     * This method retrieves the currently authenticated user's ID from the JWT token
     * in the request headers. It integrates with the Gravitycar authentication system
     * that uses JWT tokens for user session management.
     * 
     * @return string|null The current user's ID if authenticated, null otherwise
     */
    protected function getCurrentUserId(): ?string {
        try {
            $currentUser = ServiceLocator::getCurrentUser();
            return $currentUser ? $currentUser->get('id') : null;
        } catch (\Exception $e) {
            $this->logger->debug('Failed to get current user ID for audit fields', [
                'error' => $e->getMessage(),
                'model_class' => static::class
            ]);
            return null;
        }
    }

    /**
     * Get the currently authenticated user model instance
     * 
     * This method returns the full Users model instance for the currently authenticated user.
     * The authentication is based on JWT tokens that are validated against the database.
     * 
     * @return \Gravitycar\Models\ModelBase|null The current user model if authenticated, null otherwise
     */
    public function getCurrentUser(): ?\Gravitycar\Models\ModelBase {
        try {
            return ServiceLocator::getCurrentUser();
        } catch (\Exception $e) {
            $this->logger->debug('Failed to get current user model', [
                'error' => $e->getMessage(),
                'model_class' => static::class
            ]);
            return null;
        }
    }

    /**
     * Get table name from metadata
     */
    public function getTableName(): string {
        return $this->metadata['table'] ?? strtolower(static::class);
    }

    /**
     * Get alias for the model (defaults to table name)
     * Can be overridden by subclasses if a different alias is needed
     */
    public function getAlias(): string {
        return $this->getTableName();
    }

    /**
     * Get display columns for UI representation
     * Returns array of field names to display in lists or as related records
     */
    public function getDisplayColumns(): array {
        $displayColumns = $this->metadata['displayColumns'] ?? ['name'];

        // Ensure it's an array
        if (!is_array($displayColumns)) {
            $this->logger->warning("Model displayColumns should be an array, got " . gettype($displayColumns), [
                'model_class' => static::class,
                'display_columns' => $displayColumns
            ]);
            return ['name'];
        }
        
        // Validate that the specified fields exist on this model
        $validColumns = $this->filterExistingColumns($displayColumns);

        // If no valid columns found, fall back to default columns
        if (empty($validColumns)) {
            $validColumns = $this->getFallbackDisplayColumns();
        }

        return $validColumns;
    }

    /**
     * Filter display columns to only include fields that exist on this model
     */
    protected function filterExistingColumns(array $columns): array {
        $validColumns = [];

        foreach ($columns as $column) {
            if ($this->hasField($column)) {
                $validColumns[] = $column;
            } else {
                $this->logger->warning("Display column '$column' does not exist as a field", [
                    'model_class' => static::class,
                    'missing_column' => $column,
                    'available_fields' => array_keys($this->fields)
                ]);
            }
        }
        
        return $validColumns;
    }

    /**
     * Get fallback display columns when no valid columns are found
     */
    protected function getFallbackDisplayColumns(): array {
        // Try 'name' field first
        if ($this->hasField('name')) {
            return ['name'];
        }

        // Use the first available field as last resort
        $fieldNames = array_keys($this->fields);
        $fallbackColumns = !empty($fieldNames) ? [$fieldNames[0]] : [];

        $this->logger->warning("No valid display columns found, using fallback", [
            'model_class' => static::class,
            'fallback_column' => $fallbackColumns
        ]);

        return $fallbackColumns;
    }

    /**
     * Populate this model instance from a database row
     */
    public function populateFromRow(array $row): void {
        foreach ($row as $fieldName => $value) {
            if ($this->hasField($fieldName)) {
                $field = $this->getField($fieldName);
                if (method_exists($field, 'setValueFromTrustedSource')) {
                    $field->setValueFromTrustedSource($value);
                } else {
                    // Fallback for fields that don't have setValueFromTrustedSource
                    $this->set($fieldName, $value);
                }
            }
        }
    }

    /**
     * Create multiple instances from database rows using ModelFactory
     */
    public function fromRows(array $rows): array {
        $instances = [];
        foreach ($rows as $row) {
            $instance = \Gravitycar\Factories\ModelFactory::new(basename(str_replace('\\', '/', static::class)));
            $instance->populateFromRow($row);
            $instances[] = $instance;
        }
        return $instances;
    }

    /**
     * Include core fields metadata automatically
     */
    /**
     * Get all relationships (lazy loading)
     */
    public function getRelationships(): array {
        if (!$this->relationshipsInitialized) {
            $this->initializeRelationships();
        }
        return $this->relationships;
    }

    /**
     * Get a specific relationship (lazy loading)
     */
    public function getRelationship(string $relationshipName): ?RelationshipBase {
        if (!$this->relationshipsInitialized) {
            $this->initializeRelationships();
        }
        return $this->relationships[$relationshipName] ?? null;
    }

    /**
     * Get related records for a specific relationship
     * Returns raw database records as associative arrays
     */
    public function getRelated(string $relationshipName): array {
        $relationship = $this->getRelationship($relationshipName);
        if (!$relationship) {
            throw new GCException("Relationship '{$relationshipName}' not found", [
                'relationship_name' => $relationshipName,
                'model_class' => static::class,
                'available_relationships' => array_keys($this->relationships)
            ]);
        }

        return $relationship->getRelatedRecords($this);
    }

    /**
     * Get related records as model instances (when needed)
     * This is more expensive than getRelated() but returns full model objects
     */
    public function getRelatedModels(string $relationshipName): array {
        $records = $this->getRelated($relationshipName);
        $models = [];

        // Determine the related model class from the relationship
        $relationship = $this->getRelationship($relationshipName);
        $relatedModelClass = $this->getRelatedModelClass($relationship);
        $relatedModelName = basename(str_replace('\\', '/', $relatedModelClass));

        foreach ($records as $record) {
            $instance = \Gravitycar\Factories\ModelFactory::new($relatedModelName);
            $instance->populateFromRow($record);
            $models[] = $instance;
        }

        return $models;
    }

    /**
     * Add a relationship to another model
     */
    public function addRelation(string $relationshipName, ModelBase $relatedModel, array $additionalData = []): bool {
        $relationship = $this->getRelationship($relationshipName);
        if (!$relationship) {
            throw new GCException("Relationship '{$relationshipName}' not found", [
                'relationship_name' => $relationshipName,
                'model_class' => static::class
            ]);
        }

        return $relationship->add($this, $relatedModel, $additionalData);
    }

    /**
     * Remove a relationship to another model
     */
    public function removeRelation(string $relationshipName, ModelBase $relatedModel): bool {
        $relationship = $this->getRelationship($relationshipName);
        if (!$relationship) {
            throw new GCException("Relationship '{$relationshipName}' not found", [
                'relationship_name' => $relationshipName,
                'model_class' => static::class
            ]);
        }

        return $relationship->remove($this, $relatedModel);
    }

    /**
     * Check if a relationship exists with another model
     */
    public function hasRelation(string $relationshipName, ModelBase $relatedModel): bool {
        $relationship = $this->getRelationship($relationshipName);
        if (!$relationship) {
            return false;
        }

        return $relationship->has($this, $relatedModel);
    }

    /**
     * Get paginated related records
     */
    public function getRelatedWithPagination(string $relationshipName, int $page = 1, int $perPage = 20): array {
        $relationship = $this->getRelationship($relationshipName);
        if (!$relationship) {
            throw new GCException("Relationship '{$relationshipName}' not found", [
                'relationship_name' => $relationshipName,
                'model_class' => static::class
            ]);
        }

        // Check if the relationship supports pagination
        if (method_exists($relationship, 'getRelatedPaginated')) {
            return $relationship->getRelatedPaginated($this, $page, $perPage);
        }

        // Fallback to regular getRelated with manual pagination
        $allRecords = $relationship->getRelatedRecords($this);
        $total = count($allRecords);
        $offset = ($page - 1) * $perPage;
        $records = array_slice($allRecords, $offset, $perPage);

        return [
            'records' => $records,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => ($offset + $perPage) < $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Handle cascade operations when this model is deleted
     */
    protected function handleRelationshipCascades(string $cascadeAction): bool {
        foreach ($this->relationships as $relationshipName => $relationship) {
            try {
                if (!$relationship->handleModelDeletion($this, $cascadeAction)) {
                    $this->logger->error("Cascade operation failed for relationship", [
                        'relationship_name' => $relationshipName,
                        'cascade_action' => $cascadeAction,
                        'model_class' => static::class,
                        'model_id' => $this->get('id')
                    ]);
                    return false;
                }
            } catch (\Exception $e) {
                $this->logger->error("Exception during cascade operation", [
                    'relationship_name' => $relationshipName,
                    'cascade_action' => $cascadeAction,
                    'model_class' => static::class,
                    'model_id' => $this->get('id'),
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }
        return true;
    }

    /**
     * Get the related model class from a relationship
     */
    public function getRelatedModelClass(RelationshipBase $relationship): string {
        $metadata = $relationship->getRelationshipMetadata();
        $currentModelName = basename(str_replace('\\', '/', static::class));

        // Determine which model is the related one based on relationship type
        switch ($metadata['type']) {
            case 'OneToOne':
                $modelA = $metadata['modelA'];
                $modelB = $metadata['modelB'];
                $relatedModelName = ($currentModelName === $modelA) ? $modelB : $modelA;
                break;

            case 'OneToMany':
                $modelOne = $metadata['modelOne'];
                $modelMany = $metadata['modelMany'];
                $relatedModelName = ($currentModelName === $modelOne) ? $modelMany : $modelOne;
                break;

            case 'ManyToMany':
                $modelA = $metadata['modelA'];
                $modelB = $metadata['modelB'];
                $relatedModelName = ($currentModelName === $modelA) ? $modelB : $modelA;
                break;

            default:
                throw new GCException("Unknown relationship type: {$metadata['type']}", [
                    'relationship_metadata' => $metadata,
                    'model_class' => static::class
                ]);
        }

        return "Gravitycar\\Models\\{$relatedModelName}";
    }

    public function getName():string {
        return $this->name;
    }

    /**
     * Register API routes from metadata
     * 
     * This method checks for 'apiRoutes' property in the model's metadata
     * and returns them for registration with the APIRouteRegistry.
     * Routes are validated by the APIRouteRegistry during registration.
     * 
     * @return array Array of route definitions from metadata
     */
    public function registerRoutes(): array {
        $routes = [];
        
        // Get routes from metadata (loaded from metadata files into $this->metadata)
        if (isset($this->metadata['apiRoutes']) && is_array($this->metadata['apiRoutes'])) {
            $routes = array_merge($routes, $this->metadata['apiRoutes']);
            
            $this->logger->debug("Found API routes in metadata for model", [
                'model_class' => static::class,
                'route_count' => count($this->metadata['apiRoutes'])
            ]);
        }
        
        return $routes;
    }
    
    // ===================================================================
    // PHASE 3: React Library Configuration Methods
    // ===================================================================
    
    /**
     * Get searchable fields for React components
     * 
     * Returns fields that are appropriate for text search operations.
     * Override in model subclasses to customize searchable fields.
     * 
     * @return array Array of field names that support search
     */
    protected function getSearchableFields(): array
    {
        // Check metadata first for searchable fields configuration
        if (isset($this->metadata['searchableFields']) && is_array($this->metadata['searchableFields'])) {
            return $this->metadata['searchableFields'];
        }
        
        // If no explicit searchableFields, try to use displayColumns as they're most relevant for user search
        $displayColumns = $this->getDisplayColumns();
        if (!empty($displayColumns)) {
            $searchableFields = [];
            $this->initializeModel(); // Ensure fields are loaded
            
            foreach ($displayColumns as $fieldName) {
                if (isset($this->fields[$fieldName]) && $this->isFieldSearchable($this->fields[$fieldName])) {
                    $searchableFields[] = $fieldName;
                }
            }
            
            // If we found searchable display columns, use them
            if (!empty($searchableFields)) {
                return $searchableFields;
            }
        }
        
        // Fallback: Auto-detect searchable fields based on field types
        $searchableFields = [];
        $this->initializeModel(); // Ensure fields are loaded
        
        foreach ($this->fields as $fieldName => $field) {
            if ($this->isFieldSearchable($field)) {
                $searchableFields[] = $fieldName;
            }
        }
        
        // Ensure we always return at least ID field as a safe default
        if (empty($searchableFields) && isset($this->fields['id'])) {
            $searchableFields = ['id'];
        }
        
        return $searchableFields;
    }
    
    /**
     * Get sortable fields for React components
     * 
     * Returns fields that are safe and efficient to sort by.
     * Override in model subclasses to customize sortable fields.
     * 
     * @return array Array of field names that support sorting
     */
    protected function getSortableFields(): array
    {
        // Check metadata first for sortable fields configuration
        if (isset($this->metadata['sortableFields']) && is_array($this->metadata['sortableFields'])) {
            return $this->metadata['sortableFields'];
        }
        
        // Auto-detect sortable fields based on field types
        $sortableFields = [];
        $this->initializeModel(); // Ensure fields are loaded
        
        foreach ($this->fields as $fieldName => $field) {
            if ($this->isFieldSortable($field)) {
                $sortableFields[] = $fieldName;
            }
        }
        
        // Default sortable fields for safety
        $defaultSortable = ['id', 'created_at', 'updated_at'];
        foreach ($defaultSortable as $defaultField) {
            if (isset($this->fields[$defaultField]) && !in_array($defaultField, $sortableFields)) {
                $sortableFields[] = $defaultField;
            }
        }
        
        return $sortableFields;
    }
    
    /**
     * Get default sorting for React components
     * 
     * Returns the default sort order when no sorting is specified.
     * Override in model subclasses to customize default sorting.
     * 
     * @return array Array of sort definitions [['field' => 'fieldName', 'direction' => 'asc|desc']]
     */
    protected function getDefaultSort(): array
    {
        // Check metadata first for default sort configuration
        if (isset($this->metadata['defaultSort']) && is_array($this->metadata['defaultSort'])) {
            return $this->metadata['defaultSort'];
        }
        
        // Default to ID ascending for consistent results
        return [['field' => 'id', 'direction' => 'asc']];
    }
    
    /**
     * Get filterable fields configuration (deprecated - use field-based operators instead)
     * 
     * This method is maintained for backward compatibility but the new field-based
     * operator system is preferred for filter validation.
     * 
     * @return array Array of filterable field configurations
     * @deprecated Use field-based operators defined in field metadata instead
     */
    protected function getFilterableFields(): array
    {
        $this->logger->warning("getFilterableFields() is deprecated, use field-based operators instead", [
            'model' => static::class,
            'method' => __METHOD__
        ]);
        
        // Return basic configuration for backward compatibility
        $filterableFields = [];
        $this->initializeModel(); // Ensure fields are loaded
        
        foreach ($this->fields as $fieldName => $field) {
            $filterableFields[$fieldName] = [
                'type' => $this->getFieldTypeName($field),
                'operators' => $field->getSupportedOperators()
            ];
        }
        
        return $filterableFields;
    }
    
    /**
     * Custom filter validation for business rules
     * 
     * Override this method in model subclasses to implement custom validation
     * logic for filters based on business rules, user permissions, etc.
     * 
     * @param array $filters Array of validated filters
     * @return array Array of filters after custom validation
     */
    protected function validateCustomFilters(array $filters): array
    {
        // Default implementation - no custom validation
        // Override in model subclasses for custom business rules
        return $filters;
    }
    
    /**
     * Get pagination configuration for React components
     * 
     * Returns pagination settings specific to this model.
     * Override in model subclasses to customize pagination behavior.
     * 
     * @return array Pagination configuration
     */
    protected function getPaginationConfig(): array
    {
        // Check metadata first for pagination configuration
        if (isset($this->metadata['pagination']) && is_array($this->metadata['pagination'])) {
            return $this->metadata['pagination'];
        }
        
        // Default pagination configuration
        return [
            'defaultPageSize' => 20,
            'maxPageSize' => 1000,
            'allowedPageSizes' => [10, 20, 50, 100, 200, 500, 1000]
        ];
    }
    
    /**
     * Get React library compatibility information
     * 
     * Returns information about which React libraries and formats this model supports.
     * 
     * @return array React compatibility configuration
     */
    protected function getReactCompatibility(): array
    {
        // Check metadata first for React compatibility configuration
        if (isset($this->metadata['reactCompatibility']) && is_array($this->metadata['reactCompatibility'])) {
            return $this->metadata['reactCompatibility'];
        }
        
        // Default compatibility - supports all React libraries
        return [
            'supportedFormats' => ['standard', 'ag-grid', 'mui', 'tanstack-query', 'swr', 'infinite-scroll'],
            'defaultFormat' => 'standard',
            'optimizedFor' => ['tanstack-query'], // Preferred React library
            'cursorPagination' => false, // Enable for large datasets
            'realTimeUpdates' => false   // Enable for live data
        ];
    }
    
    // ===================================================================
    // Helper Methods for Field Classification
    // ===================================================================
    
    /**
     * Check if a field is searchable based on its type
     */
    private function isFieldSearchable(FieldBase $field): bool
    {
        $fieldClassName = get_class($field);
        $shortName = substr($fieldClassName, strrpos($fieldClassName, '\\') + 1);
        
        // Field types that support text search
        $searchableTypes = [
            'TextField', 'EmailField', 'BigTextField', 'Enum', 'MultiEnum'
        ];
        
        // Field types that should NOT be searchable for security/performance
        $nonSearchableTypes = [
            'PasswordField', 'ImageField'
        ];
        
        if (in_array($shortName, $nonSearchableTypes)) {
            return false;
        }
        
        return in_array($shortName, $searchableTypes);
    }
    
    /**
     * Check if a field is sortable based on its type
     */
    private function isFieldSortable(FieldBase $field): bool
    {
        $fieldClassName = get_class($field);
        $shortName = substr($fieldClassName, strrpos($fieldClassName, '\\') + 1);
        
        // Field types that should NOT be sortable for performance/logic reasons
        $nonSortableTypes = [
            'PasswordField', 'ImageField', 'BigTextField', 'MultiEnum'
        ];
        
        return !in_array($shortName, $nonSortableTypes);
    }
    
    /**
     * Get field type name for configuration
     */
    private function getFieldTypeName(FieldBase $field): string
    {
        $fieldClassName = get_class($field);
        return substr($fieldClassName, strrpos($fieldClassName, '\\') + 1);
    }
    
    // ===================================================================
    // Public Methods for API Controllers
    // ===================================================================
    
    /**
     * Get all searchable fields (public accessor)
     */
    public function getSearchableFieldsList(): array
    {
        return $this->getSearchableFields();
    }
    
    /**
     * Get all sortable fields (public accessor)
     */
    public function getSortableFieldsList(): array
    {
        return $this->getSortableFields();
    }
    
    /**
     * Get default sort configuration (public accessor)
     */
    public function getDefaultSortConfig(): array
    {
        return $this->getDefaultSort();
    }
    
    /**
     * Get pagination configuration (public accessor)
     */
    public function getPaginationConfiguration(): array
    {
        return $this->getPaginationConfig();
    }
    
    /**
     * Get React compatibility configuration (public accessor)
     */
    public function getReactCompatibilityInfo(): array
    {
        return $this->getReactCompatibility();
    }
}
