<?php
namespace Gravitycar\Factories;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Factory for creating field instances based on metadata.
 * Gets available field types from MetadataEngine cached data instead of filesystem scanning.
 * 
 * Updated for pure dependency injection - no ServiceLocator dependencies.
 */
class FieldFactory {
    protected Logger $logger;
    protected DatabaseConnectorInterface $databaseConnector;
    protected MetadataEngineInterface $metadataEngine;
    protected array $availableFieldTypes = [];

    /**
     * Constructor with dependency injection
     * 
     * @param Logger $logger
     * @param DatabaseConnectorInterface $databaseConnector
     * @param MetadataEngineInterface $metadataEngine
     */
    public function __construct(Logger $logger, DatabaseConnectorInterface $databaseConnector, MetadataEngineInterface $metadataEngine) {
        $this->logger = $logger;
        $this->databaseConnector = $databaseConnector;
        $this->metadataEngine = $metadataEngine;
        $this->loadFieldTypesFromCache();
    }



    /**
     * Load field types from MetadataEngine cached data instead of filesystem scanning
     */
    protected function loadFieldTypesFromCache(): void {
        try {
            $fieldTypeDefinitions = $this->metadataEngine->getFieldTypeDefinitions();
            
            foreach ($fieldTypeDefinitions as $fieldType => $definition) {
                $className = $definition['class'] ?? "Gravitycar\\Fields\\{$fieldType}Field";
                $this->availableFieldTypes[$fieldType] = $className;
            }
            
            $this->logger->debug('Loaded field types from cache', [
                'field_type_count' => count($this->availableFieldTypes),
                'field_types' => array_keys($this->availableFieldTypes)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to load field types from cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback to empty array if cache loading fails
            $this->availableFieldTypes = [];
        }
    }

    /**
     * Create a field instance from metadata
     * 
     * @param array $metadata Field metadata including type
     * @param string|null $tableName Table name for the field (optional for backward compatibility)
     * @return FieldBase Created field instance
     */
    public function createField(array $metadata, ?string $tableName = null): FieldBase {
        $type = $metadata['type'] ?? 'Text';
        $className = $this->availableFieldTypes[$type] ?? "Gravitycar\\Fields\\TextField";
        
        if (!class_exists($className)) {
            throw new GCException("Field class not found for type: $type",
                ['field_type' => $type, 'expected_class' => $className]);
        }
        
        // Create field instance directly (no ServiceLocator)
        $field = new $className($metadata);

        // Set the table name if provided
        if ($tableName) {
            $field->setTableName($tableName);
        }

        return $field;
    }

    /**
     * Get all available field types
     */
    public function getAvailableFieldTypes(): array {
        return array_keys($this->availableFieldTypes);
    }
}
