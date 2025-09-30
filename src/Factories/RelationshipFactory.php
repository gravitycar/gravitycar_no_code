<?php
namespace Gravitycar\Factories;

use Gravitycar\Relationships\RelationshipBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Monolog\Logger;

/**
 * Factory for creating, validating, and managing relationship instances.
 * Handles all cross-cutting concerns that don't belong in individual relationship classes.
 * 
 * Updated for pure dependency injection - no ServiceLocator dependencies.
 */
class RelationshipFactory {
    protected Logger $logger;
    protected MetadataEngineInterface $metadataEngine;
    protected DatabaseConnectorInterface $databaseConnector;
    protected string $owner; // Kept for backward compatibility during transition
    protected array $availableRelationshipTypes = [];
    protected array $relationshipRegistry = [];
    protected array $relationshipKeys = [];

    /**
     * Constructor with dependency injection
     * 
     * @param Logger $logger
     * @param MetadataEngineInterface $metadataEngine
     * @param DatabaseConnectorInterface $databaseConnector
     * @param string $owner Owner identifier (defaults to 'ModelBase' for pure DI)
     */
    public function __construct(
        Logger $logger, 
        MetadataEngineInterface $metadataEngine, 
        DatabaseConnectorInterface $databaseConnector,
        string $owner = 'ModelBase'
    ) {
        $this->logger = $logger;
        $this->metadataEngine = $metadataEngine;
        $this->databaseConnector = $databaseConnector;
        $this->owner = $owner;
        $this->discoverRelationshipTypes();
    }



    /**
     * Create a relationship instance from relationship name (updated to use MetadataEngine)
     */
    public function createRelationship(string $relationshipName): RelationshipBase {
        try {
            // Load metadata using MetadataEngine (cached and optimized)
            $metadata = $this->metadataEngine->getRelationshipMetadata($relationshipName);

            // Validate metadata before creating instance
            $this->validateRelationshipMetadata($metadata);

            // Check for circular dependencies
            $this->detectCircularDependencies($metadata);

            // Create instance with new constructor pattern (Logger only)
            $type = $metadata['type'];
            $className = $this->availableRelationshipTypes[$type] ?? "Gravitycar\\Relationships\\{$type}Relationship";

            if (!class_exists($className)) {
                throw new GCException("Relationship class not found for type: $type", [
                    'relationship_type' => $type,
                    'expected_class' => $className,
                    'available_types' => array_keys($this->availableRelationshipTypes)
                ]);
            }

            // Create relationship instance with relationship name parameter
            $relationship = new $className($relationshipName);

            // Register the relationship
            $this->registerRelationship($relationship, $metadata);

            $this->logger->debug('Relationship created successfully via MetadataEngine', [
                'relationship_name' => $metadata['name'],
                'relationship_type' => $type,
                'owner' => $this->owner
            ]);

            return $relationship;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create relationship', [
                'relationship_name' => $relationshipName,
                'owner' => $this->owner,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Build metadata file path for relationship
     */
    /**
     * Build metadata file path for relationship (updated to use MetadataEngine)
     */
    protected function buildMetadataFilePath(string $relationshipName): string {
        return $this->metadataEngine->buildRelationshipMetadataPath($relationshipName);
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
            'owner' => $this->owner
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
        // Get project root directory (go up from src/Factories to project root)
        $projectRoot = dirname(dirname(__DIR__));
        $relationshipsDir = $projectRoot . '/src/Relationships';
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
            // Convert model name to lowercase for directory structure
            $lowerModelName = strtolower($modelName);
            $modelClass = "Gravitycar\\Models\\{$lowerModelName}\\{$modelName}";
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
