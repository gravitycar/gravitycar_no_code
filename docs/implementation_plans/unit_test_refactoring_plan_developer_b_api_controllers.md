# Unit Test Refactoring Plan - Developer B: API Controllers & Authentication

## Overview
This plan focuses on fixing unit tests for API controllers that have null service dependencies due to insufficient dependency injection in test setup.

## Assigned Test Categories

### 1. AuthController Tests (High Priority)
**Files to Modify:** `Tests/Unit/Api/AuthControllerTest.php`
**Issue:** AuthController has null services causing "Call to member function on null" errors
**Failing Tests:** 12 tests (tests 1-8, plus 4 failures from error output)

#### AuthController Dependencies
AuthController extends ApiControllerBase and requires these services:
```php
public function __construct(
    ?Logger $logger = null,
    ?ModelFactory $modelFactory = null, 
    ?DatabaseConnectorInterface $databaseConnector = null,
    ?MetadataEngineInterface $metadataEngine = null,
    ?Config $config = null,
    ?CurrentUserProviderInterface $currentUserProvider = null,
    // AuthController-specific dependencies:
    ?AuthenticationService $authService = null,
    ?GoogleOAuthService $googleOAuthService = null
)
```

#### Specific Failing Service Calls
- `$this->googleOAuthService->getAuthorizationUrl()` (line 152)
- `$this->authService->authenticateWithGoogle()` (line 195)  
- `$this->logger->warning()` (line 232, 307)
- `$this->authService->authenticateTraditional()` (line 274)
- `$this->authService->registerUser()` (line 448)
- `$this->authService->refreshJwtToken()` (line 348)

#### Fix Strategy
1. Update test setUp() to create and inject all required mock services
2. Configure mock behaviors for successful and failure scenarios
3. Ensure proper exception types are thrown instead of null reference errors

**Example Fix Pattern:**
```php
protected function setUp(): void {
    $this->logger = $this->createMock(Logger::class);
    $this->modelFactory = $this->createMock(ModelFactory::class);
    $this->databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    $this->metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $this->config = $this->createMock(Config::class);
    $this->currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
    $this->authService = $this->createMock(AuthenticationService::class);
    $this->googleOAuthService = $this->createMock(GoogleOAuthService::class);
    
    $this->authController = new AuthController(
        $this->logger,
        $this->modelFactory,
        $this->databaseConnector,
        $this->metadataEngine,
        $this->config,
        $this->currentUserProvider,
        $this->authService,
        $this->googleOAuthService
    );
}
```

### 2. HealthAPIController Tests (Medium Priority)
**Files to Modify:** `Tests/Unit/Api/HealthAPIControllerTest.php`
**Issue:** Null databaseConnector causing "Call to getConnection() on null"
**Failing Tests:** 1 test (test 9 from error output)

#### Fix Strategy
1. Inject proper DatabaseConnector mock with getConnection() method
2. Mock database health check responses

### 3. MetadataAPIController Tests (Medium Priority) 
**Files to Modify:** `Tests/Unit/Api/MetadataAPIControllerTest.php`
**Issue:** Multiple null service dependencies
**Failing Tests:** 7 tests (tests 10-16 from error output)

#### Specific Null Service Issues
- `$this->metadataEngine->get()` (line 139, 226, 295)
- `$this->metadataEngine->getAllRelationships()` (line 358)
- `$this->routeRegistry->getRoutes()` (line 381)
- `$this->cache->clearCache()` (line 424)

#### Fix Strategy
1. Inject proper MetadataEngine mock with get() and getAllRelationships() methods
2. Inject APIRouteRegistry mock with getRoutes() method  
3. Inject DocumentationCache mock with clearCache() method

### 4. TMDBController Path Fix (Low Priority)
**Files to Modify:** `Tests/Unit/Api/Movies/TMDBControllerTest.php`
**Issue:** Class path changed from `Gravitycar\Api\Movies\TMDBController` to `Gravitycar\Api\TMDBController`
**Failing Tests:** 6 tests (tests 17-22 from error output)

#### Fix Strategy
1. Update the class import at top of file
2. No other changes needed - the class functionality is the same

**Example Fix:**
```php
// OLD:
use Gravitycar\Api\Movies\TMDBController;

// NEW:
use Gravitycar\Api\TMDBController;
```

### 5. ModelBaseAPIController Tests (Low Priority)
**Files to Modify:** `Tests/Unit/Models/Api/Api/ModelBaseAPIControllerTest.php`
**Issue:** Null ModelFactory dependency
**Failing Tests:** 1 test (test 84 from error output)

#### Fix Strategy
1. Inject proper ModelFactory mock with getAvailableModels() method

## Implementation Order
1. **AuthController Tests** - Most critical, highest number of failures
2. **MetadataAPIController Tests** - Important for metadata functionality
3. **HealthAPIController Tests** - Quick fix
4. **TMDBController Path Fix** - Trivial path update
5. **ModelBaseAPIController Tests** - Simple ModelFactory injection

## Testing Strategy
After each controller fix:
```bash
# Test individual controllers
vendor/bin/phpunit Tests/Unit/Api/AuthControllerTest.php
vendor/bin/phpunit Tests/Unit/Api/MetadataAPIControllerTest.php
vendor/bin/phpunit Tests/Unit/Api/HealthAPIControllerTest.php
vendor/bin/phpunit Tests/Unit/Api/Movies/TMDBControllerTest.php
vendor/bin/phpunit Tests/Unit/Models/Api/Api/ModelBaseAPIControllerTest.php

# Test all API controllers
vendor/bin/phpunit Tests/Unit/Api/
vendor/bin/phpunit Tests/Unit/Models/Api/
```

## Mock Behavior Patterns
For consistent test behavior:

```php
// Authentication success
$this->authService->method('authenticateWithGoogle')
    ->willReturn(['user_id' => 123, 'token' => 'jwt_token']);

// Authentication failure  
$this->authService->method('authenticateTraditional')
    ->willThrowException(new GCException('Invalid credentials'));

// Metadata queries
$this->metadataEngine->method('get')
    ->willReturn(['models' => ['Users', 'Movies']]);

// Health checks
$this->databaseConnector->method('getConnection')
    ->willReturn($this->createMock(\Doctrine\DBAL\Connection::class));
```

## Expected Impact
- **Fixes 27+ failing tests** (AuthController: 12, MetadataAPI: 7, TMDB: 6, Health: 1, ModelBaseAPI: 1)
- **Critical authentication functionality** working in tests
- **API documentation and health endpoints** functioning

## Dependencies
- No dependencies on other developer work  
- Can be completed independently
- Does not conflict with Router/Infrastructure or ModelFactory work

## Estimated Time
- AuthController: 3-4 hours (complex authentication flows)
- MetadataAPIController: 2-3 hours (multiple service dependencies)
- HealthAPIController: 1 hour (simple fix)
- TMDBController: 30 minutes (path change only)
- ModelBaseAPIController: 1 hour (simple dependency injection)
- **Total: 7.5-9.5 hours**

## Success Criteria
- All AuthController tests pass (12 tests)
- All MetadataAPIController tests pass (7 tests)  
- All HealthAPIController tests pass (1 test)
- All TMDBController tests pass (6 tests)
- All ModelBaseAPIController tests pass (1 test)
- Proper exception types thrown instead of null reference errors
- Mock services properly configured for test scenarios