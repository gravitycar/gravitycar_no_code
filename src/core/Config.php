<?php

namespace Gravitycar\Core;

/**
 * Configuration manager for the Gravitycar framework
 *
 * Handles loading and managing configuration settings from files.
 */
class Config
{
    private static ?Config $instance = null;
    private array $config = [];
    private string $configPath;

    private function __construct(string $configPath = null)
    {
        $this->configPath = $configPath ?? __DIR__ . '/../../config/config.php';
        $this->loadConfig();
    }

    public static function getInstance(string $configPath = null): Config
    {
        if (self::$instance === null) {
            self::$instance = new self($configPath);
        }
        return self::$instance;
    }

    private function loadConfig(): void
    {
        if (file_exists($this->configPath)) {
            $this->config = include $this->configPath;
        } else {
            $this->config = $this->getDefaultConfig();
        }
    }

    private function getDefaultConfig(): array
    {
        return [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'gravitycar',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4'
            ],
            'app' => [
                'name' => 'Gravitycar Framework',
                'version' => '1.0.0',
                'debug' => true,
                'timezone' => 'UTC'
            ],
            'auth' => [
                'session_timeout' => 3600,
                'password_min_length' => 8,
                'require_email_verification' => false
            ],
            'api' => [
                'rate_limit' => 1000,
                'default_page_size' => 50,
                'max_page_size' => 200
            ]
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public function save(): bool
    {
        $configDir = dirname($this->configPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $content = "<?php\n\nreturn " . var_export($this->config, true) . ";\n";
        return file_put_contents($this->configPath, $content) !== false;
    }

    public function getAll(): array
    {
        return $this->config;
    }
}
