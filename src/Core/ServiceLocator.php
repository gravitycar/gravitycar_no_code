<?php
namespace Gravitycar\Core;

use Aura\Di\Container;
use Gravitycar\Contracts\LoggerInterface;
use Monolog\Logger;
use Exception;
use \Exception as BaseException;
use Gravitycar\Core\Config;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Schema\SchemaGenerator;
use Gravitycar\Factories\ValidationRuleFactory;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Models\Users;

/**
 * Service Locator for easy access to framework services.
 * Provides typed methods for common service resolution.
 */
class ServiceLocator {
    private static ?Container $container = null;
    private static ?\Gravitycar\Models\ModelBase $cachedCurrentUser = null;

    /**
     * Get the DI container
     */
    public static function getContainer(): Container {
        if (self::$container === null) {
            self::$container = ContainerConfig::getContainer();
        }
        return self::$container;
    }

    /**
     * Get the logger service
     */
    public static function getLogger(): Logger {
        return self::getContainer()->get('logger');
    }

    /**
     * Get the config service
     */
    public static function getConfig(): Config {
        try {
            return self::getContainer()->get('config');
        } catch (Exception $e) {
            $logger = self::getLogger();
            $logger->error('Failed to get Config service: ' . $e->getMessage());

            // Return minimal config stub with proper constructor parameters
            return new ConfigStub($logger, $e);
        }
    }

    /**
     * Get the database connector service
     */
    public static function getDatabaseConnector(): DatabaseConnector {
        try {
            $connector = self::getContainer()->get('database_connector');

            // If we got a stub, that means database is unavailable
            if (method_exists($connector, 'isStub') && $connector->isStub()) {
                throw new \Gravitycar\Exceptions\GCException(
                    'Database service unavailable. Please check your configuration and ensure the database is accessible.',
                    ['stub_error' => $connector->getOriginalError()->getMessage()], // Context array
                    0,  // Error code
                    $connector->getOriginalError() // Previous exception
                );
            }

            return $connector;
        } catch (\Gravitycar\Exceptions\GCException $e) {
            // Re-throw framework exceptions
            throw $e;
        } catch (Exception $e) {
            $logger = self::getLogger();
            $logger->error('Failed to get DatabaseConnector service: ' . $e->getMessage());

            throw new \Gravitycar\Exceptions\GCException(
                'Database service initialization failed: ' . $e->getMessage(),
                ['original_error' => $e->getMessage()], // Context array
                0,  // Error code
                $e  // Previous exception
            );
        }
    }

    /**
     * Get the metadata engine service (via DI container)
     */
    public static function getMetadataEngine(): MetadataEngine {
        try {
            return self::getContainer()->get('metadata_engine');
        } catch (Exception $e) {
            $logger = self::getLogger();
            $logger->error('Failed to get MetadataEngine service: ' . $e->getMessage());

            // Return stub engine with proper constructor parameters
            return new \Gravitycar\Metadata\MetadataEngineStub($logger, $e);
        }
    }

    /**
     * Get a new schema generator instance
     */
    public static function getSchemaGenerator(): SchemaGenerator {
        try {
            return self::getContainer()->get('schema_generator');
        } catch (Exception $e) {
            $logger = self::getLogger();
            $logger->error('Failed to get SchemaGenerator service: ' . $e->getMessage());

            throw new \Gravitycar\Exceptions\GCException(
                'Schema generation service unavailable. This may be due to database connectivity issues.',
                ['original_error' => $e->getMessage()], // Context array
                0,  // Error code
                $e  // Previous exception
            );
        }
    }

    /**
     * Get a new router instance
     */
    public static function getRouter(): \Gravitycar\Api\Router {
        try {
            return self::getContainer()->get('router');
        } catch (Exception $e) {
            $logger = self::getLogger();
            $logger->error('Failed to get Router service: ' . $e->getMessage());

            throw new \Gravitycar\Exceptions\GCException(
                'API routing service unavailable. Please check application configuration.',
                ['original_error' => $e->getMessage()], // Context array
                0,  // Error code
                $e  // Previous exception
            );
        }
    }

    /**
     * Get the validation rule factory service
     */
    public static function getValidationRuleFactory(): ValidationRuleFactory {
        return self::getContainer()->get('validation_rule_factory');
    }

    /**
     * Get a new installer model instance
     */
    public static function getInstaller(): \Gravitycar\Models\installer\Installer {
        return self::getContainer()->get('installer');
    }

    /**
     * Create a new model instance with proper dependencies
     */
    public static function createModel(string $modelClass): object {
        return ContainerConfig::createModel($modelClass);
    }

    /**
     * Create a new field instance with proper dependencies
     */
    public static function createField(string $fieldClass, array $metadata): object {
        return ContainerConfig::createField($fieldClass, $metadata);
    }

    /**
     * Create a new validation rule instance with proper dependencies
     */
    public static function createValidationRule(string $ruleClass): object {
        return ContainerConfig::createValidationRule($ruleClass);
    }

    /**
     * Create a new FieldFactory instance with proper dependencies and specific model
     */
    public static function createFieldFactory(\Gravitycar\Models\ModelBase $model): \Gravitycar\Factories\FieldFactory {
        return ContainerConfig::createFieldFactory($model);
    }

    /**
     * Create a new RelationshipFactory instance with proper dependencies and specific model
     */
    public static function createRelationshipFactory(\Gravitycar\Models\ModelBase $model): \Gravitycar\Factories\RelationshipFactory {
        return ContainerConfig::createRelationshipFactory($model);
    }

    /**
     * Create a new relationship instance with proper dependencies
     */
    public static function createRelationship(string $relationshipClass, array $metadata): object {
        return ContainerConfig::createRelationship($relationshipClass, $metadata);
    }

    /**
     * Get the ModelFactory service for convenient model creation
     */
    public static function getModelFactory(): \Gravitycar\Factories\ModelFactory {
        return self::getContainer()->get('model_factory');
    }

    /**
     * Get the APIRouteRegistry singleton instance
     */
    public static function getAPIRouteRegistry(): \Gravitycar\Api\APIRouteRegistry {
        return \Gravitycar\Api\APIRouteRegistry::getInstance();
    }

    /**
     * Get the current user - enhanced for authentication system with guest fallback
     * 
     * This method first tries to get the authenticated user via JWT token.
     * If no authenticated user is found, it returns the guest user instead.
     * This ensures that there is always a user context available for audit trails.
     * 
     * The user instance is cached in memory after the first retrieval to improve performance.
     */
    public static function getCurrentUser(): ?\Gravitycar\Models\ModelBase {
        // Return cached user if already retrieved
        if (self::$cachedCurrentUser !== null) {
            return self::$cachedCurrentUser;
        }
        
        try {
            $token = self::getAuthTokenFromRequest();
            
            if ($token) {
                $authService = self::getAuthenticationService();
                $authenticatedUser = $authService->validateJwtToken($token);
                
                if ($authenticatedUser) {
                    // Cache the authenticated user
                    self::$cachedCurrentUser = $authenticatedUser;
                    return self::$cachedCurrentUser;
                }
            }
            
            // No authenticated user found, fall back to guest user
            try {
                $guestManager = new \Gravitycar\Utils\GuestUserManager();
                $guestUser = $guestManager->getGuestUser();
                
                // Cache the guest user
                self::$cachedCurrentUser = $guestUser;
                return self::$cachedCurrentUser;
            } catch (Exception $e) {
                self::getLogger()->error('Failed to get guest user fallback: ' . $e->getMessage());
                return null;
            }
            
        } catch (Exception $e) {
            self::getLogger()->debug('Unable to get current user: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clear the cached current user
     * 
     * This method should be called when the user session changes (login, logout, etc.)
     * to ensure the cache is refreshed on the next getCurrentUser() call.
     */
    public static function clearCurrentUserCache(): void {
        self::$cachedCurrentUser = null;
    }

    /**
     * Get FieldFactory for a specific model (for relationship dynamic field creation)
     */
    public static function getFieldFactory(\Gravitycar\Models\ModelBase $model): \Gravitycar\Factories\FieldFactory {
        return self::createFieldFactory($model);
    }

    /**
     * Set container for testing
     */
    public static function setContainer(Container $container): void {
        self::$container = $container;
    }

    /**
     * Reset container (useful for testing)
     */
    public static function reset(): void {
        self::$container = null;
        ContainerConfig::resetContainer();
    }

    /**
     * Get any service with auto-wiring support
     */
    public static function get(string $serviceName): object {
        try {
            return self::getContainer()->get($serviceName);
        } catch (Exception $e) {
            // Try auto-wiring if service not found
            return ContainerConfig::autoWire($serviceName);
        }
    }

    public static function hasService(string $serviceName): bool {
        try {
            self::getContainer()->get($serviceName);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create any class with auto-wiring
     */
    public static function create(string $className, array $parameters = []): object {
        if (empty($parameters)) {
            return ContainerConfig::autoWire($className);
        }

        // Mix auto-wiring with explicit parameters - use direct instantiation to avoid container locking issues
        return self::createWithMixedParameters($className, $parameters);
    }

    /**
     * Create instance with mix of auto-wired and explicit parameters
     * Uses direct instantiation to avoid container locking issues
     */
    private static function createWithMixedParameters(string $className, array $parameters): object {
        if (!class_exists($className)) {
            throw new \Exception("Class $className does not exist");
        }

        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            // No constructor, create simple instance
            return new $className();
        }

        $constructorParams = $constructor->getParameters();
        $dependencies = [];

        foreach ($constructorParams as $param) {
            $paramName = $param->getName();

            // Check if explicit parameter provided
            if (array_key_exists($paramName, $parameters)) {
                $dependencies[] = $parameters[$paramName];
                continue;
            }

            // Auto-wire this parameter
            $type = $param->getType();

            if (!$type || $type->isBuiltin()) {
                // Handle primitive types
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    throw new \Exception("Cannot resolve primitive parameter '$paramName' for class $className");
                }
            } else {
                // Auto-wire dependency
                $typeName = $type->getName();

                // Map to container services if available
                $serviceMap = [
                    'Monolog\\Logger' => 'logger',
                    'Gravitycar\\Core\\Config' => 'config',
                    'Gravitycar\\Database\\DatabaseConnector' => 'database_connector',
                    'Gravitycar\\Metadata\\MetadataEngine' => 'metadata_engine',
                    'Gravitycar\\Factories\\FieldFactory' => 'field_factory',
                    'Gravitycar\\Factories\\ValidationRuleFactory' => 'validation_rule_factory',
                ];

                if (isset($serviceMap[$typeName])) {
                    $dependencies[] = self::getContainer()->get($serviceMap[$typeName]);
                } else {
                    // Auto-wire custom classes
                    $dependencies[] = ContainerConfig::autoWire($typeName);
                }
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Initialize the service locator and container
     */
    public static function initialize(): void {
        // This forces the container to be created and configured
        self::getContainer();
    }

    /**
     * Get the core fields metadata service
     */
    public static function getCoreFieldsMetadata(): \Gravitycar\Metadata\CoreFieldsMetadata {
        try {
            return self::getContainer()->get('core_fields_metadata');
        } catch (Exception $e) {
            $logger = self::getLogger();
            $logger->error('Failed to get CoreFieldsMetadata service: ' . $e->getMessage());
            throw new \Gravitycar\Exceptions\GCException(
                'CoreFieldsMetadata service unavailable: ' . $e->getMessage(),
                ['service_error' => $e->getMessage()],
                0,
                $e
            );
        }
    }

    /**
     * Get the authentication service
     */
    public static function getAuthenticationService(): AuthenticationService {
        try {
            return self::getContainer()->get('authentication_service');
        } catch (Exception $e) {
            $logger = self::getLogger();
            $logger->error('Failed to get AuthenticationService: ' . $e->getMessage());
            throw new \Gravitycar\Exceptions\GCException(
                'AuthenticationService unavailable: ' . $e->getMessage(),
                ['service_error' => $e->getMessage()],
                0,
                $e
            );
        }
    }

    /**
     * Get the authorization service
     */
    public static function getAuthorizationService(): AuthorizationService {
        try {
            return self::getContainer()->get('authorization_service');
        } catch (Exception $e) {
            $logger = self::getLogger();
            $logger->error('Failed to get AuthorizationService: ' . $e->getMessage());
            throw new \Gravitycar\Exceptions\GCException(
                'AuthorizationService unavailable: ' . $e->getMessage(),
                ['service_error' => $e->getMessage()],
                0,
                $e
            );
        }
    }

    /**
     * Extract JWT token from request headers
     */
    public static function getAuthTokenFromRequest(): ?string {
        // Check Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check for token in cookies
        if (isset($_COOKIE['jwt_token'])) {
            return $_COOKIE['jwt_token'];
        }

        return null;
    }
}
