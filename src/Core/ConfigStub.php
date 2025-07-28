<?php
namespace Gravitycar\Core;

use Monolog\Logger;
use Exception;

/**
 * ConfigStub provides fallback configuration when the main Config service fails.
 * Returns reasonable defaults and logs warnings about missing configuration.
 */
class ConfigStub extends Config {
    private ?Exception $originalError;

    public function __construct(Logger $logger, Exception $error = null) {
        $this->logger = $logger;
        $this->originalError = $error;
        $this->config = $this->getDefaultConfig();
        $this->configFilePath = 'config.php';

        $this->logger->warning('Using ConfigStub with default values - config.php may be missing or invalid');
        if ($error) {
            $this->logger->error('Original config error: ' . $error->getMessage());
        }
    }

    public function get(string $key, $default = null) {
        $this->logger->debug("Using default config for key: $key (config.php not available)");
        return parent::get($key, $default);
    }

    public function set(string $key, $value): void {
        $this->logger->warning("Attempting to set config key '$key' but using ConfigStub - changes will not persist");
        parent::set($key, $value);
    }

    public function write(): void {
        $this->logger->error('Cannot write config file - ConfigStub is in use due to configuration errors');
        throw new \Gravitycar\Exceptions\GCException(
            'Configuration file cannot be written - check file permissions and configuration',
            $this->logger,
            $this->originalError
        );
    }

    public function configFileExists(): bool {
        return false; // Always false since we're using the stub
    }

    private function getDefaultConfig(): array {
        return [
            'database' => [
                'driver' => 'pdo_mysql',
                'host' => 'localhost',
                'dbname' => 'gravitycar',
                'user' => 'root',
                'password' => '',
                'port' => 3306
            ],
            'app' => [
                'debug' => true,
                'environment' => 'development',
                'name' => 'Gravitycar Framework'
            ],
            'logging' => [
                'level' => 'info',
                'path' => 'logs/gravitycar.log'
            ],
            'installed' => false
        ];
    }

    public function getOriginalError(): ?Exception {
        return $this->originalError;
    }
}
