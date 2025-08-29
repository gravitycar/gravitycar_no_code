<?php
namespace Gravitycar\Validation;

/**
 * UniqueValidation: Ensures a value is unique in the database.
 */
class UniqueValidation extends ValidationRuleBase {
    public function __construct() {
        parent::__construct('Unique', 'This value must be unique.');
    }

    public function validate($value, $model = null): bool {
        // Skip validation for empty values using inherited method
        if (!$this->shouldValidateValue($value)) {
            return true;
        }

        // UniqueValidation requires field context to work
        if (!$this->field) {
            $this->logger->error('UniqueValidation requires field context but field is not set');
            return false;
        }

        try {
            // Use DatabaseConnector to check if this value already exists
            $databaseConnector = \Gravitycar\Core\ServiceLocator::get('Gravitycar\Database\DatabaseConnector');
            
            // If we have a model with an ID, use recordExistsExcludingId to exclude the current record
            if ($model && $model instanceof \Gravitycar\Models\ModelBase && $model->get('id')) {
                $exists = $databaseConnector->recordExistsExcludingId($this->field, $value, $model->get('id'));
            } else {
                // For new records (no ID), use the regular recordExists check
                $exists = $databaseConnector->recordExists($this->field, $value);
            }

            if ($exists) {
                $this->logger->info('Unique validation failed - value already exists', [
                    'field_name' => $this->field->getName(),
                    'value' => $value,
                    'model_id' => ($model && $model instanceof \Gravitycar\Models\ModelBase) ? $model->get('id') : 'none'
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error during unique validation', [
                'field_name' => $this->field ? $this->field->getName() : 'unknown',
                'value' => $value,
                'model_id' => ($model && $model instanceof \Gravitycar\Models\ModelBase) ? $model->get('id') : 'none',
                'error' => $e->getMessage()
            ]);

            // In case of error, we'll be conservative and fail validation
            return false;
        }
    }

    /**
     * Get JavaScript validation logic for client-side validation
     * Note: Unique validation is server-side only, so client-side always returns valid
     */
    public function getJavascriptValidation(): string {
        return "
        function validateUnique(value, fieldName) {
            // Unique validation is server-side only
            // Client-side validation always passes - server will do the actual check
            return { valid: true };
        }";
    }
}
