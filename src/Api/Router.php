<?php
namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * API Router: Routes API requests to appropriate handlers
 */
class Router {
    /** @var APIRouteRegistry */
    protected APIRouteRegistry $routeRegistry;
    /** @var Logger */
    protected Logger $logger;
    /** @var \Gravitycar\Metadata\MetadataEngine */
    protected $metadataEngine;

    public function __construct(\Gravitycar\Metadata\MetadataEngine $metadataEngine, Logger $logger) {
        $this->routeRegistry = new APIRouteRegistry();
        $this->logger = $logger;
        $this->metadataEngine = $metadataEngine;
    }

    /**
     * Route an API request to the correct controller and handler
     */
    public function route(string $method, string $path, array $params = []) {
        $routes = $this->routeRegistry->getRoutes();

        // If no routes are registered, provide a helpful error message
        if (empty($routes)) {
            throw new GCException("No routes registered. API controllers may not be properly configured.",
                ['method' => $method, 'path' => $path, 'routes_count' => 0]);
        }

        foreach ($routes as $route => $info) {
            if ($this->matchRoute($route, $method, $path)) {
                $controllerClass = $info['controller'];
                $handlerMethod = $info['handler'];

                if (!class_exists($controllerClass)) {
                    throw new GCException("API controller class not found: $controllerClass",
                        ['controller_class' => $controllerClass, 'route' => $route]);
                }

                $controller = new $controllerClass([], $this->logger);
                if (!method_exists($controller, $handlerMethod)) {
                    throw new GCException("Handler method not found: $handlerMethod in $controllerClass",
                        ['handler_method' => $handlerMethod, 'controller_class' => $controllerClass]);
                }

                return $controller->$handlerMethod($params);
            }
        }

        throw new GCException("No matching route found for $method $path",
            ['method' => $method, 'path' => $path, 'available_routes' => array_keys($routes)]);
    }

    /**
     * Handle incoming HTTP request
     */
    public function handleRequest(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $params = $this->getRequestParams();

        $this->logger->info("Routing request: $method $path");

        try {
            $result = $this->route($method, $path, $params);

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
     * Match a route pattern to the request method and path
     */
    protected function matchRoute(string $route, string $method, string $path): bool {
        // Simple match: route pattern is METHOD PATH
        [$routeMethod, $routePath] = explode(' ', $route, 2);
        return strtoupper($method) === strtoupper($routeMethod) && $routePath === $path;
    }
}
