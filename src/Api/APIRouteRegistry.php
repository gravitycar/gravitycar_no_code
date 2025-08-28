<?php

namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Psr\Log\LoggerInterface;

/**
 * Enhanced APIRouteRegistry (Singleton)
 * 
 * Discovers, registers, and organizes routes by method and path length
 * for efficient scoring-based route matching.
 */
class APIRouteRegistry
{
    private static ?APIRouteRegistry $instance = null;
    protected string $apiControllersDirPath;
    protected string $modelsDirPath;
    protected string $cacheFilePath;
    protected LoggerInterface $logger;
    protected array $routes = [];
    protected array $groupedRoutes = [];

    private function __construct()
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
     * Get the singleton instance
     */
    public static function getInstance(): APIRouteRegistry
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the singleton
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the singleton
     */
    public function __wakeup() {}

    /**
     * Force rebuild of routes cache (useful for development or when models change)
     */
    public function rebuildCache(): void
    {
        $this->logger->info("***** Forcing cache rebuild for API routes *****");
        $this->routes = [];
        $this->groupedRoutes = [];
        $this->logger->info("***** About to call discoverAndRegisterRoutes *****");
        $this->discoverAndRegisterRoutes();
        $this->logger->info("***** Finished discoverAndRegisterRoutes. Routes count: " . count($this->routes) . " *****");
    }

    /**
     * Discover API controllers and models, then register their routes
     */
    protected function discoverAndRegisterRoutes(): void
    {
        // Discover all ApiControllerBase subclasses automatically
        $this->discoverAPIControllers();
        
        // Auto-discover ModelBase routes from metadata
        $this->discoverModelRoutes();
        
        // Group routes for efficient lookup
        $this->groupRoutesByMethodAndLength();
        
        // Cache the results
        $this->cacheRoutes();
    }

    /**
     * Discover all API controllers that extend ApiControllerBase
     */
    protected function discoverAPIControllers(): void
    {
        $this->logger->info("Starting automatic discovery of ApiControllerBase subclasses");
        
        // First, register the global ModelBaseAPIController if it exists
        $modelBaseAPIControllerClass = "Gravitycar\\Models\\Api\\Api\\ModelBaseAPIController";
        if (class_exists($modelBaseAPIControllerClass)) {
            try {
                $controller = new $modelBaseAPIControllerClass();
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
                    $controller = new $className();
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
                                $controller = new $className();
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
    
    /**
     * Extract class name from PHP file path
     */
    private function getClassNameFromFile(string $filePath): ?string {
        $fileName = basename($filePath, '.php');
        $namespace = 'Gravitycar\\Api\\';
        $className = $namespace . $fileName;
        
        return class_exists($className) ? $className : null;
    }
    
    /**
     * Check if a class extends ApiControllerBase
     */
    private function extendsApiControllerBase(string $className): bool {
        if (!class_exists($className)) {
            return false;
        }
        
        $reflection = new \ReflectionClass($className);
        $parentClass = $reflection->getParentClass();
        
        while ($parentClass) {
            if ($parentClass->getName() === 'Gravitycar\\Api\\ApiControllerBase') {
                return true;
            }
            $parentClass = $parentClass->getParentClass();
        }
        
        return false;
    }

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
                                $model = \Gravitycar\Factories\ModelFactory::new($modelName);
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
            
            // Count dynamic path components (those with {} or wildcards like ?)
            $dynamicComponents = array_filter($pathComponents, function($component) {
                return (str_starts_with($component, '{') && str_ends_with($component, '}')) || $component === '?';
            });
            
            if (count($route['parameterNames']) !== count($dynamicComponents)) {
                throw new GCException("Parameter names count must match dynamic path components count", [
                    'parameterNames' => $route['parameterNames'],
                    'pathComponents' => $pathComponents,
                    'dynamicComponents' => array_values($dynamicComponents),
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
     * Get all routes for a specific model (for documentation purposes)
     * This generates implied static routes based on ModelBaseAPIController wildcards
     */
    public function getModelRoutes(string $modelName): array {
        $modelRoutes = [];
        
        // First, find any explicitly registered routes for this model
        foreach ($this->routes as $route) {
            if ($this->isRouteForModel($route, $modelName)) {
                $modelRoutes[] = $route;
            }
        }
        
        // Then generate implied routes from ModelBaseAPIController wildcards for documentation
        $impliedRoutes = $this->generateImpliedModelRoutes($modelName);
        $modelRoutes = array_merge($modelRoutes, $impliedRoutes);
        
        return $modelRoutes;
    }
    
    /**
     * Generate implied model routes from ModelBaseAPIController wildcards (for documentation)
     */
    private function generateImpliedModelRoutes(string $modelName): array {
        $impliedRoutes = [];
        
        // Find ModelBaseAPIController wildcard routes
        foreach ($this->routes as $route) {
            if ($route['apiClass'] === 'Gravitycar\\Models\\Api\\Api\\ModelBaseAPIController' &&
                str_contains($route['path'], '/?')) {
                
                // Convert wildcard to specific route for documentation
                $impliedRoute = $this->convertWildcardToImpliedRoute($route, $modelName);
                if ($impliedRoute) {
                    $impliedRoutes[] = $impliedRoute;
                }
            }
        }
        
        return $impliedRoutes;
    }
    
    /**
     * Convert a wildcard route to an implied static route (for documentation only)
     */
    private function convertWildcardToImpliedRoute(array $wildcardRoute, string $modelName): ?array {
        $impliedRoute = $wildcardRoute;
        
        // Convert path: replace /? with /{modelName}
        $path = $wildcardRoute['path'];
        
        if (str_starts_with($path, '/?')) {
            // Replace first /? with /{modelName}
            $path = '/' . $modelName . substr($path, 2);
        }
        
        $impliedRoute['path'] = $path;
        $impliedRoute['isImplied'] = true; // Mark as implied for documentation
        
        return $impliedRoute;
    }
    
    /**
     * Replace wildcard placeholders with actual model name in route path
     */
    private function replaceModelNameInPath(string $path, array $parameterNames, string $modelName): string {
        // Split path into components
        $pathComponents = explode('/', trim($path, '/'));
        
        // Find the position of 'modelName' in parameterNames and replace in path
        $modelNameIndex = array_search('modelName', $parameterNames);
        if ($modelNameIndex !== false && isset($pathComponents[$modelNameIndex])) {
            $pathComponents[$modelNameIndex] = $modelName;
        }
        
        return '/' . implode('/', $pathComponents);
    }
    
    /**
     * Get routes summary for API documentation
     */
    public function getRoutesSummary(): array {
        $summary = [
            'total_routes' => count($this->routes),
            'routes_by_method' => [],
            'routes_by_model' => []
        ];
        
        // Group by HTTP method
        foreach ($this->routes as $route) {
            $method = $route['method'];
            if (!isset($summary['routes_by_method'][$method])) {
                $summary['routes_by_method'][$method] = 0;
            }
            $summary['routes_by_method'][$method]++;
        }
        
        // Group by model
        $routesByModel = $this->getRoutesByModel();
        foreach ($routesByModel as $modelName => $routes) {
            $summary['routes_by_model'][$modelName] = count($routes);
        }
        
        return $summary;
    }
    
    /**
     * Get endpoint documentation for OpenAPI
     */
    public function getEndpointDocumentation(string $path, string $method): array {
        foreach ($this->routes as $route) {
            if ($route['path'] === $path && $route['method'] === $method) {
                return [
                    'path' => $route['path'],
                    'method' => $route['method'],
                    'apiClass' => $route['apiClass'],
                    'apiMethod' => $route['apiMethod'],
                    'parameterNames' => $route['parameterNames'] ?? [],
                    'description' => $this->generateEndpointDescription($route)
                ];
            }
        }
        return [];
    }
    
    /**
     * Get all unique endpoint paths
     */
    public function getAllEndpointPaths(): array {
        $paths = [];
        foreach ($this->routes as $route) {
            $paths[] = $route['path'];
        }
        return array_unique($paths);
    }
    
    /**
     * Get routes grouped by model
     */
    public function getRoutesByModel(): array {
        $routesByModel = [];
        
        foreach ($this->routes as $route) {
            $modelName = $this->extractModelFromRoute($route);
            if ($modelName) {
                if (!isset($routesByModel[$modelName])) {
                    $routesByModel[$modelName] = [];
                }
                $routesByModel[$modelName][] = $route;
            }
        }
        
        return $routesByModel;
    }
    
    /**
     * Check if a route belongs to a specific model
     */
    private function isRouteForModel(array $route, string $modelName): bool {
        // Method 1: Check if model name appears in path
        if (stripos($route['path'], $modelName) !== false) {
            return true;
        }
        
        // Method 2: Use parameterNames to find modelName position and check path
        $extractedModel = $this->extractModelFromRoute($route);
        return $extractedModel === $modelName;
    }
    
    /**
     * Extract model name from route using parameterNames array for accurate positioning
     */
    private function extractModelFromRoute(array $route): ?string {
        // Method 1: Extract from apiClass if it contains model name
        if (isset($route['apiClass'])) {
            if (preg_match('/Models\\\\([^\\\\]+)\\\\/', $route['apiClass'], $matches)) {
                return $matches[1];
            }
        }
        
        // Method 2: Extract from path using parameterNames to find correct position
        return $this->extractModelFromRoutePath($route['path'], $route['parameterNames'] ?? []);
    }
    
    /**
     * Extract model name from route path using parameterNames array to find modelName position
     */
    private function extractModelFromRoutePath(string $path, array $parameterNames): ?string {
        // Find the index of 'modelName' in parameterNames
        $modelNameIndex = array_search('modelName', $parameterNames);
        if ($modelNameIndex === false) {
            // Try to extract from first path component as fallback
            $pathComponents = explode('/', trim($path, '/'));
            if (!empty($pathComponents) && !str_contains($pathComponents[0], '{')) {
                return $pathComponents[0];
            }
            return null; // This is a wildcard route template, no specific model name
        }
        
        // Split path and get component at modelName index
        $pathComponents = explode('/', trim($path, '/'));
        if (isset($pathComponents[$modelNameIndex]) && !str_contains($pathComponents[$modelNameIndex], '{')) {
            return $pathComponents[$modelNameIndex];
        }
        
        return null;
    }
    
    /**
     * Generate endpoint description for documentation
     */
    private function generateEndpointDescription(array $route): string {
        $method = $route['method'];
        $modelName = $this->extractModelFromRoute($route);
        
        if ($modelName) {
            switch ($method) {
                case 'GET':
                    if (str_contains($route['path'], '{')) {
                        return "Get a specific {$modelName} record";
                    }
                    return "List all {$modelName} records";
                case 'POST':
                    return "Create a new {$modelName} record";
                case 'PUT':
                case 'PATCH':
                    return "Update a {$modelName} record";
                case 'DELETE':
                    return "Delete a {$modelName} record";
            }
        }
        
        return "{$method} {$route['path']}";
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
