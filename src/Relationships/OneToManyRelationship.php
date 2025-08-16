<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;

/**
 * Handles OneToMany relationships between models.
 * One record in modelOne relates to many records in modelMany.
 */
class OneToManyRelationship extends RelationshipBase {



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
     * Add relationship with ordering support
     */
    public function addRelationWithOrder(ModelBase $oneModel, ModelBase $manyModel, int $order = 0): bool {
        return $this->add($oneModel, $manyModel, ['order' => $order]);
    }

    /**
     * Update additional fields in a relationship
     */
    public function updateRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData): bool {
        try {
            if (empty($additionalData)) {
                return true; // Nothing to update
            }

            // First, find the existing relationship record
            $criteria = [
                $this->getModelIdField($modelA) => $modelA->get('id'),
                $this->getModelIdField($modelB) => $modelB->get('id'),
                'deleted_at' => null  // Only find non-deleted relationships
            ];

            $dbConnector = $this->getDatabaseConnector();
            $results = $dbConnector->find($this, $criteria, [], ['limit' => 1]);

            if (empty($results)) {
                $this->logger->warning('OneToMany relationship not found for update', [
                    'relationship' => $this->getName(),
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

            // Update the fields with new data
            foreach ($additionalData as $field => $value) {
                if ($relationshipInstance->hasField($field)) {
                    $relationshipInstance->set($field, $value);
                }
            }

            // Set updated_at timestamp
            if ($relationshipInstance->hasField('updated_at')) {
                $relationshipInstance->set('updated_at', date('Y-m-d H:i:s'));
            }

            // Update the relationship record using DatabaseConnector
            $success = $dbConnector->update($relationshipInstance);

            if ($success) {
                $this->logger->info('OneToMany relationship updated', [
                    'relationship' => $this->getName(),
                    'model_a_class' => get_class($modelA),
                    'model_a_id' => $modelA->get('id'),
                    'model_b_class' => get_class($modelB),
                    'model_b_id' => $modelB->get('id'),
                    'updated_data' => $additionalData
                ]);
            }

            return $success;

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
                // Just soft delete the relationship record, don't delete the "one" model
                return $this->bulkSoftDeleteRelationships($manyModel);

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
     * Check if a model is on the "one" side of the relationship
     */
    public function isOneModel(ModelBase $model): bool {
        $modelName = basename(str_replace('\\', '/', get_class($model)));
        return strtolower($modelName) === strtolower($this->metadata['modelOne']);
    }

    /**
     * Check if a model is on the "many" side of the relationship
     */
    public function isManyModel(ModelBase $model): bool {
        $modelName = basename(str_replace('\\', '/', get_class($model)));
        return strtolower($modelName) === strtolower($this->metadata['modelMany']);
    }

    /**
     * Get "many" model instance from a relationship record
     */
    protected function getManyModelFromRecord(array $record): ?ModelBase {
        try {
            $manyModelName = $this->metadata['modelMany'];
            $manyIdField = 'many_' . strtolower($this->metadata['modelMany']) . '_id';
            $manyId = $record[$manyIdField] ?? null;

            if (!$manyId) {
                return null;
            }

            return ModelFactory::retrieve($manyModelName, $manyId);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get many model from record', [
                'record' => $record,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
