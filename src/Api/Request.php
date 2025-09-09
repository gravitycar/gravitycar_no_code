<?php

namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;

/**
 * Enhanced Request Data Transfer Object
 * 
 * Consolidates all request data (path parameters, query parameters, POST data, JSON body)
 * and provides access to pagination, filtering, search, and response formatting helpers.
 */
class Request
{
    private array $extractedParameters = [];
    private string $url;
    private string $method;
    private array $requestData = [];
    
    // Helper class properties (set by Router) - TODO: Add type hints after creating classes
    protected $parameterParser = null;
    protected $filterCriteria = null;
    protected $searchEngine = null;
    protected $paginationManager = null;
    protected $sortingManager = null;
    protected $responseFormatter = null;
    protected ?array $parsedParams = null;
    protected ?array $validatedParams = null;

    /**
     * Create a new Request instance
     * 
     * @param string $url The request URL path
     * @param array $parameterNames Array of parameter names corresponding to path components
     * @param string $httpMethod The HTTP method (GET, POST, etc.)
     * @param array $requestData All request data (query params, POST data, JSON body)
     * @throws GCException If parameter validation fails
     */
    public function __construct(string $url, array $parameterNames, string $httpMethod, array $requestData = [])
    {
        $this->url = $url;
        $this->method = strtoupper($httpMethod);
        $this->requestData = $requestData;
        
        $pathComponents = $this->parsePathComponents($url);
        
        // For proper parameter extraction, parameterNames should match pathComponents count
        // This ensures static routes like /Users can extract modelName = "Users"
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
        // Path parameters take precedence over query parameters
        return $this->extractedParameters[$paramName] ?? $this->requestData[$paramName] ?? null;
    }

    /**
     * Check if a parameter exists
     * 
     * @param string $paramName The parameter name
     * @return bool True if the parameter exists
     */
    public function has(string $paramName): bool
    {
        return isset($this->extractedParameters[$paramName]) || isset($this->requestData[$paramName]);
    }

    /**
     * Get all request data including path parameters, query parameters, POST data, JSON body, and cookies
     * 
     * @return array All request data consolidated with path parameters taking precedence
     */
    public function all(): array
    {
        // Start with request data (POST, GET, JSON body)
        $allData = $this->requestData;
        
        // Add cookies if available
        if (!empty($_COOKIE)) {
            $allData = array_merge($_COOKIE, $allData);
        }
        
        // Path parameters take precedence over other data
        $allData = array_merge($allData, $this->extractedParameters);
        
        return $allData;
    }

    /**
     * Get only the extracted path parameters
     * 
     * @return array Only the path parameters as key-value pairs
     */
    public function getPathParameters(): array
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

    // ======================= NEW REQUEST DATA METHODS =======================
    
    /**
     * Get all request data (query parameters, POST data, JSON body)
     * 
     * @return array All request data consolidated
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }
    
    /**
     * Set request data (called by Router)
     * 
     * @param array $requestData All request data to store
     */
    public function setRequestData(array $requestData): void
    {
        $this->requestData = $requestData;
    }
    
    /**
     * Get a specific request parameter (query param, POST data, JSON body)
     * 
     * @param string $key The parameter key
     * @param mixed $default Default value if parameter not found
     * @return mixed The parameter value or default
     */
    public function getRequestParam(string $key, $default = null)
    {
        return $this->requestData[$key] ?? $default;
    }
    
    /**
     * Check if a request parameter exists
     * 
     * @param string $key The parameter key
     * @return bool True if the parameter exists
     */
    public function hasRequestParam(string $key): bool
    {
        return isset($this->requestData[$key]);
    }
    
    /**
     * Alias for getRequestData() for backward compatibility
     * 
     * @return array All request parameters
     */
    public function getAllRequestParams(): array
    {
        return $this->getRequestData();
    }
    
    // ======================= HELPER CLASS SETTERS (Router calls these) =======================
    
    /**
     * Set parameter parser helper (called by Router)
     */
    public function setParameterParser($parameterParser): void
    {
        $this->parameterParser = $parameterParser;
    }
    
    /**
     * Set filter criteria helper (called by Router)
     */
    public function setFilterCriteria($filterCriteria): void
    {
        $this->filterCriteria = $filterCriteria;
    }
    
    /**
     * Set search engine helper (called by Router)
     */
    public function setSearchEngine($searchEngine): void
    {
        $this->searchEngine = $searchEngine;
    }
    
    /**
     * Set pagination manager helper (called by Router)
     */
    public function setPaginationManager($paginationManager): void
    {
        $this->paginationManager = $paginationManager;
    }
    
    /**
     * Set sorting manager helper (called by Router)
     */
    public function setSortingManager($sortingManager): void
    {
        $this->sortingManager = $sortingManager;
    }
    
    /**
     * Set response formatter helper (called by Router)
     */
    public function setResponseFormatter($responseFormatter): void
    {
        $this->responseFormatter = $responseFormatter;
    }
    
    /**
     * Set parsed parameters (called by Router)
     * 
     * @param array $parsedParams All parsed parameter data
     */
    public function setParsedParams(array $parsedParams): void
    {
        $this->parsedParams = $parsedParams;
    }
    
    /**
     * Set validated parameters (called by Router)
     * 
     * @param array $validatedParams All validated parameter data
     */
    public function setValidatedParams(array $validatedParams): void
    {
        $this->validatedParams = $validatedParams;
    }
    
    // ======================= HELPER CLASS GETTERS =======================
    
    /**
     * Get parameter parser helper
     * 
     * @return RequestParameterParser|null
     */
    public function getParameterParser()
    {
        return $this->parameterParser;
    }
    
    /**
     * Get filter criteria helper
     * 
     * @return FilterCriteria|null
     */
    public function getFilterCriteria()
    {
        return $this->filterCriteria;
    }
    
    /**
     * Get search engine helper
     * 
     * @return SearchEngine|null
     */
    public function getSearchEngine()
    {
        return $this->searchEngine;
    }
    
    /**
     * Get pagination manager helper
     * 
     * @return PaginationManager|null
     */
    public function getPaginationManager()
    {
        return $this->paginationManager;
    }
    
    /**
     * Get sorting manager helper
     * 
     * @return SortingManager|null
     */
    public function getSortingManager()
    {
        return $this->sortingManager;
    }
    
    /**
     * Get response formatter helper
     * 
     * @return ResponseFormatter|null
     */
    public function getResponseFormatter()
    {
        return $this->responseFormatter;
    }
    
    /**
     * Get parsed parameters
     * 
     * @return array|null All parsed parameters
     */
    public function getParsedParams(): ?array
    {
        return $this->parsedParams;
    }
    
    /**
     * Get validated parameters
     * 
     * @return array|null All validated parameters
     */
    public function getValidatedParams(): ?array
    {
        return $this->validatedParams;
    }
    
    // ======================= CONVENIENCE METHODS FOR QUICK ACCESS =======================
    
    /**
     * Get parsed filter criteria
     * 
     * @return array Parsed filter parameters
     */
    public function getFilters(): array
    {
        return $this->parsedParams['filters'] ?? [];
    }
    
    /**
     * Get parsed search parameters
     * 
     * @return array Parsed search parameters
     */
    public function getSearchParams(): array
    {
        return $this->parsedParams['search'] ?? [];
    }
    
    /**
     * Get parsed pagination parameters
     * 
     * @return array Parsed pagination parameters
     */
    public function getPaginationParams(): array
    {
        return $this->parsedParams['pagination'] ?? [];
    }
    
    /**
     * Get parsed sorting parameters
     * 
     * @return array Parsed sorting parameters
     */
    public function getSortingParams(): array
    {
        return $this->parsedParams['sorting'] ?? [];
    }
    
    /**
     * Get detected response format
     * 
     * @return string Detected response format (e.g., 'ag-grid', 'mui', 'tanstack')
     */
    public function getResponseFormat(): string
    {
        return $this->parsedParams['responseFormat'] ?? 'standard';
    }
    
    /**
     * Format response data using the attached ResponseFormatter
     * 
     * @param array $data The data to format
     * @param array $meta The metadata to include
     * @param string|null $format Override format (optional)
     * @return array Formatted response
     */
    public function formatResponse(array $data, array $meta, ?string $format = null): array
    {
        if (!$this->responseFormatter) {
            // Fallback to standard format if no formatter is available
            return [
                'success' => true,
                'data' => $data,
                'meta' => $meta
            ];
        }
        
        $responseFormat = $format ?? $this->getResponseFormat();
        return $this->responseFormatter->format($data, $meta, $responseFormat);
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
