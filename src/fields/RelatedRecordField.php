<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\GCException;

/**
 * Related Record field implementation
 *
 * Handles relationships to other models/records.
 */
class RelatedRecordField extends FieldsBase
{
    protected string $type = 'RelatedRecord';
    protected string $phpDataType = 'int';
    protected string $databaseType = 'INT';
    protected string $uiDataType = 'select';
    protected ?string $relatedModel = null;
    protected ?string $displayField = null;
    protected bool $isIndexed = true;

    public function __construct(array $fieldDefinition)
    {
        // Validate that relatedModel is provided
        if (empty($fieldDefinition['relatedModel'])) {
            throw new GCException("RelatedRecordField requires 'relatedModel' to be defined");
        }

        // Set default display field if not provided
        if (empty($fieldDefinition['displayField'])) {
            $fieldDefinition['displayField'] = 'name';
        }

        parent::__construct($fieldDefinition);

        $this->relatedModel = $fieldDefinition['relatedModel'];
        $this->displayField = $fieldDefinition['displayField'];
    }

    public function getValueForApi(): mixed
    {
        return $this->value !== null ? (int) $this->value : null;
    }

    public function setValueFromDB(mixed $value): void
    {
        $this->value = $value !== null ? (int) $value : null;
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }

    public function getRelatedRecord(): ?object
    {
        if ($this->value && $this->relatedModel) {
            $modelClass = "Gravitycar\\Models\\{$this->relatedModel}";
            if (class_exists($modelClass)) {
                return $modelClass::find($this->value);
            }
        }
        return null;
    }

    public function getDisplayValue(): string
    {
        $relatedRecord = $this->getRelatedRecord();
        if ($relatedRecord && $this->displayField) {
            return $relatedRecord->get($this->displayField) ?? '';
        }
        return '';
    }

    public function getOptions(): array
    {
        $options = [];

        if ($this->relatedModel) {
            $modelClass = "Gravitycar\\Models\\{$this->relatedModel}";
            if (class_exists($modelClass)) {
                $records = $modelClass::findAll();
                foreach ($records as $record) {
                    $id = $record->get($record->getPrimaryKey());
                    $display = $record->get($this->displayField) ?? "Record #{$id}";
                    $options[$id] = $display;
                }
            }
        }

        return $options;
    }

    protected function validateValue(mixed $value, \Gravitycar\Core\ModelBase $model): void
    {
        // First run standard validation
        parent::validateValue($value, $model);

        // Validate that the related record exists
        if (!empty($value) && $this->relatedModel) {
            $modelClass = "Gravitycar\\Models\\{$this->relatedModel}";
            if (class_exists($modelClass)) {
                $relatedRecord = $modelClass::find($value);
                if (!$relatedRecord) {
                    $this->validationErrors[] = "Related record not found";
                    $model->addValidationError($this->name, $this->validationErrors);
                }
            }
        }
    }

    public function getRelatedModel(): ?string
    {
        return $this->relatedModel;
    }

    public function getDisplayField(): ?string
    {
        return $this->displayField;
    }
}
