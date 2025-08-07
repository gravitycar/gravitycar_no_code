<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Metadata\CoreFieldsMetadata;
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

    protected array $relationshipMetadata = [];
    protected ?string $tableName = null;
    protected CoreFieldsMetadata $coreFieldsMetadata;

    public function __construct(array $metadata, Logger $logger, ?CoreFieldsMetadata $coreFieldsMetadata = null) {
        $this->relationshipMetadata = $metadata;
        $this->coreFieldsMetadata = $coreFieldsMetadata ?? new CoreFieldsMetadata($logger);
        parent::__construct($logger);
    }

    /**
     * Ingest metadata for this relationship - called by parent constructor
     */
    protected function ingestMetadata(): void {
        try {
            // Validate the metadata that was passed to constructor
            $this->validateRelationshipMetadata($this->relationshipMetadata);

            // Generate table name based on relationship type and models
            $this->generateTableName();

            // Get core fields from CoreFieldsMetadata
            $this->ingestCoreFields();

            // Generate dynamic fields for the relationship
            $this->generateDynamicFields();

            $this->logger->info('Relationship metadata ingested successfully', [
                'relationship_name' => $this->getName(),
                'relationship_type' => $this->getType(),
                'table_name' => $this->getTableName()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to ingest relationship metadata', [
                'class_name' => get_class($this),
                'metadata' => $this->relationshipMetadata,
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
     * Build metadata file path for relationship
     */
    protected function buildMetadataFilePath(string $relationshipName): string {
        return __DIR__ . "/{$relationshipName}/{$relationshipName}_metadata.php";
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
        $type = $this->relationshipMetadata['type'];

        switch ($type) {
            case 'OneToOne':
                $modelA = strtolower($this->relationshipMetadata['modelA']);
                $modelB = strtolower($this->relationshipMetadata['modelB']);
                $this->tableName = "rel_1_{$modelA}_1_{$modelB}";
                break;

            case 'OneToMany':
                $modelOne = strtolower($this->relationshipMetadata['modelOne']);
                $modelMany = strtolower($this->relationshipMetadata['modelMany']);
                $this->tableName = "rel_1_{$modelOne}_M_{$modelMany}";
                break;

            case 'ManyToMany':
                $modelA = strtolower($this->relationshipMetadata['modelA']);
                $modelB = strtolower($this->relationshipMetadata['modelB']);
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
        $type = $this->relationshipMetadata['type'];

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
        $modelA = $this->relationshipMetadata['modelA'];
        $modelB = $this->relationshipMetadata['modelB'];

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
        $modelOne = $this->relationshipMetadata['modelOne'];
        $modelMany = $this->relationshipMetadata['modelMany'];

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
        $modelA = $this->relationshipMetadata['modelA'];
        $modelB = $this->relationshipMetadata['modelB'];

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
        $additionalFields = $this->relationshipMetadata['additionalFields'] ?? [];

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
     * Get the table name for this relationship
     */
    public function getTableName(): string {
        return $this->tableName;
    }

    /**
     * Get relationship metadata
     */
    public function getRelationshipMetadata(): array {
        return $this->relationshipMetadata;
    }

    /**
     * Get relationship type
     */
    public function getType(): string {
        return $this->relationshipMetadata['type'];
    }

    /**
     * Get relationship name
     */
    public function getName(): string {
        return $this->relationshipMetadata['name'] ?? 'unknown';
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
                $modelOne = strtolower($this->relationshipMetadata['modelOne']);
                $modelMany = strtolower($this->relationshipMetadata['modelMany']);

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
     */
    abstract public function getRelatedRecords(ModelBase $model): array;

    /**
     * Add a new relationship record
     */
    abstract public function addRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData = []): bool;

    /**
     * Remove a relationship record
     */
    abstract public function removeRelation(ModelBase $modelA, ModelBase $modelB): bool;

    /**
     * Check if a relationship exists between two models
     */
    abstract public function hasRelation(ModelBase $modelA, ModelBase $modelB): bool;

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
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                WHERE {$this->getModelIdField($model)} = ? 
                AND deleted_at IS NULL";

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([$model->get('id')]);

        return $stmt->fetchOne() > 0;
    }

    /**
     * Count active (non-soft-deleted) related records
     */
    protected function getActiveRelatedCount(ModelBase $model): int {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                WHERE {$this->getModelIdField($model)} = ? 
                AND deleted_at IS NULL";

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([$model->get('id')]);

        return (int) $stmt->fetchOne();
    }

    /**
     * Bulk soft delete relationship records - optimized for performance
     */
    protected function bulkSoftDeleteRelationships(ModelBase $model): bool {
        try {
            $conn = $this->getDatabaseConnector()->getConnection();
            $currentUser = $this->getCurrentUserId();
            $currentDateTime = date('Y-m-d H:i:s');

            $sql = "UPDATE {$this->getTableName()} 
                    SET deleted_at = ?, deleted_by = ? 
                    WHERE {$this->getModelIdField($model)} = ? 
                    AND deleted_at IS NULL";

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeStatement([
                $currentDateTime,
                $currentUser,
                $model->get('id')
            ]);

            $this->logger->info('Bulk soft delete of relationship records completed', [
                'relationship_table' => $this->getTableName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'records_updated' => $result
            ]);

            return true;

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
            $conn = $this->getDatabaseConnector()->getConnection();
            $currentUser = $this->getCurrentUserId();
            $currentDateTime = date('Y-m-d H:i:s');

            if ($modelB === null) {
                // Soft delete all relationships for modelA (bulk operation)
                return $this->bulkSoftDeleteRelationships($modelA);
            } else {
                // Soft delete specific relationship
                $sql = "UPDATE {$this->getTableName()} 
                        SET deleted_at = ?, deleted_by = ? 
                        WHERE {$this->getModelIdField($modelA)} = ? 
                        AND {$this->getModelIdField($modelB)} = ? 
                        AND deleted_at IS NULL";

                $stmt = $conn->prepare($sql);
                $result = $stmt->executeStatement([
                    $currentDateTime,
                    $currentUser,
                    $modelA->get('id'),
                    $modelB->get('id')
                ]);

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
            $conn = $this->getDatabaseConnector()->getConnection();

            if ($modelB === null) {
                // Restore all relationships for modelA (bulk operation)
                $sql = "UPDATE {$this->getTableName()} 
                        SET deleted_at = NULL, deleted_by = NULL 
                        WHERE {$this->getModelIdField($modelA)} = ? 
                        AND deleted_at IS NOT NULL";

                $stmt = $conn->prepare($sql);
                $result = $stmt->executeStatement([$modelA->get('id')]);

            } else {
                // Restore specific relationship
                $sql = "UPDATE {$this->getTableName()} 
                        SET deleted_at = NULL, deleted_by = NULL 
                        WHERE {$this->getModelIdField($modelA)} = ? 
                        AND {$this->getModelIdField($modelB)} = ? 
                        AND deleted_at IS NOT NULL";

                $stmt = $conn->prepare($sql);
                $result = $stmt->executeStatement([
                    $modelA->get('id'),
                    $modelB->get('id')
                ]);
            }

            $this->logger->info('Relationship restore completed', [
                'relationship_table' => $this->getTableName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => $modelB ? get_class($modelB) : null,
                'model_b_id' => $modelB?->get('id'),
                'records_restored' => $result
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to restore relationship', [
                'relationship_table' => $this->getTableName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => $modelB ? get_class($modelB) : null,
                'model_b_id' => $modelB?->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Relationship restore failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Get all active (non-soft-deleted) relationship records for a model
     */
    public function getActiveRelationshipRecords(ModelBase $model): array {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE {$this->getModelIdField($model)} = ? 
                AND deleted_at IS NULL";

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([$model->get('id')]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all soft-deleted relationship records for a model
     */
    public function getDeletedRelationshipRecords(ModelBase $model): array {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE {$this->getModelIdField($model)} = ? 
                AND deleted_at IS NOT NULL";

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([$model->get('id')]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
