<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Metadata\MetadataEngine;
use Monolog\Logger;

/**
 * Abstract base class for all relationship types in the Gravitycar framework.
 * Extends ModelBase to inherit common model functionality including core fields.
 */
abstract class RelationshipBase extends ModelBase {

    // Cascade delete options
    const CASCADE_RESTRICT = 'restrict';
    const CASCADE_CASCADE = 'cascade';
    const CASCADE_SOFT_DELETE = 'softDelete';
    const CASCADE_SET_DEFAULT = 'setDefault';

    protected ?string $tableName = null;
    protected CoreFieldsMetadata $coreFieldsMetadata;
    protected MetadataEngine $metadataEngine;
    protected bool $metadataFromEngine = false;

    /**
     * Constructor - uses ServiceLocator for dependencies
     */
    public function __construct() {
        $this->logger = ServiceLocator::getLogger();
        $this->coreFieldsMetadata = ServiceLocator::getCoreFieldsMetadata();
        $this->metadataEngine = ServiceLocator::getMetadataEngine();
        $this->metadataFromEngine = true; // Always use MetadataEngine now
        
        parent::__construct();
    }

    /**
     * Override loadMetadata to load relationship metadata instead of model metadata
     */
    protected function loadMetadata(): void {
        if ($this->metadataFromEngine) {
            // Load relationship metadata using MetadataEngine
            $relationshipName = $this->getRelationshipNameFromClass();
            $this->metadata = $this->metadataEngine->getRelationshipMetadata($relationshipName);
        }
        // For backward compatibility, metadata is already set in constructor for array-based approach
        
        $this->validateMetadata($this->metadata);
        $this->metadataLoaded = true;
    }

    /**
     * Override validateMetadata to validate relationship metadata structure
     */
    protected function validateMetadata(array $metadata): void {
        $this->validateRelationshipMetadata($metadata);
    }

    /**
     * Ingest metadata for this relationship - called by parent constructor
     */
    protected function ingestMetadata(): void {
        try {
            // Metadata is already loaded during construction, just validate and process
            $this->validateRelationshipMetadata($this->metadata);

            // Generate table name based on relationship type and models
            $this->generateTableName();

            // Get core fields from CoreFieldsMetadata (already included in MetadataEngine for new pattern)
            if (!$this->metadataFromEngine) {
                $this->ingestCoreFields();
            }

            // Generate dynamic fields for the relationship
            $this->generateDynamicFields();

            $this->logger->info('Relationship metadata ingested successfully', [
                'relationship_name' => $this->getName(),
                'metadata_source' => $this->metadataFromEngine ? 'MetadataEngine' : 'Direct',
                'metadata' => $this->metadata,
                'table_name' => $this->tableName,
                'total_fields' => count($this->fields)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to ingest relationship metadata', [
                'class_name' => get_class($this),
                'metadata' => $this->metadata,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get relationship name from class name
     */
    protected function getRelationshipNameFromClass(): string {
        $className = get_class($this);
        $baseName = basename(str_replace('\\', '/', $className));

        // Remove "Relationship" suffix if present
        if (str_ends_with($baseName, 'Relationship')) {
            $baseName = substr($baseName, 0, -12);
        }

        return strtolower($baseName);
    }

    /**
     * Build metadata file path for relationship (updated to use MetadataEngine)
     */
    protected function buildMetadataFilePath(string $relationshipName): string {
        return $this->metadataEngine->buildRelationshipMetadataPath($relationshipName);
    }

    /**
     * Ingest core fields from CoreFieldsMetadata
     */
    protected function ingestCoreFields(): void {
        try {
            $coreFields = $this->coreFieldsMetadata->getStandardCoreFields();

            foreach ($coreFields as $fieldName => $fieldMetadata) {
                $this->addFieldFromMetadata($fieldName, $fieldMetadata);
            }

            $this->logger->debug('Core fields ingested for relationship', [
                'relationship_name' => $this->getName(),
                'core_fields_count' => count($coreFields)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to ingest core fields for relationship', [
                'relationship_name' => $this->getName(),
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to ingest core fields: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Validate essential relationship metadata
     */
    protected function validateRelationshipMetadata(array $metadata): void {
        $requiredFields = ['name', 'type'];

        foreach ($requiredFields as $field) {
            if (!isset($metadata[$field]) || empty($metadata[$field])) {
                throw new GCException("Required field '{$field}' missing from relationship metadata", [
                    'metadata' => $metadata,
                    'required_fields' => $requiredFields
                ]);
            }
        }

        // Type-specific required fields
        $type = $metadata['type'];
        switch ($type) {
            case 'OneToOne':
                if (!isset($metadata['modelA']) || !isset($metadata['modelB'])) {
                    throw new GCException("OneToOne relationships require 'modelA' and 'modelB' fields", [
                        'metadata' => $metadata
                    ]);
                }
                break;

            case 'OneToMany':
                if (!isset($metadata['modelOne']) || !isset($metadata['modelMany'])) {
                    throw new GCException("OneToMany relationships require 'modelOne' and 'modelMany' fields", [
                        'metadata' => $metadata
                    ]);
                }
                break;

            case 'ManyToMany':
                if (!isset($metadata['modelA']) || !isset($metadata['modelB'])) {
                    throw new GCException("ManyToMany relationships require 'modelA' and 'modelB' fields", [
                        'metadata' => $metadata
                    ]);
                }
                break;

            default:
                throw new GCException("Unknown relationship type: {$type}", [
                    'type' => $type,
                    'supported_types' => ['OneToOne', 'OneToMany', 'ManyToMany']
                ]);
        }
    }

    /**
     * Generate table name based on relationship type and involved models
     */
    protected function generateTableName(): void {
        $type = $this->metadata['type'];

        switch ($type) {
            case 'OneToOne':
                $modelA = strtolower($this->metadata['modelA']);
                $modelB = strtolower($this->metadata['modelB']);
                $this->tableName = "rel_1_{$modelA}_1_{$modelB}";
                break;

            case 'OneToMany':
                $modelOne = strtolower($this->metadata['modelOne']);
                $modelMany = strtolower($this->metadata['modelMany']);
                $this->tableName = "rel_1_{$modelOne}_M_{$modelMany}";
                break;

            case 'ManyToMany':
                $modelA = strtolower($this->metadata['modelA']);
                $modelB = strtolower($this->metadata['modelB']);
                $this->tableName = "rel_N_{$modelA}_M_{$modelB}";
                break;

            default:
                throw new GCException("Unknown relationship type: {$type}");
        }

        // Ensure table name doesn't exceed database limits
        if (strlen($this->tableName) > 64) {
            $this->tableName = $this->truncateTableName($this->tableName);
        }
    }

    /**
     * Truncate table name if it exceeds database limits
     */
    protected function truncateTableName(string $tableName): string {
        // Simple truncation - could be made more sophisticated
        return substr($tableName, 0, 64);
    }

    /**
     * Generate dynamic fields for the relationship based on involved models
     */
    protected function generateDynamicFields(): void {
        $type = $this->metadata['type'];

        switch ($type) {
            case 'OneToOne':
                $this->generateOneToOneFields();
                break;

            case 'OneToMany':
                $this->generateOneToManyFields();
                break;

            case 'ManyToMany':
                $this->generateManyToManyFields();
                break;
        }

        // Add any additional fields specified in metadata
        $this->generateAdditionalFields();
    }

    /**
     * Generate fields for OneToOne relationships
     */
    protected function generateOneToOneFields(): void {
        $modelA = $this->metadata['modelA'];
        $modelB = $this->metadata['modelB'];

        // Generate ID field for model A
        $fieldAName = strtolower($modelA) . '_id';
        $this->addDynamicField($fieldAName, [
            'type' => 'IDField',
            'label' => "{$modelA} ID",
            'required' => true,
            'relatedModel' => $modelA,
            'relatedField' => 'id',
            'validationRules' => [
                'Unique' => true,
                'ForeignKeyExists' => true
            ]
        ]);

        // Generate ID field for model B
        $fieldBName = strtolower($modelB) . '_id';
        $this->addDynamicField($fieldBName, [
            'type' => 'IDField',
            'label' => "{$modelB} ID",
            'required' => true,
            'relatedModel' => $modelB,
            'relatedField' => 'id',
            'validationRules' => [
                'Unique' => true,
                'ForeignKeyExists' => true
            ]
        ]);
    }

    /**
     * Generate fields for OneToMany relationships
     */
    protected function generateOneToManyFields(): void {
        $modelOne = $this->metadata['modelOne'];
        $modelMany = $this->metadata['modelMany'];

        // Generate ID field for "one" model
        $fieldOneName = 'one_' . strtolower($modelOne) . '_id';
        $this->addDynamicField($fieldOneName, [
            'type' => 'IDField',
            'label' => "{$modelOne} ID",
            'required' => true,
            'relatedModel' => $modelOne,
            'relatedField' => 'id',
            'validationRules' => [
                'ForeignKeyExists' => true
            ]
        ]);

        // Generate ID field for "many" model
        $fieldManyName = 'many_' . strtolower($modelMany) . '_id';
        $this->addDynamicField($fieldManyName, [
            'type' => 'IDField',
            'label' => "{$modelMany} ID",
            'required' => true,
            'relatedModel' => $modelMany,
            'relatedField' => 'id',
            'validationRules' => [
                'ForeignKeyExists' => true
            ]
        ]);
    }

    /**
     * Generate fields for ManyToMany relationships
     */
    protected function generateManyToManyFields(): void {
        $modelA = $this->metadata['modelA'];
        $modelB = $this->metadata['modelB'];

        // Generate ID field for model A
        $fieldAName = strtolower($modelA) . '_id';
        $this->addDynamicField($fieldAName, [
            'type' => 'IDField',
            'label' => "{$modelA} ID",
            'required' => true,
            'relatedModel' => $modelA,
            'relatedField' => 'id',
            'validationRules' => [
                'ForeignKeyExists' => true
            ]
        ]);

        // Generate ID field for model B
        $fieldBName = strtolower($modelB) . '_id';
        $this->addDynamicField($fieldBName, [
            'type' => 'IDField',
            'label' => "{$modelB} ID",
            'required' => true,
            'relatedModel' => $modelB,
            'relatedField' => 'id',
            'validationRules' => [
                'ForeignKeyExists' => true
            ]
        ]);
    }

    /**
     * Generate additional fields specified in relationship metadata
     */
    protected function generateAdditionalFields(): void {
        $additionalFields = $this->metadata['additionalFields'] ?? [];

        foreach ($additionalFields as $fieldName => $fieldMetadata) {
            $this->addDynamicField($fieldName, $fieldMetadata);
        }
    }

    /**
     * Add a dynamic field to the relationship
     */
    protected function addDynamicField(string $fieldName, array $fieldMetadata): void {
        $this->addFieldFromMetadata($fieldName, $fieldMetadata);
    }

    /**
     * Add field metadata to the relationship's field collection
     * This method handles adding field definitions to the relationship's metadata structure
     */
    protected function addFieldFromMetadata(string $fieldName, array $fieldMetadata): void {
        // Initialize fields array in relationship metadata if it doesn't exist
        if (!isset($this->metadata['fields'])) {
            $this->metadata['fields'] = [];
        }

        // Ensure the field name is set in the metadata
        $fieldMetadata['name'] = $fieldName;

        // Add the field to the relationship's field collection
        $this->metadata['fields'][$fieldName] = $fieldMetadata;

        // Also initialize in the inherited ModelBase metadata structure
        if (!isset($this->metadata['fields'])) {
            $this->metadata['fields'] = [];
        }
        $this->metadata['fields'][$fieldName] = $fieldMetadata;

        $this->logger->debug('Field added to relationship metadata', [
            'relationship_name' => $this->getName(),
            'field_name' => $fieldName,
            'field_type' => $fieldMetadata['type'] ?? 'unknown'
        ]);
    }

    /**
     * Get the table name for this relationship
     */
    public function getTableName(): string {
        return $this->tableName;
    }

    /**
     * Get relationship metadata
     */
    public function getRelationshipMetadata(): array {
        return $this->metadata;
    }

    /**
     * Get relationship type
     */
    public function getType(): string {
        return $this->metadata['type'];
    }

    /**
     * Get relationship name
     */
    public function getName(): string {
        return $this->metadata['name'] ?? 'unknown';
    }

    /**
     * Get currently logged-in user ID for deleted_by field
     */
    protected function getCurrentUserId(): ?string {
        // This would integrate with your authentication system
        // For now, return null or a default system user ID
        return ServiceLocator::getCurrentUser()?->get('id') ?? 'system';
    }

    /**
     * Get database connector
     */
    protected function getDatabaseConnector(): DatabaseConnector {
        return ServiceLocator::getDatabaseConnector();
    }

    /**
     * Get the field name for model ID in this relationship table
     */
    protected function getModelIdField(ModelBase $model): string {
        $modelClass = get_class($model);
        $modelName = strtolower(basename(str_replace('\\', '/', $modelClass)));

        $type = $this->getType();
        switch ($type) {
            case 'OneToOne':
            case 'ManyToMany':
                return $modelName . '_id';

            case 'OneToMany':
                // Determine if this is the "one" or "many" model
                $modelOne = strtolower($this->metadata['modelOne']);
                $modelMany = strtolower($this->metadata['modelMany']);

                if ($modelName === $modelOne) {
                    return 'one_' . $modelName . '_id';
                } else {
                    return 'many_' . $modelName . '_id';
                }

            default:
                throw new GCException("Unknown relationship type: {$type}");
        }
    }

    // Abstract methods that must be implemented by specific relationship types

    /**
     * Get all related records for a given model instance
     * This implementation works for all relationship types
     */
    public function getRelatedRecords(ModelBase $model): array {
        $modelIdFieldName = $this->getModelIdField($model);
        
        // Build criteria for active (non-deleted) relationships
        $criteria = [
            $modelIdFieldName => $model->get('id'),
            'deleted_at' => null  // Only find non-deleted relationships
        ];

        // Set parameters based on relationship type
        $parameters = [];
        if ($this->getType() === 'OneToOne') {
            $parameters['limit'] = 1; // OneToOne should return at most one record
        }

        // Use DatabaseConnector->find() for consistent data access
        $dbConnector = $this->getDatabaseConnector();
        $records = $dbConnector->find(static::class, $criteria, [], $parameters);

        $this->logger->debug('{type} getRelatedRecords completed', [
            'relationship_type' => $this->getType(),
            'model_class' => get_class($model),
            'model_id' => $model->get('id'),
            'relationship' => $this->getName(),
            'records_found' => count($records)
        ]);

        return $records;
    }

    /**
     * Add a new relationship record
     */
    public function add(ModelBase $modelA, ModelBase $modelB, array $additionalData = []): bool {
        try {
            // For ManyToMany and OneToMany, check if relationship already exists
            if ($this->has($modelA, $modelB)) {
                $this->logger->warning('Relationship already exists', [
                    'relationship' => $this->getName(),
                    'relationship_type' => get_class($this),
                    'model_a_class' => get_class($modelA),
                    'model_a_id' => $modelA->get('id'),
                    'model_b_class' => get_class($modelB),
                    'model_b_id' => $modelB->get('id')
                ]);
                return false;
            }

            // Set the model ID fields on this relationship instance
            $this->set($this->getModelIdField($modelA), $modelA->get('id'));
            $this->set($this->getModelIdField($modelB), $modelB->get('id'));
            
            // Generate and set the ID field using existing UUID generation
            if ($this->hasField('id') && !$this->get('id')) {
                $this->set('id', $this->generateUuid());
            }
            
            // Set audit fields
            $currentTimestamp = date('Y-m-d H:i:s');
            $currentUserId = $this->getCurrentUserId();
            
            if ($this->hasField('created_at')) {
                $this->set('created_at', $currentTimestamp);
            }
            if ($this->hasField('updated_at')) {
                $this->set('updated_at', $currentTimestamp);
            }
            if ($this->hasField('created_by') && $currentUserId) {
                $this->set('created_by', $currentUserId);
            }
            if ($this->hasField('updated_by') && $currentUserId) {
                $this->set('updated_by', $currentUserId);
            }
            
            // Set any additional data provided
            foreach ($additionalData as $fieldName => $value) {
                if ($this->hasField($fieldName)) {
                    $this->set($fieldName, $value);
                }
            }

            // Use DatabaseConnector->create() to handle the insertion
            $dbConnector = $this->getDatabaseConnector();
            $success = $dbConnector->create($this);

            if ($success) {
                $this->logger->info('Relationship added', [
                    'relationship' => $this->getName(),
                    'relationship_type' => get_class($this),
                    'model_a_class' => get_class($modelA),
                    'model_a_id' => $modelA->get('id'),
                    'model_b_class' => get_class($modelB),
                    'model_b_id' => $modelB->get('id'),
                    'additional_data' => $additionalData
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Failed to add relationship', [
                'relationship' => $this->getName(),
                'relationship_type' => get_class($this),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to add relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Soft delete existing relationships for a model (used by OneToOne)
     */
    protected function softDeleteExistingRelationships(ModelBase $model): void {
        try {
            $currentUserId = $this->getCurrentUserId();
            $fieldName = $this->getModelIdField($model);
            $fieldValue = $model->get('id');

            // Use DatabaseConnector for bulk soft delete
            $this->getDatabaseConnector()->bulkSoftDeleteByFieldValue(
                $this,
                $fieldName,
                $fieldValue,
                $currentUserId
            );

            $this->logger->debug('Soft deleted existing relationships for model', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'deleted_by' => $currentUserId
            ]);

        } catch (\Exception $e) {
            $this->logger->warning('Failed to soft delete existing relationships', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'error' => $e->getMessage()
            ]);
            // Don't throw here, let the main add operation continue
        }
    }

    /**
     * Remove a specific relationship record (soft delete)
     */
    public function remove(ModelBase $modelA, ModelBase $modelB): bool {
        try {
            // First, find the existing relationship record
            $criteria = [
                $this->getModelIdField($modelA) => $modelA->get('id'),
                $this->getModelIdField($modelB) => $modelB->get('id'),
                'deleted_at' => null  // Only find non-deleted relationships
            ];

            $dbConnector = $this->getDatabaseConnector();
            $results = $dbConnector->find(static::class, $criteria, [], ['limit' => 1]);

            if (empty($results)) {
                $this->logger->warning('Relationship not found for removal', [
                    'relationship_type' => $this->getType(),
                    'model_a_class' => get_class($modelA),
                    'model_a_id' => $modelA->get('id'),
                    'model_b_class' => get_class($modelB),
                    'model_b_id' => $modelB->get('id')
                ]);
                return false;
            }

            // Create a relationship instance from the found record and populate it
            $relationshipInstance = new static($this->metadata, $this->logger, $this->coreFieldsMetadata);
            $relationshipInstance->populateFromRow($results[0]);

            // Set soft delete fields
            $currentUserId = $this->getCurrentUserId();
            $currentTimestamp = date('Y-m-d H:i:s');

            if ($relationshipInstance->hasField('deleted_at')) {
                $relationshipInstance->set('deleted_at', $currentTimestamp);
            }
            if ($relationshipInstance->hasField('deleted_by') && $currentUserId) {
                $relationshipInstance->set('deleted_by', $currentUserId);
            }

            // Update the relationship record using DatabaseConnector
            $success = $dbConnector->update($relationshipInstance);

            if ($success) {
                $this->logger->info('{type} relationship soft deleted successfully', [
                    'relationship_type' => $this->getType(),
                    'relationship_name' => $this->getName(),
                    'model_a_class' => get_class($modelA),
                    'model_a_id' => $modelA->get('id'),
                    'model_b_class' => get_class($modelB),
                    'model_b_id' => $modelB->get('id'),
                    'deleted_at' => $currentTimestamp,
                    'deleted_by' => $currentUserId
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Failed to remove {type} relationship', [
                'relationship_type' => $this->getType(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to remove relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Check if a relationship exists between two models
     */
    public function has(ModelBase $modelA, ModelBase $modelB): bool {
        try {
            // Build criteria to find a record in the relationship table
            $criteria = [
                $this->getModelIdField($modelA) => $modelA->get('id'),
                $this->getModelIdField($modelB) => $modelB->get('id'),
                'deleted_at' => null  // Only find non-deleted relationships
            ];

            // Use DatabaseConnector to find matching records
            $dbConnector = $this->getDatabaseConnector();
            $results = $dbConnector->find(static::class, $criteria, [], ['limit' => 1]);

            // Return true if any record is found
            return !empty($results);

        } catch (\Exception $e) {
            $this->logger->error('Failed to check {type} relationship existence', [
                'relationship_type' => $this->getType(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to check relationship existence: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Update additional fields in a relationship
     */
    abstract public function updateRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData): bool;

    /**
     * Handle cascade operations when a model is deleted
     */
    abstract public function handleModelDeletion(ModelBase $deletedModel, string $cascadeAction): bool;

    // Common utility methods for all relationship types

    /**
     * Check if model has active (non-soft-deleted) relationships
     */
    protected function hasActiveRelation(ModelBase $model): bool {
        $modelIdFieldName = $this->getModelIdField($model);
        
        // Use DatabaseConnector->getCount() for efficient checking
        $dbConnector = $this->getDatabaseConnector();
        $count = $dbConnector->getCount($this, $modelIdFieldName, $model->get('id'), false);
        
        return $count > 0;
    }

    /**
     * Count active (non-soft-deleted) related records
     */
    protected function getActiveRelatedCount(ModelBase $model): int {
        $modelIdFieldName = $this->getModelIdField($model);
        
        // Use DatabaseConnector->getCount() for efficient counting
        $dbConnector = $this->getDatabaseConnector();
        return $dbConnector->getCount($this, $modelIdFieldName, $model->get('id'), false);
    }

    /**
     * Bulk soft delete relationship records - optimized for performance
     */
    protected function bulkSoftDeleteRelationships(ModelBase $model): bool {
        try {
            $currentUser = $this->getCurrentUserId();
            $fieldName = $this->getModelIdField($model);
            $fieldValue = $model->get('id');

            $result = $this->getDatabaseConnector()->bulkSoftDeleteByFieldValue(
                $this,
                $fieldName,
                $fieldValue,
                $currentUser
            );

            $this->logger->info('Bulk soft delete of relationship records completed', [
                'relationship_table' => $this->getTableName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'records_updated' => $result
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to bulk soft delete relationship records', [
                'relationship_table' => $this->getTableName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Bulk soft delete of relationships failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Soft delete specific relationship
     */
    public function softDeleteRelationship(ModelBase $modelA, ?ModelBase $modelB = null): bool {
        try {
            $currentUser = $this->getCurrentUserId();

            if ($modelB === null) {
                // Soft delete all relationships for modelA (bulk operation)
                return $this->bulkSoftDeleteRelationships($modelA);
            } else {
                // Soft delete specific relationship using criteria
                $criteria = [
                    $this->getModelIdField($modelA) => $modelA->get('id'),
                    $this->getModelIdField($modelB) => $modelB->get('id')
                ];

                $result = $this->getDatabaseConnector()->bulkSoftDeleteByCriteria(
                    $this,
                    $criteria,
                    $currentUser
                );

                $this->logger->info('Relationship soft delete completed', [
                    'relationship_table' => $this->getTableName(),
                    'model_a_class' => get_class($modelA),
                    'model_a_id' => $modelA->get('id'),
                    'model_b_class' => get_class($modelB),
                    'model_b_id' => $modelB->get('id'),
                    'records_updated' => $result,
                    'deleted_by' => $currentUser
                ]);

                return $result > 0;
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to soft delete relationship', [
                'relationship_table' => $this->getTableName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => $modelB ? get_class($modelB) : null,
                'model_b_id' => $modelB?->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Relationship soft delete failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Restore soft-deleted relationship
     */
    public function restoreRelationship(ModelBase $modelA, ?ModelBase $modelB = null): bool {
        try {
            if ($modelB === null) {
                // Restore all relationships for modelA (bulk operation)
                $criteria = [
                    $this->getModelIdField($modelA) => $modelA->get('id')
                ];

                $result = $this->getDatabaseConnector()->bulkRestoreByCriteria($this, $criteria);

            } else {
                // Restore specific relationship
                $criteria = [
                    $this->getModelIdField($modelA) => $modelA->get('id'),
                    $this->getModelIdField($modelB) => $modelB->get('id')
                ];

                $result = $this->getDatabaseConnector()->bulkRestoreByCriteria($this, $criteria);
            }

            $this->logger->info('Relationship restore completed', [
                'relationship_table' => $this->getTableName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => $modelB ? get_class($modelB) : null,
                'model_b_id' => $modelB ? $modelB->get('id') : null,
                'records_restored' => $result
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to restore relationship', [
                'relationship_table' => $this->getTableName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => $modelB ? get_class($modelB) : null,
                'model_b_id' => $modelB ? $modelB->get('id') : null,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to restore relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Get all active (non-soft-deleted) relationship records for a model
     */
    public function getActiveRelationshipRecords(ModelBase $model, array $parameters = []): array {
        $modelIdFieldName = $this->getModelIdField($model);
        
        // Build criteria for active (non-deleted) relationships
        $criteria = [
            $modelIdFieldName => $model->get('id'),
            'deleted_at' => null  // Only find non-deleted relationships
        ];

        // Use DatabaseConnector->find() with optional parameters
        $dbConnector = $this->getDatabaseConnector();
        return $dbConnector->find(static::class, $criteria, [], $parameters);
    }

    /**
     * Get all soft-deleted relationship records for a model
     */
    public function getDeletedRelationshipRecords(ModelBase $model, array $parameters = []): array {
        $modelIdFieldName = $this->getModelIdField($model);
        
        // Build criteria for deleted relationships using the special __NOT_NULL__ marker
        $criteria = [
            $modelIdFieldName => $model->get('id'),
            'deleted_at' => '__NOT_NULL__'  // Special marker for IS NOT NULL condition
        ];

        // Use DatabaseConnector->find() with enhanced criteria support
        $dbConnector = $this->getDatabaseConnector();
        return $dbConnector->find(static::class, $criteria, [], $parameters);
    }

    /**
     * Get paginated related records
     * This method is available to all relationship types
     */
    public function getRelatedPaginated(ModelBase $model, int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;
        $fieldName = $this->getModelIdField($model);
        $fieldValue = $model->get('id');

        // Get total count using DatabaseConnector
        $dbConnector = $this->getDatabaseConnector();
        $total = $dbConnector->getCount($this, $fieldName, $fieldValue, false);

        // Build criteria for paginated records
        $criteria = [
            $fieldName => $fieldValue,
            'deleted_at' => null  // Only find non-deleted relationships
        ];

        // Build parameters for pagination
        $parameters = [
            'limit' => $perPage,
            'offset' => $offset
        ];

        // Get paginated records using DatabaseConnector->find()
        $records = $dbConnector->find(static::class, $criteria, [], $parameters);

        $hasMore = ($offset + $perPage) < $total;

        $result = [
            'records' => $records,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $hasMore,
                'total_pages' => ceil($total / $perPage)
            ]
        ];

        $this->logger->debug('Relationship paginated records retrieved', [
            'model_class' => get_class($model),
            'model_id' => $model->get('id'),
            'relationship' => $this->getName(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'records_returned' => count($records)
        ]);

        return $result;
    }
}
