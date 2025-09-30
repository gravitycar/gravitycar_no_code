<?php
namespace Gravitycar\Core;

use Aura\Di\Container;
use Monolog\Logger;
use Gravitycar\Core\Config;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Core\ContainerConfig;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Schema\SchemaGenerator;
use Gravitycar\Api\Router;
use Gravitycar\Exceptions\GCException;
use Exception;

/**
 * Main Gravitycar Application Class
 * 
 * Orchestrates the bootstrap process and manages the application lifecycle.
 * This class pulls together all framework components and makes them work together.
 */
class Gravitycar {
    private ?Container $container = null;
    private ?Logger $logger = null;
    private ?Config $config = null;
    private bool $isBootstrapped = false;
    private string $environment = 'production';
    private array $bootstrapSteps = [];
    private array $configOptions = [];

    /**
     * Create new Gravitycar application instance
     * 
     * @param array|string $config Configuration array or path to config file
     */
    public function __construct($config = []) {
        if (is_string($config)) {
            $this->configOptions = ['config_path' => $config];
        } elseif (is_array($config)) {
            $this->configOptions = $config;
        }

        $this->detectEnvironment();
        $this->initializeBootstrapSteps();
    }

    /**
     * Bootstrap the entire application
     * 
     * @throws GCException If bootstrap fails
     */
    public function bootstrap(): self {
        if ($this->isBootstrapped) {
            return $this;
        }

        try {
            foreach ($this->bootstrapSteps as $step => $method) {
                $this->logBootstrapStep($step);
                $this->$method();
            }

            $this->isBootstrapped = true;
            $this->logBootstrapComplete();

        } catch (Exception $e) {
            $this->handleBootstrapError($e);
            throw new GCException('Application bootstrap failed', [
                'error' => $e->getMessage(),
                'step' => $this->getCurrentBootstrapStep()
            ], 0, $e);
        }

        return $this;
    }

    /**
     * Run the application (handle HTTP requests)
     */
    public function run(): void {
        if (!$this->isBootstrapped) {
            throw new GCException('Application not bootstrapped. Call bootstrap() first.');
        }

        try {
            $this->handleRequest();
        } catch (\Throwable $e) {
            $this->handleRuntimeError($e);
        }
    }

    /**
     * Get the DI container
     */
    public function getContainer(): Container {
        if ($this->container === null) {
            throw new GCException('Container not initialized. Bootstrap the application first.');
        }
        return $this->container;
    }

    /**
     * Check if application is bootstrapped
     */
    public function isBootstrapped(): bool {
        return $this->isBootstrapped;
    }

    /**
     * Get current environment
     */
    public function getEnvironment(): string {
        return $this->environment;
    }

    /**
     * Shutdown and cleanup resources
     */
    public function shutdown(): void {
        if ($this->logger) {
            $this->logger->debug('Gravitycar application shutting down');
        }

        // Cleanup resources
        $this->container = null;
        $this->logger = null;
        $this->config = null;
        $this->isBootstrapped = false;
    }

    /**
     * Initialize bootstrap steps in correct order
     */
    private function initializeBootstrapSteps(): void {
        $this->bootstrapSteps = [
            'services' => 'bootstrapServices',
            'configuration' => 'bootstrapConfiguration',
            'database' => 'bootstrapDatabase',
            'metadata' => 'bootstrapMetadata',
            'routing' => 'bootstrapRouting',
            'error_handling' => 'bootstrapErrorHandling'
        ];
    }

    /**
     * Detect current environment
     */
    private function detectEnvironment(): void {
        // Try Config first if available, then $_ENV (populated by Config), then configOptions
        $this->environment = ($this->config ? $this->config->getEnv('GRAVITYCAR_ENV') : null)
            ?? $_ENV['GRAVITYCAR_ENV']
            ?? $this->configOptions['environment'] 
            ?? 'production';
    }

    /**
     * Bootstrap services and DI container
     */
    private function bootstrapServices(): void {
        $this->container = ContainerConfig::getContainer();
        
        // Override config path if provided
        if (isset($this->configOptions['config_path'])) {
            // We'll need to modify the Config service to accept custom path
            // For now, log this requirement
            if ($this->logger) {
                $this->logger->debug('Custom config path requested', [
                    'path' => $this->configOptions['config_path']
                ]);
            }
        }
    }

    /**
     * Bootstrap configuration
     */
    private function bootstrapConfiguration(): void {
        $this->logger = ServiceLocator::getLogger();
        $this->config = ServiceLocator::getConfig();

        // Validate critical configuration
        $this->validateConfiguration();
    }

    /**
     * Bootstrap database connections
     */
    private function bootstrapDatabase(): void {
        $dbConnector = ServiceLocator::getDatabaseConnector();
        
        // Test database connection
        $dbConnector->testConnection();
        
        // Schema validation would be handled by SchemaGenerator if needed
        // For now, just ensure we can get the service
        ServiceLocator::getSchemaGenerator();
    }

    /**
     * Bootstrap metadata engine
     */
    private function bootstrapMetadata(): void {
        $metadataEngine = ServiceLocator::getMetadataEngine();
        
        // Load and cache metadata
        $metadata = $metadataEngine->loadAllMetadata();
        
        // The validateMetadata method is protected and requires metadata parameter
        // It's already called internally by loadAllMetadata, so we don't need to call it again
        $this->logger->debug('Metadata loaded successfully', [
            'models_count' => count($metadata['models'] ?? []),
            'relationships_count' => count($metadata['relationships'] ?? [])
        ]);
    }

    /**
     * Bootstrap API routing
     */
    private function bootstrapRouting(): void {
        $router = ServiceLocator::getRouter();
        
        // The Router class uses the 'route' method to handle requests
        // Route generation and caching would be handled by APIRouteRegistry
        // For now, just ensure we can get the router service
        $this->logger->debug('Router service initialized successfully');
    }

    /**
     * Bootstrap error handling
     */
    private function bootstrapErrorHandling(): void {
        // Set up global error handlers
        set_error_handler([$this, 'handlePhpError']);
        set_exception_handler([$this, 'handleUncaughtException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle HTTP requests
     */
    private function handleRequest(): void {
        $router = ServiceLocator::getRouter();
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        $this->logger->debug('Processing request', [
            'method' => $method,
            'uri' => $uri
        ]);

        // Router's handleRequest method gets parameters from $_SERVER internally
        $router->handleRequest();
    }

    /**
     * Validate critical configuration values
     */
    private function validateConfiguration(): void {
        $required = ['database.host', 'database.dbname'];

        foreach ($required as $key) {
            if ($this->config->get($key) === null) {
                throw new GCException("Required configuration missing: {$key}");
            }
        }
    }

    /**
     * Handle bootstrap errors with graceful degradation
     */
    private function handleBootstrapError(Exception $e): void {
        if ($this->logger) {
            $this->logger->error('Bootstrap error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } else {
            error_log("Gravitycar bootstrap error: " . $e->getMessage());
        }
    }

    /**
     * Handle runtime errors
     */
    private function handleRuntimeError(\Throwable $e): void {
        $this->logger->error('Runtime error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Only send HTTP response if not in CLI/test mode
        if (php_sapi_name() !== 'cli') {
            // Send appropriate HTTP response
            http_response_code(500);
            echo json_encode([
                'error' => 'Internal server error',
                'message' => $this->environment === 'development' ? $e->getMessage() : 'An error occurred'
            ]);
        }

        // Re-throw the exception for proper handling in tests
        throw $e;
    }

    /**
     * Get current bootstrap step for error reporting
     */
    private function getCurrentBootstrapStep(): ?string {
        // This would need more sophisticated tracking in a real implementation
        return 'unknown';
    }

    /**
     * Log individual bootstrap step
     */
    private function logBootstrapStep(string $step): void {
        if ($this->logger) {
            $this->logger->debug("Bootstrap step: {$step}");
        }
    }

    /**
     * Log bootstrap completion
     */
    private function logBootstrapComplete(): void {
        $this->logger->debug('Gravitycar application bootstrap completed successfully');
    }

    /**
     * Handle PHP errors
     */
    public function handlePhpError(int $severity, string $message, string $file, int $line): bool {
        if ($this->logger) {
            $this->logger->error('PHP Error', [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line
            ]);
        }
        return false; // Let PHP handle it normally
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleUncaughtException(\Throwable $e): void {
        $this->handleRuntimeError($e);
    }

    /**
     * Handle shutdown
     */
    public function handleShutdown(): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            if ($this->logger) {
                $this->logger->critical('Fatal error during shutdown', $error);
            }
        }
    }
}
