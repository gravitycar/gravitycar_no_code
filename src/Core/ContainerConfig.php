<?php
namespace Gravitycar\Core;

use Aura\Di\Container;
use Aura\Di\ContainerBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Gravitycar\Core\Config;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Schema\SchemaGenerator;
use Gravitycar\Factories\ValidationRuleFactory;
use Gravitycar\Factories\APIControllerFactory;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Services\GoogleOAuthService;
use Gravitycar\Services\UserService;
use Gravitycar\Services\PermissionsBuilder;
use Gravitycar\Exceptions\ContainerException;
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
            $di = $builder->newInstance(ContainerBuilder::AUTO_RESOLVE);

            // Configure services with error handling
            self::configureCoreServices($di);
            self::configureFactories($di);
            self::configureApplicationServices($di);
            self::configureModelClasses($di);
            self::configureInterfaces($di);
            self::configureSetterInjection($di);

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
        // Logger - singleton with daily rotation and configurable settings
        $di->set('logger', $di->lazy(function() use ($di) {
            try {
                // Create logger instance
                $logger = new Logger('gravitycar');

                // Use hardcoded defaults to avoid circular dependency with Config
                // Config can be loaded later and logger settings updated if needed
                $logFile = 'logs/gravitycar.log';
                $logLevel = Logger::INFO;
                $dailyRotation = true;
                $maxFiles = 30;

                // Create log directory if needed
                $logDir = dirname($logFile);
                if (!is_dir($logDir)) {
                    if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                        throw new ContainerException(
                            "Failed to create log directory: $logDir",
                            [
                                'service' => 'logger',
                                'log_directory' => $logDir
                            ],
                            0,
                            new Exception("Failed to create log directory: $logDir")
                        );
                    }
                }

                // Use Monolog's built-in rotation if enabled, otherwise simple StreamHandler
                if ($dailyRotation) {
                    $handler = new RotatingFileHandler($logFile, $maxFiles, $logLevel);
                } else {
                    $handler = new StreamHandler($logFile, $logLevel);
                }
                
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
                return new DatabaseConnector(
                    $di->get('logger'),
                    $di->get('config')
                );
            } catch (Exception $e) {
                $logger = $di->get('logger');
                $logger->error('Database connector failed: ' . $e->getMessage());

                // Return stub connector - DatabaseConnectorStub constructor: (Logger, Exception)
                return new \Gravitycar\Database\DatabaseConnectorStub($logger, $e);
            }
        }));

        // Database alias for database_connector (for consistency with existing services)
        $di->set('database', $di->lazyGet('database_connector'));

        // MetadataEngine - singleton managed by DI container
        $di->set('metadata_engine', $di->lazy(function() use ($di) {
            try {
                // Use DI container to create instance with proper dependencies
                return new MetadataEngine(
                    $di->get('logger'),
                    $di->get('config'),
                    $di->get('core_fields_metadata')
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
        // ValidationRuleFactory - singleton with pure DI dependencies
        $di->set('validation_rule_factory', $di->lazyNew(ValidationRuleFactory::class));
        $di->params[ValidationRuleFactory::class] = [
            'logger' => $di->lazyGet('logger'),
            'metadataEngine' => $di->lazyGet('metadata_engine')
        ];
        
        // FieldFactory - singleton with proper DI dependencies
        $di->set('field_factory', $di->lazyNew(\Gravitycar\Factories\FieldFactory::class));
        $di->params[\Gravitycar\Factories\FieldFactory::class] = [
            'logger' => $di->lazyGet('logger'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine')
        ];

        // RelationshipFactory - singleton with proper DI dependencies
        $di->set('relationship_factory', $di->lazyNew(\Gravitycar\Factories\RelationshipFactory::class));
        $di->params[\Gravitycar\Factories\RelationshipFactory::class] = [
            'logger' => $di->lazyGet('logger'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'owner' => 'ModelBase' // Default owner
        ];
        
        // ModelFactory - singleton with proper DI dependencies
        $di->set('model_factory', $di->lazyNew(\Gravitycar\Factories\ModelFactory::class));
        $di->params[\Gravitycar\Factories\ModelFactory::class] = [
            'container' => $di, // Pass the container itself for model instantiation
            'logger' => $di->lazyGet('logger'),
            'dbConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine')
        ];
        
        // APIControllerFactory - singleton with container injection
        $di->set('api_controller_factory', $di->lazyNew(\Gravitycar\Factories\APIControllerFactory::class));
        $di->params[\Gravitycar\Factories\APIControllerFactory::class] = [
            'container' => $di
        ];

        // APIPathScorer - prototype with logger injection
        $di->set('api_path_scorer', $di->lazyNew(\Gravitycar\Api\APIPathScorer::class));
        $di->params[\Gravitycar\Api\APIPathScorer::class] = [
            'logger' => $di->lazyGet('logger')
        ];
    }

    /**
     * Configure application services
     */
    private static function configureApplicationServices(Container $di): void {
        // SchemaGenerator - prototype (new instance each time)
        $di->set('schema_generator', $di->lazyNew(SchemaGenerator::class, [
            'logger' => $di->lazyGet('logger'),
            'dbConnector' => $di->lazyGet('database_connector'),
            'coreFieldsMetadata' => $di->lazyGet('core_fields_metadata')
        ]));

        // Router - pure dependency injection
        $di->set('router', $di->lazyNew(\Gravitycar\Api\Router::class));
        $di->params[\Gravitycar\Api\Router::class] = [
            'logger' => $di->lazyGet('logger'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'routeRegistry' => $di->lazyGet('api_route_registry'),
            'pathScorer' => $di->lazyGet('api_path_scorer'),
            'controllerFactory' => $di->lazyGet('api_controller_factory'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'authenticationService' => $di->lazyGet('authentication_service'),
            'authorizationService' => $di->lazyGet('authorization_service'),
            'currentUserProvider' => $di->lazyGet('current_user_provider')
        ];

        // Authentication services - Use pure dependency injection
        $di->set('authentication_service', $di->lazyNew(\Gravitycar\Services\AuthenticationService::class));
        $di->params[\Gravitycar\Services\AuthenticationService::class] = [
            'logger' => $di->lazyGet('logger'),
            'database' => $di->lazyGet('database_connector'),
            'config' => $di->lazyGet('config'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'googleOAuthService' => $di->lazyGet('google_oauth_service')
        ];

        $di->set('authorization_service', $di->lazyNew(\Gravitycar\Services\AuthorizationService::class));
        $di->params[\Gravitycar\Services\AuthorizationService::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'userContext' => $di->lazyGet('current_user_provider')
        ];

        $di->set('google_oauth_service', $di->lazyNew(\Gravitycar\Services\GoogleOAuthService::class));
        $di->params[\Gravitycar\Services\GoogleOAuthService::class] = [
            'config' => $di->lazyGet('config'),
            'logger' => $di->lazyGet('logger')
        ];

        // CurrentUserProvider - singleton service for user context
        $di->set('current_user_provider', $di->lazyNew(\Gravitycar\Services\CurrentUserProvider::class));
        $di->params[\Gravitycar\Services\CurrentUserProvider::class] = [
            'logger' => $di->lazyGet('logger'),
            'authService' => $di->lazyGet('authentication_service'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'guestUserManager' => null // Will be created internally if needed
        ];

        $di->set('user_service', $di->lazyNew(\Gravitycar\Services\UserService::class));
        $di->params[\Gravitycar\Services\UserService::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'config' => $di->lazyGet('config'),
            'databaseConnector' => $di->lazyGet('database_connector')
        ];

        // PermissionsBuilder - prototype service for building permissions from metadata
        $di->set('permissions_builder', $di->lazyNew(\Gravitycar\Services\PermissionsBuilder::class));
        $di->params[\Gravitycar\Services\PermissionsBuilder::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'apiRouteRegistry' => $di->lazyGet('api_route_registry')
        ];

        // TMDB Services
        $di->set('tmdb_api_service', $di->lazyNew(\Gravitycar\Services\TMDBApiService::class));
        $di->params[\Gravitycar\Services\TMDBApiService::class] = [
            'config' => $di->lazyGet('config'),
            'logger' => $di->lazyGet('logger')
        ];
        
        $di->set('movie_tmdb_integration_service', $di->lazyNew(\Gravitycar\Services\MovieTMDBIntegrationService::class));
        $di->params[\Gravitycar\Services\MovieTMDBIntegrationService::class] = [
            'tmdbService' => $di->lazyGet('tmdb_api_service')
        ];

        // Configure Movies model with TMDB integration service
        $di->params[\Gravitycar\Models\movies\Movies::class] = [
            'logger' => $di->lazyGet('logger'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'fieldFactory' => $di->lazyGet('field_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'relationshipFactory' => $di->lazyGet('relationship_factory'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'currentUserProvider' => $di->lazyGet('current_user_provider'),
            'tmdbIntegration' => $di->lazyGet('movie_tmdb_integration_service')
        ];

        // OAuth Services
        $di->set('google_oauth_service', $di->lazyNew(\Gravitycar\Services\GoogleOAuthService::class));
        $di->params[\Gravitycar\Services\GoogleOAuthService::class] = [
            'config' => $di->lazyGet('config'),
            'logger' => $di->lazyGet('logger')
        ];

        // Google Books Services
        $di->set('google_books_api_service', $di->lazyNew(\Gravitycar\Services\GoogleBooksApiService::class));
        $di->params[\Gravitycar\Services\GoogleBooksApiService::class] = [
            'config' => $di->lazyGet('config'),
            'logger' => $di->lazyGet('logger')
        ];

        $di->set('book_google_books_integration_service', $di->lazyNew(\Gravitycar\Services\BookGoogleBooksIntegrationService::class));
        $di->params[\Gravitycar\Services\BookGoogleBooksIntegrationService::class] = [
            'googleBooksService' => $di->lazyGet('google_books_api_service')
        ];

        // User Context Services
        $di->set('user_context', $di->lazyNew(\Gravitycar\Services\UserContext::class));
        $di->params[\Gravitycar\Services\UserContext::class] = [
            'currentUserProvider' => $di->lazyGet('current_user_provider')
        ];

        // Utility Services
        $di->set('email_service', $di->lazyNew(\Gravitycar\Services\EmailService::class));
        $di->params[\Gravitycar\Services\EmailService::class] = [
            'logger' => $di->lazyGet('logger'),
            'config' => $di->lazyGet('config')
        ];

        $di->set('notification_service', $di->lazyNew(\Gravitycar\Services\NotificationService::class));
        $di->params[\Gravitycar\Services\NotificationService::class] = [
            'logger' => $di->lazyGet('logger'),
            'emailService' => $di->lazyGet('email_service')
        ];

        // Test Services
        $di->set('test_current_user_provider', $di->lazyNew(\Gravitycar\Services\TestCurrentUserProvider::class));
        $di->params[\Gravitycar\Services\TestCurrentUserProvider::class] = [
            'logger' => $di->lazyGet('logger'),
            'testUser' => null,
            'hasAuthenticatedUser' => false
        ];

        $di->set('cli_current_user_provider', $di->lazyNew(\Gravitycar\Services\CLICurrentUserProvider::class));
        $di->params[\Gravitycar\Services\CLICurrentUserProvider::class] = [
            'logger' => $di->lazyGet('logger')
        ];

        // API Controllers
        $di->set('model_base_api_controller', $di->lazyNew(\Gravitycar\Models\Api\Api\ModelBaseAPIController::class));
        $di->params[\Gravitycar\Models\Api\Api\ModelBaseAPIController::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'config' => $di->lazyGet('config'),
            'currentUserProvider' => $di->lazyGet('current_user_provider')
        ];

        // AuthController
        $di->set('auth_controller', $di->lazyNew(\Gravitycar\Api\AuthController::class));
        $di->params[\Gravitycar\Api\AuthController::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'config' => $di->lazyGet('config'),
            'currentUserProvider' => $di->lazyGet('current_user_provider'),
            'authService' => $di->lazyGet('authentication_service'),
            'googleOAuthService' => $di->lazyGet('google_oauth_service')
        ];

        // HealthAPIController
        $di->set('health_api_controller', $di->lazyNew(\Gravitycar\Api\HealthAPIController::class));
        $di->params[\Gravitycar\Api\HealthAPIController::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'config' => $di->lazyGet('config'),
            'currentUserProvider' => $di->lazyGet('current_user_provider')
        ];

        // MetadataAPIController
        $di->set('metadata_api_controller', $di->lazyNew(\Gravitycar\Api\MetadataAPIController::class));
        $di->params[\Gravitycar\Api\MetadataAPIController::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'config' => $di->lazyGet('config'),
            'currentUserProvider' => $di->lazyGet('current_user_provider'),
            'routeRegistry' => $di->lazyGet('api_route_registry'),
            'cache' => $di->lazyGet('documentation_cache'),
            'componentMapper' => $di->lazyGet('react_component_mapper')
        ];

        // OpenAPIController
        $di->set('open_api_controller', $di->lazyNew(\Gravitycar\Api\OpenAPIController::class));
        $di->params[\Gravitycar\Api\OpenAPIController::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'config' => $di->lazyGet('config'),
            'currentUserProvider' => $di->lazyGet('current_user_provider'),
            'openAPIGenerator' => $di->lazyNew(\Gravitycar\Services\OpenAPIGenerator::class)
        ];

        // TMDBController
        $di->set('tmdb_controller', $di->lazyNew(\Gravitycar\Api\TMDBController::class));
        $di->params[\Gravitycar\Api\TMDBController::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'config' => $di->lazyGet('config'),
            'currentUserProvider' => $di->lazyGet('current_user_provider'),
            'tmdbService' => $di->lazyGet('movie_tmdb_integration_service')
        ];

        // GoogleBooksController
        $di->set('google_books_controller', $di->lazyNew(\Gravitycar\Api\GoogleBooksController::class));
        $di->params[\Gravitycar\Api\GoogleBooksController::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'config' => $di->lazyGet('config'),
            'currentUserProvider' => $di->lazyGet('current_user_provider'),
            'googleBooksService' => $di->lazyNew(\Gravitycar\Services\GoogleBooksApiService::class),
            'integrationService' => $di->lazyNew(\Gravitycar\Services\BookGoogleBooksIntegrationService::class)
        ];

        // TriviaGameAPIController
        $di->set('trivia_game_api_controller', $di->lazyNew(\Gravitycar\Api\TriviaGameAPIController::class));
        $di->params[\Gravitycar\Api\TriviaGameAPIController::class] = [
            'logger' => $di->lazyGet('logger'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'config' => $di->lazyGet('config'),
            'currentUserProvider' => $di->lazyGet('current_user_provider')
        ];

        // APIRouteRegistry for MetadataAPIController (singleton pattern)
        $di->set('api_route_registry', function() {
            return \Gravitycar\Api\APIRouteRegistry::getInstance();
        });

        // DocumentationCache service
        $di->set('documentation_cache', $di->lazyNew(\Gravitycar\Services\DocumentationCache::class));

        // ReactComponentMapper service
        $di->set('react_component_mapper', $di->lazyNew(\Gravitycar\Services\ReactComponentMapper::class));

        // Documentation Services (using pure dependency injection)
        $di->set('documentation_cache', $di->lazyNew(\Gravitycar\Services\DocumentationCache::class));
        $di->params[\Gravitycar\Services\DocumentationCache::class] = [
            'logger' => $di->lazyGet('logger'),
            'config' => $di->lazyGet('config')
        ];
        
        $di->set('react_component_mapper', $di->lazyNew(\Gravitycar\Services\ReactComponentMapper::class));
        $di->params[\Gravitycar\Services\ReactComponentMapper::class] = [
            'logger' => $di->lazyGet('logger'),
            'metadataEngine' => $di->lazyGet('metadata_engine')
        ];

        $di->set('openapi_generator', $di->lazyNew(\Gravitycar\Services\OpenAPIGenerator::class));
        $di->params[\Gravitycar\Services\OpenAPIGenerator::class] = [
            'logger' => $di->lazyGet('logger'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'fieldFactory' => $di->lazyGet('field_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'config' => $di->lazyGet('config'),
            'componentMapper' => $di->lazyGet('react_component_mapper'),
            'cache' => $di->lazyGet('documentation_cache')
        ];
        
        // Alias for OpenAPIGenerator service (APIControllerFactory expects underscore naming)
        $di->set('open_api_generator', $di->lazyGet('openapi_generator'));
    }

    /**
     * Configure interface to service mappings for auto-resolution
     */
    private static function configureInterfaces(Container $di): void {
        // Map interfaces to their concrete implementations
        $di->types['Psr\\Log\\LoggerInterface'] = $di->lazyGet('logger');
        $di->types['Monolog\\Logger'] = $di->lazyGet('logger');
        $di->types['Gravitycar\\Contracts\\LoggerInterface'] = $di->lazyGet('logger');
        $di->types['Gravitycar\\Contracts\\MetadataEngineInterface'] = $di->lazyGet('metadata_engine');
        $di->types['Gravitycar\\Contracts\\DatabaseConnectorInterface'] = $di->lazyGet('database_connector');
        
        // Map ModelFactory for dependency injection
        $di->types['Gravitycar\\Factories\\ModelFactory'] = $di->lazyGet('model_factory');
    }

    /**
     * Configure setter injection for optional dependencies
     */
    private static function configureSetterInjection(Container $di): void {
        // Optional cache injection for MetadataEngine (when cache service becomes available)
        // $di->setters['Gravitycar\\Metadata\\MetadataEngine']['setCache'] = $di->lazyGet('cache_service');
        
        // Optional profiler injection for DatabaseConnector (development mode)
        // $di->setters['Gravitycar\\Database\\DatabaseConnector']['setProfiler'] = $di->lazyGet('query_profiler');
        
        // Context injection for field validation (when validation context becomes available)
        // $di->setters['Gravitycar\\Fields\\FieldBase']['setValidationContext'] = $di->lazyGet('validation_context');
        
        // NOTE: Removed setter injection for ModelBase as we now use pure constructor injection
        // All dependencies are injected via the 7-parameter constructor
    }

    /**
     * Configure model classes for dependency injection
     */
    private static function configureModelClasses(Container $di): void {
        // Configure base ModelBase constructor parameters for all model classes
        // This will apply to all classes that extend ModelBase
        $di->params['Gravitycar\\Models\\ModelBase'] = [
            'logger' => $di->lazyGet('logger'),
            'metadataEngine' => $di->lazyGet('metadata_engine'),
            'fieldFactory' => $di->lazyGet('field_factory'),
            'databaseConnector' => $di->lazyGet('database_connector'),
            'relationshipFactory' => $di->lazyGet('relationship_factory'),
            'modelFactory' => $di->lazyGet('model_factory'),
            'currentUserProvider' => $di->lazyGet('current_user_provider')
        ];
        
        // Dynamically discover and register all model classes
        try {
            $modelNames = self::discoverModelNamesFromCache();
            $registeredCount = 0;
            
            foreach ($modelNames as $modelName) {
                $fullClassName = self::buildModelClassName($modelName);
                
                // Verify the model class exists before registering
                if (class_exists($fullClassName)) {
                    $di->set($fullClassName, $di->lazyNew($fullClassName));
                    $registeredCount++;
                } else {
                    // Note: Can't log here since logger might not be available yet
                    // Model class will be created on-demand by auto-wiring if needed
                }
            }
            
            // Store registry info for debugging
            $di->set('model_registration_info', $di->lazy(function() use ($modelNames, $registeredCount) {
                return new class($modelNames, $registeredCount) {
                    public string $method = 'dynamic';
                    public int $discoveredCount;
                    public int $registeredCount;
                    public array $modelNames;
                    
                    public function __construct(array $modelNames, int $registeredCount) {
                        $this->discoveredCount = count($modelNames);
                        $this->registeredCount = $registeredCount;
                        $this->modelNames = $modelNames;
                    }
                };
            }));
            
        } catch (Exception $e) {
            // Fallback to hardcoded model list if dynamic discovery fails
            self::registerFallbackModels($di);
            
            $di->set('model_registration_info', $di->lazy(function() use ($e) {
                return new class($e) {
                    public string $method = 'fallback';
                    public string $error;
                    public int $registeredCount = 11; // Known fallback count
                    
                    public function __construct(Exception $e) {
                        $this->error = $e->getMessage();
                    }
                };
            }));
        }
    }
    
    /**
     * Discover model names from metadata cache without circular dependency
     */
    private static function discoverModelNamesFromCache(): array {
        $cacheFile = 'cache/metadata_cache.php';
        
        if (!file_exists($cacheFile)) {
            throw new ContainerException(
                "Metadata cache file not found: {$cacheFile}",
                ['cache_file' => $cacheFile, 'reason' => 'File not found']
            );
        }
        
        $cachedMetadata = include $cacheFile;
        
        if (!is_array($cachedMetadata) || !isset($cachedMetadata['models'])) {
            throw new ContainerException(
                "Invalid metadata cache structure",
                ['cache_file' => $cacheFile, 'reason' => 'Invalid cache structure - missing models array']
            );
        }
        
        return array_keys($cachedMetadata['models']);
    }
    
    /**
     * Build the full class name for a model
     */
    private static function buildModelClassName(string $modelName): string {
        $modelNameLower = strtolower($modelName);
        return "Gravitycar\\Models\\{$modelNameLower}\\{$modelName}";
    }
    
    /**
     * Register fallback model classes when dynamic discovery fails
     */
    private static function registerFallbackModels(Container $di): void {
        $fallbackModels = [
            'Books' => 'Gravitycar\\Models\\books\\Books',
            'GoogleOauthTokens' => 'Gravitycar\\Models\\googleoauthtokens\\GoogleOauthTokens',
            'Installer' => 'Gravitycar\\Models\\installer\\Installer',
            'JwtRefreshTokens' => 'Gravitycar\\Models\\jwt_refresh_tokens\\JwtRefreshTokens',
            'Movie_Quote_Trivia_Games' => 'Gravitycar\\Models\\movie_quote_trivia_games\\Movie_Quote_Trivia_Games',
            'Movie_Quote_Trivia_Questions' => 'Gravitycar\\Models\\movie_quote_trivia_questions\\Movie_Quote_Trivia_Questions',
            'Movie_Quotes' => 'Gravitycar\\Models\\movie_quotes\\Movie_Quotes',
            'Movies' => 'Gravitycar\\Models\\movies\\Movies',
            'Permissions' => 'Gravitycar\\Models\\permissions\\Permissions',
            'Roles' => 'Gravitycar\\Models\\roles\\Roles',
            'Users' => 'Gravitycar\\Models\\users\\Users'
        ];
        
        foreach ($fallbackModels as $shortName => $fullClass) {
            $di->set($fullClass, $di->lazyNew($fullClass));
        }
    }

    /**
     * Create a new model instance with dependencies
     */
    public static function createModel(string $modelClass): object {
        // Check if the model class exists before trying to instantiate it
        if (!class_exists($modelClass)) {
            throw new ContainerException(
                "Model class does not exist: {$modelClass}",
                ['model_class' => $modelClass]
            );
        }

        $di = self::getContainer();
        
        // Use container's newInstance to get properly injected model
        return $di->newInstance($modelClass, [
            'logger' => $di->get('logger'),
            'metadataEngine' => $di->get('metadata_engine'),
            'fieldFactory' => $di->get('field_factory'),
            'databaseConnector' => $di->get('database_connector'),
            'relationshipFactory' => $di->get('relationship_factory'),
            'modelFactory' => $di->get('model_factory'),
            'currentUserProvider' => $di->get('current_user_provider')
        ]);
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
        $di = self::getContainer();
        return new \Gravitycar\Factories\FieldFactory(
            $di->get('logger'),
            $di->get('database_connector'),
            $di->get('metadata_engine')
        );
    }

    /**
     * Create a new RelationshipFactory instance with dependencies and specific model
     */
    public static function createRelationshipFactory(\Gravitycar\Models\ModelBase $model): object {
        $di = self::getContainer();
        return new \Gravitycar\Factories\RelationshipFactory(
            $di->get('logger'),
            $di->get('metadata_engine'),
            $di->get('database_connector'),
            get_class($model)
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
            // Use ContainerException with proper error handling
            throw new ContainerException(
                "Auto-wiring failed for class {$className}: " . $e->getMessage(),
                [
                    'class' => $className,
                    'original_error' => $e->getMessage(),
                    'original_exception' => get_class($e)
                ],
                0,
                $e
            );
        }
    }

    /**
     * Create instance with automatic dependency resolution
     */
    private static function createInstanceWithAutoWiring(string $className, Container $di, array $resolutionStack = [], int $depth = 0): object {
        // Prevent infinite recursion by limiting depth
        if ($depth > 10) {
            throw new ContainerException(
                "Auto-wiring depth limit exceeded (10) for class: {$className}",
                [
                    'class' => $className,
                    'depth' => $depth,
                    'reason' => 'Possible circular dependency'
                ]
            );
        }

        if (!class_exists($className)) {
            throw new ContainerException(
                "Class {$className} does not exist",
                ['class' => $className]
            );
        }

        // Check for circular dependency
        if (in_array($className, $resolutionStack)) {
            $dependencyChain = implode(' -> ', $resolutionStack) . " -> {$className}";
            throw new ContainerException(
                "Circular dependency detected: {$dependencyChain}",
                [
                    'class' => $className,
                    'resolution_stack' => $resolutionStack,
                    'dependency_chain' => $dependencyChain
                ]
            );
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
                throw new ContainerException(
                    "Cannot resolve container service '{$serviceMap[$typeName]}' for parameter {$parameter->getName()}: " . $e->getMessage(),
                    [
                        'parameter_name' => $parameter->getName(),
                        'type_name' => $typeName,
                        'service_name' => $serviceMap[$typeName],
                        'original_error' => $e->getMessage()
                    ],
                    0,
                    $e
                );
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
            $dependencyChain = implode(' -> ', $resolutionStack) . " -> {$typeName}";
            throw new ContainerException(
                "Circular dependency detected: {$dependencyChain}",
                [
                    'parameter_name' => $parameter->getName(),
                    'type_name' => $typeName,
                    'resolution_stack' => $resolutionStack,
                    'dependency_chain' => $dependencyChain
                ]
            );
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
            throw new ContainerException(
                "Cannot auto-wire type {$typeName} - not in auto-wirable namespaces and no default value available",
                [
                    'parameter_name' => $parameter->getName(),
                    'type_name' => $typeName,
                    'reason' => 'Type not in auto-wirable namespaces and no default value available'
                ]
            );
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

        throw new ContainerException(
            "Cannot resolve primitive parameter - no mapping, default value, or nullable type",
            [
                'parameter_name' => $parameter->getName(),
                'type_name' => 'primitive',
                'reason' => 'Cannot resolve primitive parameter - no mapping, default value, or nullable type'
            ]
        );
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

    /**
     * Create OpenAPIGenerator instance with proper dependencies
     */
    public static function createOpenAPIGenerator(): \Gravitycar\Services\OpenAPIGenerator {
        if (!class_exists('Gravitycar\\Services\\OpenAPIGenerator')) {
            throw new \Gravitycar\Exceptions\GCException("OpenAPIGenerator class does not exist");
        }
        
        $di = self::getContainer();
        return $di->newInstance('Gravitycar\\Services\\OpenAPIGenerator');
    }
    
    /**
     * Create DocumentationCache instance with proper dependencies
     */
    public static function createDocumentationCache(): \Gravitycar\Services\DocumentationCache {
        if (!class_exists('Gravitycar\\Services\\DocumentationCache')) {
            throw new \Gravitycar\Exceptions\GCException("DocumentationCache class does not exist");
        }
        
        $di = self::getContainer();
        return $di->newInstance('Gravitycar\\Services\\DocumentationCache');
    }
    
    /**
     * Create ReactComponentMapper instance with proper dependencies
     */
    public static function createReactComponentMapper(): \Gravitycar\Services\ReactComponentMapper {
        if (!class_exists('Gravitycar\\Services\\ReactComponentMapper')) {
            throw new \Gravitycar\Exceptions\GCException("ReactComponentMapper class does not exist");
        }
        
        $di = self::getContainer();
        return $di->newInstance('Gravitycar\\Services\\ReactComponentMapper');
    }
}
