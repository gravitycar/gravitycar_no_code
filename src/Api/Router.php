<?php
namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * API Router: Routes API requests to appropriate handlers
 */
class Router {
    /** @var APIRouteRegistry */
    protected APIRouteRegistry $routeRegistry;
    /** @var Logger */
    protected Logger $logger;
    /** @var APIPathScorer */
    protected APIPathScorer $pathScorer;
    /** @var \Gravitycar\Metadata\MetadataEngine */
    protected $metadataEngine;

    public function __construct($serviceLocator) {
        if ($serviceLocator instanceof ServiceLocator) {
            $this->logger = $serviceLocator->get('logger');
            $this->metadataEngine = $serviceLocator->get('metadataEngine');
        } else {
            // Backward compatibility - assume it's MetadataEngine for old constructor
            $this->metadataEngine = $serviceLocator;
            $this->logger = ServiceLocator::getLogger();
        }
        
        $this->routeRegistry = new APIRouteRegistry($this->logger);
        $this->pathScorer = new APIPathScorer($this->logger);
    }

    /**
     * Route an API request to the correct controller and handler
     */
    public function route(string $method, string $path, array $additionalParams = []): mixed {
        // 1. Get routes from registry grouped by method and path length
        $pathLength = count($this->parsePathComponents($path));
        $candidateRoutes = $this->routeRegistry->getRoutesByMethodAndLength($method, $pathLength);
        
        // 2. Use APIPathScorer to find best match
        $bestRoute = null;
        if (!empty($candidateRoutes)) {
            $bestRoute = $this->pathScorer->findBestMatch($method, $path, $candidateRoutes);
        }
        
        // 3. If no exact length match, try other lengths for wildcard matching
        if (!$bestRoute) {
            $bestRoute = $this->findMatchingRoute($method, $path);
        }
        
        if (!$bestRoute) {
            $allRoutes = $this->routeRegistry->getRoutes();
            throw new GCException("No matching route found for $method $path", [
                'method' => $method, 
                'path' => $path, 
                'available_routes' => array_map(function($route) {
                    return $route['method'] . ' ' . $route['path'];
                }, array_slice($allRoutes, 0, 10))
            ]);
        }
        
        // 4. Create Request object for parameter extraction
        $request = new Request($path, $bestRoute['parameterNames'], $method);
        
        // 5. Execute route with Request object
        return $this->executeRoute($bestRoute, $request, $additionalParams);
    }

    /**
     * Handle incoming HTTP request
     */
    public function handleRequest(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Get additional parameters (query params, POST data)
        $additionalParams = $this->getRequestParams();

        $this->logger->info("Routing request: $method $path");

        try {
            // Use new route() method - Request object created internally
            $result = $this->route($method, $path, $additionalParams);

            // Only output if not in CLI/test mode
            if (php_sapi_name() !== 'cli') {
                // Set content type and output result
                header('Content-Type: application/json');
                echo json_encode($result);
            }

        } catch (GCException $e) {
            $this->logger->error("Routing error: " . $e->getMessage());

            // Only output if not in CLI/test mode
            if (php_sapi_name() !== 'cli') {
                http_response_code(404);
                echo json_encode(['error' => 'Route not found', 'message' => $e->getMessage()]);
            }

            // Re-throw the exception for proper error handling in tests
            throw $e;
        }
    }

    /**
     * Find matching route using all available path lengths
     */
    protected function findMatchingRoute(string $method, string $path): ?array {
        $pathLength = count($this->parsePathComponents($path));
        
        // Try other path lengths for wildcard matching
        $allMethodRoutes = $this->routeRegistry->getGroupedRoutes()[$method] ?? [];
        foreach ($allMethodRoutes as $length => $routes) {
            if ($length !== $pathLength) {
                $bestRoute = $this->pathScorer->findBestMatch($method, $path, $routes);
                if ($bestRoute) {
                    return $bestRoute;
                }
            }
        }
        
        return null;
    }

    /**
     * Execute route with Request object
     */
    protected function executeRoute(array $route, Request $request, array $additionalParams = []): mixed {
        $controllerClass = $route['apiClass'];
        $handlerMethod = $route['apiMethod'];
        
        if (!class_exists($controllerClass)) {
            throw new GCException("API controller class not found: $controllerClass", [
                'controller_class' => $controllerClass,
                'route' => $route['path']
            ]);
        }
        
        $controller = new $controllerClass($this->logger);
        
        if (!method_exists($controller, $handlerMethod)) {
            throw new GCException("Handler method not found: $handlerMethod in $controllerClass", [
                'handler_method' => $handlerMethod,
                'controller_class' => $controllerClass
            ]);
        }
        
        // Validate Request parameters
        $this->validateRequestParameters($request, $route);
        
        // Call controller method with Request object
        return $controller->$handlerMethod($request, $additionalParams);
    }

    /**
     * Validate Request object has required parameters
     */
    protected function validateRequestParameters(Request $request, array $route): void {
        $expectedParams = array_filter($route['parameterNames']); // Remove empty parameter names
        
        foreach ($expectedParams as $paramName) {
            if (!$request->has($paramName)) {
                throw new GCException("Missing required route parameter: $paramName", [
                    'route' => $route['path'],
                    'expected_params' => $expectedParams,
                    'available_params' => array_keys($request->all())
                ]);
            }
        }
    }

    /**
     * Get request parameters from various sources
     */
    protected function getRequestParams(): array {
        $params = [];

        // GET parameters
        $params = array_merge($params, $_GET);

        // POST/PUT/PATCH body
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            $jsonData = json_decode($input, true);
            if ($jsonData) {
                $params = array_merge($params, $jsonData);
            } else {
                $params = array_merge($params, $_POST);
            }
        }

        return $params;
    }

    /**
     * Parse a path string into components
     */
    protected function parsePathComponents(string $path): array {
        if (empty($path) || $path === '/') {
            return [];
        }

        // Remove leading and trailing slashes, then split
        $path = trim($path, '/');
        return explode('/', $path);
    }
}
