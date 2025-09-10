# Pure Dependency Injection Migration Guidelines

## Overview

This document provides comprehensive guidelines for migrating Gravitycar Framework classes from ServiceLocator-based patterns to pure dependency injection. These guidelines are based on the successful ModelBase refactor and the lessons learned during that migration.

## Core Principles

### 1. Pure Dependency Injection Definition
- **All dependencies must be explicitly injected via constructor**
- **No ServiceLocator fallbacks or hidden dependencies**
- **Container manages all object creation and dependency resolution**
- **Dependencies are immutable after construction**

### 2. Container-First Architecture
- Use `ContainerConfig::getContainer()` for accessing the DI container
- Prefer explicit container usage over ServiceLocator wrapper methods
- All object creation should go through the container when possible
- Services should be registered in the container configuration

## Migration Planning Framework

### Phase 1: Assessment and Preparation (Planning Phase)

#### 1.1 Dependency Analysis
```bash
# Identify ServiceLocator usage
grep -r "ServiceLocator::" src/
grep -r "new ClassName()" src/  # Direct instantiation patterns
grep -r "ClassName::create" src/  # Static factory patterns
```

**Create a dependency map:**
1. **Direct Dependencies**: Classes the target class needs in its constructor
2. **Transitive Dependencies**: Dependencies of dependencies
3. **Circular Dependencies**: Identify and plan to break with interfaces
4. **Optional Dependencies**: Services that may not always be available

#### 1.2 Impact Assessment
**Breaking Change Analysis:**
- How many classes extend/use the target class?
- Are there existing constructor signatures that will break?
- What test infrastructure changes are needed?
- Are there API/interface changes required?

**Example Assessment Template:**
```markdown
## Target Class: MyService
- **Current Constructor**: `public function __construct()`
- **Required Dependencies**: Logger, DatabaseConnector, OtherService
- **Subclasses**: 3 classes extend this
- **Usage Points**: 15 files instantiate this class
- **Test Impact**: 8 test files need updates
- **Breaking Change Level**: HIGH (constructor signature change)
```

#### 1.3 Migration Strategy Selection

**Strategy A: Big Bang Migration** (Used for ModelBase)
- ✅ **When to use**: Core infrastructure classes with clear boundaries
- ✅ **Benefits**: Clean cut, no transition period, immediate pure DI
- ❌ **Risks**: High coordination required, all-or-nothing deployment

**Strategy B: Gradual Migration**
- ✅ **When to use**: Classes with many dependents, less critical path
- ✅ **Benefits**: Lower risk, incremental validation
- ❌ **Risks**: Temporary architectural inconsistency

**Strategy C: Parallel Implementation**
- ✅ **When to use**: Classes with complex external interfaces
- ✅ **Benefits**: Allows testing before cutover
- ❌ **Risks**: Code duplication, longer timeline

### Phase 2: Container Configuration

#### 2.1 Service Registration
**Register all dependencies in ContainerConfig:**
```php
// In ContainerConfig.php
private static function configureMyServiceClasses(Container $di): void {
    // Register the service itself
    $di->set('my_service', $di->lazyNew('Gravitycar\\Services\\MyService'));
    
    // Configure its dependencies
    $di->params['Gravitycar\\Services\\MyService'] = [
        'logger' => $di->lazyGet('logger'),
        'databaseConnector' => $di->lazyGet('database_connector'),
        'otherService' => $di->lazyGet('other_service')
    ];
}
```

#### 2.2 Interface-Based Dependencies
**Define interfaces for major dependencies:**
```php
interface MyServiceInterface {
    public function performAction(string $data): Result;
}

class MyService implements MyServiceInterface {
    public function __construct(
        private Logger $logger,
        private DatabaseConnectorInterface $databaseConnector,
        private OtherServiceInterface $otherService
    ) {}
}
```

#### 2.3 Factory Method Creation
**Add container-based factory method:**
```php
// In ContainerConfig.php
public static function createMyService(): MyServiceInterface {
    if (!class_exists('Gravitycar\\Services\\MyService')) {
        throw new GCException("MyService class does not exist");
    }
    
    $di = self::getContainer();
    return $di->newInstance('Gravitycar\\Services\\MyService');
}
```

### Phase 3: Core Class Refactoring

#### 3.1 Constructor Design Pattern
**Standard constructor template:**
```php
class MyService implements MyServiceInterface {
    public function __construct(
        private Logger $logger,
        private DatabaseConnectorInterface $databaseConnector,
        private OtherServiceInterface $otherService
    ) {
        // All dependencies explicitly injected
        // No ServiceLocator calls
        // Initialize immediately - all dependencies available
        $this->validateDependencies();
        $this->initialize();
    }
    
    private function validateDependencies(): void {
        // Optional: Validate dependencies are properly configured
        if (!$this->databaseConnector instanceof DatabaseConnectorInterface) {
            throw new GCException("Invalid database connector dependency");
        }
    }
}
```

#### 3.2 Dependency Access Pattern
**Direct property access (no getters):**
```php
// ✅ CORRECT: Direct property access
public function performDatabaseOperation(): array {
    return $this->databaseConnector->find('table', $criteria);
}

// ❌ INCORRECT: Getter methods (unnecessary indirection)
public function performDatabaseOperation(): array {
    return $this->getDatabaseConnector()->find('table', $criteria);
}

private function getDatabaseConnector(): DatabaseConnectorInterface {
    return $this->databaseConnector; // Unnecessary method
}
```

#### 3.3 ServiceLocator Elimination
**Remove all ServiceLocator usage:**
```php
// ❌ BEFORE: ServiceLocator pattern
class MyService {
    public function performAction(): void {
        $logger = ServiceLocator::getLogger(); // Remove this
        $db = ServiceLocator::getDatabaseConnector(); // Remove this
        $other = ServiceLocator::getOtherService(); // Remove this
    }
}

// ✅ AFTER: Pure dependency injection
class MyService {
    public function __construct(
        private Logger $logger,
        private DatabaseConnectorInterface $databaseConnector,
        private OtherServiceInterface $otherService
    ) {}
    
    public function performAction(): void {
        $this->logger->info('Performing action');
        $result = $this->databaseConnector->find('table', []);
        $this->otherService->process($result);
    }
}
```

### Phase 4: Subclass and Dependent Updates

#### 4.1 Subclass Constructor Pattern
**Update all subclasses:**
```php
// Parent class
abstract class BaseService {
    public function __construct(
        protected Logger $logger,
        protected DatabaseConnectorInterface $databaseConnector
    ) {}
}

// Child class - MUST call parent constructor
class ConcreteService extends BaseService {
    public function __construct(
        Logger $logger,
        DatabaseConnectorInterface $databaseConnector,
        private SpecificServiceInterface $specificService
    ) {
        parent::__construct($logger, $databaseConnector);
        // Child-specific initialization
    }
}
```

#### 4.2 Usage Pattern Updates
**Update all instantiation points:**
```php
// ❌ BEFORE: Direct instantiation
$service = new MyService(); // Will fail - requires dependencies

// ❌ BEFORE: Static factory
$service = MyService::create(); // Remove static factories

// ✅ AFTER: Container-based creation
$container = ContainerConfig::getContainer();
$service = $container->get('my_service');

// ✅ AFTER: Factory method (if available)
$service = ContainerConfig::createMyService();
```

### Phase 5: Test Infrastructure Migration

#### 5.1 Test Setup Simplification
**Direct dependency injection in tests:**
```php
class MyServiceTest extends PHPUnit\Framework\TestCase {
    private MyService $service;
    private Logger $mockLogger;
    private DatabaseConnectorInterface $mockDatabase;
    
    protected function setUp(): void {
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockDatabase = $this->createMock(DatabaseConnectorInterface::class);
        $mockOtherService = $this->createMock(OtherServiceInterface::class);
        
        // Direct injection - no complex setup needed
        $this->service = new MyService(
            $this->mockLogger,
            $this->mockDatabase,
            $mockOtherService
        );
    }
    
    public function testPerformAction(): void {
        // Configure mocks
        $this->mockDatabase->method('find')->willReturn([]);
        
        // Test the actual method
        $result = $this->service->performAction();
        
        // Verify behavior
        $this->assertInstanceOf(Result::class, $result);
    }
}
```

#### 5.2 TestableClass Pattern (Optional)
**For complex internal method testing:**
```php
class TestableMyService extends MyService {
    // Expose protected methods for testing
    public function testValidateInput(array $input): bool {
        return $this->validateInput($input);
    }
    
    public function testProcessData(array $data): array {
        return $this->processData($data);
    }
}
```

### Phase 6: Documentation and Validation

#### 6.1 Migration Validation Script
**Create automated validation:**
```php
#!/usr/bin/env php
<?php
// tmp/validate_pure_di_migration.php

function validateClassPureDI(string $className): array {
    $reflection = new ReflectionClass($className);
    $constructor = $reflection->getConstructor();
    $errors = [];
    
    if (!$constructor) {
        $errors[] = "No constructor found";
        return $errors;
    }
    
    // Check for required dependencies
    $params = $constructor->getParameters();
    if (count($params) === 0) {
        $errors[] = "Constructor has no parameters (likely missing DI)";
    }
    
    // Check for ServiceLocator usage
    $classFile = file_get_contents($reflection->getFileName());
    if (strpos($classFile, 'ServiceLocator::') !== false) {
        $errors[] = "Still contains ServiceLocator usage";
    }
    
    return $errors;
}

// Run validation
$classes = ['MyService', 'OtherService']; // Add target classes
foreach ($classes as $className) {
    $errors = validateClassPureDI($className);
    if (empty($errors)) {
        echo "✅ {$className}: Pure DI validation passed\n";
    } else {
        echo "❌ {$className}: " . implode(", ", $errors) . "\n";
    }
}
```

#### 6.2 Performance Benchmarking
**Compare before/after performance:**
```php
// Benchmark script
$start = microtime(true);

// Before: ServiceLocator pattern
for ($i = 0; $i < 1000; $i++) {
    $service = new MyService(); // Old pattern
}
$serviceLocatorTime = microtime(true) - $start;

$start = microtime(true);

// After: Container pattern
$container = ContainerConfig::getContainer();
for ($i = 0; $i < 1000; $i++) {
    $service = $container->get('my_service');
}
$containerTime = microtime(true) - $start;

echo "ServiceLocator: {$serviceLocatorTime}s\n";
echo "Container: {$containerTime}s\n";
echo "Improvement: " . (($serviceLocatorTime - $containerTime) / $serviceLocatorTime * 100) . "%\n";
```

## Lessons Learned from ModelBase Migration

### 1. Big Bang Migration Success Factors
- **Complete dependency analysis upfront**
- **All-at-once constructor signature changes**
- **Comprehensive test updates**
- **Container configuration before implementation**
- **Validation script for verification**

### 2. Critical Success Patterns

#### Container Configuration is Key
```php
// ✅ Complete dependency configuration required
$di->params['Target\\Class'] = [
    'logger' => $di->lazyGet('logger'),
    'dependency1' => $di->lazyGet('dependency1'),
    'dependency2' => $di->lazyNew('Dependency2\\Class'),
    // ALL dependencies must be configured
];
```

#### Factory Delegation Pattern
```php
// ✅ Factories should delegate to container
class MyFactory {
    public function create(string $type): MyInterface {
        $className = "App\\Services\\{$type}";
        return ContainerConfig::getContainer()->newInstance($className);
    }
}
```

#### Transitional Infrastructure
```php
// ✅ Create temporary helpers for complex migrations
class MigrationHelper {
    public static function createServiceWithDependencies(): MyService {
        // Temporary bridge during migration
        // Remove in final cleanup phase
    }
}
```

### 3. Common Pitfalls to Avoid

#### Incomplete Container Configuration
```php
// ❌ Partial configuration causes runtime errors
$di->params['MyClass'] = [
    'logger' => $di->lazyGet('logger'),
    // Missing other required dependencies
];
```

#### Mixing Patterns
```php
// ❌ Don't mix ServiceLocator and pure DI
class MyService {
    public function __construct(Logger $logger) {
        $this->logger = $logger; // ✅ Pure DI
        $this->database = ServiceLocator::getDatabase(); // ❌ Mixed pattern
    }
}
```

#### Test Infrastructure Neglect
```php
// ❌ Forgetting to update test setup
class MyServiceTest {
    public function setUp(): void {
        $this->service = new MyService(); // Will fail after migration
    }
}
```

## Migration Checklist Template

### Pre-Migration
- [ ] Complete dependency analysis
- [ ] Impact assessment (subclasses, usage points)
- [ ] Container configuration designed
- [ ] Migration strategy selected
- [ ] Rollback plan created

### Implementation
- [ ] Container services registered
- [ ] Interface definitions complete
- [ ] Constructor refactored with all dependencies
- [ ] ServiceLocator usage eliminated
- [ ] Subclasses updated
- [ ] Factory patterns updated
- [ ] Usage points updated

### Testing
- [ ] Test infrastructure migrated
- [ ] Unit tests updated and passing
- [ ] Integration tests validated
- [ ] Performance benchmarking complete
- [ ] Migration validation script passing

### Documentation
- [ ] Architecture documentation updated
- [ ] Migration guide created
- [ ] Code patterns documented
- [ ] AI instruction updates (if applicable)

### Cleanup
- [ ] Temporary migration infrastructure removed
- [ ] Deprecated methods removed
- [ ] Legacy patterns eliminated
- [ ] Final validation complete

## Architectural Benefits Achieved

### Code Quality Improvements
- **Explicit Dependencies**: All dependencies visible in constructor
- **Testability**: Direct mock injection, no complex setup
- **Immutability**: Dependencies set once at construction
- **Container Management**: Centralized object creation

### Performance Benefits
- **Reduced Overhead**: No ServiceLocator lookup costs
- **Memory Efficiency**: Clear object lifecycle management
- **Faster Tests**: Simplified test setup and execution

### Maintainability Benefits
- **Clear Relationships**: Dependency graph explicit
- **Reduced Coupling**: No hidden service dependencies
- **Better Debugging**: Stack traces show real dependency flow
- **Easier Refactoring**: Dependencies explicit and typed

## Conclusion

Pure dependency injection migration requires careful planning but delivers significant architectural benefits. The key to success is thorough preparation, complete container configuration, and comprehensive testing. The ModelBase migration demonstrates that even complex, foundational classes can be successfully migrated with the right approach and attention to detail.

Use this guide as a template for migrating other classes in the Gravitycar Framework to pure dependency injection patterns.

---
*Generated from lessons learned during the ModelBase Pure DI Migration*  
*Framework: Gravitycar - December 10, 2025*
