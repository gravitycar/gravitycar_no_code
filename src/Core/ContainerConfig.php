<?php
namespace Gravitycar\Core;

use Aura\Di\Container;
use Aura\Di\ContainerBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Gravitycar\Core\Config;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Schema\SchemaGenerator;
use Gravitycar\Factories\ValidationRuleFactory;
use Exception;

/**
 * Container configuration for Gravitycar framework.
 * Defines all service definitions and their dependencies.
 */
class ContainerConfig {
    /** @var Container */
    private static ?Container $container = null;

    /**
     * Get the configured container instance
     */
    public static function getContainer(): Container {
        if (self::$container === null) {
            self::$container = self::buildContainer();
        }
        return self::$container;
    }

    /**
     * Build and configure the DI container
     */
    private static function buildContainer(): Container {
        try {
            $builder = new ContainerBuilder();
            $di = $builder->newInstance();

            // Configure services with error handling
            self::configureCoreServices($di);
            self::configureFactories($di);
            self::configureApplicationServices($di);

            // Don't call enableAutoWiringForNamespaces here - it causes infinite loop
            // Auto-wiring namespace setup is now done in autoWire() method

            return $di;
        } catch (Exception $e) {
            // Log the error and fall back to minimal container
            error_log("Container initialization failed: " . $e->getMessage());
            return self::buildFallbackContainer($e);
        }
    }

    /**
     * Configure core framework services as singletons
     */
    private static function configureCoreServices(Container $di): void {
        // Logger - singleton with error handling and fallback
        $di->set('logger', $di->lazy(function() {
            try {
                $logger = new Logger('gravitycar');

                // Try to create log directory
                $logDir = dirname('logs/gravitycar.log');
                if (!is_dir($logDir)) {
                    if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                        throw new \Exception("Failed to create log directory: $logDir");
                    }
                }

                $handler = new StreamHandler('logs/gravitycar.log', Logger::INFO);
                $logger->pushHandler($handler);
                return $logger;
            } catch (Exception $e) {
                // Fallback to stderr handler if file logging fails
                $logger = new Logger('gravitycar');
                $logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));
                $logger->warning('File logging failed, using stderr: ' . $e->getMessage());
                return $logger;
            }
        }));

        // Config - singleton with error handling for missing/invalid config files  
        $di->set('config', $di->lazy(function() use ($di) {
            try {
                return new Config();
            } catch (\Gravitycar\Exceptions\GCException $e) {
                $logger = $di->get('logger');
                $logger->error('Config loading failed: ' . $e->getMessage());

                // Return minimal config with defaults - ConfigStub constructor: (Logger, Exception = null)
                return new \Gravitycar\Core\ConfigStub($logger, $e);
            } catch (Exception $e) {
                $logger = $di->get('logger');
                $logger->error('Unexpected config error: ' . $e->getMessage());
                return new \Gravitycar\Core\ConfigStub($logger, $e);
            }
        }));

        // DatabaseConnector - singleton with error handling for invalid DB credentials
        $di->set('database_connector', $di->lazy(function() use ($di) {
            try {
                $config = $di->get('config');
                $dbParams = $config->getDatabaseParams();

                if (empty($dbParams)) {
                    $logger = $di->get('logger');
                    throw new \Gravitycar\Exceptions\GCException(
                        'Database parameters not configured',
                        ['config_check' => 'dbParams empty or null'] // Context array
                    );
                }

                return new DatabaseConnector($di->get('logger'), $dbParams);
            } catch (Exception $e) {
                $logger = $di->get('logger');
                $logger->error('Database connector failed: ' . $e->getMessage());

                // Return stub connector - DatabaseConnectorStub constructor: (Logger, Exception)
                return new \Gravitycar\Database\DatabaseConnectorStub($logger, $e);
            }
        }));

        // MetadataEngine - singleton managed by DI container
        $di->set('metadata_engine', $di->lazy(function() use ($di) {
            try {
                // DI container manages singleton behavior, but we still use getInstance 
                // to ensure consistency if MetadataEngine is accessed directly elsewhere
                return MetadataEngine::getInstance(
                    $di->get('logger'),
                    'src/Models',
                    'src/Relationships',
                    'cache/'
                );
            } catch (Exception $e) {
                $logger = $di->get('logger');
                $logger->error('MetadataEngine initialization failed: ' . $e->getMessage());

                // Return stub that provides empty metadata - MetadataEngineStub constructor: (Logger, Exception)
                return new \Gravitycar\Metadata\MetadataEngineStub($logger, $e);
            }
        }));

        // CoreFieldsMetadata - singleton service for managing core field definitions
        $di->set('core_fields_metadata', $di->lazy(function() use ($di) {
            return new \Gravitycar\Metadata\CoreFieldsMetadata();
        }));
    }

    /**
     * Build fallback container for critical failures
     */
    private static function buildFallbackContainer(Exception $originalError): Container {
        $builder = new ContainerBuilder();
        $di = $builder->newInstance();

        // Minimal logger that works without file system
        $di->set('logger', $di->lazy(function() use ($originalError) {
            $logger = new Logger('gravitycar-fallback');
            $logger->pushHandler(new StreamHandler('php://stderr', Logger::ERROR));
            $logger->error('Container initialization failed, using fallback: ' . $originalError->getMessage());
            return $logger;
        }));

        // Stub services that provide error messages
        $di->set('config', $di->lazy(function() use ($di, $originalError) {
            return new \Gravitycar\Core\ConfigStub($di->get('logger'), $originalError);
        }));

        $di->set('database_connector', $di->lazy(function() use ($di, $originalError) {
            return new \Gravitycar\Database\DatabaseConnectorStub($di->get('logger'), $originalError);
        }));

        $di->set('metadata_engine', $di->lazy(function() use ($di, $originalError) {
            return new \Gravitycar\Metadata\MetadataEngineStub($di->get('logger'), $originalError);
        }));

        return $di;
    }

    /**
     * Configure factory services
     */
    private static function configureFactories(Container $di): void {
        // ValidationRuleFactory - singleton
        $di->set('validation_rule_factory', $di->lazyNew(ValidationRuleFactory::class));
    }

    /**
     * Configure application services
     */
    private static function configureApplicationServices(Container $di): void {
        // SchemaGenerator - prototype (new instance each time)
        $di->set('schema_generator', $di->lazyNew(SchemaGenerator::class));

        // Router - prototype with ServiceLocator instance
        $di->set('router', $di->lazyNew(\Gravitycar\Api\Router::class, [
            'serviceLocator' => $di->lazyGet('metadata_engine') // Backward compatibility - pass MetadataEngine as serviceLocator
        ]));

        // Installer model - prototype
        $di->set('installer', $di->lazyNew(\Gravitycar\Models\installer\Installer::class, [
            'logger' => $di->lazyGet('logger')
        ]));
    }

    /**
     * Create a new model instance with dependencies
     */
    public static function createModel(string $modelClass): object {
        // Check if the model class exists before trying to instantiate it
        if (!class_exists($modelClass)) {
            throw new \Gravitycar\Exceptions\GCException(
                "Model class does not exist: {$modelClass}",
                ['model_class' => $modelClass]
            );
        }

        $di = self::getContainer();
        return new $modelClass(
            $di->get('logger')
        );
    }

    /**
     * Create a new field instance with dependencies
     */
    public static function createField(string $fieldClass, array $metadata): object {
        return new $fieldClass($metadata);
    }

    /**
     * Create a new validation rule instance with dependencies
     */
    public static function createValidationRule(string $ruleClass): object {
        return new $ruleClass();
    }

    /**
     * Create a new FieldFactory instance with dependencies and specific model
     */
    public static function createFieldFactory(\Gravitycar\Models\ModelBase $model): object {
        return new \Gravitycar\Factories\FieldFactory($model);
    }

    /**
     * Create a new RelationshipFactory instance with dependencies and specific model
     */
    public static function createRelationshipFactory(\Gravitycar\Models\ModelBase $model): object {
        $di = self::getContainer();
        return new \Gravitycar\Factories\RelationshipFactory(
            get_class($model),
            $di->get('logger')
        );
    }

    /**
     * Create a new relationship instance with dependencies
     */
    public static function createRelationship(string $relationshipClass, array $metadata): object {
        $di = self::getContainer();
        return new $relationshipClass(
            $metadata,
            $di->get('logger')
        );
    }

    /**
     * Reset container for testing
     */
    public static function resetContainer(): void {
        self::$container = null;
    }

    /**
     * Configure container for testing with mocks
     */
    public static function configureForTesting(array $testServices = []): Container {
        $builder = new ContainerBuilder();
        $di = $builder->newInstance();

        // Configure test services or use defaults
        foreach ($testServices as $serviceName => $testInstance) {
            $di->set($serviceName, $testInstance);
        }

        // Fill in any missing services with defaults
        self::configureCoreServices($di);
        self::configureFactories($di);
        self::configureApplicationServices($di);

        self::$container = $di;
        return $di;
    }

    /**
     * Auto-wire a class if not explicitly configured
     */
    public static function autoWire(string $className): object {
        $di = self::getContainer();

        try {
            // Check if service is already configured
            if ($di->has($className)) {
                return $di->get($className);
            }

            // Auto-wire the class with recursion protection
            return self::createInstanceWithAutoWiring($className, $di, [], 0);

        } catch (Exception $e) {
            // Use GCException with new constructor signature (no logger parameter)
            throw new \Gravitycar\Exceptions\GCException(
                "Auto-wiring failed for class $className: " . $e->getMessage(),
                ['class' => $className, 'original_error' => $e->getMessage()], // Context array
                0, // Error code
                $e // Previous exception
            );
        }
    }

    /**
     * Create instance with automatic dependency resolution
     */
    private static function createInstanceWithAutoWiring(string $className, Container $di, array $resolutionStack = [], int $depth = 0): object {
        // Prevent infinite recursion by limiting depth
        if ($depth > 10) {
            throw new \Exception("Auto-wiring depth limit exceeded (10) for class: $className. Possible circular dependency.");
        }

        if (!class_exists($className)) {
            throw new \Exception("Class $className does not exist");
        }

        // Check for circular dependency
        if (in_array($className, $resolutionStack)) {
            throw new \Exception("Circular dependency detected: " . implode(' -> ', $resolutionStack) . " -> $className");
        }

        $resolutionStack[] = $className;

        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            // No constructor, create simple instance
            return new $className();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencies[] = self::resolveDependency($parameter, $di, $resolutionStack, $depth + 1);
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve a single constructor dependency
     */
    private static function resolveDependency(\ReflectionParameter $parameter, Container $di, array $resolutionStack = [], int $depth = 0): mixed {
        $type = $parameter->getType();

        if (!$type || $type->isBuiltin()) {
            // Handle primitive types or no type hint
            return self::resolvePrimitiveDependency($parameter, $di);
        }

        $typeName = $type->getName();

        // Map common dependencies to container services
        $serviceMap = [
            'Monolog\\Logger' => 'logger',
            'Gravitycar\\Core\\Config' => 'config',
            'Gravitycar\\Database\\DatabaseConnector' => 'database_connector',
            'Gravitycar\\Metadata\\MetadataEngine' => 'metadata_engine',
            'Gravitycar\\Factories\\ValidationRuleFactory' => 'validation_rule_factory',
        ];

        if (isset($serviceMap[$typeName])) {
            // Always use container services for mapped types - don't auto-wire them
            try {
                return $di->get($serviceMap[$typeName]);
            } catch (Exception $e) {
                // If container service fails, try fallbacks
                if ($parameter->allowsNull()) {
                    return null;
                }
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }
                throw new \Exception("Cannot resolve container service '{$serviceMap[$typeName]}' for parameter {$parameter->getName()}: " . $e->getMessage());
            }
        }

        // Check if this type is already being resolved (circular dependency check)
        if (in_array($typeName, $resolutionStack)) {
            // Handle circular dependency - try to use nullable or default value
            if ($parameter->allowsNull()) {
                return null;
            }
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new \Exception("Circular dependency detected and cannot be resolved for parameter: {$parameter->getName()} of type $typeName");
        }

        // Auto-wire all Gravitycar classes except excluded namespaces
        $excludedNamespaces = [
            'Gravitycar\\External\\',  // Third-party integrations
            'Gravitycar\\Legacy\\',    // Legacy code that shouldn't be auto-wired
            'Gravitycar\\Tests\\',     // Test classes
        ];

        $isAutoWirable = str_starts_with($typeName, 'Gravitycar\\');

        // Check if this type is in an excluded namespace
        foreach ($excludedNamespaces as $excluded) {
            if (str_starts_with($typeName, $excluded)) {
                $isAutoWirable = false;
                break;
            }
        }

        if (!$isAutoWirable) {
            // Not auto-wirable - try fallbacks
            if ($parameter->allowsNull()) {
                return null;
            }
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new \Exception("Cannot auto-wire type $typeName - not in auto-wirable namespaces and no default value available");
        }

        // Auto-wire the dependency recursively with stack protection
        return self::createInstanceWithAutoWiring($typeName, $di, $resolutionStack, $depth);
    }

    /**
     * Resolve primitive type dependencies
     */
    private static function resolvePrimitiveDependency(\ReflectionParameter $parameter, Container $di): mixed {
        $paramName = $parameter->getName();

        // Common parameter name mappings
        $primitiveMap = [
            'metadata' => [],
            'dbParams' => function() use ($di) {
                return $di->get('config')->getDatabaseParams();
            },
            'modelsDirPath' => 'src/models',
            'relationshipsDirPath' => 'src/relationships',
            'cacheDirPath' => 'cache/',
        ];

        if (isset($primitiveMap[$paramName])) {
            $value = $primitiveMap[$paramName];
            return is_callable($value) ? $value() : $value;
        }

        // Check for default value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Check if nullable
        if ($parameter->allowsNull()) {
            return null;
        }

        throw new \Exception("Cannot resolve primitive parameter: {$parameter->getName()}");
    }

    /**
     * Register auto-wiring for specific namespaces
     */
    public static function enableAutoWiringForNamespaces(array $namespaces): void {
        $di = self::getContainer();

        foreach ($namespaces as $namespace) {
            // Register namespace pattern for auto-loading
            $di->setters[$namespace . '*'] = function($instance) use ($di) {
                // Auto-inject common services if properties exist and are null
                if (property_exists($instance, 'logger') && !isset($instance->logger)) {
                    $instance->logger = $di->get('logger');
                }
                if (property_exists($instance, 'config') && !isset($instance->config)) {
                    $instance->config = $di->get('config');
                }
                return $instance;
            };
        }
    }
}
