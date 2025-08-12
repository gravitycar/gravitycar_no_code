# API Scoring System Implementation Plan

## Feature Overview

The API Scoring System is an advanced routing mechanism that expands the existing API routing logic in the Gravitycar Framework. This feature introduces intelligent path matching through a scoring algorithm that evaluates the similarity between incoming REST API requests and registered paths.

### Current State
The existing routing system in Gravitycar uses simple exact path matching. The `Router` class matches routes using a basic string comparison between the request path and registered routes, which limits flexibility and doesn't support wildcard matching or intelligent route selection.

### Proposed Enhancement
The API Scoring System introduces:
- **Wildcard Path Registration**: Allow paths to be registered with `?` wildcards that match any path component
- **Intelligent Path Scoring**: Use a weighted scoring algorithm to determine the best matching route
- **Position-Aware Matching**: Give higher scores to matches at the beginning of the path
- **Method-Specific Routing**: Only consider routes that match both HTTP method and path length

### Problems Solved
1. **Inflexible Routing**: Current exact matching prevents dynamic path handling
2. **Limited Path Patterns**: No support for parameterized routes (e.g., `/Users/{id}`)
3. **Route Conflicts**: No mechanism to resolve ambiguous routes intelligently
4. **Poor Developer Experience**: Requires exact path registration without flexibility

## Requirements

### Functional Requirements

#### FR1: Route Registration Enhancement
- **FR1.1**: ModelBase classes MUST be able to register API routes through metadata
- **FR1.2**: ModelBase classes MUST be able to register API routes through a `registerRoutes()` method
- **FR1.3**: APIControllerBase classes MUST register routes through their `registerRoutes()` method
- **FR1.4**: Route registration MUST include HTTP method, path pattern, API class, and API method
- **FR1.5**: Path patterns MUST support `?` wildcard characters for dynamic segments
- **FR1.6**: Route registration MAY include parameter names for path components to enable parameter extraction

#### FR2: Path Scoring Algorithm
- **FR2.1**: Scoring MUST only compare routes with matching HTTP method and path length
- **FR2.2**: Exact path component matches MUST score higher than wildcard matches
- **FR2.3**: Earlier path components MUST receive higher weight than later components
- **FR2.4**: The scoring formula MUST be: `((pathLength - component_index) * (2 for exact match, 1 for wildcard, 0 for no match))`
- **FR2.5**: The highest-scoring route MUST be selected for request handling

#### FR3: Route Matching and Selection
- **FR3.1**: System MUST group registered routes by HTTP method and path length
- **FR3.2**: System MUST calculate scores for all matching routes
- **FR3.3**: System MUST select the route with the highest score
- **FR3.4**: System MUST handle ties by selecting the first registered route (deterministic behavior)

#### FR4: Backwards Compatibility
- **FR4.1**: Existing exact path matching MUST continue to work
- **FR4.2**: Current APIControllerBase implementations MUST not require changes
- **FR4.3**: Existing route cache mechanism MUST be preserved

### Non-Functional Requirements

#### NFR1: Performance
- **NFR1.1**: Route scoring MUST complete within 10ms for up to 1000 registered routes
- **NFR1.2**: Route grouping MUST be optimized for O(1) lookup by method and path length
- **NFR1.3**: Route cache MUST be maintained for performance

#### NFR2: Maintainability
- **NFR2.1**: Scoring logic MUST be encapsulated in dedicated classes
- **NFR2.2**: Code MUST include comprehensive PHPDoc documentation
- **NFR2.3**: Implementation MUST follow existing Gravitycar coding standards

#### NFR3: Testability
- **NFR3.1**: All scoring logic MUST be unit testable
- **NFR3.2**: Route registration MUST be integration testable
- **NFR3.3**: End-to-end API request handling MUST be testable

## Design

### High-Level Architecture

The API Scoring System extends the existing routing architecture with new components:

```
Incoming Request
       ↓
    Router
       ↓
APIRouteRegistry → APIPathScorer → Selected Route
       ↓                ↓
   Route Cache    Scoring Algorithm
```

### Component Design

#### 1. Enhanced APIRouteRegistry
**Responsibility**: Discover, register, and organize routes by method and path length

**Key Changes**:
- Group routes by HTTP method and path length for efficient scoring
- Support both exact and wildcard path patterns
- Maintain backwards compatibility with existing registration
- **Programmatically resolve fully qualified class names** using existing naming conventions
- **Validate route format and throw GCException for invalid routes**

**New Methods**:
- `getRoutesByMethodAndLength(string $method, int $pathLength): array`
- `parsePathComponents(string $path): array`
- `getPathLength(string $path): int`
- `resolveControllerClassName(string $apiClass): string` - Converts short API class name to fully qualified class name
- `validateRouteFormat(array $route): void` - Validates route structure and throws GCException for invalid routes

**Controller Class Name Resolution Strategy**:
The system supports two resolution approaches to handle both model-based and standalone API controllers:

**1. Model-Based Convention Resolution** (Primary approach):
- **Pattern**: `Gravitycar\Models\{ModelName}\Api\{ApiClass}`
- **Algorithm**: 
  - Extract model name from API class (e.g., "UsersAPIController" → "Users")
  - Apply naming convention: `Gravitycar\Models\Users\Api\UsersAPIController`
  - Verify class exists using `class_exists()`

**2. Fully Qualified Class Name** (For non-model controllers):
- **Pattern**: Use the `apiClass` value as-is if it contains namespace separators (`\`)
- **Algorithm**: 
  - If `apiClass` contains `\`, treat as fully qualified class name
  - Verify class exists using `class_exists()`

**3. Fallback Discovery** (Last resort):
- Scan the registry of already-discovered controllers
- Match by class basename

**Resolution Algorithm**:
```php
function resolveControllerClassName(string $apiClass): string {
    // Case 1: Already fully qualified
    if (str_contains($apiClass, '\\')) {
        return class_exists($apiClass) ? $apiClass : null;
    }
    
    // Case 2: Model-based convention
    $modelName = str_replace('APIController', '', $apiClass);
    $conventionClass = "Gravitycar\\Models\\{$modelName}\\Api\\{$apiClass}";
    if (class_exists($conventionClass)) {
        return $conventionClass;
    }
    
    // Case 3: Fallback to discovered controllers registry
    return $this->findInDiscoveredControllers($apiClass);
}
```

**Examples**:
```php
// Model-based resolution
'UsersAPIController' → 'Gravitycar\Models\Users\Api\UsersAPIController'

// Fully qualified resolution  
'App\Controllers\AdminAPIController' → 'App\Controllers\AdminAPIController'

// Custom location resolution
'MyCompany\Services\EmailAPIController' → 'MyCompany\Services\EmailAPIController'
```

This hybrid approach provides maximum flexibility while maintaining convention-over-configuration for standard model-based controllers.

**Route Validation Strategy**:
The `validateRouteFormat()` method ensures all routes conform to the expected structure before registration:

**Validation Rules**:
- **Required Properties**: `method`, `path`, `apiClass`, `apiMethod` must all be present
- **Method Validation**: HTTP method must be one of GET, POST, PUT, DELETE, PATCH
- **Path Validation**: Path must start with `/` and contain only valid characters and `?` wildcards
- **Class Validation**: `apiClass` must be a non-empty string
- **Method Validation**: `apiMethod` must be a non-empty string and an existing method on the class named in apiClass
- **Parameter Names Validation**: If `parameterNames` is provided, its length must match the path component count

**Validation Implementation**:
```php
public function validateRouteFormat(array $route): void {
    $requiredFields = ['method', 'path', 'apiClass', 'apiMethod'];
    
    foreach ($requiredFields as $field) {
        if (!isset($route[$field]) || empty($route[$field])) {
            throw new GCException("Route missing required field: {$field}", ['route' => $route]);
        }
    }
    
    $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
    if (!in_array(strtoupper($route['method']), $validMethods)) {
        throw new GCException("Invalid HTTP method: {$route['method']}", ['route' => $route]);
    }
    
    if (!str_starts_with($route['path'], '/')) {
        throw new GCException("Route path must start with '/': {$route['path']}", ['route' => $route]);
    }
    
    // Resolve the fully qualified class name before method validation
    $resolvedClassName = $this->resolveControllerClassName($route['apiClass']);
    if (!$resolvedClassName) {
        throw new GCException("API class not found: {$route['apiClass']}", ['route' => $route]);
    }
    
    if (!method_exists($resolvedClassName, $route['apiMethod'])) {
        throw new GCException("API method '{$route['apiMethod']}' not found in class '{$resolvedClassName}'", ['route' => $route]);
    }
    
    // Validate parameter names if provided
    if (isset($route['parameterNames'])) {
        $pathComponents = $this->parsePathComponents($route['path']);
        if (count($route['parameterNames']) !== count($pathComponents)) {
            throw new GCException("Parameter names count must match path components count", [
                'parameterNames' => $route['parameterNames'],
                'pathComponents' => $pathComponents,
                'route' => $route
            ]);
        }
    }
}
```

#### 2. New APIPathScorer Class
**Responsibility**: Implement the scoring algorithm to find the best matching route

**Key Methods**:
- `scoreRoute(array $clientPathComponents, array $registeredPathComponents): int`
- `findBestMatch(string $method, string $path, array $routes): ?array`
- `calculateComponentScore(string $clientComponent, string $registeredComponent, int $position, int $pathLength): int`

#### 3. New Request Data Transfer Object
**Responsibility**: Handle parameter extraction and provide clean access to path parameters

**Key Methods**:
- `__construct(string $url, array $parameterNames, string $httpMethod)`
- `get(string $paramName): ?string`
- `has(string $paramName): bool`
- `all(): array`

**Parameter Extraction Logic**:
- Extract path components from URL
- Map components to parameter names based on position
- Store only named parameters (skip empty string names)
- Validate parameter count matches path component count

#### 4. Enhanced Router Class
**Responsibility**: Coordinate scoring and route selection

**Key Changes**:
- Replace simple `matchRoute()` with intelligent scoring
- Integrate with `APIPathScorer` for route selection
- Maintain backwards compatibility for exact matches

### Data Structures

#### Route Registration Format

**Model-Based API Controller Example**:
```php
[
    'method' => 'GET',
    'path' => '/Users/?',
    'pathComponents' => ['Users', '?'], // Parsed by APIRouteRegistry
    'pathLength' => 2, // Dynamically calculated by APIRouteRegistry from pathComponents count
    'parameterNames' => ['', 'userId'], // Optional: parameter names for each component
    'apiClass' => 'UsersAPIController', // Resolved to Gravitycar\Models\Users\Api\UsersAPIController
    'apiMethod' => 'read'
]
```

**Standalone API Controller Example**:
```php
[
    'method' => 'GET',
    'path' => '/Admin/system/status',
    'pathComponents' => ['Admin', 'system', 'status'], // Parsed by APIRouteRegistry
    'pathLength' => 3, // Dynamically calculated by APIRouteRegistry from pathComponents count
    'parameterNames' => ['', '', ''], // Optional: no parameter extraction needed
    'apiClass' => 'App\\Controllers\\AdminAPIController', // Fully qualified class name
    'apiMethod' => 'getSystemStatus'
]
```

**Multi-Parameter Example**:
```php
[
    'method' => 'GET',
    'path' => '/Users/?/orders/?',
    'pathComponents' => ['Users', '?', 'orders', '?'], // Parsed by APIRouteRegistry
    'pathLength' => 4, // Dynamically calculated by APIRouteRegistry from pathComponents count
    'parameterNames' => ['', 'userId', '', 'orderId'], // Extract userId and orderId
    'apiClass' => 'OrdersAPIController',
    'apiMethod' => 'getUserOrder'
]
```

**Note**: 
- The `pathLength` is automatically calculated by the `APIRouteRegistry` class based on the number of components in the `pathComponents` array. It should never be manually set during route registration.
- The `apiClass` can be either a simple class name (for model-based controllers) or a fully qualified class name (for standalone controllers).
- The fully qualified controller class name is resolved programmatically using the hybrid resolution strategy described above.
- The `parameterNames` array is optional and enables parameter extraction. Empty strings indicate components that should not be extracted. If provided, the array length must match the path component count.

#### Route Grouping Structure
```php
[
    'GET' => [
        2 => [ // path length
            'route1' => [...],
            'route2' => [...]
        ],
        3 => [
            'route3' => [...]
        ]
    ],
    'POST' => [...]
]
```

### Scoring Algorithm Details

#### Component Scoring Logic
1. **Exact Match**: Component matches exactly → Score = 2
2. **Wildcard Match**: Registered path has `?` → Score = 1  
3. **No Match**: Components don't match and no wildcard → Score = 0

#### Position Weighting
- Position weight = `pathLength - component_index`
- Earlier components get higher weights
- Final score = `position_weight * component_score`

#### Example Calculation
For client path `/Users/123` against registered path `/Users/?`:
- Component 0: "Users" vs "Users" → 2 * (2 - 0) = 4
- Component 1: "123" vs "?" → 1 * (2 - 1) = 1
- **Total Score**: 5

### Integration Points

#### 1. ModelBase Integration
Models will register routes through metadata definitions:

**ModelBase Class Structure**:
```php
// In ModelBase class - empty property for metadata population
protected array $apiRoutes = [];
```

**Metadata Definition Example** (`src/Models/users/Users/users_metadata.php`):
```php
return [
    // ... other metadata ...
    'apiRoutes' => [
        [
            'method' => 'GET',
            'path' => '/Users/?',
            'parameterNames' => ['', 'userId'], // Extract the user ID parameter
            'apiClass' => 'UsersAPIController', // Convention-based resolution
            'apiMethod' => 'read'
        ],
        [
            'method' => 'POST', 
            'path' => '/Users/batch/import',
            'apiClass' => 'MyCompany\\Services\\UserImportAPIController', // Fully qualified
            'apiMethod' => 'importUsers'
        ]
    ]
    // ... other metadata ...
];
```

**ModelBase registerRoutes() Implementation**:
```php
public function registerRoutes(): array {
    $routes = [];
    
    // Get routes from metadata (loaded into $this->metadata)
    if (isset($this->metadata['apiRoutes'])) {
        $routes = array_merge($routes, $this->metadata['apiRoutes']);
    }
    
    return $routes;
}
```

#### 2. APIControllerBase Integration
Controllers will continue using existing `registerRoutes()` method with enhanced path patterns and optional parameter extraction:

```php
public function registerRoutes(): array {
    return [
        [
            'method' => 'GET',
            'path' => '/Users',
            'apiClass' => 'UsersAPIController',
            'apiMethod' => 'list'
        ],
        [
            'method' => 'GET',
            'path' => '/Users/?',
            'parameterNames' => ['', 'userId'], // Extract user ID from wildcard
            'apiClass' => 'UsersAPIController',
            'apiMethod' => 'get'
        ],
        [
            'method' => 'POST',
            'path' => '/Users',
            'apiClass' => 'UsersAPIController',
            'apiMethod' => 'post'
        ],
        [
            'method' => 'PUT',
            'path' => '/Users/?',
            'parameterNames' => ['', 'userId'], // Extract user ID for updates
            'apiClass' => 'UsersAPIController',
            'apiMethod' => 'put'
        ],
        [
            'method' => 'DELETE',
            'path' => '/Users/?',
            'parameterNames' => ['', 'userId'], // Extract user ID for deletion
            'apiClass' => 'UsersAPIController',
            'apiMethod' => 'delete'
        ]
    ];
}

// Controller method examples
public function get(Request $request, array $params = []): array {
    $userId = $request->get('userId');
    return $this->userService->getUser($userId);
}

public function list(array $params = []): array {
    // No Request DTO needed for routes without parameter extraction
    return $this->userService->getAllUsers();
}
```

### Error Handling

#### Route Registration Errors
- Invalid path patterns (malformed wildcards)
- Duplicate route registrations
- Missing API class or method references
- Invalid route structure (handled by APIRouteRegistry::validateRouteFormat())
- Missing required route properties (method, path, apiClass, apiMethod)

#### Runtime Errors
- No matching routes found
- Multiple routes with identical scores
- API class or method not found

### Backwards Compatibility Strategy

1. **Existing Route Format**: Current exact path routes continue to work
2. **Fallback Mechanism**: If scoring fails, fall back to exact matching
3. **Gradual Migration**: New wildcard routes can be added incrementally
4. **Cache Compatibility**: Existing route cache structure preserved

This design ensures a smooth transition while providing powerful new routing capabilities.

## Implementation Steps

The implementation will be divided into several phases to ensure proper integration and testing at each stage.

### Phase 1: Core Scoring Infrastructure

#### Step 1.1: Create APIPathScorer Class
**Duration**: 2 days  
**Dependencies**: None  

Create the core scoring algorithm implementation:
- **File**: `src/Api/APIPathScorer.php`
- **Methods**:
  - `scoreRoute(array $clientPathComponents, array $registeredPathComponents): int`
  - `findBestMatch(string $method, string $path, array $routes): ?array`
  - `calculateComponentScore(string $clientComponent, string $registeredComponent, int $position, int $pathLength): int`
  - `parsePathComponents(string $path): array`

**Key Requirements**:
- Implement the exact scoring formula: `((pathLength - component_index) * (2 for exact match, 1 for wildcard, 0 for no match))`
- Handle wildcard matching with `?` character
- Include comprehensive PHPDoc documentation
- Add input validation and error handling
- Include debug logging before and after scoring calculations

#### Step 1.2: Create Request Data Transfer Object
**Duration**: 1 day  
**Dependencies**: None  

Create the Request DTO for parameter extraction:
- **File**: `src/Api/Request.php`
- **Methods**:
  - `__construct(string $url, array $parameterNames, string $httpMethod)`
  - `get(string $paramName): ?string`
  - `has(string $paramName): bool`
  - `all(): array`
  - `getMethod(): string`
  - `getUrl(): string`

**Key Requirements**:
- Validate parameter names array length matches path component count
- Extract only named parameters (skip empty string names)
- Throw GCException for parameter count mismatches
- Provide clean access methods for extracted parameters
- Include comprehensive PHPDoc documentation

**Implementation Example**:
```php
class Request {
    private array $extractedParameters = [];
    private string $url;
    private string $method;
    
    public function __construct(string $url, array $parameterNames, string $httpMethod) {
        $this->url = $url;
        $this->method = $httpMethod;
        
        $pathComponents = $this->parsePathComponents($url);
        
        if (count($parameterNames) !== count($pathComponents)) {
            throw new GCException("Parameter names count must match path components count", [
                'parameterNames' => $parameterNames,
                'pathComponents' => $pathComponents
            ]);
        }
        
        $this->extractParameters($pathComponents, $parameterNames);
    }
    
    public function get(string $paramName): ?string {
        return $this->extractedParameters[$paramName] ?? null;
    }
    
    private function extractParameters(array $pathComponents, array $parameterNames): void {
        for ($i = 0; $i < count($parameterNames); $i++) {
            if (!empty($parameterNames[$i])) {
                $this->extractedParameters[$parameterNames[$i]] = $pathComponents[$i];
            }
        }
    }
}
```

#### Step 1.3: Unit Tests for APIPathScorer and Request
**Duration**: 1 day  
**Dependencies**: Step 1.1, Step 1.2  

Create comprehensive unit tests:
- **Files**: 
  - `Tests/Unit/Api/APIPathScorerTest.php`
  - `Tests/Unit/Api/RequestTest.php`
- **Test Cases**:
  - Exact match scoring
  - Wildcard match scoring
  - Position weighting verification
  - Edge cases (empty paths, malformed paths)
  - Parameter extraction with named parameters
  - Request DTO validation and error handling

### Phase 2: Enhanced Route Registry

#### Step 2.1: Enhance APIRouteRegistry Class
**Duration**: 3 days  
**Dependencies**: Step 1.1  

Extend the existing `APIRouteRegistry` class:
- **File**: `src/Api/APIRouteRegistry.php`
- **New Methods**:
  - `getRoutesByMethodAndLength(string $method, int $pathLength): array`
  - `parsePathComponents(string $path): array`
  - `getPathLength(string $path): int`
  - `resolveControllerClassName(string $apiClass): string`
  - `groupRoutesByMethodAndLength(): array`
  - `validateRouteFormat(array $route): void`

**Key Changes**:
- Modify route storage structure to group by method and path length
- Implement hybrid controller class name resolution
- Add support for wildcard path parsing
- Add comprehensive route validation with GCException for invalid routes
- Maintain backwards compatibility with existing route format
- Update route caching to include new structure

#### Step 2.2: Update Route Registration Logic
**Duration**: 2 days  
**Dependencies**: Step 2.1  

Modify how routes are stored and retrieved:
- Update `discoverAndRegisterRoutes()` to use new grouping structure
- Implement controller class name resolution algorithm
- Add comprehensive route validation using `validateRouteFormat()` method
- Ensure cache compatibility with new structure

#### Step 2.3: Integration Tests for APIRouteRegistry
**Duration**: 1 day  
**Dependencies**: Step 2.2  

Create integration tests:
- **File**: `Tests/Integration/Api/APIRouteRegistryTest.php`
- **Test Cases**:
  - Route discovery and grouping
  - Controller class name resolution (both conventions)
  - Cache persistence and retrieval

### Phase 3: ModelBase Route Registration

#### Step 3.1: Add registerRoutes() Method to ModelBase
**Duration**: 2 days  
**Dependencies**: Step 2.1  

Update the `ModelBase` class:
- **File**: `src/models/ModelBase.php`
- **New Method**: `public function registerRoutes(): array`
- **Functionality**:
  - Check for `$apiRoutes` metadata property
  - Return routes from metadata (validation handled by APIRouteRegistry)
  - Return empty array if no routes defined in metadata

**Implementation Details**:
```php
public function registerRoutes(): array {
    $routes = [];
    
    // Get routes from metadata (loaded from metadata files into $this->metadata)
    if (isset($this->metadata['apiRoutes'])) {
        $routes = array_merge($routes, $this->metadata['apiRoutes']);
    }
    
    return $routes;
}
```

**Note**: The `$apiRoutes` property in ModelBase is initialized as an empty array. Route definitions are stored in the model's metadata file (e.g., `src/Models/users/Users/users_metadata.php`) under the `'apiRoutes'` key and loaded into `$this->metadata` during model initialization. Route validation is handled by the APIRouteRegistry class.

#### Step 3.2: Metadata Integration for API Routes
**Duration**: 1 day  
**Dependencies**: Step 3.1  

Update metadata handling and documentation:
- **Files**: Model metadata files (e.g., `src/Models/users/Users/users_metadata.php`)
- Add support for `apiRoutes` metadata property in model metadata files
- Ensure proper validation of route metadata structure in MetadataEngine
- Document metadata format and examples in model metadata files

**Metadata File Structure**:
```php
// Example: src/Models/users/Users/users_metadata.php
return [
    'tableName' => 'users',
    'fields' => [...],
    'relationships' => [...],
    'apiRoutes' => [
        [
            'method' => 'GET',
            'path' => '/Users/?',
            'apiClass' => 'UsersAPIController',
            'apiMethod' => 'read'
        ],
        // ... additional routes
    ],
    // ... other metadata
];
```

#### Step 3.3: Update APIRouteRegistry to Scan ModelBase Classes
**Duration**: 2 days  
**Dependencies**: Step 3.2  

Modify route discovery:
- **File**: `src/Api/APIRouteRegistry.php`
- Update `discoverAndRegisterRoutes()` to scan ModelBase classes
- Call `registerRoutes()` on discovered models
- Handle both APIControllerBase and ModelBase route registration
- Ensure proper error handling for models without routes

**Key Changes**:
```php
protected function discoverAndRegisterRoutes(): void {
    // Existing APIController discovery...
    $this->discoverAPIControllers();
    
    // New: ModelBase route discovery
    $this->discoverModelRoutes();
}

protected function discoverModelRoutes(): void {
    // Scan src/models directory for ModelBase subclasses
    // Instantiate models and call registerRoutes()
    // Validate all routes using validateRouteFormat()
    // Process and store routes using new format
}
```

### Phase 4: Router Integration

#### Step 4.1: Enhance Router Class
**Duration**: 2 days  
**Dependencies**: Phase 2, Step 1.1  

Update the `Router` class:
- **File**: `src/Api/Router.php`
- **Key Changes**:
  - Integrate `APIPathScorer` for route matching
  - Replace `matchRoute()` with intelligent scoring
  - Add fallback to exact matching for backwards compatibility
  - Update error handling for scoring failures

**New Logic**:
```php
public function route(string $method, string $path, array $params = []) {
    // Debug logging before scoring process
    $this->logger->debug("Starting route scoring process", [
        'method' => $method, 
        'path' => $path,
        'timestamp' => microtime(true)
    ]);
    
    // Try intelligent scoring first
    $scoredRoute = $this->findRouteByScoring($method, $path);
    if ($scoredRoute) {
        $this->logger->debug("Route scoring completed successfully", [
            'method' => $method,
            'path' => $path,
            'selectedRoute' => $scoredRoute,
            'timestamp' => microtime(true)
        ]);
        return $this->executeRoute($scoredRoute, $path, $method, $params);
    }
    
    // Fallback to exact matching
    $exactRoute = $this->findExactRoute($method, $path);
    if ($exactRoute) {
        $this->logger->debug("Fallback to exact matching successful", [
            'method' => $method,
            'path' => $path,
            'selectedRoute' => $exactRoute
        ]);
        return $this->executeRoute($exactRoute, $path, $method, $params);
    }
    
    $this->logger->debug("No matching route found", [
        'method' => $method,
        'path' => $path
    ]);
    throw new GCException("No matching route found");
}

private function executeRoute(array $route, string $path, string $method, array $params = []): mixed {
    $controller = $this->instantiateController($route['apiClass']);
    
    // Create Request DTO if route has parameter names defined
    if (isset($route['parameterNames']) && !empty(array_filter($route['parameterNames']))) {
        $request = new Request($path, $route['parameterNames'], $method);
        return $controller->{$route['apiMethod']}($request, $params);
    }
    
    // Legacy behavior for routes without parameter extraction
    return $controller->{$route['apiMethod']}($params);
}
```

#### Step 4.2: Controller Class Resolution Integration
**Duration**: 1 day  
**Dependencies**: Step 4.1  

Update controller instantiation:
- Use `APIRouteRegistry::resolveControllerClassName()` for class resolution
- Update error handling for missing classes
- Ensure proper dependency injection for controllers

### Phase 5: Testing and Validation

#### Step 5.1: End-to-End Integration Tests
**Duration**: 2 days  
**Dependencies**: Phase 4  

Create comprehensive integration tests:
- **File**: `Tests/Integration/Api/APIScoringIntegrationTest.php`
- **Test Scenarios**:
  - Model-based route registration and scoring
  - APIController-based route registration and scoring
  - Mixed route scenarios with scoring precedence

**Note**: This phase focuses on component integration testing. Full functional testing with HTTP requests will be addressed separately after web framework integration.

### Phase 6: Documentation and Examples

#### Step 6.1: Update API Documentation
**Duration**: 2 days  
**Dependencies**: Phase 5  

Update framework documentation:
- **Files**: 
  - `docs/api/APIScoring.md` (existing)
  - `docs/models/ModelRouteRegistration.md` (new)
  - `docs/api/RouterEnhancements.md` (new)
- Document new route registration patterns
- Provide migration guide from exact matching
- Include performance guidelines and best practices

#### Step 6.2: Create Example Implementations
**Duration**: 1 day  
**Dependencies**: Step 6.1  

Create working examples:
- **Files**:
  - `examples/api_scoring_demo.php`
  - `examples/model_route_registration.php`
- Demonstrate both model-based and standalone controller registration
- Show wildcard usage patterns
- Include performance optimization examples

**Total Estimated Duration**: 17 days (approximately 3.5 weeks)

**Critical Path**: Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5

This implementation plan ensures a systematic approach with proper testing and backwards compatibility at each phase.

## Testing Strategy

The testing strategy for the API Scoring System focuses on multiple levels of validation to ensure reliability, performance, and backwards compatibility.

### Unit Testing

#### Core Algorithm Testing
**Target**: `APIPathScorer` class  
**Coverage**: 100% line and branch coverage  

**Test Categories**:
1. **Scoring Algorithm Accuracy**
   - Exact component matching scores (value = 2)
   - Wildcard component matching scores (value = 1)
   - No-match component scores (value = 0)
   - Position weighting calculation verification
   - Complex path scoring scenarios

2. **Edge Cases**
   - Empty paths and path components
   - Paths with multiple consecutive wildcards
   - Paths with mixed exact and wildcard components
   - Invalid path formats
   - Extremely long paths (performance edge cases)



#### Route Registry Testing
**Target**: `APIRouteRegistry` enhancements  
**Coverage**: All new methods and modified functionality  

**Test Categories**:
1. **Controller Class Resolution**
   - Model-based convention resolution success cases
   - Fully qualified class name handling
   - Fallback discovery mechanism
   - Invalid class name handling

2. **Route Grouping and Storage**
   - Correct grouping by method and path length
   - Cache persistence and retrieval
   - Route format validation using validateRouteFormat()

#### ModelBase Integration Testing
**Target**: `ModelBase::registerRoutes()` method  
**Coverage**: All metadata and programmatic route registration scenarios  

**Test Categories**:
1. **Metadata Route Processing**
   - Routes from `$apiRoutes` metadata property
   - Routes from instance properties
   - Merged routes from multiple sources
   - Empty routes array when no metadata defined

2. **Route Format Validation**
   - Valid route structure validation (handled by APIRouteRegistry)
   - Invalid route rejection (handled by APIRouteRegistry)
   - GCException throwing for malformed routes

### Integration Testing

#### Router Integration
**Focus**: End-to-end request routing with scoring  

**Test Scenarios**:
1. **Scoring vs Exact Matching**
   - Requests that should use scoring
   - Requests that should fall back to exact matching
   - Mixed route environments (scored + exact)

2. **Controller Resolution and Execution**
   - Model-based controller instantiation
   - Standalone controller instantiation
   - Error handling for missing controllers/methods

#### Model-Controller Integration
**Focus**: ModelBase route registration flowing through to Router execution  

**Test Scenarios**:
1. **Model Route Discovery**
   - Automatic discovery of models with routes
   - Proper registration of metadata-defined routes
   - Integration with existing APIController discovery

2. **Route Execution Chain**
   - Request → Scoring → Controller Resolution → Method Execution
   - Parameter passing through the chain
   - Error propagation and handling

### Automated Testing Pipeline

#### Continuous Integration Requirements
1. **Pre-commit Testing**
   - Unit tests for all modified components
   - Code coverage threshold enforcement (90%+)
   - Static analysis and code quality checks

2. **Integration Testing**
   - Full integration test suite on pull requests

3. **Release Testing**
   - Complete test suite execution
   - Cross-environment compatibility testing

**Note**: Functional testing with real HTTP requests will be addressed in a separate testing phase after the API Scoring System is fully integrated into the web framework.

## Documentation

Comprehensive documentation is essential for successful adoption and maintenance of the API Scoring System.

### Technical Documentation

#### API Reference Documentation
**Target Audience**: Framework developers and contributors  

**Content**:
1. **Class Documentation**
   - `APIPathScorer` - Complete method documentation with examples
   - `APIRouteRegistry` enhancements - New methods and changed behavior
   - `ModelBase` route registration - Usage patterns and examples

2. **Algorithm Documentation**
   - Detailed scoring formula explanation
   - Component matching rules and examples
   - Performance characteristics and optimization tips

#### Internal Design Documentation
**Target Audience**: Framework maintainers  

**Content**:
1. **Architecture Decision Records (ADRs)**
   - Why scoring over alternative approaches
   - Controller class resolution strategy decisions
   - Backwards compatibility approach rationale

2. **Code Organization**
   - File structure and responsibilities
   - Class interaction diagrams
   - Data flow documentation

### User Documentation

#### Developer Guide
**Target Audience**: Application developers using Gravitycar  

**Content**:
1. **Route Registration Guide**
   - ModelBase route registration patterns
   - APIController route registration best practices
   - Wildcard usage guidelines and examples

2. **Migration Guide**
   - Migrating from exact matching to scoring
   - Identifying optimization opportunities
   - Common migration pitfalls and solutions

3. **Performance Guide**
   - Route organization for optimal performance
   - Caching strategies and considerations
   - Debugging routing issues with debug logging

#### API Documentation Updates
**Target Audience**: API consumers and integrators  

**Content**:
1. **Updated Endpoint Documentation**
   - New wildcard path support in API specifications
   - Parameter extraction from wildcard segments
   - Error response format documentation

2. **Integration Examples**
   - Sample API client code for wildcard endpoints
   - Testing strategies for dynamic routes
   - Best practices for API versioning with scoring

### Examples and Tutorials

#### Code Examples
**Files to Create**:
1. **`examples/api_scoring_demo.php`**
   - Complete working example of scoring system
   - Multiple route types and scoring scenarios
   - Debug logging examples for route scoring

2. **`examples/model_route_registration.php`**
   - ModelBase route registration patterns
   - Metadata vs programmatic registration
   - Integration with existing models

3. **`examples/advanced_routing_patterns.php`**
   - Complex wildcard usage
   - Mixed exact and scored routing
   - Performance optimization techniques

#### Tutorial Documentation
**Content Structure**:
1. **Getting Started Tutorial**
   - Setting up your first scored routes
   - Understanding scoring behavior
   - Testing and debugging routes

2. **Advanced Usage Tutorial**
   - Complex routing scenarios
   - Performance optimization
   - Custom controller architectures

3. **Migration Tutorial**
   - Step-by-step migration from exact matching
   - Validating migration success
   - Rollback procedures

### Documentation Standards

#### Writing Guidelines
1. **Clarity and Accessibility**
   - Clear, jargon-free explanations
   - Progressive complexity (basic to advanced)
   - Multiple learning styles (text, code, diagrams)

2. **Completeness**
   - All public APIs documented
   - All configuration options explained
   - All error scenarios covered

3. **Accuracy and Maintenance**
   - Code examples tested with CI/CD
   - Version-specific documentation
   - Regular review and update processes

## Risks and Mitigations

### Technical Risks

#### Risk 1: Performance Degradation
**Probability**: Medium  
**Impact**: High  

**Description**: The scoring algorithm could introduce significant latency compared to exact matching, especially with large route sets.

**Mitigation Strategies**:
1. **Algorithmic Optimization**
   - Implement early termination for impossible matches
   - Use efficient data structures for route grouping
   - Cache frequently accessed route groups

2. **Graceful Degradation**
   - Implement circuit breaker pattern for scoring failures
   - Fallback to exact matching when scoring failures occur
   - Debug logging before and after route scoring process

#### Risk 2: Complex Route Conflicts
**Probability**: Low  
**Impact**: Medium  

**Description**: Complex scoring scenarios could lead to unexpected route selection or ambiguous routing decisions.

**Mitigation Strategies**:
1. **Deterministic Behavior**
   - Well-defined tie-breaking rules
   - Consistent route ordering and selection
   - Comprehensive conflict detection tools

2. **Developer Tools**
   - Route debugging and analysis tools
   - Route conflict detection utilities
   - Clear documentation of scoring behavior

3. **Validation Systems**
   - Route registration validation
   - Automatic conflict detection during registration
   - Warning systems for ambiguous routes

### Operational Risks

#### Risk 3: Increased System Complexity
**Probability**: High  
**Impact**: Medium  

**Description**: The scoring system adds complexity that could make debugging and maintenance more difficult.

**Mitigation Strategies**:
1. **Comprehensive Documentation**
   - Clear architectural documentation
   - Troubleshooting guides and common issues
   - Internal training for development team

2. **Debugging Tools**
   - Route scoring visualization tools
   - Detailed debug logging for route selection decisions
   - Development mode with enhanced debugging output

3. **Modular Design**
   - Clear separation of concerns between components
   - Well-defined interfaces and responsibilities
   - Independent testing of scoring components

### Business Risks

#### Risk 4: Maintenance Overhead
**Probability**: Medium  
**Impact**: Low  

**Description**: The additional complexity could increase long-term maintenance costs and effort.

**Mitigation Strategies**:
1. **Quality Implementation**
   - Comprehensive test coverage to reduce bugs
   - Clean, well-documented code for easier maintenance
   - Automated testing and quality assurance processes

2. **Knowledge Management**
   - Thorough documentation of design decisions
   - Knowledge transfer processes for team changes
   - Regular code review and knowledge sharing

3. **Community Contribution**
   - Open source community involvement in maintenance
   - Distributed knowledge and contribution base
   - Regular maintenance and improvement cycles

This comprehensive risk analysis ensures proactive identification and mitigation of potential issues throughout the implementation and deployment of the API Scoring System.
