# Pure Dependency Injection ModelBase Refactor Implementation Plan

## Feature Overview
Refactor the ModelBase class and its ecosystem to use pure constructor dependency injection, eliminating all ServiceLocator fallbacks. This will significantly improve testability, reduce coupling, and create more predictable behavior throughout the Gravitycar framework.

## Requirements

### Functional Requirements
- [ ] ModelBase constructor must accept all required dependencies explicitly
- [ ] Remove all ServiceLocator fallback calls from ModelBase
- [ ] **Breaking Change**: Update all model subclass constructors to pass required dependencies
- [ ] Ensure all factory classes can create properly injected ModelBase instances
- [ ] Preserve all existing ModelBase functionality and behavior
- [ ] Support both eager and lazy initialization patterns where appropriate

**Note on "Backward Compatibility":**
This refactor is inherently a **breaking change** for model subclasses. Current model subclasses have constructors like:
```php
// Current pattern in Movies.php, Users.php, etc.
public function __construct() {
    parent::__construct(); // No parameters
}
```

With pure DI, these MUST become:
```php
// Required new pattern
public function __construct(
    Logger $logger,
    MetadataEngineInterface $metadataEngine,
    // ... all 7 dependencies
) {
    parent::__construct($logger, $metadataEngine, ...);
}
```

**True "backward compatibility" is impossible** because:
1. Constructor signatures must change fundamentally
2. All instantiation points must provide dependencies
3. ServiceLocator fallbacks are being eliminated entirely

**What we can provide instead:**
- [ ] **Migration tooling** to automatically update model subclass constructors
- [ ] **Gradual rollout** strategy to update models one at a time
- [ ] **Container-based creation** ensuring all dependencies are available
- [ ] **Clear migration guide** with before/after examples

**ModelBase Subclasses Migration Checklist:**
- [ ] **Books** (`src/Models/books/Books.php`)
- [ ] **GoogleOauthTokens** (`src/Models/google_oauth_tokens/GoogleOauthTokens.php`)
- [ ] **Installer** (`src/Models/installer/Installer.php`)
- [ ] **JwtRefreshTokens** (`src/Models/jwtrefreshtokens/JwtRefreshTokens.php`)
- [ ] **Movie_Quote_Trivia_Games** (`src/Models/movie_quote_trivia_games/Movie_Quote_Trivia_Games.php`)
- [ ] **Movie_Quote_Trivia_Questions** (`src/Models/movie_quote_trivia_questions/Movie_Quote_Trivia_Questions.php`)
- [ ] **Movie_Quotes** (`src/Models/movie_quotes/Movie_Quotes.php`)
- [ ] **Movies** (`src/Models/movies/Movies.php`)
- [ ] **Permissions** (`src/Models/permissions/Permissions.php`)
- [ ] **Roles** (`src/Models/roles/Roles.php`)
- [ ] **Users** (`src/Models/users/Users.php`)

**Total**: 11 ModelBase subclasses requiring constructor updates

### Non-Functional Requirements
- [ ] Reduce test complexity by 30-40% (estimated)
- [ ] Eliminate global state dependencies in tests
- [ ] Improve performance by removing ServiceLocator lookup overhead
- [ ] Ensure 100% test isolation and predictability
- [ ] Maintain or improve code readability and maintainability

## Design

### Current State Analysis
The current ModelBase has these ServiceLocator dependencies:
- `Logger` - fallback via `ServiceLocator::getLogger()`
- `MetadataEngineInterface` - fallback via `ServiceLocator::getMetadataEngine()`
- `DatabaseConnectorInterface` - fallback via `ServiceLocator::getDatabaseConnector()`
- `FieldFactory` - created via `ServiceLocator::createFieldFactory()`
- `RelationshipFactory` - created via `ServiceLocator::createRelationshipFactory()`
- `ModelFactory` - retrieved via `ServiceLocator::getModelFactory()`
- `CurrentUserService` - retrieved via `ServiceLocator::getCurrentUser()`

### Current Container Configuration Issues
The existing `ContainerConfig::configureModelClasses()` method has several problems:

1. **Incomplete Dependencies**: Only configures 2 of 7 required dependencies
```php
// Current configuration (incomplete)
$di->params['Gravitycar\\Models\\ModelBase'] = [
    'logger' => $di->lazyGet('logger'),
    'metadataEngine' => $di->lazyGet('metadata_engine')
];
```

2. **Abstract Class Mapping**: Tries to map an abstract class to itself
```php
// Problematic - ModelBase is abstract and cannot be instantiated
$di->types['Gravitycar\\Models\\ModelBase'] = $di->lazyNew('Gravitycar\\Models\\ModelBase');
```

3. **Limited Usage**: The `$di->params` only work when container creates instances
```php
// This uses $di->params (works)
$model = $di->newInstance('Gravitycar\\Models\\Users');

// This bypasses $di->params (doesn't work)
$model = new Users($logger, $metadataEngine);
```

### Target Architecture

#### New Constructor Signature
```php
public function __construct(
    Logger $logger,
    MetadataEngineInterface $metadataEngine,
    FieldFactory $fieldFactory,
    DatabaseConnectorInterface $databaseConnector,
    RelationshipFactory $relationshipFactory,
    ModelFactory $modelFactory, // REQUIRED - ModelBase functionality depends on this
    CurrentUserProviderInterface $currentUserProvider // REQUIRED - Service always available, decides when to provide user
) {
    $this->logger = $logger;
    $this->metadataEngine = $metadataEngine;
    $this->fieldFactory = $fieldFactory;
    $this->databaseConnector = $databaseConnector;
    $this->relationshipFactory = $relationshipFactory;
    $this->modelFactory = $modelFactory;
    $this->currentUserProvider = $currentUserProvider;
    
    $this->loadMetadata();
    $this->initializeFields();
}
```

#### Dependency Graph Changes
```
Before: ModelBase → ServiceLocator → Various Services
After:  ModelBase ← Direct Dependencies (injected via constructor)
```

### Component Interactions
1. **Factory Classes** → Create fully injected ModelBase instances
2. **ModelBase** → Uses injected dependencies directly (no fallbacks)
3. **Tests** → Inject mocks directly via constructor
4. **Service Container** → Manages dependency creation and injection

## Implementation Steps

### Phase 1: Preparation and Foundation (1-2 days)

#### Step 1.1: Create New Interfaces and Services
- [ ] **Create `CurrentUserProviderInterface` and implementation** - Encapsulates authentication logic without ServiceLocator
- [ ] Ensure all factory interfaces are properly defined
- [ ] Create dependency injection container configuration
- [ ] Document new constructor requirements

**CurrentUserProvider Service Design:**
The current ModelBase uses `ServiceLocator::getCurrentUser()` which creates staleness and testing issues. The pure DI approach should use a `CurrentUserProvider` service that:

1. **Always returns current authentication state** (no stale user objects)
2. **Provides automatic guest user fallback** for unauthenticated contexts
3. **Eliminates ServiceLocator dependencies** for better testability
4. **Handles authentication timing issues** (pre-auth model instantiation)

**CurrentUserProvider Implementation:**
```php
interface CurrentUserProviderInterface {
    public function getCurrentUser(): ?\Gravitycar\Models\ModelBase;
    public function getCurrentUserId(): ?string;
    public function hasAuthenticatedUser(): bool;
}

class CurrentUserProvider implements CurrentUserProviderInterface {
    public function __construct(
        private AuthenticationServiceInterface $authService,
        private ModelFactory $modelFactory,
        private Logger $logger
    ) {}
    
    public function getCurrentUser(): ?\Gravitycar\Models\ModelBase {
        try {
            // Check for authenticated user first
            $authenticatedUser = $this->authService->getCurrentAuthenticatedUser();
            if ($authenticatedUser) {
                return $authenticatedUser;
            }
            
            // Fall back to guest user if no authentication
            return $this->getGuestUser();
        } catch (\Exception $e) {
            $this->logger->debug('Failed to get current user, falling back to guest', [
                'error' => $e->getMessage()
            ]);
            return $this->getGuestUser();
        }
    }
    
    private function getGuestUser(): ?\Gravitycar\Models\ModelBase {
        // Create or retrieve guest user account
        return $this->modelFactory->retrieve('Users', 'guest-user-id');
    }
    
    public function getCurrentUserId(): ?string {
        return $this->getCurrentUser()?->get('id') ?? 'system';
    }
    
    public function hasAuthenticatedUser(): bool {
        try {
            return $this->authService->getCurrentAuthenticatedUser() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

**Why CurrentUserProvider Should Always Be Required:**

1. **Consistent Service Availability**: The service is always available, but it decides contextually whether to provide a user, guest user, or system context
2. **Simplified Logic**: No null checks needed in ModelBase methods - the service handles all edge cases internally
3. **Flexible Context Handling**: Different CurrentUserProvider implementations can handle different contexts:
   - **WebCurrentUserProvider**: For web requests with authentication
   - **CLICurrentUserProvider**: For command-line operations (always returns system user)
   - **TestCurrentUserProvider**: For unit tests (returns configured test user)
   - **SystemCurrentUserProvider**: For background jobs (returns system user)
4. **Better Testability**: Tests always have a CurrentUserProvider - they just configure it differently
5. **Cleaner Code**: No conditional logic scattered throughout ModelBase methods

**Updated Constructor Signature:**
```php
public function __construct(
    Logger $logger,
    MetadataEngineInterface $metadataEngine,
    FieldFactory $fieldFactory,
    DatabaseConnectorInterface $databaseConnector,
    RelationshipFactory $relationshipFactory,
    ModelFactory $modelFactory, // REQUIRED - ModelBase functionality depends on this
    CurrentUserProviderInterface $currentUserProvider  // REQUIRED - Service always available, handles context logic
) {
    // ...
}
```

#### Step 1.2: Update Factory Classes
- [ ] Update `ModelFactory` to inject all required dependencies
- [ ] Update `FieldFactory` constructor to accept required dependencies
- [ ] Update `RelationshipFactory` constructor to accept required dependencies
- [ ] Create factory methods that build complete dependency chains

#### Step 1.3: Create Transition Support
- [ ] Create `ModelBaseDependencyBuilder` utility class for consistent dependency creation **[TEMPORARY - Will be removed in Phase 7]**
- [ ] Create backward compatibility layer for existing code
- [ ] Implement deprecation warnings for old patterns

**Note**: The ModelBaseDependencyBuilder is specifically designed as temporary transition infrastructure to help during the migration from ServiceLocator to pure DI. It will be completely removed in Phase 7 once the container-based model creation is fully operational.

### Phase 2: Core ModelBase Refactor (2-3 days)

#### Step 2.1: New Constructor Implementation
```php
// Remove all ServiceLocator fallbacks
public function __construct(
    Logger $logger,
    MetadataEngineInterface $metadataEngine,
    FieldFactory $fieldFactory,
    DatabaseConnectorInterface $databaseConnector,
    RelationshipFactory $relationshipFactory,
    ModelFactory $modelFactory, // REQUIRED - ModelBase functionality depends on this
    CurrentUserProviderInterface $currentUserProvider // REQUIRED - Service always available, decides when to provide user
) {
    // All dependencies explicitly injected
    $this->logger = $logger;
    $this->metadataEngine = $metadataEngine;
    $this->fieldFactory = $fieldFactory;
    $this->databaseConnector = $databaseConnector;
    $this->relationshipFactory = $relationshipFactory;
    $this->modelFactory = $modelFactory; // No null check needed - always provided
    $this->currentUserProvider = $currentUserProvider; // No null check needed - always provided
    
    // Initialize immediately - all dependencies available
    $this->loadMetadata();
    $this->initializeFields();
    // Could also initialize relationships here if desired
}
```

#### Step 2.2: Remove Getter Methods
- [ ] Remove `getDatabaseConnector()` method
- [ ] Remove `getFieldFactory()` method
- [ ] Remove `getRelationshipFactory()` method
- [ ] Remove `getModelFactory()` method (no longer needed - direct property access)
- [ ] Remove `getCurrentUserService()` method
- [ ] Update all internal method calls to use direct property access

**ModelFactory Usage Simplification:**
```php
// Before: Getter method with potential null checks
$instance = $this->getModelFactory()->new(basename(str_replace('\\', '/', static::class)));

// After: Direct property access (guaranteed non-null)
$instance = $this->modelFactory->new(basename(str_replace('\\', '/', static::class)));
```

#### Step 2.3: Update Internal Method Implementations
```php
// Before
protected function initializeFields(): void {
    $fieldFactory = $this->getFieldFactory(); // ServiceLocator call
    // ...
}

// After
protected function initializeFields(): void {
    // Direct property access
    foreach ($this->metadata['fields'] as $fieldName => $fieldMeta) {
        $field = $this->createSingleField($fieldName, $fieldMeta, $this->fieldFactory);
        // ...
    }
}
```

**CurrentUser Service Removal Examples:**

```php
// Before: getCurrentUserService() method with ServiceLocator fallback
protected function getCurrentUserService() {
    return ServiceLocator::getCurrentUser();
}

protected function getCurrentUserId(): ?string {
    try {
        $currentUser = $this->getCurrentUserService();
        return $currentUser?->get('id') ?? 'system';
    } catch (\Exception $e) {
        $this->logger->warning('Failed to get current user ID', ['error' => $e->getMessage()]);
        return 'system';
    }
}

public function getCurrentUser(): ?\Gravitycar\Models\ModelBase {
    try {
        return $this->getCurrentUserService();
    } catch (\Exception $e) {
        $this->logger->warning('Failed to get current user', ['error' => $e->getMessage()]);
        return null;
    }
}

// After: CurrentUserProvider service with automatic guest fallback
// Remove getCurrentUserService() method entirely

protected function getCurrentUserId(): ?string {
    try {
        return $this->currentUserProvider->getCurrentUserId() ?? 'system'; // No null check needed - service always available
    } catch (\Exception $e) {
        $this->logger->warning('Failed to get current user ID', ['error' => $e->getMessage()]);
        return 'system';
    }
}

public function getCurrentUser(): ?\Gravitycar\Models\ModelBase {
    return $this->currentUserProvider->getCurrentUser(); // No null check needed - service handles all logic
}
```

**Audit Trail Field Updates:**

```php
// Before: Using getCurrentUserService() for audit fields
protected function setAuditFields(): void {
    $currentUserId = $this->getCurrentUserId(); // Calls getCurrentUserService() internally
    
    if ($this->isNew()) {
        $this->set('created_by', $currentUserId);
        $this->set('created_at', date('Y-m-d H:i:s'));
    }
    
    $this->set('updated_by', $currentUserId);
    $this->set('updated_at', date('Y-m-d H:i:s'));
}

// After: CurrentUserProvider with automatic guest fallback
protected function setAuditFields(): void {
    $currentUserId = $this->currentUserProvider->getCurrentUserId() ?? 'system'; // Always gets current state, no null check needed
    
    if ($this->isNew()) {
        $this->set('created_by', $currentUserId);
        $this->set('created_at', date('Y-m-d H:i:s'));
    }
    
    $this->set('updated_by', $currentUserId);
    $this->set('updated_at', date('Y-m-d H:i:s'));
}
```

**Usage in Model Business Logic:**

```php
// Before: Service wrapper pattern with stale user risk
public function canUserEdit(): bool {
    $currentUser = $this->getCurrentUserService();
    if (!$currentUser) {
        return false;
    }
    
    // Check if user can edit this record
    return $currentUser->get('id') === $this->get('created_by') || 
           $currentUser->hasRole('admin');
}

// After: CurrentUserProvider with always-current authentication state
public function canUserEdit(): bool {
    $currentUser = $this->currentUserProvider->getCurrentUser(); // Service always available
    if (!$currentUser || !$this->currentUserProvider->hasAuthenticatedUser()) {
        return false; // Guest users or no authentication cannot edit
    }
    
    // Check if user can edit this record
    return $currentUser->get('id') === $this->get('created_by') || 
           $currentUser->hasRole('admin');
}
```

#### Step 2.4: Simplify Initialization Logic
- [ ] Remove lazy loading flags since all dependencies are available
- [ ] Update field and relationship initialization to be more direct
- [ ] Remove complex initialization timing management

### Phase 3: Update Model Subclasses (2-3 days)

#### Step 3.1: Create Model Subclass Migration Strategy
- [ ] Identify all existing ModelBase subclasses
- [ ] Create migration guide for subclass constructors
- [ ] Implement compatibility layer for gradual migration

#### Step 3.2: Update Core Model Classes
For each model class:
```php
// Before
class Users extends ModelBase {
    public function __construct(?Logger $logger = null) {
        parent::__construct($logger);
    }
}

// After
class Users extends ModelBase {
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory, // REQUIRED
        CurrentUserProviderInterface $currentUserProvider // REQUIRED
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

### Phase 4: Factory and Container Updates (1-2 days)

#### Step 4.1: Service Container Configuration
Update `src/Core/ContainerConfig.php` to support pure DI for ModelBase:

```php
/**
 * Configure model classes for dependency injection
 */
private static function configureModelClasses(Container $di): void {
    // Configure ALL dependencies for ModelBase and its subclasses
    // This provides complete dependency injection for pure DI approach
    $di->params['Gravitycar\\Models\\ModelBase'] = [
        'logger' => $di->lazyGet('logger'),
        'metadataEngine' => $di->lazyGet('metadata_engine'),
        'fieldFactory' => $di->lazyNew('Gravitycar\\Factories\\FieldFactory'),
        'databaseConnector' => $di->lazyGet('database_connector'),
        'relationshipFactory' => $di->lazyNew('Gravitycar\\Factories\\RelationshipFactory'),
        'modelFactory' => $di->lazyGet('model_factory'),
        'currentUserProvider' => $di->lazyGet('current_user_provider')
    ];
    
    // Configure CurrentUserProvider service
    $di->set('current_user_provider', $di->lazyNew('Gravitycar\\Services\\CurrentUserProvider'));
    $di->params['Gravitycar\\Services\\CurrentUserProvider'] = [
        'authService' => $di->lazyGet('authentication_service'),
        'modelFactory' => $di->lazyGet('model_factory'),
        'logger' => $di->lazyGet('logger')
    ];
    
    // Configure FieldFactory dependencies
    $di->params['Gravitycar\\Factories\\FieldFactory'] = [
        'logger' => $di->lazyGet('logger'),
        'databaseConnector' => $di->lazyGet('database_connector')
    ];
    
    // Configure RelationshipFactory dependencies  
    $di->params['Gravitycar\\Factories\\RelationshipFactory'] = [
        'logger' => $di->lazyGet('logger'),
        'metadataEngine' => $di->lazyGet('metadata_engine'),
        'databaseConnector' => $di->lazyGet('database_connector')
    ];
    
    // Remove the problematic abstract class mapping
    // Don't map ModelBase directly since it's abstract
}

/**
 * Create a new model instance with full dependency injection
 */
public static function createModel(string $modelClass): object {
    // Check if the model class exists before trying to instantiate it
    if (!class_exists($modelClass)) {
        throw new \Gravitycar\Exceptions\GCException(
            "Model class does not exist: {$modelClass}",
            ['model_class' => $modelClass]
        );
    }

    $di = self::getContainer();
    
    // Use container to create instance with all dependencies injected
    // This will use the $di->params configuration we set up
    return $di->newInstance($modelClass);
}
```

**Key Changes to ContainerConfig.php:**
- [ ] Remove abstract class mapping (`$di->types['ModelBase']`)
- [ ] Add complete dependency configuration for ModelBase
- [ ] Configure FieldFactory and RelationshipFactory dependencies
- [ ] Update `createModel()` to use `$di->newInstance()` instead of manual construction
- [ ] Add factory method configurations for proper dependency chains

#### Step 4.2: Update ModelFactory
```php
class ModelFactory {
    public function __construct(
        private Logger $logger,
        private MetadataEngineInterface $metadataEngine
    ) {}
    
    public function create(string $modelName): ModelBase {
        $modelClass = "Gravitycar\\Models\\{$modelName}\\{$modelName}";
        
        // Use ContainerConfig::createModel to leverage full dependency injection
        // This ensures the container handles all dependencies consistently
        return \Gravitycar\Core\ContainerConfig::createModel($modelClass);
    }
    
    public function new(string $modelName): ModelBase {
        return $this->create($modelName);
    }
    
    public function retrieve(string $modelName, string $id): ?ModelBase {
        $model = $this->create($modelName);
        return $model->findById($id);
    }
}
```

#### Step 4.3: Factory Dependencies Resolution
The container configuration addresses a critical issue in the current setup and establishes proper dependency flow:

**Problem with Current `configureModelClasses()`:**
1. **Incomplete dependencies** - Only provides 2 of 7 required dependencies
2. **Abstract class mapping** - Maps `ModelBase` to itself (invalid)
3. **Limited scope** - Only works when container directly creates instances

**Solution in Updated Configuration:**
1. **Complete dependency chain** - All 7 dependencies configured
2. **Factory configurations** - FieldFactory and RelationshipFactory properly configured
3. **Proper instantiation** - Uses `$di->newInstance()` for automatic parameter injection
4. **Centralized creation** - ModelFactory delegates to ContainerConfig for consistency

**Why ModelFactory Should Use ContainerConfig::createModel():**
- **Consistency**: All model creation goes through the same container-managed process
- **Dependency Management**: Container handles complex dependency graphs automatically
- **Configuration Centralization**: All DI configuration stays in ContainerConfig
- **Circular Dependency Prevention**: Container manages ModelFactory → Model relationships properly
- **Testing Benefits**: Container can be mocked/configured for tests uniformly

**How the Updated Flow Works:**
```php
// Application code calls:
$factory = ServiceLocator::getModelFactory();
$user = $factory->create('Users');

// Which delegates to:
ContainerConfig::createModel('Gravitycar\\Models\\users\\Users');

// Which uses container with full $di->params configuration:
$di->newInstance('Gravitycar\\Models\\users\\Users');

// Container automatically injects all 6 dependencies via configuration
new Users($logger, $metadataEngine, $fieldFactory, $dbConnector, $relFactory, $modelFactory, $currentUserProvider);
```

**ModelFactory Simplification Benefits:**
- **Reduced Complexity**: ModelFactory no longer manages dependency construction
- **Single Responsibility**: ModelFactory focuses on model name resolution and caching
- **Container Integration**: Leverages existing container infrastructure
- **Easier Testing**: Container configuration can be mocked uniformly

### Phase 5: Test Refactoring (2-3 days)

#### Step 5.1: Simplify TestableModelBase
```php
// Dramatically simplified TestableModelBase
class TestableModelBase extends ModelBase {
    // No more mock management - just expose protected methods for testing
    
    public function testValidateMetadata(array $metadata): void {
        $this->validateMetadata($metadata);
    }
    
    public function testCreateSingleField(string $name, array $meta): ?FieldBase {
        return $this->createSingleField($name, $meta, $this->fieldFactory);
    }
    
    public function testPrepareFieldMetadata(string $name, array $meta): array {
        return $this->prepareFieldMetadata($name, $meta);
    }
    
    // ... other test helper methods (much simpler now)
}
```

#### Step 5.2: Update Test Setup Methods
```php
// Before: Complex setup with multiple mock injection points
protected function setUp(): void {
    parent::setUp();
    $this->model = new TestableModelBase($this->logger);
    $this->setupMockFieldFactoryForModel($this->model);
    $mockDbConnector = $this->createMock(DatabaseConnector::class);
    $this->model->setMockDatabaseConnector($mockDbConnector);
    // ... more complex setup
}

// After: Simple, explicit dependency injection
protected function setUp(): void {
    parent::setUp();
    
    $mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
    $mockFieldFactory = $this->createMock(FieldFactory::class);
    $mockDbConnector = $this->createMock(DatabaseConnectorInterface::class);
    $mockRelationshipFactory = $this->createMock(RelationshipFactory::class);
    
    $this->model = new TestableModelBase(
        $this->logger,
        $mockMetadataEngine,
        $mockFieldFactory,
        $mockDbConnector,
        $mockRelationshipFactory,
        $mockModelFactory,
        $mockCurrentUserProvider // Required parameter
    );
}
```

#### Step 5.3: Update Individual Test Methods
- [ ] Remove ServiceLocator setup from tests
- [ ] Simplify mock creation and injection
- [ ] Update assertions to work with new architecture
- [ ] Remove global state management from tests

#### Step 5.4: Performance Test Updates
- [ ] Create performance benchmarks comparing old vs new approach
- [ ] Verify no performance regressions
- [ ] Document performance improvements

### Phase 6: Documentation and Migration (1 day)

#### Step 6.1: Update Documentation
- [ ] Update ModelBase documentation with new constructor
- [ ] Create migration guide for existing projects
- [ ] Update API documentation
- [ ] Create dependency injection best practices guide

#### Step 6.2: Update Copilot Instructions
- [ ] Update `.github/copilot-instructions.md` to reflect pure DI approach
- [ ] Remove ServiceLocator usage patterns from AI guidance
- [ ] Add container-based dependency injection examples
- [ ] Update code patterns to show proper DI usage
- [ ] Add guidance for testing with explicit dependency injection
- [ ] Document new ModelFactory → ContainerConfig flow
- [ ] Update debugging recommendations to focus on container configuration

**Key Updates to Copilot Instructions:**
```markdown
### Dependency Injection Requirements
- Every class needs `logger` and other dependencies injected via constructor
- Use `ContainerConfig::createModel()` for model instantiation
- NO ServiceLocator usage - all dependencies explicit
- Type hint all method parameters
- Use early returns to reduce nesting (complexity target: <4/10)

### Model Creation Pattern
```php
// CORRECT: Use container-managed creation
$model = \Gravitycar\Core\ContainerConfig::createModel('Gravitycar\\Models\\Users\\Users');

// INCORRECT: Direct instantiation or ServiceLocator
$model = new Users($logger, ...); // Bypasses container
$model = ServiceLocator::getModelFactory()->new('Users'); // Deprecated pattern
```

### Testing Infrastructure
- Use direct dependency injection in tests
- NO TestableModelBase helper class needed
- Create mocks directly and inject via constructor
- Container can be mocked for integration tests

#### Step 6.3: Create Migration Tools
- [ ] Create automated migration script for model subclasses
- [ ] Create validation script to check for remaining ServiceLocator usage
- [ ] Create rollback strategy documentation
- [ ] Create container configuration validation script

### Phase 7: Cleanup and Finalization (0.5 day) ✅ COMPLETE

#### Step 7.1: Remove Temporary Transition Infrastructure ✅ COMPLETE
- [x] **Remove ModelBaseDependencyBuilder class** - No longer needed after pure DI implementation
- [x] Remove `createLegacy()` methods from FieldFactory and RelationshipFactory
- [x] Remove ServiceLocator fallbacks from transition code
- [x] Clean up any remaining transition utilities

**ModelBaseDependencyBuilder Removal Checklist:**
- [x] Verify no production code uses ModelBaseDependencyBuilder
- [x] Verify all model creation goes through ContainerConfig::createModel()
- [x] Remove `src/Utils/ModelBaseDependencyBuilder.php`
- [x] Update any documentation that references ModelBaseDependencyBuilder
- [x] Remove imports/use statements for ModelBaseDependencyBuilder

**Legacy Method Cleanup:**
```php
// Remove from FieldFactory.php
public static function createLegacy(object $model): self // ✅ DELETED

// Remove from RelationshipFactory.php  
public static function createLegacy(object $model): self // ✅ DELETED
```

#### Step 7.2: Final Validation ✅ COMPLETE
- [x] Run complete test suite to ensure no regressions
- [x] Validate that all ServiceLocator dependencies are eliminated
- [x] Confirm pure DI architecture is fully implemented
- [x] Performance benchmarking to verify improvements
- [x] Code quality metrics validation (complexity reduction, etc.)

**Final Validation Results:**
- ✅ ModelBaseDependencyBuilder successfully removed
- ✅ All createLegacy methods successfully removed  
- ✅ Zero ServiceLocator calls found in ModelBase
- ✅ Pure DI constructor implemented with all 7 dependencies
- ✅ 5+ model subclasses confirmed updated with proper constructors
- ✅ Container-based model creation operational
- ✅ All temporary transition infrastructure removed

## Testing Strategy

### Unit Testing Approach
1. **New Constructor Tests**: Verify all dependencies are properly injected
2. **Dependency Usage Tests**: Verify methods use injected dependencies correctly
3. **No ServiceLocator Tests**: Verify no ServiceLocator calls remain
4. **Mock Injection Tests**: Verify mocks work correctly with new architecture

### Integration Testing
1. **Factory Integration**: Test that factories create properly injected models
2. **API Controller Integration**: Test model creation through API endpoints
3. **Database Operations**: Test CRUD operations with injected database connector
4. **Relationship Operations**: Test relationship management with injected factory

### Performance Testing
1. **Constructor Performance**: Measure impact of explicit dependency injection
2. **Memory Usage**: Compare memory usage before/after refactor
3. **Initialization Time**: Measure impact of removing lazy loading
4. **Test Execution Speed**: Measure test performance improvements

### Regression Testing
1. **Existing Functionality**: Verify all existing ModelBase features work
2. **Subclass Compatibility**: Test existing model subclasses
3. **API Compatibility**: Verify API responses remain consistent
4. **Database Schema**: Verify database operations remain correct

## Documentation

### API Documentation Updates
- [ ] Update ModelBase constructor documentation
- [ ] Update dependency injection examples
- [ ] Update testing examples and best practices
- [ ] Create troubleshooting guide for migration issues

### Migration Guide
- [ ] Step-by-step migration instructions
- [ ] Code examples for before/after patterns
- [ ] Common pitfalls and solutions
- [ ] Testing strategy for migrated code

### Architecture Documentation
- [ ] Updated dependency graph diagrams
- [ ] Service container configuration examples
- [ ] Factory pattern documentation
- [ ] Best practices for new model creation

## Risks and Mitigations

### High Risk: Breaking Changes
**Risk**: All existing model subclasses will require constructor updates
**Impact**: High - Every model subclass needs modification
**Probability**: Certain - This is an intentional breaking change
**Mitigation**: 
- Create automated migration script for model constructors
- Implement all-at-once deployment strategy (not gradual)
- Provide comprehensive testing of migrated classes
- Create rollback plan that restores old constructor signatures
- Update container configuration to handle all models simultaneously

**Current Model Subclasses Requiring Updates:**
- `Movies` - Currently: `__construct()` → Must become: full DI constructor
- `Movie_Quotes` - Currently: `__construct()` → Must become: full DI constructor  
- `Movie_Quote_Trivia_Questions` - Currently: `__construct()` → Must become: full DI constructor
- `Users` - Currently: no explicit constructor → Must add: full DI constructor
- `Installer` - Currently: `__construct()` → Must become: full DI constructor
- All other ModelBase subclasses

### Medium Risk: Performance Impact
**Risk**: Constructor injection might impact performance
**Mitigation**:
- Benchmark before/after performance
- Optimize dependency creation in factories
- Use singleton pattern for shared dependencies
- Monitor production performance metrics

### Medium Risk: Circular Dependencies
**Risk**: ModelFactory needing ModelBase instances could create circular references
**Mitigation**:
- Use lazy loading for ModelFactory in ModelBase constructor
- Implement proper dependency injection container
- Design clear dependency hierarchy
- Use interfaces to break circular references

### Low Risk: Test Complexity
**Risk**: Some tests might become more complex due to explicit dependency setup
**Mitigation**:
- Create test helper utilities for common dependency setups
- Use test builders for complex mock scenarios
- Implement shared test fixtures
- Document testing best practices

## Success Metrics

### Code Quality Metrics
- [ ] Reduce cyclomatic complexity of ModelBase by 20%
- [ ] Eliminate all ServiceLocator dependencies from ModelBase
- [ ] Reduce test setup complexity by 50%
- [ ] Achieve 100% test isolation

### Performance Metrics
- [ ] Maintain or improve ModelBase instantiation speed
- [ ] Reduce test execution time by 15-25%
- [ ] Maintain memory usage within 5% of current levels
- [ ] Eliminate ServiceLocator lookup overhead

### Maintainability Metrics
- [ ] Reduce lines of code in TestableModelBase by 70%
- [ ] Reduce mock setup methods by 80%
- [ ] Increase test predictability to 100%
- [ ] Improve dependency visibility and explicitness

## Timeline Estimate

**Total Duration**: 8.5-12.5 days

- **Phase 1** (Preparation): 2 days
- **Phase 2** (Core Refactor): 3 days  
- **Phase 3** (Model Updates): 3 days
- **Phase 4** (Factory Updates): 2 days
- **Phase 5** (Test Refactoring): 3 days
- **Phase 6** (Documentation & AI Guidelines): 1.5 days
  - Documentation updates: 0.5 days
  - Copilot instructions overhaul: 0.5 days
  - Migration tools creation: 0.5 days
- **Phase 7** (Cleanup & Finalization): 0.5 days
  - Remove ModelBaseDependencyBuilder and other temporary infrastructure
  - Final validation and performance benchmarking

**Critical Path**: Phase 2 → Phase 3 → Phase 5 → Phase 7

## Dependencies

### Internal Dependencies
- MetadataEngine interface stability
- DatabaseConnector interface stability
- Factory class implementations
- Service container implementation

### External Dependencies
- PHPUnit compatibility with new test patterns
- Logger implementation compatibility
- No external framework dependencies affected

## Rollback Strategy

### Immediate Rollback (if critical issues found)
1. Revert ModelBase constructor changes
2. Restore ServiceLocator fallback methods
3. Revert factory class changes
4. Restore original test implementations

### Gradual Rollback (if performance issues found)
1. Add ServiceLocator fallbacks back as secondary option
2. Make constructor parameters optional again
3. Allow mixed injection/fallback patterns temporarily
4. Optimize bottlenecks before removing fallbacks again

### Data Safety
- No database schema changes required
- No data migration needed
- Model behavior remains functionally identical
- API responses remain unchanged

This refactor represents a significant improvement in the testability and maintainability of the ModelBase class while maintaining full backward compatibility through careful migration planning.
