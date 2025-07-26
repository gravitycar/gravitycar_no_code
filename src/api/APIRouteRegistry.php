<?php
namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * APIRouteRegistry: Discovers, registers, and caches API routes for all controllers.
 */
class APIRouteRegistry {
    /** @var string */
    protected string $apiControllersDirPath;
    /** @var string */
    protected string $cacheFilePath;
    /** @var Logger */
    protected Logger $logger;
    /** @var array */
    protected array $routes = [];

    public function __construct() {
        $this->logger = new Logger(static::class);
        $this->apiControllersDirPath = 'src/models';
        $this->cacheFilePath = 'cache/api_routes.php';
        $this->discoverAndRegisterRoutes();
    }

    /**
     * Discover API controllers and register their routes
     */
    protected function discoverAndRegisterRoutes(): void {
        if (!is_dir($this->apiControllersDirPath)) {
            $this->logger->warning("API controllers directory not found: {$this->apiControllersDirPath}");
            return;
        }

        $dirs = scandir($this->apiControllersDirPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;

            $controllerDir = $this->apiControllersDirPath . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . 'api';
            if (!is_dir($controllerDir)) continue;

            $files = scandir($controllerDir);
            foreach ($files as $file) {
                if (preg_match('/^(.*)ApiController\.php$/', $file, $matches)) {
                    $className = "Gravitycar\\Models\\{$dir}\\Api\\{$matches[1]}ApiController";
                    if (class_exists($className)) {
                        $controller = new $className([], $this->logger);
                        $routes = $controller->registerRoutes();
                        foreach ($routes as $route => $handler) {
                            $this->routes[$route] = [
                                'controller' => $className,
                                'handler' => $handler,
                            ];
                        }
                    } else {
                        $this->logger->warning("API controller class not found: $className");
                    }
                }
            }
        }
        $this->cacheRoutes();
    }

    /**
     * Cache registered routes for performance
     */
    protected function cacheRoutes(): void {
        if (!is_dir(dirname($this->cacheFilePath))) {
            mkdir(dirname($this->cacheFilePath), 0755, true);
        }

        $content = '<?php return ' . var_export($this->routes, true) . ';';
        if (file_put_contents($this->cacheFilePath, $content) === false) {
            $this->logger->warning("Failed to write API route cache file: {$this->cacheFilePath}");
        } else {
            $this->logger->info("API route cache written: {$this->cacheFilePath}");
        }
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array {
        if (!empty($this->routes)) {
            return $this->routes;
        }
        if (file_exists($this->cacheFilePath)) {
            $data = include $this->cacheFilePath;
            if (is_array($data)) {
                $this->routes = $data;
                return $data;
            }
        }
        return [];
    }
}
