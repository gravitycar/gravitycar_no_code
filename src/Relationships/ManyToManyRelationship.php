<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;

/**
 * Handles ManyToMany relationships between models.
 * Multiple records in modelA can relate to multiple records in modelB.
 */
class ManyToManyRelationship extends RelationshipBase {

    /**
     * Get all related records for a given model instance
     */
    public function getRelatedRecords(ModelBase $model): array {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE {$this->getModelIdField($model)} = ? 
                AND deleted_at IS NULL";

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([$model->get('id')]);

        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug('ManyToMany getRelatedRecords completed', [
            'model_class' => get_class($model),
            'model_id' => $model->get('id'),
            'relationship' => $this->getName(),
            'records_found' => count($records)
        ]);

        return $records;
    }

    /**
     * Get all relationships with additional field data
     */
    public function getRelatedWithData(ModelBase $model, array $additionalFields = []): array {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE {$this->getModelIdField($model)} = ? 
                AND deleted_at IS NULL";

        $params = [$model->get('id')];

        // Add conditions for additional fields
        if (!empty($additionalFields)) {
            $conditions = [];
            foreach ($additionalFields as $field => $value) {
                $conditions[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " AND " . implode(' AND ', $conditions);
        }

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug('ManyToMany getRelatedWithData completed', [
            'model_class' => get_class($model),
            'model_id' => $model->get('id'),
            'relationship' => $this->getName(),
            'additional_fields' => $additionalFields,
            'records_found' => count($records)
        ]);

        return $records;
    }

    /**
     * Add a new relationship record
     */
    public function addRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData = []): bool {
        try {
            // Check if relationship already exists
            if ($this->hasRelation($modelA, $modelB)) {
                $this->logger->warning('ManyToMany relationship already exists', [
                    'relationship' => $this->getName(),
                    'model_a_class' => get_class($modelA),
                    'model_a_id' => $modelA->get('id'),
                    'model_b_class' => get_class($modelB),
                    'model_b_id' => $modelB->get('id')
                ]);
                return false;
            }

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

            $this->logger->info('ManyToMany relationship added', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'additional_data' => $additionalData
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to add ManyToMany relationship', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to add ManyToMany relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Bulk add multiple relationships
     */
    public function addMultipleRelations(ModelBase $model, array $relatedModels, array $commonAdditionalData = []): bool {
        try {
            $successCount = 0;

            foreach ($relatedModels as $relatedModel) {
                if ($this->addRelation($model, $relatedModel, $commonAdditionalData)) {
                    $successCount++;
                }
            }

            $this->logger->info('ManyToMany bulk relationships added', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'total_attempted' => count($relatedModels),
                'successful_additions' => $successCount,
                'common_data' => $commonAdditionalData
            ]);

            return $successCount > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to add multiple ManyToMany relationships', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to add multiple ManyToMany relationships: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Bulk add multiple relationships using batch insert for better performance
     */
    public function addMultipleRelationsBatch(ModelBase $model, array $relatedModels, array $commonData = []): bool {
        try {
            if (empty($relatedModels)) {
                return true;
            }

            $batchSize = 1000; // Configurable batch size
            $batches = array_chunk($relatedModels, $batchSize);
            $totalInserted = 0;

            foreach ($batches as $batch) {
                $inserted = $this->executeBatchInsert($model, $batch, $commonData);
                $totalInserted += $inserted;
            }

            $this->logger->info('ManyToMany batch relationships added', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'total_models' => count($relatedModels),
                'total_inserted' => $totalInserted,
                'batch_count' => count($batches)
            ]);

            return $totalInserted > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to batch add ManyToMany relationships', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to batch add ManyToMany relationships: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Execute batch insert for a set of related models
     */
    protected function executeBatchInsert(ModelBase $model, array $relatedModels, array $commonData): int {
        $conn = $this->getDatabaseConnector()->getConnection();

        // Build the batch INSERT query
        $modelAField = $this->getModelIdField($model);
        $fields = [$modelAField];

        // Determine modelB field name
        $modelBField = $this->getOtherModelIdField($model);
        $fields[] = $modelBField;

        // Add common fields
        $fields = array_merge($fields, ['created_at', 'updated_at'], array_keys($commonData));

        $fieldList = implode(', ', $fields);
        $placeholders = '(' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $allPlaceholders = implode(',', array_fill(0, count($relatedModels), $placeholders));

        $sql = "INSERT IGNORE INTO {$this->getTableName()} ({$fieldList}) VALUES {$allPlaceholders}";

        // Prepare parameters
        $params = [];
        $currentDateTime = date('Y-m-d H:i:s');

        foreach ($relatedModels as $relatedModel) {
            $params[] = $model->get('id');
            $params[] = $relatedModel->get('id');
            $params[] = $currentDateTime;
            $params[] = $currentDateTime;

            foreach ($commonData as $value) {
                $params[] = $value;
            }
        }

        $stmt = $conn->prepare($sql);
        return $stmt->executeStatement($params);
    }

    /**
     * Get the other model's ID field name (for batch operations)
     */
    protected function getOtherModelIdField(ModelBase $model): string {
        $modelName = strtolower(basename(str_replace('\\', '/', get_class($model))));
        $modelAName = strtolower($this->relationshipMetadata['modelA']);
        $modelBName = strtolower($this->relationshipMetadata['modelB']);

        if ($modelName === $modelAName) {
            return $modelBName . '_id';
        } else {
            return $modelAName . '_id';
        }
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

            $this->logger->info('ManyToMany relationship removed', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id')
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to remove ManyToMany relationship', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to remove ManyToMany relationship: ' . $e->getMessage(), [], 0, $e);
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

            $this->logger->info('All ManyToMany relationships removed for model', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'relationships_removed' => $result
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to remove all ManyToMany relationships', [
                'relationship' => $this->getName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to remove all ManyToMany relationships: ' . $e->getMessage(), [], 0, $e);
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
     * Check if a model has any relationships
     */
    public function hasAnyRelations(ModelBase $model): bool {
        return $this->getActiveRelatedCount($model) > 0;
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

            $this->logger->info('ManyToMany relationship updated', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'updated_data' => $additionalData
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update ManyToMany relationship', [
                'relationship' => $this->getName(),
                'model_a_class' => get_class($modelA),
                'model_a_id' => $modelA->get('id'),
                'model_b_class' => get_class($modelB),
                'model_b_id' => $modelB->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to update ManyToMany relationship: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Handle cascade operations when a model is deleted
     */
    public function handleModelDeletion(ModelBase $deletedModel, string $cascadeAction): bool {
        switch ($cascadeAction) {
            case self::CASCADE_RESTRICT:
                if ($this->hasAnyRelations($deletedModel)) {
                    throw new GCException(
                        'Cannot delete model with existing ManyToMany relationships',
                        [
                            'model' => get_class($deletedModel),
                            'id' => $deletedModel->get('id'),
                            'active_relationships' => $this->getActiveRelatedCount($deletedModel),
                            'relationship' => $this->getName()
                        ]
                    );
                }
                return true;

            case self::CASCADE_CASCADE:
                // For ManyToMany, cascade typically means delete all relationship records
                // but NOT the related models (they may have other relationships)
                return $this->hardDeleteAllRelationships($deletedModel);

            case self::CASCADE_SOFT_DELETE:
                // Bulk soft delete all relationship records for this model
                return $this->bulkSoftDeleteRelationships($deletedModel);

            default:
                throw new GCException("Unknown cascade action: {$cascadeAction}");
        }
    }

    /**
     * Hard delete all relationship records for a model
     */
    protected function hardDeleteAllRelationships(ModelBase $model): bool {
        try {
            $conn = $this->getDatabaseConnector()->getConnection();

            $sql = "DELETE FROM {$this->getTableName()} 
                    WHERE {$this->getModelIdField($model)} = ?";

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeStatement([$model->get('id')]);

            $this->logger->info('Hard delete of all ManyToMany relationship records completed', [
                'relationship_table' => $this->getTableName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'records_deleted' => $result
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to hard delete ManyToMany relationship records', [
                'relationship_table' => $this->getTableName(),
                'model_class' => get_class($model),
                'model_id' => $model->get('id'),
                'error' => $e->getMessage()
            ]);

            throw new GCException('Hard delete of ManyToMany relationships failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Get paginated related records
     */
    public function getRelatedPaginated(ModelBase $model, int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;

        // Get total count
        $totalSql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                     WHERE {$this->getModelIdField($model)} = ? 
                     AND deleted_at IS NULL";

        $conn = $this->getDatabaseConnector()->getConnection();
        $stmt = $conn->prepare($totalSql);
        $stmt->execute([$model->get('id')]);
        $total = $stmt->fetchOne();

        // Get paginated records
        $recordsSql = "SELECT * FROM {$this->getTableName()} 
                       WHERE {$this->getModelIdField($model)} = ? 
                       AND deleted_at IS NULL 
                       LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($recordsSql);
        $stmt->execute([$model->get('id'), $perPage, $offset]);
        $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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

        $this->logger->debug('ManyToMany paginated records retrieved', [
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
