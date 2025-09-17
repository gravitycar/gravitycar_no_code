# Unit Test Refactoring Plan - Developer A: Router & Core Infrastructure

## Overview
This plan focuses on fixing unit tests for core infrastructure classes that have undergone major constructor changes during the dependency injection refactoring.

## Assigned Test Categories

### 1. Router Tests (High Priority)
**Files to Modify:** `Tests/Unit/Api/RouterTest.php`
**Issue:** Router constructor now requires 9 parameters instead of 1
**Failing Tests:** 28 tests (tests 23-50 from error output)

#### Router Constructor Changes
```php
// OLD (what tests expect):
public function __construct(MetadataEngine $metadataEngine)

// NEW (current implementation):
public function __construct(
    Logger $logger,
    MetadataEngineInterface $metadataEngine,
    APIRouteRegistry $routeRegistry,
    APIPathScorer $pathScorer,
    APIControllerFactory $controllerFactory,
    ModelFactory $modelFactory,
    AuthenticationService $authenticationService,
    AuthorizationService $authorizationService,
    CurrentUserProviderInterface $currentUserProvider
)
```

#### Fix Strategy
1. Update `setUp()` method to create all 9 mock dependencies
2. Update router instantiation to pass all required parameters
3. Update any method calls that depend on the new services

**Example Fix Pattern:**
```php
protected function setUp(): void {
    $this->logger = $this->createMock(Logger::class);
    $this->metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $this->routeRegistry = $this->createMock(APIRouteRegistry::class);
    $this->pathScorer = $this->createMock(APIPathScorer::class);
    $this->controllerFactory = $this->createMock(APIControllerFactory::class);
    $this->modelFactory = $this->createMock(ModelFactory::class);
    $this->authService = $this->createMock(AuthenticationService::class);
    $this->authzService = $this->createMock(AuthorizationService::class);
    $this->currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
    
    $this->router = new Router(
        $this->logger,
        $this->metadataEngine,
        $this->routeRegistry,
        $this->pathScorer,
        $this->controllerFactory,
        $this->modelFactory,
        $this->authService,
        $this->authzService,
        $this->currentUserProvider
    );
}
```

### 2. DatabaseConnector Tests (High Priority)
**Files to Modify:** 
- `Tests/Unit/Database/DatabaseConnectorTest.php`
- `Tests/Unit/DatabaseTestCase.php` (shared base class)

**Issue:** DatabaseConnector constructor now requires Config object instead of array
**Failing Tests:** 20 tests (tests 52-73 from error output)

#### DatabaseConnector Constructor Changes
```php
// OLD (what tests expect):
public function __construct(Logger $logger, array $dbParams)

// NEW (current implementation):
public function __construct(Logger $logger, Config $config)
```

#### Fix Strategy
1. Update `DatabaseTestCase.php` to create Config mock instead of array
2. Update all DatabaseConnector instantiations throughout test suite
3. Mock Config->getDatabaseParams() method to return test database parameters

**Example Fix Pattern:**
```php
// In DatabaseTestCase.php setUp():
$this->config = $this->createMock(Config::class);
$this->config->method('getDatabaseParams')->willReturn([
    'host' => 'localhost',
    'dbname' => 'test_db',
    'username' => 'test_user',
    'password' => 'test_pass'
]);

$this->databaseConnector = new DatabaseConnector($this->logger, $this->config);
```

### 3. Database-dependent Relationship Tests
**Files to Modify:** `Tests/Unit/Relationships/RelationshipBaseDatabaseTest.php`
**Issue:** Inherits from DatabaseTestCase which has DatabaseConnector constructor issues
**Failing Tests:** 9 tests (tests 86-94 from error output)

#### Fix Strategy
1. These will be automatically fixed once DatabaseTestCase.php is updated
2. Verify that relationship tests still pass after DatabaseConnector fixes

## Implementation Order
1. **Fix DatabaseTestCase.php first** - This will resolve multiple test failures at once
2. **Fix RouterTest.php** - Major infrastructure component
3. **Verify RelationshipBaseDatabaseTest.php** - Should auto-resolve

## Testing Strategy
After each fix:
```bash
# Test specific files
vendor/bin/phpunit Tests/Unit/Database/DatabaseConnectorTest.php
vendor/bin/phpunit Tests/Unit/Api/RouterTest.php
vendor/bin/phpunit Tests/Unit/Relationships/RelationshipBaseDatabaseTest.php

# Test all affected areas
vendor/bin/phpunit Tests/Unit/Database/
vendor/bin/phpunit Tests/Unit/Api/RouterTest.php
vendor/bin/phpunit Tests/Unit/Relationships/
```

## Expected Impact
- **Fixes 57+ failing tests** (Router: 28, DatabaseConnector: 20, Relationships: 9)
- **High-impact fixes** - These are fundamental infrastructure classes
- **No conflicts with other developers** - These files are isolated from API controller and model factory work

## Dependencies
- No dependencies on other developer work
- Can be completed independently
- Provides foundation for other test fixes

## Estimated Time
- DatabaseTestCase.php: 1-2 hours
- RouterTest.php: 2-3 hours  
- Verification: 1 hour
- **Total: 4-6 hours**

## Success Criteria
- All DatabaseConnector tests pass (20 tests)
- All Router tests pass (28 tests)
- All RelationshipBaseDatabaseTest tests pass (9 tests)
- No new test failures introduced
- Proper dependency injection patterns used throughout