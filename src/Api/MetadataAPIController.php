<?php
namespace Gravitycar\Api;

use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Api\Request;
use Gravitycar\Services\ReactComponentMapper;
use Gravitycar\Services\DocumentationCache;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Core\Config;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\InternalServerErrorException;
use Gravitycar\Exceptions\ServiceUnavailableException;
use Gravitycar\Exceptions\GCException;
use Psr\Log\LoggerInterface;

/**
 * MetadataAPIController: Provides API endpoints for model and field metadata discovery
 */
class MetadataAPIController {
    private MetadataEngine $metadataEngine;
    private ?APIRouteRegistry $routeRegistry = null;
    private ReactComponentMapper $componentMapper;
    private DocumentationCache $cache;
    private Config $config;
    private LoggerInterface $logger;
    
    public function __construct() {
        $this->metadataEngine = MetadataEngine::getInstance();
        $this->cache = new DocumentationCache();
        $this->config = ServiceLocator::getConfig();
        $this->logger = ServiceLocator::getLogger();
        $this->componentMapper = new ReactComponentMapper();
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
     * Register routes for this controller
     */
    public function registerRoutes(): array {
        return [
            [
                'method' => 'GET',
                'path' => '/metadata/models',
                'apiClass' => '\\Gravitycar\\Api\\MetadataAPIController',
                'apiMethod' => 'getModels',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/metadata/models/?',
                'apiClass' => '\\Gravitycar\\Api\\MetadataAPIController',
                'apiMethod' => 'getModelMetadata',
                'parameterNames' => ['modelName']
            ],
            [
                'method' => 'GET',
                'path' => '/metadata/field-types',
                'apiClass' => '\\Gravitycar\\Api\\MetadataAPIController',
                'apiMethod' => 'getFieldTypes',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/metadata/relationships',
                'apiClass' => '\\Gravitycar\\Api\\MetadataAPIController',
                'apiMethod' => 'getRelationships',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/help',
                'apiClass' => '\\Gravitycar\\Api\\MetadataAPIController',
                'apiMethod' => 'getHelp',
                'parameterNames' => []
            ],
            [
                'method' => 'POST',
                'path' => '/metadata/cache/clear',
                'apiClass' => '\\Gravitycar\\Api\\MetadataAPIController',
                'apiMethod' => 'clearDocumentationCache',
                'parameterNames' => []
            ]
        ];
    }
    
    /**
     * Get all available models
     */
    public function getModels(): array {
        // Use configuration for caching behavior
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return $this->generateModelsListFresh();
        }
        
        // Check cache first (respecting cache TTL)
        $cached = $this->cache->getCachedModelsList();
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $cachedMetadata = $this->metadataEngine->getCachedMetadata();
            if (empty($cachedMetadata['models'])) {
                throw new NotFoundException('No models found in metadata cache');
            }
            
            $models = [];
            foreach ($cachedMetadata['models'] as $modelName => $modelData) {
                // Respect internal field exposure configuration
                if (!$this->shouldExposeModel($modelName, $modelData)) {
                    continue;
                }
                
                $routes = $this->getRouteRegistry()->getModelRoutes($modelName);
                
                $models[$modelName] = [
                    'name' => $modelName,
                    'endpoint' => $this->extractPrimaryEndpoint($routes),
                    'operations' => $this->getAvailableOperations($routes),
                    'description' => $modelData['description'] ?? "Model for {$modelName}",
                    'table' => $modelData['table'] ?? strtolower($modelName)
                ];
            }
            
            $result = [
                'success' => true,
                'status' => 200,
                'data' => $models,
                'timestamp' => date('c')
            ];
            
            // Include debug info if configured
            if ($this->config->get('documentation.enable_debug_info', false)) {
                $result['debug'] = [
                    'cache_hit' => false,
                    'models_count' => count($models),
                    'source' => 'metadata_engine'
                ];
            }
            
            // Cache if enabled
            if ($this->config->get('documentation.cache_enabled', true)) {
                $this->cache->cacheModelsList($result);
            }
            
            return $result;
            
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Use configured error handling
            if ($this->config->get('documentation.graceful_degradation', true)) {
                return $this->getGracefulErrorResponse('Failed to retrieve models metadata', $e);
            }
            throw new InternalServerErrorException(
                'Failed to retrieve models metadata',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    /**
     * Get metadata for a specific model
     */
    public function getModelMetadata(Request $request): array {
        $modelName = $request->get('modelName');
        if (!$modelName) {
            throw new BadRequestException('Model name is required', [
                'available_parameters' => array_keys($request->all())
            ]);
        }
        
        try {
            // Check cache first
            if ($this->config->get('documentation.cache_enabled', true)) {
                $cached = $this->cache->getCachedModelMetadata($modelName);
                if ($cached !== null) {
                    return $cached;
                }
            }
            
            $modelData = $this->metadataEngine->getModelMetadata($modelName);
            $routes = $this->getRouteRegistry()->getModelRoutes($modelName);
            
            // Enhance fields with React information
            $enhancedFields = $this->enhanceFieldsWithReactInfo($modelData['fields'] ?? []);
            
            $result = [
                'success' => true,
                'status' => 200,
                'data' => [
                    'name' => $modelName,
                    'table' => $modelData['table'] ?? strtolower($modelName),
                    'description' => $modelData['description'] ?? "Model for {$modelName}",
                    'fields' => $enhancedFields,
                    'relationships' => $modelData['relationships'] ?? [],
                    'api_endpoints' => $this->formatApiEndpoints($routes),
                    'react_form_schema' => $this->componentMapper->generateFormSchema($modelName)
                ],
                'timestamp' => date('c')
            ];
            
            // Cache if enabled
            if ($this->config->get('documentation.cache_enabled', true)) {
                $this->cache->cacheModelMetadata($modelName, $result);
            }
            
            return $result;
            
        } catch (NotFoundException $e) {
            throw $e;
        } catch (GCException $e) {
            // Convert GCException to NotFoundException for model not found scenarios
            if (strpos($e->getMessage(), 'not found') !== false) {
                throw new NotFoundException(
                    "Model '{$modelName}' not found",
                    ['model' => $modelName, 'available_models' => $e->getContext()['available_models'] ?? []],
                    $e
                );
            }
            // For other GCExceptions, wrap in InternalServerErrorException
            throw new InternalServerErrorException(
                "Failed to retrieve metadata for model '{$modelName}'",
                ['model' => $modelName, 'original_error' => $e->getMessage()],
                $e
            );
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                "Failed to retrieve metadata for model '{$modelName}'",
                ['model' => $modelName, 'original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    /**
     * Get all available field types with React component mappings
     */
    public function getFieldTypes(): array {
        try {
            // Check cache if enabled
            if ($this->config->get('documentation.cache_enabled', true)) {
                $cached = $this->cache->getCachedFieldTypes();
                if ($cached !== null) {
                    return $cached;
                }
            }
            
            $fieldTypeDefinitions = $this->metadataEngine->getFieldTypeDefinitions();
            if (empty($fieldTypeDefinitions)) {
                throw new ServiceUnavailableException(
                    'Field type definitions not available. Please regenerate metadata cache.'
                );
            }
            
            $fieldTypes = [];
            foreach ($fieldTypeDefinitions as $fieldType => $fieldData) {
                if (!$this->config->get('documentation.expose_field_capabilities', true)) {
                    // Remove validation rules if not configured to expose them
                    unset($fieldData['validation_rules']);
                }
                
                $fieldTypeInfo = array_merge($fieldData, [
                    'react_component' => $this->componentMapper->getReactComponentForFieldType($fieldType),
                    'props' => $this->componentMapper->getComponentPropsForFieldType($fieldType)
                ]);
                
                $fieldTypes[$fieldType] = $fieldTypeInfo;
            }
            
            $result = [
                'success' => true,
                'status' => 200,
                'data' => $fieldTypes,
                'timestamp' => date('c')
            ];
            
            // Cache if enabled
            if ($this->config->get('documentation.cache_enabled', true)) {
                $this->cache->cacheFieldTypes($result);
            }
            
            return $result;
            
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                'Failed to retrieve field types metadata',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    /**
     * Get all relationships
     */
    public function getRelationships(): array {
        try {
            $relationships = $this->metadataEngine->getAllRelationships();
            
            return [
                'success' => true,
                'status' => 200,
                'data' => $relationships,
                'timestamp' => date('c')
            ];
            
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                'Failed to retrieve relationships metadata',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    /**
     * Get help information about available endpoints
     */
    public function getHelp(): array {
        try {
            $routes = $this->getRouteRegistry()->getRoutes();
            $endpoints = [];
            
            foreach ($routes as $route) {
                $endpoints[] = [
                    'method' => $route['method'],
                    'path' => $route['path'],
                    'description' => $this->getRouteRegistry()->getEndpointDocumentation($route['path'], $route['method'])['description'] ?? ''
                ];
            }
            
            return [
                'success' => true,
                'status' => 200,
                'data' => [
                    'title' => 'Gravitycar Framework API',
                    'version' => $this->config->get('documentation.api_version', '1.0.0'),
                    'description' => $this->config->get('documentation.api_description', 'Auto-generated API documentation'),
                    'endpoints' => $endpoints,
                    'documentation_urls' => [
                        'openapi_spec' => '/openapi.json',
                        'swagger_ui' => '/docs',
                        'models' => '/metadata/models',
                        'field_types' => '/metadata/field-types'
                    ]
                ],
                'timestamp' => date('c')
            ];
            
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                'Failed to generate help information',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    /**
     * Clear documentation cache (for development use)
     */
    public function clearDocumentationCache(): array {
        try {
            $this->cache->clearCache();
            
            return [
                'success' => true,
                'status' => 200,
                'message' => 'Documentation cache cleared successfully',
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Failed to clear documentation cache: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Generate fresh models list without cache
     */
    private function generateModelsListFresh(): array {
        return $this->getModels();
    }
    
    /**
     * Extract primary endpoint from routes
     */
    private function extractPrimaryEndpoint(array $routes): string {
        foreach ($routes as $route) {
            if ($route['method'] === 'GET' && !str_contains($route['path'], '{')) {
                return $route['path'];
            }
        }
        return '/Unknown';
    }
    
    /**
     * Get available operations from routes
     */
    private function getAvailableOperations(array $routes): array {
        $operations = [];
        foreach ($routes as $route) {
            switch ($route['method']) {
                case 'GET':
                    if (str_contains($route['path'], '{')) {
                        $operations[] = 'read';
                    } else {
                        $operations[] = 'list';
                    }
                    break;
                case 'POST':
                    $operations[] = 'create';
                    break;
                case 'PUT':
                case 'PATCH':
                    $operations[] = 'update';
                    break;
                case 'DELETE':
                    $operations[] = 'delete';
                    break;
            }
        }
        return array_unique($operations);
    }
    
    /**
     * Enhance fields with React component information
     */
    private function enhanceFieldsWithReactInfo(array $fields): array {
        $enhancedFields = [];
        foreach ($fields as $fieldName => $fieldData) {
            $fieldData['react_component'] = $this->componentMapper->getReactComponentForField($fieldData);
            $fieldData['react_validation'] = $this->componentMapper->getReactValidationRules($fieldData);
            $fieldData['component_props'] = $this->componentMapper->getComponentPropsFromField($fieldData);
            $enhancedFields[$fieldName] = $fieldData;
        }
        return $enhancedFields;
    }
    
    /**
     * Format API endpoints for documentation
     */
    private function formatApiEndpoints(array $routes): array {
        $endpoints = [];
        foreach ($routes as $route) {
            $key = strtolower($route['method']);
            if (str_contains($route['path'], '{')) {
                $key .= '_single';
            } else {
                $key .= '_list';
            }
            $endpoints[$key] = $route['method'] . ' ' . $route['path'];
        }
        return $endpoints;
    }
    
    /**
     * Check if model should be exposed based on configuration
     */
    private function shouldExposeModel(string $modelName, array $modelData): bool {
        // Hide internal framework models if configured
        if (!$this->config->get('documentation.expose_internal_fields', false)) {
            $internalModels = ['MetadataCache', 'SystemLog', 'FrameworkConfig'];
            if (in_array($modelName, $internalModels)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate graceful error response when degradation is enabled
     */
    private function getGracefulErrorResponse(string $message, \Exception $e): array {
        return [
            'success' => false,
            'status' => 500,
            'message' => $message,
            'error' => $this->config->get('documentation.detailed_error_responses', true) ? $e->getMessage() : 'Internal server error',
            'timestamp' => date('c')
        ];
    }
}
