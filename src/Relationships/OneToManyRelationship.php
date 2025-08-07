<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;

/**
 * Handles OneToMany relationships between models.
 * One record in modelOne relates to many records in modelMany.
 */
class OneToManyRelationship extends RelationshipBase {

    /**
     * Get all related records for a given model instance
     * Returns different results based on whether the model is on the "one" or "many" side
     */
    public function getRelatedRecords(ModelBase $model): array {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE {$this->getModelIdField($model)} = ? 
                AND deleted_at IS NULL";

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([$model->get('id')]);

        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug('OneToMany getRelatedRecords completed', [
            'model_class' => get_class($model),
            'model_id' => $model->get('id'),
            'relationship' => $this->getName(),
            'is_one_model' => $this->isOneModel($model),
            'records_found' => count($records)
        ]);

        return $records;
    }

    /**
     * Get related records from "many" side (returns single "one" record)
     */
    public function getRelatedFromMany(ModelBase $manyModel): ?array {
        if (!$this->isManyModel($manyModel)) {
            throw new GCException('Model is not on the "many" side of this OneToMany relationship', [
                'model_class' => get_class($manyModel),
                'relationship' => $this->getName()
            ]);
        }

        $records = $this->getRelatedRecords($manyModel);
        return empty($records) ? null : $records[0]; // Single "one" record
    }

    /**
     * Get related records from "one" side (returns array of "many" records)
     */
    public function getRelatedFromOne(ModelBase $oneModel): array {
        if (!$this->isOneModel($oneModel)) {
            throw new GCException('Model is not on the "one" side of this OneToMany relationship', [
                'model_class' => get_class($oneModel),
                'relationship' => $this->getName()
            ]);
        }

        return $this->getRelatedRecords($oneModel); // Array of "many" records
    }

    /**
     * Add a new relationship record
     */
    public function addRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData = []): bool {
        try {
            // Determine which model is "one" and which is "many"
            if ($this->isOneModel($modelA) && $this->isManyModel($modelB)) {
                $oneModel = $modelA;
                $manyModel = $modelB;
            } elseif ($this->isOneModel($modelB) && $this->isManyModel($modelA)) {
                $oneModel = $modelB;
                $manyModel = $modelA;
            } else {
                throw new GCException('Invalid model combination for OneToMany relationship', [
                    'model_a_class' => get_class($modelA),
                    'model_b_class' => get_class($modelB),
                    'relationship' => $this->getName()
                ]);
            }

            // Check if relationship already exists
            if ($this->hasRelation($oneModel, $manyModel)) {
                $this->logger->warning('OneToMany relationship already exists', [
                    'relationship' => $this->getName(),
                    'one_model_class' => get_class($oneModel),
                    'one_model_id' => $oneModel->get('id'),
                    'many_model_class' => get_class($manyModel),
                    'many_model_id' => $manyModel->get('id')
                ]);
                return false;
            }

            $conn = $this->getDatabaseConnector()->getConnection();

            // Prepare data for insertion
            $data = array_merge([
                $this->getModelIdField($oneModel) => $oneModel->get('id'),
                $this->getModelIdField($manyModel) => $manyModel->get('id'),
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

            $this->logger->info('OneToMany relationship added', [
                'relationship' => $this->getName(),
                'one_model_class' => get_class($oneModel),
                'one_model_id' => $oneModel->get('id'),
                'many_model_class' => get_class($manyModel),
                'many_model_id' => $manyModel->get('id'),
                'additional_data' => $additionalData
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to add OneToMany relationship', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to add OneToMany relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Add relationship with ordering support
     */
    public function addRelationWithOrder(ModelBase $oneModel, ModelBase $manyModel, int $order = 0): bool {
        return $this->addRelation($oneModel, $manyModel, ['order' => $order]);
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

            $this->logger->info('OneToMany relationship removed', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id')
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to remove OneToMany relationship', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to remove OneToMany relationship: ' . $e->getMessage(), [], 0, $e);
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

            $this->logger->info('OneToMany relationship updated', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'updated_data' => $additionalData
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update OneToMany relationship', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to update OneToMany relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Handle cascade operations when a model is deleted
     */
    public function handleModelDeletion(ModelBase $deletedModel, string $cascadeAction): bool {
        $isOneModel = $this->isOneModel($deletedModel);

        if ($isOneModel) {
            // Deleting from "one" side - affects multiple "many" records
            return $this->handleOneModelDeletion($deletedModel, $cascadeAction);
        } else {
            // Deleting from "many" side - affects single "one" relationship
            return $this->handleManyModelDeletion($deletedModel, $cascadeAction);
        }
    }

    /**
     * Handle deletion of a model on the "one" side
     */
    private function handleOneModelDeletion(ModelBase $oneModel, string $cascadeAction): bool {
        switch ($cascadeAction) {
            case self::CASCADE_RESTRICT:
                if ($this->hasActiveRelationsFromOne($oneModel)) {
                    throw new GCException(
                        'Cannot delete model with existing OneToMany relationships',
                        [
                            'model' => get_class($oneModel),
                            'id' => $oneModel->get('id'),
                            'related_count' => $this->getActiveRelatedCount($oneModel),
                            'relationship' => $this->getName()
                        ]
                    );
                }
                return true;

            case self::CASCADE_CASCADE:
                // Hard delete all related "many" models
                $manyRecords = $this->getRelatedFromOne($oneModel);
                foreach ($manyRecords as $record) {
                    $manyModel = $this->getManyModelFromRecord($record);
                    if ($manyModel && !$manyModel->delete()) {
                        return false;
                    }
                }
                return true;

            case self::CASCADE_SOFT_DELETE:
                // Bulk soft delete all relationship records for this "one" model
                return $this->bulkSoftDeleteRelationships($oneModel);

            default:
                throw new GCException("Unknown cascade action: {$cascadeAction}");
        }
    }

    /**
     * Handle deletion of a model on the "many" side
     */
    private function handleManyModelDeletion(ModelBase $manyModel, string $cascadeAction): bool {
        switch ($cascadeAction) {
            case self::CASCADE_RESTRICT:
                // For "many" side deletion, we typically don't restrict
                return true;

            case self::CASCADE_CASCADE:
                // Just remove the relationship record, don't delete the "one" model
                return $this->removeAllRelations($manyModel);

            case self::CASCADE_SOFT_DELETE:
                // Soft delete the relationship records
                return $this->bulkSoftDeleteRelationships($manyModel);

            default:
                throw new GCException("Unknown cascade action: {$cascadeAction}");
        }
    }

    /**
     * Check if model has active relationships from "one" side
     */
    protected function hasActiveRelationsFromOne(ModelBase $oneModel): bool {
        if (!$this->isOneModel($oneModel)) {
            return false;
        }

        return $this->getActiveRelatedCount($oneModel) > 0;
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

            $this->logger->info('All OneToMany relationships removed for model', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'relationships_removed' => $result
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to remove all OneToMany relationships', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to remove all OneToMany relationships: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Check if a model is on the "one" side of the relationship
     */
    public function isOneModel(ModelBase $model): bool {
        $modelName = basename(str_replace('\\', '/', get_class($model)));
        return strtolower($modelName) === strtolower($this->relationshipMetadata['modelOne']);
    }

    /**
     * Check if a model is on the "many" side of the relationship
     */
    public function isManyModel(ModelBase $model): bool {
        $modelName = basename(str_replace('\\', '/', get_class($model)));
        return strtolower($modelName) === strtolower($this->relationshipMetadata['modelMany']);
    }

    /**
     * Get "many" model instance from a relationship record
     */
    protected function getManyModelFromRecord(array $record): ?ModelBase {
        try {
            $manyModelClass = "Gravitycar\\Models\\{$this->relationshipMetadata['modelMany']}";
            $manyIdField = 'many_' . strtolower($this->relationshipMetadata['modelMany']) . '_id';
            $manyId = $record[$manyIdField] ?? null;

            if (!$manyId) {
                return null;
            }

            $manyModel = new $manyModelClass($this->logger);
            return $manyModel->findById($manyId);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get many model from record', [
                'record' => $record,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
