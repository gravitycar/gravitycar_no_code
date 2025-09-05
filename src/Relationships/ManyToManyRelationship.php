<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;

/**
 * Handles ManyToMany relationships between models.
 * Multiple records in modelA can relate to multiple records in modelB.
 */
class ManyToManyRelationship extends RelationshipBase {

    /**
     * Get the other model in the relationship given one model
     * For ManyToMany relationships, returns the opposite model (A->B or B->A)
     */
    public function getOtherModel(ModelBase $model): ModelBase {
        $modelClass = get_class($model);
        $modelName = basename(str_replace('\\', '/', $modelClass));
        
        $modelA = $this->metadata['modelA'];
        $modelB = $this->metadata['modelB'];
        
        if ($modelName === $modelA) {
            // Source is modelA, return modelB instance
            return ModelFactory::new($modelB);
        } elseif ($modelName === $modelB) {
            // Source is modelB, return modelA instance
            return ModelFactory::new($modelA);
        } else {
            throw new GCException("Model {$modelName} is not part of this ManyToMany relationship", [
                'model_class' => $modelClass,
                'relationship_name' => $this->getName(),
                'expected_models' => [$modelA, $modelB]
            ]);
        }
    }

    /**
     * Get all relationships with additional field data
     */
    public function getRelatedWithData(ModelBase $model, array $additionalFields = []): array {
        // Build criteria for the query
        $criteria = [
            $this->getModelIdField($model) => $model->get('id'),
            'deleted_at' => null  // Only find non-deleted relationships
        ];

        // Add additional field criteria
        foreach ($additionalFields as $field => $value) {
            $criteria[$field] = $value;
        }

        // Use DatabaseConnector->find() for consistent data access
        $dbConnector = $this->getDatabaseConnector();
        $records = $dbConnector->find($this, $criteria);

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
     * Bulk add multiple relationships
     */
    public function addMultipleRelations(ModelBase $model, array $relatedModels, array $commonAdditionalData = []): bool {
        try {
            $successCount = 0;

            foreach ($relatedModels as $relatedModel) {
                if ($this->add($model, $relatedModel, $commonAdditionalData)) {
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

            // First, find the existing relationship record
            $criteria = [
                $this->getModelIdField($modelA) => $modelA->get('id'),
                $this->getModelIdField($modelB) => $modelB->get('id'),
                'deleted_at' => null  // Only find non-deleted relationships
            ];

            $dbConnector = $this->getDatabaseConnector();
            $results = $dbConnector->find($this, $criteria, [], ['limit' => 1]);

            if (empty($results)) {
                $this->logger->warning('ManyToMany relationship not found for update', [
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
                $this->logger->info('ManyToMany relationship updated', [
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
                // For ManyToMany, cascade now means soft delete all relationship records
                // but NOT the related models (they may have other relationships)
                return $this->bulkSoftDeleteRelationships($deletedModel);

            case self::CASCADE_SOFT_DELETE:
                // Bulk soft delete all relationship records for this model
                return $this->bulkSoftDeleteRelationships($deletedModel);

            default:
                throw new GCException("Unknown cascade action: {$cascadeAction}");
        }
    }
}
