<?php

namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;

/**
 * Request Data Transfer Object
 * 
 * Handles parameter extraction from URL paths and provides clean access
 * to path parameters through named accessors.
 */
class Request
{
    private array $extractedParameters = [];
    private string $url;
    private string $method;

    /**
     * Create a new Request instance
     * 
     * @param string $url The request URL path
     * @param array $parameterNames Array of parameter names for each path component
     * @param string $httpMethod The HTTP method (GET, POST, etc.)
     * @throws GCException If parameter names count doesn't match path components count
     */
    public function __construct(string $url, array $parameterNames, string $httpMethod)
    {
        $this->url = $url;
        $this->method = strtoupper($httpMethod);
        
        $pathComponents = $this->parsePathComponents($url);
        
        if (count($parameterNames) !== count($pathComponents)) {
            throw new GCException("Parameter names count must match path components count", [
                'parameterNames' => $parameterNames,
                'pathComponents' => $pathComponents,
                'url' => $url,
                'method' => $httpMethod
            ]);
        }
        
        $this->extractParameters($pathComponents, $parameterNames);
    }

    /**
     * Get a parameter value by name
     * 
     * @param string $paramName The parameter name
     * @return string|null The parameter value or null if not found
     */
    public function get(string $paramName): ?string
    {
        return $this->extractedParameters[$paramName] ?? null;
    }

    /**
     * Check if a parameter exists
     * 
     * @param string $paramName The parameter name
     * @return bool True if the parameter exists
     */
    public function has(string $paramName): bool
    {
        return isset($this->extractedParameters[$paramName]);
    }

    /**
     * Get all extracted parameters
     * 
     * @return array All extracted parameters as key-value pairs
     */
    public function all(): array
    {
        return $this->extractedParameters;
    }

    /**
     * Get the HTTP method
     * 
     * @return string The HTTP method (uppercase)
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the original URL
     * 
     * @return string The original URL path
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Extract parameters from path components based on parameter names
     * 
     * @param array $pathComponents The path components from the URL
     * @param array $parameterNames The parameter names for each component
     */
    private function extractParameters(array $pathComponents, array $parameterNames): void
    {
        for ($i = 0; $i < count($parameterNames); $i++) {
            // Only extract parameters with non-empty names
            if (!empty($parameterNames[$i])) {
                $this->extractedParameters[$parameterNames[$i]] = $pathComponents[$i];
            }
        }
    }

    /**
     * Parse a path string into components
     * 
     * @param string $path The path to parse (e.g., "/Users/123")
     * @return array Array of path components (e.g., ["Users", "123"])
     */
    private function parsePathComponents(string $path): array
    {
        if (empty($path) || $path === '/') {
            return [];
        }

        // Remove leading and trailing slashes, then split
        $path = trim($path, '/');
        return explode('/', $path);
    }
}
