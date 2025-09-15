# Unit Test Refactoring Plan - Developer C: ModelFactory & Models

## Overview
This plan focuses on fixing unit tests for ModelFactory (static to instance method conversion) and model-related tests that have dependency injection issues.

## Assigned Test Categories

### 1. ModelFactory Tests (High Priority)
**Files to Modify:** `Tests/Unit/Factories/ModelFactoryTest.php`
**Issue:** ModelFactory methods are now instance methods instead of static methods
**Failing Tests:** 10 tests (tests 74-83 from error output)

#### ModelFactory Method Changes
```php
// OLD (what tests expect):
ModelFactory::new('Users')
ModelFactory::retrieve('Users', $id)
ModelFactory::getAvailableModels()
ModelFactory::resolveModelClass($modelName)
ModelFactory::validateModelClass($className)

// NEW (current implementation):
$factory = new ModelFactory($container, $logger, $dbConnector, $metadataEngine);
$factory->new('Users')
$factory->retrieve('Users', $id)
$factory->getAvailableModels()
$factory->resolveModelClass($modelName)
$factory->validateModelClass($className)
```

#### Fix Strategy
1. Update test setUp() to create ModelFactory instance with required dependencies
2. Convert all static method calls to instance method calls
3. Update reflection tests to work with instance methods instead of static methods

**Example Fix Pattern:**
```php
protected function setUp(): void {
    $this->container = $this->createMock(Container::class);
    $this->logger = $this->createMock(Logger::class);
    $this->databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    $this->metadataEngine = $this->createMock(MetadataEngineInterface::class);
    
    $this->modelFactory = new ModelFactory(
        $this->container,
        $this->logger,
        $this->databaseConnector,
        $this->metadataEngine
    );
}

// Convert static calls:
// OLD: $model = ModelFactory::new('Users');
// NEW: $model = $this->modelFactory->new('Users');

// For reflection tests:
// OLD: $reflection = new ReflectionMethod(ModelFactory::class, 'resolveModelClass');
// NEW: $reflection = new ReflectionMethod($this->modelFactory, 'resolveModelClass');
//      $result = $reflection->invoke($this->modelFactory, $modelName);
```

### 2. Relationship Model Tests (Medium Priority)
**Files to Modify:** 
- `Tests/Unit/Relationships/ManyToManyRelationshipTest.php`
- `Tests/Unit/Relationships/RelationshipBaseRemoveMethodTest.php`

**Issue:** ModelBase and RelationshipBase missing required dependencies
**Failing Tests:** 2 tests (tests 85, 95 from error output)

#### ManyToManyRelationship Test Issues
**Error:** `Typed property Gravitycar\Relationships\RelationshipBase::$metadataEngine must not be accessed before initialization`

#### Fix Strategy
1. Ensure RelationshipBase has proper dependency injection in test setup
2. Mock all required typed properties before they're accessed

**Example Fix Pattern:**
```php
protected function setUp(): void {
    $this->logger = $this->createMock(Logger::class);
    $this->metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $this->databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    
    // Create relationship with proper dependencies
    $metadata = ['name' => 'test_relationship', 'type' => 'ManyToMany'];
    $this->relationship = new ManyToManyRelationship($metadata, $this->logger);
    
    // Inject dependencies via reflection or setter methods
    $this->injectDependency($this->relationship, 'metadataEngine', $this->metadataEngine);
    $this->injectDependency($this->relationship, 'databaseConnector', $this->databaseConnector);
}

private function injectDependency($object, $propertyName, $value): void {
    $reflection = new ReflectionClass($object);
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);
    $property->setValue($object, $value);
}
```

#### RelationshipBaseRemoveMethod Test Issues
**Error:** `Typed property Gravitycar\Models\ModelBase::$fieldFactory must not be accessed before initialization`

#### Fix Strategy
1. Create ModelBase instance with all 7 required dependencies
2. Use proper dependency injection instead of incomplete instantiation

### 3. GuestUserManager Tests (Medium Priority)
**Files to Modify:** 
- `Tests/Unit/Utils/GuestUserManagerEdgeCaseTest.php`
- `Tests/Unit/Utils/GuestUserManagerIntegrationTest.php`

**Issue:** Inherits from DatabaseTestCase which has DatabaseConnector constructor issues
**Failing Tests:** 18 tests (tests 98-115 from error output)

#### Fix Strategy
1. These will be automatically fixed once Developer A fixes DatabaseTestCase.php
2. Verify tests still pass after DatabaseConnector constructor fix
3. May need to update GuestUserManager dependency injection

## Implementation Order
1. **ModelFactory Tests** - Core factory functionality, high impact
2. **ManyToManyRelationship Test** - Critical for relationship functionality  
3. **RelationshipBaseRemoveMethod Test** - Important for model relationships
4. **GuestUserManager Tests** - Wait for DatabaseTestCase fix from Developer A

## Testing Strategy
After each fix:
```bash
# Test ModelFactory specifically
vendor/bin/phpunit Tests/Unit/Factories/ModelFactoryTest.php

# Test relationship functionality
vendor/bin/phpunit Tests/Unit/Relationships/ManyToManyRelationshipTest.php
vendor/bin/phpunit Tests/Unit/Relationships/RelationshipBaseRemoveMethodTest.php

# Test utility classes
vendor/bin/phpunit Tests/Unit/Utils/

# Test all factories
vendor/bin/phpunit Tests/Unit/Factories/
```

## ModelFactory Instance Creation Patterns
For consistent test setup across all ModelFactory tests:

```php
// Standard setup pattern
protected function setUp(): void {
    $this->container = $this->createMock(Container::class);
    $this->logger = $this->createMock(Logger::class);
    $this->databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    $this->metadataEngine = $this->createMock(MetadataEngineInterface::class);
    
    // Configure container to return model instances
    $this->container->method('newInstance')->willReturnCallback(function($className) {
        return $this->createMockModel($className);
    });
    
    // Configure metadataEngine for validation
    $this->metadataEngine->method('getModelNames')->willReturn(['Users', 'Movies', 'Roles']);
    
    $this->modelFactory = new ModelFactory(
        $this->container,
        $this->logger,
        $this->databaseConnector,
        $this->metadataEngine
    );
}

private function createMockModel(string $className) {
    $mock = $this->createMock($className);
    // Configure mock as needed for specific tests
    return $mock;
}
```

## Dependency Injection Helper Methods
Create reusable methods for common injection patterns:

```php
/**
 * Inject dependencies into ModelBase instances for testing
 */
private function setupModelDependencies($model): void {
    $logger = $this->createMock(Logger::class);
    $metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $fieldFactory = $this->createMock(FieldFactory::class);
    $databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    $relationshipFactory = $this->createMock(RelationshipFactory::class);
    $modelFactory = $this->createMock(ModelFactory::class);
    $currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
    
    // Use reflection to set private properties
    $this->injectModelBaseDependencies($model, [
        'logger' => $logger,
        'metadataEngine' => $metadataEngine,
        'fieldFactory' => $fieldFactory,
        'databaseConnector' => $databaseConnector,
        'relationshipFactory' => $relationshipFactory,
        'modelFactory' => $modelFactory,
        'currentUserProvider' => $currentUserProvider
    ]);
}

private function injectModelBaseDependencies($model, array $dependencies): void {
    $reflection = new ReflectionClass($model);
    foreach ($dependencies as $propertyName => $value) {
        try {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($model, $value);
        } catch (ReflectionException $e) {
            // Property might not exist in all model classes
        }
    }
}
```

## Expected Impact
- **Fixes 30+ failing tests** (ModelFactory: 10, Relationships: 2, GuestUserManager: 18)
- **Core model creation functionality** working in tests
- **Relationship system** properly tested
- **User management utilities** functioning

## Dependencies
- **Depends on Developer A** completing DatabaseTestCase.php fix for GuestUserManager tests
- **No conflicts** with API controller work (Developer B)

## Coordination Notes
- **GuestUserManager tests** should be started after Developer A completes DatabaseTestCase fix
- **ModelFactory and Relationship tests** can be started immediately
- Share dependency injection helper methods with other developers

## Estimated Time
- ModelFactory Tests: 4-5 hours (complex static to instance conversion)
- ManyToManyRelationship Test: 2 hours (dependency injection setup)
- RelationshipBaseRemoveMethod Test: 2 hours (ModelBase dependency setup)
- GuestUserManager Tests: 1 hour (verification after DatabaseTestCase fix)
- **Total: 9-10 hours**

## Success Criteria
- All ModelFactory tests pass (10 tests)
- All relationship tests pass (2 tests)
- All GuestUserManager tests pass (18 tests)
- No static method calls to ModelFactory remain
- All ModelBase instances have proper dependency injection
- Reusable dependency injection patterns established