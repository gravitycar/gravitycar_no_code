<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;

/**
 * Handles OneToOne relationships between models.
 * Each record in modelA relates to exactly one record in modelB and vice versa.
 */
class OneToOneRelationship extends RelationshipBase {

    /**
     * Get all related records for a given model instance
     * For OneToOne, this returns at most one record
     */
    public function getRelatedRecords(ModelBase $model): array {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE {$this->getModelIdField($model)} = ? 
                AND deleted_at IS NULL 
                LIMIT 1";

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([$model->get('id')]);

        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug('OneToOne getRelatedRecords completed', [
            'model_class' => get_class($model),
            'model_id' => $model->get('id'),
            'relationship' => $this->getName(),
            'records_found' => count($records)
        ]);

        return $records;
    }

    /**
     * Get the single related record (convenience method)
     */
    public function getRelatedRecord(ModelBase $model): ?array {
        $records = $this->getRelatedRecords($model);
        return empty($records) ? null : $records[0];
    }

    /**
     * Add a new relationship record
     * For OneToOne, this replaces any existing relationship
     */
    public function addRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData = []): bool {
        try {
            // First, remove any existing relationships for both models (OneToOne constraint)
            $this->removeAllRelations($modelA);
            $this->removeAllRelations($modelB);

            $conn = $this->getDatabaseConnector()->getConnection();

            // Prepare data for insertion
            $data = array_merge([
                $this->getModelIdField($modelA) => $modelA->get('id'),
                $this->getModelIdField($modelB) => $modelB->get('id'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], $additionalData);

            // Build INSERT query
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');

            $sql = "INSERT INTO {$this->getTableName()} 
                    (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeStatement(array_values($data));

            $this->logger->info('OneToOne relationship added', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'additional_data' => $additionalData
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to add OneToOne relationship', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to add OneToOne relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Set or replace the relationship (removes existing if present)
     */
    public function setRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData = []): bool {
        // For OneToOne, setRelation is the same as addRelation since addRelation already handles replacement
        return $this->addRelation($modelA, $modelB, $additionalData);
    }

    /**
     * Remove a specific relationship record
     */
    public function removeRelation(ModelBase $modelA, ModelBase $modelB): bool {
        try {
            $conn = $this->getDatabaseConnector()->getConnection();

            $sql = "DELETE FROM {$this->getTableName()} 
                    WHERE {$this->getModelIdField($modelA)} = ? 
                    AND {$this->getModelIdField($modelB)} = ?";

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeStatement([
                $modelA->get('id'),
                $modelB->get('id')
            ]);

            $this->logger->info('OneToOne relationship removed', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id')
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to remove OneToOne relationship', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to remove OneToOne relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Remove all relationships for a model
     */
    public function removeAllRelations(ModelBase $model): bool {
        try {
            $conn = $this->getDatabaseConnector()->getConnection();

            $sql = "DELETE FROM {$this->getTableName()} 
                    WHERE {$this->getModelIdField($model)} = ?";

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeStatement([$model->get('id')]);

            $this->logger->info('All OneToOne relationships removed for model', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'relationships_removed' => $result
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to remove all OneToOne relationships', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to remove all OneToOne relationships: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Check if a relationship exists between two models
     */
    public function hasRelation(ModelBase $modelA, ModelBase $modelB): bool {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                WHERE {$this->getModelIdField($modelA)} = ? 
                AND {$this->getModelIdField($modelB)} = ? 
                AND deleted_at IS NULL";

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $modelA->get('id'),
            $modelB->get('id')
        ]);

        return $stmt->fetchOne() > 0;
    }

    /**
     * Update additional fields in a relationship
     */
    public function updateRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData): bool {
        try {
            if (empty($additionalData)) {
                return true; // Nothing to update
            }

            $conn = $this->getDatabaseConnector()->getConnection();

            // Add updated_at timestamp
            $additionalData['updated_at'] = date('Y-m-d H:i:s');

            // Build UPDATE query
            $setClauses = [];
            foreach (array_keys($additionalData) as $field) {
                $setClauses[] = "{$field} = ?";
            }

            $sql = "UPDATE {$this->getTableName()} 
                    SET " . implode(', ', $setClauses) . " 
                    WHERE {$this->getModelIdField($modelA)} = ? 
                    AND {$this->getModelIdField($modelB)} = ?";

            $params = array_merge(
                array_values($additionalData),
                [$modelA->get('id'), $modelB->get('id')]
            );

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeStatement($params);

            $this->logger->info('OneToOne relationship updated', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'updated_data' => $additionalData
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update OneToOne relationship', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to update OneToOne relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Handle cascade operations when a model is deleted
     */
    public function handleModelDeletion(ModelBase $deletedModel, string $cascadeAction): bool {
        switch ($cascadeAction) {
            case self::CASCADE_RESTRICT:
                if ($this->hasActiveRelation($deletedModel)) {
                    throw new GCException(
                        'Cannot delete model with existing OneToOne relationship',
                        [
                            'model' => get_class($deletedModel),
                            'id' => $deletedModel->get('id'),
                            'relationship' => $this->getName()
                        ]
                    );
                }
                return true;

            case self::CASCADE_CASCADE:
                // Get the related model and delete it
                $relatedRecord = $this->getRelatedRecord($deletedModel);
                if ($relatedRecord) {
                    // Find which model this is and get the actual model instance
                    $relatedModel = $this->getRelatedModelInstance($deletedModel, $relatedRecord);
                    if ($relatedModel) {
                        return $relatedModel->delete(); // This will cascade further if needed
                    }
                }
                return true;

            case self::CASCADE_SOFT_DELETE:
                // Soft delete the relationship record itself
                return $this->bulkSoftDeleteRelationships($deletedModel);

            default:
                throw new GCException("Unknown cascade action: {$cascadeAction}");
        }
    }

    /**
     * Get the related model instance from a relationship record
     */
    protected function getRelatedModelInstance(ModelBase $sourceModel, array $relationshipRecord): ?ModelBase {
        try {
            $sourceModelName = strtolower(basename(str_replace('\\', '/', get_class($sourceModel))));

            // Determine which is the related model
            $modelA = strtolower($this->relationshipMetadata['modelA']);
            $modelB = strtolower($this->relationshipMetadata['modelB']);

            if ($sourceModelName === $modelA) {
                // Source is modelA, so related is modelB
                $relatedModelClass = "Gravitycar\\Models\\{$this->relationshipMetadata['modelB']}";
                $relatedId = $relationshipRecord[$modelB . '_id'];
            } else {
                // Source is modelB, so related is modelA
                $relatedModelClass = "Gravitycar\\Models\\{$this->relationshipMetadata['modelA']}";
                $relatedId = $relationshipRecord[$modelA . '_id'];
            }

            // Create and load the related model
            $relatedModel = new $relatedModelClass($this->logger);
            return $relatedModel->findById($relatedId);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get related model instance', [
                'source_model' => get_class($sourceModel),
                'relationship_record' => $relationshipRecord,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
