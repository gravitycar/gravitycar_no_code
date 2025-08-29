<?php
namespace Gravitycar\Validation;

use Gravitycar\Fields\RelatedRecordField;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;

/**
 * Validation rule to ensure foreign key values exist in the related table.
 * Specifically designed for RelatedRecord fields.
 */
class ForeignKeyExistsValidation extends ValidationRuleBase {

    public function __construct(string $name = '', string $errorMessage = '') {
        $name = $name ?: 'ForeignKeyExists';
        $errorMessage = $errorMessage ?: 'The selected {fieldName} does not exist.';

        parent::__construct($name, $errorMessage);

        // Set properties specific to this validation rule
        $this->contextSensitive = true;
        $this->skipIfEmpty = true; // Skip validation if value is empty
        $this->stopOnFailure = false;
        $this->priority = 50; // Run after basic validations but before custom ones
    }

    /**
     * Validate that the foreign key value exists in the related table
     */
    public function validate(mixed $value, $model = null): bool {
        // Early validation checks
        if (!$this->shouldValidateValue($value)) {
            return true;
        }

        // Field type validation
        if (!$this->isValidFieldType()) {
            return true;
        }

        try {
            // Get the related field for validation
            $relatedField = $this->getRelatedField();
            
            // Check if the foreign key exists
            return $this->checkForeignKeyExists($relatedField, $value);

        } catch (\Exception $e) {
            $this->logValidationError($e, $value);
            return false;
        }
    }

    /**
     * Check if the field type is valid for this validation
     */
    private function isValidFieldType(): bool {
        if (!($this->field instanceof RelatedRecordField)) {
            $this->logger->warning('ForeignKeyExists validation applied to non-RelatedRecord field', [
                'field_name' => $this->field ? $this->field->getName() : 'unknown',
                'field_type' => $this->field ? get_class($this->field) : 'unknown'
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get the related field for validation
     */
    private function getRelatedField(): FieldBase {
        $field = $this->getRelatedRecordField();
        
        // Get the related model instance
        $relatedModelInstance = $field->getRelatedModelInstance();

        // Get the specific field we need to check in the related table
        $relatedFieldName = $field->getRelatedFieldName();
        $relatedField = $relatedModelInstance->getField($relatedFieldName);

        if (!$relatedField) {
            throw new GCException("Related field '{$relatedFieldName}' not found in related model", [
                'field_name' => $field->getName(),
                'related_model' => $field->getRelatedModelName(),
                'related_field_name' => $relatedFieldName
            ]);
        }

        return $relatedField;
    }

    /**
     * Check if the foreign key value exists in the related table
     */
    private function checkForeignKeyExists(FieldBase $relatedField, mixed $value): bool {
        // Use DatabaseConnector to check if record exists
        $databaseConnector = \Gravitycar\Core\ServiceLocator::get('Gravitycar\Database\DatabaseConnector');
        $exists = $databaseConnector->recordExists($relatedField, $value);

        if (!$exists) {
            $field = $this->getRelatedRecordField();
            $this->logger->info('Foreign key validation failed - record not found', [
                'field_name' => $field->getName(),
                'foreign_key_value' => $value,
                'related_model' => $field->getRelatedModelName(),
                'related_field_name' => $field->getRelatedFieldName()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Log validation errors that occur during the process
     */
    private function logValidationError(\Exception $e, mixed $value): void {
        $field = $this->field ? $this->getRelatedRecordField() : null;
        $this->logger->error('Error during foreign key validation', [
            'field_name' => $field ? $field->getName() : 'unknown',
            'foreign_key_value' => $value,
            'related_model' => $field ? $field->getRelatedModelName() : 'unknown',
            'error' => $e->getMessage()
        ]);
    }

    /**
     * Set the value to be validated
     */
    public function setValue(mixed $value): void {
        $this->value = $value;
    }

    /**
     * Set the field object this validation rule is associated with
     * @param FieldBase $field The field instance (should be RelatedRecordField for proper validation)
     */
    public function setField(FieldBase $field): void {
        $this->field = $field;
        
        // Log a warning if this is not a RelatedRecordField, but don't throw an exception
        // The validation logic will handle this gracefully
        if (!($field instanceof RelatedRecordField)) {
            $this->logger->debug('ForeignKeyExistsValidation set on non-RelatedRecordField - validation will return true with warning', [
                'field_name' => $field->getName(),
                'field_type' => get_class($field)
            ]);
        }
    }

    /**
     * Get the field as a RelatedRecordField instance
     * @return RelatedRecordField
     * @throws GCException if field is not set or not a RelatedRecordField
     */
    protected function getRelatedRecordField(): RelatedRecordField {
        if (!$this->field) {
            throw new GCException('Field not set for ForeignKeyExistsValidation');
        }
        
        if (!($this->field instanceof RelatedRecordField)) {
            throw new GCException('Field must be a RelatedRecordField instance', [
                'field_type' => get_class($this->field)
            ]);
        }
        
        return $this->field;
    }

    /**
     * Set the model object this validation rule is associated with
     */
    public function setModel(ModelBase $model): void {
        $this->model = $model;
    }

    /**
     * Check if this validation rule is applicable
     */
    public function isApplicable(mixed $value, FieldBase $field, ?ModelBase $model = null): bool {
        // Only apply to RelatedRecord fields
        if (!($field instanceof RelatedRecordField)) {
            return false;
        }

        // Skip if empty and skipIfEmpty is true
        if ($this->skipIfEmpty && (empty($value) || $value === null || $value === '')) {
            return false;
        }

        // Check if validation is enabled
        if (!$this->isEnabled) {
            return false;
        }

        // Check conditional rules if any exist
        if (!empty($this->conditionalRules)) {
            foreach ($this->conditionalRules as $condition) {
                // Implementation of conditional logic would go here
                // For now, we'll assume all conditions pass
            }
        }

        return true;
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        // For foreign key validation, we'd typically need an AJAX call to verify existence
        return "
        function validateForeignKey(value, fieldName, relatedModel) {
            if (!value || value === '') return { valid: true };
            
            // This would typically make an AJAX call to verify the foreign key exists
            // For now, we'll return true and rely on server-side validation
            // In a full implementation, you'd call something like:
            /*
            return fetch('/api/validate-foreign-key', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    value: value,
                    model: relatedModel,
                    field: fieldName
                })
            })
            .then(response => response.json())
            .then(data => ({
                valid: data.exists,
                message: data.exists ? '' : 'The selected ' + fieldName + ' does not exist.'
            }))
            .catch(error => ({
                valid: false,
                message: 'Error validating ' + fieldName + ': ' + error.message
            }));
            */
            
            return { valid: true }; // Placeholder - rely on server-side validation
        }";
    }

    /**
     * Format error message with field-specific values
     */
    public function getFormatErrorMessage(): string {
        $message = $this->getErrorMessage();

        if ($this->field) {
            $message = str_replace('{fieldName}', $this->field->getName(), $message);
        }

        if ($this->value !== null) {
            $message = str_replace('{value}', (string)$this->value, $message);
        }

        return $message;
    }
}
