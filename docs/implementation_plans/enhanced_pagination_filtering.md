# Enhanced Pagination & Filtering System Implementation Plan

## 1. Feature Overview

This plan focuses on implementing advanced pagination and filtering capabilities for the Gravitycar Framework's REST API to provide React-friendly data management. The system will enhance the existing basic pagination with comprehensive search, filtering, and sorting functionality.

## 2. Current State Assessment

**Current State**: Basic pagination exists but lacks frontend-friendly features
**Impact**: Essential for React data grids, tables, and lists
**Priority**: HIGH - Week 1-2 implementation

### 2.1 Existing Capabilities
- Basic pagination in ModelBaseAPIController
- Simple limit/offset functionality
- JSON response format

### 2.2 Missing Features
- Advanced pagination metadata
- Search functionality
- Dynamic filtering system
- Sorting capabilities
- React-friendly response format

## 3. Requirements

### 3.1 Functional Requirements
- React-friendly pagination response format
- Search across multiple fields
- Dynamic filter building from query parameters
- Multiple filter types (equals, contains, range, etc.)
- Sorting by multiple fields
- SQL injection prevention
- Performance optimization for large datasets

### 3.2 Non-Functional Requirements
- Backward compatibility with existing pagination
- Efficient database queries
- Configurable per-model search/filter fields
- Memory-efficient processing
- Response time under 500ms for typical queries

## 4. Design

### 4.1 Architecture Components

```php
// Filter Criteria Management
class FilterCriteria {
    public function parseFromRequest(Request $request): array;
    public function applyToQuery(QueryBuilder $qb, array $filters): void;
    public function validateFilters(array $filters, string $model): bool;
    public function getSupportedFilters(string $model): array;
}

// Search Engine
class SearchEngine {
    public function buildSearchQuery(QueryBuilder $qb, string $searchTerm, array $searchFields): void;
    public function getSearchableFields(string $model): array;
    public function parseSearchTerm(string $term): array;
}

// Pagination Manager
class PaginationManager {
    public function buildPaginationResponse(array $data, int $total, int $page, int $perPage): array;
    public function calculatePageInfo(int $total, int $page, int $perPage): array;
    public function generatePaginationLinks(string $baseUrl, array $params): array;
}

// Enhanced API Controller Methods
class ModelBaseAPIController {
    public function getListWithAdvancedFiltering(Request $request): array;
    public function applySearch(QueryBuilder $qb, string $searchTerm): void;
    public function applyFilters(QueryBuilder $qb, array $filters): void;
    public function applySorting(QueryBuilder $qb, array $sorting): void;
}
```

### 4.2 Query Parameter Format

```
GET /Users?search=john&filter[role]=admin&filter[age][gte]=18&filter[age][lte]=65&sort=created_at:desc,name:asc&page=2&per_page=20
```

### 4.3 Response Format

```json
{
  "success": true,
  "status": 200,
  "data": [...],
  "pagination": {
    "current_page": 2,
    "per_page": 20,
    "total": 157,
    "total_pages": 8,
    "has_previous": true,
    "has_next": true,
    "from": 21,
    "to": 40,
    "links": {
      "first": "/Users?page=1&per_page=20&search=john&filter[role]=admin",
      "last": "/Users?page=8&per_page=20&search=john&filter[role]=admin",
      "prev": "/Users?page=1&per_page=20&search=john&filter[role]=admin",
      "next": "/Users?page=3&per_page=20&search=john&filter[role]=admin"
    }
  },
  "filters": {
    "search": "john",
    "active_filters": {
      "role": "admin",
      "age": {
        "gte": 18,
        "lte": 65
      }
    },
    "available_filters": {
      "role": ["admin", "user", "moderator"],
      "status": ["active", "inactive"],
      "age": {
        "type": "range",
        "min": 0,
        "max": 120
      }
    }
  },
  "sorting": {
    "active": [
      {"field": "created_at", "direction": "desc"},
      {"field": "name", "direction": "asc"}
    ],
    "available": ["id", "name", "email", "created_at", "updated_at"]
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

## 5. Implementation Steps

### 5.1 Phase 1: Core Filtering System (Week 1)

#### Step 1: FilterCriteria Class
```php
class FilterCriteria {
    private array $supportedOperators = [
        'eq' => '=',           // equals
        'ne' => '!=',          // not equals
        'gt' => '>',           // greater than
        'gte' => '>=',         // greater than or equal
        'lt' => '<',           // less than
        'lte' => '<=',         // less than or equal
        'like' => 'LIKE',      // contains
        'in' => 'IN',          // in array
        'nin' => 'NOT IN',     // not in array
        'null' => 'IS NULL',   // is null
        'nnull' => 'IS NOT NULL' // is not null
    ];
    
    public function parseFromRequest(Request $request): array;
    public function applyToQuery(QueryBuilder $qb, array $filters): void;
    public function validateFilters(array $filters, string $model): bool;
}
```

#### Step 2: Search Engine Implementation
```php
class SearchEngine {
    public function buildSearchQuery(QueryBuilder $qb, string $searchTerm, array $searchFields): void {
        if (empty($searchTerm) || empty($searchFields)) {
            return;
        }
        
        $qb->andWhere(function(QueryBuilder $subQb) use ($searchTerm, $searchFields) {
            foreach ($searchFields as $field) {
                $subQb->orWhere($field . ' LIKE ?', ['%' . $searchTerm . '%']);
            }
        });
    }
}
```

#### Step 3: Enhanced ModelBaseAPIController
- Update `getList()` method to support advanced filtering
- Add query parameter parsing
- Integrate FilterCriteria and SearchEngine

### 5.2 Phase 2: Pagination Enhancement (Week 1)

#### Step 1: PaginationManager Class
```php
class PaginationManager {
    public function buildPaginationResponse(array $data, int $total, int $page, int $perPage): array {
        $totalPages = (int) ceil($total / $perPage);
        $from = ($page - 1) * $perPage + 1;
        $to = min($page * $perPage, $total);
        
        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages,
            'from' => $total > 0 ? $from : 0,
            'to' => $total > 0 ? $to : 0
        ];
    }
}
```

#### Step 2: Link Generation
- Generate first, last, prev, next links
- Preserve query parameters in pagination links
- Handle edge cases (first page, last page)

### 5.3 Phase 3: Model Configuration (Week 2)

#### Step 1: Searchable Fields Configuration
```php
// In User model
class User extends ModelBase {
    protected array $searchableFields = ['first_name', 'last_name', 'email', 'username'];
    protected array $filterableFields = [
        'role' => ['type' => 'enum', 'values' => ['admin', 'user', 'moderator']],
        'status' => ['type' => 'enum', 'values' => ['active', 'inactive']],
        'age' => ['type' => 'range', 'min' => 0, 'max' => 120],
        'created_at' => ['type' => 'date'],
        'email_verified' => ['type' => 'boolean']
    ];
    protected array $sortableFields = ['id', 'name', 'email', 'created_at', 'updated_at'];
}
```

#### Step 2: Metadata Integration
- Add search/filter configuration to model metadata
- Auto-generate filter options from field types
- Support for relationship-based filtering

## 6. Query Parameter Specification

### 6.1 Search Parameters
```
?search=john              // Simple search across searchable fields
?search[name]=john        // Search specific field
?search[advanced]=true    // Enable advanced search parsing
```

### 6.2 Filter Parameters
```
?filter[role]=admin                    // Simple equality
?filter[age][gte]=18                   // Greater than or equal
?filter[age][lte]=65                   // Less than or equal
?filter[status][in]=active,pending     // In array
?filter[email][like]=@company.com      // Contains
?filter[deleted_at][null]=true         // Is null
```

### 6.3 Sorting Parameters
```
?sort=name                    // Sort by name ascending
?sort=name:asc               // Sort by name ascending (explicit)
?sort=name:desc              // Sort by name descending
?sort=created_at:desc,name:asc // Multiple sort fields
```

### 6.4 Pagination Parameters
```
?page=2                      // Page number (1-based)
?per_page=20                 // Items per page
?limit=20&offset=40          // Alternative format (backward compatibility)
```

## 7. Database Optimization

### 7.1 Index Strategy
```sql
-- Add indexes for commonly filtered/sorted fields
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_created_at ON users(created_at);
CREATE INDEX idx_users_email ON users(email);

-- Composite indexes for common filter combinations
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_users_created_at_role ON users(created_at, role);

-- Full-text search index for search functionality
CREATE FULLTEXT INDEX idx_users_search ON users(first_name, last_name, email);
```

### 7.2 Query Optimization
- Use LIMIT/OFFSET efficiently
- Avoid COUNT(*) for large datasets when possible
- Implement query result caching for common searches
- Use prepared statements for all dynamic queries

## 8. React Integration Examples

### 8.1 React Query Integration
```typescript
interface PaginationParams {
  page?: number;
  per_page?: number;
  search?: string;
  filters?: Record<string, any>;
  sort?: string[];
}

const useUsers = (params: PaginationParams) => {
  return useQuery(['users', params], () => 
    fetchUsers(params)
  );
};
```

### 8.2 Filter Hook Example
```typescript
const useFilters = (model: string) => {
  const [filters, setFilters] = useState({});
  const [search, setSearch] = useState('');
  const [sort, setSort] = useState([]);
  
  const updateFilter = (field: string, value: any) => {
    setFilters(prev => ({ ...prev, [field]: value }));
  };
  
  return { filters, search, sort, updateFilter, setSearch, setSort };
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

### 12.1 Framework Components
- ModelBase for model metadata
- QueryBuilder for database operations
- RestApiHandler for request processing
- Exception handling system

### 12.2 Database Requirements
- Support for complex WHERE clauses
- Full-text search capabilities (optional)
- Index optimization support

## 13. Risks and Mitigations

### 13.1 Performance Risks
- **Risk**: Slow queries with complex filters
- **Mitigation**: Query optimization, indexing strategy, query analysis

- **Risk**: Memory usage with large result sets
- **Mitigation**: Streaming responses, result set limits

### 13.2 Security Risks
- **Risk**: SQL injection through dynamic filters
- **Mitigation**: Parameterized queries, input validation

### 13.3 Compatibility Risks
- **Risk**: Breaking existing API consumers
- **Mitigation**: Backward compatibility layer, gradual migration

## 14. Estimated Timeline

**Total Time: 2 weeks**

- **Week 1**: FilterCriteria, SearchEngine, basic pagination enhancement
- **Week 2**: Model configuration, optimization, testing, documentation

This implementation will provide React applications with powerful, efficient data management capabilities while maintaining the Gravitycar Framework's performance and security standards.
