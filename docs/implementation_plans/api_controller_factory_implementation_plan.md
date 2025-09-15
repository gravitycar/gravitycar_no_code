# API Controller Factory Implementation Plan

## Overview

This implementation plan addresses the critical dependency injection problem in APIRouteRegistry and Router where API controllers are instantiated without their required dependencies. The current approach uses `new $controllerClass()` or `new $controllerClass($logger)`, missing 5+ required dependencies for proper Pure DI architecture.

## Problem Statement

### Current Issues

1. **APIRouteRegistry::discoverAPIControllers()** - Line 99-107: Uses `new $modelBaseAPIControllerClass()` without dependencies
2. **Router::executeRoute()** - Line 156: Uses `new $controllerClass($this->logger)` - only passes logger, missing other required dependencies
3. **Performance Concerns** - Directory scanning during every instantiation would be too slow
4. **DI Container Limitations** - Aura DI provides no way to get service by class name, requires manual service mapping maintenance

### Current Constructor Signatures Analysis

All API controllers extending ApiControllerBase require these dependencies:

**Base Dependencies (6):**
- `Logger $logger`
- `ModelFactory $modelFactory` 
- `DatabaseConnectorInterface $databaseConnector`
- `MetadataEngineInterface $metadataEngine`
- `Config $config`
- `CurrentUserProviderInterface $currentUserProvider`

**Controller-Specific Dependencies:**
- **AuthController**: `+AuthenticationService`, `+GoogleOAuthService`
- **MetadataAPIController**: `+APIRouteRegistry`, `+DocumentationCache`, `+ReactComponentMapper`
- **OpenAPIController**: `+OpenAPIGenerator` (non-optional)
- **TMDBController**: `+MovieTMDBIntegrationService` (non-optional)
- **GoogleBooksController**: `+GoogleBooksApiService`, `+BookGoogleBooksIntegrationService`
- **TriviaGameAPIController**: Base dependencies only
- **HealthAPIController**: Base dependencies only
- **ModelBaseAPIController**: `+CurrentUserProviderInterface` (duplicate for specific access)

## Proposed Solution: API Controller Factory

### Architecture Overview

Create an `APIControllerFactory` that:
1. **Resolves dependencies** from DI container using service mapping
2. **Provides single creation method**: 
   - `createControllerWithDependencyList()` - Uses explicit dependency list from route data
3. **Reads route data** from existing `api_routes.php` cache file written by APIRouteRegistry

### Key Benefits

- ✅ **Zero Performance Impact**: No runtime directory scanning
- ✅ **Self-Maintaining**: Automatically discovers and caches controller dependencies  
- ✅ **Framework Agnostic**: Works with any DI container implementation
- ✅ **Backward Compatible**: Existing controllers work without modification
- ✅ **Development Friendly**: Cache rebuilds automatically during setup.php runs

## Implementation Plan

### Phase 1: Create API Controller Factory (2-3 hours)

#### Step 1.1: Create APIControllerFactory Class
**Location**: `src/Factories/APIControllerFactory.php`

```php
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
     * @param array $dependencyClassNames
     * @return ApiControllerBase
     */
    public function createControllerWithDependencyList(string $controllerClassName, array $dependencyClassNames): ApiControllerBase {
        $dependencies = [];
        
        foreach ($dependencyClassNames as $dependencyClassName) {
            $dependencies[] = $this->resolveDependency($dependencyClassName);
        }
        
        $reflection = new \ReflectionClass($controllerClassName);
        return $reflection->newInstanceArgs($dependencies);
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
```

#### Step 1.2: Add Factory to Container Configuration
**Location**: `src/Core/ContainerConfig.php`

Add to the `configureFactories()` method:

```php
// Add APIControllerFactory
$di->set('api_controller_factory', $di->lazyNew(
    'Gravitycar\\Factories\\APIControllerFactory',
    ['container' => $di]
));

// Add missing service configurations
$di->set('api_route_registry', function() {
    return \Gravitycar\Api\APIRouteRegistry::getInstance();
});

$di->set('documentation_cache', $di->lazyNew('Gravitycar\\Services\\DocumentationCache'));
$di->set('react_component_mapper', $di->lazyNew('Gravitycar\\Services\\ReactComponentMapper'));
```

### Phase 2: Update APIRouteRegistry (1-2 hours)

#### Step 2.1: Add extractDependenciesFromConstructor() Method to APIRouteRegistry
**Location**: `src/Api/APIRouteRegistry.php`

Add this method to APIRouteRegistry class:

```php
/**
 * Extract dependency class names from constructor using reflection
 */
private function extractDependenciesFromConstructor(string $className): array {
    $reflection = new \ReflectionClass($className);
    $constructor = $reflection->getConstructor();
    
    if (!$constructor) {
        return [];
    }
    
    $dependencies = [];
    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();
        if ($type && !$type->isBuiltin()) {
            $dependencies[] = $type->getName();
        }
    }
    
    return $dependencies;
}
```

#### Step 2.2: Update registerRoute() Method
**Location**: `src/Api/APIRouteRegistry.php`

```php
/**
 * Register a single route with validation
 */
protected function registerRoute(array $route): void
{
    try {
        // Validate route format
        $this->validateRouteFormat($route);
        
        // Parse path components
        $route['pathComponents'] = $this->parsePathComponents($route['path']);
        $route['pathLength'] = count($route['pathComponents']);
        
        // Resolve controller class name
        $route['resolvedApiClass'] = $this->resolveControllerClassName($route['apiClass']);
        
        // Extract and cache controller dependencies in route data
        if ($route['resolvedApiClass']) {
            $route['controllerDependencies'] = $this->extractDependenciesFromConstructor($route['resolvedApiClass']);
        } else {
            $route['controllerDependencies'] = [];
        }
        
        $this->routes[] = $route;
        
        $this->logger->debug("Registered route", [
            'method' => $route['method'],
            'path' => $route['path'],
            'apiClass' => $route['apiClass'],
            'dependencies' => count($route['controllerDependencies'])
        ]);
    } catch (GCException $e) {
        $this->logger->error("Failed to register route: " . $e->getMessage(), ['route' => $route]);
    }
}
```

#### Step 2.3: Modify discoverAPIControllers() Method
**Location**: `src/Api/APIRouteRegistry.php`

```php
/**
 * Discover all API controllers that extend ApiControllerBase
 */
protected function discoverAPIControllers(): void
{
    $this->logger->info("Starting automatic discovery of ApiControllerBase subclasses");
    
    $container = ServiceLocator::getContainer();
    $apiControllerFactory = $container->get('api_controller_factory');
    
    // First, register the global ModelBaseAPIController if it exists
    $modelBaseAPIControllerClass = "Gravitycar\\Models\\Api\\Api\\ModelBaseAPIController";
    if (class_exists($modelBaseAPIControllerClass)) {
        try {
            // Extract dependencies and create controller using factory
            $dependencies = $this->extractDependenciesFromConstructor($modelBaseAPIControllerClass);
            
            $controller = $apiControllerFactory->createControllerWithDependencyList(
                $modelBaseAPIControllerClass, 
                $dependencies
            );
            
            $this->register($controller, $modelBaseAPIControllerClass);
            $this->logger->info("Registered global ModelBaseAPIController");
        } catch (\Exception $e) {
            $this->logger->error("Failed to register ModelBaseAPIController: " . $e->getMessage());
        }
    }
    
    // Discover all classes in src/Api directory that extend ApiControllerBase
    $apiDir = 'src/Api';
    if (!is_dir($apiDir)) {
        $this->logger->warning("API directory not found: {$apiDir}");
        return;
    }
    
    $files = glob($apiDir . '/*Controller.php');
    foreach ($files as $file) {
        $className = $this->getClassNameFromFile($file);
        if ($className && $this->extendsApiControllerBase($className)) {
            try {
                // Extract dependencies and create controller using factory
                $dependencies = $this->extractDependenciesFromConstructor($className);
                
                $controller = $apiControllerFactory->createControllerWithDependencyList(
                    $className,
                    $dependencies
                );
                
                $this->register($controller, $className);
                $this->logger->info("Auto-discovered and registered: {$className}");
            } catch (\Exception $e) {
                $this->logger->error("Failed to instantiate controller {$className}: " . $e->getMessage());
            }
        }
    }
    
    // Also discover model-specific API controllers from directory structure
    if (is_dir($this->modelsDirPath)) {
        $dirs = scandir($this->modelsDirPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;

            $controllerDir = $this->modelsDirPath . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . 'api';
            if (!is_dir($controllerDir)) continue;

            $files = scandir($controllerDir);
            foreach ($files as $file) {
                if (preg_match('/^(.*)APIController\.php$/', $file, $matches)) {
                    $className = "Gravitycar\\Models\\{$dir}\\Api\\{$matches[1]}APIController";
                    if (class_exists($className) && $this->extendsApiControllerBase($className)) {
                        try {
                            // Extract dependencies and create controller using factory
                            $dependencies = $this->extractDependenciesFromConstructor($className);
                            
                            $controller = $apiControllerFactory->createControllerWithDependencyList(
                                $className,
                                $dependencies
                            );
                            
                            $this->register($controller, $className);
                            $this->logger->info("Auto-discovered model controller: {$className}");
                        } catch (\Exception $e) {
                            $this->logger->error("Failed to instantiate model API controller {$className}: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
    
    $this->logger->info("Finished automatic discovery of API controllers");
}
```

#### Step 2.4: Update discoverModelRoutes() Method
```php
/**
 * Discover ModelBase routes from metadata (for models with custom registerRoutes methods)
 */
protected function discoverModelRoutes(): void
{
    // ModelBase routes are primarily handled through ModelBaseAPIController wildcards
    // This method only registers custom routes from models that have registerRoutes methods
    
    if (!is_dir($this->modelsDirPath)) {
        $this->logger->warning("Models directory not found: {$this->modelsDirPath}");
        return;
    }

    $dirs = scandir($this->modelsDirPath);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;

        $modelDir = $this->modelsDirPath . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($modelDir)) continue;

        // Look for ModelBase subclasses that have custom registerRoutes methods
        $files = scandir($modelDir);
        foreach ($files as $file) {
            if (preg_match('/^(.*)\.php$/', $file, $matches) && $matches[1] !== 'api') {
                $modelName = $matches[1];
                $className = "Gravitycar\\Models\\{$dir}\\{$modelName}";
                if (class_exists($className)) {
                    try {
                        // Only register if the model has a custom registerRoutes method
                        if (method_exists($className, 'registerRoutes')) {
                            // Use ModelFactory for model instantiation (not APIControllerFactory)
                            $model = \Gravitycar\Core\ServiceLocator::getModelFactory()->new($modelName);
                            $this->register($model, $className);
                        }
                    } catch (\Exception $e) {
                        $this->logger->error("Failed to instantiate model {$modelName}: " . $e->getMessage());
                    }
                }
            }
        }
    }
}
```

### Phase 3: Update Router::executeRoute() (30 minutes)

#### Step 3.1: Modify executeRoute Method
**Location**: `src/Api/Router.php`

Replace the current `executeRoute` method:

```php
/**
 * Execute route with enhanced Request object
 */
protected function executeRoute(array $route, Request $request): mixed {
    $controllerClass = $route['apiClass'];
    $handlerMethod = $route['apiMethod'];
    
    if (!class_exists($controllerClass)) {
        throw new GCException("API controller class not found: $controllerClass", [
            'controller_class' => $controllerClass,
            'route' => $route['path']
        ]);
    }
    
    // Use APIControllerFactory to read dependencies from route data
    $container = ServiceLocator::getContainer();
    $apiControllerFactory = $container->get('api_controller_factory');
    
    // Get dependencies from the route data (stored during registerRoute())
    $dependencyClassNames = $route['controllerDependencies'] ?? [];
    
    if (empty($dependencyClassNames)) {
        throw new GCException("No dependency information found in route data for controller: $controllerClass", [
            'controller_class' => $controllerClass,
            'route' => $route['path']
        ]);
    }
    
    try {
        $controller = $apiControllerFactory->createControllerWithDependencyList($controllerClass, $dependencyClassNames);
    } catch (\Exception $e) {
        throw new GCException("Failed to create controller instance: " . $e->getMessage(), [
            'controller_class' => $controllerClass,
            'dependencies' => $dependencyClassNames,
            'route' => $route['path']
        ]);
    }
    
    if (!method_exists($controller, $handlerMethod)) {
        throw new GCException("Handler method not found: $handlerMethod in $controllerClass", [
            'handler_method' => $handlerMethod,
            'controller_class' => $controllerClass
        ]);
    }
    
    // Authentication and authorization middleware
    $this->handleAuthentication($route, $request);
    
    // Validate Request parameters
    $this->validateRequestParameters($request, $route);
    
    // Call controller method with enhanced Request object (no additionalParams)
    return $controller->$handlerMethod($request);
}
```

### Phase 4: Performance Optimizations (30 minutes)

#### Step 4.1: Setup Integration
**Location**: `setup.php`

The dependency caching is now handled automatically by APIRouteRegistry during route discovery. No additional setup needed for dependency cache warming.

```php
// Existing setup.php cache operations remain unchanged
// Dependencies are automatically cached in api_routes.php during route discovery
echo "API controller dependencies will be cached automatically during route discovery\n";
```

## Testing Strategy

### Unit Tests Required

1. **APIControllerFactory Tests**
   - Service resolution from container
   - Error handling for missing dependencies

2. **APIRouteRegistry Tests**
   - Dependency extraction from constructors
   - Route registration with dependency caching
   - Integration with factory during discovery

3. **Router Integration Tests**
   - Router executeRoute with factory
   - Full request lifecycle with proper dependency injection

3. **Performance Tests**
   - Cache hit/miss scenarios
   - Controller instantiation benchmarks
   - Memory usage validation

### Test Implementation

```php
// Example unit test structure
class APIControllerFactoryTest extends PHPUnit\Framework\TestCase {
    
    public function testCreateControllerWithDependencyList() {
        $factory = new APIControllerFactory($this->mockContainer);
        $dependencies = ['Monolog\\Logger', 'Gravitycar\\Factories\\ModelFactory'];
        
        $controller = $factory->createControllerWithDependencyList(
            'Gravitycar\\Api\\HealthAPIController', 
            $dependencies
        );
        
        $this->assertInstanceOf(ApiControllerBase::class, $controller);
    }
    
    public function testDependencyResolution() {
        // Test service resolution from container vs fallback instantiation
    }
}

class APIRouteRegistryTest extends PHPUnit\Framework\TestCase {
    
    public function testExtractDependenciesFromConstructor() {
        $registry = new APIRouteRegistry();
        $dependencies = $this->callPrivateMethod($registry, 'extractDependenciesFromConstructor', [
            'Gravitycar\\Api\\AuthController'
        ]);
        
        $this->assertContains('Monolog\\Logger', $dependencies);
        $this->assertContains('Gravitycar\\Services\\AuthenticationService', $dependencies);
    }
    
    public function testRegisterRouteWithDependencies() {
        // Test that registerRoute() adds controllerDependencies to route data
    }
}
```

## Risk Analysis & Mitigation

### Identified Risks

1. **Route Data Dependency Mismatch**: Constructor changes invalidate stored dependencies in route cache
   - **Mitigation**: Route cache rebuilds during APIRouteRegistry rebuildCache(), clear error reporting

2. **Missing Service Configurations**: New dependencies not mapped
   - **Mitigation**: Comprehensive error messages, container auto-instantiation fallback

3. **Performance Regression**: Factory overhead vs direct instantiation
   - **Mitigation**: Benchmark testing, dependency data stored in existing route cache

### Success Criteria

- ✅ All API controllers instantiate with full dependencies
- ✅ No performance degradation vs current broken implementation
- ✅ No separate caching system - uses existing api_routes.php cache
- ✅ Automatic dependency discovery during route registration
- ✅ Clear error messages for configuration issues

## Implementation Timeline

- **Phase 1**: 2 hours - Create simplified factory (no caching methods)
- **Phase 2**: 2-3 hours - Update APIRouteRegistry with dependency extraction and route data storage
- **Phase 3**: 30 minutes - Update Router to read dependencies from route data
- **Phase 4**: 30 minutes - Remove separate cache file requirements
- **Testing**: 2-3 hours - Unit and integration tests

**Total Estimated Time**: 5-7 hours

## Conclusion

This implementation solves the fundamental dependency injection problem in the Gravitycar framework's API routing system. The simplified APIControllerFactory approach provides:

- **Complete Pure DI**: All controllers get proper dependencies
- **Integrated Caching**: Dependencies stored in existing api_routes.php cache file
- **Simplified Architecture**: Single factory method, no separate cache management
- **Low Maintenance**: Automatic dependency discovery during route registration
- **Developer Friendly**: Clear errors and integrated with existing caching system
- **Future Proof**: Easily extensible for new controllers and dependencies

The solution is architecturally sound, uses existing infrastructure, and maintains the framework's performance and maintainability goals.