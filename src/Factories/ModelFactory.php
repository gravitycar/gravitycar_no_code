<?php
namespace Gravitycar\Factories;

use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Factory for creating and retrieving model instances.
 * Provides a centralized way to instantiate ModelBase subclasses using simple model names.
 * 
 * Usage:
 *   $user = ModelFactory::new('Users');
 *   $user = ModelFactory::retrieve('Users', '123');
 */
class ModelFactory {
    
    /**
     * Create a new, empty model instance from model name
     * 
     * @param string $modelName Simple model name (e.g., 'Users', 'Movies')
     * @return ModelBase New model instance ready for use
     * @throws GCException If model class doesn't exist or instantiation fails
     * 
     * @example
     * $user = ModelFactory::new('Users');
     * $user->set('username', 'john@example.com');
     * $user->create();
     */
    public static function new(string $modelName): ModelBase {
        $logger = self::getLogger();
        
        try {
            $modelClass = self::resolveModelClass($modelName);
            self::validateModelClass($modelClass);
            
            $logger->debug('Creating new model instance', [
                'model_name' => $modelName,
                'model_class' => $modelClass
            ]);
            
            // Use ServiceLocator to create model with proper DI
            $model = ServiceLocator::createModel($modelClass);
            
            $logger->info('Model instance created successfully', [
                'model_name' => $modelName,
                'model_class' => $modelClass,
                'model_id' => $model->get('id') ?? 'new'
            ]);
            
            return $model;
            
        } catch (\Exception $e) {
            $logger->error('Failed to create model instance', [
                'model_name' => $modelName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw as GCException if not already
            if ($e instanceof GCException) {
                throw $e;
            }
            
            throw new GCException(
                "Failed to create model instance for '$modelName': " . $e->getMessage(),
                [
                    'model_name' => $modelName,
                    'original_error' => $e->getMessage()
                ],
                0,
                $e
            );
        }
    }
    
    /**
     * Retrieve and populate a model instance from database by ID
     * 
     * @param string $modelName Simple model name (e.g., 'Users', 'Movies')
     * @param string $id Record ID to retrieve
     * @return ModelBase|null Populated model instance or null if not found
     * @throws GCException If model class doesn't exist or database error occurs
     * 
     * @example
     * $user = ModelFactory::retrieve('Users', '123');
     * if ($user) {
     *     echo $user->get('username');
     * }
     */
    public static function retrieve(string $modelName, string $id): ?ModelBase {
        $logger = self::getLogger();
        
        try {
            $modelClass = self::resolveModelClass($modelName);
            self::validateModelClass($modelClass);
            
            $logger->debug('Retrieving model from database', [
                'model_name' => $modelName,
                'model_class' => $modelClass,
                'id' => $id
            ]);
            
            // Get DatabaseConnector through ServiceLocator
            $dbConnector = ServiceLocator::getDatabaseConnector();
            
            // Create a temporary model instance for the query (performance optimized)
            $tempModel = new $modelClass();
            
            // Find record by ID using DatabaseConnector with model instance
            $row = $dbConnector->findById($tempModel, $id);
            
            if ($row === null) {
                $logger->info('Model record not found', [
                    'model_name' => $modelName,
                    'model_class' => $modelClass,
                    'id' => $id
                ]);
                return null;
            }
            
            // Create model instance using our new() method
            $model = self::new($modelName);
            
            // Populate model with database data
            $model->populateFromRow($row);
            
            $logger->info('Model retrieved and populated successfully', [
                'model_name' => $modelName,
                'model_class' => $modelClass,
                'id' => $id,
                'populated_fields' => array_keys($row)
            ]);
            
            return $model;
            
        } catch (\Exception $e) {
            $logger->error('Failed to retrieve model from database', [
                'model_name' => $modelName,
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw as GCException if not already
            if ($e instanceof GCException) {
                throw $e;
            }
            
            throw new GCException(
                "Failed to retrieve model '$modelName' with ID '$id': " . $e->getMessage(),
                [
                    'model_name' => $modelName,
                    'id' => $id,
                    'original_error' => $e->getMessage()
                ],
                0,
                $e
            );
        }
    }
    
    /**
     * Resolve simple model name to full namespaced class name
     * 
     * Converts model names like 'Users' to 'Gravitycar\Models\users\Users'
     * following the framework's directory structure convention.
     * 
     * @param string $modelName Simple model name
     * @return string Full namespaced class name
     * @throws GCException If model name is invalid
     */
    private static function resolveModelClass(string $modelName): string {
        // Validate model name format
        if (empty($modelName) || !is_string($modelName)) {
            throw new GCException('Model name must be a non-empty string', [
                'provided_model_name' => $modelName,
                'type' => gettype($modelName)
            ]);
        }
        
        // Remove any whitespace and validate characters
        $modelName = trim($modelName);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $modelName)) {
            throw new GCException('Model name contains invalid characters', [
                'model_name' => $modelName,
                'allowed_pattern' => '^[A-Za-z_][A-Za-z0-9_]*$'
            ]);
        }
        
        // Convert to framework naming convention
        // 'Users' -> 'Gravitycar\Models\users\Users'
        // 'Movie_Quotes' -> 'Gravitycar\Models\movie_quotes\Movie_Quotes'
        $lowerModelName = strtolower($modelName);
        $fullClassName = "Gravitycar\\Models\\{$lowerModelName}\\{$modelName}";
        
        self::getLogger()->debug('Resolved model class name', [
            'input_model_name' => $modelName,
            'resolved_class' => $fullClassName,
            'directory_path' => "src/Models/{$lowerModelName}/"
        ]);
        
        return $fullClassName;
    }
    
    /**
     * Validate that the resolved model class exists and is instantiable
     * 
     * @param string $modelClass Full namespaced class name
     * @throws GCException If class doesn't exist or is not valid
     */
    private static function validateModelClass(string $modelClass): void {
        if (!class_exists($modelClass)) {
            // Extract model name from class for better error message
            $modelName = basename(str_replace('\\', '/', $modelClass));
            $expectedPath = str_replace('\\', '/', $modelClass);
            $expectedPath = str_replace('Gravitycar/Models/', 'src/Models/', $expectedPath) . '.php';
            
            throw new GCException("Model class not found: {$modelClass}", [
                'model_class' => $modelClass,
                'model_name' => $modelName,
                'expected_file_path' => $expectedPath,
                'suggestion' => "Ensure the model class exists at {$expectedPath}"
            ]);
        }
        
        // Validate that it extends ModelBase
        if (!is_subclass_of($modelClass, ModelBase::class)) {
            throw new GCException("Class must extend ModelBase: {$modelClass}", [
                'model_class' => $modelClass,
                'required_parent' => ModelBase::class,
                'actual_parents' => class_parents($modelClass)
            ]);
        }
        
        // Validate that it's not abstract
        $reflection = new \ReflectionClass($modelClass);
        if ($reflection->isAbstract()) {
            throw new GCException("Cannot instantiate abstract model class: {$modelClass}", [
                'model_class' => $modelClass,
                'is_abstract' => true
            ]);
        }
        
        self::getLogger()->debug('Model class validation passed', [
            'model_class' => $modelClass,
            'extends_model_base' => true,
            'is_instantiable' => true
        ]);
    }
    
    /**
     * Get logger instance from ServiceLocator
     * 
     * @return Logger
     */
    private static function getLogger(): Logger {
        return ServiceLocator::getLogger();
    }
    
    /**
     * Get list of available model names by scanning the Models directory
     * 
     * This is a utility method for debugging and development purposes.
     * 
     * @return array Array of available model names
     */
    public static function getAvailableModels(): array {
        $logger = self::getLogger();
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
