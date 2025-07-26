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

    public function __construct() {
        $this->routeRegistry = new APIRouteRegistry();
        $this->logger = new Logger(static::class);
    }

    /**
     * Route an API request to the correct controller and handler
     */
    public function route(string $method, string $path, array $params = []) {
        $routes = $this->routeRegistry->getRoutes();

        foreach ($routes as $route => $info) {
            if ($this->matchRoute($route, $method, $path)) {
                $controllerClass = $info['controller'];
                $handlerMethod = $info['handler'];

                if (!class_exists($controllerClass)) {
                    throw new GCException("API controller class not found: $controllerClass", $this->logger);
                }

                $controller = new $controllerClass([], $this->logger);
                if (!method_exists($controller, $handlerMethod)) {
                    throw new GCException("Handler method not found: $handlerMethod in $controllerClass", $this->logger);
                }

                return $controller->$handlerMethod($params);
            }
        }

        throw new GCException("No matching route found for $method $path", $this->logger);
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
