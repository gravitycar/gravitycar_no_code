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
    private array $sqlOperatorMap = [
        'equals' => '=',
        'notEquals' => '!=',
        'contains' => 'LIKE',
        'notContains' => 'NOT LIKE',
        'startsWith' => 'LIKE',
        'endsWith' => 'LIKE',
        'in' => 'IN',
        'notIn' => 'NOT IN',
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
        'between' => 'BETWEEN',
        'notBetween' => 'NOT BETWEEN',
        'before' => '<',
        'after' => '>',
        'isEmpty' => "= ''",
        'isNotEmpty' => "!= ''",
        'isNull' => 'IS NULL',
        'isNotNull' => 'IS NOT NULL',
        'isValidEmail' => 'REGEXP', // Database-specific implementation
        'hasFile' => 'IS NOT NULL', // For ImageField
        'exists' => 'EXISTS', // For RelatedRecordField
        'notExists' => 'NOT EXISTS', // For RelatedRecordField
        'containsAll' => 'JSON_CONTAINS', // For MultiEnumField - MySQL specific
        'containsAny' => 'JSON_OVERLAPS', // For MultiEnumField - MySQL specific
        'containsNone' => 'NOT JSON_OVERLAPS' // For MultiEnumField - MySQL specific
    ];
    
    private LoggerInterface $logger;
    
    public function __construct(Request $request) {
        // Setup logging for validation tracking
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Validate filters against model fields and their allowed operators
     * This is the core validation method called by Request::validateAllParameters()
     * Uses ParameterValidationException for error aggregation
     */
    public function validateAndFilterForModel(array $filters, ModelBase $model): array {
        $validatedFilters = [];
        $validationException = new ParameterValidationException();
        
        $this->logger->info("Starting filter validation", [
            'model' => get_class($model),
            'filter_count' => count($filters),
            'available_fields' => array_keys($model->getFields())
        ]);
        
        foreach ($filters as $filter) {
            $fieldName = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;
            
            // Validate field name is not empty
            if (empty($fieldName)) {
                $validationException->addError('filter', 'field', 'Filter field name cannot be empty');
                $this->logger->warning("Filter validation failed: Empty field name", ['filter' => $filter]);
                continue;
            }
            
            // Check if field exists in model using ModelBase method
            if (!$model->hasField($fieldName)) {
                $validationException->addError('filter', $fieldName, "Field '{$fieldName}' does not exist in model");
                $this->logger->warning("Filter validation failed: Field '{$fieldName}' not found in model", [
                    'model' => get_class($model),
                    'available_fields' => array_keys($model->getFields()),
                    'requested_field' => $fieldName
                ]);
                continue; // Skip invalid field
            }
            
            // Get field instance using ModelBase method
            $fieldInstance = $model->getField($fieldName);
            if (!$fieldInstance) {
                $validationException->addError('filter', $fieldName, "Could not retrieve field instance for '{$fieldName}'");
                $this->logger->warning("Filter validation failed: Could not retrieve field instance for '{$fieldName}'", [
                    'model' => get_class($model),
                    'field_name' => $fieldName
                ]);
                continue; // Skip if field instance not available
            }
            
            // CRITICAL: Only allow filtering on database fields
            if (!$fieldInstance->isDBField()) {
                $validationException->addError('filter', $fieldName, "Field '{$fieldName}' is not a database field and cannot be filtered");
                $this->logger->warning("Filter validation failed: Field '{$fieldName}' is not a database field", [
                    'field_name' => $fieldName,
                    'field_class' => get_class($fieldInstance),
                    'model' => get_class($model)
                ]);
                continue; // Skip non-database fields
            }
            
            // Check if field allows filtering using field-level configuration
            if (!$fieldInstance->isFilterable()) {
                $validationException->addError('filter', $fieldName, "Field '{$fieldName}' is not configured to allow filtering");
                $this->logger->warning("Filter validation failed: Field '{$fieldName}' is not filterable", [
                    'field_name' => $fieldName,
                    'field_class' => get_class($fieldInstance),
                    'model' => get_class($model)
                ]);
                continue; // Skip non-filterable fields
            }
            
            // Get allowed operators for this specific field instance
            $allowedOperators = $fieldInstance->getOperators();
            
            // Validate operator is allowed for this field
            if (!in_array($operator, $allowedOperators)) {
                $validationException->addError('filter', $fieldName, 
                    "Operator '{$operator}' not allowed for field '{$fieldName}'. Allowed operators: " . implode(', ', $allowedOperators));
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
                $validationException->addError('filter', $fieldName, "Operator '{$operator}' not implemented in SQL mapping");
                $this->logger->warning("Filter validation failed: Operator '{$operator}' not implemented in SQL mapping", [
                    'operator' => $operator,
                    'field_name' => $fieldName,
                    'available_operators' => array_keys($this->sqlOperatorMap)
                ]);
                continue; // Skip unimplemented operator
            }
            
            // Skip null/empty operators that don't need values
            $nullOperators = ['isNull', 'isNotNull', 'isEmpty', 'isNotEmpty', 'hasFile'];
            if (!in_array($operator, $nullOperators)) {
                // Validate value using field's validation method
                if (!$fieldInstance->isValidFilterValue($value, $operator)) {
                    $validationException->addError('filter', $fieldName, 
                        "Invalid filter value '{$value}' for operator '{$operator}' on field '{$fieldName}'");
                    $this->logger->warning("Filter validation failed: Value invalid for field '{$fieldName}' with operator '{$operator}'", [
                        'field_name' => $fieldName,
                        'operator' => $operator,
                        'value' => $value,
                        'field_class' => get_class($fieldInstance)
                    ]);
                    continue; // Skip invalid value
                }
            }
            
            // Normalize/convert value using field's method
            $normalizedValue = $fieldInstance->normalizeFilterValue($value, $operator);
            
            $validatedFilters[] = [
                'field' => $fieldName,
                'operator' => $operator,
                'value' => $normalizedValue,
                'sql_operator' => $this->sqlOperatorMap[$operator],
                'field_instance' => $fieldInstance // Keep reference for advanced operations
            ];
            
            $this->logger->info("Filter validation passed", [
                'field' => $fieldName,
                'operator' => $operator,
                'value' => $normalizedValue,
                'field_class' => get_class($fieldInstance)
            ]);
        }
        
        // Throw aggregated validation errors if any exist
        if ($validationException->hasErrors()) {
            $this->logger->warning("Filter validation failed", [
                'total_errors' => count($validationException->getErrors()),
                'error_summary' => $validationException->getErrorCountByType()
            ]);
            throw $validationException;
        }
        
        $this->logger->info("Filter validation complete", [
            'original_count' => count($filters),
            'validated_count' => count($validatedFilters)
        ]);
        
        return $validatedFilters;
    }
    
    /**
     * Apply validated filters to QueryBuilder
     * All filters passed to this method are already validated and safe
     */
    public function applyToQuery(QueryBuilder $qb, array $validatedFilters, string $mainAlias): void {
        foreach ($validatedFilters as $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];
            $fieldInstance = $filter['field_instance'];
            
            $columnName = "$mainAlias.$field";
            $paramName = "filter_" . str_replace(['.', '-'], '_', $field) . '_' . uniqid();
            
            switch ($operator) {
                case 'equals':
                case 'notEquals':
                    $sqlOp = $operator === 'equals' ? '=' : '!=';
                    $qb->andWhere("$columnName $sqlOp :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'contains':
                case 'notContains':
                    $sqlOp = $operator === 'contains' ? 'LIKE' : 'NOT LIKE';
                    $qb->andWhere("$columnName $sqlOp :$paramName");
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
                case 'notIn':
                    $sqlOp = $operator === 'in' ? 'IN' : 'NOT IN';
                    $qb->andWhere("$columnName $sqlOp (:$paramName)");
                    $qb->setParameter($paramName, is_array($value) ? $value : [$value], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                    break;
                    
                case 'gt':
                case 'gte':
                case 'lt':
                case 'lte':
                    $sqlOp = $this->sqlOperatorMap[$operator];
                    $qb->andWhere("$columnName $sqlOp :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'between':
                case 'notBetween':
                    $sqlOp = $operator === 'between' ? 'BETWEEN' : 'NOT BETWEEN';
                    if (is_array($value) && count($value) >= 2) {
                        $qb->andWhere("$columnName $sqlOp :${paramName}_start AND :${paramName}_end");
                        $qb->setParameter("${paramName}_start", $value[0]);
                        $qb->setParameter("${paramName}_end", $value[1]);
                    }
                    break;
                    
                case 'before':
                case 'after':
                    $sqlOp = $operator === 'before' ? '<' : '>';
                    $qb->andWhere("$columnName $sqlOp :$paramName");
                    $qb->setParameter($paramName, $value);
                    break;
                    
                case 'isEmpty':
                case 'isNotEmpty':
                    $sqlOp = $operator === 'isEmpty' ? "= ''" : "!= ''";
                    $qb->andWhere("$columnName $sqlOp");
                    break;
                    
                case 'isNull':
                case 'isNotNull':
                    $sqlOp = $operator === 'isNull' ? 'IS NULL' : 'IS NOT NULL';
                    $qb->andWhere("$columnName $sqlOp");
                    break;
                    
                case 'isValidEmail':
                    // Database-specific email validation
                    $platform = $qb->getConnection()->getDatabasePlatform()->getName();
                    if ($platform === 'mysql') {
                        $qb->andWhere("$columnName REGEXP :$paramName");
                        $qb->setParameter($paramName, '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$');
                    } else {
                        // Fallback for other databases
                        $qb->andWhere("$columnName LIKE :$paramName");
                        $qb->setParameter($paramName, '%@%.%');
                    }
                    break;
                    
                case 'hasFile':
                    // For ImageField - check if file path is not null and not empty
                    $qb->andWhere("$columnName IS NOT NULL AND $columnName != ''");
                    break;
                    
                case 'exists':
                case 'notExists':
                    // For RelatedRecordField - check if foreign key relationship exists
                    if ($fieldInstance instanceof RelatedRecordField) {
                        $relatedModel = $fieldInstance->getRelatedModel();
                        $foreignKey = $fieldInstance->getForeignKey();
                        $relatedTable = $relatedModel::getTableName();
                        
                        $existsOp = $operator === 'exists' ? 'EXISTS' : 'NOT EXISTS';
                        $qb->andWhere("$existsOp (SELECT 1 FROM $relatedTable WHERE id = $mainAlias.$foreignKey)");
                    }
                    break;
                    
                case 'containsAll':
                case 'containsAny':
                case 'containsNone':
                    // For MultiEnumField - JSON operations (MySQL specific)
                    if ($fieldInstance instanceof MultiEnumField) {
                        $platform = $qb->getConnection()->getDatabasePlatform()->getName();
                        if ($platform === 'mysql') {
                            switch ($operator) {
                                case 'containsAll':
                                    $qb->andWhere("JSON_CONTAINS($columnName, :$paramName)");
                                    break;
                                case 'containsAny':
                                    $qb->andWhere("JSON_OVERLAPS($columnName, :$paramName)");
                                    break;
                                case 'containsNone':
                                    $qb->andWhere("NOT JSON_OVERLAPS($columnName, :$paramName)");
                                    break;
                            }
                            $qb->setParameter($paramName, json_encode(is_array($value) ? $value : [$value]));
                        }
                    }
                    break;
                    
                default:
                    $this->logger->warning("Unhandled filter operator in query building", [
                        'operator' => $operator,
                        'field' => $field
                    ]);
                    break;
            }
        }
    }
    
    /**
     * Validate filters without model context (basic structure validation)
     * Used for early validation before model is known
     */
    public function validateFilters(array $filters, string $model): bool {
        try {
            foreach ($filters as $filter) {
                if (!is_array($filter)) {
                    return false;
                }
                
                if (!isset($filter['field']) || empty($filter['field'])) {
                    return false;
                }
                
                if (!isset($filter['operator'])) {
                    return false;
                }
                
                // Check if operator exists in our mapping
                if (!isset($this->sqlOperatorMap[$filter['operator']])) {
                    return false;
                }
            }
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Filter validation error", [
                'error' => $e->getMessage(),
                'model' => $model
            ]);
            return false;
        }
    }
    
    /**
     * Get all filterable fields for a model with their supported operators
     */
    public function getSupportedFilters(string $modelName): array {
        try {
            $modelInstance = ModelFactory::new($modelName);
            $supportedFilters = [];
            
            // Use ModelBase methods to iterate through fields
            foreach ($modelInstance->getFields() as $fieldName => $fieldInstance) {
                // Only include database fields that can be filtered
                if ($modelInstance->hasField($fieldName) && $fieldInstance->isDBField()) {
                    $supportedFilters[$fieldName] = [
                        'type' => get_class($fieldInstance),
                        'operators' => $fieldInstance->getOperators(),
                        'description' => $this->getFieldDescription($fieldInstance)
                    ];
                }
            }
            
            return $supportedFilters;
        } catch (\Exception $e) {
            $this->logger->error("Error getting supported filters", [
                'model' => $modelName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Parse AG-Grid filter model into standard filter format
     */
    public function parseAgGridFilters(array $filterModel): array {
        $filters = [];
        
        foreach ($filterModel as $field => $filterConfig) {
            if (!is_array($filterConfig)) {
                continue;
            }
            
            $type = $filterConfig['type'] ?? 'text';
            $filter = $filterConfig['filter'] ?? null;
            
            if ($filter === null) {
                continue;
            }
            
            // Map AG-Grid filter types to our operators
            $operator = $this->mapAgGridFilterType($type, $filterConfig);
            
            $filters[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => $filter
            ];
        }
        
        return $filters;
    }
    
    /**
     * Parse MUI DataGrid filter model (JSON string) into standard format
     */
    public function parseMuiFilters(string $filterModelJson): array {
        $filters = [];
        
        try {
            $filterModel = json_decode($filterModelJson, true);
            if (!is_array($filterModel)) {
                return [];
            }
            
            foreach ($filterModel as $field => $filterConfig) {
                if (is_string($filterConfig)) {
                    // Simple field = value format
                    $filters[] = [
                        'field' => $field,
                        'operator' => 'equals',
                        'value' => $filterConfig
                    ];
                } elseif (is_array($filterConfig)) {
                    // Complex filter with operators
                    foreach ($filterConfig as $operator => $value) {
                        $filters[] = [
                            'field' => $field,
                            'operator' => $this->mapMuiOperator($operator),
                            'value' => $value
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Error parsing MUI filters", [
                'json' => $filterModelJson,
                'error' => $e->getMessage()
            ]);
        }
        
        return $filters;
    }
    
    /**
     * Map AG-Grid filter types to our operator names
     */
    private function mapAgGridFilterType(string $type, array $config): string {
        $typeMap = [
            'text' => 'contains',
            'equals' => 'equals',
            'notEqual' => 'notEquals',
            'contains' => 'contains',
            'notContains' => 'notContains',
            'startsWith' => 'startsWith',
            'endsWith' => 'endsWith',
            'number' => 'equals',
            'greaterThan' => 'gt',
            'greaterThanOrEqual' => 'gte',
            'lessThan' => 'lt',
            'lessThanOrEqual' => 'lte',
            'inRange' => 'between',
            'date' => 'equals',
            'dateEquals' => 'equals',
            'dateBefore' => 'before',
            'dateAfter' => 'after'
        ];
        
        return $typeMap[$type] ?? 'equals';
    }
    
    /**
     * Map MUI DataGrid operators to our operator names
     */
    private function mapMuiOperator(string $muiOperator): string {
        $operatorMap = [
            'eq' => 'equals',
            'neq' => 'notEquals',
            'gt' => 'gt',
            'gte' => 'gte',
            'lt' => 'lt',
            'lte' => 'lte',
            'contains' => 'contains',
            'startsWith' => 'startsWith',
            'endsWith' => 'endsWith',
            'is' => 'equals',
            'not' => 'notEquals',
            'isAnyOf' => 'in'
        ];
        
        return $operatorMap[$muiOperator] ?? 'equals';
    }
    
    /**
     * Get human-readable description for a field type
     */
    private function getFieldDescription(FieldBase $field): string {
        $descriptions = [
            'TextField' => 'Text field supporting string operations',
            'BigTextField' => 'Large text field with limited operators for performance',
            'IntegerField' => 'Integer field supporting numeric comparisons',
            'FloatField' => 'Float field supporting numeric comparisons',
            'BooleanField' => 'Boolean field supporting true/false values',
            'DateField' => 'Date field supporting date comparisons',
            'DateTimeField' => 'DateTime field supporting timestamp comparisons',
            'EmailField' => 'Email field with validation support',
            'PasswordField' => 'Password field with limited operators for security',
            'EnumField' => 'Enumeration field with predefined values',
            'MultiEnumField' => 'Multiple selection enumeration field',
            'RadioButtonSetField' => 'Radio button selection field',
            'IDField' => 'ID field for primary/foreign keys',
            'ImageField' => 'Image upload field',
            'RelatedRecordField' => 'Foreign key relationship field'
        ];
        
        $className = get_class($field);
        $shortName = substr($className, strrpos($className, '\\') + 1);
        
        return $descriptions[$shortName] ?? 'Custom field type';
    }
}

// Search Engine
class SearchEngine {
    private LoggerInterface $logger;
    private array $searchOperators = [
        'contains', 'startsWith', 'endsWith', 'equals', 'fullText'
    ];
    
    public function __construct(Request $request) {
        // Setup logging for search query tracking
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Build search query conditions across multiple fields
     */
    public function buildSearchQuery(QueryBuilder $qb, string $searchTerm, array $searchFields, string $mainAlias): void {
        if (empty($searchTerm) || empty($searchFields)) {
            return;
        }
        
        $searchConditions = [];
        $paramCounter = 0;
        
        foreach ($searchFields as $fieldConfig) {
            $fieldName = is_array($fieldConfig) ? $fieldConfig['field'] : $fieldConfig;
            $searchType = is_array($fieldConfig) ? ($fieldConfig['type'] ?? 'contains') : 'contains';
            $weight = is_array($fieldConfig) ? ($fieldConfig['weight'] ?? 1.0) : 1.0;
            
            $paramName = "search_param_{$paramCounter}";
            $paramCounter++;
            
            switch ($searchType) {
                case 'contains':
                    $searchConditions[] = "{$mainAlias}.{$fieldName} LIKE :{$paramName}";
                    $qb->setParameter($paramName, "%{$searchTerm}%");
                    break;
                    
                case 'startsWith':
                    $searchConditions[] = "{$mainAlias}.{$fieldName} LIKE :{$paramName}";
                    $qb->setParameter($paramName, "{$searchTerm}%");
                    break;
                    
                case 'endsWith':
                    $searchConditions[] = "{$mainAlias}.{$fieldName} LIKE :{$paramName}";
                    $qb->setParameter($paramName, "%{$searchTerm}");
                    break;
                    
                case 'equals':
                    $searchConditions[] = "{$mainAlias}.{$fieldName} = :{$paramName}";
                    $qb->setParameter($paramName, $searchTerm);
                    break;
                    
                case 'fullText':
                    // MySQL full-text search
                    $searchConditions[] = "MATCH({$mainAlias}.{$fieldName}) AGAINST(:{$paramName} IN NATURAL LANGUAGE MODE)";
                    $qb->setParameter($paramName, $searchTerm);
                    break;
                    
                default:
                    $this->logger->warning("Unknown search type: {$searchType} for field: {$fieldName}");
            }
        }
        
        if (!empty($searchConditions)) {
            $qb->andWhere('(' . implode(' OR ', $searchConditions) . ')');
            
            $this->logger->info("Search query built", [
                'search_term' => $searchTerm,
                'fields_count' => count($searchFields),
                'conditions_count' => count($searchConditions)
            ]);
        }
    }
    
    /**
     * Validate search parameters against model fields
     * Uses ParameterValidationException for error aggregation
     */
    public function validateSearchForModel(array $searchParams, ModelBase $model): array {
        $validatedSearch = [];
        $validationException = new ParameterValidationException();
        
        // Validate search term
        if (isset($searchParams['term']) && !empty($searchParams['term'])) {
            $searchTerm = trim($searchParams['term']);
            if (strlen($searchTerm) < 1) {
                $validationException->addError('search', 'term', 'Search term cannot be empty');
            } elseif (strlen($searchTerm) > 1000) {
                $validationException->addError('search', 'term', 'Search term exceeds maximum length of 1000 characters');
            } else {
                $validatedSearch['term'] = $searchTerm;
            }
        }
        
        // Validate and filter search fields
        $requestedFields = $searchParams['fields'] ?? [];
        $availableFields = $this->getSearchableFields($model);
        
        if (empty($requestedFields)) {
            // Use default searchable fields if none specified
            $validatedSearch['fields'] = array_keys($availableFields);
        } else {
            $validFields = [];
            foreach ($requestedFields as $fieldName) {
                if (isset($availableFields[$fieldName])) {
                    $validFields[] = $fieldName;
                } else {
                    $validationException->addError('search', $fieldName, "Field '{$fieldName}' is not searchable");
                    $this->logger->warning("Search field not searchable", [
                        'field' => $fieldName,
                        'model' => get_class($model)
                    ]);
                }
            }
            $validatedSearch['fields'] = $validFields;
            
            // Check if any valid fields remain
            if (empty($validFields) && !empty($requestedFields)) {
                $validationException->addError('search', 'fields', 'None of the requested search fields are searchable');
            }
        }
        
        // Validate search type
        $searchType = $searchParams['type'] ?? 'contains';
        if (in_array($searchType, $this->searchOperators)) {
            $validatedSearch['type'] = $searchType;
        } else {
            $validationException->addError('search', 'type', 
                "Invalid search type '{$searchType}'. Available types: " . implode(', ', $this->searchOperators));
            $validatedSearch['type'] = 'contains'; // Default fallback
            $this->logger->warning("Invalid search type, defaulting to 'contains'", [
                'requested_type' => $searchType,
                'available_types' => $this->searchOperators
            ]);
        }
        
        // Throw aggregated validation errors if any exist
        if ($validationException->hasErrors()) {
            $this->logger->warning("Search validation failed", [
                'total_errors' => count($validationException->getErrors()),
                'error_summary' => $validationException->getErrorCountByType()
            ]);
            throw $validationException;
        }
        
        return $validatedSearch;
    }
    
    /**
     * Get all searchable fields for a model with their search configuration
     */
    public function getSearchableFields(ModelBase $model): array {
        $searchableFields = [];
        $fields = $model->getFields();
        
        foreach ($fields as $fieldName => $field) {
            // Only include database fields that are searchable
            if (!$field->isDBField()) {
                continue;
            }
            
            // Use field-level configuration to determine searchability
            if (!$field->isSearchable()) {
                continue;
            }
            
            $fieldType = get_class($field);
            $shortName = substr($fieldType, strrpos($fieldType, '\\') + 1);
            
            // Determine search configuration based on field type
            $searchTypes = [];
            $weight = 1.0;
            
            switch ($shortName) {
                case 'TextField':
                case 'EmailField':
                    $searchTypes = ['contains', 'startsWith', 'endsWith', 'equals'];
                    $weight = 1.0;
                    break;
                    
                case 'BigTextField':
                    // BigTextField can be searchable if explicitly enabled
                    $searchTypes = ['contains', 'fulltext'];
                    $weight = 0.8; // Lower weight for performance reasons
                    break;
                    
                case 'IntegerField':
                case 'FloatField':
                case 'IDField':
                    $searchTypes = ['equals'];
                    $weight = 1.2; // Higher weight for exact matches
                    break;
                    
                case 'DateField':
                case 'DateTimeField':
                    $searchTypes = ['equals'];
                    $weight = 1.0;
                    break;
                    
                case 'BooleanField':
                    $searchTypes = ['equals'];
                    $weight = 1.0;
                    break;
                    
                case 'EnumField':
                case 'RadioButtonSetField':
                    $searchTypes = ['equals'];
                    $weight = 1.1;
                    break;
                    
                case 'MultiEnumField':
                    $searchTypes = ['contains', 'equals'];
                    $weight = 0.9;
                    break;
                    
                case 'PasswordField':
                case 'ImageField':
                    // These field types override isSearchable to false in their class definition
                    $searchTypes = [];
                    break;
                    
                case 'RelatedRecordField':
                    // RelatedRecord fields have limited search capabilities
                    $searchTypes = ['equals'];
                    $weight = 0.7; // Lower weight for relationship searches
                    break;
                    
                default:
                    // Custom field types - basic search capability
                    $searchTypes = ['contains', 'equals'];
                    $weight = 0.8;
            }
            
            // Allow metadata to override search configuration
            $metadataSearchTypes = $field->getMetadataValue('searchTypes');
            if (is_array($metadataSearchTypes)) {
                $searchTypes = array_intersect($metadataSearchTypes, $this->searchOperators);
            }
            
            $metadataWeight = $field->getMetadataValue('searchWeight');
            if (is_numeric($metadataWeight)) {
                $weight = (float) $metadataWeight;
            }
            
            if (!empty($searchTypes)) {
                $searchableFields[$fieldName] = [
                    'field' => $fieldName,
                    'types' => $searchTypes,
                    'weight' => $weight,
                    'description' => $this->getFieldDescription($field)
                ];
            }
        }
        
        return $searchableFields;
    }
    
    /**
     * Parse search term into components for advanced search features
     */
    public function parseSearchTerm(string $term): array {
        $parsedTerm = [
            'original' => $term,
            'cleaned' => trim($term),
            'words' => [],
            'phrases' => [],
            'operators' => []
        ];
        
        // Extract quoted phrases
        if (preg_match_all('/"([^"]+)"/', $term, $matches)) {
            $parsedTerm['phrases'] = $matches[1];
            // Remove phrases from term for word extraction
            $term = preg_replace('/"[^"]+"/', '', $term);
        }
        
        // Extract individual words (3+ characters)
        $words = array_filter(
            explode(' ', $term),
            function($word) {
                return strlen(trim($word)) >= 2;
            }
        );
        $parsedTerm['words'] = array_map('trim', $words);
        
        // Detect boolean operators (future enhancement)
        if (strpos($parsedTerm['original'], ' AND ') !== false) {
            $parsedTerm['operators'][] = 'AND';
        }
        if (strpos($parsedTerm['original'], ' OR ') !== false) {
            $parsedTerm['operators'][] = 'OR';
        }
        if (strpos($parsedTerm['original'], ' NOT ') !== false) {
            $parsedTerm['operators'][] = 'NOT';
        }
        
        return $parsedTerm;
    }
    
    /**
     * Build MySQL full-text search query (requires FULLTEXT indexes)
     */
    public function buildFullTextSearch(QueryBuilder $qb, string $searchTerm, array $fullTextFields, string $mainAlias): void {
        if (empty($searchTerm) || empty($fullTextFields)) {
            return;
        }
        
        $fullTextColumns = [];
        foreach ($fullTextFields as $field) {
            $fullTextColumns[] = "{$mainAlias}.{$field}";
        }
        
        $columnsString = implode(', ', $fullTextColumns);
        $qb->andWhere("MATCH({$columnsString}) AGAINST(:fulltext_term IN NATURAL LANGUAGE MODE)")
           ->setParameter('fulltext_term', $searchTerm);
        
        $this->logger->info("Full-text search query built", [
            'search_term' => $searchTerm,
            'fields' => $fullTextFields
        ]);
    }
    
    /**
     * Get human-readable description for a field type
     */
    private function getFieldDescription(FieldBase $field): string {
        $fieldType = get_class($field);
        $shortName = substr($fieldType, strrpos($fieldType, '\\') + 1);
        
        $descriptions = [
            'TextField' => 'Text field supporting partial matches',
            'BigTextField' => 'Large text field (full-text search available)',
            'EmailField' => 'Email field with text search capabilities',
            'IntegerField' => 'Integer field supporting exact matches',
            'FloatField' => 'Decimal number field supporting exact matches',
            'IDField' => 'ID field supporting exact matches',
            'DateField' => 'Date field supporting exact date matches',
            'DateTimeField' => 'Date/time field supporting exact matches',
            'BooleanField' => 'Boolean field (true/false)',
            'EnumField' => 'Selection field with predefined options',
            'MultiEnumField' => 'Multiple selection field',
            'RadioButtonSetField' => 'Radio button selection field',
            'RelatedRecordField' => 'Related record reference'
        ];
        
        return $descriptions[$shortName] ?? 'Custom field type';
    }
}

// Sorting Manager - handles multi-field sorting with validation
class SortingManager {
    private LoggerInterface $logger;
    private array $validDirections = ['asc', 'desc'];
    private const DEFAULT_DIRECTION = 'asc';
    private const MAX_SORT_FIELDS = 10; // Prevent excessive sorting overhead
    
    public function __construct(Request $request) {
        // Setup logging for sorting operation tracking
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Validate sorting parameters against model fields
     * Uses ParameterValidationException for error aggregation
     */
    public function validateSortingForModel(array $sorting, ModelBase $model): array {
        $validatedSorting = [];
        $validationException = new ParameterValidationException();
        
        $this->logger->info("Starting sorting validation", [
            'model' => get_class($model),
            'sort_count' => count($sorting),
            'available_fields' => array_keys($model->getFields())
        ]);
        
        // Check maximum sort fields limit
        if (count($sorting) > self::MAX_SORT_FIELDS) {
            $validationException->addError('sorting', 'fields', 
                "Too many sort fields. Maximum allowed: " . self::MAX_SORT_FIELDS);
        }
        
        foreach ($sorting as $index => $sort) {
            $fieldName = $sort['field'] ?? null;
            $direction = strtolower($sort['direction'] ?? self::DEFAULT_DIRECTION);
            $priority = $sort['priority'] ?? $index + 1;
            
            // Validate field name
            if (empty($fieldName)) {
                $validationException->addError('sorting', 'field', 'Sorting field name cannot be empty');
                continue;
            }
            
            // Check if field exists in model
            if (!$model->hasField($fieldName)) {
                $validationException->addError('sorting', $fieldName, 
                    "Field '{$fieldName}' does not exist in model");
                continue;
            }
            
            // Get field instance for validation
            $fieldInstance = $model->getField($fieldName);
            if (!$fieldInstance) {
                $validationException->addError('sorting', $fieldName, 
                    "Could not retrieve field instance for '{$fieldName}'");
                continue;
            }
            
            // CRITICAL: Only allow sorting on database fields
            if (!$fieldInstance->isDBField()) {
                $validationException->addError('sorting', $fieldName, 
                    "Field '{$fieldName}' is not a database field and cannot be sorted");
                continue;
            }
            
            // Check if field allows sorting (some field types may disable sorting via metadata)
            if (!$this->isFieldSortable($fieldInstance)) {
                $validationException->addError('sorting', $fieldName, 
                    "Field '{$fieldName}' does not allow sorting");
                continue;
            }
            
            // Validate sort direction
            if (!in_array($direction, $this->validDirections)) {
                $validationException->addError('sorting', $fieldName, 
                    "Invalid sort direction '{$direction}'. Must be 'asc' or 'desc'");
                $direction = self::DEFAULT_DIRECTION; // Use default for recovery
            }
            
            // Validate priority (for multi-field sorting)
            if (!is_numeric($priority) || $priority < 1) {
                $priority = $index + 1; // Auto-assign priority based on order
            }
            
            $validatedSorting[] = [
                'field' => $fieldName,
                'direction' => $direction,
                'priority' => (int) $priority,
                'field_instance' => $fieldInstance,
                'sql_column' => $this->getSqlColumnName($fieldInstance, $fieldName)
            ];
            
            $this->logger->info("Sorting validation passed", [
                'field' => $fieldName,
                'direction' => $direction,
                'priority' => $priority,
                'field_class' => get_class($fieldInstance)
            ]);
        }
        
        // Throw aggregated validation errors if any exist
        if ($validationException->hasErrors()) {
            $this->logger->warning("Sorting validation failed", [
                'total_errors' => count($validationException->getErrors()),
                'error_summary' => $validationException->getErrorCountByType()
            ]);
            throw $validationException;
        }
        
        // Sort by priority for multi-field sorting
        usort($validatedSorting, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        $this->logger->info("Sorting validation complete", [
            'original_count' => count($sorting),
            'validated_count' => count($validatedSorting)
        ]);
        
        return $validatedSorting;
    }
    
    /**
     * Apply validated sorting to QueryBuilder
     */
    public function applyToQuery(QueryBuilder $qb, array $validatedSorting, string $mainAlias): void {
        foreach ($validatedSorting as $sort) {
            $field = $sort['field'];
            $direction = strtoupper($sort['direction']);
            $fieldInstance = $sort['field_instance'];
            
            // Build column reference
            $columnName = $this->buildColumnReference($mainAlias, $field, $fieldInstance);
            
            // Apply sorting with proper SQL escaping
            $qb->addOrderBy($columnName, $direction);
            
            $this->logger->debug("Applied sorting", [
                'field' => $field,
                'direction' => $direction,
                'column' => $columnName,
                'priority' => $sort['priority']
            ]);
        }
    }
    
    /**
     * Parse various sorting parameter formats into standard format
     */
    public function parseSortingParams(array $params, string $format): array {
        $sorting = [];
        
        switch ($format) {
            case 'simple':
                $sorting = $this->parseSimpleSorting($params);
                break;
                
            case 'structured':
                $sorting = $this->parseStructuredSorting($params);
                break;
                
            case 'json':
                $sorting = $this->parseJsonSorting($params);
                break;
                
            case 'ag-grid':
                $sorting = $this->parseAgGridSorting($params);
                break;
                
            case 'mui':
                $sorting = $this->parseMuiSorting($params);
                break;
                
            default:
                $this->logger->warning("Unknown sorting format", ['format' => $format]);
                $sorting = $this->parseSimpleSorting($params);
        }
        
        return $sorting;
    }
    
    /**
     * Parse simple sorting format: sort=field1:asc,field2:desc
     */
    private function parseSimpleSorting(array $params): array {
        $sorting = [];
        $sortParam = $params['sort'] ?? $params['sortBy'] ?? null;
        
        if (empty($sortParam)) {
            return [];
        }
        
        // Handle single field with separate direction parameter
        if (isset($params['sortOrder']) || isset($params['sortDirection'])) {
            $direction = $params['sortOrder'] ?? $params['sortDirection'] ?? 'asc';
            return [[
                'field' => $sortParam,
                'direction' => strtolower($direction),
                'priority' => 1
            ]];
        }
        
        // Handle multi-field format: field1:asc,field2:desc
        $sortFields = explode(',', $sortParam);
        foreach ($sortFields as $index => $sortField) {
            $parts = explode(':', trim($sortField));
            $field = trim($parts[0]);
            $direction = isset($parts[1]) ? strtolower(trim($parts[1])) : 'asc';
            
            if (!empty($field)) {
                $sorting[] = [
                    'field' => $field,
                    'direction' => $direction,
                    'priority' => $index + 1
                ];
            }
        }
        
        return $sorting;
    }
    
    /**
     * Parse structured sorting format: sort[0][field]=name&sort[0][direction]=asc
     */
    private function parseStructuredSorting(array $params): array {
        $sorting = [];
        $sortParams = $params['sort'] ?? [];
        
        if (!is_array($sortParams)) {
            return [];
        }
        
        foreach ($sortParams as $index => $sortConfig) {
            if (!is_array($sortConfig)) continue;
            
            $field = $sortConfig['field'] ?? $sortConfig['colId'] ?? null;
            $direction = $sortConfig['direction'] ?? $sortConfig['sort'] ?? 'asc';
            
            if (!empty($field)) {
                $sorting[] = [
                    'field' => $field,
                    'direction' => strtolower($direction),
                    'priority' => is_numeric($index) ? $index + 1 : count($sorting) + 1
                ];
            }
        }
        
        return $sorting;
    }
    
    /**
     * Parse JSON sorting format (MUI DataGrid)
     */
    private function parseJsonSorting(array $params): array {
        $sorting = [];
        $sortModel = $params['sortModel'] ?? null;
        
        if (empty($sortModel)) {
            return [];
        }
        
        try {
            $sortArray = is_string($sortModel) ? json_decode($sortModel, true) : $sortModel;
            
            if (is_array($sortArray)) {
                foreach ($sortArray as $index => $sortConfig) {
                    $field = $sortConfig['field'] ?? null;
                    $direction = $sortConfig['sort'] ?? 'asc';
                    
                    if (!empty($field)) {
                        $sorting[] = [
                            'field' => $field,
                            'direction' => strtolower($direction),
                            'priority' => $index + 1
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning("Failed to parse JSON sorting", [
                'sortModel' => $sortModel,
                'error' => $e->getMessage()
            ]);
        }
        
        return $sorting;
    }
    
    /**
     * Parse AG-Grid sorting format
     */
    private function parseAgGridSorting(array $params): array {
        return $this->parseStructuredSorting($params);
    }
    
    /**
     * Parse MUI DataGrid sorting format
     */
    private function parseMuiSorting(array $params): array {
        return $this->parseJsonSorting($params);
    }
    
    /**
     * Get sortable fields for a model with their configuration
     */
    public function getSortableFields(ModelBase $model): array {
        $sortableFields = [];
        $fields = $model->getFields();
        
        foreach ($fields as $fieldName => $field) {
            // Only include database fields that can be sorted
            if (!$field->isDBField()) {
                continue;
            }
            
            // Use field-level configuration to determine sortability
            if ($field->isSortable()) {
                $sortableFields[$fieldName] = [
                    'field' => $fieldName,
                    'type' => get_class($field),
                    'description' => $this->getFieldDescription($field),
                    'supports_null_ordering' => $this->supportsNullOrdering($field)
                ];
            }
        }
        
        return $sortableFields;
    }
    
    /**
     * Check if a field instance allows sorting
     */
    private function isFieldSortable(FieldBase $field): bool {
        // Check metadata override
        $sortableMetadata = $field->getMetadataValue('sortable');
        if ($sortableMetadata !== null) {
            return (bool) $sortableMetadata;
        }
        
        // Field type-based defaults
        $fieldType = get_class($field);
        $shortName = substr($fieldType, strrpos($fieldType, '\\') + 1);
        
        switch ($shortName) {
            case 'PasswordField':
                // Never sortable for security reasons
                return false;
                
            case 'BigTextField':
                // Disabled by default for performance, can be enabled via metadata
                return false;
                
            case 'ImageField':
                // Not meaningful to sort by file paths
                return false;
                
            case 'MultiEnumField':
                // Complex to sort, disabled by default
                return false;
                
            default:
                // Most field types are sortable by default
                return true;
        }
    }
    
    /**
     * Get SQL column name for sorting
     */
    private function getSqlColumnName(FieldBase $field, string $fieldName): string {
        // Allow field to customize column name for sorting
        if (method_exists($field, 'getSortColumn')) {
            return $field->getSortColumn();
        }
        
        return $fieldName;
    }
    
    /**
     * Build proper column reference for QueryBuilder
     */
    private function buildColumnReference(string $mainAlias, string $field, FieldBase $fieldInstance): string {
        $columnName = $this->getSqlColumnName($fieldInstance, $field);
        
        // Handle special cases like JSON fields or computed columns
        if (method_exists($fieldInstance, 'buildSortExpression')) {
            return $fieldInstance->buildSortExpression($mainAlias, $columnName);
        }
        
        return "{$mainAlias}.{$columnName}";
    }
    
    /**
     * Check if field supports NULL ordering (NULLS FIRST/LAST)
     */
    private function supportsNullOrdering(FieldBase $field): bool {
        // Fields that commonly have NULL values benefit from explicit NULL ordering
        $fieldType = get_class($field);
        $shortName = substr($fieldType, strrpos($fieldType, '\\') + 1);
        
        return in_array($shortName, [
            'DateField', 'DateTimeField', 'FloatField', 'IntegerField', 'RelatedRecordField'
        ]);
    }
    
    /**
     * Get human-readable description for a field type
     */
    private function getFieldDescription(FieldBase $field): string {
        $fieldType = get_class($field);
        $shortName = substr($fieldType, strrpos($fieldType, '\\') + 1);
        
        $descriptions = [
            'TextField' => 'Text field - alphabetical sorting',
            'IntegerField' => 'Integer field - numeric sorting',
            'FloatField' => 'Decimal field - numeric sorting',
            'DateField' => 'Date field - chronological sorting',
            'DateTimeField' => 'DateTime field - chronological sorting',
            'BooleanField' => 'Boolean field - false before true',
            'EmailField' => 'Email field - alphabetical sorting',
            'EnumField' => 'Selection field - alphabetical by value',
            'RadioButtonSetField' => 'Radio selection - alphabetical by value',
            'IDField' => 'ID field - numeric sorting',
            'RelatedRecordField' => 'Related record - sorting by foreign key'
        ];
        
        return $descriptions[$shortName] ?? 'Custom field type';
    }
    
    /**
     * Generate default sorting for a model
     */
    public function getDefaultSorting(ModelBase $model): array {
        // Check if model defines default sorting
        if (method_exists($model, 'getDefaultSort')) {
            $defaultSort = $model->getDefaultSort();
            if (!empty($defaultSort)) {
                return $defaultSort;
            }
        }
        
        // Auto-generate sensible default sorting
        $fields = $model->getFields();
        
        // Prefer ID field for primary sorting
        if (isset($fields['id']) && $fields['id']->isDBField()) {
            return [
                [
                    'field' => 'id',
                    'direction' => 'desc',
                    'priority' => 1
                ]
            ];
        }
        
        // Fallback to created_at or updated_at
        foreach (['created_at', 'updated_at'] as $timeField) {
            if (isset($fields[$timeField]) && $fields[$timeField]->isDBField()) {
                return [
                    [
                        'field' => $timeField,
                        'direction' => 'desc',
                        'priority' => 1
                    ]
                ];
            }
        }
        
        // Last resort: use first sortable field
        foreach ($fields as $fieldName => $field) {
            if ($field->isDBField() && $field->isSortable()) {
                return [
                    [
                        'field' => $fieldName,
                        'direction' => 'asc',
                        'priority' => 1
                    ]
                ];
            }
        }
        
        // No sortable fields found
        return [];
    }
}

// Pagination Manager
class PaginationManager {
    private LoggerInterface $logger;
    private const MAX_PAGE_SIZE = 1000;
    private const DEFAULT_PAGE_SIZE = 20;
    private const CURSOR_SALT = 'gc_pagination_salt_2025';
    
    public function __construct(Request $request) {
        // Setup logging for pagination tracking
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Build offset-based pagination metadata for traditional page navigation
     */
    public function buildOffsetPagination(array $data, array $paginationParams, int $total): array {
        $page = max(1, (int) ($paginationParams['page'] ?? 1));
        $perPage = $this->validatePageSize($paginationParams['pageSize'] ?? $paginationParams['per_page'] ?? self::DEFAULT_PAGE_SIZE);
        
        // Calculate pagination metadata
        $totalPages = (int) ceil($total / $perPage);
        $currentPage = min($page, max(1, $totalPages)); // Ensure current page is valid
        $offset = ($currentPage - 1) * $perPage;
        $hasNextPage = $currentPage < $totalPages;
        $hasPreviousPage = $currentPage > 1;
        
        // Calculate item range
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);
        
        $paginationMeta = [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'from' => $from,
            'to' => $to,
            'has_next_page' => $hasNextPage,
            'has_previous_page' => $hasPreviousPage,
            'is_first_page' => $currentPage === 1,
            'is_last_page' => $currentPage === $totalPages || $totalPages === 0,
            'offset' => $offset,
            'limit' => $perPage
        ];
        
        // Add page numbers for pagination UI
        $paginationMeta['page_numbers'] = $this->generatePageNumbers($currentPage, $totalPages);
        
        $this->logger->info("Offset pagination built", [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages
        ]);
        
        return $paginationMeta;
    }
    
    /**
     * Build cursor-based pagination for infinite scroll and real-time data
     */
    public function buildCursorPagination(array $data, array $paginationParams, int $total = null): array {
        $limit = $this->validatePageSize($paginationParams['limit'] ?? $paginationParams['pageSize'] ?? self::DEFAULT_PAGE_SIZE);
        $cursor = $paginationParams['cursor'] ?? null;
        $before = $paginationParams['before'] ?? null;
        
        // Determine if we have more data
        $hasMoreData = count($data) > $limit;
        if ($hasMoreData) {
            // Remove the extra record used for has_next detection
            array_pop($data);
        }
        
        $paginationMeta = [
            'limit' => $limit,
            'has_next_page' => $hasMoreData,
            'has_previous_page' => !empty($cursor) || !empty($before),
            'count' => count($data)
        ];
        
        // Generate cursors if we have data
        if (!empty($data)) {
            $firstRecord = reset($data);
            $lastRecord = end($data);
            
            $paginationMeta['start_cursor'] = $this->encodeCursor($firstRecord);
            $paginationMeta['end_cursor'] = $this->encodeCursor($lastRecord);
            
            // For GraphQL-style pagination
            $paginationMeta['edges'] = array_map(function($record) {
                return [
                    'node' => $record,
                    'cursor' => $this->encodeCursor($record)
                ];
            }, $data);
        } else {
            $paginationMeta['start_cursor'] = null;
            $paginationMeta['end_cursor'] = null;
            $paginationMeta['edges'] = [];
        }
        
        // Include total count if provided (optional for performance)
        if ($total !== null) {
            $paginationMeta['total_count'] = $total;
        }
        
        $this->logger->info("Cursor pagination built", [
            'limit' => $limit,
            'has_next' => $hasMoreData,
            'count' => count($data),
            'cursor_provided' => !empty($cursor)
        ]);
        
        return $paginationMeta;
    }
    
    /**
     * Calculate comprehensive page information for UI components
     */
    public function calculatePageInfo(int $total, int $page, int $perPage): array {
        $totalPages = (int) ceil($total / $perPage);
        $currentPage = min($page, max(1, $totalPages));
        $offset = ($currentPage - 1) * $perPage;
        
        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total' => $total,
            'offset' => $offset,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
            'has_next' => $currentPage < $totalPages,
            'has_previous' => $currentPage > 1,
            'is_first' => $currentPage === 1,
            'is_last' => $currentPage === $totalPages || $totalPages === 0,
            'page_numbers' => $this->generatePageNumbers($currentPage, $totalPages),
            'showing_from' => $offset + 1,
            'showing_to' => min($offset + $perPage, $total),
            'showing_of' => $total
        ];
    }
    
    /**
     * Generate pagination navigation links for REST API hypermedia
     */
    public function generatePaginationLinks(string $baseUrl, array $params): array {
        $page = (int) ($params['page'] ?? 1);
        $perPage = $this->validatePageSize($params['pageSize'] ?? $params['per_page'] ?? self::DEFAULT_PAGE_SIZE);
        $total = (int) ($params['total'] ?? 0);
        $totalPages = (int) ceil($total / $perPage);
        
        // Remove page from params to rebuild URLs
        $queryParams = $params;
        unset($queryParams['page'], $queryParams['total']);
        
        $links = [
            'self' => $this->buildUrl($baseUrl, array_merge($queryParams, ['page' => $page])),
            'first' => $this->buildUrl($baseUrl, array_merge($queryParams, ['page' => 1])),
            'last' => $totalPages > 0 ? $this->buildUrl($baseUrl, array_merge($queryParams, ['page' => $totalPages])) : null
        ];
        
        // Add previous page link
        if ($page > 1) {
            $links['prev'] = $this->buildUrl($baseUrl, array_merge($queryParams, ['page' => $page - 1]));
        } else {
            $links['prev'] = null;
        }
        
        // Add next page link
        if ($page < $totalPages) {
            $links['next'] = $this->buildUrl($baseUrl, array_merge($queryParams, ['page' => $page + 1]));
        } else {
            $links['next'] = null;
        }
        
        return $links;
    }
    
    /**
     * Encode cursor for stateless pagination
     */
    public function encodeCursor(array $lastRecord): string {
        // Use primary key and timestamp for cursor if available
        $cursorData = [];
        
        // Prefer ID field for cursor
        if (isset($lastRecord['id'])) {
            $cursorData['id'] = $lastRecord['id'];
        }
        
        // Add timestamp for ordering consistency
        if (isset($lastRecord['created_at'])) {
            $cursorData['created_at'] = $lastRecord['created_at'];
        } elseif (isset($lastRecord['updated_at'])) {
            $cursorData['updated_at'] = $lastRecord['updated_at'];
        }
        
        // Add sort fields if present in record
        foreach (['name', 'title', 'email'] as $sortField) {
            if (isset($lastRecord[$sortField])) {
                $cursorData[$sortField] = $lastRecord[$sortField];
                break; // Only include one sort field
            }
        }
        
        // Add security hash to prevent cursor tampering
        $dataString = json_encode($cursorData, JSON_SORT_KEYS);
        $hash = hash_hmac('sha256', $dataString, self::CURSOR_SALT);
        $cursorData['_hash'] = $hash;
        
        return base64_encode(json_encode($cursorData));
    }
    
    /**
     * Decode and validate cursor for pagination
     */
    public function decodeCursor(string $cursor): array {
        try {
            $decoded = base64_decode($cursor, true);
            if ($decoded === false) {
                throw new \InvalidArgumentException('Invalid cursor encoding');
            }
            
            $cursorData = json_decode($decoded, true);
            if (!is_array($cursorData)) {
                throw new \InvalidArgumentException('Invalid cursor format');
            }
            
            // Verify cursor integrity
            $providedHash = $cursorData['_hash'] ?? '';
            unset($cursorData['_hash']);
            
            $dataString = json_encode($cursorData, JSON_SORT_KEYS);
            $expectedHash = hash_hmac('sha256', $dataString, self::CURSOR_SALT);
            
            if (!hash_equals($expectedHash, $providedHash)) {
                throw new \InvalidArgumentException('Cursor integrity check failed');
            }
            
            return $cursorData;
            
        } catch (\Exception $e) {
            $this->logger->warning("Cursor decode failed", [
                'cursor' => $cursor,
                'error' => $e->getMessage()
            ]);
            
            // Return empty array for invalid cursors - let query start from beginning
            return [];
        }
    }
    
    /**
     * Validate and normalize page size
     */
    private function validatePageSize($pageSize): int {
        $size = (int) $pageSize;
        
        if ($size <= 0) {
            return self::DEFAULT_PAGE_SIZE;
        }
        
        if ($size > self::MAX_PAGE_SIZE) {
            $this->logger->warning("Page size exceeded maximum", [
                'requested' => $size,
                'max_allowed' => self::MAX_PAGE_SIZE,
                'using' => self::MAX_PAGE_SIZE
            ]);
            return self::MAX_PAGE_SIZE;
        }
        
        return $size;
    }
    
    /**
     * Generate page numbers for pagination UI (smart pagination)
     */
    private function generatePageNumbers(int $currentPage, int $totalPages, int $maxVisible = 7): array {
        if ($totalPages <= $maxVisible) {
            return range(1, $totalPages);
        }
        
        $pages = [];
        $halfVisible = (int) floor($maxVisible / 2);
        
        // Always show first page
        $pages[] = 1;
        
        // Calculate start and end of middle section
        $start = max(2, $currentPage - $halfVisible);
        $end = min($totalPages - 1, $currentPage + $halfVisible);
        
        // Adjust if we're near the beginning
        if ($start <= 3) {
            $start = 2;
            $end = min($totalPages - 1, $maxVisible - 1);
        }
        
        // Adjust if we're near the end
        if ($end >= $totalPages - 2) {
            $end = $totalPages - 1;
            $start = max(2, $totalPages - $maxVisible + 2);
        }
        
        // Add ellipsis if there's a gap after page 1
        if ($start > 2) {
            $pages[] = '...';
        }
        
        // Add middle pages
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        
        // Add ellipsis if there's a gap before last page
        if ($end < $totalPages - 1) {
            $pages[] = '...';
        }
        
        // Always show last page (if it exists and isn't already included)
        if ($totalPages > 1) {
            $pages[] = $totalPages;
        }
        
        return $pages;
    }
    
    /**
     * Build URL with query parameters
     */
    private function buildUrl(string $baseUrl, array $params): string {
        if (empty($params)) {
            return $baseUrl;
        }
        
        $queryString = http_build_query($params);
        $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
        
        return $baseUrl . $separator . $queryString;
    }
    
    /**
     * Build AG-Grid compatible pagination for server-side row model
     */
    public function buildAgGridPagination(array $data, array $paginationParams, int $total): array {
        $startRow = (int) ($paginationParams['startRow'] ?? 0);
        $endRow = (int) ($paginationParams['endRow'] ?? 100);
        $requestedRows = $endRow - $startRow;
        
        // AG-Grid expects 'lastRow' to indicate total count for infinite scroll
        $lastRow = count($data) < $requestedRows ? $startRow + count($data) : null;
        
        return [
            'lastRow' => $lastRow, // null means more data available
            'rowData' => $data, // AG-Grid expects 'rowData' property
            'rowCount' => count($data),
            'startRow' => $startRow,
            'endRow' => $startRow + count($data),
            'totalRows' => $total // Optional total count
        ];
    }
    
    /**
     * Build MUI DataGrid compatible pagination metadata
     */
    public function buildMuiPagination(array $data, array $paginationParams, int $total): array {
        $page = (int) ($paginationParams['page'] ?? 0); // MUI uses 0-based pages
        $pageSize = $this->validatePageSize($paginationParams['pageSize'] ?? self::DEFAULT_PAGE_SIZE);
        
        return [
            'rows' => $data, // MUI expects 'rows' property
            'rowCount' => $total, // Total count for pagination
            'page' => $page,
            'pageSize' => $pageSize,
            'loading' => false,
            'error' => null
        ];
    }
}

// Response Formatter - handles multiple output formats
class ResponseFormatter {
    private LoggerInterface $logger;
    private array $supportedFormats = [
        'standard', 'tanstack', 'ag-grid', 'mui', 'infinite-scroll', 'cursor', 'swr'
    ];
    
    public function __construct(Request $request) {
        // Setup logging for response formatting tracking
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Universal response formatter - detects format and delegates to specific formatter
     */
    public function format(array $data, array $meta, string $format): array {
        $format = strtolower($format);
        
        if (!in_array($format, $this->supportedFormats)) {
            $this->logger->warning("Unsupported response format requested", [
                'requested_format' => $format,
                'supported_formats' => $this->supportedFormats,
                'defaulting_to' => 'standard'
            ]);
            $format = 'standard';
        }
        
        $response = [];
        $startTime = microtime(true);
        
        switch ($format) {
            case 'tanstack':
                $response = $this->formatForTanStackQuery($data, $meta, $meta['links'] ?? []);
                break;
                
            case 'ag-grid':
                $totalCount = $meta['pagination']['total'] ?? count($data);
                $response = $this->formatForAgGrid($data, $totalCount, $meta);
                break;
                
            case 'mui':
                $response = $this->formatForMuiDataGrid($data, $meta);
                break;
                
            case 'infinite-scroll':
            case 'cursor':
                $pageInfo = $meta['pagination'] ?? [];
                $response = $this->formatForInfiniteScroll($data, $pageInfo, $meta);
                break;
                
            case 'swr':
                $response = $this->formatForSWR($data, $meta);
                break;
                
            case 'standard':
            default:
                $response = $this->formatStandard($data, $meta);
                break;
        }
        
        $formatTime = round((microtime(true) - $startTime) * 1000, 2);
        $this->logger->info("Response formatted", [
            'format' => $format,
            'data_count' => count($data),
            'format_time_ms' => $formatTime
        ]);
        
        return $response;
    }
    
    /**
     * Format response for TanStack Query (React Query) compatibility
     */
    public function formatForTanStackQuery(array $data, array $meta, array $links): array {
        $pagination = $meta['pagination'] ?? [];
        $search = $meta['search'] ?? [];
        $filters = $meta['filters'] ?? [];
        
        return [
            'success' => true,
            'status' => 200,
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'current_page' => $pagination['current_page'] ?? 1,
                    'per_page' => $pagination['per_page'] ?? 20,
                    'total' => $pagination['total'] ?? count($data),
                    'total_pages' => $pagination['total_pages'] ?? 1,
                    'from' => $pagination['from'] ?? 1,
                    'to' => $pagination['to'] ?? count($data),
                    'has_next_page' => $pagination['has_next_page'] ?? false,
                    'has_previous_page' => $pagination['has_previous_page'] ?? false
                ],
                'search' => [
                    'term' => $search['term'] ?? null,
                    'fields' => $search['fields'] ?? [],
                    'total_searchable_fields' => count($search['available_fields'] ?? [])
                ],
                'filters' => [
                    'active' => $filters['active'] ?? [],
                    'available' => $filters['available'] ?? []
                ],
                'sorting' => $meta['sorting'] ?? [],
                'query_time_ms' => $meta['query_time_ms'] ?? 0,
                'total_query_time_ms' => $meta['total_time_ms'] ?? 0
            ],
            'links' => [
                'self' => $links['self'] ?? null,
                'first' => $links['first'] ?? null,
                'last' => $links['last'] ?? null,
                'prev' => $links['prev'] ?? null,
                'next' => $links['next'] ?? null
            ],
            'timestamp' => date('c'), // ISO 8601 format
            'cache_key' => $this->generateCacheKey($meta)
        ];
    }
    
    /**
     * Format response for AG-Grid server-side row model
     */
    public function formatForAgGrid(array $data, int $totalCount, array $meta = []): array {
        $pagination = $meta['pagination'] ?? [];
        $startRow = $pagination['offset'] ?? 0;
        $endRow = $startRow + count($data);
        
        // AG-Grid expects 'lastRow' to indicate when we've reached the end
        // If we have fewer records than requested, this is the last batch
        $requestedRows = $pagination['per_page'] ?? count($data);
        $lastRow = count($data) < $requestedRows ? $endRow : null;
        
        $response = [
            'success' => true,
            'rowData' => $data, // AG-Grid expects 'rowData' property
            'lastRow' => $lastRow, // null = more data available, number = total count
            'startRow' => $startRow,
            'endRow' => $endRow
        ];
        
        // Include additional metadata that AG-Grid can use
        if (isset($meta['columns'])) {
            $response['secondaryColumns'] = $meta['columns'];
        }
        
        if (isset($meta['group_info'])) {
            $response['groupKeys'] = $meta['group_info'];
        }
        
        // Include performance metrics
        if (isset($meta['query_time_ms'])) {
            $response['queryTimeMs'] = $meta['query_time_ms'];
        }
        
        return $response;
    }
    
    /**
     * Format response for Material-UI DataGrid
     */
    public function formatForMuiDataGrid(array $data, array $meta): array {
        $pagination = $meta['pagination'] ?? [];
        
        return [
            'success' => true,
            'rows' => $data, // MUI expects 'rows' property
            'rowCount' => $pagination['total'] ?? count($data),
            'page' => max(0, ($pagination['current_page'] ?? 1) - 1), // MUI uses 0-based pages
            'pageSize' => $pagination['per_page'] ?? 25,
            'loading' => false,
            'error' => null,
            'hasNextPage' => $pagination['has_next_page'] ?? false,
            'hasPreviousPage' => $pagination['has_previous_page'] ?? false,
            'filterModel' => $meta['filters']['active'] ?? [],
            'sortModel' => $this->convertSortingToMuiFormat($meta['sorting'] ?? []),
            'queryTime' => $meta['query_time_ms'] ?? 0
        ];
    }
    
    /**
     * Format response for infinite scroll / cursor-based pagination
     */
    public function formatForInfiniteScroll(array $data, array $pageInfo, array $meta = []): array {
        return [
            'success' => true,
            'data' => $data,
            'pageInfo' => [
                'hasNextPage' => $pageInfo['has_next_page'] ?? false,
                'hasPreviousPage' => $pageInfo['has_previous_page'] ?? false,
                'startCursor' => $pageInfo['start_cursor'] ?? null,
                'endCursor' => $pageInfo['end_cursor'] ?? null
            ],
            'edges' => $pageInfo['edges'] ?? array_map(function($item, $index) use ($pageInfo) {
                return [
                    'node' => $item,
                    'cursor' => $pageInfo['edges'][$index]['cursor'] ?? null
                ];
            }, $data, array_keys($data)),
            'totalCount' => $pageInfo['total_count'] ?? null, // Optional for performance
            'count' => count($data),
            'queryTime' => $meta['query_time_ms'] ?? 0,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Format response for SWR library compatibility
     */
    public function formatForSWR(array $data, array $meta): array {
        $pagination = $meta['pagination'] ?? [];
        
        return [
            'data' => $data,
            'pagination' => [
                'page' => $pagination['current_page'] ?? 1,
                'pageSize' => $pagination['per_page'] ?? 20,
                'total' => $pagination['total'] ?? count($data),
                'hasMore' => $pagination['has_next_page'] ?? false
            ],
            'meta' => [
                'timestamp' => time(),
                'queryTime' => $meta['query_time_ms'] ?? 0,
                'cacheKey' => $this->generateCacheKey($meta),
                'filters' => $meta['filters']['active'] ?? [],
                'search' => $meta['search']['term'] ?? null
            ],
            'error' => null,
            'isValidating' => false
        ];
    }
    
    /**
     * Standard Gravitycar API response format
     */
    public function formatStandard(array $data, array $meta): array {
        return [
            'success' => true,
            'status' => 200,
            'data' => $data,
            'meta' => $meta,
            'count' => count($data),
            'timestamp' => date('c'),
            'api_version' => '2.0',
            'response_format' => 'standard'
        ];
    }
    
    /**
     * Format error response for specific React library
     */
    public function formatError(\Exception $exception, string $format = 'standard'): array {
        $errorData = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'type' => get_class($exception)
        ];
        
        $baseError = [
            'success' => false,
            'status' => $this->getHttpStatusFromException($exception),
            'error' => $errorData,
            'timestamp' => date('c')
        ];
        
        switch (strtolower($format)) {
            case 'tanstack':
                return array_merge($baseError, [
                    'data' => null,
                    'meta' => null,
                    'links' => null
                ]);
                
            case 'ag-grid':
                return [
                    'success' => false,
                    'rowData' => [],
                    'lastRow' => 0,
                    'error' => $errorData
                ];
                
            case 'mui':
                return [
                    'success' => false,
                    'rows' => [],
                    'rowCount' => 0,
                    'loading' => false,
                    'error' => $errorData
                ];
                
            case 'infinite-scroll':
            case 'cursor':
                return [
                    'success' => false,
                    'data' => [],
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'hasPreviousPage' => false,
                        'startCursor' => null,
                        'endCursor' => null
                    ],
                    'error' => $errorData
                ];
                
            case 'swr':
                return [
                    'data' => null,
                    'error' => $errorData,
                    'isValidating' => false,
                    'meta' => [
                        'timestamp' => time()
                    ]
                ];
                
            default:
                return $baseError;
        }
    }
    
    /**
     * Convert internal sorting format to MUI DataGrid format
     */
    private function convertSortingToMuiFormat(array $sorting): array {
        $muiSort = [];
        
        foreach ($sorting as $sort) {
            if (isset($sort['field']) && isset($sort['direction'])) {
                $muiSort[] = [
                    'field' => $sort['field'],
                    'sort' => strtolower($sort['direction']) === 'desc' ? 'desc' : 'asc'
                ];
            }
        }
        
        return $muiSort;
    }
    
    /**
     * Generate cache key for client-side caching
     */
    private function generateCacheKey(array $meta): string {
        $keyData = [
            'filters' => $meta['filters']['active'] ?? [],
            'search' => $meta['search']['term'] ?? '',
            'sorting' => $meta['sorting'] ?? [],
            'pagination' => [
                'page' => $meta['pagination']['current_page'] ?? 1,
                'per_page' => $meta['pagination']['per_page'] ?? 20
            ]
        ];
        
        return 'gc_' . md5(json_encode($keyData, JSON_SORT_KEYS));
    }
    
    /**
     * Get appropriate HTTP status code from exception
     */
    private function getHttpStatusFromException(\Exception $exception): int {
        if ($exception instanceof \InvalidArgumentException) {
            return 400; // Bad Request
        }
        
        if ($exception instanceof \UnauthorizedHttpException) {
            return 401; // Unauthorized
        }
        
        if ($exception instanceof \AccessDeniedHttpException) {
            return 403; // Forbidden
        }
        
        if ($exception instanceof \NotFoundHttpException) {
            return 404; // Not Found
        }
        
        if ($exception instanceof \MethodNotAllowedHttpException) {
            return 405; // Method Not Allowed
        }
        
        if ($exception instanceof \TooManyRequestsHttpException) {
            return 429; // Too Many Requests
        }
        
        // Default to 500 for any other exception
        return 500; // Internal Server Error
    }
    
    /**
     * Add debug information to response (development mode only)
     */
    public function addDebugInfo(array $response, array $debugData): array {
        $config = \Gravitycar\Core\ServiceLocator::getConfig();
        $isDevelopment = $config->get('environment') === 'development';
        
        if (!$isDevelopment) {
            return $response;
        }
        
        $response['debug'] = [
            'sql_queries' => $debugData['queries'] ?? [],
            'query_count' => count($debugData['queries'] ?? []),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time_ms' => $debugData['execution_time_ms'] ?? 0,
            'database_time_ms' => $debugData['database_time_ms'] ?? 0,
            'cache_hits' => $debugData['cache_hits'] ?? 0,
            'cache_misses' => $debugData['cache_misses'] ?? 0
        ];
        
        return $response;
    }
    
    /**
     * Validate response data before formatting
     */
    private function validateResponseData(array $data, array $meta): void {
        // Ensure data is an array of records
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Response data must be an array');
        }
        
        // Validate pagination metadata if present
        if (isset($meta['pagination'])) {
            $pagination = $meta['pagination'];
            
            if (isset($pagination['current_page']) && $pagination['current_page'] < 1) {
                throw new \InvalidArgumentException('Current page must be >= 1');
            }
            
            if (isset($pagination['per_page']) && $pagination['per_page'] < 1) {
                throw new \InvalidArgumentException('Per page must be >= 1');
            }
            
            if (isset($pagination['total']) && $pagination['total'] < 0) {
                throw new \InvalidArgumentException('Total count cannot be negative');
            }
        }
    }
}

// ParameterValidationException - For aggregating parameter validation errors
class ParameterValidationException extends \Exception {
    private array $errors = [];
    
    public function __construct(string $message = 'Parameter validation failed', int $code = 0, ?\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Add a validation error to the collection
     */
    public function addError(string $type, string $field, string $message): void {
        $this->errors[] = [
            'type' => $type,        // 'filter', 'search', 'sorting', 'pagination'
            'field' => $field,      // Field name or parameter name
            'message' => $message,  // Human-readable error message
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Get all collected validation errors
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Check if any errors have been collected
     */
    public function hasErrors(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Get error count by type
     */
    public function getErrorCountByType(): array {
        $counts = ['filter' => 0, 'search' => 0, 'sorting' => 0, 'pagination' => 0];
        
        foreach ($this->errors as $error) {
            $type = $error['type'] ?? 'unknown';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }
        
        return $counts;
    }
    
    /**
     * Get errors for a specific field
     */
    public function getErrorsForField(string $field): array {
        return array_filter($this->errors, function($error) use ($field) {
            return ($error['field'] ?? '') === $field;
        });
    }
    
    /**
     * Clear all collected errors
     */
    public function clearErrors(): void {
        $this->errors = [];
    }
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

## 5.3 Validation Orchestration Execution Timing

### 5.3.1 Controller Implementation Pattern

The validation orchestration approach is executed in the **Controller layer** where the model is known. Here's the recommended implementation pattern:

```php
// Enhanced ModelBaseAPIController with validation orchestration
class ModelBaseAPIController {
    
    public function list(Request $request): array {
        // 1. Instantiate model (required for validation)
        $modelName = $this->getModelName();
        $model = ModelFactory::new($modelName);
        
        // 2. SINGLE validation call - validates ALL parameters at once
        try {
            $validatedParams = $request->validateAllParameters($model);
        } catch (BadRequestException $e) {
            // Return comprehensive error response with all validation issues
            return $request->getResponseFormatter()->formatError($e, $request->getResponseFormat());
        }
        
        // 3. Extract validated parameters (guaranteed to be valid)
        $filters = $validatedParams['filters'];
        $searchParams = $validatedParams['search'];
        $sorting = $validatedParams['sorting'];
        $pagination = $validatedParams['pagination'];
        
        // 4. Execute query with validated parameters
        $databaseConnector = new DatabaseConnector();
        $queryResult = $databaseConnector->getEnhancedList(
            $modelName,
            $filters,
            $searchParams,
            $sorting,
            $pagination
        );
        
        // 5. Format response for client library
        return $request->getResponseFormatter()->format(
            $queryResult['data'],
            $queryResult['meta'],
            $request->getResponseFormat()
        );
    }
    
    // Alternative: Individual validation methods (legacy support)
    public function listLegacy(Request $request): array {
        $modelName = $this->getModelName();
        $model = ModelFactory::new($modelName);
        
        // Individual validation calls (less efficient, multiple exception handling)
        try {
            $filters = $request->getValidatedFilters($model);
        } catch (ParameterValidationException $e) {
            // Handle filter errors...
        }
        
        try {
            $searchParams = $request->getValidatedSearchParams($model);
        } catch (ParameterValidationException $e) {
            // Handle search errors...
        }
        
        // ... more individual validation calls
    }
}
```

### 5.3.2 Execution Flow Timing

**CRITICAL ARCHITECTURAL DECISION**: Validation now happens in the **Router layer** where we have model access, eliminating controller validation repetition:

#### **NEW Execution Flow**:
1. **Request Router Phase**: Parse parameters into generic structures (no validation)
2. **Router Model Detection**: Check if `$request->has('modelName')` and instantiate model if available
3. **Router Validation Orchestration**: Single validation call for ALL parameters against model schema
4. **Router Error Handling**: Comprehensive BadRequestException with all validation errors
5. **Controller Entry**: Controllers receive pre-validated parameters
6. **Database Query Phase**: Execute with guaranteed-valid parameters
7. **Response Formatting**: Format results for React library compatibility

#### **Key Benefits of Router-Level Validation**:

##### ✅ **Eliminates Controller Repetition**
- **Before**: Every controller override needed `getValidatedFilters()`, `getValidatedSorting()`, etc.
- **After**: Controllers just access `$request->getValidatedParams()` - no validation calls needed
- **Impact**: Cleaner controller code, no risk of missing validation calls

##### ✅ **Early Error Detection**
- **Before**: Validation errors surfaced deep in controller methods
- **After**: Validation happens immediately in Router before expensive controller instantiation
- **Impact**: Better performance, cleaner error location

##### ✅ **Graceful Non-Model Route Handling**
- **Routes with model**: Full validation performed (e.g., `/Users/list`)
- **Routes without model**: Empty arrays returned (e.g., `/metadata`, `/health`)
- **Impact**: Consistent behavior across all route types

##### ✅ **Comprehensive Error Responses**
- **Before**: Individual validation errors thrown separately
- **After**: All validation errors aggregated into single comprehensive response
- **Impact**: Better developer experience, faster debugging

##### ✅ **Simplified Testing**
- **Before**: Test validation in every controller method
- **After**: Test validation once in Router, test business logic in controllers
- **Impact**: Reduced test complexity, better separation of concerns

#### **Route Types and Validation Behavior**:

```php
// Model routes - full validation performed
GET /Users/list?filter[age][gte]=18&sort=name:asc
// Router extracts 'Users' from modelName parameter
// Instantiates User model, validates all parameters
// Controllers receive pre-validated data

// Non-model routes - graceful fallback
GET /metadata?someParam=value
// No modelName parameter available
// Router sets empty validated params
// Controllers receive empty arrays, work normally

// Invalid model routes - immediate error
GET /InvalidModel/list
// Router tries ModelFactory::new('InvalidModel')
// ModelFactory throws exception
// Router converts to BadRequestException with helpful error
```

#### **Controller Simplification Example**:

```php
// BEFORE (Router-level validation):
public function list(Request $request): array {
    $model = ModelFactory::new($this->getModelName($request));
    
    // Lots of repetitive validation calls
    $filters = $request->getValidatedFilters($model);
    $sorting = $request->getValidatedSorting($model); 
    $search = $request->getValidatedSearchParams($model);
    $pagination = $request->getPaginationParams();
    
    // ... business logic
}

// AFTER (Router-level validation):
public function list(Request $request): array {
    // Validation already done! Just get the validated data
    $validatedParams = $request->getValidatedParams();
    $filters = $validatedParams['filters'];
    $sorting = $validatedParams['sorting'];
    $search = $validatedParams['search'];
    $pagination = $validatedParams['pagination'];
    
    // ... business logic (same as before)
}
```

This approach transforms validation from a repetitive controller concern into a centralized Router responsibility, dramatically simplifying the framework's usage while improving error handling and performance.

### 5.3.3 Error Response Structure

When validation orchestration fails, clients receive comprehensive error information:

```json
{
  "success": false,
  "error": {
    "message": "Invalid query parameters",
    "type": "BadRequestException",
    "status": 400,
    "parameter_errors": [
      {
        "type": "filter",
        "field": "invalid_field",
        "message": "Field 'invalid_field' does not exist in model",
        "timestamp": "2025-08-18T10:30:00+00:00"
      },
      {
        "type": "search",
        "field": "search_field",
        "message": "Field 'search_field' is not searchable",
        "timestamp": "2025-08-18T10:30:00+00:00"
      }
    ],
    "error_count": 2,
    "validation_summary": {
      "filter_errors": 1,
      "search_errors": 1,
      "sorting_errors": 0,
      "pagination_errors": 0,
      "fields_with_errors": ["invalid_field", "search_field"]
    },
    "suggested_fixes": {
      "filters": {
        "message": "Check available filterable fields and operators",
        "available_fields": ["id", "name", "email", "status", "created_at"],
        "example": "/Users?filter[status]=active&filter[age][gte]=18"
      },
      "search": {
        "message": "Check searchable fields configuration",
        "available_fields": ["name", "email"],
        "example": "/Users?search=john&search_fields=name,email"
      }
    }
  },
  "timestamp": "2025-08-18T10:30:00+00:00"
}
```

### 5.3.4 Performance Benefits

**Single Validation Call Advantages**:
- **Reduced Exception Overhead**: One try/catch block instead of multiple
- **Better User Experience**: All validation errors returned at once
- **Faster Development**: Comprehensive error feedback reduces iteration cycles
- **Database Protection**: Guaranteed valid parameters before expensive queries
- **Consistent Error Format**: Standardized error response across all endpoints

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
    
    // Field configuration properties
    protected bool $isSearchable = true;
    protected bool $isFilterable = true;
    protected bool $isSortable = true;
    
    // ... existing methods ...
    
    /**
     * Get allowed operators for this field instance
     * Can be overridden by metadata via ingestMetadata()
     */
    public function getOperators(): array {
        return $this->operators;
    }
    
    /**
     * Check if this field can be used for searching
     */
    public function isSearchable(): bool {
        return $this->isSearchable;
    }
    
    /**
     * Check if this field can be used for filtering
     */
    public function isFilterable(): bool {
        return $this->isFilterable;
    }
    
    /**
     * Check if this field can be used for sorting
     */
    public function isSortable(): bool {
        return $this->isSortable;
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
    
    // Enhanced ingestMetadata to handle operators and configuration
    public function ingestMetadata(array $metadata): void {
        // ... existing metadata ingestion ...
        
        // Allow metadata to override operators
        if (isset($metadata['operators']) && is_array($metadata['operators'])) {
            $this->operators = $metadata['operators'];
        }
        
        // Allow metadata to override configuration flags
        if (isset($metadata['isSearchable']) && is_bool($metadata['isSearchable'])) {
            $this->isSearchable = $metadata['isSearchable'];
        }
        
        if (isset($metadata['isFilterable']) && is_bool($metadata['isFilterable'])) {
            $this->isFilterable = $metadata['isFilterable'];
        }
        
        if (isset($metadata['isSortable']) && is_bool($metadata['isSortable'])) {
            $this->isSortable = $metadata['isSortable'];
        }
    }
}

// Complete Field Subclass Operator Definitions for ALL Framework Field Types:

class TextField extends FieldBase {
    protected array $operators = [
        'equals', 'notEquals', 'contains', 'notContains', 'startsWith', 'endsWith', 
        'in', 'notIn', 'isNull', 'isNotNull', 'isEmpty', 'isNotEmpty'
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
    
    public function normalizeFilterValue($value, string $operator) {
        if (is_array($value)) {
            return array_map('strval', $value);
        }
        return (string) $value;
    }
}

class BigTextField extends TextField {
    // Override to remove expensive operators by default for performance
    protected array $operators = [
        'equals', 'notEquals', 'in', 'notIn', 'isNull', 'isNotNull', 'isEmpty', 'isNotEmpty'
        // Note: 'contains', 'startsWith', 'endsWith' removed due to performance
        // Can be re-enabled per field via metadata if needed
    ];
}

class IntegerField extends FieldBase {
    protected array $operators = [
        'equals', 'notEquals', 'gt', 'gte', 'lt', 'lte', 'between', 'notBetween',
        'in', 'notIn', 'isNull', 'isNotNull'
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        if (in_array($operator, ['in', 'notIn'])) {
            return is_array($value) && !empty($value) && 
                   array_filter($value, 'is_numeric') === $value;
        }
        
        if (in_array($operator, ['between', 'notBetween'])) {
            return is_array($value) && count($value) === 2 && 
                   is_numeric($value[0]) && is_numeric($value[1]);
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

class FloatField extends FieldBase {
    protected array $operators = [
        'equals', 'notEquals', 'gt', 'gte', 'lt', 'lte', 'between', 'notBetween',
        'in', 'notIn', 'isNull', 'isNotNull'
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        if (in_array($operator, ['in', 'notIn'])) {
            return is_array($value) && !empty($value) && 
                   array_filter($value, 'is_numeric') === $value;
        }
        
        if (in_array($operator, ['between', 'notBetween'])) {
            return is_array($value) && count($value) === 2 && 
                   is_numeric($value[0]) && is_numeric($value[1]);
        }
        
        return is_numeric($value);
    }
    
    public function normalizeFilterValue($value, string $operator) {
        if (is_array($value)) {
            return array_map('floatval', $value);
        }
        return (float) $value;
    }
}

class BooleanField extends FieldBase {
    protected array $operators = ['equals', 'notEquals', 'isNull', 'isNotNull'];
    
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
        'equals', 'notEquals', 'before', 'after', 'between', 'notBetween',
        'isNull', 'isNotNull'
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        if (in_array($operator, ['between', 'notBetween'])) {
            return is_array($value) && count($value) === 2;
        }
        
        // Validate date format - accept string, DateTime, or timestamp
        return is_string($value) || $value instanceof \DateTime || is_numeric($value);
    }
    
    public function normalizeFilterValue($value, string $operator) {
        if (is_array($value)) {
            return array_map([$this, 'normalizeDate'], $value);
        }
        return $this->normalizeDate($value);
    }
    
    private function normalizeDate($value): string {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
            return date('Y-m-d', $value);
        }
        return date('Y-m-d', strtotime($value));
    }
}

class DateTimeField extends FieldBase {
    protected array $operators = [
        'equals', 'notEquals', 'before', 'after', 'between', 'notBetween',
        'isNull', 'isNotNull'
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        if (in_array($operator, ['between', 'notBetween'])) {
            return is_array($value) && count($value) === 2;
        }
        
        return is_string($value) || $value instanceof \DateTime || is_numeric($value);
    }
    
    public function normalizeFilterValue($value, string $operator) {
        if (is_array($value)) {
            return array_map([$this, 'normalizeDateTime'], $value);
        }
        return $this->normalizeDateTime($value);
    }
    
    private function normalizeDateTime($value): string {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', $value);
        }
        return date('Y-m-d H:i:s', strtotime($value));
    }
}

class EmailField extends TextField {
    // Inherit most operators from TextField but add email-specific validation
    protected array $operators = [
        'equals', 'notEquals', 'contains', 'notContains', 'startsWith', 'endsWith',
        'in', 'notIn', 'isNull', 'isNotNull', 'isEmpty', 'isNotEmpty', 'isValidEmail'
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        if ($operator === 'isValidEmail') {
            return true; // No value needed for this operator
        }
        
        return true; // Parent validation handles the rest
    }
}

class PasswordField extends FieldBase {
    // Very limited operators for security - no search/contains operations
    protected array $operators = ['isNull', 'isNotNull', 'isEmpty', 'isNotEmpty'];
    
    // Password fields should not be searchable for security reasons
    protected bool $isSearchable = false;
    
    public function isValidFilterValue($value, string $operator): bool {
        return parent::isValidFilterValue($value, $operator);
    }
}

class EnumField extends FieldBase {
    protected array $operators = ['equals', 'notEquals', 'in', 'notIn', 'isNull', 'isNotNull'];
    
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

class MultiEnumField extends FieldBase {
    protected array $operators = [
        'contains', 'notContains', 'containsAll', 'containsAny', 'containsNone',
        'equals', 'notEquals', 'isNull', 'isNotNull', 'isEmpty', 'isNotEmpty'
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        $allowedOptions = $this->getOptions();
        
        if (in_array($operator, ['contains', 'notContains', 'containsAll', 'containsAny', 'containsNone'])) {
            if (is_string($value)) {
                $value = [$value]; // Single value
            }
            return is_array($value) && array_diff($value, $allowedOptions) === [];
        }
        
        if (in_array($operator, ['equals', 'notEquals'])) {
            return is_array($value) && array_diff($value, $allowedOptions) === [];
        }
        
        return true;
    }
}

class RadioButtonSetField extends EnumField {
    // Same as EnumField since radio buttons represent single selection
    protected array $operators = ['equals', 'notEquals', 'in', 'notIn', 'isNull', 'isNotNull'];
}

class IDField extends IntegerField {
    // ID fields have limited operators for security and performance
    protected array $operators = [
        'equals', 'notEquals', 'gt', 'gte', 'lt', 'lte', 'in', 'notIn', 'isNull', 'isNotNull'
        // Note: No 'between' operator to prevent range scans on primary keys
    ];
}

class ImageField extends FieldBase {
    // Image fields have very limited filtering capabilities
    protected array $operators = ['isNull', 'isNotNull', 'isEmpty', 'isNotEmpty', 'hasFile'];
    
    public function isValidFilterValue($value, string $operator): bool {
        if ($operator === 'hasFile') {
            return is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0], true);
        }
        
        return parent::isValidFilterValue($value, $operator);
    }
}

class RelatedRecordField extends FieldBase {
    protected array $operators = [
        'equals', 'notEquals', 'in', 'notIn', 'isNull', 'isNotNull',
        'exists', 'notExists' // Special operators for relationship existence
    ];
    
    public function isValidFilterValue($value, string $operator): bool {
        if (!parent::isValidFilterValue($value, $operator)) {
            return false;
        }
        
        if (in_array($operator, ['exists', 'notExists'])) {
            return is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0], true);
        }
        
        if (in_array($operator, ['in', 'notIn'])) {
            return is_array($value) && !empty($value) && 
                   array_filter($value, 'is_numeric') === $value;
        }
        
        return is_numeric($value); // Expecting foreign key ID
    }
    
    public function normalizeFilterValue($value, string $operator) {
        if (in_array($operator, ['exists', 'notExists'])) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        
        if (is_array($value)) {
            return array_map('intval', $value);
        }
        
        return (int) $value;
    }
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
    protected ?SortingManager $sortingManager = null;
    protected ?PaginationManager $paginationManager = null;
    protected ?ResponseFormatter $responseFormatter = null;
    protected ?array $parsedParams = null;
    protected ?array $validatedParams = null; // NEW: Store validated parameters
    
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
    
    public function getSortingManager(): ?SortingManager {
        return $this->sortingManager;
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
    
    // NEW: Validated parameters methods
    public function setValidatedParams(array $validatedParams): void {
        $this->validatedParams = $validatedParams;
    }
    
    public function getValidatedParams(): array {
        return $this->validatedParams ?? [
            'filters' => [],
            'search' => [],
            'sorting' => [],
            'pagination' => ['page' => 1, 'pageSize' => 20]
        ];
    }
    
    public function hasValidatedParams(): bool {
        return $this->validatedParams !== null;
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
    
    public function setSortingManager(SortingManager $manager): void {
        $this->sortingManager = $manager;
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
    
    // NEW: Convenience methods for validated parameters (preferred for controllers)
    public function getValidatedFilters(): array {
        return $this->validatedParams['filters'] ?? [];
    }
    
    public function getValidatedSearch(): array {
        return $this->validatedParams['search'] ?? [];
    }
    
    public function getValidatedSorting(): array {
        return $this->validatedParams['sorting'] ?? [];
    }
    
    public function getValidatedPagination(): array {
        return $this->validatedParams['pagination'] ?? ['page' => 1, 'pageSize' => 20];
    }
    
    // NEW: Validation Orchestration Method - Single Point of Parameter Validation
    /**
     * Validate all query parameters at once using aggregated error collection.
     * This is the RECOMMENDED validation approach that collects all validation errors
     * before throwing, providing comprehensive feedback to API clients.
     * 
     * @param ModelBase $model The model to validate parameters against
     * @return array Validated parameters for all query components
     * @throws BadRequestException If any validation errors occur (with all errors aggregated)
     */
    public function validateAllParameters(ModelBase $model): array {
        $validationException = new ParameterValidationException();
        $validatedParams = [];
        
        // Validate filters using FilterCriteria helper
        try {
            $filters = $this->getFilters();
            if (!empty($filters)) {
                $validatedParams['filters'] = $this->filterCriteria->validateAndFilterForModel($filters, $model);
            } else {
                $validatedParams['filters'] = [];
            }
        } catch (ParameterValidationException $e) {
            // Collect filter validation errors
            foreach ($e->getErrors() as $error) {
                $validationException->addError($error['type'], $error['field'], $error['message']);
            }
            $validatedParams['filters'] = []; // Use empty array on validation failure
        }
        
        // Validate search parameters using SearchEngine helper
        try {
            $searchParams = $this->getSearchParams();
            if (!empty($searchParams['term'])) {
                $validatedParams['search'] = $this->searchEngine->validateSearchForModel($searchParams, $model);
            } else {
                $validatedParams['search'] = [];
            }
        } catch (ParameterValidationException $e) {
            // Collect search validation errors
            foreach ($e->getErrors() as $error) {
                $validationException->addError($error['type'], $error['field'], $error['message']);
            }
            $validatedParams['search'] = []; // Use empty array on validation failure
        }
        
        // Validate sorting parameters using SortingManager helper
        try {
            $sorting = $this->getSortingParams();
            if (!empty($sorting)) {
                $validatedParams['sorting'] = $this->sortingManager->validateSortingForModel($sorting, $model);
            } else {
                $validatedParams['sorting'] = $this->sortingManager->getDefaultSorting($model);
            }
        } catch (ParameterValidationException $e) {
            // Collect sorting validation errors
            foreach ($e->getErrors() as $error) {
                $validationException->addError($error['type'], $error['field'], $error['message']);
            }
            $validatedParams['sorting'] = $this->sortingManager->getDefaultSorting($model); // Use default sorting on validation failure
        }
        
        // Validate pagination parameters (minimal validation, rarely fails)
        try {
            $paginationParams = $this->getPaginationParams();
            $validatedParams['pagination'] = $this->validatePaginationParams($paginationParams);
        } catch (ParameterValidationException $e) {
            // Collect pagination validation errors
            foreach ($e->getErrors() as $error) {
                $validationException->addError($error['type'], $error['field'], $error['message']);
            }
            $validatedParams['pagination'] = ['page' => 1, 'pageSize' => 20]; // Use defaults on failure
        }
        
        // Throw aggregated errors if any exist
        if ($validationException->hasErrors()) {
            throw $validationException; // Router will catch this and convert to BadRequestException
        }
        
        return $validatedParams;
    }
    }
    
    // Legacy Model-aware validation methods (maintained for backward compatibility)
    // DEPRECATED: Use validateAllParameters() instead for better error aggregation
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
            return $this->getSortingManager()->getDefaultSorting($model);
        }
        
        return $this->getSortingManager()->validateSortingForModel($sorting, $model);
    }
    
    public function getValidatedSearchParams(ModelBase $model): array {
        $searchParams = $this->getSearchParams();
        if (empty($searchParams)) {
            return [];
        }
        
        $searchEngine = $this->getSearchEngine();
        return $searchEngine ? $searchEngine->validateSearchForModel($searchParams, $model) : [];
    }
    
    private function validatePaginationParams(array $pagination): array {
        $validationException = new ParameterValidationException();
        $validatedPagination = [];
        
        // Validate page number
        $page = (int) ($pagination['page'] ?? 1);
        if ($page < 1) {
            $validationException->addError('pagination', 'page', 'Page number must be >= 1');
            $page = 1;
        }
        $validatedPagination['page'] = $page;
        
        // Validate page size
        $pageSize = (int) ($pagination['pageSize'] ?? $pagination['per_page'] ?? 20);
        if ($pageSize < 1) {
            $validationException->addError('pagination', 'pageSize', 'Page size must be >= 1');
            $pageSize = 20;
        }
        if ($pageSize > 1000) {
            $validationException->addError('pagination', 'pageSize', 'Page size exceeds maximum limit of 1000');
            $pageSize = 1000;
        }
        $validatedPagination['pageSize'] = $pageSize;
        
        // Validate cursor if present
        if (!empty($pagination['cursor'])) {
            try {
                $this->getPaginationManager()->decodeCursor($pagination['cursor']);
                $validatedPagination['cursor'] = $pagination['cursor'];
            } catch (\Exception $e) {
                $validationException->addError('pagination', 'cursor', 'Invalid pagination cursor format');
            }
        }
        
        // Validate limit for cursor-based pagination
        if (!empty($pagination['limit'])) {
            $limit = (int) $pagination['limit'];
            if ($limit < 1) {
                $validationException->addError('pagination', 'limit', 'Limit must be >= 1');
            } elseif ($limit > 1000) {
                $validationException->addError('pagination', 'limit', 'Limit exceeds maximum of 1000');
            } else {
                $validatedPagination['limit'] = $limit;
            }
        }
        
        if ($validationException->hasErrors()) {
            throw $validationException;
        }
        
        return $validatedPagination;
    }
    
    private function generateValidationSummary(array $errors): array {
        $summary = [
            'filter_errors' => 0,
            'search_errors' => 0,
            'sorting_errors' => 0,
            'pagination_errors' => 0,
            'fields_with_errors' => []
        ];
        
        foreach ($errors as $error) {
            $type = $error['type'] ?? 'unknown';
            $field = $error['field'] ?? 'unknown';
            
            switch ($type) {
                case 'filter':
                    $summary['filter_errors']++;
                    break;
                case 'search':
                    $summary['search_errors']++;
                    break;
                case 'sorting':
                    $summary['sorting_errors']++;
                    break;
                case 'pagination':
                    $summary['pagination_errors']++;
                    break;
            }
            
            if ($field !== 'unknown' && !in_array($field, $summary['fields_with_errors'])) {
                $summary['fields_with_errors'][] = $field;
            }
        }
        
        return $summary;
    }
    
    private function generateSuggestedFixes(array $errors, ModelBase $model): array {
        $fixes = [];
        
        // Group errors by type for targeted suggestions
        $errorsByType = [];
        foreach ($errors as $error) {
            $type = $error['type'] ?? 'unknown';
            $errorsByType[$type][] = $error;
        }
        
        // Generate suggestions based on error patterns
        if (isset($errorsByType['filter'])) {
            $fixes['filters'] = [
                'message' => 'Check available filterable fields and operators',
                'available_fields' => array_keys($this->getFilterCriteria()->getSupportedFilters(get_class($model))),
                'example' => '/Users?filter[status]=active&filter[age][gte]=18'
            ];
        }
        
        if (isset($errorsByType['search'])) {
            $fixes['search'] = [
                'message' => 'Check searchable fields configuration',
                'available_fields' => array_keys($this->getSearchEngine()->getSearchableFields($model)),
                'example' => '/Users?search=john&search_fields=first_name,last_name'
            ];
        }
        
        if (isset($errorsByType['sorting'])) {
            $fixes['sorting'] = [
                'message' => 'Use valid field names and sort directions',
                'valid_directions' => ['asc', 'desc'],
                'example' => '/Users?sort=created_at:desc,name:asc'
            ];
        }
        
        if (isset($errorsByType['pagination'])) {
            $fixes['pagination'] = [
                'message' => 'Check pagination parameter values',
                'valid_range' => ['page' => '1-N', 'pageSize' => '1-1000'],
                'example' => '/Users?page=1&pageSize=20'
            ];
        }
        
        return $fixes;
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
        $sortingManager = new SortingManager($request);
        $paginationManager = new PaginationManager($request);
        $responseFormatter = new ResponseFormatter($request);
        
        // Attach as Request properties for easy access
        $request->setParameterParser($parameterParser);
        $request->setFilterCriteria($filterCriteria);
        $request->setSearchEngine($searchEngine);
        $request->setSortingManager($sortingManager);
        $request->setPaginationManager($paginationManager);
        $request->setResponseFormatter($responseFormatter);
        
        // Parse parameters immediately for availability using the new factory pattern
        $parsedParams = $parameterParser->parseUnified($request);
        $request->setParsedParams($parsedParams);
        
        // Perform validation if model is available
        $this->performValidationWithModel($request, $parsedParams);
    }
    
    /**
     * Get model instance from request, return null if no model or invalid model
     */
    protected function getModel(Request $request): ?ModelBase {
        // Only use route parameter for model detection
        if (!$request->has('modelName')) {
            return null;
        }
        
        $modelName = $request->get('modelName');
        if (empty($modelName)) {
            return null;
        }
        
        try {
            $model = ModelFactory::new($modelName);
            $this->logger->info("Model instantiated for validation", [
                'model_name' => $modelName,
                'model_class' => get_class($model)
            ]);
            return $model;
        } catch (\Exception $e) {
            // ModelFactory failed - this is a client error
            $this->logger->warning("Invalid model name in route", [
                'model_name' => $modelName,
                'error' => $e->getMessage()
            ]);
            throw new BadRequestException("Invalid model name: {$modelName}", [
                'error' => 'MODEL_NOT_FOUND',
                'model_name' => $modelName,
                'available_models' => $this->getAvailableModels() // Optional helper
            ]);
        }
    }
    
    /**
     * Perform validation using validation orchestration approach
     */
    protected function performValidationWithModel(Request $request, array $parsedParams): void {
        $model = $this->getModel($request);
        
        if (!$model) {
            // No model available - set empty validated params for graceful fallback
            $request->setValidatedParams([
                'filters' => [],
                'search' => [],
                'sorting' => [],
                'pagination' => $this->getDefaultPagination($parsedParams)
            ]);
            
            $this->logger->info("No model available for validation, using empty parameters", [
                'route' => $request->getPath(),
                'has_model_param' => $request->has('modelName')
            ]);
            return;
        }
        
        // Use validation orchestration to validate all parameters at once
        try {
            $validatedParams = $request->validateAllParameters($model);
            $request->setValidatedParams($validatedParams);
            
            $this->logger->info("Parameter validation successful", [
                'model' => get_class($model),
                'filters_count' => count($validatedParams['filters']),
                'sorting_count' => count($validatedParams['sorting']),
                'has_search' => !empty($validatedParams['search']['term'])
            ]);
            
        } catch (ParameterValidationException $e) {
            // Validation failed - throw comprehensive error
            $this->logger->warning("Parameter validation failed in Router", [
                'model' => get_class($model),
                'total_errors' => count($e->getErrors()),
                'error_summary' => $e->getErrorCountByType()
            ]);
            
            throw new BadRequestException('Invalid query parameters', [
                'errors' => $e->getErrors(),
                'model' => get_class($model),
                'validation_summary' => $this->generateValidationSummary($e->getErrors()),
                'suggestions' => $this->generateSuggestedFixes($e->getErrors(), $model),
                'available_filters' => $this->getAvailableFilters($model),
                'available_sort_fields' => $this->getAvailableSortFields($model)
            ]);
        }
    }
    
    /**
     * Get default pagination when no model is available
     */
    protected function getDefaultPagination(array $parsedParams): array {
        $pagination = $parsedParams['pagination'] ?? [];
        return [
            'page' => max(1, (int) ($pagination['page'] ?? 1)),
            'pageSize' => min(1000, max(1, (int) ($pagination['pageSize'] ?? 20))),
            'type' => $pagination['type'] ?? 'simple'
        ];
    }
    
    /**
     * Generate validation error summary for API response
     */
    protected function generateValidationSummary(array $errors): array {
        $summary = [
            'total_errors' => count($errors),
            'error_types' => [],
            'affected_fields' => []
        ];
        
        foreach ($errors as $error) {
            $type = $error['type'] ?? 'unknown';
            $field = $error['field'] ?? 'unknown';
            
            if (!isset($summary['error_types'][$type])) {
                $summary['error_types'][$type] = 0;
            }
            $summary['error_types'][$type]++;
            
            if (!in_array($field, $summary['affected_fields'])) {
                $summary['affected_fields'][] = $field;
            }
        }
        
        return $summary;
    }
    
    /**
     * Generate suggested fixes for validation errors
     */
    protected function generateSuggestedFixes(array $errors, ModelBase $model): array {
        $fixes = [];
        $errorsByType = [];
        
        // Group errors by type
        foreach ($errors as $error) {
            $type = $error['type'] ?? 'unknown';
            $errorsByType[$type][] = $error;
        }
        
        // Generate type-specific suggestions
        if (isset($errorsByType['filter'])) {
            $fixes['filters'] = [
                'message' => 'Check field names and operators',
                'available_fields' => array_keys($model->getFields()),
                'example' => 'filter[field_name][operator]=value'
            ];
        }
        
        if (isset($errorsByType['search'])) {
            $fixes['search'] = [
                'message' => 'Verify search fields and term length',
                'searchable_fields' => $this->getSearchableFields($model),
                'example' => 'search=term&search_fields=field1,field2'
            ];
        }
        
        if (isset($errorsByType['sorting'])) {
            $fixes['sorting'] = [
                'message' => 'Use valid field names and directions (asc/desc)',
                'sortable_fields' => $this->getSortableFields($model),
                'example' => 'sort=field1:asc,field2:desc'
            ];
        }
        
        if (isset($errorsByType['pagination'])) {
            $fixes['pagination'] = [
                'message' => 'Check page numbers and sizes',
                'limits' => ['min_page' => 1, 'max_page_size' => 1000],
                'example' => 'page=1&pageSize=20'
            ];
        }
        
        return $fixes;
    }
    
    /**
     * Get available filters for a model (for error responses)
     */
    protected function getAvailableFilters(ModelBase $model): array {
        $filters = [];
        foreach ($model->getFields() as $fieldName => $field) {
            if ($field->isDBField()) {
                $filters[$fieldName] = [
                    'operators' => $field->getOperators(),
                    'type' => get_class($field)
                ];
            }
        }
        return $filters;
    }
    
    /**
     * Get available sort fields for a model (for error responses)
     */
    protected function getAvailableSortFields(ModelBase $model): array {
        $sortableFields = [];
        foreach ($model->getFields() as $fieldName => $field) {
            if ($field->isDBField() && $this->isFieldSortable($field)) {
                $sortableFields[] = $fieldName;
            }
        }
        return $sortableFields;
    }
    
    /**
     * Get searchable fields for a model (for error responses)
     */
    protected function getSearchableFields(ModelBase $model): array {
        $searchableFields = [];
        foreach ($model->getFields() as $fieldName => $field) {
            if ($field->isDBField() && $this->isFieldSearchable($field)) {
                $searchableFields[] = $fieldName;
            }
        }
        return $searchableFields;
    }
    
    /**
     * Check if field is sortable (helper for error messages)
     */
    protected function isFieldSortable(FieldBase $field): bool {
        // Basic sortability check - can be enhanced
        $sortableMetadata = $field->getMetadataValue('sortable');
        if ($sortableMetadata !== null) {
            return (bool) $sortableMetadata;
        }
        
        // Most fields are sortable by default except certain types
        $fieldType = get_class($field);
        $shortName = substr($fieldType, strrpos($fieldType, '\\') + 1);
        
        $nonSortableTypes = ['PasswordField', 'ImageField'];
        return !in_array($shortName, $nonSortableTypes);
    }
    
    /**
     * Check if field is searchable (helper for error messages)
     */
    protected function isFieldSearchable(FieldBase $field): bool {
        // Basic searchability check - can be enhanced
        $searchableMetadata = $field->getMetadataValue('searchable');
        if ($searchableMetadata !== null) {
            return (bool) $searchableMetadata;
        }
        
        // Text fields are searchable by default
        $fieldType = get_class($field);
        $shortName = substr($fieldType, strrpos($fieldType, '\\') + 1);
        
        $searchableTypes = ['TextField', 'EmailField', 'IntegerField', 'FloatField', 'EnumField'];
        return in_array($shortName, $searchableTypes);
    }
    
    /**
     * Get list of available models (optional helper for error responses)
     */
    protected function getAvailableModels(): array {
        // This could be implemented to return a list of available models
        // For now, return empty array to avoid complexity
        return [];
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
// Abstract base class for format-specific request parsers
abstract class FormatSpecificRequestParser {
    protected const MAX_PAGE_SIZE = 1000;
    protected const MAX_FILTER_DEPTH = 10;
    protected const DEFAULT_PAGE_SIZE = 20;
    
    protected LoggerInterface $logger;
    protected array $requestData;
    
    public function __construct(array $requestData) {
        $this->requestData = $requestData;
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Get the format identifier for this parser
     */
    abstract public function getFormatName(): string;
    
    /**
     * Check if this parser can handle the given request data
     */
    abstract public function canHandle(array $requestData): bool;
    
    /**
     * Parse pagination parameters for this format
     */
    abstract public function parsePagination(): array;
    
    /**
     * Parse filter parameters for this format
     */
    abstract public function parseFilters(): array;
    
    /**
     * Parse sorting parameters for this format
     */
    abstract public function parseSorting(): array;
    
    /**
     * Parse search parameters for this format
     */
    abstract public function parseSearch(): array;
    
    /**
     * Parse additional options for this format
     */
    public function parseOptions(): array {
        return [
            'include_total' => $this->parseBooleanParam($this->requestData['include_total'] ?? 'true'),
            'include_metadata' => $this->parseBooleanParam($this->requestData['include_metadata'] ?? 'true'),
            'include_deleted' => $this->parseBooleanParam($this->requestData['include_deleted'] ?? 'false'),
            'include_related' => $this->parseRelatedFields($this->requestData['include_related'] ?? []),
            'response_format' => $this->requestData['response_format'] ?? $this->getFormatName(),
            'debug' => $this->parseBooleanParam($this->requestData['debug'] ?? 'false')
        ];
    }
    
    /**
     * Get default page size from config
     */
    protected function getDefaultPageSize(): int {
        $config = \Gravitycar\Core\ServiceLocator::getConfig();
        return $config->get('default_page_size', self::DEFAULT_PAGE_SIZE);
    }
    
    /**
     * Normalize operator names across different formats
     */
    protected function normalizeOperator(string $operator): string {
        $operatorMap = [
            // AG-Grid operators
            'contains' => 'contains',
            'notContains' => 'notContains',
            'equals' => 'equals',
            'notEqual' => 'notEquals',
            'startsWith' => 'startsWith',
            'endsWith' => 'endsWith',
            'lessThan' => 'lt',
            'lessThanOrEqual' => 'lte',
            'greaterThan' => 'gt',
            'greaterThanOrEqual' => 'gte',
            'inRange' => 'between',
            
            // MUI operators
            'is' => 'equals',
            'not' => 'notEquals',
            'after' => 'gt',
            'onOrAfter' => 'gte',
            'before' => 'lt',
            'onOrBefore' => 'lte',
            'isEmpty' => 'isEmpty',
            'isNotEmpty' => 'isNotEmpty',
            'isAnyOf' => 'in',
            
            // Standard operators
            'eq' => 'equals',
            'ne' => 'notEquals',
            'neq' => 'notEquals',
            'like' => 'contains',
            'ilike' => 'contains',
            'begins_with' => 'startsWith',
            'ends_with' => 'endsWith',
            'gte' => 'gte',
            'lte' => 'lte',
            'gt' => 'gt',
            'lt' => 'lt',
            'in' => 'in',
            'not_in' => 'notIn',
            'null' => 'isNull',
            'not_null' => 'isNotNull',
            'between' => 'between'
        ];
        
        return $operatorMap[strtolower($operator)] ?? $operator;
    }
    
    /**
     * Parse filter values based on operator type
     */
    protected function parseFilterValue($value, string $operator) {
        switch ($operator) {
            case 'in':
            case 'notIn':
                if (is_string($value)) {
                    return array_map('trim', explode(',', $value));
                }
                return is_array($value) ? $value : [$value];
                
            case 'between':
            case 'notBetween':
                if (is_string($value)) {
                    $parts = array_map('trim', explode(',', $value));
                    return count($parts) >= 2 ? [$parts[0], $parts[1]] : $value;
                }
                return is_array($value) ? $value : [$value];
                
            default:
                return $value;
        }
    }
    
    /**
     * Parse search fields specification
     */
    protected function parseSearchFields($fields): array {
        if (is_string($fields)) {
            return array_map('trim', explode(',', $fields));
        }
        
        return is_array($fields) ? $fields : [];
    }
    
    /**
     * Parse boolean parameters
     */
    protected function parseBooleanParam($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        }
        
        return (bool) $value;
    }
    
    /**
     * Parse related fields specification
     */
    protected function parseRelatedFields($fields): array {
        if (is_string($fields)) {
            return array_map('trim', explode(',', $fields));
        }
        
        return is_array($fields) ? $fields : [];
    }
    
    /**
     * Validate direction parameter
     */
    protected function validateDirection(string $direction): string {
        $direction = strtoupper(trim($direction));
        return in_array($direction, ['ASC', 'DESC']) ? $direction : 'ASC';
    }
    
    /**
     * Sanitize field name
     */
    protected function sanitizeFieldName(string $field): ?string {
        $field = trim($field);
        // Basic sanitization - allow alphanumeric, underscore, and dot for relationships
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.]*$/', $field)) {
            return $field;
        }
        
        $this->logger->warning("Invalid field name detected", ['field' => $field]);
        return null;
    }
}

// AG-Grid format parser
class AgGridRequestParser extends FormatSpecificRequestParser {
    
    public function getFormatName(): string {
        return 'ag-grid';
    }
    
    public function canHandle(array $requestData): bool {
        return isset($requestData['startRow']) && isset($requestData['endRow']);
    }
    
    public function parsePagination(): array {
        $startRow = max(0, (int) ($this->requestData['startRow'] ?? 0));
        $endRow = (int) ($this->requestData['endRow'] ?? 100);
        $pageSize = min(self::MAX_PAGE_SIZE, max(1, $endRow - $startRow));
        
        return [
            'type' => 'ag-grid',
            'startRow' => $startRow,
            'endRow' => $endRow,
            'pageSize' => $pageSize,
            'offset' => $startRow,
            'limit' => $pageSize
        ];
    }
    
    public function parseFilters(): array {
        $filters = [];
        
        if (!isset($this->requestData['filters']) || !is_array($this->requestData['filters'])) {
            return $filters;
        }
        
        foreach ($this->requestData['filters'] as $field => $filterDef) {
            $field = $this->sanitizeFieldName($field);
            if (!$field || !is_array($filterDef)) {
                continue;
            }
            
            $operator = $filterDef['type'] ?? 'equals';
            $value = $filterDef['filter'] ?? null;
            
            $operator = $this->normalizeOperator($operator);
            
            if ($value !== null && $value !== '') {
                $filters[] = [
                    'field' => $field,
                    'operator' => $operator,
                    'value' => $this->parseFilterValue($value, $operator)
                ];
            }
        }
        
        return $filters;
    }
    
    public function parseSorting(): array {
        $sorting = [];
        
        if (!isset($this->requestData['sort']) || !is_array($this->requestData['sort'])) {
            return $sorting;
        }
        
        foreach ($this->requestData['sort'] as $index => $sortDef) {
            if (!is_array($sortDef)) continue;
            
            $field = $this->sanitizeFieldName($sortDef['colId'] ?? $sortDef['field'] ?? '');
            $direction = $this->validateDirection($sortDef['sort'] ?? $sortDef['direction'] ?? 'ASC');
            
            if ($field) {
                $sorting[] = [
                    'field' => $field,
                    'direction' => $direction,
                    'priority' => $index + 1
                ];
            }
        }
        
        return $sorting;
    }
    
    public function parseSearch(): array {
        $search = [
            'term' => '',
            'fields' => [],
            'mode' => 'contains'
        ];
        
        // AG-Grid global search
        if (isset($this->requestData['globalFilter'])) {
            $search['term'] = trim($this->requestData['globalFilter']);
        }
        
        return $search;
    }
}

// MUI DataGrid format parser
class MuiDataGridRequestParser extends FormatSpecificRequestParser {
    
    public function getFormatName(): string {
        return 'mui';
    }
    
    public function canHandle(array $requestData): bool {
        return isset($requestData['filterModel']) || isset($requestData['sortModel']);
    }
    
    public function parsePagination(): array {
        $page = max(0, (int) ($this->requestData['page'] ?? 0)); // 0-based for MUI
        $pageSize = min(self::MAX_PAGE_SIZE, max(1, (int) ($this->requestData['pageSize'] ?? 25)));
        
        return [
            'type' => 'mui',
            'page' => $page,
            'pageSize' => $pageSize,
            'offset' => $page * $pageSize,
            'limit' => $pageSize
        ];
    }
    
    public function parseFilters(): array {
        $filters = [];
        
        if (!isset($this->requestData['filterModel'])) {
            return $filters;
        }
        
        $filterModel = $this->decodeJsonParam($this->requestData['filterModel']);
        if (!is_array($filterModel)) {
            return $filters;
        }
        
        // Handle items array format (MUI DataGrid Pro)
        if (isset($filterModel['items']) && is_array($filterModel['items'])) {
            foreach ($filterModel['items'] as $item) {
                if (!is_array($item)) continue;
                
                $field = $this->sanitizeFieldName($item['field'] ?? '');
                $operator = $this->normalizeOperator($item['operator'] ?? 'equals');
                $value = $item['value'] ?? null;
                
                if ($field && $value !== null && $value !== '') {
                    $filters[] = [
                        'field' => $field,
                        'operator' => $operator,
                        'value' => $this->parseFilterValue($value, $operator)
                    ];
                }
            }
        } else {
            // Handle direct field mapping format
            foreach ($filterModel as $field => $filterDef) {
                $field = $this->sanitizeFieldName($field);
                if (!$field) continue;
                
                if (is_scalar($filterDef)) {
                    // Simple field = value format
                    if ($filterDef !== null && $filterDef !== '') {
                        $filters[] = [
                            'field' => $field,
                            'operator' => 'equals',
                            'value' => $filterDef
                        ];
                    }
                } elseif (is_array($filterDef)) {
                    // Complex filter definition
                    foreach ($filterDef as $operator => $value) {
                        $normalizedOperator = $this->normalizeOperator($operator);
                        if ($value !== null && $value !== '') {
                            $filters[] = [
                                'field' => $field,
                                'operator' => $normalizedOperator,
                                'value' => $this->parseFilterValue($value, $normalizedOperator)
                            ];
                        }
                    }
                }
            }
        }
        
        return $filters;
    }
    
    public function parseSorting(): array {
        $sorting = [];
        
        if (!isset($this->requestData['sortModel'])) {
            return $sorting;
        }
        
        $sortModel = $this->decodeJsonParam($this->requestData['sortModel']);
        if (!is_array($sortModel)) {
            return $sorting;
        }
        
        foreach ($sortModel as $index => $sortDef) {
            if (!is_array($sortDef)) continue;
            
            $field = $this->sanitizeFieldName($sortDef['field'] ?? '');
            $direction = $this->validateDirection($sortDef['sort'] ?? $sortDef['direction'] ?? 'ASC');
            
            if ($field) {
                $sorting[] = [
                    'field' => $field,
                    'direction' => $direction,
                    'priority' => $index + 1
                ];
            }
        }
        
        return $sorting;
    }
    
    public function parseSearch(): array {
        return [
            'term' => trim($this->requestData['search'] ?? ''),
            'fields' => [],
            'mode' => 'contains'
        ];
    }
    
    /**
     * Decode JSON parameter (string or array)
     */
    private function decodeJsonParam($param) {
        if (is_string($param)) {
            $decoded = json_decode($param, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning("Invalid JSON in parameter", [
                    'json_error' => json_last_error_msg(),
                    'param' => substr($param, 0, 100) // Log first 100 chars
                ]);
                return null;
            }
            return $decoded;
        }
        
        return $param;
    }
}

// Structured format parser (filter[field][operator]=value)
class StructuredRequestParser extends FormatSpecificRequestParser {
    
    public function getFormatName(): string {
        return 'structured';
    }
    
    public function canHandle(array $requestData): bool {
        return isset($requestData['filter']) && is_array($requestData['filter']);
    }
    
    public function parsePagination(): array {
        $page = max(1, (int) ($this->requestData['page'] ?? 1)); // 1-based
        $pageSize = min(self::MAX_PAGE_SIZE, max(1, (int) ($this->requestData['pageSize'] ?? $this->getDefaultPageSize())));
        
        return [
            'type' => 'structured',
            'page' => $page,
            'pageSize' => $pageSize,
            'offset' => ($page - 1) * $pageSize,
            'limit' => $pageSize
        ];
    }
    
    public function parseFilters(): array {
        $filters = [];
        
        if (!isset($this->requestData['filter']) || !is_array($this->requestData['filter'])) {
            return $filters;
        }
        
        foreach ($this->requestData['filter'] as $field => $filterDef) {
            $field = $this->sanitizeFieldName($field);
            if (!$field) continue;
            
            if (is_scalar($filterDef)) {
                // Simple field = value format
                if ($filterDef !== null && $filterDef !== '') {
                    $filters[] = [
                        'field' => $field,
                        'operator' => 'equals',
                        'value' => $filterDef
                    ];
                }
            } elseif (is_array($filterDef)) {
                // Complex filter definition: filter[field][operator] = value
                foreach ($filterDef as $operator => $value) {
                    $normalizedOperator = $this->normalizeOperator($operator);
                    if ($value !== null && $value !== '') {
                        $filters[] = [
                            'field' => $field,
                            'operator' => $normalizedOperator,
                            'value' => $this->parseFilterValue($value, $normalizedOperator)
                        ];
                    }
                }
            }
        }
        
        return $filters;
    }
    
    public function parseSorting(): array {
        $sorting = [];
        
        if (isset($this->requestData['sort']) && is_array($this->requestData['sort'])) {
            foreach ($this->requestData['sort'] as $index => $sortDef) {
                if (!is_array($sortDef)) continue;
                
                $field = $this->sanitizeFieldName($sortDef['field'] ?? '');
                $direction = $this->validateDirection($sortDef['direction'] ?? 'ASC');
                
                if ($field) {
                    $sorting[] = [
                        'field' => $field,
                        'direction' => $direction,
                        'priority' => is_numeric($index) ? $index + 1 : count($sorting) + 1
                    ];
                }
            }
        }
        
        return $sorting;
    }
    
    public function parseSearch(): array {
        $search = [
            'term' => trim($this->requestData['search'] ?? ''),
            'fields' => $this->parseSearchFields($this->requestData['search_fields'] ?? []),
            'mode' => $this->requestData['search_mode'] ?? 'contains'
        ];
        
        return $search;
    }
}

// Advanced format parser (comprehensive parameter support)
class AdvancedRequestParser extends FormatSpecificRequestParser {
    
    public function getFormatName(): string {
        return 'advanced';
    }
    
    public function canHandle(array $requestData): bool {
        return isset($requestData['search_fields']) || 
               isset($requestData['per_page']) ||
               isset($requestData['include_total']);
    }
    
    public function parsePagination(): array {
        $page = max(1, (int) ($this->requestData['page'] ?? 1)); // 1-based
        $perPage = min(self::MAX_PAGE_SIZE, max(1, (int) ($this->requestData['per_page'] ?? $this->getDefaultPageSize())));
        
        return [
            'type' => 'advanced',
            'page' => $page,
            'per_page' => $perPage,
            'pageSize' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'limit' => $perPage,
            'cursor' => $this->requestData['cursor'] ?? null,
            'before' => $this->requestData['before'] ?? null
        ];
    }
    
    public function parseFilters(): array {
        $filters = [];
        
        // Parse structured filters first (if present)
        if (isset($this->requestData['filter']) && is_array($this->requestData['filter'])) {
            $structuredParser = new StructuredRequestParser($this->requestData);
            $filters = array_merge($filters, $structuredParser->parseFilters());
        }
        
        // Parse direct field filters (field=value format)
        $excludeParams = ['page', 'per_page', 'search', 'search_fields', 'search_mode', 'sort', 'filter', 'cursor', 'before', 'include_total', 'include_metadata', 'include_deleted', 'include_related', 'response_format', 'debug'];
        
        foreach ($this->requestData as $key => $value) {
            if (in_array($key, $excludeParams)) {
                continue;
            }
            
            $field = $this->sanitizeFieldName($key);
            if ($field && is_scalar($value) && $value !== '') {
                $filters[] = [
                    'field' => $field,
                    'operator' => 'equals',
                    'value' => $value
                ];
            }
        }
        
        return $filters;
    }
    
    public function parseSorting(): array {
        $sorting = [];
        
        if (isset($this->requestData['sort'])) {
            if (is_string($this->requestData['sort'])) {
                // Parse comma-separated format: "field1:desc,field2:asc"
                $sortPairs = explode(',', $this->requestData['sort']);
                foreach ($sortPairs as $index => $sortPair) {
                    $parts = explode(':', trim($sortPair));
                    $field = $this->sanitizeFieldName(trim($parts[0] ?? ''));
                    $direction = $this->validateDirection(trim($parts[1] ?? 'ASC'));
                    
                    if ($field) {
                        $sorting[] = [
                            'field' => $field,
                            'direction' => $direction,
                            'priority' => $index + 1
                        ];
                    }
                }
            } elseif (is_array($this->requestData['sort'])) {
                // Parse structured array format
                $structuredParser = new StructuredRequestParser($this->requestData);
                $sorting = $structuredParser->parseSorting();
            }
        }
        
        return $sorting;
    }
    
    public function parseSearch(): array {
        return [
            'term' => trim($this->requestData['search'] ?? ''),
            'fields' => $this->parseSearchFields($this->requestData['search_fields'] ?? []),
            'mode' => $this->requestData['search_mode'] ?? 'contains'
        ];
    }
}

// Simple/Default format parser (basic field=value parameters)
class SimpleRequestParser extends FormatSpecificRequestParser {
    
    public function getFormatName(): string {
        return 'simple';
    }
    
    public function canHandle(array $requestData): bool {
        // Default parser - always can handle any request
        return true;
    }
    
    public function parsePagination(): array {
        $page = max(1, (int) ($this->requestData['page'] ?? 1)); // 1-based for standard
        $pageSize = min(self::MAX_PAGE_SIZE, max(1, (int) ($this->requestData['pageSize'] ?? $this->getDefaultPageSize())));
        
        return [
            'type' => 'simple',
            'page' => $page,
            'pageSize' => $pageSize,
            'offset' => ($page - 1) * $pageSize,
            'limit' => $pageSize
        ];
    }
    
    public function parseFilters(): array {
        $filters = [];
        
        // Parse direct field parameters (excluding known pagination/sort parameters)
        $excludeParams = ['page', 'pageSize', 'search', 'sortBy', 'sortOrder', 'limit', 'offset'];
        
        foreach ($this->requestData as $key => $value) {
            if (in_array($key, $excludeParams)) {
                continue;
            }
            
            $field = $this->sanitizeFieldName($key);
            if ($field && is_scalar($value) && $value !== '') {
                $filters[] = [
                    'field' => $field,
                    'operator' => 'equals',
                    'value' => $value
                ];
            }
        }
        
        return $filters;
    }
    
    public function parseSorting(): array {
        $sorting = [];
        
        $field = $this->sanitizeFieldName($this->requestData['sortBy'] ?? '');
        $direction = $this->validateDirection($this->requestData['sortOrder'] ?? 'ASC');
        
        if ($field) {
            $sorting[] = [
                'field' => $field,
                'direction' => $direction,
                'priority' => 1
            ];
        }
        
        return $sorting;
    }
    
    public function parseSearch(): array {
        return [
            'term' => trim($this->requestData['search'] ?? ''),
            'fields' => [],
            'mode' => 'contains'
        ];
    }
}

/**
 * FORMAT-SPECIFIC PARSER ARCHITECTURE OVERVIEW
 * =============================================
 * 
 * The RequestParameterParser has been refactored to use a factory pattern with format-specific parsers:
 * 
 * PARSERS:
 * - FormatSpecificRequestParser: Abstract base class with common utility methods
 * - AgGridRequestParser: Handles AG-Grid startRow/endRow and complex filter formats
 * - MuiDataGridRequestParser: Handles MUI DataGrid JSON-encoded filterModel/sortModel
 * - StructuredRequestParser: Handles filter[field][operator]=value format
 * - AdvancedRequestParser: Comprehensive format with multiple parameter styles
 * - SimpleRequestParser: Basic field=value format (default fallback)
 * 
 * BENEFITS:
 * - Testability: Each format parser can be unit tested independently
 * - Extensibility: Adding new React component formats requires only creating new parser class
 * - Maintainability: Format-specific logic isolated in dedicated classes
 * - Performance: Format detection happens once per request with proper logging
 * - Robustness: Field name sanitization and fallback to SimpleRequestParser ensures no request failures
 */

// Refactored RequestParameterParser - now acts as a factory/coordinator
class RequestParameterParser {
    private const MAX_PAGE_SIZE = 1000;
    private const MAX_FILTER_DEPTH = 10;
    
    private LoggerInterface $logger;
    private array $formatParsers = [];
    
    public function __construct(Request $request) {
        $this->logger = ServiceLocator::getLogger();
        
        // Register format-specific parsers in order of specificity
        // Most specific parsers should be checked first
        $this->registerParser(new AgGridRequestParser($request->getRequestData()));
        $this->registerParser(new MuiDataGridRequestParser($request->getRequestData()));
        $this->registerParser(new StructuredRequestParser($request->getRequestData()));
        $this->registerParser(new AdvancedRequestParser($request->getRequestData()));
        $this->registerParser(new SimpleRequestParser($request->getRequestData())); // Default - always last
    }
    
    /**
     * Register a format-specific parser
     */
    private function registerParser(FormatSpecificRequestParser $parser): void {
        $this->formatParsers[] = $parser;
    }
    
    /**
     * Main entry point - detects format and delegates to appropriate parser
     */
    public function parseUnified(Request $request): array {
        $requestData = $request->getRequestData();
        $parser = $this->selectParser($requestData);
        
        $this->logger->info("Using format parser", [
            'parser' => get_class($parser),
            'format' => $parser->getFormatName(),
            'request_params' => array_keys($requestData)
        ]);
        
        $startTime = microtime(true);
        
        $parsed = [
            'pagination' => $parser->parsePagination(),
            'filters' => $parser->parseFilters(),
            'sorting' => $parser->parseSorting(),
            'search' => $parser->parseSearch(),
            'format' => $parser->getFormatName(),
            'options' => $parser->parseOptions()
        ];
        
        $parseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info("Parameter parsing complete", [
            'format' => $parser->getFormatName(),
            'parse_time_ms' => $parseTime,
            'filters_count' => count($parsed['filters']),
            'sorting_count' => count($parsed['sorting']),
            'search_term_length' => strlen($parsed['search']['term'] ?? '')
        ]);
        
        return $parsed;
    }
    
    /**
     * Select the appropriate format parser
     */
    private function selectParser(array $requestData): FormatSpecificRequestParser {
        foreach ($this->formatParsers as $parser) {
            if ($parser->canHandle($requestData)) {
                return $parser;
            }
        }
        
        // This should never happen since SimpleRequestParser always returns true
        // But just in case, create a fallback
        $this->logger->warning("No parser could handle request, using SimpleRequestParser as fallback");
        return new SimpleRequestParser($requestData);
    }
    
    /**
     * Get available format parsers (for testing/debugging)
     */
    public function getAvailableParsers(): array {
        return array_map(function($parser) {
            return [
                'class' => get_class($parser),
                'format' => $parser->getFormatName()
            ];
        }, $this->formatParsers);
    }
    
    /**
     * Test which parser would be selected for given request data (for testing)
     */
    public function testFormatDetection(array $requestData): string {
        $parser = $this->selectParser($requestData);
        return $parser->getFormatName();
    }
}
```php

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
    // NEW APPROACH: Use pre-validated parameters from Router
    public function list(Request $request): array {
        $modelName = $this->getModelName($request);
        $this->validateModelName($modelName);
        
        // Access pre-validated parameters (validation already done in Router!)
        $validatedParams = $request->getValidatedParams();
        $filters = $validatedParams['filters'];
        $sorting = $validatedParams['sorting'];
        $search = $validatedParams['search'];
        $pagination = $validatedParams['pagination'];
        $responseFormat = $request->getResponseFormat();
        
        // Access specific request data when needed
        $includeDeleted = $request->getRequestParam('include_deleted', false);
        $includeMetadata = $request->getRequestParam('include_metadata', true);
        
        try {
            // Create model instance for database operations
            $queryInstance = ModelFactory::new($modelName);
            
            // Use enhanced DatabaseConnector methods with PRE-VALIDATED parameters
            $databaseConnector = ServiceLocator::get(DatabaseConnector::class);
            
            // Get total count for pagination (filters and search already validated)
            $total = $databaseConnector->getCountWithCriteria(
                $queryInstance, 
                $filters, 
                $search
            );
            
            // Get paginated data (all parameters are validated)
            $records = $databaseConnector->findWithReactParameters(
                $queryInstance,
                $filters,
                $search,
                $sorting,
                $pagination
            );
            
            // Format response using ResponseFormatter
            $responseFormatter = $request->getResponseFormatter();
            $meta = [
                'pagination' => $this->buildPaginationMeta($pagination, $total),
                'filters' => $this->buildFiltersMeta($filters, $queryInstance),
                'search' => $this->buildSearchMeta($search, $queryInstance),
                'sorting' => $sorting
            ];
            
            return $responseFormatter->format($records, $meta, $responseFormat);
            
        } catch (\Exception $e) {
            $this->logger->error("Error in list method", [
                'model' => $modelName,
                'error' => $e->getMessage(),
                'validated_params_count' => [
                    'filters' => count($filters),
                    'sorting' => count($sorting),
                    'search_term_length' => strlen($search['term'] ?? '')
                ]
            ]);
            throw $e;
        }
    }
    
    // LEGACY METHODS: Maintained for backward compatibility during transition
    // These now just delegate to the validated params from Router
    /**
     * @deprecated Use $request->getValidatedParams()['filters'] instead
     */
    public function getValidatedFilters(ModelBase $model): array {
        return $this->request->getValidatedFilters();
    }
    
    /**
     * @deprecated Use $request->getValidatedParams()['sorting'] instead
     */
    public function getValidatedSorting(ModelBase $model): array {
        return $this->request->getValidatedSorting();
    }
    
    /**
     * @deprecated Use $request->getValidatedParams()['search'] instead
     */
    public function getValidatedSearchParams(ModelBase $model): array {
        return $this->request->getValidatedSearch();
    }
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

#### Core Helper Classes Testing
- **FilterCriteria class methods**: Validation logic, SQL operator mapping, field-based operator checking
- **SearchEngine functionality**: Multi-field search, search term parsing, field type validation
- **PaginationManager calculations**: Offset/cursor pagination, page metadata generation, cursor encoding/decoding
- **RequestParameterParser**: Format detection, parameter extraction for all supported React library formats
- **SortingManager**: Multi-field sorting validation, priority ordering, default sorting generation
- **ResponseFormatter**: React library-specific formatting, error response structure, cache key generation

#### Field-Level Configuration Testing
- **FieldBase operator validation**: Each field type's supported operators and validation rules
- **Metadata override functionality**: Configuration inheritance and override mechanisms
- **Security validation**: PasswordField restrictions, field-level accessibility controls

#### Router-Level Validation Testing
- **Model detection and instantiation**: Valid and invalid model name handling
- **Validation orchestration**: Comprehensive parameter validation with error aggregation
- **Error response generation**: BadRequestException formatting with suggested fixes

### 9.2 Integration Tests

#### End-to-End Filtering Scenarios
- **Complex filter combinations**: Multiple operators on different field types simultaneously
- **Field type validation**: Ensure proper operator restrictions for each field type (e.g., no 'contains' on IntegerField)
- **Invalid parameter handling**: Malformed filters, non-existent fields, invalid operators
- **Model-aware validation**: Field existence validation, database field verification

#### Search Functionality Testing
- **Multi-field search**: Search across different field types with proper weighting
- **Search term parsing**: Quote handling, word extraction, boolean operator detection
- **Field-level search controls**: Respect isSearchable configuration, PasswordField exclusions
- **Full-text search integration**: MySQL FULLTEXT search capabilities where enabled

#### Pagination Edge Cases
- **Boundary conditions**: First page, last page, empty results, single record
- **Cursor-based pagination**: Forward/backward navigation, cursor integrity validation
- **Large dataset handling**: Performance with 10k+ records, memory usage optimization
- **React library compatibility**: AG-Grid infinite scroll, MUI DataGrid server-side pagination

#### Error Handling Integration
- **Validation error aggregation**: Multiple parameter errors in single response
- **Graceful degradation**: Default values when validation fails, empty parameter handling
- **Client library error formats**: Proper error response formatting for each React library

### 9.3 Performance Tests

#### Query Performance Benchmarks
- **Filter query optimization**: Test with various filter combinations and field types
- **Search query performance**: Multi-field search across different dataset sizes
- **Sorting performance**: Multi-field sorting with priority ordering
- **Index utilization**: Verify proper database index usage for common query patterns

#### Scalability Testing
- **Large dataset pagination**: Performance with 100k+ records using cursor-based pagination
- **Memory usage monitoring**: Ensure consistent memory usage regardless of dataset size
- **Response time benchmarks**: Target <100ms for typical queries, <500ms for complex searches
- **Concurrent request handling**: Multiple simultaneous filtering/search requests

#### Database Performance
- **Query complexity analysis**: Monitor SQL query generation and execution plans
- **Index effectiveness**: Verify performance improvement from recommended indexes
- **Connection efficiency**: Database connection usage and query batching where applicable

### 9.4 Security Testing

#### Input Validation Security
- **SQL injection prevention**: Parameterized query validation, malicious filter detection
- **Field access control**: Verify field-level permissions respected in filtering/search
- **Parameter sanitization**: Ensure proper sanitization of all input parameters

#### Access Control Integration
- **Model-level permissions**: Integration with existing RBAC system
- **Sensitive field protection**: PasswordField and other sensitive data exclusions
- **Performance attack prevention**: Rate limiting validation, query complexity limits

### 9.5 React Library Compatibility Testing

#### Format-Specific Parser Testing
- **AG-Grid compatibility**: startRow/endRow pagination, complex filter models
- **MUI DataGrid compatibility**: JSON-encoded filterModel/sortModel parsing
- **TanStack Query integration**: Response format validation, cache key generation
- **SWR library support**: Proper response structure and error handling

#### Error Response Testing
- **Library-specific error formats**: Ensure proper error structure for each React library
- **Loading state compatibility**: Proper response metadata for loading indicators
- **Empty result handling**: Graceful empty state responses for all supported libraries

This comprehensive testing strategy ensures the Enhanced Pagination & Filtering system meets all functional, performance, and security requirements while maintaining compatibility with major React data grid libraries.

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
- ✅ **Router-Level Validation Architecture** - Centralized validation eliminates controller repetition
- ✅ **Format-Specific Parser Architecture** - Factory pattern provides extensible request parsing

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

## 9. Architectural Improvements Summary

### 9.1 Format-Specific Parser Architecture ✅ COMPLETED

The RequestParameterParser has been refactored to use a factory pattern with format-specific parsers:

**ARCHITECTURE OVERVIEW**:
- **FormatSpecificRequestParser** - Abstract base class with common utility methods
- **AgGridRequestParser** - Handles AG-Grid `startRow`/`endRow` and complex filter formats
- **MuiDataGridRequestParser** - Handles MUI DataGrid JSON-encoded `filterModel`/`sortModel`
- **StructuredRequestParser** - Handles `filter[field][operator]=value` format
- **AdvancedRequestParser** - Comprehensive format with multiple parameter styles
- **SimpleRequestParser** - Basic `field=value` format (default fallback)

**KEY BENEFITS**:
- **Testability**: Each format parser can be unit tested independently
- **Extensibility**: Adding new React component formats requires only creating new parser class
- **Maintainability**: Format-specific logic isolated in dedicated classes
- **Performance**: Format detection happens once per request with proper logging
- **Robustness**: Field name sanitization and fallback to SimpleRequestParser ensures no request failures

### 9.2 Router-Level Validation Architecture ✅ COMPLETED

Centralized validation in Router layer eliminates controller repetition and provides comprehensive error handling:

**ARCHITECTURE OVERVIEW**:
- **Model Detection**: Uses only `Request->get('modelName')` from route parameters
- **Validation Orchestration**: Router.attachRequestHelpers() performs comprehensive validation
- **Error Aggregation**: ParameterValidationException collects all validation errors
- **Controller Simplification**: Controllers access pre-validated data without validation calls

**KEY BENEFITS**:
1. **Elimination of Controller Repetition**: All validation moved to Router layer
2. **Early Error Detection**: Validation occurs before controller execution
3. **Comprehensive Error Responses**: All validation errors returned in single response
4. **Graceful Fallback**: Non-model routes bypass validation seamlessly
5. **Clean Controller Logic**: Controllers focus solely on business logic

**IMPLEMENTATION DETAILS**:
- **Router.getModel()**: Safe model instantiation with error handling
- **Router.performValidationWithModel()**: Comprehensive validation orchestration
- **Request.setValidatedParams()/getValidatedParams()**: Validated parameter storage
- **Request.validateAllParameters()**: Enhanced with ParameterValidationException
- **ModelBaseAPIController**: Simplified to use pre-validated data

**EXECUTION FLOW**:
1. Router detects model from route parameters
2. Router instantiates model and performs comprehensive validation
3. Router stores validated parameters in Request object
4. Controller accesses pre-validated data for business logic execution
5. Validation errors return early with comprehensive error details

This architecture provides significant performance and maintainability benefits while ensuring comprehensive error handling and simplified controller logic.
