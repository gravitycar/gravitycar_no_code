# OpenAPI Improvements Implementation Plan

## Feature Overview

This plan addresses the enhancement of the Gravitycar Framework's OpenAPI documentation to better support AI tools like MCP servers and improve the overall developer experience. The current implementation generates basic OpenAPI 3.0.3 specifications with dynamic wildcard routes, but lacks specific model routes, proper RBAC filtering, and rich documentation details.

## Requirements

### Functional Requirements

1. **Explicit Model Routes**: Replace dynamic wildcard routes (`/?`, `/?/?`) with explicit routes for each model (`/Movies`, `/Users`, etc.)
2. **RBAC-Based Route Filtering**: Only document routes accessible to users with the 'user' role using `AuthorizationService::hasPermissionForRoute()`
3. **Enhanced Documentation**: Provide rich, human and AI-friendly descriptions, examples, and natural language summaries
4. **Intent Metadata**: Add custom `x-gravitycar-*` extensions for better AI tool integration
5. **Complete Parameter Documentation**: Include all supported query parameters (search, page, pageSize, etc.)
6. **Comprehensive Response Schemas**: Document all response types (200, 401, 403, 404, 421, 500)

### Non-Functional Requirements

1. **Performance**: Maintain efficient caching of generated OpenAPI specifications
2. **Maintainability**: Use metadata-driven approach to minimize manual documentation
3. **Extensibility**: Support future expansion of API documentation features

## Discovered Current Parameter Support

Based on analysis of the codebase and API testing, the Gravitycar Framework currently supports these parameters:

### Pagination Parameters
- `page` - Page number (1-based, default: 1)
- `pageSize` - Records per page (default: 20, max: 1000)

### Search Parameters  
- `search` - Global search term that searches across model-specific searchable fields
- Model-specific searchable fields are determined by:
  1. `searchableFields` in model metadata (if defined)
  2. `displayColumns` fields that are text-searchable (fallback)
  3. Auto-detected text fields (TextField, EmailField, BigTextField, Enum, MultiEnum)

**Current Model Search Fields:**
- **Movies**: searches on `name` field only
- **Movie_Quotes**: searches on `quote` field only  
- **Users**: searches on `first_name`, `last_name`, `email`, `username`

### Sorting Parameters
- `sortBy` - Field name to sort by
- `sortOrder` - Direction: `asc` or `desc` (default: asc)

### Filtering Parameters
- **Dynamic Field Filters**: Any field name can be used as a query parameter for exact match filtering
- **Examples**: `release_year=1980`, `name=Star Wars`, `status=active`
- **Supported Field Types**: Text, Integer, Float, Boolean, Date, DateTime, Enum
- **Excluded Field Types**: Image, Video, Password, BigText (performance/security reasons)

### Response Format
The API returns comprehensive metadata including:
- Applied filters with field types and operators
- Applied search terms and searched fields  
- Applied sorting with field and direction
- Pagination metadata with total counts and page info
- Available fields information (when requested)

### High-Level Architecture

The enhancement will modify the existing `OpenAPIGenerator` service to:

1. **Model Discovery & Route Generation**: Replace dynamic route discovery with explicit model-based route generation
2. **Permission Filtering**: Integrate with `AuthorizationService` to filter routes based on user permissions
3. **Enhanced Documentation Generation**: Generate rich descriptions and examples for each route
4. **Schema Enhancement**: Improve response schemas with detailed field information

### Key Components

1. **OpenAPIGenerator** (Enhanced)
   - New `generateExplicitModelRoutes()` method
   - Enhanced `generateOperationFromModelRoute()` method
   - Integration with `AuthorizationService`
   - Custom extension generation (`x-gravitycar-*`)

2. **OpenAPIPermissionFilter** (New)
   - Handles RBAC-based route filtering
   - Creates test user with 'user' role for permission checking
   - Caches permission results for performance

3. **OpenAPIModelRouteBuilder** (New) 
   - Generates explicit routes for each model
   - Creates natural language descriptions
   - Builds comprehensive parameter documentation
   - Generates intent metadata

### Route Generation Strategy

#### Current Behavior (Dynamic Routes)
```json
{
  "\/?": {
    "get": {
      "summary": "List api records",
      "operationId": "get_?",
      "tags": ["api"]
    }
  }
}
```

#### Target Behavior (Explicit Model Routes)
**Example: Movies Model with Full Route Coverage**
```json
{
  "\/Movies": {
    "get": {
      "summary": "Retrieve Movies records from the gravitycar api with optional search parameters",
      "description": "Get a paginated list of movies with optional filtering by title or name. Supports search, pagination, and sorting parameters.",
      "operationId": "get_Movies",
      "tags": ["api", "Movies"],
      "parameters": [
        {
          "name": "search",
          "in": "query",
          "description": "Search Movies records by searching across: name",
          "schema": {"type": "string"},
          "example": "Star Wars"
        },
        {
          "name": "page",
          "in": "query", 
          "description": "Page number for pagination (starts at 1)",
          "schema": {"type": "integer", "minimum": 1, "default": 1}
        },
        {
          "name": "pageSize",
          "in": "query",
          "description": "Number of records per page (max 1000)",
          "schema": {"type": "integer", "minimum": 1, "maximum": 1000, "default": 20}
        },
        {
          "name": "sortBy",
          "in": "query",
          "description": "Field to sort by",
          "schema": {"type": "string"},
          "example": "name"
        },
        {
          "name": "sortOrder",
          "in": "query",
          "description": "Sort direction (asc or desc)",
          "schema": {"type": "string", "enum": ["asc", "desc"], "default": "asc"}
        },
        {
          "name": "name",
          "in": "query",
          "description": "Filter by Title (exact match)",
          "schema": {"type": "string"},
          "example": "Star Wars"
        },
        {
          "name": "release_year",
          "in": "query",
          "description": "Filter by Release Year (exact match)",
          "schema": {"type": "integer"},
          "example": 2024
        },
        {
          "name": "tmdb_id",
          "in": "query",
          "description": "Filter by TMDB ID (exact match)",
          "schema": {"type": "integer"},
          "example": 1
        }
      ],
      "responses": {
        "200": {
          "description": "List of Movies matching optional filter criteria",
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "success": {"type": "boolean"},
                  "data": {
                    "type": "array",
                    "items": {"$ref": "#/components/schemas/Movies"}
                  },
                  "pagination": {"$ref": "#/components/schemas/Pagination"}
                }
              }
            }
          }
        },
        "401": {"$ref": "#/components/responses/Unauthorized"},
        "403": {"$ref": "#/components/responses/Forbidden"},
        "500": {"$ref": "#/components/responses/InternalServerError"}
      },
      "x-gravitycar-intent": "search",
      "x-gravitycar-entity": "Movies",
      "x-gravitycar-database": "internal",
      "x-gravitycar-operation-type": "read-collection",
      "x-gravitycar-examples": [
        {
          "description": "Find movies by title",
          "parameters": {"search": "Star Wars"}
        },
        {
          "description": "Get second page of results",
          "parameters": {"page": 2, "pageSize": 20}
        }
      ]
    },
    "post": {
      "summary": "Create a new Movies record with the provided data",
      "operationId": "post_Movies",
      "tags": ["api", "Movies"],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {"$ref": "#/components/schemas/MoviesInput"}
          }
        }
      },
      "responses": {
        "200": {"$ref": "#/components/responses/MoviesCreated"},
        "409": {"$ref": "#/components/responses/Conflict"},
        "422": {"$ref": "#/components/responses/UnprocessableEntity"}
      },
      "x-gravitycar-intent": "create",
      "x-gravitycar-entity": "Movies",
      "x-gravitycar-operation-type": "write-single"
    }
  },
  "\/Movies\/{id}": {
    "get": {
      "summary": "Get a specific Movies record by its unique identifier",
      "operationId": "get_Movies_byId",
      "tags": ["api", "Movies"],
      "parameters": [
        {
          "name": "id",
          "in": "path",
          "required": true,
          "description": "Unique identifier for the Movies record",
          "schema": {"type": "string", "format": "uuid"}
        }
      ],
      "responses": {
        "200": {"$ref": "#/components/responses/MoviesRetrieved"},
        "404": {"$ref": "#/components/responses/NotFound"}
      },
      "x-gravitycar-intent": "read",
      "x-gravitycar-operation-type": "read-single"
    }
  },
  "\/Movies\/deleted": {
    "get": {
      "summary": "Retrieve soft-deleted Movies records that can be restored",
      "operationId": "get_Movies_deleted",
      "tags": ["api", "Movies"],
      "x-gravitycar-intent": "search-deleted",
      "x-gravitycar-operation-type": "read-collection"
    }
  },
  "\/Movies\/{id}\/restore": {
    "put": {
      "summary": "Restore a previously deleted Movies record by its unique identifier",
      "operationId": "put_Movies_restore",
      "tags": ["api", "Movies"],
      "x-gravitycar-intent": "restore",
      "x-gravitycar-operation-type": "write-single"
    }
  },
  "\/Movies\/{id}\/link\/{relationshipName}": {
    "get": {
      "summary": "Get related records linked to a specific Movies via the specified relationship",
      "operationId": "get_Movies_related",
      "tags": ["api", "Movies"],
      "parameters": [
        {
          "name": "id",
          "in": "path",
          "required": true,
          "description": "Unique identifier for the Movies record",
          "schema": {"type": "string", "format": "uuid"}
        },
        {
          "name": "relationshipName",
          "in": "path",
          "required": true,
          "description": "Name of the relationship to access",
          "schema": {"type": "string"},
          "example": "quotes"
        }
      ],
      "x-gravitycar-intent": "search-related",
      "x-gravitycar-operation-type": "read-collection"
    },
    "post": {
      "summary": "Create a new related record and automatically link it to the specified Movies",
      "operationId": "post_Movies_createAndLink",
      "tags": ["api", "Movies"],
      "x-gravitycar-intent": "create-and-link",
      "x-gravitycar-operation-type": "write-relationship"
    }
  },
  "\/Movies\/{id}\/link\/{relationshipName}\/{idToLink}": {
    "put": {
      "summary": "Create a relationship link between a Movies record and an existing related record",
      "operationId": "put_Movies_link",
      "tags": ["api", "Movies"],
      "x-gravitycar-intent": "link",
      "x-gravitycar-operation-type": "write-relationship"
    },
    "delete": {
      "summary": "Remove a relationship link between a Movies record and a related record",
      "operationId": "delete_Movies_unlink",
      "tags": ["api", "Movies"],
      "x-gravitycar-intent": "unlink",
      "x-gravitycar-operation-type": "write-relationship"
    }
  }
}
```

## Implementation Steps

### Phase 1: Core Infrastructure (3-4 days)

#### Step 1.1: Create OpenAPIPermissionFilter Service
```php
namespace Gravitycar\Services;

class OpenAPIPermissionFilter {
    private AuthorizationService $authorizationService;
    private ModelFactory $modelFactory;
    private array $permissionCache = [];
    
    public function isRouteAccessibleToUsers(array $route): bool;
    private function getTestUser(): ModelBase;
    private function createTestRequest(array $route): Request;
}
```

**Implementation Details:**
- Create test user with 'user' role (jane@example.com)
- Implement permission caching to avoid repeated database queries
- Handle edge cases for routes without clear model mapping

#### Step 1.2: Create OpenAPIModelRouteBuilder Service
```php
namespace Gravitycar\Services;

class OpenAPIModelRouteBuilder {
    private MetadataEngineInterface $metadataEngine;
    private FieldFactory $fieldFactory;
    private ModelFactory $modelFactory;
    
    public function generateModelRoutes(string $modelName): array;
    public function generateModelOperation(string $modelName, string $httpMethod, string $routePattern): array;
    private function generateNaturalLanguageDescription(string $modelName, string $operation): string;
    private function generateParameters(string $modelName, string $operation): array;
    private function generateIntentMetadata(string $modelName, string $operation): array;
    private function generateRelationshipExample(string $modelName): string;
    private function generateExampleRequestBody(string $modelName, string $operation): array;
    private function getOperationType(string $operation): string;
    private function generateSuccessResponse(string $modelName, string $operation): array;
    
    // New methods for dynamic parameter generation
    private function getModelSearchableFields(string $modelName): array;
    private function generateSearchDescription(string $modelName, array $searchableFields): string;
    private function generateSortExample(string $modelName): string;
    private function getModelFilterableFields(string $modelName): array;
    private function generateFieldSchema(array $fieldInfo): array;
    private function generateFieldExample(string $fieldName, array $fieldInfo): mixed;
}
```

**Key Methods:**
- `generateModelRoutes()`: Creates all CRUD and relationship routes for a model
- `generateNaturalLanguageDescription()`: Creates human-friendly descriptions
- `generateParameters()`: Documents query parameters dynamically based on model metadata
- `generateIntentMetadata()`: Adds `x-gravitycar-*` extensions including operation types
- `generateRelationshipExample()`: Provides model-specific relationship examples
- `generateExampleRequestBody()`: Creates example request bodies for create/update operations
- `getModelSearchableFields()`: Dynamically retrieves searchable fields from model instance
- `getModelFilterableFields()`: Dynamically discovers filterable fields from model metadata
- `generateFieldSchema()`: Creates proper OpenAPI schemas based on field types
- `generateFieldExample()`: Generates realistic examples for each field type

#### Step 1.3: Enhance OpenAPIGenerator with Model Route Generation
```php
// In OpenAPIGenerator class
private function generatePaths(): array {
    $paths = [];
    
    // Get explicit model routes instead of dynamic routes
    $explicitPaths = $this->generateExplicitModelPaths();
    $paths = array_merge($paths, $explicitPaths);
    
    // Get non-model routes (auth, metadata, etc.)
    $staticPaths = $this->generateStaticPaths();
    $paths = array_merge($paths, $staticPaths);
    
    return $paths;
}

private function generateExplicitModelPaths(): array;
private function generateStaticPaths(): array;

// Methods for enriching ALL routes with OpenAPI defaults (Addresses Gap 3)
public function enrichRouteWithOpenAPIDefaults(array $route): array;
private function generateOperationId(array $route): string;
private function generateTagsFromPath(string $path): array;
```

**Custom Route Documentation (Addresses Gap 3):**

This functionality applies to **all routes** (both model-based and custom controller routes).

Controllers can provide OpenAPI documentation via optional fields in `registerRoutes()`:

```php
// Example: Custom controller with OpenAPI documentation
public function registerRoutes(): array {
    return [
        [
            // Required fields
            'method' => 'GET',
            'path' => '/trivia/game/{gameId}/question',
            'apiClass' => self::class,
            'apiMethod' => 'getNextQuestion',
            'parameterNames' => ['', '', 'gameId'],
            
            // Optional OpenAPI fields (work for ANY controller)
            'summary' => 'Get next trivia question',
            'description' => 'Retrieves the next unanswered question for the specified game.',
            'operationId' => 'getTriviaQuestion',
            'tags' => ['Trivia', 'Games']
        ]
    ];
}
```

**Auto-Generation for Missing Fields:**
```php
// In OpenAPIGenerator (applies to all routes)
public function enrichRouteWithOpenAPIDefaults(array $route): array {
    // Generate operationId if not provided
    if (empty($route['operationId'])) {
        $route['operationId'] = $this->generateOperationId($route);
        $this->logger->info('Auto-generated operationId for route', [
            'path' => $route['path'],
            'operationId' => $route['operationId']
        ]);
    }
    
    // Generate tags if not provided
    if (empty($route['tags'])) {
        $route['tags'] = $this->generateTagsFromPath($route['path']);
        $this->logger->info('Auto-generated tags for route', [
            'path' => $route['path'],
            'tags' => $route['tags']
        ]);
    }
    
    // Log warning if summary is missing (recommended but not required)
    if (empty($route['summary'])) {
        $this->logger->warning('Route missing summary field', [
            'path' => $route['path'],
            'controller' => $route['apiClass'] ?? 'unknown'
        ]);
    }
    
    return $route;
}

private function generateOperationId(array $route): string {
    // Convert path to camelCase operation name
    // Example: GET /trivia/game/{gameId}/question -> getTriviaGameQuestion
    $pathParts = array_filter(explode('/', $route['path']), fn($p) => !empty($p) && !str_starts_with($p, '{'));
    $method = strtolower($route['method']);
    
    $operation = $method . implode('', array_map('ucfirst', $pathParts));
    return $operation;
}

private function generateTagsFromPath(string $path): array {
    // Extract primary resource from path
    // Example: /trivia/game/{gameId} -> ['Trivia']
    $parts = array_filter(explode('/', $path), fn($p) => !empty($p) && !str_starts_with($p, '{'));
    
    if (empty($parts)) {
        return ['API'];
    }
    
    return [ucfirst($parts[0])];
}
```

### Phase 2: Model Route Generation (4-5 days)

#### Step 2.1: Implement Model Discovery and Route Generation
- Use `MetadataEngine::getAvailableModels()` to discover all models
- Generate explicit routes for each model based on `ModelBaseAPIController` patterns
- Map wildcard patterns to specific model routes:

**Basic CRUD Operations:**
  - `GET /?` → `GET /Movies`, `GET /Users`, etc. (list)
  - `GET /?/?` → `GET /Movies/{id}`, `GET /Users/{id}`, etc. (retrieve)
  - `POST /?` → `POST /Movies`, `POST /Users`, etc. (create)
  - `PUT /?/?` → `PUT /Movies/{id}`, `PUT /Users/{id}`, etc. (update)
  - `DELETE /?/?` → `DELETE /Movies/{id}`, `DELETE /Users/{id}`, etc. (delete)

**Soft Delete Management:**
  - `GET /?/deleted` → `GET /Movies/deleted`, `GET /Users/deleted`, etc. (listDeleted)
  - `PUT /?/?/restore` → `PUT /Movies/{id}/restore`, `PUT /Users/{id}/restore`, etc. (restore)

**Relationship Operations:**
  - `GET /?/?/link/?` → `GET /Movies/{id}/link/{relationshipName}` (listRelated)
  - `POST /?/?/link/?` → `POST /Movies/{id}/link/{relationshipName}` (createAndLink)
  - `PUT /?/?/link/?/?` → `PUT /Movies/{id}/link/{relationshipName}/{idToLink}` (link)
  - `DELETE /?/?/link/?/?` → `DELETE /Movies/{id}/link/{relationshipName}/{idToLink}` (unlink)

#### Step 2.2: Implement Natural Language Description Generation
```php
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
```

#### Step 2.3: Implement Parameter Documentation
```php
private function generateParameters(string $modelName, string $operation): array {
    $parameters = [];
    
    switch ($operation) {
        case 'list':
        case 'listDeleted':
            // Get model-specific searchable fields for better documentation
            $searchableFields = $this->getModelSearchableFields($modelName);
            $searchDescription = $this->generateSearchDescription($modelName, $searchableFields);
            
            $parameters = [
                [
                    'name' => 'search',
                    'in' => 'query',
                    'description' => $searchDescription,
                    'schema' => ['type' => 'string'],
                    'example' => $this->generateSearchExample($modelName)
                ],
                [
                    'name' => 'page',
                    'in' => 'query',
                    'description' => 'Page number for pagination (starts at 1)',
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]
                ],
                [
                    'name' => 'pageSize',
                    'in' => 'query',
                    'description' => 'Number of records per page (max 1000)',
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 20]
                ],
                [
                    'name' => 'sortBy',
                    'in' => 'query',
                    'description' => 'Field to sort by',
                    'schema' => ['type' => 'string'],
                    'example' => $this->generateSortExample($modelName)
                ],
                [
                    'name' => 'sortOrder',
                    'in' => 'query',
                    'description' => 'Sort direction (asc or desc)',
                    'schema' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc']
                ]
            ];
            
            // Add dynamic field filters based on model fields
            $modelFields = $this->getModelFilterableFields($modelName);
            foreach ($modelFields as $fieldName => $fieldInfo) {
                $parameters[] = [
                    'name' => $fieldName,
                    'in' => 'query',
                    'description' => "Filter by {$fieldInfo['label']} (exact match)",
                    'schema' => $this->generateFieldSchema($fieldInfo),
                    'example' => $this->generateFieldExample($fieldName, $fieldInfo)
                ];
            }
            break;
            
        case 'retrieve':
        case 'update':
        case 'delete':
        case 'restore':
            $parameters = [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'description' => "Unique identifier for the {$modelName} record",
                    'schema' => ['type' => 'string', 'format' => 'uuid']
                ]
            ];
            break;
            
        case 'listRelated':
        case 'createAndLink':
            $parameters = [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'description' => "Unique identifier for the {$modelName} record",
                    'schema' => ['type' => 'string', 'format' => 'uuid']
                ],
                [
                    'name' => 'relationshipName',
                    'in' => 'path',
                    'required' => true,
                    'description' => "Name of the relationship to access",
                    'schema' => ['type' => 'string'],
                    'example' => $this->generateRelationshipExample($modelName)
                ]
            ];
            // Add pagination for listRelated
            if ($operation === 'listRelated') {
                $parameters = array_merge($parameters, [
                    [
                        'name' => 'search',
                        'in' => 'query',
                        'description' => 'Search filter for related records',
                        'schema' => ['type' => 'string']
                    ],
                    [
                        'name' => 'page',
                        'in' => 'query',
                        'description' => 'Page number for pagination (starts at 1)',
                        'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]
                    ],
                    [
                        'name' => 'pageSize',
                        'in' => 'query',
                        'description' => 'Number of records per page (max 100)',
                        'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10]
                    ]
                ]);
            }
            break;
            
        case 'link':
        case 'unlink':
            $parameters = [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'description' => "Unique identifier for the {$modelName} record",
                    'schema' => ['type' => 'string', 'format' => 'uuid']
                ],
                [
                    'name' => 'relationshipName',
                    'in' => 'path',
                    'required' => true,
                    'description' => "Name of the relationship to manage",
                    'schema' => ['type' => 'string'],
                    'example' => $this->generateRelationshipExample($modelName)
                ],
                [
                    'name' => 'idToLink',
                    'in' => 'path',
                    'required' => true,
                    'description' => "Unique identifier for the related record to link/unlink",
                    'schema' => ['type' => 'string', 'format' => 'uuid']
                ]
            ];
            break;
    }
    
    return $parameters;
}

private function generateRelationshipExample(string $modelName): string {
    $examples = [
        'Movies' => 'quotes',
        'Users' => 'roles',
        'Books' => 'authors',
        'Roles' => 'users'
    ];
    
    return $examples[$modelName] ?? 'related';
}

private function generateExampleRequestBody(string $modelName, string $operation): array {
    try {
        $model = $this->modelFactory->new($modelName);
        $metadata = $model->getMetadata();
        $requestBody = [];
        
        // Define audit fields that should never be included in request bodies
        $auditFields = [
            'id', 'created_at', 'updated_at', 'deleted_at', 
            'created_by', 'updated_by', 'deleted_by',
            'created_by_name', 'updated_by_name', 'deleted_by_name'
        ];
        
        // For update operations, include the id field
        if (in_array($operation, ['update', 'restore'])) {
            $requestBody['id'] = $this->getExampleId($modelName);
        }
        
        foreach ($metadata['fields'] ?? [] as $fieldName => $fieldDef) {
            // Skip audit fields (except id for updates)
            if (in_array($fieldName, $auditFields) && !($fieldName === 'id' && in_array($operation, ['update', 'restore']))) {
                continue;
            }
            
            // Skip read-only fields
            if ($fieldDef['readOnly'] ?? false) {
                continue;
            }
            
            // Skip non-DB fields unless they're input fields
            if (($fieldDef['isDBField'] ?? true) === false) {
                continue;
            }
            
            // For create operations, include all required fields and some optional ones
            if ($operation === 'create' || $operation === 'createAndLink') {
                if ($this->isFieldRequired($fieldDef) || $this->shouldIncludeInExample($fieldName, $fieldDef)) {
                    $requestBody[$fieldName] = $this->generateFieldExampleValue($fieldName, $fieldDef, $modelName);
                }
            }
            
            // For update operations, include a mix of fields that might be updated
            if (in_array($operation, ['update', 'restore'])) {
                if ($this->shouldIncludeInUpdateExample($fieldName, $fieldDef)) {
                    $requestBody[$fieldName] = $this->generateFieldExampleValue($fieldName, $fieldDef, $modelName);
                }
            }
        }
        
        return $requestBody;
        
    } catch (Exception $e) {
        // Fallback to simple examples if model instantiation fails
        return $this->getFallbackExampleRequestBody($modelName, $operation);
    }
}

private function isFieldRequired(array $fieldDef): bool {
    // Check both required flag and validation rules
    if ($fieldDef['required'] ?? false) {
        return true;
    }
    
    $validationRules = $fieldDef['validationRules'] ?? [];
    return in_array('Required', $validationRules);
}

private function shouldIncludeInExample(string $fieldName, array $fieldDef): bool {
    // Include commonly used fields in examples even if not required
    $commonFields = ['name', 'title', 'email', 'username', 'description', 'status', 'type'];
    
    if (in_array($fieldName, $commonFields)) {
        return true;
    }
    
    // Include text fields that are likely user-input
    $fieldType = $fieldDef['type'] ?? '';
    $userInputTypes = ['Text', 'Email', 'Enum', 'Integer', 'Float', 'Boolean', 'Date', 'DateTime'];
    
    return in_array($fieldType, $userInputTypes);
}

private function shouldIncludeInUpdateExample(string $fieldName, array $fieldDef): bool {
    // For updates, include a subset of updateable fields
    $fieldType = $fieldDef['type'] ?? '';
    $updateableTypes = ['Text', 'Email', 'Integer', 'Float', 'Boolean', 'Date', 'DateTime', 'Enum'];
    
    // Skip certain field types that are rarely updated
    $skipTypes = ['Image', 'Video', 'Password', 'BigText'];
    
    return in_array($fieldType, $updateableTypes) && !in_array($fieldType, $skipTypes);
}

private function generateFieldExampleValue(string $fieldName, array $fieldDef, string $modelName): mixed {
    $fieldType = $fieldDef['type'] ?? 'Text';
    
    switch ($fieldType) {
        case 'Integer':
            return $this->getIntegerExample($fieldName, $fieldDef);
        case 'Float':
            return $this->getFloatExample($fieldName, $fieldDef);
        case 'Boolean':
            return $this->getBooleanExample($fieldName);
        case 'Date':
        case 'DateTime':
            return $this->getDateExample($fieldType);
        case 'Email':
            return $this->getEmailExample($modelName);
        case 'Enum':
            return $this->getEnumExample($fieldDef);
        case 'Text':
        case 'BigText':
        default:
            return $this->getTextExample($fieldName, $modelName);
    }
}

private function getIntegerExample(string $fieldName, array $fieldDef): int {
    // Use field constraints if available
    $minValue = $fieldDef['minValue'] ?? null;
    $maxValue = $fieldDef['maxValue'] ?? null;
    
    // Field-specific examples
    $examples = [
        'release_year' => 2024,
        'year' => 2024,
        'age' => 25,
        'count' => 10,
        'rating' => 5,
        'score' => 85,
        'priority' => 1,
        'order' => 1,
        'position' => 1
    ];
    
    if (isset($examples[$fieldName])) {
        $value = $examples[$fieldName];
        // Ensure value respects constraints
        if ($minValue !== null && $value < $minValue) $value = $minValue;
        if ($maxValue !== null && $value > $maxValue) $value = $maxValue;
        return $value;
    }
    
    // Default based on constraints
    if ($minValue !== null && $maxValue !== null) {
        return (int) (($minValue + $maxValue) / 2);
    }
    
    return 1;
}

private function getFloatExample(string $fieldName, array $fieldDef): float {
    $examples = [
        'price' => 29.99,
        'rate' => 4.5,
        'percentage' => 75.5,
        'weight' => 2.5,
        'height' => 180.0,
        'latitude' => 37.7749,
        'longitude' => -122.4194
    ];
    
    return $examples[$fieldName] ?? 1.0;
}

private function getBooleanExample(string $fieldName): bool {
    // Field-specific boolean defaults
    $trueDefaults = ['active', 'enabled', 'published', 'verified', 'public', 'visible'];
    $falseDefaults = ['deleted', 'disabled', 'private', 'hidden', 'archived'];
    
    if (in_array($fieldName, $trueDefaults)) {
        return true;
    }
    
    if (in_array($fieldName, $falseDefaults)) {
        return false;
    }
    
    return true; // Default to true
}

private function getDateExample(string $fieldType): string {
    if ($fieldType === 'DateTime') {
        return '2024-01-15T10:30:00Z';
    }
    return '2024-01-15';
}

private function getEmailExample(string $modelName): string {
    $prefix = strtolower($modelName);
    return "example@{$prefix}.com";
}

private function getEnumExample(array $fieldDef): string {
    // Try to get first option from enum options if available
    $options = $fieldDef['options'] ?? $fieldDef['enumOptions'] ?? [];
    
    if (!empty($options)) {
        return is_array($options) ? array_keys($options)[0] : $options[0];
    }
    
    return 'option1';
}

private function getTextExample(string $fieldName, string $modelName): string {
    // Field-specific text examples
    $examples = [
        'name' => "Example {$modelName} Name",
        'title' => "Example {$modelName} Title", 
        'description' => "This is an example {$modelName} description",
        'summary' => "Example summary",
        'content' => "Example content",
        'username' => 'example_user',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '+1-555-123-4567',
        'address' => '123 Example Street',
        'city' => 'Example City',
        'state' => 'CA',
        'country' => 'US',
        'zipcode' => '12345',
        'code' => 'EX123',
        'slug' => 'example-slug',
        'tag' => 'example-tag',
        'category' => 'example-category',
        'status' => 'active',
        'type' => 'standard',
        'url' => 'https://example.com',
        'quote' => 'This is an example quote from the movie.',
        'synopsis' => 'An engaging story about...',
        'comment' => 'This is an example comment.'
    ];
    
    return $examples[$fieldName] ?? "Example {$fieldName}";
}

private function getFallbackExampleRequestBody(string $modelName, string $operation): array {
    // Simple fallback examples when model metadata is not accessible
    $fallbacks = [
        'Movies' => [
            'create' => ['name' => 'Example Movie Title'],
            'update' => ['id' => '123e4567-e89b-12d3-a456-426614174000', 'name' => 'Updated Movie Title']
        ],
        'Users' => [
            'create' => ['username' => 'example_user', 'email' => 'user@example.com'],
            'update' => ['id' => '234e5678-e89b-12d3-a456-426614174001', 'username' => 'updated_user']
        ],
        'Movie_Quotes' => [
            'create' => ['quote' => 'This is an example movie quote.'],
            'update' => ['id' => '345e6789-e89b-12d3-a456-426614174002', 'quote' => 'This is an updated movie quote.']
        ]
    ];
    
    $opType = in_array($operation, ['update', 'restore']) ? 'update' : 'create';
    
    return $fallbacks[$modelName][$opType] ?? 
           ($opType === 'update' 
               ? ['id' => '456e7890-e89b-12d3-a456-426614174999', 'name' => "Updated {$modelName}"]
               : ['name' => "Example {$modelName}"]);
}


private function getModelSearchableFields(string $modelName): array {
    try {
        $model = $this->modelFactory->new($modelName);
        return $model->getSearchableFields();
    } catch (Exception $e) {
        return [];
    }
}

private function generateSearchDescription(string $modelName, array $searchableFields): string {
    if (empty($searchableFields)) {
        return "Search {$modelName} records";
    }
    
    $fieldList = implode(', ', $searchableFields);
    return "Search {$modelName} records by searching across: {$fieldList}";
}

private function generateSortExample(string $modelName): string {
    $examples = [
        'Movies' => 'name',
        'Users' => 'username',
        'Movie_Quotes' => 'quote',
        'Roles' => 'name'
    ];
    
    return $examples[$modelName] ?? 'id';
}

private function getModelFilterableFields(string $modelName): array {
    try {
        $model = $this->modelFactory->new($modelName);
        $metadata = $model->getMetadata();
        $filterableFields = [];
        
        foreach ($metadata['fields'] ?? [] as $fieldName => $fieldDef) {
            // Skip certain field types that aren't good for filtering
            if (in_array($fieldDef['type'], ['Image', 'Video', 'Password', 'BigText'])) {
                continue;
            }
            
            $filterableFields[$fieldName] = [
                'type' => $fieldDef['type'],
                'label' => $fieldDef['label'] ?? ucwords(str_replace('_', ' ', $fieldName))
            ];
        }
        
        return $filterableFields;
    } catch (Exception $e) {
        return [];
    }
}

private function generateFieldSchema(array $fieldInfo): array {
    switch ($fieldInfo['type']) {
        case 'Integer':
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

private function generateFieldExample(string $fieldName, array $fieldInfo): mixed {
    switch ($fieldInfo['type']) {
        case 'Integer':
            return $fieldName === 'release_year' ? 2024 : 1;
        case 'Boolean':
            return true;
        case 'Date':
        case 'DateTime':
            return '2024-01-01';
        default:
            $examples = [
                'name' => 'example name',
                'email' => 'user@example.com',
                'role' => 'admin',
                'status' => 'active'
            ];
            return $examples[$fieldName] ?? 'example';
    }
}
```

### Phase 3: Permission-Based Filtering (2-3 days)

#### Step 3.1: Implement Route Permission Checking

**Basic Route Filtering:**
```php
private function filterRoutesByPermissions(array $routes): array {
    $filteredRoutes = [];
    
    foreach ($routes as $path => $pathOperations) {
        $filteredOperations = [];
        
        foreach ($pathOperations as $method => $operation) {
            $route = $this->buildRouteFromPathAndMethod($path, $method);
            
            if ($this->permissionFilter->isRouteAccessibleToUsers($route)) {
                $filteredOperations[$method] = $operation;
            }
        }
        
        if (!empty($filteredOperations)) {
            $filteredRoutes[$path] = $filteredOperations;
        }
    }
    
    return $filteredRoutes;
}
```

**Relationship Route Permission Checking (Addresses Gap 5):**

Relationship routes require dual permission checking for both the primary model and the related model:

```php
// In OpenAPIPermissionFilter::isRouteAccessibleToUsers()
private function checkRelationshipRoutePermissions(array $route, Request $request, ModelBase $testUser): bool {
    $modelName = $request->get('modelName');
    $relationshipName = $request->get('relationshipName');
    
    // Determine related model from relationship metadata
    $relatedModelName = $this->getRelatedModelFromRelationship($modelName, $relationshipName);
    
    if (!$relatedModelName) {
        $this->logger->warning('Cannot determine related model for relationship route', [
            'model' => $modelName,
            'relationship' => $relationshipName,
            'route' => $route['path']
        ]);
        return false; // NO FALLBACK - exclude route if we can't determine permissions
    }
    
    // Determine required permissions based on HTTP method and route pattern
    [$primaryAction, $relatedAction] = $this->determineRelationshipActions($route);
    
    // Create test requests for both models
    $primaryRequest = $this->createModelTestRequest($modelName, $primaryAction);
    $relatedRequest = $this->createModelTestRequest($relatedModelName, $relatedAction);
    
    // Check permissions for BOTH models
    $hasPrimaryPermission = $this->authorizationService->hasPermissionForRoute(
        ['apiClass' => 'Gravitycar\\Api\\ModelBaseAPIController'],
        $primaryRequest,
        $testUser
    );
    
    $hasRelatedPermission = $this->authorizationService->hasPermissionForRoute(
        ['apiClass' => 'Gravitycar\\Api\\ModelBaseAPIController'],
        $relatedRequest,
        $testUser
    );
    
    $this->logger->debug('Relationship route permission check', [
        'primary_model' => $modelName,
        'primary_action' => $primaryAction,
        'primary_permission' => $hasPrimaryPermission,
        'related_model' => $relatedModelName,
        'related_action' => $relatedAction,
        'related_permission' => $hasRelatedPermission,
        'route_accessible' => $hasPrimaryPermission && $hasRelatedPermission
    ]);
    
    // Both permissions must be granted - NO FALLBACKS
    return $hasPrimaryPermission && $hasRelatedPermission;
}

private function determineRelationshipActions(array $route): array {
    $method = $route['method'];
    $path = $route['path'];
    
    // Pattern: /{model}/{id}/link/{relationshipName}
    if (preg_match('#/[^/]+/[^/]+/link/[^/]+$#', $path)) {
        if ($method === 'GET') {
            return ['read', 'list']; // listRelated
        }
        if ($method === 'POST') {
            return ['read', 'create']; // createAndLink
        }
    }
    
    // Pattern: /{model}/{id}/link/{relationshipName}/{idToLink}
    if (preg_match('#/[^/]+/[^/]+/link/[^/]+/[^/]+$#', $path)) {
        if ($method === 'PUT') {
            return ['update', 'read']; // link
        }
        if ($method === 'DELETE') {
            return ['update', 'read']; // unlink
        }
    }
    
    // Should never reach here for relationship routes
    return ['read', 'read']; // Safe default
}

private function getRelatedModelFromRelationship(string $modelName, string $relationshipName): ?string {
    try {
        $model = $this->modelFactory->new($modelName);
        $relationshipMetadata = $model->getRelationshipMetadata($relationshipName);
        
        // Extract related model name from relationship metadata
        // OneToMany: 'modelMany', ManyToMany: 'modelB', OneToOne: 'relatedModel'
        $relatedModel = $relationshipMetadata['modelMany'] 
                     ?? $relationshipMetadata['modelB'] 
                     ?? $relationshipMetadata['relatedModel']
                     ?? null;
        
        return $relatedModel;
        
    } catch (Exception $e) {
        $this->logger->error('Failed to get related model from relationship', [
            'model' => $modelName,
            'relationship' => $relationshipName,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}
```

**Permission Requirements by Route Type:**
- **listRelated** (`GET /{model}/{id}/link/{relationshipName}`): Primary `read` + Related `list`
- **createAndLink** (`POST /{model}/{id}/link/{relationshipName}`): Primary `read` + Related `create`
- **link** (`PUT /{model}/{id}/link/{relationshipName}/{idToLink}`): Primary `update` + Related `read`
- **unlink** (`DELETE /{model}/{id}/link/{relationshipName}/{idToLink}`): Primary `update` + Related `read`

#### Step 3.2: Create Test User for Permission Testing
```php
private function getTestUser(): ModelBase {
    static $testUser = null;
    
    if ($testUser === null) {
        // Retrieve jane@example.com user that has 'user' role
        $userModel = $this->modelFactory->new('Users');
        $testUser = $userModel->findByField('email', 'jane@example.com');
        
        if (!$testUser) {
            throw new \RuntimeException('Test user jane@example.com not found. Run setup.php to create test data.');
        }
    }
    
    return $testUser;
}
```

#### Step 3.3: Implement Request Object Creation for Permission Testing
```php
private function createTestRequest(array $route): Request {
    $requestData = [
        'REQUEST_METHOD' => $route['method'],
        'REQUEST_URI' => $route['path'],
        'SERVER_NAME' => 'localhost',
        'SERVER_PORT' => '8081'
    ];
    
    return new Request($requestData);
}
```

### Phase 4: Enhanced Documentation & Intent Metadata (3-4 days)

#### Step 4.1: Implement Intent Metadata Generation
```php
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
        'x-gravitycar-operation-type' => $this->getOperationType($operation),
        'x-gravitycar-examples' => $this->generateExamples($modelName, $operation)
    ];
}

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

private function generateExamples(string $modelName, string $operation): array {
    $examples = [];
    
    switch ($operation) {
        case 'list':
        case 'listDeleted':
            $examples = [
                [
                    'description' => "Find specific {$modelName} by name or title",
                    'parameters' => ['search' => $this->getExampleSearchTerm($modelName)]
                ],
                [
                    'description' => 'Get paginated results',
                    'parameters' => ['page' => 2, 'pageSize' => 20]
                ]
            ];
            break;
        case 'retrieve':
        case 'update':
        case 'delete':
        case 'restore':
            $examples = [
                [
                    'description' => "Get specific {$modelName} record",
                    'parameters' => ['id' => $this->getExampleId($modelName)]
                ]
            ];
            break;
        case 'listRelated':
            $examples = [
                [
                    'description' => "Get related records for a specific {$modelName}",
                    'parameters' => [
                        'id' => $this->getExampleId($modelName),
                        'relationshipName' => $this->generateRelationshipExample($modelName)
                    ]
                ]
            ];
            break;
        case 'createAndLink':
            $examples = [
                [
                    'description' => "Create new related record and link to {$modelName}",
                    'parameters' => [
                        'id' => $this->getExampleId($modelName),
                        'relationshipName' => $this->generateRelationshipExample($modelName)
                    ],
                    'body' => $this->generateExampleRequestBody($modelName, $operation)
                ]
            ];
            break;
        case 'link':
        case 'unlink':
            $examples = [
                [
                    'description' => "{$operation} records via relationship",
                    'parameters' => [
                        'id' => $this->getExampleId($modelName),
                        'relationshipName' => $this->generateRelationshipExample($modelName),
                        'idToLink' => $this->getExampleId('Related')
                    ]
                ]
            ];
            break;
    }
    
    return $examples;
}
```

#### Step 4.2: Enhance Response Schema Documentation
```php
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
        case 'listDeleted':
            // No additional responses for listDeleted
            break;
    }
    
    return $responses;
}

private function generateSuccessResponse(string $modelName, string $operation): array {
    switch ($operation) {
        case 'list':
        case 'listDeleted':
        case 'listRelated':
            return [
                'description' => "List of {$modelName} records" . ($operation === 'listDeleted' ? ' (deleted)' : '') . ($operation === 'listRelated' ? ' (related)' : ''),
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
                                'pagination' => ['$ref' => '#/components/schemas/Pagination']
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
```

#### Step 4.3: Implement Model-Specific Examples (Addresses Gap 7)

**Strategy**: Use real database records via `ModelBase::find()` for realistic examples, with synthetic fallbacks.

**Fetch Real Data from Database:**
```php
private function getModelExampleData(string $modelName, int $limit = 3): array {
    try {
        $model = $this->modelFactory->new($modelName);
        
        // Fetch first few records for examples
        $records = $model->find([], ['limit' => $limit]);
        
        if (empty($records)) {
            $this->logger->info('No example data found in database for model', [
                'model' => $modelName
            ]);
            return [];
        }
        
        // Convert model instances to arrays for OpenAPI examples
        return array_map(function($record) {
            return $record->toArray();
        }, $records);
        
    } catch (Exception $e) {
        $this->logger->warning('Failed to fetch example data for model', [
            'model' => $modelName,
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

private function generateExampleRequestBody(string $modelName, string $operation): array {
    // First try to get real data from database
    $exampleRecords = $this->getModelExampleData($modelName, 1);
    
    if (!empty($exampleRecords)) {
        $realData = $exampleRecords[0];
        
        // Filter based on operation type
        if ($operation === 'create' || $operation === 'createAndLink') {
            // Remove audit fields for create operations
            $auditFields = ['id', 'created_at', 'updated_at', 'deleted_at', 
                           'created_by', 'updated_by', 'deleted_by',
                           'created_by_name', 'updated_by_name', 'deleted_by_name'];
            
            return array_diff_key($realData, array_flip($auditFields));
        }
        
        if ($operation === 'update') {
            // Keep id for update, remove other audit fields
            $auditFields = ['created_at', 'updated_at', 'deleted_at', 
                           'created_by', 'updated_by', 'deleted_by',
                           'created_by_name', 'updated_by_name', 'deleted_by_name'];
            
            return array_diff_key($realData, array_flip($auditFields));
        }
        
        return $realData;
    }
    
    // Fallback to synthetic examples if no data exists
    return $this->generateSyntheticExampleRequestBody($modelName, $operation);
}

private function generateExamples(string $modelName, string $operation): array {
    $examples = [];
    $realData = $this->getModelExampleData($modelName, 3);
    
    switch ($operation) {
        case 'list':
        case 'listDeleted':
            if (!empty($realData)) {
                // Use first record's searchable field value as search example
                $searchableFields = $this->getModelSearchableFields($modelName);
                if (!empty($searchableFields) && isset($realData[0][$searchableFields[0]])) {
                    $searchValue = $realData[0][$searchableFields[0]];
                    $examples[] = [
                        'description' => "Find {$modelName} by {$searchableFields[0]}",
                        'parameters' => ['search' => $searchValue]
                    ];
                }
            } else {
                // Synthetic search example
                $examples[] = [
                    'description' => "Find specific {$modelName} records",
                    'parameters' => ['search' => $this->getExampleSearchTerm($modelName)]
                ];
            }
            
            $examples[] = [
                'description' => 'Get paginated results',
                'parameters' => ['page' => 2, 'pageSize' => 20]
            ];
            break;
            
        case 'retrieve':
        case 'update':
        case 'delete':
        case 'restore':
            if (!empty($realData)) {
                // Use real record ID
                $examples[] = [
                    'description' => "Get specific {$modelName} record",
                    'parameters' => ['id' => $realData[0]['id']]
                ];
            } else {
                // Synthetic ID example
                $examples[] = [
                    'description' => "Get specific {$modelName} record",
                    'parameters' => ['id' => $this->getExampleId($modelName)]
                ];
            }
            break;
            
        case 'create':
        case 'createAndLink':
            if (!empty($realData)) {
                // Use real data structure
                $exampleBody = $this->generateExampleRequestBody($modelName, $operation);
                
                $examples[] = [
                    'description' => "Create new {$modelName} with realistic data",
                    'body' => $exampleBody
                ];
            }
            break;
    }
    
    return $examples;
}
```

**Synthetic Fallback Examples:**
```php
private function getExampleSearchTerm(string $modelName): string {
    $examples = [
        'Movies' => 'Star Wars',
        'Users' => 'john',
        'Books' => 'Harry Potter',
        'Roles' => 'admin'
    ];
    
    return $examples[$modelName] ?? strtolower($modelName);
}

private function getExampleId(string $modelName): string {
    // Generate consistent example UUIDs for each model
    $examples = [
        'Movies' => '123e4567-e89b-12d3-a456-426614174000',
        'Users' => '234e5678-e89b-12d3-a456-426614174001',
        'Books' => '345e6789-e89b-12d3-a456-426614174002'
    ];
    
    return $examples[$modelName] ?? '456e7890-e89b-12d3-a456-426614174999';
}

private function generateSyntheticExampleRequestBody(string $modelName, string $operation): array {
    // Use existing generateExampleRequestBody() logic from Phase 2
    // This is the fallback when no real data exists
}
```

**Benefits:**
- Examples reflect actual data structures and values from the system
- AI tools get realistic context for API usage
- No manual example maintenance required
- Graceful degradation when database is empty

### Phase 5: Integration & Testing (2-3 days)

#### Step 5.1: Update OpenAPIGenerator Integration
- Modify `generatePaths()` to use new explicit route generation
- Integrate permission filtering

#### Step 5.2: Create Comprehensive Tests
```php
class OpenAPIEnhancementsTest extends TestCase {
    public function testExplicitModelRoutesGenerated(): void;
    public function testPermissionFilteringWorks(): void;
    public function testNaturalLanguageDescriptions(): void;
    public function testIntentMetadataGeneration(): void;
    public function testParameterDocumentation(): void;
    public function testEnhancedResponseSchemas(): void;
}
```

#### Step 5.3: Performance Testing
- Verify caching performance with new route generation
- Test memory usage with large numbers of models
- Benchmark generation time vs. current implementation

## Testing Strategy

### Unit Tests

1. **OpenAPIPermissionFilter Tests**
   - Test user role checking
   - Test permission caching
   - Test edge cases for unknown routes

2. **OpenAPIModelRouteBuilder Tests**
   - Test route generation for different models
   - Test description generation
   - Test parameter documentation
   - Test intent metadata generation

3. **Enhanced OpenAPIGenerator Tests**
   - Test explicit route generation
   - Test permission filtering integration
   - Test caching with new structure

### Integration Tests

1. **End-to-End OpenAPI Generation**
   - Test complete specification generation
   - Verify all models are included
   - Verify permission filtering works correctly

2. **Permission Integration Tests**
   - Test with different user roles
   - Test with various model permissions
   - Test edge cases for restricted models

3. **API Response Tests**
   - Test `/openapi.json` endpoint returns enhanced specification
   - Verify JSON schema validity
   - Test caching behavior

### Feature Tests

1. **AI Tool Compatibility Tests**
   - Validate OpenAPI spec with Swagger tools
   - Test with sample MCP server integration
   - Verify intent metadata is properly formatted

2. **Performance Tests**
   - Benchmark generation time vs. current implementation
   - Test memory usage with many models
   - Verify caching effectiveness

## Documentation

### User Documentation

1. **API Documentation Guide**
   - How to interpret the enhanced OpenAPI documentation
   - Understanding intent metadata
   - Using examples for API integration

2. **Developer Guide**
   - Extending the OpenAPI generation
   - Adding custom intent metadata
   - Customizing model descriptions

### Technical Documentation

1. **Architecture Documentation**
   - New service interactions
   - Permission filtering flow
   - Integration with existing DocumentationCache

2. **API Reference**
   - Custom extension format (`x-gravitycar-*`)
   - Troubleshooting guide
   - No additional configuration required (uses existing OpenAPI info, base URL from system config)

## Risks and Mitigations

### Risk 1: Performance Impact
**Risk**: Generating explicit routes for many models could slow down OpenAPI generation.

**Mitigation**: 
- Generate routes lazily only for models with proper permissions
- Existing DocumentationCache handles spec caching efficiently
- Performance monitoring during implementation phase

### Risk 2: Permission System Complexity
**Risk**: Complex permission rules might make route filtering unreliable.

**Mitigation**:
- Start with simple user role testing (jane@example.com)
- Add comprehensive test coverage for edge cases
- Add detailed logging for permission decisions

### Risk 3: Maintenance Overhead
**Risk**: Natural language descriptions might become outdated.

**Mitigation**:
- Generate descriptions from metadata when possible
- Create template system for easy updates
- Add validation to detect missing or poor descriptions
- Document maintenance procedures

## Identified Gaps

### Gap 1: Model Metadata Completeness ✓
**Issue**: Some models might lack sufficient metadata for rich documentation generation.

**Resolution**: Existing model metadata is comprehensive and sufficient.
- ✓ All models have field labels, types, validation rules, and constraints
- ✓ Implementation plan includes robust fallback mechanisms:
  - `generateNaturalLanguageDescription()` for operation descriptions
  - `getFallbackExampleRequestBody()` for synthetic examples when metadata is unavailable
  - `generateFieldExampleValue()` with field-type-specific example generation
  - Field-name-based intelligent defaults (e.g., 'release_year' → 2024, 'email' → contextual email)
- ✓ Metadata-driven approach extracts all available information automatically
- ✓ No models identified with insufficient metadata for OpenAPI generation

### Gap 2: Controller Permission Coverage
**Issue**: All API controllers inherit default `$rolesAndActions` from `ApiControllerBase` (full permissions for all roles), but some controllers may need more restrictive permissions.

**Current State**:
- `ApiControllerBase` defines default `$rolesAndActions` property with `['admin' => ['*'], 'manager' => ['*'], 'user' => ['*'], 'guest' => ['*']]`
- All controllers inherit this via `getRolesAndActions()` method
- Controllers can override `$rolesAndActions` to customize permissions (e.g., `AuthController` does this)
- `PermissionsBuilder::buildPermissionsForController()` creates permission records for all controllers during setup
- `AuthorizationService::determineComponent()` uses controller class name as component for non-model routes
- `AuthorizationService::checkDatabasePermission()` queries Permissions table with controller class name as component
- **This system works correctly** - every route has permissions established

**Potential Concern**:
- Controllers with overly permissive defaults might expose functionality that should be restricted
- Example: `OpenAPIController` allows all roles including 'guest' to view API documentation
- Example: `MetadataAPIController` allows all roles to view system metadata

**Solution Options**:
1. **Accept current defaults** - If the default full permissions are appropriate for all controllers, no changes needed
2. **Override in specific controllers** - Controllers requiring restrictions can define custom `$rolesAndActions`:
   ```php
   // Example: Restrict OpenAPI to authenticated users only
   class OpenAPIController extends ApiControllerBase {
       protected array $rolesAndActions = [
           'admin' => ['read'],
           'manager' => ['read'],
           'user' => ['read'],
           'guest' => []  // No access
       ];
   }
   ```
3. **Review all controllers** - Audit each controller to determine if default permissions are appropriate

**Resolution**: Wide-open default permissions are an **intentional design decision** for this implementation.
- ✓ OpenAPI and metadata endpoints benefit from wide accessibility for client integration
- ✓ Permission system works correctly - all controllers have proper RBAC in place
- ✓ Controllers can override `$rolesAndActions` if restrictions become necessary in the future
- ✓ This is a policy choice, not a technical gap - mark as resolved

### Gap 3: Custom Route Documentation ✓
**Issue**: Custom API controllers outside ModelBaseAPIController need a way to provide OpenAPI documentation (summaries, descriptions, operation IDs, tags).

**Solution**: Extend the existing `registerRoutes()` return structure with optional OpenAPI documentation fields.

**Implementation**: See Phase 1, Step 1.3 for complete code examples (applies to ALL routes via OpenAPIGenerator).

**Key Features:**
- Controllers add optional fields (`summary`, `description`, `operationId`, `tags`) to route definitions
- Auto-generation for missing fields (operationId from path, tags from first path segment)
- Warning logs for missing summaries (quality improvement)
- No changes required to existing controllers (backwards compatible)

**Benefits:**
- Single source of truth (documentation with route definition)
- Gradual enhancement (works without OpenAPI fields)
- No parallel systems (no separate interfaces/traits)
- Framework consistency (follows existing `registerRoutes()` pattern)

### Gap 4: Soft Delete Operations Documentation ✓
**Issue**: Soft delete operations (`listDeleted`, `restore`) need clear documentation to distinguish from regular operations.

**Solution**: Already implemented in the plan.
- ✓ Specific descriptions generated via `generateNaturalLanguageDescription()`
- ✓ Response codes match regular operations (`listDeleted` uses same as `list`, `restore` uses same as `update`)
- ✓ Intent metadata included: `x-gravitycar-intent` values `'search-deleted'` and `'restore'`
- ✓ Operation type metadata: `'read-collection'` for listDeleted, `'write-single'` for restore
- ✓ Parameters documented (listDeleted supports search, pagination, filtering like list)
- ✓ Routes properly mapped: `GET /{model}/deleted` and `PUT /{model}/{id}/restore`

### Gap 5: Relationship Route Permission Checking ✓
**Issue**: Relationship routes involve two models and need permission checking for both the primary model and the related model.

**Examples**: `GET /Movies/{id}/link/Movie_Quotes`, `POST /Movies/{id}/link/Movie_Quotes`, etc.

**Solution**: Dual permission checking for both models.

**Implementation**: See Phase 3, Step 3.1 for complete code examples.

**Permission Requirements by Route Type:**
- **listRelated** (`GET /{model}/{id}/link/{relationshipName}`): Primary `read` + Related `list`
- **createAndLink** (`POST /{model}/{id}/link/{relationshipName}`): Primary `read` + Related `create`
- **link** (`PUT /{model}/{id}/link/{relationshipName}/{idToLink}`): Primary `update` + Related `read`
- **unlink** (`DELETE /{model}/{id}/link/{relationshipName}/{idToLink}`): Primary `update` + Related `read`

**NO FALLBACKS Rule:**
- Route excluded if related model cannot be determined
- Route excluded if either permission check fails
- Route excluded if relationship metadata is missing
- All exclusions logged for debugging

**Testing Strategy:**
- Test with user having both permissions (included)
- Test with user missing primary permission (excluded)
- Test with user missing related permission (excluded)
- Test with invalid relationship names (excluded with warning)

### Gap 6: Dynamic Parameter Discovery ✓
**Issue**: Different models have different searchable fields and filterable fields that should be documented in the OpenAPI spec.

**Solution**: Implemented in Phase 2 of the plan.
- ✓ `getModelSearchableFields()` uses `ModelFactory` and calls `getSearchableFields()` on model instances
- ✓ `getModelFilterableFields()` parses model metadata to identify filterable fields and their types
- ✓ `generateParameters()` generates field-specific parameter documentation with proper OpenAPI schemas
- ✓ `generateFieldSchema()` creates type-appropriate schemas (integer, string, boolean, date, etc.)
- ✓ `generateFieldExample()` creates model-specific examples based on actual field types and constraints
- ✓ Dynamic field filters added to list/listDeleted operations for each filterable field

### Gap 7: Example Data Quality ✓
**Issue**: Synthetic examples like `"Example Movie Title"` are less useful than real data.

**Solution**: Use real database records via `ModelBase::find()` with synthetic fallbacks.

**Implementation**: See Phase 4, Step 4.3 for complete code examples.

**Strategy:**
- Fetch up to 3 real records from database for each model
- Filter audit fields based on operation (create/update)
- Use real IDs, search terms, and field values in examples
- Graceful fallback to synthetic examples when database is empty

**Benefits:**
- Examples reflect actual data structures and values
- AI tools get realistic context for API usage
- No manual example maintenance required
- Developers see how the API actually behaves

**Edge Cases:**
- Empty database → Synthetic examples with INFO log
- Sensitive data → Exclude password/token fields
- Large payloads → Limit to 3 records max

**External API Routes:**
- Use related model data (e.g., Movies model for TMDB routes)
- Custom controllers can implement `getOpenAPIExamples()` method
- Non-model routes use minimal synthetic examples

## Success Criteria

1. **Functional Success**
   - All model routes explicitly documented instead of wildcards
   - Only user-accessible routes included in documentation
   - Rich descriptions and examples for all routes
   - Intent metadata properly formatted and useful

2. **Technical Success**
   - No significant performance degradation (<20% increase in generation time)
   - OpenAPI specification validates against OpenAPI 3.0.3 schema
   - Caching system maintains effectiveness

3. **User Experience Success**
   - AI tools can successfully parse and use the enhanced documentation
   - Human developers find the documentation more helpful
   - Examples are realistic and actionable
   - Intent metadata enables better tool integration

4. **Quality Success**
   - Comprehensive test coverage (>90%)
   - No regression in existing functionality
   - Clear documentation and maintenance procedures
   - Robust error handling and logging

## Error Handling Strategy

### Permission Check Failures

**Expected Behavior (Not Errors):**
When `AuthorizationService::hasPermissionForRoute()` returns `false`, this indicates the test user lacks permission for the route. This is **expected behavior**, not a failure condition.

**Action**: Silently exclude the route from OpenAPI documentation and continue generation.

```php
// In OpenAPIPermissionFilter::isRouteAccessibleToUsers()
public function isRouteAccessibleToUsers(array $route): bool {
    try {
        $testUser = $this->getTestUser();
        $request = $this->createTestRequest($route);
        
        $hasPermission = $this->authorizationService->hasPermissionForRoute(
            $route,
            $request,
            $testUser
        );
        
        if (!$hasPermission) {
            // This is EXPECTED - user doesn't have permission
            $this->logger->debug('Route excluded from documentation - user lacks permission', [
                'route' => $route['path'] ?? 'unknown',
                'method' => $route['method'] ?? 'unknown',
                'test_user' => $testUser->get('email')
            ]);
        }
        
        return $hasPermission;
        
    } catch (\Exception $e) {
        // This is an ERROR - something went wrong during permission checking
        $this->logger->error('Permission check failed with exception - aborting documentation generation', [
            'route' => $route['path'] ?? 'unknown',
            'method' => $route['method'] ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Re-throw to fail OpenAPI generation with 500 error
        throw new \RuntimeException(
            'OpenAPI generation failed during permission checking: ' . $e->getMessage(),
            500,
            $e
        );
    }
}
```

### Exception Handling Rules

1. **Permission Denied (false return)**: 
   - Log level: `DEBUG`
   - Action: Exclude route, continue generation
   - HTTP response: 200 OK with reduced route set

2. **Exception During Permission Check**:
   - Log level: `ERROR` 
   - Action: Abort documentation generation
   - HTTP response: 500 Internal Server Error
   - Include: Error message, route being checked, stack trace

3. **Missing Test User**:
   - Log level: `ERROR`
   - Action: Abort documentation generation  
   - HTTP response: 500 Internal Server Error
   - Message: "Test user jane@example.com not found. Run setup.php to create test data."

4. **Model Not Found in Route**:
   - Log level: `WARNING`
   - Action: Exclude route, continue generation
   - Used for: Routes with invalid modelName parameters

### Error Propagation

```php
// In OpenAPIGenerator::generate()
public function generate(): array {
    try {
        $this->logger->info('Starting OpenAPI specification generation');
        
        // Generate paths with permission filtering
        $paths = $this->generatePaths();
        
        $spec = [
            'openapi' => '3.0.3',
            'info' => $this->generateInfo(),
            'paths' => $paths,
            'components' => $this->generateComponents()
        ];
        
        $this->logger->info('OpenAPI specification generated successfully', [
            'routes_count' => count($paths)
        ]);
        
        return $spec;
        
    } catch (\Exception $e) {
        $this->logger->error('OpenAPI generation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Re-throw for controller to handle with 500 response
        throw $e;
    }
}
```

### Logging Standards

- **DEBUG**: Route excluded due to permissions (expected)
- **INFO**: Generation started, generation completed, auto-generated fields
- **WARNING**: Route excluded due to invalid data, missing relationship metadata
- **ERROR**: Exceptions during generation, missing test user, database errors

## Future Considerations

1. **Dynamic Schema Generation**
   - Generate request/response schemas from field metadata
   - Add validation rules to OpenAPI schemas
   - Support for conditional fields and dynamic validation

2. **API Versioning Support**
   - Document multiple API versions
   - Version-specific route filtering
   - Deprecation warnings in documentation

3. **Interactive Documentation**
   - Integration with Swagger UI
   - Live example execution
   - Real-time schema validation

4. **Internationalization**
   - Multi-language descriptions
   - Localized examples
   - Cultural considerations for AI tools

This implementation plan provides a comprehensive roadmap for enhancing the Gravitycar Framework's OpenAPI documentation while maintaining system stability and performance.