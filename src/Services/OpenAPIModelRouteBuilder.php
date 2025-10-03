<?php
namespace Gravitycar\Services;

use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\ModelFactory;
use Psr\Log\LoggerInterface;

/**
 * OpenAPIModelRouteBuilder: Generates OpenAPI documentation for model routes
 * 
 * Creates explicit routes for each model with rich documentation including:
 * - Natural language descriptions
 * - Dynamic parameter documentation
 * - Intent metadata (x-gravitycar-*)
 * - Real database examples
 */
class OpenAPIModelRouteBuilder {
    private MetadataEngineInterface $metadataEngine;
    private FieldFactory $fieldFactory;
    private ModelFactory $modelFactory;
    private LoggerInterface $logger;
    
    public function __construct(
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        ModelFactory $modelFactory,
        LoggerInterface $logger
    ) {
        $this->metadataEngine = $metadataEngine;
        $this->fieldFactory = $fieldFactory;
        $this->modelFactory = $modelFactory;
        $this->logger = $logger;
    }
    
    /**
     * Generate all routes for a model (CRUD + soft delete + relationships)
     * 
     * @param string $modelName The model name (e.g., 'Movies', 'Users')
     * @return array Array of route definitions
     */
    public function generateModelRoutes(string $modelName): array {
        $routes = [];
        
        // Basic CRUD operations
        $routes["/{$modelName}"] = [
            'get' => $this->generateModelOperation($modelName, 'GET', 'list'),
            'post' => $this->generateModelOperation($modelName, 'POST', 'create')
        ];
        
        $routes["/{$modelName}/{id}"] = [
            'get' => $this->generateModelOperation($modelName, 'GET', 'retrieve'),
            'put' => $this->generateModelOperation($modelName, 'PUT', 'update'),
            'delete' => $this->generateModelOperation($modelName, 'DELETE', 'delete')
        ];
        
        // Soft delete operations
        $routes["/{$modelName}/deleted"] = [
            'get' => $this->generateModelOperation($modelName, 'GET', 'listDeleted')
        ];
        
        $routes["/{$modelName}/{id}/restore"] = [
            'put' => $this->generateModelOperation($modelName, 'PUT', 'restore')
        ];
        
        // Relationship operations
        $routes["/{$modelName}/{id}/link/{relationshipName}"] = [
            'get' => $this->generateModelOperation($modelName, 'GET', 'listRelated'),
            'post' => $this->generateModelOperation($modelName, 'POST', 'createAndLink')
        ];
        
        $routes["/{$modelName}/{id}/link/{relationshipName}/{idToLink}"] = [
            'put' => $this->generateModelOperation($modelName, 'PUT', 'link'),
            'delete' => $this->generateModelOperation($modelName, 'DELETE', 'unlink')
        ];
        
        return $routes;
    }
    
    /**
     * Generate OpenAPI operation definition for a model operation
     * 
     * @param string $modelName The model name
     * @param string $httpMethod The HTTP method
     * @param string $operation The operation type (list, retrieve, create, etc.)
     * @return array OpenAPI operation definition
     */
    public function generateModelOperation(string $modelName, string $httpMethod, string $operation): array {
        $opDef = [
            'summary' => $this->generateNaturalLanguageDescription($modelName, $operation),
            'operationId' => strtolower($httpMethod) . '_{$modelName}_' . $operation,
            'tags' => ['api', $modelName],
            'parameters' => $this->generateParameters($modelName, $operation),
            'responses' => $this->generateEnhancedResponses($modelName, $operation)
        ];
        
        // Add intent metadata
        $intentMetadata = $this->generateIntentMetadata($modelName, $operation);
        foreach ($intentMetadata as $key => $value) {
            $opDef[$key] = $value;
        }
        
        // Add request body for write operations
        if (in_array($operation, ['create', 'update', 'createAndLink'])) {
            $opDef['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object'],
                        'example' => $this->generateExampleRequestBody($modelName, $operation)
                    ]
                ]
            ];
        }
        
        return $opDef;
    }
    
    /**
     * Generate natural language description for operation
     */
    private function generateNaturalLanguageDescription(string $modelName, string $operation): string {
        $descriptions = [
            'list' => "Retrieve {$modelName} records from the gravitycar api with optional search parameters in the query string.",
            'retrieve' => "Get a specific {$modelName} record by its unique identifier.",
            'create' => "Create a new {$modelName} record with the provided data.",
            'update' => "Update an existing {$modelName} record by its unique identifier.",
            'delete' => "Delete a {$modelName} record by its unique identifier (soft delete).",
            'listDeleted' => "Retrieve soft-deleted {$modelName} records that can be restored.",
            'restore' => "Restore a previously deleted {$modelName} record by its unique identifier.",
            'listRelated' => "Get related records linked to a specific {$modelName} via the specified relationship.",
            'createAndLink' => "Create a new related record and automatically link it to the specified {$modelName}.",
            'link' => "Create a relationship link between a {$modelName} record and an existing related record.",
            'unlink' => "Remove a relationship link between a {$modelName} record and a related record."
        ];
        
        return $descriptions[$operation] ?? "Perform {$operation} operation on {$modelName}";
    }
    
    /**
     * Generate parameters for operation
     */
    private function generateParameters(string $modelName, string $operation): array {
        $parameters = [];
        
        switch ($operation) {
            case 'list':
            case 'listDeleted':
                // Get model-specific searchable fields
                $searchableFields = $this->getModelSearchableFields($modelName);
                $searchDescription = $this->generateSearchDescription($modelName, $searchableFields);
                
                $parameters[] = [
                    'name' => 'search',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string'],
                    'description' => $searchDescription,
                    'example' => $this->getExampleSearchTerm($modelName)
                ];
                
                $parameters[] = [
                    'name' => 'page',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    'description' => 'Page number (1-based)',
                    'example' => 1
                ];
                
                $parameters[] = [
                    'name' => 'pageSize',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 20],
                    'description' => 'Records per page',
                    'example' => 20
                ];
                
                $parameters[] = [
                    'name' => 'sortBy',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string'],
                    'description' => 'Field name to sort by',
                    'example' => $this->generateSortExample($modelName)
                ];
                
                $parameters[] = [
                    'name' => 'sortOrder',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
                    'description' => 'Sort direction',
                    'example' => 'asc'
                ];
                
                // Add dynamic field filters
                $filterableFields = $this->getModelFilterableFields($modelName);
                foreach ($filterableFields as $fieldName => $fieldInfo) {
                    $parameters[] = [
                        'name' => $fieldName,
                        'in' => 'query',
                        'required' => false,
                        'schema' => $this->generateFieldSchema($fieldInfo),
                        'description' => "Filter by {$fieldName}",
                        'example' => $this->generateFieldExample($fieldName, $fieldInfo)
                    ];
                }
                break;
                
            case 'retrieve':
            case 'update':
            case 'delete':
            case 'restore':
                $parameters[] = [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string', 'format' => 'uuid'],
                    'description' => 'Unique identifier',
                    'example' => $this->getExampleId($modelName)
                ];
                break;
                
            case 'listRelated':
            case 'createAndLink':
                $parameters[] = [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string', 'format' => 'uuid'],
                    'description' => 'Primary record identifier'
                ];
                
                $parameters[] = [
                    'name' => 'relationshipName',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => 'Name of the relationship',
                    'example' => $this->generateRelationshipExample($modelName)
                ];
                
                // listRelated also supports pagination
                if ($operation === 'listRelated') {
                    $parameters[] = [
                        'name' => 'page',
                        'in' => 'query',
                        'required' => false,
                        'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                        'description' => 'Page number'
                    ];
                    
                    $parameters[] = [
                        'name' => 'pageSize',
                        'in' => 'query',
                        'required' => false,
                        'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 20],
                        'description' => 'Records per page'
                    ];
                }
                break;
                
            case 'link':
            case 'unlink':
                $parameters[] = [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string', 'format' => 'uuid'],
                    'description' => 'Primary record identifier'
                ];
                
                $parameters[] = [
                    'name' => 'relationshipName',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => 'Name of the relationship'
                ];
                
                $parameters[] = [
                    'name' => 'idToLink',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string', 'format' => 'uuid'],
                    'description' => 'Related record identifier to link/unlink'
                ];
                break;
        }
        
        return $parameters;
    }
    
    /**
     * Get model searchable fields dynamically
     */
    private function getModelSearchableFields(string $modelName): array {
        try {
            $model = $this->modelFactory->new($modelName);
            return $model->getSearchableFieldsList();
        } catch (\Exception $e) {
            $this->logger->debug("Could not get searchable fields for {$modelName}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate search description based on searchable fields
     */
    private function generateSearchDescription(string $modelName, array $searchableFields): string {
        if (empty($searchableFields)) {
            return "Search {$modelName} records";
        }
        
        $fieldList = implode(', ', $searchableFields);
        return "Search {$modelName} records by searching across: {$fieldList}";
    }
    
    /**
     * Get example search term for model
     */
    private function getExampleSearchTerm(string $modelName): string {
        $examples = [
            'Movies' => 'Star Wars',
            'Users' => 'john',
            'Movie_Quotes' => 'may the force',
            'Roles' => 'admin'
        ];
        
        return $examples[$modelName] ?? strtolower($modelName);
    }
    
    /**
     * Get example sort field for model
     */
    private function generateSortExample(string $modelName): string {
        $examples = [
            'Movies' => 'name',
            'Users' => 'username',
            'Movie_Quotes' => 'quote',
            'Roles' => 'name'
        ];
        
        return $examples[$modelName] ?? 'id';
    }
    
    /**
     * Get example relationship name for model
     */
    private function generateRelationshipExample(string $modelName): string {
        $examples = [
            'Movies' => 'Movie_Quotes',
            'Users' => 'Roles',
            'Movie_Quotes' => 'Movies'
        ];
        
        return $examples[$modelName] ?? 'related';
    }
    
    /**
     * Get model filterable fields
     */
    private function getModelFilterableFields(string $modelName): array {
        try {
            $metadata = $this->metadataEngine->getModelMetadata($modelName);
            $filterableFields = [];
            
            foreach ($metadata['fields'] ?? [] as $fieldName => $fieldDef) {
                // Skip certain field types that aren't good for filtering
                $skipTypes = ['Image', 'Video', 'Password', 'BigText'];
                $fieldType = $fieldDef['type'] ?? 'Text';
                
                if (!in_array($fieldType, $skipTypes)) {
                    $filterableFields[$fieldName] = $fieldDef;
                }
            }
            
            return $filterableFields;
        } catch (\Exception $e) {
            $this->logger->debug("Could not get filterable fields for {$modelName}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate field schema for OpenAPI
     */
    private function generateFieldSchema(array $fieldInfo): array {
        switch ($fieldInfo['type'] ?? 'Text') {
            case 'Integer':
            case 'ID':
                return ['type' => 'integer'];
            case 'Float':
                return ['type' => 'number'];
            case 'Boolean':
                return ['type' => 'boolean'];
            case 'Date':
            case 'DateTime':
                return ['type' => 'string', 'format' => 'date'];
            default:
                return ['type' => 'string'];
        }
    }
    
    /**
     * Generate field example value
     */
    private function generateFieldExample(string $fieldName, array $fieldInfo): mixed {
        switch ($fieldInfo['type'] ?? 'Text') {
            case 'Integer':
                return $fieldName === 'release_year' ? 2024 : 1;
            case 'Boolean':
                return true;
            case 'Date':
            case 'DateTime':
                return '2024-01-01';
            default:
                $examples = [
                    'release_year' => 1980,
                    'name' => 'Example Name',
                    'email' => 'user@example.com'
                ];
                return $examples[$fieldName] ?? 'example';
        }
    }
    
    /**
     * Get example ID for model
     */
    private function getExampleId(string $modelName): string {
        $examples = [
            'Movies' => '123e4567-e89b-12d3-a456-426614174000',
            'Users' => '234e5678-e89b-12d3-a456-426614174001',
            'Movie_Quotes' => '345e6789-e89b-12d3-a456-426614174002'
        ];
        
        return $examples[$modelName] ?? '456e7890-e89b-12d3-a456-426614174999';
    }
    
    /**
     * Generate intent metadata (x-gravitycar-* extensions)
     */
    private function generateIntentMetadata(string $modelName, string $operation): array {
        $intentMap = [
            'list' => 'search',
            'retrieve' => 'read',
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            'listDeleted' => 'search-deleted',
            'restore' => 'restore',
            'listRelated' => 'search-related',
            'createAndLink' => 'create-and-link',
            'link' => 'link',
            'unlink' => 'unlink'
        ];
        
        return [
            'x-gravitycar-intent' => $intentMap[$operation] ?? 'unknown',
            'x-gravitycar-entity' => $modelName,
            'x-gravitycar-database' => 'internal',
            'x-gravitycar-operation-type' => $this->getOperationType($operation)
        ];
    }
    
    /**
     * Get operation type for x-gravitycar-operation-type extension
     */
    private function getOperationType(string $operation): string {
        $operationTypes = [
            'list' => 'read-collection',
            'retrieve' => 'read-single',
            'create' => 'write-single',
            'update' => 'write-single',
            'delete' => 'write-single',
            'listDeleted' => 'read-collection',
            'restore' => 'write-single',
            'listRelated' => 'read-collection',
            'createAndLink' => 'write-relationship',
            'link' => 'write-relationship',
            'unlink' => 'write-relationship'
        ];
        
        return $operationTypes[$operation] ?? 'unknown';
    }
    
    /**
     * Generate enhanced responses with proper status codes
     */
    private function generateEnhancedResponses(string $modelName, string $operation): array {
        $responses = [
            '200' => $this->generateSuccessResponse($modelName, $operation),
            '400' => ['$ref' => '#/components/responses/BadRequest'],
            '401' => ['$ref' => '#/components/responses/Unauthorized'],
            '403' => ['$ref' => '#/components/responses/Forbidden'],
            '500' => ['$ref' => '#/components/responses/InternalServerError']
        ];
        
        // Add operation-specific responses
        switch ($operation) {
            case 'retrieve':
            case 'update':
            case 'delete':
            case 'restore':
            case 'listRelated':
            case 'link':
            case 'unlink':
                $responses['404'] = ['$ref' => '#/components/responses/NotFound'];
                break;
            case 'create':
            case 'createAndLink':
                $responses['409'] = ['$ref' => '#/components/responses/Conflict'];
                $responses['422'] = ['$ref' => '#/components/responses/UnprocessableEntity'];
                break;
        }
        
        return $responses;
    }
    
    /**
     * Generate success response schema
     */
    private function generateSuccessResponse(string $modelName, string $operation): array {
        switch ($operation) {
            case 'list':
            case 'listDeleted':
            case 'listRelated':
                return [
                    'description' => "Collection of {$modelName} records",
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'success' => ['type' => 'boolean'],
                                    'data' => [
                                        'type' => 'array',
                                        'items' => ['$ref' => "#/components/schemas/{$modelName}"]
                                    ],
                                    'meta' => ['$ref' => '#/components/schemas/Pagination']
                                ]
                            ]
                        ]
                    ]
                ];
                
            case 'retrieve':
            case 'create':
            case 'update':
            case 'restore':
            case 'createAndLink':
                return [
                    'description' => "Single {$modelName} record",
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'success' => ['type' => 'boolean'],
                                    'data' => ['$ref' => "#/components/schemas/{$modelName}"]
                                ]
                            ]
                        ]
                    ]
                ];
                
            case 'delete':
            case 'link':
            case 'unlink':
                return [
                    'description' => 'Operation completed successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'success' => ['type' => 'boolean'],
                                    'message' => ['type' => 'string']
                                ]
                            ]
                        ]
                    ]
                ];
                
            default:
                return [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiResponse']
                        ]
                    ]
                ];
        }
    }
    
    /**
     * Generate example request body (to be enhanced with real data in Phase 4)
     */
    private function generateExampleRequestBody(string $modelName, string $operation): array {
        // Simple fallback examples for now - Phase 4 will enhance with real database data
        $examples = [
            'Movies' => ['name' => 'Example Movie Title', 'release_year' => 2024],
            'Users' => ['username' => 'example_user', 'email' => 'user@example.com'],
            'Movie_Quotes' => ['quote' => 'This is an example movie quote.']
        ];
        
        if ($operation === 'update') {
            $example = $examples[$modelName] ?? ['name' => "Example {$modelName}"];
            $example['id'] = $this->getExampleId($modelName);
            return $example;
        }
        
        return $examples[$modelName] ?? ['name' => "Example {$modelName}"];
    }
}
