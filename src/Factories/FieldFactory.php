<?php
namespace Gravitycar\Factories;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Factory for creating field instances based on metadata.
 * Discovers available field types and instantiates them dynamically.
 * 
 * Updated for pure dependency injection - no ServiceLocator dependencies.
 */
class FieldFactory {
    protected Logger $logger;
    protected DatabaseConnectorInterface $databaseConnector;
    protected array $availableFieldTypes = [];

    /**
     * Constructor with dependency injection
     * 
     * @param Logger $logger
     * @param DatabaseConnectorInterface $databaseConnector
     */
    public function __construct(Logger $logger, DatabaseConnectorInterface $databaseConnector) {
        $this->logger = $logger;
        $this->databaseConnector = $databaseConnector;
        $this->discoverFieldTypes();
    }



    /**
     * Scan src/fields directory for available field types
     */
    protected function discoverFieldTypes(): void {
        $fieldsDir = __DIR__ . '/../Fields';
        if (!is_dir($fieldsDir)) {
            $this->logger->warning("Fields directory not found: $fieldsDir");
            return;
        }
        $files = scandir($fieldsDir);
        foreach ($files as $file) {
            if (preg_match('/^(.*)Field\.php$/', $file, $matches)) {
                $type = $matches[1];
                $this->availableFieldTypes[$type] = "Gravitycar\\Fields\\{$type}Field";
            }
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
