<?php
namespace Gravitycar\Factories;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Factory for creating field instances based on metadata.
 * Discovers available field types and instantiates them dynamically.
 */
class FieldFactory {
    /** @var object */
    protected object $model;
    /** @var Logger */
    protected Logger $logger;
    /** @var array */
    protected array $availableFieldTypes = [];

    public function __construct(object $model) {
        $this->model = $model;
        $this->logger = ServiceLocator::getLogger();
        $this->discoverFieldTypes();
    }

    /**
     * Scan src/fields directory for available field types
     */
    protected function discoverFieldTypes(): void {
        $fieldsDir = __DIR__ . '/../fields';
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
     */
    public function createField(array $metadata): FieldBase {
        $type = $metadata['type'] ?? 'Text';
        $className = $this->availableFieldTypes[$type] ?? "Gravitycar\\Fields\\TextField";
        if (!class_exists($className)) {
            throw new GCException("Field class not found for type: $type",
                ['field_type' => $type, 'expected_class' => $className]);
        }
        
        // Use ServiceLocator to create field with proper dependencies
        $field = \Gravitycar\Core\ServiceLocator::createField($className, $metadata);

        // Set the table name from the model
        $field->setTableName($this->model->getTableName());

        return $field;
    }

    /**
     * Get all available field types
     */
    public function getAvailableFieldTypes(): array {
        return array_keys($this->availableFieldTypes);
    }
}
