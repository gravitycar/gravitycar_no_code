<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Field type for linking records to other models via foreign key relationships.
 * Automatically includes validation to ensure foreign key values exist in the related table.
 */
class RelatedRecordField extends FieldBase {
    /** @var array */
    protected array $requiredMetadataFields = [
        'relatedModel',
        'relatedFieldName',
        'displayFieldName'
    ];

    /** @var string */
    protected string $relatedModel;

    /** @var string */
    protected string $relatedFieldName;

    /** @var string */
    protected string $displayFieldName;
    
    /** @var string React component name for this field type */
    protected string $reactComponent = 'RelatedRecordSelect';
    
    /** @var array Related record operators for foreign key filtering */
    protected array $operators = [
        'equals', 'notEquals', 'in', 'notIn', 'isNull', 'isNotNull'
    ];

    public function __construct(array $metadata, ?Logger $logger = null) {
        parent::__construct($metadata, $logger);
        $this->validateRelatedRecordMetadata(empty($metadata));
        
        // Initialize properties from metadata if available
        if (!empty($metadata)) {
            $this->relatedModel = $metadata['relatedModel'] ?? '';
            $this->relatedFieldName = $metadata['relatedFieldName'] ?? '';
            $this->displayFieldName = $metadata['displayFieldName'] ?? '';
        }
    }

    /**
     * Validate that required metadata for RelatedRecord field is present
     */
    protected function validateRelatedRecordMetadata(bool $emptyMetadata = false): void {
        // Skip validation if we're instantiating with empty metadata (used for field type discovery)
        if ($emptyMetadata) {
            return;
        }
        
        foreach ($this->requiredMetadataFields as $field) {
            if (!isset($this->metadata[$field]) || empty($this->metadata[$field])) {
                throw new GCException("RelatedRecord field missing required metadata: {$field}", [
                    'field_name' => $this->name ?? 'unknown',
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
        return $this->relatedModel;
    }

    /**
     * Get the name of the related field
     */
    public function getRelatedFieldName(): string {
        return $this->relatedFieldName;
    }

    /**
     * Get an instance of the related model using ModelFactory
     */
    public function getRelatedModelInstance() {
        $modelName = $this->getRelatedModelName();

        try {
            return \Gravitycar\Core\ServiceLocator::getModelFactory()->new($modelName);
        } catch (\Exception $e) {
            throw new GCException("Could not create instance of related model: {$modelName}", [
                'field_name' => $this->name,
                'related_model' => $modelName,
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
