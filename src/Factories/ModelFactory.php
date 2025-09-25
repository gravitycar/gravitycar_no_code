<?php
namespace Gravitycar\Factories;

use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Contracts\MetadataEngineInterface;
use Aura\Di\Container;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating and retrieving model instances.
 * Phase 6.1 - Instance-based factory with full DI container integration
 */
class ModelFactory {
    private LoggerInterface $logger;
    private DatabaseConnectorInterface $dbConnector;
    private MetadataEngineInterface $metadataEngine;
    private Container $container;

    /**
     * Constructor with dependency injection
     */
    public function __construct(
        Container $container,
        LoggerInterface $logger, 
        DatabaseConnectorInterface $dbConnector,
        MetadataEngineInterface $metadataEngine
    ) {
        $this->container = $container;
        $this->logger = $logger;
        $this->dbConnector = $dbConnector;
        $this->metadataEngine = $metadataEngine;
    }

    /**
     * Create a new, empty model instance from model name
     */
    public function new(string $modelName): ModelBase {
        try {
            $modelClass = $this->resolveModelClass($modelName);
            $this->validateModelClass($modelClass);
            
            $this->logger->debug('Creating new model instance via ContainerConfig', [
                'model_name' => $modelName,
                'model_class' => $modelClass
            ]);
            
            // Use ContainerConfig::createModel for proper pure DI instantiation
            $model = \Gravitycar\Core\ContainerConfig::createModel($modelClass);
            
            $this->logger->debug('Model created via ContainerConfig', [
                'model_name' => $modelName,
                'model_class' => $modelClass,
                'instance_id' => spl_object_id($model)
            ]);
            
            return $model;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create model instance', [
                'model_name' => $modelName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new GCException(
                "Failed to create model instance for '{$modelName}': " . $e->getMessage(),
                [], 500,
                $e
            );
        }
    }

    /**
     * Retrieve and populate a model instance from database by ID
     */
    public function retrieve(string $modelName, string $id): ?ModelBase {
        try {
            $this->logger->debug('Retrieving model by ID', [
                'model_name' => $modelName,
                'id' => $id
            ]);
            
            // Create empty instance first
            $model = $this->new($modelName);
            
            // Try to load data from database using the model instance
            $dbData = $this->dbConnector->findById($model, $id);

            
            if ($dbData === null) {
                $this->logger->debug('Model not found in database', [
                    'model_name' => $modelName,
                    'id' => $id
                ]);
                return null;
            }
            
            // Populate model with database data
            $model->populateFromAPI($dbData);
            
            $this->logger->debug('Model retrieved and populated successfully', [
                'model_name' => $modelName,
                'id' => $id,
                'data_keys' => array_keys($dbData)
            ]);
            
            return $model;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve model', [
                'model_name' => $modelName,
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new GCException(
                "Failed to retrieve {$modelName} with ID '{$id}': " . $e->getMessage(),
                [], 500,
                $e
            );
        }
    }

    /**
     * Create a new model instance populated with data
     */
    public function createNew(string $modelName, array $data = []): ModelBase {
        $model = $this->new($modelName);
        if ($data) {
            $model->populateFromAPI($data);
        }
        return $model;
    }

    /**
     * Find existing model or create new one
     */
    public function findOrNew(string $modelName, string $id): ModelBase {
        return $this->retrieve($modelName, $id) ?? $this->new($modelName);
    }

    /**
     * Create and save model in one call
     */
    public function create(string $modelName, array $data): ModelBase {
        $model = $this->createNew($modelName, $data);
        $model->create();
        return $model;
    }

    /**
     * Update existing record
     */
    public function update(string $modelName, string $id, array $data): ?ModelBase {
        $model = $this->retrieve($modelName, $id);
        if (!$model) return null;
        
        $model->populateFromAPI($data);
        $model->update();
        return $model;
    }

    // === PRIVATE HELPER METHODS ===

    /**
     * Resolve simple model name to full namespaced class name
     */
    private function resolveModelClass(string $modelName): string {
        // Validate model name format
        if (empty($modelName) || !is_string($modelName)) {
            throw new GCException("Invalid model name provided: must be non-empty string");
        }
        
        // Convert to proper case and build class name
        $modelName = ucfirst($modelName);
        $lowerCaseModelName = strtolower($modelName);
        $modelClass = "Gravitycar\\Models\\{$lowerCaseModelName}\\{$modelName}";
        
        return $modelClass;
    }

    /**
     * Validate that a model class exists and can be instantiated
     */
    private function validateModelClass(string $modelClass): void {
        if (!class_exists($modelClass)) {
            throw new GCException("Model class does not exist: {$modelClass}");
        }
        
        if (!is_subclass_of($modelClass, ModelBase::class)) {
            throw new GCException("Model class must extend ModelBase: {$modelClass}");
        }
        
        $reflection = new \ReflectionClass($modelClass);
        if (!$reflection->isInstantiable()) {
            throw new GCException("Model class is not instantiable: {$modelClass}");
        }
    }

    // === INSTANCE METHODS ===

    /**
     * Get list of available model names by scanning the Models directory
     */
    public function getAvailableModels(): array {
        $modelsDir = __DIR__ . '/../Models';
        $availableModels = [];
        
        if (!is_dir($modelsDir)) {
            $this->logger->warning('Models directory not found', [
                'expected_path' => $modelsDir
            ]);
            return [];
        }
        
        try {
            $directories = scandir($modelsDir);
            
            foreach ($directories as $dir) {
                if ($dir === '.' || $dir === '..' || !is_dir($modelsDir . '/' . $dir)) {
                    continue;
                }
                
                // Check if directory contains a model class file
                $potentialModelFile = $modelsDir . '/' . $dir . '/' . ucfirst($dir) . '.php';
                if (file_exists($potentialModelFile)) {
                    $availableModels[] = ucfirst($dir);
                }
            }
            
            $this->logger->debug('Available models discovered', [
                'models_directory' => $modelsDir,
                'available_models' => $availableModels,
                'count' => count($availableModels)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to scan models directory', [
                'models_directory' => $modelsDir,
                'error' => $e->getMessage()
            ]);
        }
        
        return $availableModels;
    }

    // === STATIC LEGACY METHODS (For backward compatibility) ===

    /**
     * @deprecated Use instance method getAvailableModels() instead
     * Get list of available model names by scanning the Models directory
     */
    public static function getAvailableModelsStatic(): array {
        $logger = ServiceLocator::getLogger();
        $modelsDir = __DIR__ . '/../Models';
        $availableModels = [];
        
        if (!is_dir($modelsDir)) {
            $logger->warning('Models directory not found', [
                'expected_path' => $modelsDir
            ]);
            return [];
        }
        
        try {
            $directories = scandir($modelsDir);
            
            foreach ($directories as $dir) {
                if ($dir === '.' || $dir === '..' || !is_dir($modelsDir . '/' . $dir)) {
                    continue;
                }
                
                // Check if directory contains a model class file
                $potentialModelFile = $modelsDir . '/' . $dir . '/' . ucfirst($dir) . '.php';
                if (file_exists($potentialModelFile)) {
                    $availableModels[] = ucfirst($dir);
                }
            }
            
            $logger->debug('Available models discovered', [
                'models_directory' => $modelsDir,
                'available_models' => $availableModels,
                'count' => count($availableModels)
            ]);
            
        } catch (\Exception $e) {
            $logger->error('Failed to scan models directory', [
                'models_directory' => $modelsDir,
                'error' => $e->getMessage()
            ]);
        }
        
        return $availableModels;
    }
}
