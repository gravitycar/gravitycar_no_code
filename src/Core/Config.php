<?php
namespace Gravitycar\Core;

use Gravitycar\Exceptions\GCException;

/**
 * Config class for centralized configuration management.
 * Loads, provides, and writes configuration values for the framework.
 */
class Config {
    /** @var array */
    protected array $config = [];
    /** @var array */
    protected array $env = [];
    /** @var string */
    protected string $configFilePath;
    /** @var string */
    protected string $envFilePath;

    public function __construct() {
        // Find the project root directory (where config.php should be)
        $this->configFilePath = $this->findProjectFile('config.php');
        $this->envFilePath = $this->findProjectFile('.env');
        
        $this->loadEnv();
        $this->loadConfig();
    }

    /**
     * Find a file in the project root directory by traversing up from current directory
     */
    protected function findProjectFile(string $filename): string {
        $currentDir = getcwd();
        $maxLevels = 10; // Prevent infinite loops
        
        for ($i = 0; $i < $maxLevels; $i++) {
            $filePath = $currentDir . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($filePath)) {
                return $filePath;
            }
            
            // Check if we can find composer.json or vendor directory as indicators of project root
            $composerPath = $currentDir . DIRECTORY_SEPARATOR . 'composer.json';
            $vendorPath = $currentDir . DIRECTORY_SEPARATOR . 'vendor';
            if (file_exists($composerPath) || is_dir($vendorPath)) {
                // We're in the project root, but the file doesn't exist here
                return $currentDir . DIRECTORY_SEPARATOR . $filename;
            }
            
            // Move up one directory
            $parentDir = dirname($currentDir);
            if ($parentDir === $currentDir) {
                // We've reached the filesystem root
                break;
            }
            $currentDir = $parentDir;
        }
        
        // Fallback to relative path if not found
        return $filename;
    }

    /**
     * Load environment variables from .env file
     */
    protected function loadEnv(): void {
        if (!file_exists($this->envFilePath)) {
            // .env file is optional
            return;
        }
        
        $lines = file($this->envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new GCException('Failed to read .env file', [
                'env_file_path' => $this->envFilePath
            ]);
        }
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                $this->env[$key] = $value;
                // Also populate $_ENV for backward compatibility during transition
                $_ENV[$key] = $value;
            }
        }
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
     * Get an environment variable by key
     */
    public function getEnv(string $key, $default = null) {
        return $this->env[$key] ?? $default;
    }

    /**
     * Set an environment variable
     */
    public function setEnv(string $key, $value): void {
        $this->env[$key] = $value;
        $_ENV[$key] = $value; // Maintain backward compatibility
    }

    /**
     * Get all environment variables
     */
    public function getAllEnv(): array {
        return $this->env;
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
