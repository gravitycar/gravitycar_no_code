<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Field type for linking records to other models via foreign key relationships.
 * Automatically includes validation to ensure foreign key values exist in the related table.
 */
class RelatedRecordField extends FieldBase {
    /** @var array */
    protected array $requiredMetadataFields = [
        'relatedModelName',
        'relatedFieldName',
        'displayFieldName'
    ];

    /** @var string */
    protected string $relatedModelName;

    /** @var string */
    protected string $relatedFieldName;

    /** @var string */
    protected string $displayFieldName;

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->validateRelatedRecordMetadata();
    }

    /**
     * Validate that required metadata for RelatedRecord field is present
     */
    protected function validateRelatedRecordMetadata(): void {
        foreach ($this->requiredMetadataFields as $field) {
            if (!isset($this->metadata[$field]) || empty($this->metadata[$field])) {
                throw new GCException("RelatedRecord field missing required metadata: {$field}", [
                    'field_name' => $this->name,
                    'metadata' => $this->metadata,
                    'required_fields' => $this->requiredMetadataFields
                ]);
            }
        }
    }

    /**
     * Get the name of the related model class
     */
    public function getRelatedModelName(): string {
        return $this->relatedModelName;
    }

    /**
     * Get the name of the related field
     */
    public function getRelatedFieldName(): string {
        return $this->relatedFieldName;
    }

    /**
     * Get an instance of the related model using ServiceLocator
     */
    public function getRelatedModelInstance() {
        $modelName = $this->getRelatedModelName();
        $fullClassName = "Gravitycar\\Models\\{$modelName}";

        try {
            return ServiceLocator::create($fullClassName);
        } catch (\Exception $e) {
            throw new GCException("Could not create instance of related model: {$modelName}", [
                'field_name' => $this->name,
                'related_model' => $modelName,
                'full_class_name' => $fullClassName,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the display field name for UI representation
     */
    public function getDisplayFieldName(): string {
        return $this->displayFieldName;
    }

    /**
     * Render the field for output (typically used in forms/views)
     */
    public function render(): string {
        // For RelatedRecord fields, we typically want to show the display name
        // This would be used in conjunction with UI components
        return (string) $this->value;
    }

    /**
     * Get field type for metadata/schema purposes
     */
    public function getType(): string {
        return 'RelatedRecord';
    }
}
