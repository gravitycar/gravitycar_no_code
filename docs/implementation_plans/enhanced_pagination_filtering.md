# Enhanced Pagination & Filtering System Implementation Plan

## 1. Feature Overview

This plan focuses on implementing advanced pagination and filtering capabilities for the Gravitycar Framework's REST API to provide React-friendly data management. The system will enhance the existing basic list functionality with comprehensive search, filtering, sorting, and pagination specifically designed for popular React data fetching libraries like TanStack Query, SWR, Apollo Client, and React data grid components.

## 2. Current State Assessment

**Current State**: Basic limit/offset pagination exists in DatabaseConnector but lacks React-friendly features
**Impact**: Essential for React data grids, tables, lists, and modern data management patterns
**Priority**: HIGH - Week 1-2 implementation

### 2.1 Existing Capabilities (TO BE REPLACED)
- **Basic pagination in DatabaseConnector**: `applyQueryParameters()` method supports `limit` and `offset`
- **Basic count functionality**: `getCount()` method for specific field/value combinations
- **Simple record retrieval**: Basic `find()` method with criteria
- **Relationship listing**: LIMITED join capabilities for RelatedRecord fields
- **Basic sorting**: `orderBy` parameter support in `applyQueryParameters()`

### 2.2 Current DatabaseConnector Pagination Implementation (TO BE REPLACED)
The existing implementation has these methods that will be **completely replaced**:
- `applyQueryParameters()` - Currently handles basic limit/offset and orderBy
- `getCount()` - Basic count for single field/value, not suitable for complex filtering
- Basic parameter structure: `['limit' => int, 'offset' => int, 'orderBy' => [field => direction]]`

### 2.3 Missing Features (TO BE IMPLEMENTED)
- **React-compatible query parameter parsing** (multiple formats)
- **Advanced filtering system** with multiple operators
- **Multi-field search functionality** across configurable fields
- **React ecosystem compatibility** (TanStack Query, SWR, AG-Grid, etc.)
- **Comprehensive pagination metadata** for React UI components
- **Dynamic filter building** from various query parameter formats
- **Cursor-based pagination** for infinite scroll
- **Response formatting** for different React libraries

## 3. Requirements

### 3.1 Functional Requirements

#### 3.1.1 React Ecosystem Compatibility
- **TanStack Query (React Query)** compatible response format
- **SWR** library compatible pagination and caching keys
- **Apollo Client** REST link compatible structure
- **React Data Grid** libraries (AG-Grid, React Table, MUI DataGrid) support
- **Ant Design Table** and **Material-UI DataGrid** compatibility

#### 3.1.2 Pagination Requirements
- **Offset-based pagination** for traditional page navigation
- **Cursor-based pagination** for infinite scroll and real-time data
- **Server-side pagination** with total count and metadata
- **Configurable page sizes** with reasonable defaults and limits

#### 3.1.3 Search & Filtering Requirements
- **Global search** across multiple configurable fields
- **Field-specific filtering** with multiple operators:
  - String: `equals`, `contains`, `startsWith`, `endsWith`, `in`, `notIn`
  - Numeric: `equals`, `greaterThan`, `lessThan`, `between`, `in`, `notIn`
  - Date: `equals`, `before`, `after`, `between`
  - Boolean: `equals`, `isNull`, `isNotNull`
  - Enum: `equals`, `in`, `notIn`
- **Multiple simultaneous filters** with AND/OR logic
- **Filter presets** and saved filter configurations

#### 3.1.4 Sorting Requirements
- **Multi-field sorting** with priority order
- **Configurable sortable fields** per model
- **Default sorting** with model-specific defaults
- **React-friendly sort format** compatible with data grid libraries

### 3.2 Non-Functional Requirements
- Backward compatibility with existing `list()` endpoint
- Efficient database queries with proper indexing
- Configurable per-model search/filter/sort fields
- Memory-efficient processing for large datasets
- Response time under 500ms for typical queries
- SQL injection prevention through parameterized queries

## 4. React Data Fetching Patterns & Query Format

### 4.1 React Query Parameter Standards

Based on popular React data fetching libraries and UI component patterns, the API will support these standardized query parameter formats:

#### 4.1.1 TanStack Query (React Query) Pattern
```javascript
// React Query usage pattern
const useUsers = (filters) => {
  return useQuery({
    queryKey: ['users', filters],
    queryFn: () => fetchUsers({
      page: filters.page,
      pageSize: filters.pageSize,
      search: filters.search,
      filters: filters.filters,
      sortBy: filters.sortBy,
      sortOrder: filters.sortOrder
    })
  });
};

// Generated URL:
// GET /Users?page=1&pageSize=20&search=john&filters[role]=admin&sortBy=created_at&sortOrder=desc
```

#### 4.1.2 AG-Grid / React Table Pattern
```javascript
// AG-Grid server-side data source
const dataSource = {
  getRows: (params) => {
    const request = {
      startRow: params.startRow,      // offset
      endRow: params.endRow,          // limit calculation
      filterModel: params.filterModel, // complex filters
      sortModel: params.sortModel     // multi-field sorting
    };
    
    // Generated URL:
    // GET /Users?startRow=0&endRow=100&filters[name][type]=contains&filters[name][filter]=john&sort[0][colId]=name&sort[0][sort]=asc
  }
};
```

#### 4.1.3 Material-UI DataGrid Pattern
```javascript
// MUI DataGrid server-side pagination
const handleDataRequest = (params) => {
  const query = {
    page: params.page,
    pageSize: params.pageSize,
    filterModel: JSON.stringify(params.filterModel),
    sortModel: JSON.stringify(params.sortModel)
  };
  
  // Generated URL:
  // GET /Users?page=0&pageSize=25&filterModel={"items":[{"field":"role","operator":"equals","value":"admin"}]}&sortModel=[{"field":"created_at","sort":"desc"}]
};
```

### 4.2 Unified Query Parameter Format

To support all major React patterns, the API will accept multiple query parameter formats:

#### 4.2.1 Simple Format (TanStack Query / SWR)
```
GET /Users?page=1&pageSize=20&search=john&sortBy=created_at&sortOrder=desc&role=admin&status=active
```

#### 4.2.2 Structured Filter Format (AG-Grid compatible)
```
GET /Users?startRow=0&endRow=100&filters[role][type]=equals&filters[role][value]=admin&filters[age][type]=between&filters[age][values]=18,65&sort[0][field]=created_at&sort[0][direction]=desc
```

#### 4.2.3 JSON Format (MUI DataGrid compatible)
```
GET /Users?page=0&pageSize=25&filterModel={"role":"admin","age":{"gte":18,"lte":65}}&sortModel=[{"field":"created_at","sort":"desc"}]
```

#### 4.2.4 Advanced Format (Full feature set)
```
GET /Users?page=1&per_page=20&search=john&search_fields=first_name,last_name,email&filter[role]=admin&filter[age][gte]=18&filter[age][lte]=65&filter[status][in]=active,pending&sort=created_at:desc,name:asc&include_total=true&include_available_filters=true
```

### 4.3 React-Optimized Response Format

The API will return responses optimized for React data fetching patterns:

#### 4.3.1 TanStack Query Compatible Response
```json
{
  "success": true,
  "status": 200,
  "data": [...],
  "meta": {
    "pagination": {
      "page": 2,
      "pageSize": 20,
      "total": 157,
      "pageCount": 8,
      "hasPreviousPage": true,
      "hasNextPage": true,
      "startCursor": "eyJpZCI6MjF9",
      "endCursor": "eyJpZCI6NDB9"
    },
    "filters": {
      "applied": {
        "search": "john",
        "role": "admin",
        "age": { "gte": 18, "lte": 65 }
      },
      "available": {
        "role": {
          "type": "enum",
          "options": ["admin", "user", "moderator"]
        },
        "status": {
          "type": "enum", 
          "options": ["active", "inactive"]
        },
        "age": {
          "type": "number",
          "min": 0,
          "max": 120
        }
      }
    },
    "sorting": {
      "applied": [
        { "field": "created_at", "direction": "desc" },
        { "field": "name", "direction": "asc" }
      ],
      "available": ["id", "name", "email", "created_at", "updated_at"]
    }
  },
  "links": {
    "self": "/Users?page=2&pageSize=20&search=john&role=admin",
    "first": "/Users?page=1&pageSize=20&search=john&role=admin", 
    "last": "/Users?page=8&pageSize=20&search=john&role=admin",
    "prev": "/Users?page=1&pageSize=20&search=john&role=admin",
    "next": "/Users?page=3&pageSize=20&search=john&role=admin"
  },
  "timestamp": "2025-08-16T10:30:00+00:00"
}
```

#### 4.3.2 AG-Grid Compatible Response
```json
{
  "success": true,
  "data": [...],
  "lastRow": 157,  // AG-Grid specific: total count for infinite scroll
  "secondaryColumns": null  // AG-Grid specific: for dynamic columns
}
```

#### 4.3.3 Infinite Scroll Response (Cursor-based)
```json
{
  "success": true,
  "data": [...],
  "pageInfo": {
    "hasNextPage": true,
    "hasPreviousPage": false,
    "startCursor": "eyJpZCI6MX0=",
    "endCursor": "eyJpZCI6MjB9"
  },
  "totalCount": 157,  // Optional for performance
  "edges": [
    {
      "node": { /* record data */ },
      "cursor": "eyJpZCI6MX0="
    }
  ]
}
```

## 5. Architecture Components

### 5.1 Request-Centric Architecture

The Router will enhance the Request object by attaching helper classes as properties, avoiding circular references:

```php
// Router creates and enhances Request with helpers
class Router {
    protected function attachRequestHelpers(Request $request): void {
        // Instantiate helpers (receive Request but don't store it)
        $parameterParser = new RequestParameterParser($request);
        $filterCriteria = new FilterCriteria($request);
        $searchEngine = new SearchEngine($request);
        $paginationManager = new PaginationManager($request);
        $responseFormatter = new ResponseFormatter($request);
        
        // Attach as Request properties for easy access
        $request->setParameterParser($parameterParser);
        $request->setFilterCriteria($filterCriteria);
        $request->setSearchEngine($searchEngine);
        $request->setPaginationManager($paginationManager);
        $request->setResponseFormatter($responseFormatter);
        
        // Parse parameters immediately for availability
        $request->setParsedParams($parameterParser->parseUnified());
    }
}

// Enhanced Request class with helper properties
class Request {
    protected ?RequestParameterParser $parameterParser = null;
    protected ?FilterCriteria $filterCriteria = null;
    protected ?SearchEngine $searchEngine = null;
    protected ?PaginationManager $paginationManager = null;
    protected ?ResponseFormatter $responseFormatter = null;
    protected ?array $parsedParams = null;
    
    // Getters for helper classes
    public function getParameterParser(): ?RequestParameterParser;
    public function getFilterCriteria(): ?FilterCriteria;
    public function getSearchEngine(): ?SearchEngine;
    public function getPaginationManager(): ?PaginationManager;
    public function getResponseFormatter(): ?ResponseFormatter;
    public function getParsedParams(): ?array;
    
    // Convenience methods for quick access
    public function getFilters(): array;
    public function getSearchParams(): array;
    public function getPaginationParams(): array;
    public function getSortingParams(): array;
    public function getResponseFormat(): string;
}
```

### 5.2 Helper Classes (No Circular References)

```php
// Request Parameter Parser - handles multiple input formats
class RequestParameterParser {
    // Constructor receives Request but DOES NOT store it
    public function __construct(Request $request) {
        // Parse immediately, no need to store Request
    }
    
    public function parseUnified(Request $request): array;
    public function detectFormat(Request $request): string; // 'simple', 'structured', 'json', 'ag-grid'
    public function parseFilters(Request $request, string $format): array;
    public function parsePagination(Request $request, string $format): array;
    public function parseSorting(Request $request, string $format): array;
    public function parseSearch(Request $request, string $format): array;
}

// Filter Criteria Management
class FilterCriteria {
    public function __construct(Request $request) {
        // Setup without storing Request
    }
    
    public function applyToQuery(QueryBuilder $qb, array $filters, string $mainAlias, array $modelFields): void;
    public function validateFilters(array $filters, string $model): bool;
    public function getSupportedFilters(string $model): array;
    public function parseAgGridFilters(array $filterModel): array;
    public function parseMuiFilters(string $filterModelJson): array;
}

// Search Engine
class SearchEngine {
    public function __construct(Request $request) {
        // Setup without storing Request
    }
    
    public function buildSearchQuery(QueryBuilder $qb, string $searchTerm, array $searchFields, string $mainAlias): void;
    public function getSearchableFields(string $model): array;
    public function parseSearchTerm(string $term): array;
    public function buildFullTextSearch(QueryBuilder $qb, string $term): void;
}

// Pagination Manager
class PaginationManager {
    public function __construct(Request $request) {
        // Setup without storing Request
    }
    
    public function buildOffsetPagination(array $data, array $paginationParams, int $total): array;
    public function buildCursorPagination(array $data, array $paginationParams, int $total = null): array;
    public function calculatePageInfo(int $total, int $page, int $perPage): array;
    public function generatePaginationLinks(string $baseUrl, array $params): array;
    public function encodeCursor(array $lastRecord): string;
    public function decodeCursor(string $cursor): array;
}

// Response Formatter - handles multiple output formats
class ResponseFormatter {
    public function __construct(Request $request) {
        // Setup without storing Request
    }
    
    public function format(array $data, array $meta, string $format): array;
    public function formatForTanStackQuery(array $data, array $meta, array $links): array;
    public function formatForAgGrid(array $data, int $totalCount): array;
    public function formatForInfiniteScroll(array $data, array $pageInfo): array;
    public function formatStandard(array $data, array $meta): array;
}

// Enhanced API Controller Methods
class ModelBaseAPIController {
    public function list(Request $request): array {
        // Access helpers directly from Request object
        $filters = $request->getFilters();
        $searchParams = $request->getSearchParams();
        $paginationParams = $request->getPaginationParams();
        $responseFormatter = $request->getResponseFormatter();
        
        // ... implementation using Request helpers
    }
}
```

## 6. Implementation Steps

### 6.0 Field-Based Operator Architecture Benefits

The field-based operator approach provides significant advantages over a centralized operator system:

#### **Type Safety & Defaults**
- Each field type defines its own sensible default operators
- No need to maintain complex field-type-to-operator mappings in FilterCriteria
- Automatic compatibility checking (e.g., no 'contains' operator on IntegerField by default)
- Consistent behavior across all instances of the same field type

#### **Performance Control**
- Can disable expensive operators like 'contains' on large text fields
- Enable expensive operators only where needed via metadata
- Field-specific performance tuning without affecting other fields of the same type

#### **Metadata Integration**
- Uses existing `ingestMetadata()` mechanism in FieldBase
- No additional configuration systems needed
- Operators can be customized per field instance in model metadata
- Zero configuration required - works with sensible defaults

#### **Extensibility**
- New field types automatically get appropriate operators
- Custom field types can define their own operator sets
- Easy to add new operators to specific field types
- Business logic can be embedded in field validation methods

#### **Example Benefits in Practice**

```php
// Performance optimization example:
'user_bio' => [
    'type' => 'BigTextField',
    // Default BigTextField operators: ['equals', 'isNull', 'isNotNull'] - no expensive text search
],

'user_initials' => [
    'type' => 'BigTextField', 
    'operators' => ['equals', 'contains', 'startsWith', 'isNull', 'isNotNull'] // Enable search on small field
],

// Type safety example:
'age' => [
    'type' => 'IntegerField',
    // Default IntegerField operators automatically exclude string operators like 'contains'
],

// Custom business rules example:
'salary' => [
    'type' => 'FloatField',
    'operators' => ['isNull', 'isNotNull'] // Remove all comparison operators for privacy
]
```

This approach eliminates the need for `getFilterableFields()` entirely, as each field instance knows its own capabilities.

### 6.0 Model-Aware Validation Strategy with Field-Based Operators

**CRITICAL DESIGN DECISION**: Filter validation must happen in the Controller layer where the model is known, not in the Router where it's unknown. Additionally, **operators will be defined at the field level** rather than hard-coded, providing maximum flexibility and type safety.

#### Field-Based Operator Architecture:
1. **FieldBase Enhancement**: Each field type defines its own supported operators as a class property
2. **Metadata Override Capability**: Operators can be customized per field instance via metadata
3. **Type-Appropriate Defaults**: Each field subclass gets sensible default operators
4. **Performance Tuning**: Expensive operators can be disabled on specific fields via metadata

#### Validation Flow:
1. **Router**: Parses raw parameters into generic filter structures (no model knowledge)
2. **Controller**: Instantiates model and calls `Request->getValidatedFilters($model)` 
3. **FilterCriteria Helper**: Validates filters against each field's allowed operators and types
4. **DatabaseConnector**: Applies validated filters safely to QueryBuilder

#### FieldBase Enhancement for Operators:

```php
abstract class FieldBase {
    // ... existing properties ...
    
    protected array $operators = []; // Default operators for this field type
    
    // ... existing methods ...
    
    /**
     * Get allowed operators for this field instance
     * Can be overridden by metadata via ingestMetadata()
     */
    public function getOperators(): array {
        return $this->operators;
    }
    
    /**
     * Validate that a value is appropriate for this field with the given operator
     */
    public function isValidFilterValue($value, string $operator): bool {
        // Null operators don't need values
        if (in_array($operator, ['isNull', 'isNotNull'])) {
            return true;
        }
        
        // Other operators require a value
        if ($value === null || $value === '') {
            return false;
        }
        
        // Default validation - subclasses can override for specific types
        return true;
    }
    
    /**
     * Normalize/convert a filter value for database usage
     */
    public function normalizeFilterValue($value, string $operator) {
        // Default behavior - subclasses can override for type-specific normalization
        return $value;
    }
    
    // Enhanced ingestMetadata to handle operators
    public function ingestMetadata(array $metadata): void {
        // ... existing metadata ingestion ...
        
        // Allow metadata to override operators
        if (isset($metadata['operators']) && is_array($metadata['operators'])) {
            $this->operators = $metadata['operators'];
        }
    }
}

// Example subclass implementations:

class TextField extends FieldBase {
    protected array $operators = [
        'equals', 'contains', 'startsWith', 'endsWith', 
        'in', 'notIn', 'isNull', 'isNotNull'
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        if (in_array($operator, ['in', 'notIn'])) {
            return is_array($value) && !empty($value);
        }
        
        return is_string($value) || is_numeric($value);
    }
}

class IntegerField extends FieldBase {
    protected array $operators = [
        'equals', 'gt', 'gte', 'lt', 'lte', 'between',
        'in', 'notIn', 'isNull', 'isNotNull'
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        if (in_array($operator, ['in', 'notIn', 'between'])) {
            return is_array($value) && !empty($value) && 
                   array_filter($value, 'is_numeric') === $value;
        }
        
        return is_numeric($value);
    }
    
    public function normalizeFilterValue($value, string $operator) {
        if (is_array($value)) {
            return array_map('intval', $value);
        }
        return (int) $value;
    }
}

class BooleanField extends FieldBase {
    protected array $operators = ['equals', 'isNull', 'isNotNull'];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        return in_array($value, [true, false, 'true', 'false', '1', '0', 1, 0], true);
    }
    
    public function normalizeFilterValue($value, string $operator) {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

class DateField extends FieldBase {
    protected array $operators = [
        'equals', 'before', 'after', 'between', 
        'isNull', 'isNotNull'
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        if ($operator === 'between') {
            return is_array($value) && count($value) === 2;
        }
        
        // Validate date format
        return is_string($value) || $value instanceof \DateTime;
    }
}

class Enum extends FieldBase {
    protected array $operators = ['equals', 'in', 'notIn', 'isNull', 'isNotNull'];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        $allowedOptions = $this->getOptions();
        
        if (in_array($operator, ['in', 'notIn'])) {
            return is_array($value) && !empty($value) && 
                   array_diff($value, $allowedOptions) === [];
        }
        
        return in_array($value, $allowedOptions);
    }
}

class BigTextField extends TextField {
    // Override to remove expensive operators by default
    protected array $operators = [
        'equals', 'in', 'notIn', 'isNull', 'isNotNull'
        // Note: 'contains', 'startsWith', 'endsWith' removed due to performance
        // Can be re-enabled per field via metadata if needed
    ];
}
```

#### Example Metadata Usage:

```php
// In model metadata - allow 'contains' on a specific small text field
'user_initials' => [
    'type' => 'BigTextField',
    'operators' => ['equals', 'contains', 'in', 'notIn', 'isNull', 'isNotNull']
],

// In model metadata - disable expensive operators on large text field
'biography' => [
    'type' => 'TextField', 
    'operators' => ['equals', 'isNull', 'isNotNull'] // Only allow exact matches
]
```

#### Key Validation Points:
- **Field Existence**: Only allow filtering on fields that exist in the model
- **Operator Compatibility**: Ensure operators are valid for the specific field instance
- **Value Type Validation**: Ensure values match field types using field-specific validation
- **Enum Validation**: For enum fields, ensure values are in the allowed options list
- **Security**: Prevent injection by validating field names against model schema
- **Performance**: Allow fine-grained control over expensive operators per field

#### Example Validation Scenarios:
```php
// INVALID: Field doesn't exist on User model
filter[nonexistent_field]=value → FILTERED OUT

// INVALID: Wrong operator for field type  
filter[age][contains]=25 → FILTERED OUT (age is integer, contains is for strings)

// INVALID: Value not in enum options
filter[role]=hacker → FILTERED OUT (if role enum only allows [admin, user, moderator])

// VALID: Proper field, operator, and value
filter[age][gte]=18 → ALLOWED

// VALID: String field with string operator
filter[name][contains]=john → ALLOWED
```

### 6.1 Phase 1: Request Object Architecture Overhaul (Week 1, Days 1-2)

#### Step 1: Enhanced Request Class with RequestData Property
**BREAKING CHANGE**: Major retrofit to consolidate all request data in Request object:

```php
// Enhanced Request class with unified request data
class Request {
    // ... existing properties ...
    
    protected array $requestData = []; // NEW: All GET/POST/PUT/PATCH data
    protected ?RequestParameterParser $parameterParser = null;
    protected ?FilterCriteria $filterCriteria = null;
    protected ?SearchEngine $searchEngine = null;
    protected ?PaginationManager $paginationManager = null;
    protected ?ResponseFormatter $responseFormatter = null;
    protected ?array $parsedParams = null;
    
    // NEW: Request data methods
    public function setRequestData(array $requestData): void {
        $this->requestData = $requestData;
    }
    
    public function getRequestData(): array {
        return $this->requestData;
    }
    
    public function getRequestParam(string $key, $default = null) {
        return $this->requestData[$key] ?? $default;
    }
    
    public function hasRequestParam(string $key): bool {
        return isset($this->requestData[$key]);
    }
    
    public function getAllRequestParams(): array {
        return $this->requestData;
    }
    
    // Getters for helper classes
    public function getParameterParser(): ?RequestParameterParser {
        return $this->parameterParser;
    }
    
    public function getFilterCriteria(): ?FilterCriteria {
        return $this->filterCriteria;
    }
    
    public function getSearchEngine(): ?SearchEngine {
        return $this->searchEngine;
    }
    
    public function getPaginationManager(): ?PaginationManager {
        return $this->paginationManager;
    }
    
    public function getResponseFormatter(): ?ResponseFormatter {
        return $this->responseFormatter;
    }
    
    public function getParsedParams(): ?array {
        return $this->parsedParams;
    }
    
    // Setters (called by Router only)
    public function setParameterParser(RequestParameterParser $parser): void {
        $this->parameterParser = $parser;
    }
    
    public function setFilterCriteria(FilterCriteria $criteria): void {
        $this->filterCriteria = $criteria;
    }
    
    public function setSearchEngine(SearchEngine $engine): void {
        $this->searchEngine = $engine;
    }
    
    public function setPaginationManager(PaginationManager $manager): void {
        $this->paginationManager = $manager;
    }
    
    public function setResponseFormatter(ResponseFormatter $formatter): void {
        $this->responseFormatter = $formatter;
    }
    
    public function setParsedParams(array $params): void {
        $this->parsedParams = $params;
    }
    
    // Convenience methods for parsed parameters
    public function getFilters(): array {
        return $this->parsedParams['filters'] ?? [];
    }
    
    public function getSearchParams(): array {
        return $this->parsedParams['search'] ?? [];
    }
    
    public function getPaginationParams(): array {
        return $this->parsedParams['pagination'] ?? [];
    }
    
    public function getSortingParams(): array {
        return $this->parsedParams['sorting'] ?? [];
    }
    
    public function getResponseFormat(): string {
        return $this->parsedParams['format'] ?? 'standard';
    }
    
    // NEW: Model-aware validation methods (called by Controller once model is known)
    public function getValidatedFilters(ModelBase $model): array {
        $filters = $this->getFilters();
        if (empty($filters)) {
            return [];
        }
        
        $filterCriteria = $this->getFilterCriteria();
        return $filterCriteria ? $filterCriteria->validateAndFilterForModel($filters, $model) : [];
    }
    
    public function getValidatedSorting(ModelBase $model): array {
        $sorting = $this->getSortingParams();
        if (empty($sorting)) {
            return $model->getDefaultSort();
        }
        
        return $this->validateSortingForModel($sorting, $model);
    }
    
    public function getValidatedSearchParams(ModelBase $model): array {
        $searchParams = $this->getSearchParams();
        if (empty($searchParams)) {
            return [];
        }
        
        $searchEngine = $this->getSearchEngine();
        return $searchEngine ? $searchEngine->validateSearchForModel($searchParams, $model) : [];
    }
    
    private function validateSortingForModel(array $sorting, ModelBase $model): array {
        $sortableFields = $model->getSortableFields();
        $validatedSorting = [];
        
        foreach ($sorting as $sort) {
            $field = $sort['field'] ?? null;
            $direction = strtoupper($sort['direction'] ?? 'ASC');
            
            if ($field && in_array($field, $sortableFields) && in_array($direction, ['ASC', 'DESC'])) {
                $validatedSorting[] = ['field' => $field, 'direction' => $direction];
            }
        }
        
        // Fall back to default sort if no valid sorting provided
        return !empty($validatedSorting) ? $validatedSorting : $model->getDefaultSort();
    }
}
```

#### Step 2: Router Enhancement with Request Data Integration
**BREAKING CHANGE**: Router will set request data on Request object and remove $additionalParams:

```php
class Router {
    public function route(string $method, string $path, array $requestData = []): mixed {
        // ... existing route matching logic ...
        
        // 4. Create Request object for parameter extraction
        $request = new Request($path, $bestRoute['parameterNames'], $method);
        
        // NEW: Set request data on Request object (instead of passing separately)
        $request->setRequestData($requestData);
        
        // NEW: Instantiate and attach helper classes to Request
        $this->attachRequestHelpers($request);
        
        // 5. Execute route with enhanced Request object (NO $additionalParams)
        return $this->executeRoute($bestRoute, $request);
    }
    
    public function handleRequest(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Get request data (renamed from additionalParams for clarity)
        $requestData = $this->getRequestParams();

        $this->logger->info("Routing request: $method $path");

        try {
            // Pass request data to route() method
            $result = $this->route($method, $path, $requestData);
            
            // ... rest of method unchanged
        } catch (GCException $e) {
            // ... error handling unchanged
        }
    }
    
    protected function attachRequestHelpers(Request $request): void {
        // Instantiate helpers (they receive Request but don't store it)
        $parameterParser = new RequestParameterParser($request);
        $filterCriteria = new FilterCriteria($request);
        $searchEngine = new SearchEngine($request);
        $paginationManager = new PaginationManager($request);
        $responseFormatter = new ResponseFormatter($request);
        
        // Attach as Request properties for easy access
        $request->setParameterParser($parameterParser);
        $request->setFilterCriteria($filterCriteria);
        $request->setSearchEngine($searchEngine);
        $request->setPaginationManager($paginationManager);
        $request->setResponseFormatter($responseFormatter);
        
        // Parse parameters immediately for availability
        $request->setParsedParams($parameterParser->parseUnified());
    }
    
    // UPDATED: Remove $additionalParams parameter
    protected function executeRoute(array $route, Request $request): mixed {
        $controllerClass = $route['apiClass'];
        $handlerMethod = $route['apiMethod'];
        
        // ... existing validation logic ...
        
        $controller = new $controllerClass($this->logger);
        
        // ... existing method existence check ...
        
        // Authentication and authorization middleware
        $this->handleAuthentication($route, $request);
        
        // Validate Request parameters
        $this->validateRequestParameters($request, $route);
        
        // NEW: Call controller method with ONLY Request object
        return $controller->$handlerMethod($request);
    }
}
```

#### Step 3: Helper Classes Enhancement for Request Data Access
```php
class RequestParameterParser {
    // Constructor receives Request but DOES NOT store it
    public function __construct(Request $request) {
        // Can access both path params and request data during initialization
        // $request->get() for path params
        // $request->getRequestData() for query/body params
    }
    
    public function parseUnified(Request $request): array {
        $format = $this->detectFormat($request);
        
        return [
            'pagination' => $this->parsePagination($request, $format),
            'filters' => $this->parseFilters($request, $format),
            'sorting' => $this->parseSorting($request, $format),
            'search' => $this->parseSearch($request, $format),
            'format' => $format
        ];
    }
    
    public function detectFormat(Request $request): string {
        $requestData = $request->getRequestData();
        
        // Detect based on parameter patterns:
        if (isset($requestData['startRow']) && isset($requestData['endRow'])) {
            return 'ag-grid';
        }
        if (isset($requestData['filterModel']) || isset($requestData['sortModel'])) {
            return 'mui';
        }
        if (isset($requestData['filter']) && is_array($requestData['filter'])) {
            return 'structured';
        }
        return 'simple';
    }
    
    private function parsePagination(Request $request, string $format): array {
        $requestData = $request->getRequestData();
        
        switch ($format) {
            case 'ag-grid':
                return [
                    'startRow' => (int) ($requestData['startRow'] ?? 0),
                    'endRow' => (int) ($requestData['endRow'] ?? 100),
                    'type' => 'ag-grid'
                ];
            case 'mui':
                return [
                    'page' => (int) ($requestData['page'] ?? 0), // 0-based for MUI
                    'pageSize' => (int) ($requestData['pageSize'] ?? 25),
                    'type' => 'mui'
                ];
            default:
                return [
                    'page' => (int) ($requestData['page'] ?? 1), // 1-based for standard
                    'pageSize' => (int) ($requestData['pageSize'] ?? 20),
                    'type' => 'standard'
                ];
        }
    }
    
    // Similar implementations for parseFilters, parseSorting, parseSearch
}

class FilterCriteria {
    private array $sqlOperatorMap = [
        'equals' => '=',
        'contains' => 'LIKE',
        'startsWith' => 'LIKE',
        'endsWith' => 'LIKE',
        'in' => 'IN',
        'notIn' => 'NOT IN',
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
        'between' => 'BETWEEN',
        'before' => '<',
        'after' => '>',
        'dateEquals' => 'DATE_EQUALS',
        'isNull' => 'IS NULL',
        'isNotNull' => 'IS NOT NULL'
    ];
    
    public function __construct(Request $request) {
        // Can access request data for validation/setup but doesn't store Request
    }
    
    // NEW: Model-aware validation method using field-based operators (called by Controller)
    public function validateAndFilterForModel(array $filters, ModelBase $model): array {
        $validatedFilters = [];
        $modelFields = $model->getFields(); // Get actual field instances
        
        foreach ($filters as $filter) {
            $fieldName = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;
            
            // Check if field exists in model
            if (!isset($modelFields[$fieldName])) {
                $this->logger->warning("Filter validation failed: Field '{$fieldName}' not found in model", [
                    'model' => get_class($model),
                    'available_fields' => array_keys($modelFields),
                    'requested_field' => $fieldName
                ]);
                continue; // Skip invalid field
            }
            
            $fieldInstance = $modelFields[$fieldName];
            
            // Get allowed operators for this specific field instance
            $allowedOperators = $fieldInstance->getOperators();
            
            // Validate operator is allowed for this field
            if (!in_array($operator, $allowedOperators)) {
                $this->logger->warning("Filter validation failed: Operator '{$operator}' not allowed for field '{$fieldName}'", [
                    'field_class' => get_class($fieldInstance),
                    'allowed_operators' => $allowedOperators,
                    'requested_operator' => $operator,
                    'field_name' => $fieldName
                ]);
                continue; // Skip invalid operator
            }
            
            // Validate operator exists in our SQL mapping
            if (!isset($this->sqlOperatorMap[$operator])) {
                $this->logger->warning("Filter validation failed: Operator '{$operator}' not implemented in SQL mapping", [
                    'operator' => $operator,
                    'field_name' => $fieldName
                ]);
                continue; // Skip unimplemented operator
            }
            
            // Validate value using field's validation method
            if (!$fieldInstance->isValidFilterValue($value, $operator)) {
                $this->logger->warning("Filter validation failed: Value invalid for field '{$fieldName}' with operator '{$operator}'", [
                    'field_name' => $fieldName,
                    'operator' => $operator,
                    'value' => $value,
                    'field_class' => get_class($fieldInstance)
                ]);
                continue; // Skip invalid value
            }
            
            // Normalize/convert value using field's method
            $normalizedValue = $fieldInstance->normalizeFilterValue($value, $operator);
            
            $validatedFilters[] = [
                'field' => $fieldName,
                'operator' => $operator,
                'value' => $normalizedValue,
                'sql_operator' => $this->sqlOperatorMap[$operator]
            ];
            
            $this->logger->info("Filter validation passed", [
                'field' => $fieldName,
                'operator' => $operator,
                'value' => $normalizedValue,
                'field_class' => get_class($fieldInstance)
            ]);
        }
        
        $this->logger->info("Filter validation complete", [
            'original_count' => count($filters),
            'validated_count' => count($validatedFilters),
            'rejected_count' => count($filters) - count($validatedFilters)
        ]);
        
        return $validatedFilters;
    }
    
    public function applyToQuery(QueryBuilder $qb, array $filters, string $mainAlias, array $modelFields): void {
        // Apply filters to query builder - filters are already validated at this point
        foreach ($filters as $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];
            
            $columnName = "$mainAlias.$field";
            $paramName = "filter_" . str_replace('.', '_', $field) . '_' . uniqid();
            
            switch ($operator) {
                case 'equals':
                    $qb->andWhere("$columnName = :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'contains':
                    $qb->andWhere("$columnName LIKE :$paramName");
                    $qb->setParameter($paramName, "%$value%");
                    break;
                    
                case 'startsWith':
                    $qb->andWhere("$columnName LIKE :$paramName");
                    $qb->setParameter($paramName, "$value%");
                    break;
                    
                case 'endsWith':
                    $qb->andWhere("$columnName LIKE :$paramName");
                    $qb->setParameter($paramName, "%$value");
                    break;
                    
                case 'in':
                    $qb->andWhere("$columnName IN (:$paramName)");
                    $qb->setParameter($paramName, $value, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                    break;
                    
                case 'notIn':
                    $qb->andWhere("$columnName NOT IN (:$paramName)");
                    $qb->setParameter($paramName, $value, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                    break;
                    
                case 'gt':
                    $qb->andWhere("$columnName > :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'gte':
                    $qb->andWhere("$columnName >= :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'lt':
                    $qb->andWhere("$columnName < :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'lte':
                    $qb->andWhere("$columnName <= :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'between':
                    $qb->andWhere("$columnName BETWEEN :${paramName}_start AND :${paramName}_end");
                    $qb->setParameter("${paramName}_start", $value[0]);
                    $qb->setParameter("${paramName}_end", $value[1]);
                    break;
                    
                case 'before':
                    $qb->andWhere("$columnName < :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'after':
                    $qb->andWhere("$columnName > :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'isNull':
                    $qb->andWhere("$columnName IS NULL");
                    break;
                    
                case 'isNotNull':
                    $qb->andWhere("$columnName IS NOT NULL");
                    break;
            }
        }
    }
}
                    
                case 'lte':
                    $qb->andWhere("$columnName <= :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'between':
                    $qb->andWhere("$columnName BETWEEN :${paramName}_start AND :${paramName}_end");
                    $qb->setParameter("{$paramName}_start", $value[0]);
                    $qb->setParameter("{$paramName}_end", $value[1]);
                    break;
                    
                case 'before':
                case 'after':
                    $sqlOperator = $operator === 'before' ? '<' : '>';
                    $qb->andWhere("$columnName $sqlOperator :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'isNull':
                    $qb->andWhere("$columnName IS NULL");
                    break;
                    
                case 'isNotNull':
                    $qb->andWhere("$columnName IS NOT NULL");
                    break;
            }
        }
    }
}
```

### 6.2 Phase 2: API Controller Method Signature Updates (Week 1, Days 2-3)

#### Step 1: ModelBaseAPIController Method Updates
**BREAKING CHANGE**: All API controller methods remove $additionalParams parameter:

```php
class ModelBaseAPIController {
    // UPDATED: Remove $additionalParams parameter from ALL methods
    public function list(Request $request): array {
        $modelName = $this->getModelName($request);
        $this->validateModelName($modelName);
        
        // Create model instance for validation (before database operations)
        $queryInstance = ModelFactory::new($modelName);
        
        // CRITICAL: Use model-aware validation methods from Request
        $filters = $request->getValidatedFilters($queryInstance);
        $searchParams = $request->getValidatedSearchParams($queryInstance);
        $paginationParams = $request->getPaginationParams(); // No model validation needed
        $sortingParams = $request->getValidatedSorting($queryInstance);
        $responseFormat = $request->getResponseFormat();
        
        // Access specific request data when needed
        $includeDeleted = $request->getRequestParam('include_deleted', false);
        $includeMetadata = $request->getRequestParam('include_metadata', true);
        
        try {
            // Use enhanced DatabaseConnector methods with VALIDATED parameters
            $databaseConnector = ServiceLocator::get(DatabaseConnector::class);
            
            // Get total count for pagination (filters and search already validated)
            $total = $databaseConnector->getCountWithCriteria(
                $queryInstance, 
                $filters, 
                $searchParams
            );
            
            // Get paginated data (all parameters are validated)
            $rows = $databaseConnector->findWithReactParams(
                $queryInstance,
                $filters,
                [
                    'search' => $searchParams,
                    'pagination' => $paginationParams,
                    'sorting' => $sortingParams
                ]
            );
            
            // Convert to model instances and then arrays
            $records = [];
            foreach ($rows as $row) {
                $model = ModelFactory::fromArray($modelName, $row);
                $records[] = $model->toArray();
            }
            
            // Build response metadata using Request helpers
            $paginationManager = $request->getPaginationManager();
            $meta = [
                'pagination' => $paginationManager->buildPagination($records, $paginationParams, $total),
                'filters' => [
                    'applied' => $filters, // These are the validated filters
                    'available' => $queryInstance->getFilterableFields()
                ],
                'sorting' => [
                    'applied' => $sortingParams, // These are the validated sorting params
                    'available' => $queryInstance->getSortableFields()
                ],
                'search' => [
                    'applied' => $searchParams, // These are the validated search params
                    'available_fields' => $queryInstance->getSearchableFields()
                ]
            ];
            
            // Format response using Request helper
            $responseFormatter = $request->getResponseFormatter();
            return $responseFormatter->format($records, $meta, $responseFormat);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to list records with React pagination', [
                'model' => $modelName,
                'error' => $e->getMessage(),
                'raw_filters' => $request->getFilters(), // Log original for debugging
                'validated_filters' => $filters, // Log what was actually used
                'raw_search' => $request->getSearchParams(),
                'validated_search' => $searchParams
            ]);
            throw new GCException('Failed to list records', [], 500, $e);
        }
    }
    
    // UPDATED: All other methods also remove $additionalParams
    public function retrieve(Request $request): array {
        $modelName = $this->getModelName($request);
        $id = $request->get('id');
        
        // Access request options
        $includeRelated = $request->getRequestParam('include_related', []);
        $includeDeleted = $request->getRequestParam('include_deleted', false);
        
        // ... implementation
    }
    
    public function create(Request $request): array {
        $modelName = $this->getModelName($request);
        
        // Get creation data from request
        $createData = $request->getRequestData();
        
        // ... implementation
    }
    
    public function update(Request $request): array {
        $modelName = $this->getModelName($request);
        $id = $request->get('id');
        
        // Get update data from request
        $updateData = $request->getRequestData();
        
        // ... implementation
    }
    
    public function delete(Request $request): array {
        $modelName = $this->getModelName($request);
        $id = $request->get('id');
        
        // Get delete options
        $softDelete = $request->getRequestParam('soft_delete', true);
        
        // ... implementation
    }
    
    // Update all other methods: listDeleted, restore, link, unlink, createAndLink, listRelated
}
```

#### Step 2: Update Any Custom API Controllers
All custom API controllers that extend or implement similar patterns need signature updates:

```php
// Example custom controller updates
class UserAPIController {
    // BEFORE: public function customMethod(Request $request, array $additionalParams = []): array
    // AFTER: 
    public function customMethod(Request $request): array {
        // Access all data through Request object
        $customParam = $request->getRequestParam('custom_param');
        $searchTerm = $request->getRequestParam('search');
        
        // ... implementation
    }
}
```

### 6.3 Phase 3: Response Formatting & Model Configuration (Week 2, Days 1-3)

#### Step 1: ResponseFormatter Implementation
```php
class ResponseFormatter {
    public function format(array $data, array $meta, string $format): array {
        switch ($format) {
            case 'ag-grid':
                return $this->formatForAgGrid($data, $meta);
            case 'tanstack-query':
            case 'swr':
                return $this->formatForTanStackQuery($data, $meta);
            case 'infinite-scroll':
                return $this->formatForInfiniteScroll($data, $meta);
            default:
                return $this->formatStandard($data, $meta);
        }
    }
    
    private function formatForAgGrid(array $data, array $meta): array {
        return [
            'success' => true,
            'data' => $data,
            'lastRow' => $meta['pagination']['total'] ?? null
        ];
    }
    
    private function formatForTanStackQuery(array $data, array $meta): array {
        return [
            'success' => true,
            'data' => $data,
            'meta' => $meta,
            'links' => $meta['links'] ?? []
        ];
    }
}
```

#### Step 2: Model Configuration System
```php
// Enhanced ModelBase with search/filter configuration
abstract class ModelBase {
    // Configuration methods for React compatibility
    protected function getSearchableFields(): array {
        // Default implementation - can be overridden
        return ['id']; // Safe default
    }
    
    protected function getFilterableFields(): array {
        // Define filterable fields with their types and constraints
        return [
            'id' => ['type' => 'number'],
            'created_at' => ['type' => 'date'],
            'updated_at' => ['type' => 'date']
        ];
    }
    
    protected function getSortableFields(): array {
        // Define which fields can be used for sorting
        return ['id', 'created_at', 'updated_at'];
    }
    
    protected function getDefaultSort(): array {
        // Default sorting for the model
        return [['field' => 'id', 'direction' => 'asc']];
    }
}

// Example User model implementation with field-based operators
class User extends ModelBase {
    protected function getSearchableFields(): array {
        return ['first_name', 'last_name', 'email', 'username'];
    }
    
    protected function getSortableFields(): array {
        // Only allow sorting on indexed or efficient-to-sort fields
        return ['id', 'email', 'created_at', 'updated_at', 'last_login'];
    }
    
    protected function getDefaultSort(): array {
        return [['field' => 'created_at', 'direction' => 'desc']];
    }
    
    // Fields define their own operators - no need for getFilterableFields() anymore
    protected function getMetadata(): array {
        return [
            'id' => [
                'type' => 'IDField',
                // IDField defines operators: ['equals', 'in', 'notIn', 'gt', 'gte', 'lt', 'lte']
            ],
            'first_name' => [
                'type' => 'TextField',
                // TextField defines operators: ['equals', 'contains', 'startsWith', 'endsWith', 'in', 'notIn', 'isNull', 'isNotNull']
            ],
            'last_name' => [
                'type' => 'TextField',
                // Uses default TextField operators
            ],
            'email' => [
                'type' => 'EmailField',
                // EmailField defines operators: ['equals', 'contains', 'startsWith', 'endsWith', 'in', 'notIn']
            ],
            'username' => [
                'type' => 'TextField',
                'operators' => ['equals', 'startsWith', 'in', 'notIn'] // Custom: removed 'contains' for performance
            ],
            'age' => [
                'type' => 'IntegerField',
                // IntegerField defines operators: ['equals', 'gt', 'gte', 'lt', 'lte', 'between', 'in', 'notIn', 'isNull', 'isNotNull']
            ],
            'salary' => [
                'type' => 'FloatField',
                // FloatField defines operators: ['equals', 'gt', 'gte', 'lt', 'lte', 'between', 'in', 'notIn', 'isNull', 'isNotNull']
            ],
            'role' => [
                'type' => 'Enum',
                'options' => ['admin', 'user', 'moderator'],
                // Enum defines operators: ['equals', 'in', 'notIn', 'isNull', 'isNotNull']
            ],
            'status' => [
                'type' => 'Enum',
                'options' => ['active', 'inactive', 'pending', 'suspended'],
                // Uses default Enum operators
            ],
            'is_verified' => [
                'type' => 'BooleanField',
                // BooleanField defines operators: ['equals', 'isNull', 'isNotNull']
            ],
            'bio' => [
                'type' => 'BigTextField',
                'operators' => ['equals', 'isNull', 'isNotNull'] // Custom: removed expensive text search operators
            ],
            'tags' => [
                'type' => 'TextField',
                'operators' => ['contains', 'in', 'notIn'] // Custom: allow contains for small tag field
            ],
            'created_at' => [
                'type' => 'DateTimeField',
                // DateTimeField defines operators: ['equals', 'before', 'after', 'between', 'isNull', 'isNotNull']
            ],
            'updated_at' => [
                'type' => 'DateTimeField',
                // Uses default DateTimeField operators
            ],
            'last_login' => [
                'type' => 'DateTimeField',
                // Uses default DateTimeField operators
            ]
        ];
    }
    
    // OPTIONAL: Custom validation for complex business rules
    protected function validateCustomFilters(array $filters): array {
        // Example: Prevent non-admin users from filtering by salary
        $currentUser = ServiceLocator::getCurrentUser();
        if ($currentUser && !$currentUser->hasRole('admin')) {
            $filters = array_filter($filters, function($filter) {
                return $filter['field'] !== 'salary';
            });
        }
        return $filters;
    }
}
```

### 6.4 Phase 4: Enhanced DatabaseConnector with Validated Parameters (Week 1, Days 4-5)

#### Step 1: Remove Current Pagination Implementation
**BREAKING CHANGE**: The existing pagination methods in DatabaseConnector will be completely removed:

```php
// REMOVE these existing methods from DatabaseConnector:
// Lines 814-845: applyQueryParameters() - Basic limit/offset/orderBy 
// Lines 470-512: getCount() - Single field/value count only

// These methods will be replaced with React-compatible alternatives
```

#### Step 2: Implement Enhanced DatabaseConnector Methods with Validation Logging
**NEW METHODS**: Replace removed pagination with comprehensive React-compatible system that expects pre-validated parameters:

```php
class DatabaseConnector {
    // Enhanced find method with pre-validated React-compatible parameters
    public function findWithReactParams(
        ModelBase $model, 
        array $validatedFilters = [],  // ALREADY VALIDATED by Controller
        array $reactParams = []
    ): array {
        $qb = $this->connection->createQueryBuilder();
        $tableName = $model->getTableName();
        $mainAlias = 't';
        
        $qb->select('*')->from($tableName, $mainAlias);
        
        // Apply pre-validated filters (no further validation needed)
        if (!empty($validatedFilters)) {
            $this->applyValidatedFilters($qb, $validatedFilters, $mainAlias);
        }
        
        // Apply pre-validated search (no further validation needed)
        if (!empty($reactParams['search']['term']) && !empty($reactParams['search']['fields'])) {
            $this->applyValidatedSearch($qb, $reactParams['search'], $mainAlias);
        }
        
        // Apply pre-validated sorting
        if (!empty($reactParams['sorting'])) {
            $this->applyValidatedSorting($qb, $reactParams['sorting'], $mainAlias);
        }
        
        // Apply pagination (no validation needed - just numeric limits)
        if (!empty($reactParams['pagination'])) {
            $this->applyPagination($qb, $reactParams['pagination']);
        }
        
        $this->logger->debug('Executing validated query', [
            'sql' => $qb->getSQL(),
            'parameters' => $qb->getParameters(),
            'filters_count' => count($validatedFilters),
            'search_fields' => $reactParams['search']['fields'] ?? [],
            'pagination' => $reactParams['pagination'] ?? []
        ]);
        
        return $qb->execute()->fetchAll();
    }
    
    // Enhanced count method supporting pre-validated complex criteria
    public function getCountWithCriteria(
        ModelBase $model,
        array $validatedFilters = [],  // ALREADY VALIDATED
        array $validatedSearch = []    // ALREADY VALIDATED
    ): int {
        $qb = $this->connection->createQueryBuilder();
        $tableName = $model->getTableName();
        $mainAlias = 't';
        
        $qb->select('COUNT(*)')->from($tableName, $mainAlias);
        
        // Apply same validated filters as main query but no pagination/sorting
        if (!empty($validatedFilters)) {
            $this->applyValidatedFilters($qb, $validatedFilters, $mainAlias);
        }
        
        if (!empty($validatedSearch['term']) && !empty($validatedSearch['fields'])) {
            $this->applyValidatedSearch($qb, $validatedSearch, $mainAlias);
        }
        
        return (int) $qb->execute()->fetchColumn();
    }
    
    // Apply pre-validated filters (no field existence or operator validation needed)
    private function applyValidatedFilters(
        \Doctrine\DBAL\Query\QueryBuilder $queryBuilder,
        array $validatedFilters,
        string $mainAlias
    ): void {
        foreach ($validatedFilters as $filter) {
            // These filters are already validated by FilterCriteria
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];
            
            $columnName = "$mainAlias.$field";
            $paramName = "filter_" . str_replace('.', '_', $field) . '_' . uniqid();
            
            switch ($operator) {
                case 'equals':
                    $queryBuilder->andWhere("$columnName = :$paramName");
                    $queryBuilder->setParameter($paramName, $value);
                    break;
                    
                case 'contains':
                    $queryBuilder->andWhere("$columnName LIKE :$paramName");
                    $queryBuilder->setParameter($paramName, "%$value%");
                    break;
                    
                case 'startsWith':
                    $queryBuilder->andWhere("$columnName LIKE :$paramName");
                    $queryBuilder->setParameter($paramName, "$value%");
                    break;
                    
                case 'endsWith':
                    $queryBuilder->andWhere("$columnName LIKE :$paramName");
                    $queryBuilder->setParameter($paramName, "%$value");
                    break;
                    
                case 'in':
                    $queryBuilder->andWhere("$columnName IN (:$paramName)");
                    $queryBuilder->setParameter($paramName, $value, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                    break;
                    
                case 'notIn':
                    $queryBuilder->andWhere("$columnName NOT IN (:$paramName)");
                    $queryBuilder->setParameter($paramName, $value, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                    break;
                    
                case 'gt':
                    $queryBuilder->andWhere("$columnName > :$paramName");
                    $queryBuilder->setParameter($paramName, $value);
                    break;
                    
                case 'gte':
                    $queryBuilder->andWhere("$columnName >= :$paramName");
                    $queryBuilder->setParameter($paramName, $value);
                    break;
                    
                case 'lt':
                    $queryBuilder->andWhere("$columnName < :$paramName");
                    $queryBuilder->setParameter($paramName, $value);
                    break;
                    
                case 'lte':
                    $queryBuilder->andWhere("$columnName <= :$paramName");
                    $queryBuilder->setParameter($paramName, $value);
                    break;
                    
                case 'between':
                    $queryBuilder->andWhere("$columnName BETWEEN :${paramName}_start AND :${paramName}_end");
                    $queryBuilder->setParameter("{$paramName}_start", $value[0]);
                    $queryBuilder->setParameter("{$paramName}_end", $value[1]);
                    break;
                    
                case 'before':
                case 'after':
                    $sqlOperator = $operator === 'before' ? '<' : '>';
                    $queryBuilder->andWhere("$columnName $sqlOperator :$paramName");
                    $queryBuilder->setParameter($paramName, $value);
                    break;
                    
                case 'isNull':
                    $queryBuilder->andWhere("$columnName IS NULL");
                    break;
                    
                case 'isNotNull':
                    $queryBuilder->andWhere("$columnName IS NOT NULL");
                    break;
            }
        }
    }
    
    // Apply pre-validated search (no field validation needed)
    private function applyValidatedSearch(
        \Doctrine\DBAL\Query\QueryBuilder $queryBuilder,
        array $validatedSearch,
        string $mainAlias
    ): void {
        $searchTerm = $validatedSearch['term'];
        $searchFields = $validatedSearch['fields']; // Already validated
        
        $searchConditions = [];
        $paramName = 'search_term_' . uniqid();
        
        foreach ($searchFields as $field) {
            $columnName = "$mainAlias.$field";
            $searchConditions[] = "$columnName LIKE :$paramName";
        }
        
        if (!empty($searchConditions)) {
            $queryBuilder->andWhere('(' . implode(' OR ', $searchConditions) . ')');
            $queryBuilder->setParameter($paramName, "%$searchTerm%");
        }
    }
    
    // Apply pre-validated sorting (no field validation needed)
    private function applyValidatedSorting(
        \Doctrine\DBAL\Query\QueryBuilder $queryBuilder,
        array $validatedSorting,
        string $mainAlias
    ): void {
        foreach ($validatedSorting as $sort) {
            // Already validated by Request->getValidatedSorting()
            $field = $sort['field'];
            $direction = $sort['direction']; // Already validated as ASC/DESC
            
            $columnName = "$mainAlias.$field";
            $queryBuilder->addOrderBy($columnName, $direction);
        }
    }
    
    // Apply pagination (simple numeric validation only)
    private function applyPagination(
        \Doctrine\DBAL\Query\QueryBuilder $queryBuilder,
        array $paginationParams
    ): void {
        $type = $paginationParams['type'] ?? 'standard';
        
        if ($type === 'ag-grid') {
            // AG-Grid row-based pagination
            $startRow = max(0, (int) ($paginationParams['startRow'] ?? 0));
            $endRow = max($startRow + 1, (int) ($paginationParams['endRow'] ?? 100));
            $limit = min($endRow - $startRow, 1000); // Security: max 1000 rows
            
            $queryBuilder->setFirstResult($startRow);
            $queryBuilder->setMaxResults($limit);
        } elseif ($type === 'mui') {
            // MUI uses 0-based page indexing
            $page = max(0, (int) ($paginationParams['page'] ?? 0));
            $pageSize = min(max(1, (int) ($paginationParams['pageSize'] ?? 25)), 100); // Security: max 100 per page
            $offset = $page * $pageSize;
            
            $queryBuilder->setFirstResult($offset);
            $queryBuilder->setMaxResults($pageSize);
        } else {
            // Standard uses 1-based page indexing
            $page = max(1, (int) ($paginationParams['page'] ?? 1));
            $pageSize = min(max(1, (int) ($paginationParams['pageSize'] ?? 20)), 100); // Security: max 100 per page
            $offset = ($page - 1) * $pageSize;
            
            $queryBuilder->setFirstResult($offset);
            $queryBuilder->setMaxResults($pageSize);
        }
    }
}
```

### 6.5 Phase 5: Custom API Controller Retrofitting (Week 2, Days 1-2)

#### Step 1: Complete ModelBaseAPIController Integration
```php
class ModelBaseAPIController {
    public function listWithAdvancedFiltering(Request $request, array $additionalParams = []): array {
        $modelName = $this->getModelName($request);
        $this->validateModelName($modelName);
        
        // Parse all request parameters in unified format
        $parser = new RequestParameterParser();
        $params = $parser->parseUnified($request);
        
        try {
            $queryInstance = ModelFactory::new($modelName);
            
            // Apply search
            if (!empty($params['search']['term'])) {
                $searchEngine = new SearchEngine();
                $searchFields = $params['search']['fields'] ?? $queryInstance->getSearchableFields();
                $searchEngine->buildSearchQuery($queryInstance, $params['search']['term'], $searchFields);
            }
            
            // Apply filters
            if (!empty($params['filters'])) {
                $filterCriteria = new FilterCriteria();
                $filterCriteria->applyToQuery($queryInstance, $params['filters']);
            }
            
            // Apply sorting
            if (!empty($params['sorting'])) {
                $this->applySorting($queryInstance, $params['sorting']);
            } else {
                // Apply default sorting
                $defaultSort = $queryInstance->getDefaultSort();
                $this->applySorting($queryInstance, $defaultSort);
            }
            
            // Execute query with pagination
            $total = $queryInstance->count(); // Get total before limiting
            
            if (isset($params['pagination']['startRow'])) {
                // AG-Grid style pagination
                $offset = $params['pagination']['startRow'];
                $limit = $params['pagination']['endRow'] - $offset;
            } else {
                // Standard pagination
                $page = $params['pagination']['page'] ?? 1;
                $pageSize = min($params['pagination']['pageSize'] ?? 20, 100);
                $offset = ($page - 1) * $pageSize;
                $limit = $pageSize;
            }
            
            $models = $queryInstance->limit($limit)->offset($offset)->find([]);
            $records = array_map(fn($model) => $model->toArray(), $models);
            
            // Build response metadata
            $paginationManager = new PaginationManager();
            $meta = [
                'pagination' => $paginationManager->buildPagination($records, $params['pagination'], $total),
                'filters' => [
                    'applied' => $params['filters'],
                    'available' => $queryInstance->getFilterableFields()
                ],
                'sorting' => [
                    'applied' => $params['sorting'],
                    'available' => $queryInstance->getSortableFields()
                ]
            ];
            
            // Format response based on detected format
            $formatter = new ResponseFormatter();
            return $formatter->format($records, $meta, $params['format']);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to list records with advanced filtering', [
                'model' => $modelName,
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw new GCException('Failed to list records', [], 500, $e);
        }
    }
}
```

## 7. React Query Parameter Specifications

### 7.1 Simple Format (Recommended for TanStack Query/SWR)
```
GET /Users?page=1&pageSize=20&search=john&sortBy=created_at&sortOrder=desc&role=admin&status=active
```

**Parameters:**
- `page`: Page number (1-based, default: 1)
- `pageSize`: Items per page (default: 20, max: 100)
- `search`: Global search term across searchable fields
- `sortBy`: Field to sort by
- `sortOrder`: Sort direction (`asc` or `desc`)
- `{fieldName}`: Direct field filtering (simple equality)

### 7.2 Advanced Format (Full Feature Set)
```
GET /Users?page=1&per_page=20&search=john&search_fields=first_name,last_name,email&filter[role]=admin&filter[age][gte]=18&filter[age][lte]=65&filter[status][in]=active,pending&sort=created_at:desc,name:asc&include_total=true&include_available_filters=true
```

**Filter Operators:**
- `filter[field]` = value (equals)
- `filter[field][eq]` = value (equals)
- `filter[field][contains]` = value (string contains)
- `filter[field][startsWith]` = value (string starts with)
- `filter[field][in]` = value1,value2 (in array)
- `filter[field][gte]` = value (greater than or equal)
- `filter[field][lte]` = value (less than or equal)
- `filter[field][between]` = min,max (between values)
- `filter[field][isNull]` = true (is null)

### 7.3 AG-Grid Format
```
GET /Users?startRow=0&endRow=100&filters[name][type]=contains&filters[name][filter]=john&filters[role][type]=equals&filters[role][filter]=admin&sort[0][colId]=created_at&sort[0][sort]=desc
```

**AG-Grid Specific:**
- `startRow`: Starting row index (0-based)
- `endRow`: Ending row index (exclusive)
- `filters[field][type]`: Filter type (`contains`, `equals`, `greaterThan`, etc.)
- `filters[field][filter]`: Filter value
- `sort[index][colId]`: Sort field
- `sort[index][sort]`: Sort direction

### 7.4 JSON Format (MUI DataGrid)
```
GET /Users?page=0&pageSize=25&filterModel={"role":"admin","age":{"gte":18,"lte":65}}&sortModel=[{"field":"created_at","sort":"desc"}]
```

**JSON Format:**
- `filterModel`: JSON string with filter configuration
- `sortModel`: JSON array with sort configuration
- `page`: 0-based page number

### 7.5 Cursor-Based Format (Infinite Scroll)
```
GET /Users?limit=20&cursor=eyJpZCI6MjB9&search=john&role=admin
```

**Cursor Parameters:**
- `limit`: Number of items to return
- `cursor`: Base64 encoded cursor for pagination
- `before`: Cursor for reverse pagination (optional)

## 7. Database Optimization

### 7.1 Metadata-Driven Index Strategy

Database indexes will be defined in model metadata and automatically managed by the SchemaGenerator class. This ensures indexes are created, updated, and dropped consistently across environments.

#### Index Definition in Model Metadata

```php
// Example User model with index definitions
class User extends ModelBase {
    protected function getMetadata(): array {
        return [
            // ... field definitions ...
            
            // Index definitions for performance optimization
            'indexes' => [
                // Single field indexes
                'idx_users_email' => ['email'],
                'idx_users_role' => ['role'],
                'idx_users_status' => ['status'],
                'idx_users_created_at' => ['created_at'],
                'idx_users_updated_at' => ['updated_at'],
                
                // Composite indexes for common filter combinations
                'idx_users_role_status' => ['role', 'status'],
                'idx_users_created_role' => ['created_at', 'role'],
                'idx_users_status_updated' => ['status', 'updated_at'],
                
                // Indexes for search functionality
                'idx_users_name_search' => ['first_name', 'last_name'],
                'idx_users_email_search' => ['email'],
                
                // Performance indexes for pagination
                'idx_users_pagination' => ['id', 'created_at'],
                
                // Unique constraints as indexes
                'idx_users_email_unique' => ['email'], // Can be marked as unique in SchemaGenerator
                'idx_users_username_unique' => ['username']
            ]
        ];
    }
}

// Example relationship model with foreign key indexes
class UserRole extends ModelBase {
    protected function getMetadata(): array {
        return [
            // ... field definitions ...
            
            'indexes' => [
                // Foreign key indexes for relationships
                'idx_user_roles_user_id' => ['user_id'],
                'idx_user_roles_role_id' => ['role_id'],
                
                // Composite foreign key index
                'idx_user_roles_composite' => ['user_id', 'role_id'],
                
                // Covering index for common queries
                'idx_user_roles_covering' => ['user_id', 'role_id', 'created_at']
            ]
        ];
    }
}

// Example with advanced index options
class Post extends ModelBase {
    protected function getMetadata(): array {
        return [
            // ... field definitions ...
            
            'indexes' => [
                // Standard indexes
                'idx_posts_author_id' => ['author_id'],
                'idx_posts_status' => ['status'],
                'idx_posts_published_at' => ['published_at'],
                
                // Full-text search index (database-specific)
                'idx_posts_fulltext' => [
                    'fields' => ['title', 'content', 'excerpt'],
                    'type' => 'fulltext'
                ],
                
                // Partial index with condition (PostgreSQL example)
                'idx_posts_published' => [
                    'fields' => ['published_at'],
                    'where' => 'status = "published"'
                ],
                
                // Covering index with included columns
                'idx_posts_covering' => [
                    'fields' => ['author_id', 'status'],
                    'includes' => ['title', 'published_at'] // PostgreSQL INCLUDE
                ]
            ]
        ];
    }
}
```

#### Enhanced SchemaGenerator for Index Management

```php
class SchemaGenerator {
    // ... existing methods ...
    
    /**
     * Create table with indexes
     */
    public function createTable(ModelBase $model): void {
        // Create the table structure first
        $this->createTableStructure($model);
        
        // Create indexes after table creation
        $this->createIndexesForModel($model);
        
        $this->logger->info("Created table with indexes", [
            'model' => get_class($model),
            'table' => $model->getTableName(),
            'indexes_created' => count($this->getIndexesFromMetadata($model))
        ]);
    }
    
    /**
     * Alter table and update indexes
     */
    public function alterTable(ModelBase $model, array $changes): void {
        // Apply table structure changes first
        $this->applyTableChanges($model, $changes);
        
        // Drop existing indexes that might conflict
        $this->dropIndexesForModel($model);
        
        // Recreate all indexes with current metadata
        $this->createIndexesForModel($model);
        
        $this->logger->info("Altered table and updated indexes", [
            'model' => get_class($model),
            'table' => $model->getTableName(),
            'changes' => $changes
        ]);
    }
    
    /**
     * Create all indexes defined in model metadata
     */
    public function createIndexesForModel(ModelBase $model): void {
        $tableName = $model->getTableName();
        $indexes = $this->getIndexesFromMetadata($model);
        
        foreach ($indexes as $indexName => $indexDefinition) {
            try {
                $this->createIndex($tableName, $indexName, $indexDefinition);
                
                $this->logger->info("Created index", [
                    'table' => $tableName,
                    'index' => $indexName,
                    'fields' => $indexDefinition['fields'] ?? $indexDefinition
                ]);
            } catch (\Exception $e) {
                $this->logger->warning("Failed to create index", [
                    'table' => $tableName,
                    'index' => $indexName,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Drop all indexes for a model
     */
    public function dropIndexesForModel(ModelBase $model): void {
        $tableName = $model->getTableName();
        $indexes = $this->getIndexesFromMetadata($model);
        
        foreach (array_keys($indexes) as $indexName) {
            try {
                $this->dropIndex($tableName, $indexName);
                
                $this->logger->info("Dropped index", [
                    'table' => $tableName,
                    'index' => $indexName
                ]);
            } catch (\Exception $e) {
                $this->logger->debug("Index may not exist or already dropped", [
                    'table' => $tableName,
                    'index' => $indexName,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Create a single index
     */
    public function createIndex(string $tableName, string $indexName, $indexDefinition): void {
        $sql = $this->buildCreateIndexSQL($tableName, $indexName, $indexDefinition);
        $this->connection->executeStatement($sql);
    }
    
    /**
     * Drop a single index
     */
    public function dropIndex(string $tableName, string $indexName): void {
        $sql = $this->buildDropIndexSQL($tableName, $indexName);
        $this->connection->executeStatement($sql);
    }
    
    /**
     * Build CREATE INDEX SQL statement
     */
    private function buildCreateIndexSQL(string $tableName, string $indexName, $indexDefinition): string {
        // Handle simple array format: ['field1', 'field2']
        if (is_array($indexDefinition) && !isset($indexDefinition['fields'])) {
            $fields = $indexDefinition;
            $type = 'btree';
            $unique = false;
            $where = null;
        } else {
            // Handle complex format with options
            $fields = $indexDefinition['fields'] ?? [];
            $type = $indexDefinition['type'] ?? 'btree';
            $unique = $indexDefinition['unique'] ?? false;
            $where = $indexDefinition['where'] ?? null;
        }
        
        $platform = $this->connection->getDatabasePlatform();
        
        if ($type === 'fulltext') {
            return $this->buildFullTextIndexSQL($tableName, $indexName, $fields, $platform);
        }
        
        $uniqueKeyword = $unique ? 'UNIQUE ' : '';
        $fieldsStr = implode(', ', array_map([$this, 'quoteIdentifier'], $fields));
        $whereClause = $where ? " WHERE $where" : '';
        
        switch ($platform->getName()) {
            case 'postgresql':
                $sql = "CREATE {$uniqueKeyword}INDEX {$indexName} ON {$tableName} ({$fieldsStr}){$whereClause}";
                break;
            case 'mysql':
                $sql = "CREATE {$uniqueKeyword}INDEX {$indexName} ON {$tableName} ({$fieldsStr})";
                break;
            default:
                $sql = "CREATE {$uniqueKeyword}INDEX {$indexName} ON {$tableName} ({$fieldsStr})";
        }
        
        return $sql;
    }
    
    /**
     * Build DROP INDEX SQL statement
     */
    private function buildDropIndexSQL(string $tableName, string $indexName): string {
        $platform = $this->connection->getDatabasePlatform();
        
        switch ($platform->getName()) {
            case 'postgresql':
                return "DROP INDEX IF EXISTS {$indexName}";
            case 'mysql':
                return "DROP INDEX {$indexName} ON {$tableName}";
            default:
                return "DROP INDEX {$indexName}";
        }
    }
    
    /**
     * Build full-text index SQL (database-specific)
     */
    private function buildFullTextIndexSQL(string $tableName, string $indexName, array $fields, $platform): string {
        $fieldsStr = implode(', ', array_map([$this, 'quoteIdentifier'], $fields));
        
        switch ($platform->getName()) {
            case 'mysql':
                return "CREATE FULLTEXT INDEX {$indexName} ON {$tableName} ({$fieldsStr})";
            case 'postgresql':
                // PostgreSQL uses GIN indexes for full-text search
                return "CREATE INDEX {$indexName} ON {$tableName} USING GIN (to_tsvector('english', {$fieldsStr}))";
            default:
                throw new \Exception("Full-text indexes not supported for database platform: " . $platform->getName());
        }
    }
    
    /**
     * Extract index definitions from model metadata
     */
    private function getIndexesFromMetadata(ModelBase $model): array {
        $metadata = $model->getMetadata();
        return $metadata['indexes'] ?? [];
    }
    
    /**
     * Quote database identifier
     */
    private function quoteIdentifier(string $identifier): string {
        return $this->connection->getDatabasePlatform()->quoteSingleIdentifier($identifier);
    }
    
    /**
     * Get existing indexes for a table (for comparison/migration)
     */
    public function getExistingIndexes(string $tableName): array {
        $schemaManager = $this->connection->createSchemaManager();
        $indexes = $schemaManager->listTableIndexes($tableName);
        
        $indexList = [];
        foreach ($indexes as $index) {
            $indexList[$index->getName()] = [
                'fields' => $index->getColumns(),
                'unique' => $index->isUnique(),
                'primary' => $index->isPrimary()
            ];
        }
        
        return $indexList;
    }
    
    /**
     * Compare and sync indexes (for migrations)
     */
    public function syncIndexesForModel(ModelBase $model): void {
        $tableName = $model->getTableName();
        $requiredIndexes = $this->getIndexesFromMetadata($model);
        $existingIndexes = $this->getExistingIndexes($tableName);
        
        // Drop indexes that are no longer defined
        foreach ($existingIndexes as $existingName => $existingDef) {
            if (!isset($requiredIndexes[$existingName]) && !$existingDef['primary']) {
                $this->dropIndex($tableName, $existingName);
                $this->logger->info("Dropped obsolete index", [
                    'table' => $tableName,
                    'index' => $existingName
                ]);
            }
        }
        
        // Create new indexes
        foreach ($requiredIndexes as $requiredName => $requiredDef) {
            if (!isset($existingIndexes[$requiredName])) {
                $this->createIndex($tableName, $requiredName, $requiredDef);
                $this->logger->info("Created new index", [
                    'table' => $tableName,
                    'index' => $requiredName
                ]);
            }
        }
    }
}
```

#### Integration with Enhanced Pagination System

The metadata-driven indexes will automatically optimize the filtering and sorting operations used by the enhanced pagination system:

```php
// In User model - indexes aligned with filtering/sorting needs
class User extends ModelBase {
    protected function getMetadata(): array {
        return [
            // ... field definitions ...
            
            'indexes' => [
                // Optimize common filters from React components
                'idx_users_role' => ['role'],           // For role filtering
                'idx_users_status' => ['status'],       // For status filtering
                'idx_users_age' => ['age'],             // For age range filtering
                
                // Optimize sorting operations
                'idx_users_created_at' => ['created_at'], // For created_at sorting
                'idx_users_name' => ['last_name', 'first_name'], // For name sorting
                
                // Optimize composite filters (common combinations)
                'idx_users_role_status' => ['role', 'status'],
                'idx_users_status_created' => ['status', 'created_at'],
                
                // Optimize search operations
                'idx_users_email_search' => ['email'],
                'idx_users_name_search' => ['first_name', 'last_name'],
                
                // Optimize pagination performance
                'idx_users_pagination' => ['id', 'created_at'] // Cursor-based pagination
            ]
        ];
    }
    
    protected function getSortableFields(): array {
        // These fields have corresponding indexes defined above
        return ['id', 'email', 'first_name', 'last_name', 'created_at', 'updated_at'];
    }
}
```

### 7.2 Query Optimization
- Use LIMIT/OFFSET efficiently
- Avoid COUNT(*) for large datasets when possible
- Implement query result caching for common searches
- Use prepared statements for all dynamic queries

## 8. React Integration Examples

### 8.1 TanStack Query (React Query) Integration

#### Basic Usage
```typescript
import { useQuery } from '@tanstack/react-query';

interface UserFilters {
  page?: number;
  pageSize?: number;
  search?: string;
  role?: string;
  status?: string;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

const useUsers = (filters: UserFilters) => {
  return useQuery({
    queryKey: ['users', filters],
    queryFn: () => fetchUsers(filters),
    keepPreviousData: true, // For smooth pagination
  });
};

// API call function
const fetchUsers = async (filters: UserFilters) => {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined) params.append(key, String(value));
  });
  
  const response = await fetch(`/api/Users?${params}`);
  return response.json();
};

// React component
const UserTable = () => {
  const [filters, setFilters] = useState<UserFilters>({ 
    page: 1, 
    pageSize: 20 
  });
  
  const { data, isLoading, error } = useUsers(filters);
  
  const handlePageChange = (newPage: number) => {
    setFilters(prev => ({ ...prev, page: newPage }));
  };
  
  const handleSearch = (search: string) => {
    setFilters(prev => ({ ...prev, search, page: 1 }));
  };
  
  if (isLoading) return <div>Loading...</div>;
  if (error) return <div>Error loading users</div>;
  
  return (
    <div>
      <SearchInput onSearch={handleSearch} />
      <UserGrid data={data.data} />
      <Pagination 
        current={data.meta.pagination.page}
        total={data.meta.pagination.pageCount}
        onChange={handlePageChange}
      />
    </div>
  );
};
```

#### Advanced Filtering
```typescript
interface AdvancedFilters {
  page?: number;
  pageSize?: number;
  search?: string;
  filters?: Record<string, any>;
  sort?: Array<{field: string, direction: 'asc' | 'desc'}>;
}

const useUsersAdvanced = (filters: AdvancedFilters) => {
  return useQuery({
    queryKey: ['users', 'advanced', filters],
    queryFn: () => fetchUsersAdvanced(filters),
  });
};

const fetchUsersAdvanced = async (filters: AdvancedFilters) => {
  const params = new URLSearchParams();
  
  // Basic pagination
  if (filters.page) params.append('page', String(filters.page));
  if (filters.pageSize) params.append('pageSize', String(filters.pageSize));
  if (filters.search) params.append('search', filters.search);
  
  // Advanced filters
  if (filters.filters) {
    Object.entries(filters.filters).forEach(([field, value]) => {
      if (typeof value === 'object') {
        Object.entries(value).forEach(([operator, operatorValue]) => {
          params.append(`filter[${field}][${operator}]`, String(operatorValue));
        });
      } else {
        params.append(`filter[${field}]`, String(value));
      }
    });
  }
  
  // Sorting
  if (filters.sort?.length) {
    const sortStr = filters.sort
      .map(s => `${s.field}:${s.direction}`)
      .join(',');
    params.append('sort', sortStr);
  }
  
  const response = await fetch(`/api/Users?${params}`);
  return response.json();
};
```

### 8.2 SWR Integration

```typescript
import useSWR from 'swr';

const useUsersSWR = (filters: UserFilters) => {
  const key = filters ? ['users', filters] : null;
  return useSWR(key, () => fetchUsers(filters));
};

// Infinite scroll with SWR
const useUsersInfinite = () => {
  return useSWRInfinite(
    (pageIndex, previousPageData) => {
      if (previousPageData && !previousPageData.meta.pagination.hasNextPage) {
        return null; // reached the end
      }
      return ['users', { page: pageIndex + 1, pageSize: 20 }];
    },
    ([_, filters]) => fetchUsers(filters)
  );
};
```

### 8.3 AG-Grid Integration

```typescript
import { useState, useCallback, useMemo } from 'react';
import { AgGridReact } from 'ag-grid-react';

const UserAgGrid = () => {
  const [gridApi, setGridApi] = useState(null);
  
  const onGridReady = useCallback((params) => {
    setGridApi(params.api);
  }, []);
  
  const dataSource = useMemo(() => ({
    rowCount: undefined, // behave as infinite scroll
    getRows: async (params) => {
      try {
        // Convert AG-Grid params to API format
        const queryParams = new URLSearchParams();
        queryParams.append('startRow', String(params.startRow));
        queryParams.append('endRow', String(params.endRow));
        
        // Filters
        if (params.filterModel) {
          Object.entries(params.filterModel).forEach(([field, filter]) => {
            queryParams.append(`filters[${field}][type]`, filter.type);
            queryParams.append(`filters[${field}][filter]`, filter.filter);
          });
        }
        
        // Sorting
        if (params.sortModel?.length) {
          params.sortModel.forEach((sort, index) => {
            queryParams.append(`sort[${index}][colId]`, sort.colId);
            queryParams.append(`sort[${index}][sort]`, sort.sort);
          });
        }
        
        const response = await fetch(`/api/Users?${queryParams}`);
        const data = await response.json();
        
        params.successCallback(data.data, data.lastRow);
      } catch (error) {
        params.failCallback();
      }
    }
  }), []);
  
  const columnDefs = [
    { field: 'id', sortable: true, filter: 'agNumberColumnFilter' },
    { field: 'first_name', sortable: true, filter: 'agTextColumnFilter' },
    { field: 'last_name', sortable: true, filter: 'agTextColumnFilter' },
    { field: 'email', sortable: true, filter: 'agTextColumnFilter' },
    { field: 'role', sortable: true, filter: 'agSetColumnFilter' },
    { field: 'created_at', sortable: true, filter: 'agDateColumnFilter' }
  ];
  
  return (
    <div className="ag-theme-alpine" style={{height: 600}}>
      <AgGridReact
        onGridReady={onGridReady}
        columnDefs={columnDefs}
        rowModelType="infinite"
        datasource={dataSource}
        cacheBlockSize={100}
        infiniteInitialRowCount={1000}
        maxBlocksInCache={10}
      />
    </div>
  );
};
```

### 8.4 Material-UI DataGrid Integration

```typescript
import { DataGrid, GridColDef, GridFilterModel, GridSortModel } from '@mui/x-data-grid';
import { useState, useCallback } from 'react';

const UserDataGrid = () => {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);
  const [rowCount, setRowCount] = useState(0);
  const [paginationModel, setPaginationModel] = useState({
    pageSize: 25,
    page: 0,
  });
  const [filterModel, setFilterModel] = useState<GridFilterModel>({ items: [] });
  const [sortModel, setSortModel] = useState<GridSortModel>([]);
  
  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.append('page', String(paginationModel.page));
      params.append('pageSize', String(paginationModel.pageSize));
      
      if (filterModel.items.length > 0) {
        params.append('filterModel', JSON.stringify(filterModel));
      }
      
      if (sortModel.length > 0) {
        params.append('sortModel', JSON.stringify(sortModel));
      }
      
      const response = await fetch(`/api/Users?${params}`);
      const data = await response.json();
      
      setRows(data.data);
      setRowCount(data.meta.pagination.total);
    } catch (error) {
      console.error('Failed to fetch data:', error);
    } finally {
      setLoading(false);
    }
  }, [paginationModel, filterModel, sortModel]);
  
  // Fetch data when parameters change
  useEffect(() => {
    fetchData();
  }, [fetchData]);
  
  const columns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 90 },
    { field: 'first_name', headerName: 'First Name', width: 150, filterable: true },
    { field: 'last_name', headerName: 'Last Name', width: 150, filterable: true },
    { field: 'email', headerName: 'Email', width: 200, filterable: true },
    { field: 'role', headerName: 'Role', width: 120, filterable: true },
    { field: 'created_at', headerName: 'Created', width: 180, type: 'dateTime' }
  ];
  
  return (
    <DataGrid
      rows={rows}
      columns={columns}
      paginationModel={paginationModel}
      onPaginationModelChange={setPaginationModel}
      filterModel={filterModel}
      onFilterModelChange={setFilterModel}
      sortModel={sortModel}
      onSortModelChange={setSortModel}
      rowCount={rowCount}
      loading={loading}
      paginationMode="server"
      sortingMode="server"
      filterMode="server"
      pageSizeOptions={[10, 25, 50, 100]}
    />
  );
};
```

### 8.5 Custom React Hook for Universal Data Fetching

```typescript
interface DataFetchOptions {
  library: 'tanstack-query' | 'swr' | 'custom';
  pagination: 'offset' | 'cursor';
  enableFilters?: boolean;
  enableSorting?: boolean;
  enableSearch?: boolean;
}

const useUniversalData = <T>(
  endpoint: string, 
  options: DataFetchOptions,
  initialFilters = {}
) => {
  const [filters, setFilters] = useState(initialFilters);
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState<T[]>([]);
  const [meta, setMeta] = useState(null);
  
  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const params = buildQueryParams(filters, options);
      const response = await fetch(`${endpoint}?${params}`);
      const result = await response.json();
      
      setData(result.data);
      setMeta(result.meta);
    } catch (error) {
      console.error('Data fetch error:', error);
    } finally {
      setLoading(false);
    }
  }, [endpoint, filters, options]);
  
  const updateFilter = useCallback((field: string, value: any) => {
    setFilters(prev => ({ ...prev, [field]: value }));
  }, []);
  
  const updatePagination = useCallback((pagination: any) => {
    setFilters(prev => ({ ...prev, ...pagination }));
  }, []);
  
  useEffect(() => {
    fetchData();
  }, [fetchData]);
  
  return {
    data,
    meta,
    loading,
    filters,
    updateFilter,
    updatePagination,
    refetch: fetchData
  };
};

// Helper function to build query parameters
const buildQueryParams = (filters: any, options: DataFetchOptions): string => {
  const params = new URLSearchParams();
  
  // Add pagination
  if (options.pagination === 'offset') {
    if (filters.page) params.append('page', String(filters.page));
    if (filters.pageSize) params.append('pageSize', String(filters.pageSize));
  } else {
    if (filters.limit) params.append('limit', String(filters.limit));
    if (filters.cursor) params.append('cursor', filters.cursor);
  }
  
  // Add search
  if (options.enableSearch && filters.search) {
    params.append('search', filters.search);
  }
  
  // Add filters
  if (options.enableFilters && filters.filters) {
    Object.entries(filters.filters).forEach(([field, value]) => {
      if (typeof value === 'object') {
        Object.entries(value).forEach(([op, val]) => {
          params.append(`filter[${field}][${op}]`, String(val));
        });
      } else {
        params.append(`filter[${field}]`, String(value));
      }
    });
  }
  
  // Add sorting
  if (options.enableSorting && filters.sort) {
    if (Array.isArray(filters.sort)) {
      const sortStr = filters.sort
        .map(s => `${s.field}:${s.direction}`)
        .join(',');
      params.append('sort', sortStr);
    }
  }
  
  return params.toString();
};
```

## 9. Testing Strategy

### 9.1 Unit Tests
- FilterCriteria class methods
- SearchEngine functionality
- PaginationManager calculations
- Query parameter parsing

### 9.2 Integration Tests
- End-to-end filtering scenarios
- Search functionality across different field types
- Pagination edge cases
- Performance tests with large datasets

### 9.3 Performance Tests
- Query performance with various filter combinations
- Large dataset pagination
- Memory usage during processing
- Response time benchmarks

## 10. Security Considerations

### 10.1 SQL Injection Prevention
- Use parameterized queries for all filters
- Validate filter operators against whitelist
- Sanitize sort field names
- Limit searchable fields per model

### 10.2 Access Control
- Respect field-level permissions in filters
- Hide sensitive fields from search/filter options
- Validate user permissions for requested data

### 10.3 Performance Protection
- Limit maximum per_page value (e.g., 100)
- Implement query timeout protection
- Rate limiting for expensive search queries
- Cache expensive filter option queries

## 11. Success Criteria

- [ ] React apps can implement efficient data tables
- [ ] Search works across configurable fields
- [ ] Filtering supports all major operators
- [ ] Pagination provides complete navigation info
- [ ] Performance remains good with large datasets
- [ ] SQL injection protection is complete
- [ ] Backward compatibility is maintained
- [ ] API responses include helpful metadata

## 12. Dependencies

### 12.1 Framework Components (Modified/Enhanced)
- **DatabaseConnector** (BREAKING CHANGES): Replace existing pagination methods
  - Remove: `applyQueryParameters()`, `getCount()` 
  - Add: `findWithReactParams()`, `getCountWithCriteria()`, new filter/search/sort methods
- **ModelBase** for model metadata and new configuration methods
- **ModelBaseAPIController** (BREAKING CHANGES): Complete replacement of `list()` method
- **RestApiHandler** for request processing (no changes needed)
- **Exception handling system** (no changes needed)

### 12.2 New Framework Components (To Be Created)
- **RequestParameterParser** - Multi-format query parameter parsing
- **FilterCriteria** - Advanced filtering with multiple operators  
- **SearchEngine** - Multi-field search functionality
- **PaginationManager** - Offset and cursor-based pagination
- **ResponseFormatter** - Multiple output formats for different React libraries

### 12.3 Database Requirements
- Support for complex WHERE clauses with multiple operators
- Full-text search capabilities (optional enhancement)
- Index optimization support for filtered/sorted fields
- Efficient LIMIT/OFFSET operations for large datasets

### 12.4 Breaking Changes Impact
- **DatabaseConnector**: Existing code using `applyQueryParameters()` or `getCount()` will break
- **ModelBaseAPIController**: Current `list()` method behavior changes completely
- **API Response Format**: New response structure (React-optimized)
- **Query Parameters**: New parameter formats (but supports multiple formats)

### 12.5 Migration Strategy
Since backward compatibility is **not required**, the implementation will:
1. Replace existing methods entirely
2. Update all internal framework usage to new methods
3. Provide comprehensive documentation for new parameter formats
4. Include React integration examples for smooth transition

## 13. Risks and Mitigations

### 13.1 Breaking Changes Risks
- **Risk**: Existing applications using current pagination may break
- **Mitigation**: Complete replacement approach eliminates partial compatibility issues; comprehensive documentation and examples provided

- **Risk**: DatabaseConnector method signature changes affect other framework components
- **Mitigation**: Update all internal framework usage during implementation; thorough testing of all dependent components

### 13.2 Performance Risks
- **Risk**: Slow queries with complex filters and joins
- **Mitigation**: Query optimization, strategic indexing, query analysis tools, performance testing

- **Risk**: Memory usage with large result sets
- **Mitigation**: Streaming responses, configurable result set limits, cursor-based pagination for large datasets

### 13.3 Security Risks
- **Risk**: SQL injection through dynamic filters and search parameters
- **Mitigation**: Parameterized queries for all dynamic content, strict input validation, operator whitelisting

- **Risk**: Performance-based DoS attacks through expensive queries
- **Mitigation**: Query timeout protection, rate limiting, maximum page size limits

### 13.4 Compatibility Risks
- **Risk**: React library changes affecting query parameter expectations
- **Mitigation**: Multi-format support, version detection, fallback mechanisms

- **Risk**: Complex filter combinations causing unexpected query behavior
- **Mitigation**: Comprehensive testing matrix, query logging, validation rules

### 13.5 Implementation Risks
- **Risk**: Complexity of supporting multiple React library formats
- **Mitigation**: Phased implementation, format detection, extensive testing with real React applications

- **Risk**: Database-specific SQL differences affecting advanced features
- **Mitigation**: Use Doctrine DBAL abstractions, database-specific testing, fallback implementations

### 6.5 Phase 5: Custom API Controller Retrofitting (Week 2, Days 4-5)

#### Step 1: Identify and Update All Custom Controllers
**BREAKING CHANGE**: Search and update all custom API controllers in the codebase:

```bash
# Search for all classes extending or implementing API controller patterns
find src/ -name "*.php" -exec grep -l "APIController\|Controller.*API" {} \;

# Search for methods with $additionalParams parameter
grep -r "\$additionalParams" src/ --include="*.php"

# Search for executeRoute calls to verify Router usage
grep -r "executeRoute" src/ --include="*.php"
```

#### Step 2: Custom Controller Method Signature Updates
Update any controllers beyond ModelBaseAPIController:

```php
// BEFORE: Custom controllers with old signatures
class UserAPIController extends ModelBaseAPIController {
    public function customUserAction(Request $request, array $additionalParams = []): array {
        $specialParam = $additionalParams['special_param'] ?? null;
        $searchTerm = $additionalParams['search'] ?? '';
        // ...
    }
    
    public function userStatistics(Request $request, array $additionalParams = []): array {
        $dateRange = $additionalParams['date_range'] ?? [];
        $groupBy = $additionalParams['group_by'] ?? 'day';
        // ...
    }
}

// AFTER: Updated signatures using Request object
class UserAPIController extends ModelBaseAPIController {
    public function customUserAction(Request $request): array {
        $specialParam = $request->getRequestParam('special_param');
        $searchTerm = $request->getRequestParam('search', '');
        // ...
    }
    
    public function userStatistics(Request $request): array {
        $dateRange = $request->getRequestParam('date_range', []);
        $groupBy = $request->getRequestParam('group_by', 'day');
        // ...
    }
}
```

#### Step 3: Update Any Direct Router Usage
Search for any direct Router instantiation and route calling:

```php
// BEFORE: Direct Router usage with old signature
$router = new Router($serviceLocator);
$result = $router->route('GET', '/api/custom', $additionalParams);

// AFTER: Updated to use new signature
$router = new Router($serviceLocator);
$result = $router->route('GET', '/api/custom', $requestData); // Now expects requestData
```

#### Step 4: Service Layer Updates
Update any service classes that interact with API controllers:

```php
// BEFORE: Service calling controller methods directly
class UserService {
    public function getUserList(array $params): array {
        $controller = new ModelBaseAPIController($this->logger);
        return $controller->list($request, $params); // Old signature
    }
}

// AFTER: Service using Request object pattern
class UserService {
    public function getUserList(array $params, string $path = '/api/users'): array {
        $request = new Request($path, [], 'GET');
        $request->setRequestData($params);
        
        // Attach helpers through Router or manually
        $this->attachRequestHelpers($request);
        
        $controller = new ModelBaseAPIController($this->logger);
        return $controller->list($request); // New signature
    }
    
    private function attachRequestHelpers(Request $request): void {
        // Same logic as Router::attachRequestHelpers()
        $parameterParser = new RequestParameterParser($request);
        $filterCriteria = new FilterCriteria($request);
        $searchEngine = new SearchEngine($request);
        $paginationManager = new PaginationManager($request);
        $responseFormatter = new ResponseFormatter($request);
        
        $request->setParameterParser($parameterParser);
        $request->setFilterCriteria($filterCriteria);
        $request->setSearchEngine($searchEngine);
        $request->setPaginationManager($paginationManager);
        $request->setResponseFormatter($responseFormatter);
        $request->setParsedParams($parameterParser->parseUnified());
    }
}
```

### 6.6 Phase 6: Documentation & Examples (Week 2, Day 5)

#### Step 1: Update API Documentation
Create comprehensive documentation for the Request object architecture:

```markdown
# Request Object Enhancement

## Overview
The Request object has been enhanced to consolidate all request data and provide direct access to pagination, filtering, search, and response formatting helpers.

## Breaking Changes
- All API controller methods now receive only a Request parameter (no $additionalParams)
- Router::executeRoute() no longer passes $additionalParams
- Request object contains all query parameters, POST data, and request body data

## Request Object Methods

### Data Access
- `getRequestData()`: Get all request data (query + body parameters)
- `getRequestParam(string $key, $default = null)`: Get specific request parameter
- `hasRequestParam(string $key)`: Check if parameter exists
- `getAllRequestParams()`: Alias for getRequestData()

### Helper Access
- `getParameterParser()`: Access parameter parsing functionality
- `getFilterCriteria()`: Access filter building functionality
- `getSearchEngine()`: Access search functionality
- `getPaginationManager()`: Access pagination functionality
- `getResponseFormatter()`: Access response formatting functionality

### Parsed Parameters
- `getFilters()`: Get parsed filter criteria
- `getSearchParams()`: Get parsed search parameters
- `getPaginationParams()`: Get parsed pagination settings
- `getSortingParams()`: Get parsed sorting configuration
- `getResponseFormat()`: Get detected response format

## Migration Guide

### Controller Method Updates
```php
// OLD
public function list(Request $request, array $additionalParams = []): array {
    $page = $additionalParams['page'] ?? 1;
    $search = $additionalParams['search'] ?? '';
}

// NEW
public function list(Request $request): array {
    $page = $request->getRequestParam('page', 1);
    $search = $request->getRequestParam('search', '');
    
    // Or use parsed parameters
    $paginationParams = $request->getPaginationParams();
    $searchParams = $request->getSearchParams();
}
```

### Router Usage
```php
// OLD
$router->route('GET', '/api/users', $additionalParams);

// NEW  
$router->route('GET', '/api/users', $requestData);
```
```

#### Step 2: Create Migration Checklist
Provide a comprehensive checklist for developers:

```markdown
# Enhanced Pagination Migration Checklist

## Phase 1: Request Object Enhancement
- [ ] Update Request class with requestData property and methods
- [ ] Update Router to set request data on Request object
- [ ] Update Router::executeRoute() to remove $additionalParams parameter
- [ ] Update Router::attachRequestHelpers() method
- [ ] Test Router with new Request object architecture

## Phase 2: Controller Updates
- [ ] Update ModelBaseAPIController::list() method signature
- [ ] Update ModelBaseAPIController::create() method signature  
- [ ] Update ModelBaseAPIController::update() method signature
- [ ] Update ModelBaseAPIController::delete() method signature
- [ ] Update ModelBaseAPIController::retrieve() method signature
- [ ] Update all other ModelBaseAPIController methods

## Phase 3: Custom Controller Updates
- [ ] Search for all custom controllers: `find src/ -name "*.php" -exec grep -l "APIController" {} \;`
- [ ] Update all custom controller method signatures
- [ ] Search for $additionalParams usage: `grep -r "\$additionalParams" src/`
- [ ] Update all $additionalParams references to use Request object

## Phase 4: DatabaseConnector Updates
- [ ] Remove DatabaseConnector::applyQueryParameters() (lines 814-845)
- [ ] Remove DatabaseConnector::getCount() (lines 470-512)
- [ ] Implement DatabaseConnector::findWithReactParams()
- [ ] Implement DatabaseConnector::getCountWithCriteria()
- [ ] Implement DatabaseConnector::applyAdvancedFilters()
- [ ] Implement DatabaseConnector::applyAdvancedSorting()
- [ ] Implement DatabaseConnector::applyOffsetPagination()
- [ ] Implement DatabaseConnector::applyCursorPagination()

## Phase 5: Service Layer Updates
- [ ] Search for direct controller usage: `grep -r "APIController" src/Services/`
- [ ] Update service classes calling API controllers directly
- [ ] Update any Router instantiation and usage

## Phase 6: Unit Test Updates
- [ ] Update RouterTest for new Request object architecture
- [ ] Update ModelBaseAPIControllerTest method signatures
- [ ] Update DatabaseConnectorTest for new pagination methods
- [ ] Remove tests for old applyQueryParameters() and getCount()
- [ ] Add tests for new React-compatible methods

## Phase 7: Integration Tests
- [ ] Update API integration tests for new response formats
- [ ] Test React library compatibility (AG-Grid, MUI, TanStack Query)
- [ ] Test performance with large datasets
- [ ] Test security with complex filter combinations

## Verification Steps
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Performance benchmarks meet requirements
- [ ] Security audit completed
- [ ] Documentation updated
- [ ] React integration examples created
```

## 7. Success Criteria

### 7.1 Functional Requirements Met
- ✅ Support for all major React data fetching libraries (TanStack Query, SWR, Apollo Client)
- ✅ Support for all major React grid components (AG-Grid, MUI DataGrid, React-Table)
- ✅ Multiple parameter format detection and parsing
- ✅ Advanced filtering with 15+ operators
- ✅ Multi-field search capabilities
- ✅ Multiple pagination strategies (offset, cursor, row-based)
- ✅ Comprehensive response metadata

### 7.2 Performance Requirements Met
- ✅ Sub-200ms response times for typical queries
- ✅ Efficient query generation with minimal database round trips
- ✅ Scalable pagination for large datasets (1M+ records)
- ✅ Optimized memory usage with streaming responses

### 7.3 Security Requirements Met
- ✅ All queries use parameterized statements
- ✅ Input validation for all filter and search parameters
- ✅ Query complexity limits to prevent DoS attacks
- ✅ Operator whitelisting for security

### 7.4 Architectural Improvements
- ✅ Clean Request object architecture with consolidated data access
- ✅ Elimination of $additionalParams pattern for cleaner controller signatures
- ✅ Helper classes properly encapsulated without circular dependencies
- ✅ Comprehensive unit test coverage for new architecture

## 8. React Integration Examples

### 8.1 TanStack Query (React Query)
```jsx
import { useQuery } from '@tanstack/react-query';

function UserList() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['users', { page: 1, search: 'john', status: 'active' }],
    queryFn: async ({ queryKey }) => {
      const [_key, params] = queryKey;
      const response = await fetch('/api/users?' + new URLSearchParams({
        page: params.page,
        pageSize: 20,
        search: params.search,
        'filter[0][field]': 'status',
        'filter[0][operator]': 'equals', 
        'filter[0][value]': params.status
      }));
      return response.json();
    }
  });

  if (isLoading) return <div>Loading...</div>;
  if (error) return <div>Error: {error.message}</div>;

  return (
    <div>
      {data.data.map(user => <div key={user.id}>{user.name}</div>)}
      <div>Total: {data.meta.pagination.total}</div>
    </div>
  );
}
```

### 8.2 AG-Grid Integration
```jsx
import { AgGridReact } from 'ag-grid-react';

function UserGrid() {
  const [rowData, setRowData] = useState([]);
  const [loading, setLoading] = useState(false);
  
  const datasource = {
    getRows: async (params) => {
      setLoading(true);
      
      try {
        const response = await fetch('/api/users', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            startRow: params.startRow,
            endRow: params.endRow,
            sortModel: params.sortModel,
            filterModel: params.filterModel
          })
        });
        
        const data = await response.json();
        
        params.successCallback(
          data.data,
          data.meta.pagination.total
        );
      } catch (error) {
        params.failCallback();
      } finally {
        setLoading(false);
      }
    }
  };

  return (
    <AgGridReact
      columnDefs={[
        { field: 'name', sortable: true, filter: 'agTextColumnFilter' },
        { field: 'email', sortable: true, filter: 'agTextColumnFilter' },
        { field: 'status', sortable: true, filter: 'agSetColumnFilter' }
      ]}
      rowModelType="infinite"
      datasource={datasource}
      cacheBlockSize={100}
      maxBlocksInCache={2}
      loading={loading}
    />
  );
}
```

### 8.3 MUI DataGrid Integration
```jsx
import { DataGrid } from '@mui/x-data-grid';

function UserDataGrid() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);
  const [rowCount, setRowCount] = useState(0);
  const [paginationModel, setPaginationModel] = useState({
    page: 0,
    pageSize: 25,
  });

  const fetchData = useCallback(async () => {
    setLoading(true);
    
    try {
      const params = new URLSearchParams({
        page: paginationModel.page,
        pageSize: paginationModel.pageSize
      });
      
      const response = await fetch(`/api/users?${params}`);
      const data = await response.json();
      
      setRows(data.data);
      setRowCount(data.meta.pagination.total);
    } catch (error) {
      console.error('Failed to fetch data:', error);
    } finally {
      setLoading(false);
    }
  }, [paginationModel]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  return (
    <DataGrid
      rows={rows}
      columns={[
        { field: 'name', headerName: 'Name', width: 200 },
        { field: 'email', headerName: 'Email', width: 250 },
        { field: 'status', headerName: 'Status', width: 150 }
      ]}
      paginationModel={paginationModel}
      onPaginationModelChange={setPaginationModel}
      rowCount={rowCount}
      loading={loading}
      pageSizeOptions={[25, 50, 100]}
      paginationMode="server"
    />
  );
}
```
