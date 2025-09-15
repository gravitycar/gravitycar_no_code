# AuthorizationService Pure Dependency Injection Migration

## Overview
Successfully migrated `AuthorizationService` from ServiceLocator-based pattern to pure dependency injection, following the ModelBase pattern established in previous service migrations. This migration involved 4 explicit dependencies and addressed critical constructor issues in the original implementation.

## Migration Summary

### Before (ServiceLocator Pattern - with Issues)
```php
class AuthorizationService {
    private Logger $logger;
    private ModelFactory $modelFactory;
    private DatabaseConnectorInterface $databaseConnector;
    private UserContextInterface $userContext;
    
    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        UserContextInterface $userContext = null
    ) {
        // BROKEN: these would reference undefined properties
        $this->logger = $logger ?? $this->logger;  // ❌
        $this->modelFactory = $modelFactory ?? ServiceLocator::getModelFactory();
        $this->databaseConnector = $databaseConnector ?? $this->databaseConnector;  // ❌
        $this->userContext = $userContext ?? new UserContext();
    }
}
```

### After (Pure Dependency Injection)
```php
class AuthorizationService {
    private LoggerInterface $logger;
    private ModelFactory $modelFactory;
    private DatabaseConnectorInterface $databaseConnector;
    private UserContextInterface $userContext;
    
    public function __construct(
        LoggerInterface $logger,
        ModelFactory $modelFactory,
        DatabaseConnectorInterface $databaseConnector,
        UserContextInterface $userContext
    ) {
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->databaseConnector = $databaseConnector;
        $this->userContext = $userContext;
    }
}
```

## Critical Issues Fixed

### 1. Constructor Logic Bugs
The original constructor had serious bugs:
- `$this->logger = $logger ?? $this->logger;` - References undefined property
- `$this->databaseConnector = $databaseConnector ?? $this->databaseConnector;` - References undefined property

These would cause fatal errors at runtime when no dependencies were provided.

### 2. Hard-coded Dependencies
- `new UserContext()` created direct dependency instead of using interface
- Made testing and dependency substitution difficult

## Dependencies Analysis

AuthorizationService requires 4 dependencies for comprehensive authorization:

1. **LoggerInterface** - Security audit logging for permission checks and authorization decisions
2. **ModelFactory** - Creating Users, Roles, and Permissions model instances
3. **DatabaseConnectorInterface** - Direct database operations for role queries
4. **UserContextInterface** - Current user context for authorization checks

## ServiceLocator Elimination

This migration involved removing **1 ServiceLocator call** and fixing the broken constructor:

### Constructor ServiceLocator Removal
- `ServiceLocator::getModelFactory()` → Direct injection
- Fixed broken property self-references
- Removed direct `new UserContext()` instantiation

### Interface Enhancement Requirement
To support the migration, we also enhanced `CurrentUserProvider` to implement both interfaces:
- `CurrentUserProviderInterface` (existing)
- `UserContextInterface` (added)

This allows `CurrentUserProvider` to be injected wherever `UserContextInterface` is required.

## Interface-Based Design Improvements

Enhanced the service to use interface-based dependencies:

### Before
```php
private Logger $logger;                           // Concrete class
private DatabaseConnectorInterface $databaseConnector; // Interface (good)
private UserContextInterface $userContext;       // Interface (good)
```

### After
```php
private LoggerInterface $logger;                  // Interface
private DatabaseConnectorInterface $databaseConnector; // Interface (maintained)
private UserContextInterface $userContext;       // Interface (maintained)
```

### Benefits
- Better abstraction and loose coupling
- Easier to mock for testing with LoggerInterface
- Interface contracts ensure API compliance
- More flexible dependency injection for authorization testing

## Container Configuration

Updated `ContainerConfig::configureCoreServices()` to include the missing `userContext` parameter:

### Before
```php
$di->set('authorization_service', $di->lazyNew(\Gravitycar\Services\AuthorizationService::class));
$di->params[\Gravitycar\Services\AuthorizationService::class] = [
    'logger' => $di->lazyGet('logger'),
    'modelFactory' => $di->lazyGet('model_factory'),
    'databaseConnector' => $di->lazyGet('database_connector')
    // Missing userContext parameter
];
```

### After
```php
$di->set('authorization_service', $di->lazyNew(\Gravitycar\Services\AuthorizationService::class));
$di->params[\Gravitycar\Services\AuthorizationService::class] = [
    'logger' => $di->lazyGet('logger'),
    'modelFactory' => $di->lazyGet('model_factory'),
    'databaseConnector' => $di->lazyGet('database_connector'),
    'userContext' => $di->lazyGet('current_user_provider')
];
```

Note: Uses `current_user_provider` which now implements both `CurrentUserProviderInterface` and `UserContextInterface`.

## Core Functionality

AuthorizationService provides security-critical authorization capabilities:

### Permission-Based Authorization
- `hasPermission(string $permission, string $model = '', ModelBase $user = null)` - Check specific permissions
- `roleHasPermission(ModelBase $role, string $permission, string $model)` - Role permission validation
- `getUserPermissions(ModelBase $user)` - Retrieve all user permissions

### Role-Based Authorization
- `hasRole(ModelBase $user, string $roleName)` - Single role check
- `hasAnyRole(ModelBase $user, array $requiredRoles)` - Multiple role check
- `getUserRoles(ModelBase $user)` - Retrieve user roles
- `isUserInRole(ModelBase $user, string $roleName)` - Alternative role checking

### Route-Based Authorization
- `authorizeByRoute(array $route)` - Route-level authorization
- `checkRoutePermissions(array $route, ModelBase $user)` - Route permission validation
- Integration with request routing system

### Administrative Functions
- `grantPermissionToRole(ModelBase $role, string $permission, string $model)` - Permission assignment
- `revokePermissionFromRole(ModelBase $role, string $permission, string $model)` - Permission removal
- `assignRoleToUser(ModelBase $user, ModelBase $role)` - Role assignment
- `removeRoleFromUser(ModelBase $user, ModelBase $role)` - Role removal

## Usage Patterns

### Container-Based Creation
```php
$container = \Gravitycar\Core\ContainerConfig::getContainer();
$authzService = $container->get('authorization_service');
```

### Direct Instantiation (Testing)
```php
$mockLogger = $this->createMock(LoggerInterface::class);
$mockModelFactory = $this->createMock(ModelFactory::class);
$mockDatabase = $this->createMock(DatabaseConnectorInterface::class);
$mockUserContext = $this->createMock(UserContextInterface::class);

$authzService = new AuthorizationService(
    $mockLogger,
    $mockModelFactory,
    $mockDatabase,
    $mockUserContext
);
```

### Authorization Examples
```php
$authzService = ContainerConfig::getContainer()->get('authorization_service');

// Permission-based authorization
if ($authzService->hasPermission('read', 'Movies')) {
    // User can read movies
}

// Role-based authorization
if ($authzService->hasAnyRole($user, ['admin', 'moderator'])) {
    // User has admin or moderator privileges
}

// Route-based authorization
$route = ['permission' => 'delete', 'model' => 'Users'];
if ($authzService->authorizeByRoute($route)) {
    // User can access this route
}

// Current user authorization (auto-resolved)
if ($authzService->hasPermission('create', 'Movies')) {
    // Current user can create movies
}
```

## Benefits Achieved

### 1. **Critical Bug Fixes**
- Fixed constructor property self-reference bugs
- Eliminated potential runtime fatal errors
- Proper dependency initialization

### 2. **Explicit Dependencies**
- Constructor signature clearly shows all 4 dependencies
- No hidden ServiceLocator dependencies
- Clear authorization service requirements

### 3. **Interface-Based Design**
- Uses LoggerInterface, DatabaseConnectorInterface, UserContextInterface
- Better abstraction and loose coupling
- Enhanced testability with interface mocks

### 4. **Immutable Dependencies**
- All dependencies set at construction time
- No runtime dependency changes
- Predictable authorization behavior

### 5. **Improved Testability**
- Direct mock injection via constructor
- No complex ServiceLocator mocking needed
- Clean test setup with interface mocks

### 6. **Security Enhancement**
- Reliable dependency injection for security-critical operations
- Consistent logging and auditing capabilities
- Predictable authorization behavior

## Security Considerations

Authorization security features preserved and enhanced:
- Role-based access control (RBAC) implementation
- Permission-level granular security
- User context security (current user resolution)
- Security audit logging for all authorization decisions
- Fail-secure behavior (deny access on errors)
- Route-level authorization for API endpoints

## Validation Results

Migration validation checks:
- ✅ No ServiceLocator usage found (eliminated constructor fallback)
- ✅ 4 explicit constructor dependencies
- ✅ Container-based creation working
- ✅ Interface-based dependencies implemented (3 interfaces)
- ✅ Constructor signature correct (no defaults)

## Files Modified

1. **src/Services/AuthorizationService.php**
   - Constructor refactored to pure DI with 4 explicit dependencies
   - Fixed critical constructor bugs (property self-references)
   - ServiceLocator usage eliminated
   - Interface-based dependencies (LoggerInterface)

2. **src/Services/CurrentUserProvider.php**
   - Added `UserContextInterface` implementation
   - Now supports both `CurrentUserProviderInterface` and `UserContextInterface`
   - Enables flexible dependency injection

3. **src/Core/ContainerConfig.php**
   - Added missing `userContext` parameter configuration
   - Proper dependency injection mapping

4. **tmp/validate_authorization_service_pure_di.php**
   - Comprehensive validation script
   - Tests all aspects of pure DI implementation

## Testing Strategy

### Unit Tests (Pure DI Pattern)
```php
class AuthorizationServiceTest extends TestCase {
    private AuthorizationService $authzService;
    private LoggerInterface $mockLogger;
    private ModelFactory $mockModelFactory;
    private DatabaseConnectorInterface $mockDatabase;
    private UserContextInterface $mockUserContext;
    
    protected function setUp(): void {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockDatabase = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockUserContext = $this->createMock(UserContextInterface::class);
        
        $this->authzService = new AuthorizationService(
            $this->mockLogger,
            $this->mockModelFactory,
            $this->mockDatabase,
            $this->mockUserContext
        );
    }
    
    public function testHasPermission(): void {
        // Test with direct dependency injection
        // No ServiceLocator mocking needed
    }
}
```

## Integration Points

AuthorizationService is used by:
- **API Controllers**: Route-level authorization for endpoints
- **AuthenticationService**: User permission validation after login
- **Administrative Interfaces**: Role and permission management
- **Middleware Systems**: Request authorization filtering
- **Business Logic**: Feature-level access control

## Performance Impact

Pure DI improvements:
- **Reduced Overhead**: No lazy loading or ServiceLocator calls during authorization
- **Faster Instantiation**: Dependencies resolved once at construction
- **Better Memory Usage**: No hidden dependency references
- **Optimized Security Operations**: Direct access to all required services
- **Consistent Logging**: Reliable audit trail for security events

## Migration Complexity Score: 7/10

This was a medium-high complexity service migration due to:
- **4 Dependencies**: Moderate dependency count with interface requirements
- **Critical Bugs**: Fixed serious constructor implementation issues
- **Security Critical**: Authorization system affects application security
- **Interface Enhancement**: Required enhancing CurrentUserProvider
- **Container Updates**: Added missing parameter configuration

## Next Steps

With AuthorizationService migrated, the services migration progress:
- ✅ **DocumentationCache**: 2 dependencies - Complete
- ✅ **ReactComponentMapper**: 2 dependencies - Complete  
- ✅ **UserService**: 4 dependencies - Complete
- ✅ **AuthorizationService**: 4 dependencies - Complete
- ✅ **AuthenticationService**: 5 dependencies - Complete
- ✅ **OpenAPIGenerator**: 7 dependencies - Complete

### Future Work
- **TMDBApiService**: External API service migration
- **GoogleOAuthService**: OAuth service migration
- **Enhanced Testing**: Comprehensive unit tests using pure DI pattern
- **Security Analysis**: Review authorization performance improvements

## Service Dependencies Comparison

| Service | Dependencies | ServiceLocator Calls | Complexity | Status |
|---------|-------------|---------------------|------------|---------|
| DocumentationCache | 2 | 4 | Low | ✅ Complete |
| ReactComponentMapper | 2 | 6 | Low | ✅ Complete |
| UserService | 4 | 6+ | Medium | ✅ Complete |
| AuthorizationService | 4 | 1 + Bugs | Medium-High | ✅ Complete |
| AuthenticationService | 5 | 20+ | High | ✅ Complete |
| OpenAPIGenerator | 7 | 15+ | High | ✅ Complete |

The AuthorizationService pure DI migration successfully addressed critical constructor bugs while implementing comprehensive dependency injection for the framework's security-critical authorization system.