<?php

namespace Gravitycar\Navigation;

use Gravitycar\Exceptions\NavigationBuilderException;

/**
 * NavigationConfig - Manages custom page navigation configuration
 * Follows framework Config class patterns for consistency
 */
class NavigationConfig
{
    protected array $config = [];
    protected string $configFilePath;

    public function __construct(?string $configFilePath = null)
    {
        $this->configFilePath = $configFilePath ?? 'src/Navigation/navigation_config.php';
        $this->loadConfig();
    }

    /**
     * Load navigation configuration from file
     */
    protected function loadConfig(): void
    {
        if (!file_exists($this->configFilePath)) {
            throw new NavigationBuilderException('Navigation config file not found', [
                'config_file_path' => $this->configFilePath
            ]);
        }
        
        $config = include $this->configFilePath;
        if (!is_array($config)) {
            throw new NavigationBuilderException('Navigation config file must return an array', [
                'config_file_path' => $this->configFilePath,
                'returned_type' => gettype($config)
            ]);
        }
        
        $this->config = $config;
    }

    /**
     * Get navigation configuration value using dot notation
     */
    public function get(string $key, $default = null)
    {
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
     * Get all custom pages for a specific role
     */
    public function getCustomPagesForRole(string $role): array
    {
        $customPages = $this->get('custom_pages', []);
        $filteredPages = [];

        foreach ($customPages as $page) {
            $allowedRoles = $page['roles'] ?? [];
            if (in_array($role, $allowedRoles) || in_array('*', $allowedRoles)) {
                $filteredPages[] = $page;
            }
        }

        // Return pages in source-code order (no sorting)
        return $filteredPages;
    }

    /**
     * Get all navigation sections configuration
     */
    public function getNavigationSections(): array
    {
        return $this->get('navigation_sections', []);
    }
}