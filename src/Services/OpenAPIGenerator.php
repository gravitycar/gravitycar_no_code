<?php
namespace Gravitycar\Services;

use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Services\ReactComponentMapper;
use Gravitycar\Services\DocumentationCache;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Core\Config;
use Gravitycar\Factories\FieldFactory;
use Psr\Log\LoggerInterface;

/**
 * OpenAPIGenerator: Generates OpenAPI 3.0.3 specifications from framework metadata
 * Uses pure dependency injection - all dependencies explicitly provided via constructor
 */
class OpenAPIGenerator {
    private LoggerInterface $logger;
    private MetadataEngineInterface $metadataEngine;
    private FieldFactory $fieldFactory;
    private DatabaseConnectorInterface $databaseConnector;
    private Config $config;
    private ReactComponentMapper $componentMapper;
    private DocumentationCache $cache;
    private ?APIRouteRegistry $routeRegistry = null;
    
    /**
     * Pure dependency injection constructor - all dependencies explicitly provided
     * 
     * @param LoggerInterface $logger
     * @param MetadataEngineInterface $metadataEngine
     * @param FieldFactory $fieldFactory
     * @param DatabaseConnectorInterface $databaseConnector
     * @param Config $config
     * @param ReactComponentMapper $componentMapper
     * @param DocumentationCache $cache
     */
    public function __construct(
        LoggerInterface $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        Config $config,
        ReactComponentMapper $componentMapper,
        DocumentationCache $cache
    ) {
        // All dependencies explicitly injected - no ServiceLocator fallbacks
        $this->logger = $logger;
        $this->metadataEngine = $metadataEngine;
        $this->fieldFactory = $fieldFactory;
        $this->databaseConnector = $databaseConnector;
        $this->config = $config;
        $this->componentMapper = $componentMapper;
        $this->cache = $cache;
    }

    
    /**
     * Get the route registry (lazy initialization to avoid circular dependency)
     */
    private function getRouteRegistry(): APIRouteRegistry {
        if ($this->routeRegistry === null) {
            $this->routeRegistry = APIRouteRegistry::getInstance();
        }
        return $this->routeRegistry;
    }
    
    /**
     * Generate complete OpenAPI specification
     */
    public function generateSpecification(): array {
        // Check cache first
        if ($this->config->get('documentation.cache_enabled', true)) {
            $cached = $this->cache->getCachedOpenAPISpec();
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $spec = [
            'openapi' => $this->config->get('documentation.openapi_version', '3.0.3'),
            'info' => $this->generateInfo(),
            'servers' => $this->generateServers(),
            'paths' => $this->generatePaths(),
            'components' => $this->generateComponents(),
            'tags' => $this->generateTags()
        ];
        
        // Validate generated specification if configured
        if ($this->config->get('documentation.validate_generated_schemas', true)) {
            $this->validateOpenAPISpec($spec);
        }
        
        // Cache the generated specification if enabled
        if ($this->config->get('documentation.cache_enabled', true)) {
            $this->cache->cacheOpenAPISpec($spec);
        }
        
        return $spec;
    }
    
    /**
     * Generate OpenAPI info section
     */
    private function generateInfo(): array {
        return [
            'title' => $this->config->get('documentation.api_title', 'Gravitycar Framework API'),
            'version' => $this->config->get('documentation.api_version', '1.0.0'),
            'description' => $this->config->get('documentation.api_description', 
                'Auto-generated API documentation for Gravitycar Framework'),
            'contact' => [
                'name' => 'Gravitycar Framework',
                'url' => 'https://github.com/gravitycar/gravitycar'
            ]
        ];
    }
    
    /**
     * Generate OpenAPI servers section
     */
    private function generateServers(): array {
        $backendUrl = $this->config->get('app.backend_url', 'http://localhost:8081');
        
        return [
            [
                'url' => $backendUrl,
                'description' => 'Application server'
            ]
        ];
    }
    
    /**
     * Generate OpenAPI paths section
     */
    private function generatePaths(): array {
        $paths = [];
        $routes = $this->getRouteRegistry()->getRoutes();
        
        foreach ($routes as $route) {
            $path = $route['path'];
            $method = strtolower($route['method']);
            
            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }
            
            $paths[$path][$method] = $this->generateOperationFromRoute($route);
        }
        
        return $paths;
    }
    
    /**
     * Generate operation definition from route
     */
    private function generateOperationFromRoute(array $route): array {
        $modelName = $this->extractModelNameFromRoute($route);
        
        $operation = [
            'summary' => $this->generateOperationSummary($route, $modelName),
            'operationId' => $this->generateOperationId($route),
            'tags' => $modelName ? [$modelName] : ['General'],
            'responses' => $this->generateResponsesForRoute($route, $modelName)
        ];
        
        // Add parameters for routes with path parameters
        if (str_contains($route['path'], '{')) {
            $operation['parameters'] = $this->generatePathParameters($route);
        }
        
        // Add request body for POST/PUT/PATCH requests
        if (in_array($route['method'], ['POST', 'PUT', 'PATCH'])) {
            $operation['requestBody'] = $this->generateRequestBody($modelName);
        }
        
        return $operation;
    }
    
    /**
     * Extract model name from route
     */
    private function extractModelNameFromRoute(array $route): string {
        // Try to extract from apiClass first
        if (isset($route['apiClass'])) {
            if (preg_match('/Models\\\\([^\\\\]+)\\\\/', $route['apiClass'], $matches)) {
                return $matches[1];
            }
        }
        
        // Fallback: extract from path
        $path = trim($route['path'], '/');
        $pathParts = explode('/', $path);
        $firstPart = $pathParts[0] ?? '';
        
        // Skip if it's a special endpoint
        if (in_array($firstPart, ['metadata', 'help', 'auth', 'openapi.json'])) {
            return '';
        }
        
        return $firstPart;
    }
    
    /**
     * Generate operation summary
     */
    private function generateOperationSummary(array $route, string $modelName): string {
        $method = $route['method'];
        
        if ($modelName) {
            switch ($method) {
                case 'GET':
                    if (str_contains($route['path'], '{')) {
                        return "Get a specific {$modelName} record";
                    }
                    return "List {$modelName} records";
                case 'POST':
                    return "Create a new {$modelName} record";
                case 'PUT':
                    return "Update a {$modelName} record";
                case 'PATCH':
                    return "Partially update a {$modelName} record";
                case 'DELETE':
                    return "Delete a {$modelName} record";
            }
        }
        
        return "{$method} {$route['path']}";
    }
    
    /**
     * Generate operation ID
     */
    private function generateOperationId(array $route): string {
        $method = strtolower($route['method']);
        $path = str_replace(['/', '{', '}'], ['_', '', ''], trim($route['path'], '/'));
        return $method . '_' . $path;
    }
    
    /**
     * Generate responses for route
     */
    private function generateResponsesForRoute(array $route, string $modelName): array {
        $responses = [
            '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => $this->generateResponseSchema($route, $modelName)
                    ]
                ]
            ],
            '400' => [
                'description' => 'Bad request',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ValidationError']
                    ]
                ]
            ],
            '500' => [
                'description' => 'Internal server error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                    ]
                ]
            ]
        ];
        
        if (str_contains($route['path'], '{')) {
            $responses['404'] = [
                'description' => 'Resource not found',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                    ]
                ]
            ];
        }
        
        return $responses;
    }
    
    /**
     * Generate response schema
     */
    private function generateResponseSchema(array $route, string $modelName): array {
        if ($modelName && $this->metadataEngine->modelExists($modelName)) {
            if ($route['method'] === 'GET' && !str_contains($route['path'], '{')) {
                // List endpoint
                return [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean'],
                        'data' => [
                            'type' => 'array',
                            'items' => ['$ref' => "#/components/schemas/{$modelName}"]
                        ],
                        'pagination' => ['$ref' => '#/components/schemas/Pagination']
                    ]
                ];
            } else {
                // Single record endpoint
                return [
                    'type' => 'object',
                    'properties' => [
                        'success' => ['type' => 'boolean'],
                        'data' => ['$ref' => "#/components/schemas/{$modelName}"]
                    ]
                ];
            }
        }
        
        return ['$ref' => '#/components/schemas/ApiResponse'];
    }
    
    /**
     * Generate path parameters
     */
    private function generatePathParameters(array $route): array {
        $parameters = [];
        $parameterNames = $route['parameterNames'] ?? [];
        
        // Extract parameter names from path
        preg_match_all('/\{([^}]+)\}/', $route['path'], $matches);
        $pathParams = $matches[1] ?? [];
        
        foreach ($pathParams as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => $this->getParameterSchema($param),
                'description' => $this->getParameterDescription($param)
            ];
        }
        
        return $parameters;
    }
    
    /**
     * Get parameter schema based on parameter name
     */
    private function getParameterSchema(string $paramName): array {
        if (in_array($paramName, ['id', 'userId', 'movieId', 'quoteId'])) {
            return ['type' => 'integer', 'minimum' => 1];
        }
        
        return ['type' => 'string'];
    }
    
    /**
     * Get parameter description
     */
    private function getParameterDescription(string $paramName): string {
        $descriptions = [
            'id' => 'Unique identifier',
            'userId' => 'User ID',
            'movieId' => 'Movie ID',
            'quoteId' => 'Quote ID',
            'modelName' => 'Model name'
        ];
        
        return $descriptions[$paramName] ?? "The {$paramName} parameter";
    }
    
    /**
     * Generate request body for POST/PUT/PATCH operations
     */
    private function generateRequestBody(string $modelName): array {
        if ($modelName && $this->metadataEngine->modelExists($modelName)) {
            return [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => "#/components/schemas/{$modelName}Input"]
                    ]
                ]
            ];
        }
        
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['type' => 'object']
                ]
            ]
        ];
    }
    
    /**
     * Generate OpenAPI components
     */
    private function generateComponents(): array {
        $schemas = [];
        $cachedMetadata = $this->metadataEngine->getCachedMetadata();
        
        if (isset($cachedMetadata['models'])) {
            foreach ($cachedMetadata['models'] as $modelName => $modelData) {
                $schemas[$modelName] = $this->generateModelSchema($modelName, $modelData);
                $schemas["{$modelName}Input"] = $this->generateModelInputSchema($modelName, $modelData);
            }
        }
        
        // Add common response schemas
        $schemas['ApiResponse'] = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'message' => ['type' => 'string'],
                'timestamp' => ['type' => 'string', 'format' => 'date-time']
            ]
        ];
        
        $schemas['ValidationError'] = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean', 'example' => false],
                'errors' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => ['type' => 'string']
                    ]
                ]
            ]
        ];
        
        $schemas['Pagination'] = [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer'],
                'pageSize' => ['type' => 'integer'],
                'total' => ['type' => 'integer'],
                'totalPages' => ['type' => 'integer']
            ]
        ];
        
        return ['schemas' => $schemas];
    }
    
    /**
     * Generate model schema for responses
     */
    private function generateModelSchema(string $modelName, array $modelData): array {
        $properties = [];
        $required = [];
        
        $tableName = $modelData['table'] ?? null;
        $fields = $modelData['fields'] ?? [];
        foreach ($fields as $fieldName => $fieldData) {
            $properties[$fieldName] = $this->generateFieldSchema($fieldData, $tableName);
            if ($fieldData['required'] ?? false) {
                $required[] = $fieldName;
            }
        }
        
        $schema = [
            'type' => 'object',
            'properties' => $properties
        ];
        
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        
        return $schema;
    }
    
    /**
     * Generate model input schema for requests (excludes read-only fields)
     */
    private function generateModelInputSchema(string $modelName, array $modelData): array {
        $properties = [];
        $required = [];
        
        $tableName = $modelData['table'] ?? null;
        $fields = $modelData['fields'] ?? [];
        foreach ($fields as $fieldName => $fieldData) {
            // Skip auto-generated fields like ID
            if ($fieldData['type'] === 'ID' || ($fieldData['readonly'] ?? false)) {
                continue;
            }
            
            $properties[$fieldName] = $this->generateFieldSchema($fieldData, $tableName);
            if ($fieldData['required'] ?? false) {
                $required[] = $fieldName;
            }
        }
        
        $schema = [
            'type' => 'object',
            'properties' => $properties
        ];
        
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        
        return $schema;
    }
    
    /**
     * Generate field schema using ServiceLocator for accurate typing
     */
    private function generateFieldSchema(array $fieldData, ?string $tableName = null): array {
        try {
            $fieldType = $fieldData['type'] ?? 'Text';
            $fieldClassName = "Gravitycar\\Fields\\{$fieldType}Field";
            
            if (class_exists($fieldClassName)) {
                $fieldInstance = $this->fieldFactory->createField($fieldData, $tableName);
                
                // Try to use field's own schema generation if available
                if (method_exists($fieldInstance, 'generateOpenAPISchema')) {
                    return $fieldInstance->generateOpenAPISchema();
                }
            }
            
            // Fallback to basic schema generation
            return $this->generateBasicFieldSchema($fieldData);
            
        } catch (\Exception $e) {
            $this->logger->warning("Failed to generate schema for field type {$fieldData['type']}: " . $e->getMessage());
            return $this->generateBasicFieldSchema($fieldData);
        }
    }
    
    /**
     * Generate basic field schema as fallback
     */
    private function generateBasicFieldSchema(array $fieldData): array {
        $fieldType = $fieldData['type'] ?? 'Text';
        
        $schema = ['type' => 'string']; // Default to string
        
        switch ($fieldType) {
            case 'Integer':
            case 'ID':
                $schema = ['type' => 'integer'];
                break;
            case 'Float':
                $schema = ['type' => 'number'];
                break;
            case 'Boolean':
                $schema = ['type' => 'boolean'];
                break;
            case 'DateTime':
            case 'Date':
                $schema = ['type' => 'string', 'format' => 'date-time'];
                break;
            case 'Email':
                $schema = ['type' => 'string', 'format' => 'email'];
                break;
            case 'Enum':
            case 'RadioButtonSet':
                if (isset($fieldData['options'])) {
                    $schema = [
                        'type' => 'string',
                        'enum' => array_keys($fieldData['options'])
                    ];
                }
                break;
            case 'MultiEnum':
                if (isset($fieldData['options'])) {
                    $schema = [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => array_keys($fieldData['options'])
                        ]
                    ];
                }
                break;
        }
        
        // Add description if available
        if (isset($fieldData['description'])) {
            $schema['description'] = $fieldData['description'];
        }
        
        // Add max length if available
        if (isset($fieldData['maxLength'])) {
            $schema['maxLength'] = $fieldData['maxLength'];
        }
        
        return $schema;
    }
    
    /**
     * Generate tags for grouping operations
     */
    private function generateTags(): array {
        $tags = [];
        $models = $this->metadataEngine->getAvailableModels();
        
        foreach ($models as $modelName) {
            $tags[] = [
                'name' => $modelName,
                'description' => "Operations for {$modelName} model"
            ];
        }
        
        $tags[] = [
            'name' => 'General',
            'description' => 'General API operations'
        ];
        
        return $tags;
    }
    
    /**
     * Validate OpenAPI specification (basic validation)
     */
    private function validateOpenAPISpec(array $spec): void {
        $requiredFields = ['openapi', 'info', 'paths'];
        
        foreach ($requiredFields as $field) {
            if (!isset($spec[$field])) {
                throw new \Exception("OpenAPI specification missing required field: {$field}");
            }
        }
        
        if (!isset($spec['info']['title']) || !isset($spec['info']['version'])) {
            throw new \Exception("OpenAPI info section missing required fields");
        }
    }
}
