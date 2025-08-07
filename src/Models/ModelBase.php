<?php
namespace Gravitycar\Models;

use Gravitycar\Factories\FieldFactory;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Validation\ValidationRuleBase;
use Gravitycar\Relationships\RelationshipBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Metadata\CoreFieldsMetadata;
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

    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->metadata = []; // Initialize empty, will be populated by ingestMetadata
        $this->initializeModel();
    }

    /**
     * Initialize all model components in the correct order
     */
    protected function initializeModel(): void {
        $this->ingestMetadata();
        $this->initializeFields();
        $this->initializeRelationships();
        $this->initializeValidationRules();
    }

    /**
     * Get metadata file paths for this model
     */
    protected function getMetaDataFilePaths(): array {
        $modelName = static::class;
        $modelName = basename(str_replace('\\', '/', $modelName));
        $modelNameLc = strtolower($modelName);
        return [
            "src/Models/{$modelName}/{$modelNameLc}_metadata.php"
        ];
    }

    /**
     * Ingest metadata from files
     */
    protected function ingestMetadata(): void {
        $metadataFiles = $this->getMetaDataFilePaths();
        $loadedMetadata = $this->loadMetadataFromFiles($metadataFiles);
        $this->metadata = $loadedMetadata;

        // Automatically include core fields metadata
        $this->includeCoreFieldsMetadata();

        $this->validateMetadata($this->metadata);
    }

    /**
     * Load metadata from multiple files and merge them
     */
    protected function loadMetadataFromFiles(array $filePaths): array {
        $mergedMetadata = [];

        foreach ($filePaths as $file) {
            if (file_exists($file)) {
                $data = include $file;
                if (is_array($data)) {
                    $mergedMetadata = $this->mergeMetadataArrays($mergedMetadata, $data);
                }
            }
        }

        return $mergedMetadata;
    }

    /**
     * Merge two metadata arrays recursively
     */
    protected function mergeMetadataArrays(array $existing, array $new): array {
        return array_merge_recursive($existing, $new);
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
     * Initialize fields from metadata
     */
    protected function initializeFields(): void {
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
    protected function initializeRelationships(): void {
        if (!isset($this->metadata['relationships'])) {
            return;
        }

        // Create RelationshipFactory instance using DI system
        $relationshipFactory = ServiceLocator::createRelationshipFactory($this);

        foreach ($this->metadata['relationships'] as $relName => $relMeta) {
            try {
                // Add name to metadata if not present
                $relMeta['name'] = $relName;
                
                // Use RelationshipFactory to create relationship with validation
                $relationship = $relationshipFactory->createRelationship($relMeta);
                $this->relationships[$relName] = $relationship;
                
                $this->logger->debug('Relationship initialized successfully', [
                    'relationship_name' => $relName,
                    'relationship_type' => $relMeta['type'] ?? 'unknown',
                    'model_class' => static::class
                ]);
                
            } catch (\Exception $e) {
                $this->logger->error("Failed to create relationship $relName: " . $e->getMessage(), [
                    'relationship_name' => $relName,
                    'relationship_metadata' => $relMeta,
                    'model_class' => static::class,
                    'error' => $e->getMessage()
                ]);
                // Continue with other relationships instead of failing completely
            }
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
        if (!$this->fields || empty($this->fields)) {
            $this->initializeFields();
        }
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
     * Find records by criteria
     */
    public static function find(array $criteria = [], array $fields = [], array $parameters = []): array {
        $dbConnector = ServiceLocator::getDatabaseConnector();
        $rows = $dbConnector->find(static::class, $criteria, $fields, $parameters);
        return static::fromRows($rows);
    }

    /**
     * Find a single record by ID
     */
    public static function findById($id, array $fields = []) {
        $dbConnector = ServiceLocator::getDatabaseConnector();
        $rows = $dbConnector->find(static::class, ['id' => $id], $fields, ['limit' => 1]);
        return empty($rows) ? null : static::fromRow($rows[0]);
    }

    /**
     * Find the first record matching criteria
     */
    public static function findFirst(array $criteria = [], array $fields = [], array $orderBy = []) {
        $parameters = ['limit' => 1];
        if (!empty($orderBy)) {
            $parameters['orderBy'] = $orderBy;
        }
        $rows = static::findRaw($criteria, $fields, $parameters);
        return empty($rows) ? null : static::fromRow($rows[0]);
    }

    /**
     * Find all records
     */
    public static function findAll(array $fields = [], array $orderBy = []): array {
        $parameters = [];
        if (!empty($orderBy)) {
            $parameters['orderBy'] = $orderBy;
        }
        return static::find([], $fields, $parameters);
    }

    /**
     * Find records by criteria and return raw database rows
     */
    public static function findRaw(array $criteria = [], array $fields = [], array $parameters = []): array {
        $dbConnector = ServiceLocator::getDatabaseConnector();
        return $dbConnector->find(static::class, $criteria, $fields, $parameters);
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
     * Create a new instance from a database row
     */
    public static function fromRow(array $row): static {
        $instance = new static(ServiceLocator::getLogger());
        $instance->populateFromRow($row);
        return $instance;
    }

    /**
     * Create multiple instances from database rows
     */
    public static function fromRows(array $rows): array {
        $instances = [];
        foreach ($rows as $row) {
            $instances[] = static::fromRow($row);
        }
        return $instances;
    }

    /**
     * Include core fields metadata automatically
     */
    protected function includeCoreFieldsMetadata(): void {
        // Get core fields metadata service from DI container
        $coreFieldsMetadata = \Gravitycar\Core\ServiceLocator::getCoreFieldsMetadata();

        // Get core fields for this specific model class
        $coreFields = $coreFieldsMetadata->getAllCoreFieldsForModel(static::class);

        // Initialize fields array if it doesn't exist
        if (!isset($this->metadata['fields'])) {
            $this->metadata['fields'] = [];
        }

        // Merge core fields with existing metadata
        // Existing model metadata takes precedence over core fields (allows overrides)
        $this->metadata['fields'] = array_merge($coreFields, $this->metadata['fields']);

        $this->logger->debug('Included core fields metadata', [
            'model_class' => static::class,
            'core_fields_added' => array_keys($coreFields),
            'total_fields' => count($this->metadata['fields'])
        ]);
    }

    /**
     * Get all relationships
     */
    public function getRelationships(): array {
        return $this->relationships;
    }

    /**
     * Get a specific relationship
     */
    public function getRelationship(string $relationshipName): ?RelationshipBase {
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

        foreach ($records as $record) {
            $models[] = $relatedModelClass::fromRow($record);
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

        return $relationship->addRelation($this, $relatedModel, $additionalData);
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

        return $relationship->removeRelation($this, $relatedModel);
    }

    /**
     * Check if a relationship exists with another model
     */
    public function hasRelation(string $relationshipName, ModelBase $relatedModel): bool {
        $relationship = $this->getRelationship($relationshipName);
        if (!$relationship) {
            return false;
        }

        return $relationship->hasRelation($this, $relatedModel);
    }

    /**
     * Get paginated related records
     */
    public function getRelatedPaginated(string $relationshipName, int $page = 1, int $perPage = 20): array {
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
    protected function getRelatedModelClass(RelationshipBase $relationship): string {
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
}
