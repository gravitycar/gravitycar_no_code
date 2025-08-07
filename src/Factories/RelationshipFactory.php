<?php
namespace Gravitycar\Factories;

use Gravitycar\Relationships\RelationshipBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * Factory for creating, validating, and managing relationship instances.
 * Handles all cross-cutting concerns that don't belong in individual relationship classes.
 */
class RelationshipFactory {
    protected object $model;
    protected Logger $logger;
    protected array $availableRelationshipTypes = [];
    protected array $relationshipRegistry = [];
    protected array $relationshipKeys = [];

    public function __construct(object $model, Logger $logger) {
        $this->model = $model;
        $this->logger = $logger;
        $this->discoverRelationshipTypes();
    }

    /**
     * Create a relationship instance from relationship name
     */
    public function createRelationship(string $relationshipName): RelationshipBase {
        try {
            // Load metadata from filesystem
            $metadataFilePath = $this->buildMetadataFilePath($relationshipName);

            if (!file_exists($metadataFilePath)) {
                throw new GCException("Relationship metadata file not found", [
                    'relationship_name' => $relationshipName,
                    'metadata_file_path' => $metadataFilePath,
                    'model_class' => get_class($this->model)
                ]);
            }

            $metadata = include $metadataFilePath;

            if (!is_array($metadata)) {
                throw new GCException("Relationship metadata file must return an array", [
                    'relationship_name' => $relationshipName,
                    'metadata_file_path' => $metadataFilePath,
                    'returned_type' => gettype($metadata)
                ]);
            }

            // Validate metadata before creating instance
            $this->validateRelationshipMetadata($metadata);

            // Check for duplicates
            $this->checkForDuplicateRelationships($metadata);

            // Check for circular dependencies
            $this->detectCircularDependencies($metadata);

            // Create instance with metadata parameter
            $type = $metadata['type'];
            $className = $this->availableRelationshipTypes[$type] ?? "Gravitycar\\Relationships\\{$type}Relationship";

            if (!class_exists($className)) {
                throw new GCException("Relationship class not found for type: $type", [
                    'relationship_type' => $type,
                    'expected_class' => $className,
                    'available_types' => array_keys($this->availableRelationshipTypes)
                ]);
            }

            // Create relationship instance with metadata parameter
            $relationship = new $className($metadata, $this->logger);

            // Register the relationship
            $this->registerRelationship($relationship, $metadata);

            $this->logger->info('Relationship created successfully', [
                'relationship_name' => $metadata['name'],
                'relationship_type' => $type,
                'model_class' => get_class($this->model)
            ]);

            return $relationship;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create relationship', [
                'relationship_name' => $relationshipName,
                'model_class' => get_class($this->model),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Build metadata file path for relationship
     */
    protected function buildMetadataFilePath(string $relationshipName): string {
        return __DIR__ . "/../Relationships/{$relationshipName}/{$relationshipName}_metadata.php";
    }

    /**
     * Prevent duplicate relationship definitions
     */
    protected function checkForDuplicateRelationships(array $metadata): void {
        $relationshipKey = $this->generateRelationshipKey($metadata);

        if (isset($this->relationshipKeys[$relationshipKey])) {
            throw new GCException(
                'Duplicate relationship definition detected',
                [
                    'relationship_key' => $relationshipKey,
                    'new_relationship' => $metadata['name'],
                    'existing_relationship' => $this->relationshipKeys[$relationshipKey],
                    'suggestion' => 'Use different relationship names or purposes to distinguish multiple relationships between the same models'
                ]
            );
        }
    }

    /**
     * Generate normalized relationship key for duplicate detection
     */
    private function generateRelationshipKey(array $metadata): string {
        $models = [
            $metadata['modelA'] ?? $metadata['modelOne'],
            $metadata['modelB'] ?? $metadata['modelMany']
        ];

        // Sort alphabetically to ensure consistent ordering
        // This makes User→Role and Role→User generate the same key for duplicate detection
        sort($models);

        // Include relationship type to allow different types between same models
        return implode('_', $models) . '_' . $metadata['type'];
    }

    /**
     * Detect circular dependency chains in relationship definitions
     */
    protected function detectCircularDependencies(array $metadata): void {
        $modelA = $metadata['modelA'] ?? $metadata['modelOne'];
        $modelB = $metadata['modelB'] ?? $metadata['modelMany'];

        // Note: Circular dependency detection depth limits will be addressed
        // when we better understand our needs. For now, implement basic detection.
        if ($this->hasCircularDependency($modelA, $modelB)) {
            throw new GCException(
                'Circular dependency detected in relationship definition',
                [
                    'relationship' => $metadata['name'],
                    'models' => [$modelA, $modelB],
                    'suggestion' => 'Review relationship chain to prevent circular references'
                ]
            );
        }
    }

    /**
     * Basic circular dependency detection
     */
    protected function hasCircularDependency(string $modelA, string $modelB): bool {
        // For now, implement basic check - more sophisticated detection can be added later
        // Check if models are the same and it's not explicitly marked as self-referential
        return false; // Placeholder - will implement more sophisticated detection later
    }

    /**
     * Register relationship in factory registry
     */
    protected function registerRelationship(RelationshipBase $relationship, array $metadata): void {
        $relationshipKey = $this->generateRelationshipKey($metadata);

        $this->relationshipRegistry[$metadata['name']] = [
            'instance' => $relationship,
            'metadata' => $metadata,
            'key' => $relationshipKey,
            'hasLoadedData' => false // Track for memory management
        ];

        $this->relationshipKeys[$relationshipKey] = $metadata['name'];
    }

    /**
     * Clean up relationships with no loaded related data (weak references)
     */
    public function cleanupEmptyRelationships(): void {
        foreach ($this->relationshipRegistry as $name => $entry) {
            if (!$entry['hasLoadedData']) {
                unset($this->relationshipRegistry[$name]);
                unset($this->relationshipKeys[$entry['key']]);
            }
        }

        $this->logger->debug('Cleaned up empty relationships', [
            'model_class' => get_class($this->model)
        ]);
    }

    /**
     * Mark relationship as having loaded data
     */
    public function markRelationshipLoaded(string $relationshipName): void {
        if (isset($this->relationshipRegistry[$relationshipName])) {
            $this->relationshipRegistry[$relationshipName]['hasLoadedData'] = true;
        }
    }

    /**
     * Get all available relationship types
     */
    public function getAvailableRelationshipTypes(): array {
        return array_keys($this->availableRelationshipTypes);
    }

    /**
     * Scan src/relationships directory for available relationship types
     */
    protected function discoverRelationshipTypes(): void {
        $relationshipsDir = __DIR__ . '/../Relationships';
        if (!is_dir($relationshipsDir)) {
            $this->logger->warning("Relationships directory not found: $relationshipsDir");
            return;
        }

        $files = scandir($relationshipsDir);
        foreach ($files as $file) {
            if (preg_match('/^(.*)Relationship\.php$/', $file, $matches)) {
                $type = $matches[1];
                if ($type !== 'RelationshipBase') { // Skip the abstract base class
                    $this->availableRelationshipTypes[$type] = "Gravitycar\\Relationships\\{$type}Relationship";
                }
            }
        }

        $this->logger->debug('Discovered relationship types', [
            'available_types' => array_keys($this->availableRelationshipTypes)
        ]);
    }

    /**
     * Validate relationship metadata before instantiation
     */
    protected function validateRelationshipMetadata(array $metadata): void {
        // Required field validation
        $this->validateRequiredFields($metadata);

        // Model existence validation
        $this->validateModelsExist($metadata);

        // Type-specific validation
        $this->validateTypeSpecificConstraints($metadata);

        // Additional fields validation
        $this->validateAdditionalFields($metadata);
    }

    /**
     * Validate required fields are present
     */
    protected function validateRequiredFields(array $metadata): void {
        $requiredFields = ['name', 'type'];

        foreach ($requiredFields as $field) {
            if (!isset($metadata[$field]) || empty($metadata[$field])) {
                throw new GCException("Required field '{$field}' missing from relationship metadata", [
                    'metadata' => $metadata,
                    'required_fields' => $requiredFields
                ]);
            }
        }

        // Type-specific required fields
        $type = $metadata['type'];
        switch ($type) {
            case 'OneToOne':
                if (!isset($metadata['modelA']) || !isset($metadata['modelB'])) {
                    throw new GCException("OneToOne relationships require 'modelA' and 'modelB' fields", [
                        'metadata' => $metadata
                    ]);
                }
                break;

            case 'OneToMany':
                if (!isset($metadata['modelOne']) || !isset($metadata['modelMany'])) {
                    throw new GCException("OneToMany relationships require 'modelOne' and 'modelMany' fields", [
                        'metadata' => $metadata
                    ]);
                }
                break;

            case 'ManyToMany':
                if (!isset($metadata['modelA']) || !isset($metadata['modelB'])) {
                    throw new GCException("ManyToMany relationships require 'modelA' and 'modelB' fields", [
                        'metadata' => $metadata
                    ]);
                }
                break;
        }
    }

    /**
     * Validate that referenced models exist
     */
    protected function validateModelsExist(array $metadata): void {
        $models = [];

        // Extract model names based on relationship type
        if (isset($metadata['modelA'])) $models[] = $metadata['modelA'];
        if (isset($metadata['modelB'])) $models[] = $metadata['modelB'];
        if (isset($metadata['modelOne'])) $models[] = $metadata['modelOne'];
        if (isset($metadata['modelMany'])) $models[] = $metadata['modelMany'];

        foreach ($models as $modelName) {
            $modelClass = "Gravitycar\\Models\\{$modelName}";
            if (!class_exists($modelClass)) {
                throw new GCException("Referenced model class does not exist: {$modelClass}", [
                    'model_name' => $modelName,
                    'model_class' => $modelClass,
                    'relationship_metadata' => $metadata
                ]);
            }
        }
    }

    /**
     * Validate type-specific constraints
     */
    protected function validateTypeSpecificConstraints(array $metadata): void {
        $type = $metadata['type'];
        $constraints = $metadata['constraints'] ?? [];

        switch ($type) {
            case 'OneToOne':
                // OneToOne should have unique constraint
                if (!isset($constraints['unique']) || !$constraints['unique']) {
                    $this->logger->warning('OneToOne relationship should have unique constraint', [
                        'relationship' => $metadata['name']
                    ]);
                }
                break;

            case 'OneToMany':
                // Validate cascade delete options
                $cascadeDelete = $constraints['cascadeDelete'] ?? 'restrict';
                $validCascadeOptions = ['restrict', 'cascade', 'softDelete', 'setDefault'];
                if (!in_array($cascadeDelete, $validCascadeOptions)) {
                    throw new GCException("Invalid cascadeDelete option: {$cascadeDelete}", [
                        'valid_options' => $validCascadeOptions,
                        'relationship' => $metadata['name']
                    ]);
                }
                break;

            case 'ManyToMany':
                // Additional fields validation will be handled separately
                break;
        }
    }

    /**
     * Validate additional fields for ManyToMany relationships
     */
    protected function validateAdditionalFields(array $metadata): void {
        $additionalFields = $metadata['additionalFields'] ?? [];

        if (empty($additionalFields)) {
            return;
        }

        foreach ($additionalFields as $fieldName => $fieldMetadata) {
            if (!isset($fieldMetadata['type'])) {
                throw new GCException("Additional field '{$fieldName}' missing type specification", [
                    'field_name' => $fieldName,
                    'field_metadata' => $fieldMetadata,
                    'relationship' => $metadata['name']
                ]);
            }

            // Check if field type exists
            $fieldType = $fieldMetadata['type'];
            $fieldClass = "Gravitycar\\Fields\\{$fieldType}";
            if (!class_exists($fieldClass)) {
                throw new GCException("Invalid field type for additional field: {$fieldType}", [
                    'field_name' => $fieldName,
                    'field_type' => $fieldType,
                    'field_class' => $fieldClass,
                    'relationship' => $metadata['name']
                ]);
            }
        }
    }

    /**
     * Get registered relationship instance
     */
    public function getRelationship(string $relationshipName): ?RelationshipBase {
        return $this->relationshipRegistry[$relationshipName]['instance'] ?? null;
    }

    /**
     * Get all registered relationships
     */
    public function getAllRelationships(): array {
        $relationships = [];
        foreach ($this->relationshipRegistry as $name => $entry) {
            $relationships[$name] = $entry['instance'];
        }
        return $relationships;
    }
}
