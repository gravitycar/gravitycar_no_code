<?php
namespace Gravitycar\Factories;

use Gravitycar\ComponentGenerator\ComponentGeneratorBase;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Factory for creating React component generator instances based on metadata.
 * Discovers available component generator types and instantiates them dynamically.
 */
class ComponentGeneratorFactory {
    /** @var Logger */
    protected Logger $logger;
    /** @var array */
    protected array $availableComponentGenerators = [];

    public function __construct() {
        $this->logger = ServiceLocator::getLogger();
        $this->discoverComponentGenerators();
    }

    /**
     * Scan src/componentGenerator directory for available component generator types
     */
    protected function discoverComponentGenerators(): void {
        $generatorDir = __DIR__ . '/../componentGenerator';
        if (!is_dir($generatorDir)) {
            $this->logger->warning("Component generator directory not found: $generatorDir");
            return;
        }
        $files = scandir($generatorDir);
        foreach ($files as $file) {
            if (preg_match('/^(.*)ComponentGenerator\.php$/', $file, $matches)) {
                $type = $matches[1];
                $this->availableComponentGenerators[$type] = "Gravitycar\\ComponentGenerator\\{$type}ComponentGenerator";
            }
        }
    }

    /**
     * Create a component generator instance from metadata
     */
    public function createComponentGenerator(array $metadata): ComponentGeneratorBase {
        $type = $metadata['type'] ?? 'Default';
        $className = $this->availableComponentGenerators[$type] ?? "Gravitycar\\ComponentGenerator\\DefaultComponentGenerator";
        if (!class_exists($className)) {
            throw new GCException("Component generator class not found for type: $type",
                ['generator_type' => $type, 'expected_class' => $className]);
        }
        return new $className($metadata, $this->logger);
    }

    /**
     * Get all available component generator types
     */
    public function getAvailableComponentGenerators(): array {
        return array_keys($this->availableComponentGenerators);
    }
}
