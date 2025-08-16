<?php

namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Psr\Log\LoggerInterface;

/**
 * Enhanced APIRouteRegistry
 * 
 * Discovers, registers, and organizes routes by method and path length
 * for efficient scoring-based route matching.
 */
class APIRouteRegistry
{
    protected string $apiControllersDirPath;
    protected string $modelsDirPath;
    protected string $cacheFilePath;
    protected LoggerInterface $logger;
    protected array $routes = [];
    protected array $groupedRoutes = [];

    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
        $this->apiControllersDirPath = 'src/models';
        $this->modelsDirPath = 'src/models';
        $this->cacheFilePath = 'cache/api_routes.php';
        
        // Try to load from cache first, only discover routes if cache doesn't exist
        if (!$this->loadFromCache()) {
            $this->discoverAndRegisterRoutes();
        }
    }

    /**
     * Force rebuild of routes cache (useful for development or when models change)
     */
    public function rebuildCache(): void
    {
        $this->logger->info("Forcing cache rebuild for API routes");
        $this->routes = [];
        $this->groupedRoutes = [];
        $this->discoverAndRegisterRoutes();
    }

    /**
     * Discover API controllers and models, then register their routes
     */
    protected function discoverAndRegisterRoutes(): void
    {
        // Register core authentication controller
        $this->registerCoreControllers();
        
        // Traditional API controller discovery
        $this->discoverAPIControllers();
        
        // Auto-discover ModelBase routes from metadata
        $this->discoverModelRoutes();
        
        // Group routes for efficient lookup
        $this->groupRoutesByMethodAndLength();
        
        // Cache the results
        $this->cacheRoutes();
    }

    /**
     * Register core framework controllers
     */
    protected function registerCoreControllers(): void
    {
        // Register AuthController
        try {
            $authController = new \Gravitycar\Api\AuthController();
            $this->register($authController, \Gravitycar\Api\AuthController::class);
        } catch (\Exception $e) {
            $this->logger->error("Failed to register AuthController: " . $e->getMessage());
        }
    }    /**
     * Discover traditional API controllers
     */
    protected function discoverAPIControllers(): void
    {
        if (!is_dir($this->apiControllersDirPath)) {
            $this->logger->warning("API controllers directory not found: {$this->apiControllersDirPath}");
            return;
        }

        // First, discover the global ModelBaseAPIController
        $modelBaseAPIControllerClass = "Gravitycar\\Models\\Api\\Api\\ModelBaseAPIController";
        if (class_exists($modelBaseAPIControllerClass)) {
            $controller = new $modelBaseAPIControllerClass();
            $this->register($controller, $modelBaseAPIControllerClass);
        }

        // Then discover model-specific API controllers
        $dirs = scandir($this->apiControllersDirPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;

            $controllerDir = $this->apiControllersDirPath . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . 'api';
            if (!is_dir($controllerDir)) continue;

            $files = scandir($controllerDir);
            foreach ($files as $file) {
                if (preg_match('/^(.*)APIController\.php$/', $file, $matches)) {
                    $className = "Gravitycar\\Models\\{$dir}\\Api\\{$matches[1]}APIController";
                    if (class_exists($className)) {
                        try {
                            $controller = new $className();
                            $this->register($controller, $className);
                        } catch (\Exception $e) {
                            $this->logger->error("Failed to instantiate API controller {$className}: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    /**
     * Discover ModelBase routes from metadata
     */
    protected function discoverModelRoutes(): void
    {
        if (!is_dir($this->modelsDirPath)) {
            $this->logger->warning("Models directory not found: {$this->modelsDirPath}");
            return;
        }

        $dirs = scandir($this->modelsDirPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;

            $modelDir = $this->modelsDirPath . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($modelDir)) continue;

            // Look for ModelBase subclasses
            $files = scandir($modelDir);
            foreach ($files as $file) {
                if (preg_match('/^(.*)\.php$/', $file, $matches) && $matches[1] !== 'api') {
                    $modelName = $matches[1]; // This is the clean model name like "Users"
                    $className = "Gravitycar\\Models\\{$dir}\\{$modelName}";
                    if (class_exists($className)) {
                        try {
                            // Use ModelFactory for proper model instantiation with all dependencies
                            $model = \Gravitycar\Factories\ModelFactory::new($modelName);
                            $this->register($model, $className);
                        } catch (\Exception $e) {
                            $this->logger->error("Failed to instantiate model {$modelName}: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    /**
     * Register routes from an instantiated object (API controller or ModelBase)
     */
    protected function register(object $instance, string $className): void
    {
        try {
            if (!method_exists($instance, 'registerRoutes')) {
                return;
            }

            $routes = $instance->registerRoutes();
            foreach ($routes as $route) {
                $this->registerRoute($route);
            }

            $this->logger->info("Registered routes from: {$className}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to register routes from {$className}: " . $e->getMessage());
        }
    }

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
            
            $this->routes[] = $route;
            
            $this->logger->debug("Registered route", [
                'method' => $route['method'],
                'path' => $route['path'],
                'apiClass' => $route['apiClass']
            ]);
        } catch (GCException $e) {
            $this->logger->error("Failed to register route: " . $e->getMessage(), ['route' => $route]);
        }
    }

    /**
     * Validate route format and throw GCException for invalid routes
     */
    public function validateRouteFormat(array $route): void
    {
        $requiredFields = ['method', 'path', 'apiClass', 'apiMethod'];
        
        foreach ($requiredFields as $field) {
            if (!isset($route[$field]) || empty($route[$field])) {
                throw new GCException("Route missing required field: {$field}", ['route' => $route]);
            }
        }
        
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        if (!in_array(strtoupper($route['method']), $validMethods)) {
            throw new GCException("Invalid HTTP method: {$route['method']}", ['route' => $route]);
        }
        
        if (!str_starts_with($route['path'], '/')) {
            throw new GCException("Route path must start with '/': {$route['path']}", ['route' => $route]);
        }
        
        // Resolve the fully qualified class name before method validation
        $resolvedClassName = $this->resolveControllerClassName($route['apiClass']);
        if (!$resolvedClassName) {
            throw new GCException("API class not found: {$route['apiClass']}", ['route' => $route]);
        }
        
        if (!method_exists($resolvedClassName, $route['apiMethod'])) {
            throw new GCException("API method '{$route['apiMethod']}' not found in class '{$resolvedClassName}'", ['route' => $route]);
        }
        
        // Validate parameter names if provided
        if (isset($route['parameterNames'])) {
            $pathComponents = $this->parsePathComponents($route['path']);
            if (count($route['parameterNames']) !== count($pathComponents)) {
                throw new GCException("Parameter names count must match path components count", [
                    'parameterNames' => $route['parameterNames'],
                    'pathComponents' => $pathComponents,
                    'route' => $route
                ]);
            }
        }
    }

    /**
     * Parse a path string into components
     */
    public function parsePathComponents(string $path): array
    {
        if (empty($path) || $path === '/') {
            return [];
        }

        // Remove leading and trailing slashes, then split
        $path = trim($path, '/');
        return explode('/', $path);
    }

    /**
     * Get path length from path string
     */
    public function getPathLength(string $path): int
    {
        return count($this->parsePathComponents($path));
    }

    /**
     * Resolve controller class name using hybrid resolution strategy
     */
    public function resolveControllerClassName(string $apiClass): ?string
    {
        // Case 1: Already fully qualified
        if (str_contains($apiClass, '\\')) {
            return (class_exists($apiClass) || interface_exists($apiClass)) ? $apiClass : null;
        }
        
        // Case 2: Model-based convention
        $modelName = str_replace('APIController', '', $apiClass);
        $conventionClass = "Gravitycar\\Models\\{$modelName}\\Api\\{$apiClass}";
        if (class_exists($conventionClass)) {
            return $conventionClass;
        }
        
        // Case 3: Fallback to discovered controllers registry
        return $this->findInDiscoveredControllers($apiClass);
    }

    /**
     * Find controller in already discovered controllers
     */
    protected function findInDiscoveredControllers(string $apiClass): ?string
    {
        foreach ($this->routes as $route) {
            if (isset($route['resolvedApiClass']) && 
                basename($route['resolvedApiClass']) === $apiClass) {
                return $route['resolvedApiClass'];
            }
        }
        return null;
    }

    /**
     * Group routes by HTTP method and path length for efficient scoring
     */
    public function groupRoutesByMethodAndLength(): array
    {
        $this->groupedRoutes = [];
        
        foreach ($this->routes as $route) {
            $method = strtoupper($route['method']);
            $pathLength = $route['pathLength'];
            
            if (!isset($this->groupedRoutes[$method])) {
                $this->groupedRoutes[$method] = [];
            }
            
            if (!isset($this->groupedRoutes[$method][$pathLength])) {
                $this->groupedRoutes[$method][$pathLength] = [];
            }
            
            $this->groupedRoutes[$method][$pathLength][] = $route;
        }
        
        return $this->groupedRoutes;
    }

    /**
     * Get routes by method and path length
     */
    public function getRoutesByMethodAndLength(string $method, int $pathLength): array
    {
        $method = strtoupper($method);
        return $this->groupedRoutes[$method][$pathLength] ?? [];
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get grouped routes
     */
    public function getGroupedRoutes(): array
    {
        return $this->groupedRoutes;
    }

    /**
     * Load routes from cache
     */
    protected function loadFromCache(): bool
    {
        if (!file_exists($this->cacheFilePath)) {
            return false;
        }

        try {
            $data = include $this->cacheFilePath;
            if (is_array($data) && isset($data['routes']) && isset($data['groupedRoutes'])) {
                $this->routes = $data['routes'];
                $this->groupedRoutes = $data['groupedRoutes'];
                $this->logger->info("Loaded routes from cache");
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->warning("Failed to load routes from cache: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Cache registered routes for performance
     */
    protected function cacheRoutes(): void
    {
        try {
            if (!is_dir(dirname($this->cacheFilePath))) {
                mkdir(dirname($this->cacheFilePath), 0755, true);
            }

            $data = [
                'routes' => $this->routes,
                'groupedRoutes' => $this->groupedRoutes,
                'cached_at' => time()
            ];

            $content = '<?php return ' . var_export($data, true) . ';';
            if (file_put_contents($this->cacheFilePath, $content) === false) {
                $this->logger->warning("Failed to write API route cache file: {$this->cacheFilePath}");
            } else {
                $this->logger->info("API route cache written: {$this->cacheFilePath}");
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to cache routes: " . $e->getMessage());
        }
    }
}
