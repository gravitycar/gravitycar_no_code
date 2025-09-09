# Aura DI Usage Assessment and Refactoring Plan

## Executive Summary

After thoroughly examining the Aura DI documentation and our current implementation, I've identified several significant deviations from intended usage patterns that are creating complexity in our testing and making our dependency injection system less effective.

## Assessment Findings

### 1. Are we using Aura as intended? **NO**

**Current Anti-Patterns:**
- **Service Locator Pattern**: We're using `ServiceLocator` as a static service locator, which Aura DI explicitly warns against ("that's bad, and you should feel bad")
- **Manual Constructor Dependency**: Our classes manually call `ServiceLocator::getLogger()` and other services in constructors instead of receiving them as constructor parameters
- **Container Locking Violations**: We're not properly using the two-stage configuration (define/modify) pattern
- **Missing Auto-Resolution**: We're not leveraging Aura's auto-resolution capabilities for constructor injection

**Intended Aura DI Pattern:**
```php
// SHOULD BE: Constructor injection with typehints
class ModelBase {
    public function __construct(
        Logger $logger,
        MetadataEngine $metadataEngine,
        DatabaseConnector $dbConnector
    ) {
        $this->logger = $logger;
        $this->metadataEngine = $metadataEngine;
        $this->dbConnector = $dbConnector;
    }
}

// Configuration in ContainerConfig
$di->params['ModelBase']['logger'] = $di->lazyGet('logger');
$di->params['ModelBase']['metadataEngine'] = $di->lazyGet('metadata_engine');
```

**Current Anti-Pattern:**
```php
// IS: Manual service locator calls
class ModelBase {
    public function __construct() {
        $this->logger = ServiceLocator::getLogger();          // BAD
        $this->metadataEngine = ServiceLocator::getMetadataEngine(); // BAD
    }
}
```

### 2. What needs to change?

#### A. Constructor Injection Refactoring

**Classes requiring constructor injection:**
- `ModelBase` - needs Logger, MetadataEngine
- `DatabaseConnector` - needs Logger, Config  
- `RelationshipBase` - needs Logger, CoreFieldsMetadata, MetadataEngine
- `MetadataEngine` - should use singleton pattern properly through DI
- All field classes extending `FieldBase`
- All validation rule classes
- All API controllers

#### B. Service Configuration Improvements

**Current Issue**: ContainerConfig doesn't use proper lazy injection patterns
```php
// BAD: Current approach
$di->set('logger', function() { return new Logger('gravitycar'); });

// GOOD: Proper Aura DI pattern  
$di->set('logger', $di->lazy(function() use ($di) {
    $config = $di->get('config');
    return new Logger('gravitycar');
}));
```

#### C. Auto-Resolution Setup

Enable auto-resolution for interface/abstract dependencies:
```php
$builder = new ContainerBuilder();
$di = $builder->newInstance(ContainerBuilder::AUTO_RESOLVE);

// Configure interface mappings
$di->types['LoggerInterface'] = $di->lazyGet('logger');
$di->types['DatabaseConnectorInterface'] = $di->lazyGet('database_connector');
```

### 3. Would this improve unit testing? **YES, SIGNIFICANTLY**

#### Current Testing Problems:
1. **Complex Mock Setup**: Tests need elaborate testable subclasses because dependencies are hardcoded
2. **ServiceLocator Dependency**: Tests must mock static ServiceLocator calls
3. **Brittle Tests**: Changes to dependency resolution break multiple tests
4. **No Isolation**: True unit testing is impossible due to ServiceLocator coupling

#### Post-Refactoring Benefits:
```php
// BEFORE: Complex test setup
class ModelBaseTest extends UnitTestCase {
    public function testSomething() {
        $model = new TestableModelBase($this->logger); // Custom subclass needed
        $model->setMockMetadataEngine($mockEngine);    // Manual injection
        // ... 50 lines of mock setup
    }
}

// AFTER: Simple constructor injection
class ModelBaseTest extends UnitTestCase {
    public function testSomething() {
        $mockLogger = $this->createMock(Logger::class);
        $mockMetadata = $this->createMock(MetadataEngine::class);
        
        $model = new ModelBase($mockLogger, $mockMetadata); // Direct injection
        // Test the actual class, not a test subclass
    }
}
```

## Implementation Plan

### Phase 1: Foundation Classes (Week 1)

#### 1.1 Update Core Configuration Classes
- **File**: `src/Core/ContainerConfig.php`
- **Changes**: 
  - Enable auto-resolution: `$builder->newInstance(ContainerBuilder::AUTO_RESOLVE)`
  - Convert all service definitions to use `$di->lazy()` patterns
  - Add proper interface/abstract type mappings

#### 1.2 Create Interfaces for Key Components
- **New Files**:
  - `src/Contracts/LoggerInterface.php` (extend PSR LoggerInterface)
  - `src/Contracts/MetadataEngineInterface.php`
  - `src/Contracts/DatabaseConnectorInterface.php`
  
#### 1.3 Update Service Definitions
- **File**: `src/Core/ContainerConfig.php`
- **Add interface mappings**:
```php
$di->types['LoggerInterface'] = $di->lazyGet('logger');
$di->types['MetadataEngineInterface'] = $di->lazyGet('metadata_engine');
$di->types['DatabaseConnectorInterface'] = $di->lazyGet('database_connector');
```

#### 1.4 Implement Setter Injection Patterns
- **Use Cases**: Optional dependencies, post-construction configuration, circular dependency resolution
- **Configuration in ContainerConfig**:
```php
// Optional cache injection for MetadataEngine
$di->setters['MetadataEngine']['setCache'] = $di->lazyGet('cache_service');

// Performance monitoring injection (optional)
$di->setters['DatabaseConnector']['setProfiler'] = $di->lazyGet('query_profiler');

// Context injection for field classes
$di->setters['FieldBase']['setValidationContext'] = $di->lazyGet('validation_context');
```

#### 1.5 Critical Application Health Check (End of Phase 1)

**CRITICAL**: After completing foundation changes, verify the application is still functional.

**Validation Test**:
```bash
# Test core API functionality
gravitycar_api_call get_movie_quotes  # Tests full stack functionality
gravitycar_api_call health_detailed   # System health verification
```

**Failure Protocol**: If validation fails, STOP Phase 2 and fix foundation issues immediately.

### Phase 2: Core Classes Refactoring (Week 2)

#### 2.1 MetadataEngine Singleton Fix
- **File**: `src/Metadata/MetadataEngine.php`
- **Changes**:
  - Remove singleton pattern (let DI container handle)
  - Add proper constructor injection
  - Update ContainerConfig to register as singleton service

#### 2.2 DatabaseConnector Constructor Injection
- **File**: `src/Database/DatabaseConnector.php`
- **Changes**:
```php
// BEFORE
public function __construct(?LoggerInterface $logger = null, ?array $dbParams = null) {
    $this->logger = $logger ?? ServiceLocator::getLogger();
    $config = ServiceLocator::getConfig();
    $this->dbParams = $dbParams ?? $config->get('database') ?? [];
}

// AFTER  
public function __construct(LoggerInterface $logger, Config $config, ?array $dbParams = null) {
    $this->logger = $logger;
    $this->dbParams = $dbParams ?? $config->get('database') ?? [];
}
```

#### 2.3 Critical Application Health Check (End of Phase 2)

**CRITICAL**: After core class refactoring, verify the application is still functional.

**Validation Test**:
```bash
# Test core API functionality after metadata/database changes
gravitycar_api_call get_movie_quotes  # Tests metadata and database systems
gravitycar_api_call get_movies         # Additional model validation
gravitycar_api_call health_detailed   # System health verification
```

**Failure Protocol**: If validation fails, STOP Phase 3 and fix core class issues immediately.

### Phase 3: Model Classes Refactoring (Week 3)

#### 3.1 ModelBase Constructor Injection
- **File**: `src/Models/ModelBase.php`
- **Changes**:
```php
// BEFORE
public function __construct() {
    $this->logger = ServiceLocator::getLogger();
    $this->metadataEngine = ServiceLocator::getMetadataEngine();
    $this->loadMetadata();
}

// AFTER
public function __construct(
    LoggerInterface $logger,
    MetadataEngineInterface $metadataEngine
) {
    $this->logger = $logger;
    $this->metadataEngine = $metadataEngine;
    $this->loadMetadata();
}

// Optional dependencies via setter injection
public function setDatabaseConnector(DatabaseConnectorInterface $connector): void {
    $this->databaseConnector = $connector;
}

public function setAuthenticationService(?AuthenticationService $auth): void {
    $this->authService = $auth; // Optional for read-only operations
}
```

#### 3.2 Setter Injection for Field Classes
- **Files**: All field classes in `src/Fields/`
- **Pattern**: Use constructor for core dependencies, setters for optional/contextual ones
```php
// RelatedRecordField example
public function __construct(LoggerInterface $logger, array $metadata) {
    $this->logger = $logger;
    $this->metadata = $metadata;
}

public function setDatabaseConnector(DatabaseConnectorInterface $connector): void {
    $this->databaseConnector = $connector; // Only needed for validation
}

public function setValidationContext(?ValidationContext $context): void {
    $this->validationContext = $context; // Optional enhancement
}
```

#### 3.2 RelationshipBase Constructor Injection
- **File**: `src/Relationships/RelationshipBase.php`
- **Similar pattern to ModelBase**

#### 3.3 Critical Application Health Check (End of Phase 3)

**CRITICAL**: After model class refactoring, verify the application is still functional.

**Validation Test**:
```bash
# Test model functionality after DI refactoring
gravitycar_api_call get_movie_quotes  # Tests model instantiation and field handling
gravitycar_api_call get_movies         # Tests model metadata and relationships
gravitycar_api_call health_detailed   # System health verification
```

**Failure Protocol**: If validation fails, STOP Phase 4 and fix model class issues immediately.

### Phase 4: Factory Pattern Updates (Week 4)

#### 4.1 ModelFactory Refactoring - Instance-Based Design

**Current Problem**: ModelFactory uses static methods that hide ServiceLocator dependencies, making testing difficult and violating DI principles.

**New Design**: Convert to instance-based factory with DI container integration.

```php
// BEFORE: Static methods with hidden dependencies
$user = ModelFactory::new('Users');
$user = ModelFactory::retrieve('Users', '123');

// AFTER: Instance-based factory injected via DI
class SomeController {
    public function __construct(private ModelFactory $models) {}
    
    public function someMethod() {
        $user = $this->models->new('Users');
        $user = $this->models->retrieve('Users', '123');
    }
}
```

**Key Changes to ModelFactory**:

```php
class ModelFactory {
    public function __construct(
        private ContainerInterface $container,
        private LoggerInterface $logger,
        private DatabaseConnectorInterface $dbConnector
    ) {}
    
    // Convert static methods to instance methods
    public function new(string $modelName): ModelBase {
        $modelClass = $this->resolveModelClass($modelName);
        return $this->container->get($modelClass); // Use DI container
    }
    
    public function retrieve(string $modelName, string $id): ?ModelBase {
        $model = $this->new($modelName);
        $row = $this->dbConnector->findById($model, $id);
        if (!$row) return null;
        
        $model->populateFromRow($row);
        return $model;
    }
    
    // Add convenience methods for common patterns
    public function createNew(string $modelName, array $data = []): ModelBase {
        $model = $this->new($modelName);
        if ($data) {
            $model->populateFromArray($data);
        }
        return $model;
    }
    
    public function findOrNew(string $modelName, string $id): ModelBase {
        return $this->retrieve($modelName, $id) ?? $this->new($modelName);
    }
}
```

#### 4.2 DI Container Configuration for ModelFactory

```php
// In ContainerConfig::configureCoreServices()
$di->set('model_factory', $di->lazyNew('ModelFactory'));
$di->params['ModelFactory'] = [
    'container' => $di->lazyGet('container'),
    'logger' => $di->lazyGet('logger'),
    'dbConnector' => $di->lazyGet('database_connector')
];

// Type mapping for easy injection
$di->types['ModelFactoryInterface'] = $di->lazyGet('model_factory');
```

#### 4.3 ModelFactory Access Strategy - ServiceLocator Bridge Pattern

**Implementation**: Hybrid approach with instance methods as primary API and static methods as compatibility bridges.

```php
class ModelFactory {
    // === INSTANCE METHODS (Primary API) ===
    public function new(string $modelName): ModelBase {
        $modelClass = $this->resolveModelClass($modelName);
        return $this->container->get($modelClass);
    }
    
    public function retrieve(string $modelName, string $id): ?ModelBase {
        $model = $this->new($modelName);
        $row = $this->dbConnector->findById($model, $id);
        if (!$row) return null;
        
        $model->populateFromRow($row);
        return $model;
    }
    
    // === STATIC BRIDGE METHODS (Backward Compatibility) ===
    /**
     * @deprecated Use instance method via dependency injection instead
     */
    public static function new(string $modelName): ModelBase {
        return ServiceLocator::getModelFactory()->new($modelName);
    }
    
    /**
     * @deprecated Use instance method via dependency injection instead
     */
    public static function retrieve(string $modelName, string $id): ?ModelBase {
        return ServiceLocator::getModelFactory()->retrieve($modelName, $id);
    }
}
```

**Usage Patterns**:

```php
// NEW: Proper DI (recommended for all new code)
class ModelBaseAPIController {
    public function __construct(private ModelFactory $models) {}
    
    public function create(Request $request): array {
        $modelName = $request->get('modelName');
        $data = $request->getJsonBody();
        
        $model = $this->models->new($modelName);  // Clean instance method
        $model->populateFromArray($data);
        $model->create();
        return $model->toArray();
    }
    
    public function retrieve(Request $request): ?array {
        $modelName = $request->get('modelName');
        $id = $request->get('id');
        
        $model = $this->models->retrieve($modelName, $id);  // Instance method
        return $model?->toArray();
    }
}

// LEGACY: Existing code continues to work unchanged
$user = ModelFactory::new('Users');           // Still works via bridge
$existing = ModelFactory::retrieve('Users', '123');  // Still works via bridge
```

#### 4.4 Enhanced Factory Methods for Common Patterns

```php
class ModelFactory {
    // Existing methods...
    
    // Create and save in one call
    public function create(string $modelName, array $data): ModelBase {
        $model = $this->createNew($modelName, $data);
        $model->create();
        return $model;
    }
    
    // Update existing record
    public function update(string $modelName, string $id, array $data): ?ModelBase {
        $model = $this->retrieve($modelName, $id);
        if (!$model) return null;
        
        $model->populateFromArray($data);
        $model->update();
        return $model;
    }
    
    // Find or create pattern
    public function findOrCreate(string $modelName, array $criteria, array $defaults = []): ModelBase {
        // Try to find existing record
        $existing = $this->findWhere($modelName, $criteria);
        if ($existing) return $existing;
        
        // Create new record with criteria + defaults
        return $this->create($modelName, array_merge($criteria, $defaults));
    }
    
    // Batch operations
    public function createMany(string $modelName, array $records): array {
        return array_map(fn($data) => $this->create($modelName, $data), $records);
    }
}
```

#### 4.5 Testing Benefits of Instance-Based Factory

```php
// BEFORE: Hard to test static methods
class SomeServiceTest extends TestCase {
    public function testSomeMethod() {
        // Can't easily mock ModelFactory::new() calls
        // Need complex ServiceLocator mocking
    }
}

// AFTER: Easy constructor injection
class SomeServiceTest extends TestCase {
    public function testSomeMethod() {
        $mockFactory = $this->createMock(ModelFactory::class);
        $mockUser = $this->createMock(ModelBase::class);
        
        $mockFactory->method('new')
            ->with('Users')
            ->willReturn($mockUser);
            
        $service = new SomeService($mockFactory);
        // Test with clean mock injection
    }
}
```

#### 4.6 Migration Strategy - Phased Static Method Removal

**Phase 4a: Add Instance Methods (Week 4, Day 1-2)**
- Add instance-based methods to ModelFactory
- Add ServiceLocator::getModelFactory() method
- Configure DI container registration
- All existing static method calls continue working unchanged

**Phase 4b: Update Controllers to Use Instance Methods (Week 4, Day 3-5)**
- Update ModelBaseAPIController constructor to inject ModelFactory
- Replace static calls with `$this->models->new()` and `$this->models->retrieve()`
- Update other API controllers similarly
- Test that both old and new patterns work

**Phase 4c: Update Service Classes (Week 5, Day 1-3)**
- Update all service classes to use dependency injection
- Replace static calls with instance methods
- Add deprecation warnings to static methods

**Phase 4d: Update Relationship Classes (Week 5, Day 4-5)**
- Update OneToOneRelationship, OneToManyRelationship
- Replace ModelFactory static calls with injected instance

**Phase 4e: Update Setup Scripts and Tests (Week 5, Day 1-2)**
- Update setup.php to use dependency injection
- Update test files to use constructor injection
- Verify no static calls remain

**Phase 4f: Remove Static Methods (Week 6, Day 4)**
- Remove static bridge methods from ModelFactory
- Remove deprecated ServiceLocator methods
- Final verification that all code uses proper DI

**Migration Verification Commands**:
```bash
# Find remaining static calls
grep -r "ModelFactory::" src/ --exclude-dir=vendor

# Verify instance method usage
grep -r "\$.*->new(" src/ | grep -v "ModelFactory"
grep -r "\$.*->retrieve(" src/ | grep -v "ModelFactory"
```

#### 4.7 ServiceLocator Integration for ModelFactory

**Add to ServiceLocator class**:
```php
/**
 * Get the ModelFactory service
 */
public static function getModelFactory(): ModelFactory {
    return self::getContainer()->get('model_factory');
}
```

**Update ContainerConfig registration**:
```php
// In ContainerConfig::configureCoreServices()
$di->set('model_factory', $di->lazyNew('ModelFactory'));

// Configure dependencies for ModelFactory
$di->params['ModelFactory'] = [
    'container' => $di, // Pass container itself for model instantiation
    'logger' => $di->lazyGet('logger'),
    'dbConnector' => $di->lazyGet('database_connector')
];
```

#### 4.8 Update Other Factory Classes
- **FieldFactory**: Convert to instance-based with similar patterns
- **RelationshipFactory**: Apply same DI principles  
- **ValidationRuleFactory**: Constructor injection for dependencies

**Pattern for all factories**:
```php
// Instance methods as primary API
public function create*(...): SomeClass

// Static bridge methods during migration
public static function create*(...): SomeClass {
    return ServiceLocator::get*Factory()->create*(...);
}
```

#### 4.9 Critical Application Health Check (End of Phase 4)

**CRITICAL**: After factory refactoring, verify the application is still functional.

**Validation Test**:
```bash
# Test factory functionality and model creation
gravitycar_api_call get_movie_quotes  # Tests ModelFactory instance methods
gravitycar_api_call get_movies         # Tests model instantiation via factories
gravitycar_api_call health_detailed   # System health verification
```

**Failure Protocol**: If validation fails, STOP Phase 5 and fix factory issues immediately.

### Phase 5: API and Service Layer Updates (Week 5)

#### 5.1 API Controllers
- **Files**: All controllers in `src/Api/`
- **Changes**: Constructor injection for dependencies

**Update ModelBaseAPIController**:
```php
class ModelBaseAPIController extends ApiControllerBase {
    public function __construct(
        private ModelFactory $models,
        private LoggerInterface $logger,
        private AuthenticationService $auth,
        private AuthorizationService $authz
    ) {}
    
    public function create(Request $request): array {
        $modelName = $request->get('modelName');
        $data = $request->getJsonBody();
        
        $model = $this->models->new($modelName);  // Clean instance method
        $model->populateFromArray($data);
        $model->create();
        return $model->toArray();
    }
    
    public function retrieve(Request $request): ?array {
        $modelName = $request->get('modelName');
        $id = $request->get('id');
        
        $model = $this->models->retrieve($modelName, $id);  // Instance method
        return $model?->toArray();
    }
}
```

#### 5.2 Service Classes
- **Files**: All services in `src/Services/`
- **Changes**: Constructor injection for dependencies

**Update UserService**:
```php
class UserService {
    public function __construct(
        private ModelFactory $models,
        private LoggerInterface $logger,
        private DatabaseConnectorInterface $dbConnector,
        private AuthenticationService $auth
    ) {}
    
    public function createUser(array $userData): ModelBase {
        $user = $this->models->new('Users');  // Instance method
        $user->populateFromArray($userData);
        $user->create();
        return $user;
    }
    
    public function getUserById(string $userId): ?ModelBase {
        return $this->models->retrieve('Users', $userId);  // Instance method
    }
}
```

#### 5.3 Update Relationship Classes

**Update OneToOneRelationship, OneToManyRelationship**:
```php
class OneToOneRelationship extends RelationshipBase {
    public function __construct(
        private ModelFactory $models,
        LoggerInterface $logger,
        MetadataEngineInterface $metadataEngine,
        ?string $relationshipName = null
    ) {
        parent::__construct($logger, $metadataEngine, $relationshipName);
    }
    
    protected function loadRelatedRecord(string $modelName, string $id): ?ModelBase {
        return $this->models->retrieve($modelName, $id);  // Instance method
    }
}
```

#### 5.4 Update Setup Scripts and Utilities

**Update setup.php**:
```php
// Replace ModelFactory static calls with container usage
$container = ServiceLocator::getContainer();
$models = $container->get('model_factory');

// Old: $user = ModelFactory::new('Users');
// New: $user = $models->new('Users');
$user = $models->new('Users');
$user->set('username', 'admin');
$user->create();
```

### Critical Application Health Check (End of Week 5)

#### 5.5 API Validation Checkpoint

**CRITICAL**: Before proceeding to Phase 6 (test rewrite), verify the application is still functional.

**Validation Test**:
```bash
# Use gravitycar_api_call tool to test core functionality
# This tests the entire stack: DI container → ModelFactory → API endpoints → database
gravitycar_api_call get_movies
```

**Success Criteria**:
- API returns valid JSON response
- No fatal errors or exceptions
- Movie quotes can be retrieved successfully
- Database connectivity is maintained

**Failure Protocol**:
If the API validation fails:

1. **STOP** implementation of Phase 6 immediately
2. **Investigate** the root cause of API failure:
   - Check error logs: `logs/gravitycar-*.log`
   - Verify DI container configuration
   - Test ModelFactory instance methods
   - Validate database connectivity
3. **Fix** any breaking changes introduced in Phases 1-5
4. **Revise** this implementation plan based on findings
5. **Re-test** API functionality before proceeding

**Validation Commands**:
```bash
# Test multiple endpoints to ensure comprehensive validation
gravitycar_api_call get_movies        # Test movie retrieval
gravitycar_api_call get_movie_quotes  # Test movie quotes (core functionality)
gravitycar_api_call health_detailed   # Test overall system health

# If any command fails, investigate immediately
```

**Documentation of Issues**:
- Record any failures in implementation log
- Update plan timeline if fixes require significant time
- Document any architectural adjustments needed

**Only proceed to Phase 6 if ALL validation tests pass.**

## Health Check Protocol for All Phases

### Standard Health Check Process

**After completing each phase (1-5), perform the following validation**:

```bash
# Standard validation suite for each phase
gravitycar_api_call get_movie_quotes  # Core functionality test
gravitycar_api_call get_movies         # Model system test  
gravitycar_api_call health_detailed   # Overall system health
```

### Universal Failure Protocol

**If ANY health check fails at ANY phase**:

1. **🛑 IMMEDIATE STOP**: Halt progress on next phase
2. **🔍 ROOT CAUSE ANALYSIS**:
   - Review error logs: `logs/gravitycar-*.log`
   - Test individual components that were changed in current phase
   - Verify DI container configuration
   - Check database connectivity
3. **🔧 FIX ISSUES**: Address all breaking changes before proceeding
4. **📝 DOCUMENT**: Record issues and solutions in implementation log
5. **✅ REVALIDATE**: Re-run health checks until ALL pass
6. **🔄 PLAN REVISION**: Update timeline/approach if significant changes needed

### Benefits of Frequent Health Checks

- **Early Detection**: Catch issues immediately after introduction
- **Isolated Debugging**: Know exactly which phase introduced the problem
- **Reduced Risk**: Prevent compound issues from accumulating
- **Faster Resolution**: Fix problems while context is fresh
- **Confidence**: Proceed to next phase knowing foundation is solid

### Phase 6: Testing Framework Complete Rewrite (Week 6)

#### 6.1 Complete Unit Test Rewrite Strategy

**Rationale**: Given the fundamental architectural changes from ServiceLocator to proper DI, existing tests are built around anti-patterns and would require more effort to rehabilitate than to rewrite cleanly.

**Approach**: Fresh start with proper DI testing patterns.

#### 6.2 New Test Base Classes
- **File**: `Tests/Unit/UnitTestCase.php` (complete rewrite)
- **Changes**: 
```php
abstract class UnitTestCase extends PHPUnitTestCase {
    /**
     * Create a clean container for testing with mocked services
     */
    protected function createTestContainer(): Container {
        $builder = new ContainerBuilder();
        $di = $builder->newInstance(ContainerBuilder::AUTO_RESOLVE);
        
        // Configure test-specific services
        $di->set('logger', $this->createMock(LoggerInterface::class));
        $di->set('config', $this->createMock(Config::class));
        $di->set('database_connector', $this->createMock(DatabaseConnectorInterface::class));
        $di->set('metadata_engine', $this->createMock(MetadataEngineInterface::class));
        
        return $di;
    }
    
    /**
     * Create instance with clean DI injection
     */
    protected function createInstanceWithMocks(string $className, array $mockOverrides = []): object {
        $container = $this->createTestContainer();
        
        // Override specific mocks if provided
        foreach ($mockOverrides as $service => $mock) {
            $container->set($service, $mock);
        }
        
        return $container->get($className);
    }
}
```

#### 6.3 Rewrite All Unit Tests - Clean Slate Approach

**6.3a: ModelBase Tests (Complete Rewrite)**
- **File**: `Tests/Unit/Models/ModelBaseTest.php` (delete and recreate)
- **New Pattern**:
```php
class ModelBaseTest extends UnitTestCase {
    public function testConstruction() {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        
        $mockMetadataEngine->method('getModelMetadata')
            ->willReturn(['fields' => ['id' => ['type' => 'ID']]]);
        
        $model = new ConcreteModelForTesting($mockLogger, $mockMetadataEngine);
        
        $this->assertInstanceOf(ModelBase::class, $model);
        // Clean, simple assertions
    }
    
    public function testFieldInitialization() {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        
        $metadata = [
            'fields' => [
                'name' => ['type' => 'Text', 'required' => true],
                'email' => ['type' => 'Email', 'required' => false]
            ]
        ];
        
        $mockMetadataEngine->method('getModelMetadata')->willReturn($metadata);
        
        $model = new ConcreteModelForTesting($mockLogger, $mockMetadataEngine);
        
        $this->assertTrue($model->hasField('name'));
        $this->assertTrue($model->hasField('email'));
    }
}

// Simple concrete class for testing (no complex mocking)
class ConcreteModelForTesting extends ModelBase {
    protected function getModelName(): string {
        return 'TestModel';
    }
}
```

**6.3b: DatabaseConnector Tests (Complete Rewrite)**
- **File**: `Tests/Unit/Database/DatabaseConnectorTest.php` (delete and recreate)
- **New Pattern**:
```php
class DatabaseConnectorTest extends UnitTestCase {
    public function testConnection() {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockConfig = $this->createMock(Config::class);
        
        $mockConfig->method('get')
            ->with('database')
            ->willReturn(['host' => 'localhost', 'dbname' => 'test']);
        
        $connector = new DatabaseConnector($mockLogger, $mockConfig);
        
        // Test actual behavior, not ServiceLocator calls
        $this->assertInstanceOf(DatabaseConnector::class, $connector);
    }
}
```

**6.3c: MetadataEngine Tests (Complete Rewrite)**
- **File**: `Tests/Unit/Metadata/MetadataEngineTest.php` (delete and recreate)
- **No more singleton testing - test actual instance behavior**

**6.3d: Factory Tests (Complete Rewrite)**
- **File**: `Tests/Unit/Factories/ModelFactoryTest.php` (delete and recreate)
- **Pattern**:
```php
class ModelFactoryTest extends UnitTestCase {
    public function testNew() {
        $mockContainer = $this->createMock(ContainerInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockDbConnector = $this->createMock(DatabaseConnectorInterface::class);
        
        $mockModel = $this->createMock(ModelBase::class);
        $mockContainer->method('get')
            ->with('Gravitycar\\Models\\users\\Users')
            ->willReturn($mockModel);
        
        $factory = new ModelFactory($mockContainer, $mockLogger, $mockDbConnector);
        
        $result = $factory->new('Users');
        
        $this->assertSame($mockModel, $result);
    }
}
```

#### 6.4 Delete Legacy Test Infrastructure

**Files to Delete Completely**:
- All `Testable*` classes (e.g., `TestableModelBase`, `TestableDatabaseConnector`)
- Complex mock setup methods in existing tests
- ServiceLocator-dependent test utilities
- Any tests that mock ServiceLocator static calls

**Examples of Files to Delete**:
```bash
rm Tests/Unit/Models/TestableModelBase.php
rm Tests/Unit/Database/TestableDatabaseConnector.php
rm Tests/Unit/Relationships/TestableRelationship.php
# Remove all Testable* classes
```

#### 6.5 New Test Utilities
- **File**: `Tests/Helpers/DITestHelper.php` (new)
- **Purpose**: Utilities for clean DI testing
```php
class DITestHelper {
    public static function createMockContainer(array $services = []): Container {
        $builder = new ContainerBuilder();
        $di = $builder->newInstance();
        
        foreach ($services as $name => $mock) {
            $di->set($name, $mock);
        }
        
        return $di;
    }
    
    public static function createMockModelFactory(): ModelFactory {
        $mockContainer = TestCase::createMock(ContainerInterface::class);
        $mockLogger = TestCase::createMock(LoggerInterface::class);
        $mockDbConnector = TestCase::createMock(DatabaseConnectorInterface::class);
        
        return new ModelFactory($mockContainer, $mockLogger, $mockDbConnector);
    }
}
```

#### 6.6 Benefits of Complete Rewrite Approach

**Immediate Benefits**:
- **No Technical Debt**: Start fresh with proper patterns
- **Faster Development**: No time wasted trying to fix broken test architecture
- **Cleaner Code**: Tests become examples of proper DI usage
- **Better Coverage**: Focus on testing actual behavior, not workarounds

**Comparison**:
```php
// OLD: Complex testable subclass with 50+ lines of setup
class ModelBaseTest extends UnitTestCase {
    private TestableModelBase $model;
    private array $mockMetadataContent = [];
    
    protected function setUp(): void {
        parent::setUp();
        // 50 lines of complex mock setup
        $this->model = new TestableModelBase($this->logger);
        $this->model->setMockMetadataContent($this->sampleMetadata);
        $this->setupMockFieldFactoryForModel($this->model);
        // ... more complex setup
    }
}

// NEW: Clean DI with 5 lines of setup
class ModelBaseTest extends UnitTestCase {
    public function testSomething() {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockMetadata = $this->createMock(MetadataEngineInterface::class);
        
        $model = new ConcreteModel($mockLogger, $mockMetadata);
        
        // Test actual behavior
        $this->assertTrue($model->someMethod());
    }
}
```

#### 6.7 Test Rewrite Timeline

**Week 6, Day 1**: Delete all existing unit tests and testable classes
**Week 6, Day 2**: Create new UnitTestCase base class and test utilities  
**Week 6, Day 3**: Rewrite ModelBase and core class tests
**Week 6, Day 4**: Rewrite Factory and service tests
**Week 6, Day 5**: Rewrite API controller tests

### Phase 6: API and Service Layer Updates (Week 6)

#### 6.1 API Controllers
- **Files**: All controllers in `src/Api/`
- **Changes**: Constructor injection for dependencies

#### 6.2 Service Classes
- **Files**: All services in `src/Services/`
- **Changes**: Constructor injection for dependencies

## Setter Injection Strategy

### When to Use Setter Injection vs Constructor Injection

**Constructor Injection (Required Dependencies)**:
- Core dependencies needed for basic object functionality
- Dependencies that never change during object lifetime
- Examples: Logger, MetadataEngine, Config

**Setter Injection (Optional/Contextual Dependencies)**:
- Optional performance enhancements (caching, profiling)
- Context-specific dependencies (authentication, validation context)
- Dependencies that might be circular or complex to resolve
- Late-binding scenarios where dependency isn't available at construction

### Key Use Cases in Gravitycar

#### 1. **Optional Performance Dependencies**
```php
// MetadataEngine with optional cache
class MetadataEngine {
    private ?CacheInterface $cache = null;
    
    public function setCache(CacheInterface $cache): void {
        $this->cache = $cache;
    }
    
    public function getMetadata(string $name): array {
        if ($this->cache && $cached = $this->cache->get($name)) {
            return $cached;
        }
        // ... load metadata
    }
}

// Configuration
$di->setters['MetadataEngine']['setCache'] = $di->lazyGet('cache_service');
```

#### 2. **Context Injection for Field Validation**
```php
// Field classes can receive validation context after construction
class RelatedRecordField extends FieldBase {
    private ?ValidationContext $validationContext = null;
    
    public function setValidationContext(ValidationContext $context): void {
        $this->validationContext = $context;
    }
    
    public function validate($value): bool {
        // Use context for enhanced validation if available
        if ($this->validationContext) {
            return $this->validationContext->validateRelatedRecord($value);
        }
        // Fallback to basic validation
        return $this->basicValidation($value);
    }
}
```

#### 3. **Circular Dependency Resolution**
```php
// Some services might have circular references that setter injection can resolve
class UserService {
    private ?AuthorizationService $authService = null;
    
    public function setAuthorizationService(AuthorizationService $auth): void {
        $this->authService = $auth;
    }
}

class AuthorizationService {
    private ?UserService $userService = null;
    
    public function setUserService(UserService $users): void {
        $this->userService = $users;
    }
}
```

#### 4. **Development/Debug Enhancements**
```php
// Optional profiling for DatabaseConnector
class DatabaseConnector {
    private ?QueryProfiler $profiler = null;
    
    public function setProfiler(QueryProfiler $profiler): void {
        $this->profiler = $profiler;
    }
    
    public function executeQuery(string $sql): Result {
        $this->profiler?->startQuery($sql);
        $result = $this->connection->execute($sql);
        $this->profiler?->endQuery();
        return $result;
    }
}

// Only inject profiler in development
if ($config->get('app.debug')) {
    $di->setters['DatabaseConnector']['setProfiler'] = $di->lazyGet('query_profiler');
}
```

### Testing Benefits of Setter Injection

**Simplified Optional Dependency Testing**:
```php
class MetadataEngineTest extends TestCase {
    public function testWithoutCache() {
        $engine = new MetadataEngine($mockLogger);
        // Test basic functionality without cache
        $result = $engine->getMetadata('Users');
        $this->assertIsArray($result);
    }
    
    public function testWithCache() {
        $engine = new MetadataEngine($mockLogger);
        $mockCache = $this->createMock(CacheInterface::class);
        $mockCache->method('get')->willReturn(['cached' => 'data']);
        
        $engine->setCache($mockCache); // Easy injection
        $result = $engine->getMetadata('Users');
        $this->assertEquals(['cached' => 'data'], $result);
    }
}
```

## Implementation Strategy

### Backward Compatibility
1. **Gradual Migration**: Keep ServiceLocator methods as deprecated wrappers initially
2. **Interface Compliance**: Ensure all changes maintain existing public API contracts
3. **Test Coverage**: Maintain or improve test coverage throughout refactoring

### Risk Mitigation
1. **Feature Branch**: Perform all changes in dedicated branch
2. **Incremental Testing**: Run full test suite after each phase
3. **API Health Checks**: Validate application functionality at critical checkpoints
4. **Rollback Plan**: Each phase should be independently reversible
5. **Failure Protocol**: Stop and fix issues immediately if API validation fails

### Critical Success Dependencies
1. **API Functionality**: Application must remain functional throughout migration
2. **Database Connectivity**: All database operations must continue working
3. **DI Container Stability**: Container configuration must not break existing services
4. **Backward Compatibility**: Static method bridges must work during transition period

### Success Metrics
1. **Test Simplification**: Reduce average unit test setup from 50+ lines to <5 lines via complete rewrite
2. **Code Reduction**: Eliminate all testable subclasses (~15 files) and complex mock infrastructure  
3. **Test Quality**: New tests become examples of proper DI usage patterns
4. **Coverage Improvement**: Focus on testing actual behavior rather than ServiceLocator workarounds
5. **Performance**: Container creation should be faster due to proper lazy loading
6. **Maintainability**: New features should require minimal DI configuration

### **Benefits of Complete Test Rewrite**

**Why Rewrite Instead of Rehabilitate**:
- **Technical Debt Elimination**: Start fresh without carrying forward anti-patterns
- **Development Speed**: Faster than trying to fix fundamentally broken test architecture
- **Quality Assurance**: New tests demonstrate proper DI patterns for future developers
- **Maintainability**: Simpler, cleaner tests are easier to understand and modify

**Before vs After Comparison**:
```php
// BEFORE: Complex test with elaborate workarounds
class ModelBaseTest extends UnitTestCase {
    private TestableModelBase $model;
    
    protected function setUp(): void {
        // 50+ lines of ServiceLocator mocking and testable subclass setup
        $this->model = new TestableModelBase($this->logger);
        $this->model->setMockMetadataContent($this->sampleMetadata);
        $this->setupMockFieldFactoryForModel($this->model);
        // ... complex infrastructure setup
    }
    
    public function testSomething() {
        // Test the testable subclass, not actual ModelBase
        $this->model->testInitializeModel();
        // ... indirect testing through test infrastructure
    }
}

// AFTER: Clean DI test  
class ModelBaseTest extends UnitTestCase {
    public function testSomething() {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockMetadata = $this->createMock(MetadataEngineInterface::class);
        
        $model = new ConcreteTestModel($mockLogger, $mockMetadata);
        
        // Test actual ModelBase behavior directly
        $result = $model->someMethod();
        $this->assertTrue($result);
    }
}
```

### **Implementation Strategy: ServiceLocator Bridge Pattern**

**Chosen Approach**: Instance-based factory with ServiceLocator bridge for backward compatibility.

**Key Benefits**:
- **Zero Breaking Changes**: All existing code continues to work unchanged
- **Clean New API**: New code uses proper DI with `$this->models->new('Users')` pattern
- **Gradual Migration**: Can migrate classes one by one without rush
- **Easy Testing**: Instance methods allow clean mocking
- **Future-Proof**: Foundation for eliminating ServiceLocator entirely

**Example Migration**:
```php
// BEFORE: Static calls (still works during transition)
$user = ModelFactory::new('Users');
$existing = ModelFactory::retrieve('Users', '123');

// AFTER: Instance methods via DI (new recommended pattern)
class MyController {
    public function __construct(private ModelFactory $models) {}
    
    public function action() {
        $user = $this->models->new('Users');           // Clean and mockable
        $existing = $this->models->retrieve('Users', '123');  // Clean and mockable
    }
}
```

**Migration Process**:
1. **Phase 4a**: Add instance methods alongside static bridges
2. **Phase 4b-4e**: Gradually update classes to use constructor injection  
3. **Critical Checkpoint**: API validation using `gravitycar_api_call get_movie_quotes`
4. **Phase 4f**: Remove static methods once all usages are migrated and API validation passes
5. **Verification**: Use grep commands to ensure complete migration

**If API validation fails at any point**:
- Immediately stop the migration process
- Investigate and fix the root cause  
- Revise implementation plan as needed
- Only proceed once API functionality is restored

This approach ensures we can migrate at a comfortable pace while maintaining all existing functionality and **verifying the application works at critical checkpoints**.

## Expected Outcomes

### Immediate Benefits
- **Simpler Tests**: Direct mock injection instead of complex test subclasses
- **Better Isolation**: True unit testing without ServiceLocator coupling  
- **Clearer Dependencies**: Constructor injection makes dependencies explicit

### Long-term Benefits
- **Easier Mocking**: Any dependency can be easily mocked for testing
- **Better Performance**: Proper lazy loading reduces unnecessary instantiation
- **Framework Compliance**: Following Aura DI best practices enables future framework features
- **Reduced Complexity**: Less boilerplate code for dependency management

## Conclusion

Our current Aura DI usage is fundamentally at odds with the framework's design principles. By refactoring to use proper constructor injection and completely rewriting our test suite, we'll achieve:

**Immediate Benefits**:
- **Dramatically Simpler Tests**: From 50+ line complex setups to 5-line clean DI tests
- **Better Architecture**: Proper dependency injection following Aura DI best practices  
- **No Technical Debt**: Fresh test infrastructure built on correct patterns

**Long-term Benefits**:
- **Easier Development**: New features follow established DI patterns
- **Better Performance**: Proper lazy loading and container management
- **Maintainable Codebase**: Tests serve as examples of proper DI usage
- **Framework Compliance**: Enables future Aura DI features and optimizations

**Complete Test Rewrite Justification**:
The existing test infrastructure is so deeply coupled to the ServiceLocator anti-pattern that rehabilitation would take longer than a clean rewrite. Starting fresh allows us to:
1. Eliminate all technical debt from improper DI usage
2. Create tests that demonstrate proper patterns for future developers  
3. Focus on testing actual behavior rather than ServiceLocator workarounds
4. Build a foundation for long-term maintainability

The effort required is substantial but manageable when approached systematically. The testing benefits alone justify the investment, as they will accelerate future development and reduce debugging time significantly.
