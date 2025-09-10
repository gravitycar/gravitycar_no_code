# Pure Dependency Injection ModelBase Migration Guide

## Overview

This guide provides step-by-step instructions for migrating from the ServiceLocator-based ModelBase to the new pure dependency injection architecture. This is a **breaking change** that requires updates to all ModelBase subclasses and their usage patterns.

## Quick Reference: What Changed

### Before (ServiceLocator Pattern)
```php
// Model creation
$user = new Users();  // ServiceLocator fallbacks

// Dependencies accessed via getters
$fieldFactory = $this->getFieldFactory();
$dbConnector = $this->getDatabaseConnector();

// Tests with complex mock injection
$this->setupMockFieldFactoryForModel($this->model);
```

### After (Pure Dependency Injection)
```php
// Model creation via Container and ModelFactory (recommended)
$container = ContainerConfig::getContainer();
$factory = $container->get('model_factory');
$user = $factory->new('Users');

// Alternative: Direct container access
$user = ContainerConfig::createModel('Gravitycar\\Models\\users\\Users');

// Dependencies accessed directly
$field = $this->fieldFactory->createField($metadata);
$result = $this->databaseConnector->find('users', $criteria);

// Tests with direct injection
$this->model = new TestableModelForPureDI($logger, $metadataEngine, ...);
```

## Step 1: Update Model Subclass Constructors

### Required Changes for Each Model

**Find all ModelBase subclasses:**
- `src/Models/books/Books.php`
- `src/Models/google_oauth_tokens/GoogleOauthTokens.php`
- `src/Models/installer/Installer.php`
- `src/Models/jwtrefreshtokens/JwtRefreshTokens.php`
- `src/Models/movie_quote_trivia_games/Movie_Quote_Trivia_Games.php`
- `src/Models/movie_quote_trivia_questions/Movie_Quote_Trivia_Questions.php`
- `src/Models/movie_quotes/Movie_Quotes.php`
- `src/Models/movies/Movies.php`
- `src/Models/permissions/Permissions.php`
- `src/Models/roles/Roles.php`
- `src/Models/users/Users.php`

### Constructor Update Pattern

**Before:**
```php
class Users extends ModelBase {
    public function __construct(?Logger $logger = null) {
        parent::__construct($logger);
    }
}
```

**After:**
```php
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

### Import Statements to Add

```php
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;
```

## Step 2: Update Model Creation Code

### Application Code Changes

**Before:**
```php
// Direct instantiation
$user = new Users();
$movie = new Movies();

// ServiceLocator factory usage
$factory = ServiceLocator::getModelFactory();
$user = $factory->new('Users');
```

**After:**
```php
// Container-managed ModelFactory creation (recommended)
$container = ContainerConfig::getContainer();
$factory = $container->get('model_factory');
$user = $factory->new('Users');
$movie = $factory->new('Movies');

// Alternative: Direct container access
$user = ContainerConfig::createModel('Gravitycar\\Models\\users\\Users');
$movie = ContainerConfig::createModel('Gravitycar\\Models\\movies\\Movies');
$factory = ServiceLocator::getModelFactory();
$user = $factory->create('Users');  // create() method delegates to container
```

### API Controller Updates

**Before:**
```php
public function createUser(Request $request): array {
    $user = new Users();
    $user->populateFromArray($request->getData());
    $user->save();
    return ['user' => $user->toArray()];
}
```

**After:**
```php
public function createUser(Request $request): array {
    $user = ContainerConfig::createModel('Gravitycar\\Models\\users\\Users');
    $user->populateFromArray($request->getData());
    $user->save();
    return ['user' => $user->toArray()];
}
```

## Step 3: Update Test Infrastructure

### Test Class Updates

**Before (Complex Mock Setup):**
```php
class ModelBaseTest extends PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->model = new TestableModelBase($this->logger);
        $this->setupMockFieldFactoryForModel($this->model);
        $mockDbConnector = $this->createMock(DatabaseConnector::class);
        $this->model->setMockDatabaseConnector($mockDbConnector);
        // ... 50+ lines of complex mock setup
    }
    
    private function setupMockFieldFactoryForModel($model): void {
        // 20+ lines of complex field factory mock injection
    }
}
```

**After (Direct Dependency Injection):**
```php
class ModelBasePureDITest extends PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        
        // Create all mocks explicitly
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockFieldFactory = $this->createMock(FieldFactory::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockRelationshipFactory = $this->createMock(RelationshipFactory::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
        
        // Set up default behaviors
        $this->setupMockDefaults();
        
        // Direct dependency injection
        $this->model = new TestableModelForPureDI(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
    }
}
```

### TestableModel Simplification

**Before (TestableModelBase - Complex):**
```php
class TestableModelBase extends ModelBase {
    // 100+ lines of mock management methods
    public function setMockDatabaseConnector($mock) { /* ... */ }
    public function setMockFieldFactory($mock) { /* ... */ }
    public function setupFieldFactory() { /* ... */ }
    // ... many more mock injection methods
}
```

**After (TestableModelForPureDI - Simple):**
```php
class TestableModelForPureDI extends ModelBase {
    // Simple method exposure - no mock management
    public function testValidateMetadata(array $metadata): void {
        $this->validateMetadata($metadata);
    }
    
    public function testCreateSingleField(string $name, array $meta): ?FieldBase {
        return $this->createSingleField($name, $meta, $this->fieldFactory);
    }
    
    public function testGetCurrentUserId(): ?string {
        return $this->getCurrentUserId();
    }
    
    // ... simple test helpers only
}
```

## Step 4: Update Factory Classes

### FieldFactory Updates

**Already Updated** - FieldFactory now uses constructor injection:
```php
public function __construct(Logger $logger, DatabaseConnectorInterface $databaseConnector) {
    $this->logger = $logger;
    $this->databaseConnector = $databaseConnector;
    $this->discoverFieldTypes();
}
```

### RelationshipFactory Updates

**Check if updated** - Should use constructor injection:
```php
public function __construct(
    Logger $logger,
    MetadataEngineInterface $metadataEngine,
    DatabaseConnectorInterface $databaseConnector
) {
    $this->logger = $logger;
    $this->metadataEngine = $metadataEngine;
    $this->databaseConnector = $databaseConnector;
}
```

### ModelFactory Updates

**Already Updated** - ModelFactory delegates to ContainerConfig:
```php
public function create(string $modelName): ModelBase {
    $modelClass = "Gravitycar\\Models\\{$modelName}\\{$modelName}";
    return \Gravitycar\Core\ContainerConfig::createModel($modelClass);
}
```

## Step 5: Container Configuration

### Verify ContainerConfig Setup

Check that `src/Core/ContainerConfig.php` has complete dependency configuration:

```php
private static function configureModelClasses(Container $di): void {
    // Complete dependency configuration for all ModelBase subclasses
    $di->params['Gravitycar\\Models\\ModelBase'] = [
        'logger' => $di->lazyGet('logger'),
        'metadataEngine' => $di->lazyGet('metadata_engine'),
        'fieldFactory' => $di->lazyNew('Gravitycar\\Factories\\FieldFactory'),
        'databaseConnector' => $di->lazyGet('database_connector'),
        'relationshipFactory' => $di->lazyNew('Gravitycar\\Factories\\RelationshipFactory'),
        'modelFactory' => $di->lazyGet('model_factory'),
        'currentUserProvider' => $di->lazyGet('current_user_provider')
    ];
    
    // CurrentUserProvider service configuration
    $di->set('current_user_provider', $di->lazyNew('Gravitycar\\Services\\CurrentUserProvider'));
    // ... other configurations
}
```

## Step 6: Validation and Testing

### Migration Checklist

- [ ] **All model constructors updated** with 7-parameter dependency injection
- [ ] **All direct model instantiation** replaced with container creation
- [ ] **All factory classes** updated to use container-managed dependencies
- [ ] **All tests updated** to use direct dependency injection
- [ ] **Container configuration** includes all required dependencies
- [ ] **Import statements** added for all interface dependencies
- [ ] **Legacy method calls** removed (no more `getDatabaseConnector()`, etc.)

### Testing Strategy

1. **Unit Tests**: Run all ModelBase unit tests to verify functionality
2. **Integration Tests**: Test model creation through API endpoints
3. **Feature Tests**: Verify complete user workflows work correctly
4. **Performance Tests**: Ensure no performance regressions

### Common Issues and Solutions

#### Issue: "Method does not exist" errors
**Cause**: Old code calling removed methods like `getDatabaseConnector()`
**Solution**: Replace with direct property access: `$this->databaseConnector`

#### Issue: "Too few arguments" constructor errors
**Cause**: Direct model instantiation without dependencies
**Solution**: Use `ContainerConfig::getContainer()->get('model_factory')->new('ModelName')` or `ContainerConfig::createModel()` instead of `new ModelClass()`

#### Issue: Test failures with mock setup
**Cause**: Old test setup trying to inject mocks via ServiceLocator
**Solution**: Update to direct dependency injection in test setUp()

#### Issue: Circular dependency errors
**Cause**: ModelFactory included in ModelBase constructor creates circular reference
**Solution**: Container configuration handles this automatically with lazy loading

## Step 7: Performance Optimization

### Expected Improvements

- **Faster model instantiation** - No ServiceLocator lookups
- **Reduced memory usage** - Direct property access
- **Faster test execution** - Simplified mock setup
- **Better initialization performance** - All dependencies available immediately

### Monitoring

After migration, monitor:
- Model creation performance
- Memory usage patterns
- Test execution times
- API response times

## Rollback Strategy

If critical issues are discovered:

1. **Immediate Rollback**: Revert all model constructors to accept only Logger
2. **Re-enable ServiceLocator**: Add fallback methods back to ModelBase
3. **Restore Tests**: Revert to complex TestableModelBase pattern
4. **Factory Rollback**: Restore ServiceLocator usage in factories

## Success Criteria

✅ **All ModelBase subclasses** use pure dependency injection constructors
✅ **Zero ServiceLocator usage** in ModelBase and subclasses
✅ **All tests passing** with simplified dependency injection
✅ **No performance regressions** in model operations
✅ **80% reduction** in test setup complexity achieved

## Additional Resources

- **ModelBase Architecture Documentation**: `docs/ModelBase_Method_Conversion_Summary.md`
- **Container Configuration Guide**: `docs/core/ContainerConfig.md`
- **Testing Best Practices**: `docs/testing/pure_di_testing_guide.md`
- **Troubleshooting Guide**: `docs/troubleshooting/migration_issues.md`

This migration represents a significant improvement in code quality, testability, and maintainability while maintaining all existing ModelBase functionality.
