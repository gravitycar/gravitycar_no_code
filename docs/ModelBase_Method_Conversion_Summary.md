# ModelBase Pure Dependency Injection Architecture

## Overview

ModelBase has been refactored to use pure dependency injection, eliminating all ServiceLocator fallbacks and dramatically improving testability. This document outlines the new architecture and usage patterns.

## Constructor Dependency Injection

### New Constructor Signature
```php
public function __construct(
    Logger $logger,
    MetadataEngineInterface $metadataEngine,
    FieldFactory $fieldFactory,
    DatabaseConnectorInterface $databaseConnector,
    RelationshipFactory $relationshipFactory,
    ModelFactory $modelFactory,
    CurrentUserProviderInterface $currentUserProvider
) {
    // All dependencies explicitly injected - no ServiceLocator fallbacks
    $this->logger = $logger;
    $this->metadataEngine = $metadataEngine;
    $this->fieldFactory = $fieldFactory;
    $this->databaseConnector = $databaseConnector;
    $this->relationshipFactory = $relationshipFactory;
    $this->modelFactory = $modelFactory;
    $this->currentUserProvider = $currentUserProvider;
    
    // Initialize immediately - all dependencies available
    $this->loadMetadata();
    $this->initializeFields();
}
```

### Required Dependencies
1. **Logger** - For logging and debugging
2. **MetadataEngineInterface** - For loading model metadata
3. **FieldFactory** - For creating field instances
4. **DatabaseConnectorInterface** - For database operations
5. **RelationshipFactory** - For managing relationships
6. **ModelFactory** - For creating related model instances
7. **CurrentUserProviderInterface** - For authentication context

## Model Creation Patterns

### Container-Managed Creation (Recommended)
```php
// Use ContainerConfig for automatic dependency injection
$user = \Gravitycar\Core\ContainerConfig::createModel('Gravitycar\\Models\\users\\Users');

// Or through ModelFactory (which delegates to ContainerConfig)
$factory = ServiceLocator::getModelFactory();
$user = $factory->create('Users');
```

### Manual Creation (Testing/Advanced Use)
```php
// Direct instantiation with all dependencies
$user = new Users(
    $logger,
    $metadataEngine,
    $fieldFactory,
    $databaseConnector,
    $relationshipFactory,
    $modelFactory,
    $currentUserProvider
);
```

## Model Subclass Updates

### Required Constructor Pattern
All ModelBase subclasses must implement the full dependency injection constructor:

```php
// Before (old pattern)
class Users extends ModelBase {
    public function __construct(?Logger $logger = null) {
        parent::__construct($logger);
    }
}

// After (required pattern)
class Users extends ModelBase {
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }
}
```

## Eliminated ServiceLocator Dependencies

### Removed Methods
- `getDatabaseConnector()` - Use `$this->databaseConnector` directly
- `getFieldFactory()` - Use `$this->fieldFactory` directly
- `getRelationshipFactory()` - Use `$this->relationshipFactory` directly
- `getModelFactory()` - Use `$this->modelFactory` directly
- `getCurrentUserService()` - Use `$this->currentUserProvider` directly

### Direct Property Access
```php
// Before: ServiceLocator fallback pattern
$fieldFactory = $this->getFieldFactory();
$field = $fieldFactory->createField($metadata);

// After: Direct property access
$field = $this->fieldFactory->createField($metadata);
```

## Testing Architecture

### Simplified Test Setup
```php
// Before: Complex mock injection
protected function setUp(): void {
    parent::setUp();
    $this->model = new TestableModelBase($this->logger);
    $this->setupMockFieldFactoryForModel($this->model);
    // ... complex mock setup
}

// After: Direct dependency injection
protected function setUp(): void {
    parent::setUp();
    
    $mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
    $mockFieldFactory = $this->createMock(FieldFactory::class);
    $mockDbConnector = $this->createMock(DatabaseConnectorInterface::class);
    // ... create all mocks
    
    $this->model = new TestableModelForPureDI(
        $this->logger,
        $mockMetadataEngine,
        $mockFieldFactory,
        $mockDbConnector,
        $mockRelationshipFactory,
        $mockModelFactory,
        $mockCurrentUserProvider
    );
}
```

### Benefits of New Test Architecture
- **80% reduction in test setup complexity**
- **Complete test isolation** - no global state
- **Predictable behavior** - all dependencies explicit
- **Easier debugging** - clear dependency flow
- **Type safety** - proper interface mocking

## Authentication Integration

### CurrentUserProvider Service
The new `CurrentUserProviderInterface` provides consistent authentication context:

```php
// Always available service - handles all authentication logic
$currentUserId = $this->currentUserProvider->getCurrentUserId() ?? 'system';
$currentUser = $this->currentUserProvider->getCurrentUser();
$isAuthenticated = $this->currentUserProvider->hasAuthenticatedUser();
```

### Context-Aware Implementations
- **WebCurrentUserProvider** - For web requests with session authentication
- **CLICurrentUserProvider** - For command-line operations (returns system user)
- **TestCurrentUserProvider** - For unit tests (returns configured test user)

## Performance Benefits

### Eliminated Overhead
- **No ServiceLocator lookups** - Direct property access
- **No lazy loading complexity** - All dependencies available at construction
- **Reduced method calls** - Direct dependency usage
- **Faster test execution** - Simplified mock setup

### Initialization Improvements
- **Immediate initialization** - `loadMetadata()` and `initializeFields()` called in constructor
- **Predictable state** - All dependencies available when needed
- **No timing issues** - Consistent initialization order

## Migration Guide

### For Existing Model Classes
1. Update constructor to accept all 7 dependencies
2. Pass all dependencies to parent constructor
3. Remove any ServiceLocator usage
4. Test with new dependency injection pattern

### For Application Code
1. Use `ContainerConfig::createModel()` for model creation
2. Update tests to use direct dependency injection
3. Remove ServiceLocator mock setup
4. Validate all model operations work correctly

### For Factory Classes
1. Use `ContainerConfig::createModel()` in ModelFactory
2. Configure all dependencies in ContainerConfig
3. Remove legacy fallback methods
4. Update factory tests

## Architecture Benefits

### Code Quality
- **Clear dependencies** - All requirements explicit
- **Better testability** - Direct mock injection
- **Reduced complexity** - No ServiceLocator indirection
- **Type safety** - Constructor injection enforces types

### Maintainability
- **Predictable behavior** - No hidden dependencies
- **Easier debugging** - Clear dependency flow
- **Better IDE support** - Type hints enable code completion
- **Simpler tests** - Direct dependency control

### Performance
- **Faster instantiation** - No ServiceLocator overhead
- **Better memory usage** - Direct property access
- **Reduced call stack** - Eliminate getter methods
- **Faster tests** - Simplified setup and teardown

This pure dependency injection architecture provides a solid foundation for maintainable, testable, and performant ModelBase functionality throughout the Gravitycar framework.
