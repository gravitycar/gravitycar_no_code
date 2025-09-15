<?php
namespace Gravitycar\Factories;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;
use Aura\Di\Container;

class APIControllerFactory {
    private Container $container;
    
    // Service map for common dependencies
    private array $serviceMap = [
        'Monolog\\Logger' => 'logger',
        'Gravitycar\\Factories\\ModelFactory' => 'model_factory', 
        'Gravitycar\\Contracts\\DatabaseConnectorInterface' => 'database_connector',
        'Gravitycar\\Contracts\\MetadataEngineInterface' => 'metadata_engine',
        'Gravitycar\\Core\\Config' => 'config',
        'Gravitycar\\Contracts\\CurrentUserProviderInterface' => 'current_user_provider',
        // Controller-specific services
        'Gravitycar\\Services\\AuthenticationService' => 'authentication_service',
        'Gravitycar\\Services\\GoogleOAuthService' => 'google_oauth_service',
        'Gravitycar\\Services\\MovieTMDBIntegrationService' => 'movie_tmdb_integration_service',
        'Gravitycar\\Services\\OpenAPIGenerator' => 'openapi_generator',
        'Gravitycar\\Services\\GoogleBooksApiService' => 'google_books_api_service',
        'Gravitycar\\Services\\BookGoogleBooksIntegrationService' => 'book_google_books_integration_service',
        'Gravitycar\\Api\\APIRouteRegistry' => 'api_route_registry',
        'Gravitycar\\Services\\DocumentationCache' => 'documentation_cache',
        'Gravitycar\\Services\\ReactComponentMapper' => 'react_component_mapper'
    ];

    public function __construct(Container $container) {
        $this->container = $container;
    }

    /**
     * Create controller with explicit dependency list (reads from route data)
     * 
     * @param string $controllerClassName
     * @param array $dependencyServiceNames List of service names (not class names)
     * @return ApiControllerBase
     */
    public function createControllerWithDependencyList(string $controllerClassName, array $dependencyServiceNames): ApiControllerBase {
        $dependencies = [];
        
        foreach ($dependencyServiceNames as $serviceName) {
            $dependencies[] = $this->resolveService($serviceName);
        }
        
        $reflection = new \ReflectionClass($controllerClassName);
        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve service by service name
     */
    private function resolveService(string $serviceName): object {
        if ($this->container->has($serviceName)) {
            return $this->container->get($serviceName);
        } else {
            throw new GCException("Service not found in container", [
                'serviceName' => $serviceName,
                'availableServices' => array_keys($this->serviceMap)
            ]);
        }
    }

    /**
     * Enhanced dependency resolution with detailed error reporting
     */
    private function resolveDependency(string $dependencyClassName): object {
        // Check service map first
        if (isset($this->serviceMap[$dependencyClassName])) {
            $serviceKey = $this->serviceMap[$dependencyClassName];
            
            if ($this->container->has($serviceKey)) {
                return $this->container->get($serviceKey);
            } else {
                throw new GCException("Service not found in container", [
                    'service_key' => $serviceKey,
                    'dependency_class' => $dependencyClassName,
                    'suggestion' => 'Add service configuration to ContainerConfig'
                ]);
            }
        }
        
        // Fall back to manual instantiation for unmapped services
        try {
            return $this->container->newInstance($dependencyClassName);
        } catch (\Exception $e) {
            throw new GCException("Failed to instantiate dependency", [
                'dependency_class' => $dependencyClassName,
                'original_error' => $e->getMessage(),
                'suggestion' => 'Consider adding to service map or container configuration'
            ]);
        }
    }
}