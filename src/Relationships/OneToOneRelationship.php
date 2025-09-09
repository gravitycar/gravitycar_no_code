<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;

/**
 * Handles OneToOne relationships between models.
 * Each record in modelA relates to exactly one record in modelB and vice versa.
 */
class OneToOneRelationship extends RelationshipBase {

    /**
     * Get the other model in the relationship given one model
     * For OneToOne relationships, returns the opposite model (A->B or B->A)
     */
    public function getOtherModel(ModelBase $model): ModelBase {
        $modelClass = get_class($model);
        $modelName = basename(str_replace('\\', '/', $modelClass));
        
        $modelA = $this->metadata['modelA'];
        $modelB = $this->metadata['modelB'];
        
        if ($modelName === $modelA) {
            // Source is modelA, return modelB instance
            return $this->modelFactory->new($modelB);
        } elseif ($modelName === $modelB) {
            // Source is modelB, return modelA instance
            return $this->modelFactory->new($modelA);
        } else {
            throw new GCException("Model {$modelName} is not part of this OneToOne relationship", [
                'model_class' => $modelClass,
                'relationship_name' => $this->getName(),
                'expected_models' => [$modelA, $modelB]
            ]);
        }
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
     * For OneToOne, this soft deletes any existing relationships for both models first
     */
    public function add(ModelBase $modelA, ModelBase $modelB, array $additionalData = []): bool {
        // Soft delete any existing relationships for both models (OneToOne constraint)
        $this->softDeleteExistingRelationships($modelA);
        $this->softDeleteExistingRelationships($modelB);

        // Call parent method to handle the actual insertion
        return parent::add($modelA, $modelB, $additionalData);
    }

    /**
     * Set or replace the relationship (removes existing if present)
     */
    public function setRelation(ModelBase $modelA, ModelBase $modelB, array $additionalData = []): bool {
        // For OneToOne, setRelation is the same as add since add already handles replacement
        return $this->add($modelA, $modelB, $additionalData);
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
                $this->logger->warning('OneToOne relationship not found for update', [
                    'relationship' => $this->getName(),
                    'model_a_class' => get_class($modelA),
                    'model_a_id' => $modelA->get('id'),
                    'model_b_class' => get_class($modelB),
                    'model_b_id' => $modelB->get('id')
                ]);
                return false;
            }

            // Create a relationship instance from the found record and populate it
            $relationshipInstance = new static(
                $this->relationshipName, 
                $this->logger, 
                $this->metadataEngine,
                $this->coreFieldsMetadata,
                $this->modelFactory,
                $this->databaseConnector
            );
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
                $this->logger->info('OneToOne relationship updated', [
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
            $modelA = strtolower($this->metadata['modelA']);
            $modelB = strtolower($this->metadata['modelB']);

            if ($sourceModelName === $modelA) {
                // Source is modelA, so related is modelB
                $relatedModelName = $this->metadata['modelB'];
                $relatedId = $relationshipRecord[$modelB . '_id'];
            } else {
                // Source is modelB, so related is modelA
                $relatedModelName = $this->metadata['modelA'];
                $relatedId = $relationshipRecord[$modelA . '_id'];
            }

            // Create and load the related model using ModelFactory
            return $this->modelFactory->retrieve($relatedModelName, $relatedId);

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
