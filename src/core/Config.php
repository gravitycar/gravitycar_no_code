<?php
namespace Gravitycar\Core;

use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Config class for centralized configuration management.
 * Loads, provides, and writes configuration values for the framework.
 */
class Config {
    /** @var array */
    protected array $config = [];
    /** @var string */
    protected string $configFilePath = 'config.php';
    /** @var Logger */
    protected Logger $logger;

    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->loadConfig();
    }

    /**
     * Load configuration from file
     */
    protected function loadConfig(): void {
        if (!file_exists($this->configFilePath)) {
            throw new GCException('Config file not found',
                ['config_file_path' => $this->configFilePath]);
        }
        $config = include $this->configFilePath;
        if (!is_array($config)) {
            throw new GCException('Config file is not a valid array',
                ['config_file_path' => $this->configFilePath, 'config_type' => gettype($this->config)]);
        }
        $this->config = $config;
    }

    /**
     * Get a config value by key (supports dot notation for nested keys)
     */
    public function get(string $key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        return $value;
    }

    /**
     * Set a config value by key (supports dot notation for nested keys)
     */
    public function set(string $key, $value): void {
        $keys = explode('.', $key);
        $ref =& $this->config;
        foreach ($keys as $k) {
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref =& $ref[$k];
        }
        $ref = $value;
    }

    /**
     * Write the config array to the config file
     */
    public function write(): void {
        $content = '<?php return ' . var_export($this->config, true) . ';';
        if (file_put_contents($this->configFilePath, $content) === false) {
            throw new GCException('Failed to write config file',
                ['config_file_path' => $this->configFilePath, 'file_writable' => is_writable($this->configFilePath)]);
        }
    }

    /**
     * Check if the config file exists and is writable
     */
    public function configFileExists(): bool {
        return file_exists($this->configFilePath) && is_writable($this->configFilePath);
    }

    /**
     * Get database connection parameters
     */
    public function getDatabaseParams(): array {
        return $this->get('database', []);
    }

    /**
     * Get logging configuration
     */
    public function getLoggingConfig(): array {
        return $this->get('logging', []);
    }

    /**
     * Get application settings
     */
    public function getAppSettings(): array {
        return $this->get('app', []);
    }
}
