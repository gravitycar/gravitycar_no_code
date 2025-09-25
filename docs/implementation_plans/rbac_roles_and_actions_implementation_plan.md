# RBAC Roles and Actions Implementation Plan

## Feature Overview

This implementation extends the Gravitycar Framework's existing Role-Based Access Control (RBAC) system to include fine-grained action-based permissions at the model level. The current system provides role-based authentication and basic permissions, but lacks granular control over specific actions (CRUD operations) for individual models.

The new feature will:
- Define default action-based permissions in `ModelBase` for all models
- Allow individual models to override default permissions via metadata
- Automatically build permission records in the database during metadata rebuilding
- Enable the `AuthorizationService` to check permissions based on model-action combinations
- Maintain backward compatibility with existing role-based authorization

## Requirements

### Functional Requirements

1. **Default Permissions Structure**
   - `ModelBase` must define a default `rolesAndActions` structure
   - Default structure includes: `admin`, `manager`, `user`, `guest` roles
   - Actions include: `list`, `read`, `create`, `update`, `delete`, and `*` (wildcard for all actions)

2. **Metadata Override System**
   - Individual models can override specific role permissions via `rolesAndActions` in metadata
   - Override is partial (not complete replacement) - only specified roles are modified
   - Metadata changes must trigger permission rebuilding

3. **Database Permission Storage**
   - Each model-role-action combination creates a `Permissions` record
   - Permissions link to specific roles via existing `roles_permissions` relationship
   - Permission records are rebuilt automatically when metadata changes

4. **Authorization Integration**
   - `AuthorizationService.hasPermission()` uses database lookups for permission checks
   - Router integration for automatic permission checking on API endpoints
   - Backward compatibility with existing role-based checks

5. **Cache Integration**
   - Model permission metadata included in `metadata_cache.php`
   - Permission rebuilding triggered by `setup.php` script during system setup

### Non-Functional Requirements

1. **Performance**
   - Permission checks must not significantly impact API response times
   - Database queries optimized for permission lookups
   - Metadata caching prevents repeated file I/O

2. **Security**
   - Fail-secure principle: deny access on permission lookup failures
   - Comprehensive logging of permission checks and failures
   - Protection against privilege escalation

3. **Maintainability**
   - Pure dependency injection for all new classes
   - Comprehensive unit and integration tests
   - Clear separation of concerns between services

## Design

### Architecture Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   ModelBase     │    │ PermissionsBuilder │   │ AuthorizationSrv │
│                 │    │                 │    │                 │
│ rolesAndActions │───▶│ buildPermissions│───▶│ hasPermission   │
│ (defaults)      │    │                 │    │ (DB lookup)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         ▲                       ▲                       ▲
         │                       │                       │
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Model Metadata  │    │   setup.php     │    │     Router      │
│                 │    │                 │    │                 │
│ rolesAndActions │───▶│ build permissions│    │ route           │
│ (overrides)     │    │                 │    │ (auth check)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Component Design

#### 1. ModelBase Enhancement
- Add protected `$rolesAndActions` property with default permissions structure
- Add `getRolesAndActions()` method that merges defaults with metadata overrides
- Maintain backward compatibility with existing model functionality

#### 2. PermissionsBuilder Service
- New service in `src/Services/PermissionsBuilder.php`
- Pure dependency injection constructor
- Methods:
  - `buildAllPermissions()`: Clear existing permissions and rebuild all model and controller permissions
  - `buildAllModelPermissions()`: Build permissions for all models without clearing existing ones
  - `buildAllControllerPermissions()`: Build permissions for all API controllers without clearing existing ones  
  - `buildPermissionsForModel()`: Build permissions for a specific model
  - `buildPermissionsForController()`: Build permissions for a specific API controller
  - `clearExistingPermissions()`: Clean up old permission records
  - `createPermissionRecord()`: Create individual permission-role associations

#### 3. APIControllerBase Enhancement
- Add protected `$rolesAndActions` property with default empty structure
- Add `getRolesAndActions()` method for controller-specific permissions
- Allow individual controllers to define custom permission structures independent of models

#### 4. Setup.php Integration
- Add PermissionsBuilder call to setup.php script after existing components
- Call permission rebuilding after MetadataEngine, APIRouteRegistry, and SchemaGenerator complete
- Ensure permissions are synchronized with metadata changes during setup

#### 5. AuthorizationService Enhancement
- Update `hasPermission()` method signature to `hasPermission(array $route, Request $request, User $currentUser)`
- Add `determineAction()` helper method to extract action from route or map HTTP methods to CRUD operations  
- Add `determineComponent()` helper method to extract component from request model or route apiClass
- Use ModelFactory (already injected) to query Permissions model via relationship joins for database-based permission checking
- Maintain existing `hasRole()` functionality for backward compatibility

#### 6. Router Integration
- Simplify permission checking in `handleAuthentication()` method
- Use new `AuthorizationService::hasPermission()` method directly with route and request context
- Remove separate model-action permission checking (integrated into unified permission check)
- Maintain existing role-based checks for backward compatibility

#### 7. Users Model Enhancement
- Automatically assign roles based on `user_type` field value during user creation and updates
- Implement `assignRoleFromUserType()` method to link users to roles via `users_roles` relationship
- Use `getRoleByName()` method (same technique as AuthorizationService) to find roles by name
- Clear existing role assignments when user_type changes to prevent duplicate assignments
- Handle role assignment errors gracefully with comprehensive logging

### Database Schema Changes

#### Permissions Table Updates
The existing `Permissions` table structure will be used with these considerations:
- `component` field: stores the model name (e.g., "Users", "Movies") OR controller class name (e.g., "Gravitycar\\Api\\CustomController")
- `action` field: stores the action name (e.g., "list", "read", "create", "update", "delete") for models OR custom actions for controllers
- Linked to roles via existing `roles_permissions` many-to-many relationship

#### Permission Record Structure
```
Permissions Record (Model):
- id: UUID
- component: "Users"
- action: "create" 
- created_at: timestamp
- updated_at: timestamp

Permissions Record (Controller):
- id: UUID
- component: "Gravitycar\\Api\\CustomController"
- action: "execute"
- created_at: timestamp
- updated_at: timestamp

Linked via roles_permissions to:
- role_id: UUID (references roles.id)
- permission_id: UUID (references permissions.id)
```

## Implementation Steps

### Phase 1: Core Infrastructure (3-4 hours)

1. **Create PermissionsBuilderException**
   - Create custom exception class extending `GCException`
   - Provides specific exception handling for permission building operations
   - Includes context-aware error messaging

**Code Example: PermissionsBuilderException**

```php
// New file: src/Exceptions/PermissionsBuilderException.php
<?php

namespace Gravitycar\Exceptions;

/**
 * PermissionsBuilderException
 * 
 * Specialized exception for permission building operations.
 * Provides context-aware error messages for permission-related failures.
 */
class PermissionsBuilderException extends GCException
{
    // Standard constructor inheritance - no static factory methods needed
    // Use: throw new PermissionsBuilderException($message, $context);
}
}
```

2. **Update Permissions Model Metadata**
   - Change 'model' field to 'component' in permissions_metadata.php
   - Update field name and description to reflect new purpose
   - Maintain backward compatibility during transition

**Code Example: Permissions Metadata Update**

```php
// Update: src/Models/permissions/permissions_metadata.php
<?php

return [
    'name' => 'Permissions',
    'table' => 'permissions',
    'fields' => [
        'component' => [  // Changed from 'model' to 'component'
            'type' => 'Text',
            'label' => 'Component',
            'name' => 'component',  // Updated field name
            'required' => true,
            'validationRules' => ['Required', 'Alphanumeric'],
            'maxLength' => 100,
            'description' => 'Application component (model name or controller class) this permission applies to'
        ],
        'action' => [
            'type' => 'Text',
            'label' => 'Action',
            'name' => 'action',
            'required' => true,
            'validationRules' => ['Required', 'Alphanumeric'],
            'maxLength' => 50
        ],
        // ... other fields remain unchanged
    ],
    
    // NEW: Permissions management is admin-only
    'rolesAndActions' => [
        'admin' => ['*'], // Only admins can manage permissions
        // All other roles inherit default: no access
    ],
    
    'validationRules' => [],
    'relationships' => ['roles_permissions', 'users_permissions'],
    'ui' => [
        'listFields' => ['action', 'component', 'description', 'is_route_permission', 'created_at'],
        'createFields' => ['action', 'component', 'description', 'allowed_roles', 'is_route_permission', 'route_pattern'],
        'editFields' => ['action', 'component', 'description', 'allowed_roles', 'is_route_permission', 'route_pattern'],
    ],
];
```

2. **Update Permissions Model Metadata**
   - Change 'model' field to 'component' in permissions_metadata.php
   - Update field name and description to reflect new purpose
   - Maintain backward compatibility during transition

**Code Example: Permissions Metadata Update**

```php
// Update: src/Models/permissions/permissions_metadata.php
<?php

return [
    'name' => 'Permissions',
    'table' => 'permissions',
    'fields' => [
        'component' => [  // Changed from 'model' to 'component'
            'type' => 'Text',
            'label' => 'Component',
            'name' => 'component',  // Updated field name
            'required' => true,
            'validationRules' => ['Required', 'Alphanumeric'],
            'maxLength' => 100,
            'description' => 'Application component (model name or controller class) this permission applies to'
        ],
        'action' => [
            'type' => 'Text',
            'label' => 'Action',
            'name' => 'action',
            'required' => true,
            'validationRules' => ['Required', 'Alphanumeric'],
            'maxLength' => 50
        ],
        // ... other fields remain unchanged
    ],
    
    // NEW: Permissions management is admin-only
    'rolesAndActions' => [
        'admin' => ['*'], // Only admins can manage permissions
        // All other roles inherit default: no access
    ],
    
    'validationRules' => [],
    'relationships' => ['roles_permissions', 'users_permissions'],
    'ui' => [
        'listFields' => ['action', 'component', 'description', 'is_route_permission', 'created_at'],
        'createFields' => ['action', 'component', 'description', 'allowed_roles', 'is_route_permission', 'route_pattern'],
        'editFields' => ['action', 'component', 'description', 'allowed_roles', 'is_route_permission', 'route_pattern'],
    ],
];
```

3. **Enhance ModelBase**
   - Add `$rolesAndActions` property with default structure
   - Implement `getRolesAndActions()` method with metadata merging logic
   - Add unit tests for permission structure handling

**Code Example: ModelBase Enhancement**

```php
// In src/Models/ModelBase.php - Add to class properties
/**
 * Default roles and actions for all models
 * Can be overridden by individual models via metadata
 */
protected array $rolesAndActions = [
    'admin' => ['*'], // Admin can perform all actions
    'manager' => ['list', 'read', 'create', 'update', 'delete'],
    'user' => ['list', 'read', 'create', 'update', 'delete'],
    'guest' => [] // Guest has no default permissions
];

// Add new method to ModelBase class
/**
 * Get roles and actions for this model, merging defaults with metadata overrides
 * 
 * @return array Combined roles and actions configuration
 */
public function getRolesAndActions(): array {
    $defaultRoles = $this->rolesAndActions;
    
    // Check if model metadata contains rolesAndActions override
    $metadataOverrides = $this->metadata['rolesAndActions'] ?? [];
    
    if (empty($metadataOverrides)) {
        $this->logger->debug('No rolesAndActions overrides found in metadata, using defaults', [
            'model' => static::class,
            'default_roles' => array_keys($defaultRoles)
        ]);
        return $defaultRoles;
    }
    
    // Merge overrides with defaults (overrides take precedence)
    $mergedRoles = $defaultRoles;
    
    foreach ($metadataOverrides as $role => $actions) {
        if (!is_array($actions)) {
            $this->logger->warning('Invalid rolesAndActions override format, skipping role', [
                'model' => static::class,
                'role' => $role,
                'actions' => $actions
            ]);
            continue;
        }
        
        $mergedRoles[$role] = $actions;
        $this->logger->debug('Applied rolesAndActions override', [
            'model' => static::class,
            'role' => $role,
            'actions' => $actions
        ]);
    }
    
    return $mergedRoles;
}

/**
 * Get all possible actions for this model (used by PermissionsBuilder)
 * 
 * @return array List of all unique actions across all roles
 */
public function getAllPossibleActions(): array {
    $rolesAndActions = $this->getRolesAndActions();
    $allActions = [];
    
    foreach ($rolesAndActions as $role => $actions) {
        if (in_array('*', $actions)) {
            // If wildcard found, return all standard CRUD actions
            return ['list', 'read', 'create', 'update', 'delete'];
        }
        $allActions = array_merge($allActions, $actions);
    }
    
    return array_unique($allActions);
}
```

4. **Enhance APIControllerBase**
   - Add `$rolesAndActions` property with default empty structure
   - Implement `getRolesAndActions()` method for controller-specific permissions
   - Allow individual controllers to define custom permission structures

**Code Example: APIControllerBase Enhancement**

```php
// In src/Api/ApiControllerBase.php - Add to class properties
/**
 * Default roles and actions for this API controller
 * Can be overridden by individual controllers
 */
protected array $rolesAndActions = [
    // Empty by default - controllers can override this property
    // Example structure:
    // 'admin' => ['*'],
    // 'manager' => ['specific_action', 'another_action'],
    // 'user' => ['read_only_action'],
    // 'guest' => []
];

// Add new method to APIControllerBase class
/**
 * Get roles and actions for this API controller
 * 
 * @return array Controller-specific roles and actions configuration
 */
public function getRolesAndActions(): array {
    // Controllers can override the $rolesAndActions property directly
    // No metadata merging needed since controllers define permissions in code
    return $this->rolesAndActions;
}

/**
 * Get the controller name for permission context
 * 
 * @return string The controller class name
 */
public function getControllerName(): string {
    return static::class;
}
```

5. **Update RelationshipBase::add() Method**
   - Change line 587 from `return false;` to `return true;` when relationship already exists
   - Ensures idempotent behavior for duplicate relationship creation attempts
   - Review and update unit tests that depend on this method's behavior

**Code Change Required:**
```php
// In src/Relationships/RelationshipBase.php - Line 587
// Change this line in the existing add() method:
return false;  // OLD

// To this:
return true;   // NEW - provides idempotent behavior
```

6. **Enhance DatabaseConnector with Truncate Method**
   - Add `truncate()` method to DatabaseConnector for efficient table clearing
   - Support both model tables and relationship junction tables
   - Provide safe truncation with proper error handling

**Code Example: DatabaseConnector Enhancement**

```php
// In src/Database/DatabaseConnector.php - Add new method

/**
 * Truncate a table efficiently while preserving structure
 * Works with both model tables and relationship junction tables
 * 
 * @param ModelBase $model The model whose table should be truncated (includes RelationshipBase)
 * @throws GCException If truncation fails
 */
public function truncate(ModelBase $model): void
{
    try {
        $tableName = $model->getTableName();
        
        $this->logger->debug('Truncating table', [
            'model' => get_class($model),
            'table' => $tableName
        ]);
        
        $sql = "TRUNCATE TABLE `{$tableName}`";
        $this->connection->executeStatement($sql);
        
        $this->logger->info('Successfully truncated table', [
            'model' => get_class($model),
            'table' => $tableName
        ]);
        
    } catch (\PDOException $e) {
        $this->logger->error('Failed to truncate table', [
            'error' => $e->getMessage(),
            'model' => get_class($model),
            'table' => $model->getTableName()
        ]);
        
        throw new GCException(
            'Database truncation failed: ' . $e->getMessage(),
            [
                'error' => $e->getMessage(),
                'model' => get_class($model),
                'table' => $model->getTableName()
            ],
            0,
            $e
        );
    }
}
```

7. **Enhance APIRouteRegistry for Controller Discovery**
   - Add `getAllRegisteredControllers()` method to APIRouteRegistry
   - Use cached route data to discover and instantiate all API controllers
   - Provide controller instances for permission building

7. **Enhance APIRouteRegistry for Controller Discovery**
   - Add `getAllRegisteredControllers()` method to APIRouteRegistry
   - Use cached route data to discover and instantiate all API controllers
   - Provide controller instances for permission building

**Code Example: APIRouteRegistry Enhancement**

```php
// In src/Api/APIRouteRegistry.php - Add new method

/**
 * Get all registered API controllers with their instances
 * Uses cached route data to discover and instantiate controllers
 * 
 * @return array Array of [$apiControllerClassName => $apiControllerInstance]
 * @throws GCException If controller instantiation fails
 */
public function getAllRegisteredControllers(): array
{
    // Ensure we have cached route data loaded
    if (empty($this->routes)) {
        if (!$this->loadFromCache()) {
            // If no cache, discover routes first
            $this->discoverAndRegisterRoutes();
        }
    }
    
    $controllers = [];
    $distinctApiClasses = [];
    
    // Extract all distinct apiClass values from routes
    foreach ($this->routes as $route) {
        $apiClass = $route['resolvedApiClass'] ?? $route['apiClass'];
        if (!in_array($apiClass, $distinctApiClasses)) {
            $distinctApiClasses[] = $apiClass;
        }
    }
    
    $this->logger->debug('Found distinct API classes for controller instantiation', [
        'count' => count($distinctApiClasses),
        'classes' => $distinctApiClasses
    ]);
    
    // Get APIControllerFactory for proper dependency injection
    try {
        $container = \Gravitycar\Core\ContainerConfig::getContainer();
        $factory = $container->get('api_controller_factory');
    } catch (\Exception $e) {
        throw new GCException('Failed to get APIControllerFactory for controller instantiation', [
            'error' => $e->getMessage()
        ], 0, $e);
    }
    
    // Instantiate each distinct controller with proper dependencies
    foreach ($distinctApiClasses as $apiClass) {
        try {
            // Find dependencies from cached route data
            $dependencies = $this->getControllerDependenciesFromRoutes($apiClass);
            
            // Create controller instance with dependencies
            $controllerInstance = $factory->createControllerWithDependencyList($apiClass, $dependencies);
            
            $controllers[$apiClass] = $controllerInstance;
            
            $this->logger->debug('Instantiated controller for permissions', [
                'class' => $apiClass,
                'dependencies' => $dependencies
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to instantiate controller for permissions', [
                'class' => $apiClass,
                'error' => $e->getMessage()
            ]);
            
            // Skip this controller but continue with others
            continue;
        }
    }
    
    $this->logger->info('Successfully instantiated controllers for permission building', [
        'total_classes' => count($distinctApiClasses),
        'successful_instantiations' => count($controllers),
        'failed_instantiations' => count($distinctApiClasses) - count($controllers)
    ]);
    
    return $controllers;
}

/**
 * Get cached controller dependencies for a specific API class
 * 
 * @param string $apiClass The API controller class name
 * @return array Array of dependency service names
 */
protected function getControllerDependenciesFromRoutes(string $apiClass): array
{
    // Find the first route that uses this controller and extract dependencies
    foreach ($this->routes as $route) {
        $routeApiClass = $route['resolvedApiClass'] ?? $route['apiClass'];
        if ($routeApiClass === $apiClass) {
            return $route['controllerDependencies'] ?? [];
        }
    }
    
    // If no dependencies found in cache, extract from constructor as fallback
    try {
        return $this->extractDependenciesFromConstructor($apiClass);
    } catch (GCException $e) {
        $this->logger->warning('No dependencies found for controller, using empty array', [
            'class' => $apiClass,
            'error' => $e->getMessage()
        ]);
        return [];
    }
}
```

8. **Create PermissionsBuilder Service**
   - Implement core service with pure dependency injection
   - Add methods for permission building and cleanup
   - Create comprehensive unit tests

**Code Example: PermissionsBuilder Service**

```php
// New file: src/Services/PermissionsBuilder.php
<?php

namespace Gravitycar\Services;

use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Exceptions\PermissionsBuilderException;
use Monolog\Logger;

/**
 * PermissionsBuilder Service
 * 
 * Builds permission records in the database based on model rolesAndActions metadata.
 * Called by MetadataEngine during cache rebuilding to synchronize permissions.
 */
class PermissionsBuilder {
    
    /**
     * Cache of roles by name for efficient lookups
     * @var array<string, \Gravitycar\Models\ModelBase>
     */
    private array $roles = [];
    
    public function __construct(
        private Logger $logger,
        private ModelFactory $modelFactory,
        private DatabaseConnectorInterface $databaseConnector,
        private MetadataEngineInterface $metadataEngine,
        private APIRouteRegistry $apiRouteRegistry
    ) {
        // Pure dependency injection - no ServiceLocator usage
    }
    
    /**
     * Build permissions for all models in the system
     * 
     * @throws PermissionsBuilderException If permission building fails
     */
    public function buildAllPermissions(): void {
        $this->logger->info('Starting permission build for all models');
        
        try {
            // Clear existing permissions to prevent duplicates
            $this->clearExistingPermissions();
            
            // Build permissions for all models
            $this->buildAllModelPermissions();
            
            // Build permissions for all API controllers
            $this->buildAllControllerPermissions();
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build permissions for all models', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException("Permission building system failure: " . $e->getMessage(), [
                'original_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Build permissions for all available models without clearing existing permissions
     * 
     * @return int Total number of permission records created
     * @throws PermissionsBuilderException If model permission building fails
     */
    public function buildAllModelPermissions(): int {
        $this->logger->info('Building permissions for all available models');
        
        try {
            // Get list of available models from MetadataEngine
            $modelNames = $this->metadataEngine->getAvailableModels();
            
            $totalPermissions = 0;
            
            foreach ($modelNames as $modelName) {
                $permissionsCreated = $this->buildPermissionsForModel($modelName);
                $totalPermissions += $permissionsCreated;
                
                $this->logger->debug('Built permissions for model', [
                    'model' => $modelName,
                    'permissions_created' => $permissionsCreated
                ]);
            }
            
            $this->logger->info('Completed permission build for all models', [
                'total_models' => count($modelNames),
                'total_permissions' => $totalPermissions
            ]);
            
            return $totalPermissions;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build model permissions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException("Permission building system failure: " . $e->getMessage(), [
                'original_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Build permissions for a specific model
     * 
     * @param string $modelName The name of the model to build permissions for
     * @return int Number of permission records created
     */
    public function buildPermissionsForModel(string $modelName): int {
        $this->logger->debug('Building permissions for model', ['model' => $modelName]);
        
        try {
            // Create model instance to get rolesAndActions
            $model = $this->modelFactory->new($modelName);
            $rolesAndActions = $model->getRolesAndActions();
            
            $permissionsCreated = 0;
            
            // Process each role's actions
            foreach ($rolesAndActions as $roleName => $actions) {
                try {
                    $role = $this->getRoleByName($roleName);
                } catch (PermissionsBuilderException $e) {
                    $this->logger->warning('Role not found in database, skipping', [
                        'model' => $modelName,
                        'role' => $roleName,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
                $actionsToCreate = $actions;
                
                // Handle wildcard permissions
                if (in_array('*', $actions)) {
                    $actionsToCreate = ['list', 'read', 'create', 'update', 'delete'];
                }
                
                // Create permission record for each action
                foreach ($actionsToCreate as $action) {
                    $permissionModel = $this->createPermissionRecord($modelName, $action);
                    $this->linkPermissionToRole($permissionModel, $role);
                    $permissionsCreated++;
                }
            }
            
            return $permissionsCreated;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build permissions for model', [
                'model' => $modelName,
                'error' => $e->getMessage()
            ]);
            
            throw new PermissionsBuilderException("Failed to build permissions for model '{$modelName}': " . $e->getMessage(), [
                'model' => $modelName,
                'original_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Build permissions for all API controllers in the system
     * 
     * @return int Total number of controller permission records created
     * @throws PermissionsBuilderException If controller permission building fails
     */
    public function buildAllControllerPermissions(): int {
        $this->logger->info('Building permissions for all API controllers');
        
        try {
            // Get all API controller instances
            $controllerInstances = $this->getApiControllerClasses();
            
            $totalPermissions = 0;
            
            foreach ($controllerInstances as $controllerClass => $controllerInstance) {
                $permissionsCreated = $this->buildPermissionsForController($controllerInstance);
                $totalPermissions += $permissionsCreated;
                
                $this->logger->debug('Built permissions for controller', [
                    'controller' => $controllerClass,
                    'permissions_created' => $permissionsCreated
                ]);
            }
            
            $this->logger->info('Completed permission build for all controllers', [
                'total_controllers' => count($controllerInstances),
                'total_permissions' => $totalPermissions
            ]);
            
            return $totalPermissions;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build controller permissions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException("Controller permission building system failure: " . $e->getMessage(), [
                'original_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Build permissions for a specific API controller
     * 
     * @param \Gravitycar\Api\ApiControllerBase $controller The controller instance to build permissions for
     * @return int Number of permission records created
     */
    public function buildPermissionsForController(\Gravitycar\Api\ApiControllerBase $controller): int {
        $controllerClassName = get_class($controller);
        $this->logger->debug('Building permissions for controller', ['controller' => $controllerClassName]);
        
        try {
            // Check if controller has getRolesAndActions method
            if (!method_exists($controller, 'getRolesAndActions')) {
                $this->logger->debug('Controller does not have getRolesAndActions method, skipping', [
                    'controller' => $controllerClassName
                ]);
                return 0;
            }
            
            $rolesAndActions = $controller->getRolesAndActions();
            
            // Skip if no permissions defined
            if (empty($rolesAndActions)) {
                $this->logger->debug('Controller has no roles and actions defined, skipping', [
                    'controller' => $controllerClassName
                ]);
                return 0;
            }
            
            $permissionsCreated = 0;
            
            // Process each role's actions
            foreach ($rolesAndActions as $roleName => $actions) {
                try {
                    $role = $this->getRoleByName($roleName);
                } catch (PermissionsBuilderException $e) {
                    $this->logger->warning('Role not found in database, skipping', [
                        'controller' => $controllerClassName,
                        'role' => $roleName,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
                
                $actionsToCreate = $actions;
                
                // Handle wildcard permissions
                if (in_array('*', $actions)) {
                    // For controllers, wildcard means all actions defined by that controller
                    // We'll use a generic set for now, but controllers could define their own
                    $actionsToCreate = ['execute', 'access'];
                }
                
                // Create permission record for each action
                foreach ($actionsToCreate as $action) {
                    $permissionModel = $this->createPermissionRecord($controllerClassName, $action);
                    $this->linkPermissionToRole($permissionModel, $role);
                    $permissionsCreated++;
                }
            }
            
            return $permissionsCreated;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build permissions for controller', [
                'controller' => $controllerClassName,
                'error' => $e->getMessage()
            ]);
            
            throw new PermissionsBuilderException("Failed to build permissions for controller '{$controllerClassName}': " . $e->getMessage(), [
                'controller' => $controllerClassName,
                'original_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Get all API controller instances in the application
     * Uses APIRouteRegistry to discover registered controllers
     * 
     * @return array Array of APIControllerBase instances
     * @throws PermissionsBuilderException If controller discovery fails
     */
    protected function getApiControllerClasses(): array {
        try {
            // Use APIRouteRegistry to get all registered controllers
            $registeredControllers = $this->apiRouteRegistry->getAllRegisteredControllers();
            
            $this->logger->debug('Retrieved API controller instances from registry', [
                'count' => count($registeredControllers),
                'classes' => array_keys($registeredControllers)
            ]);
            
            return $registeredControllers;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get API controller instances from registry', [
                'error' => $e->getMessage()
            ]);
            
            throw new PermissionsBuilderException("Failed to discover API controllers for permission building: " . $e->getMessage(), [
                'original_error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 0, $e);
        }
    }

    /**
     * Create a permission record in the database
     * 
     * @param string $modelName The model name
     * @param string $action The action name
     * @return \Gravitycar\Models\ModelBase The created or existing permission model instance
     */
    protected function createPermissionRecord(string $modelName, string $action): \Gravitycar\Models\ModelBase {
        $permissionsModel = $this->modelFactory->new('Permissions');
        
        // Check if permission already exists
        $existing = $permissionsModel->findByFields([
            'component' => $modelName,
            'action' => $action,
            'is_route_permission' => false
        ]);
        
        if (!empty($existing)) {
            // Return existing permission model instance
            return $existing[0];
        }
        
        // Create new permission record
        $permissionsModel->set('component', $modelName);
        $permissionsModel->set('action', $action);
        $permissionsModel->set('description', "Auto-generated permission for $action on $modelName");
        $permissionsModel->set('is_route_permission', false);
        $permissionsModel->set('route_pattern', null);
        
        $permissionsModel->save();
        
        $this->logger->debug('Created permission record', [
            'component' => $modelName,
            'action' => $action,
            'permission_id' => $permissionsModel->get('id')
        ]);
        
        return $permissionsModel;
    }
    
    /**
     * Link a permission to a role via the roles_permissions relationship
     * 
     * @param \Gravitycar\Models\ModelBase $permissionsModel The permission model instance
     * @param \Gravitycar\Models\ModelBase $rolesModel The role model instance
     */
    protected function linkPermissionToRole(\Gravitycar\Models\ModelBase $permissionsModel, \Gravitycar\Models\ModelBase $rolesModel): void {
        // Use ModelBase relationship system to create the link
        // RelationshipBase::add() handles duplicate checks automatically
        $permissionsModel->addRelation('roles_permissions', $rolesModel);
        
        $this->logger->debug('Linked permission to role via relationship system', [
            'permission_id' => $permissionsModel->get('id'),
            'role_id' => $rolesModel->get('id')
        ]);
    }
    
    /**
     * Clear existing auto-generated permissions to prevent duplicates
     * Uses efficient table truncation for better performance
     */
    protected function clearExistingPermissions(): void {
        $this->logger->info('Clearing existing auto-generated permissions');
        
        try {
            // Get empty Permissions model to access table structure
            $emptyPermissionsModel = $this->modelFactory->new('Permissions');
            
            // Get the roles_permissions relationship instance
            $rolesPermissionsRelationship = $emptyPermissionsModel->getRelationship('roles_permissions');
            
            // Truncate the junction table first to maintain referential integrity
            $this->databaseConnector->truncate($rolesPermissionsRelationship);
            
            // Then truncate the main permissions table
            $this->databaseConnector->truncate($emptyPermissionsModel);
            
            $this->logger->info('Successfully cleared existing permissions using table truncation');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear existing permissions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException(
                'Failed to clear existing permissions: ' . $e->getMessage(),
                [
                    'original_error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ],
                0,
                $e
            );
        }
    }
    
    /**
     * Generate a unique ID for database records
     * 
     * @return string UUID
     */
    protected function generateId(): string {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
    
    /**
     * Get a role by name with caching for performance
     * 
     * @param string $roleName The name of the role to retrieve
     * @return ModelBase The role model instance
     * @throws PermissionsBuilderException If role is not found
     */
    protected function getRoleByName(string $roleName): ModelBase {
        // Check cache first
        if (isset($this->roles[$roleName])) {
            return $this->roles[$roleName];
        }
        
        // Load role from database
        $rolesModel = $this->modelFactory->new('Roles');
        $results = $rolesModel->find(['name' => $roleName]);
        
        // Check if role was found
        if (empty($results)) {
            $this->logger->error('Role not found in database', ['role_name' => $roleName]);
            throw new PermissionsBuilderException("Role '{$roleName}' not found in database", [
                'role_name' => $roleName
            ]);
        }
        
        // Get first (and should be only) result
        $role = $results[0];
        
        // Cache the result
        $this->roles[$roleName] = $role;
        
        $this->logger->debug('Loaded and cached role', ['role_name' => $roleName]);
        
        return $role;
    }
}
```

**Unit Test Review Requirements:**
After updating `RelationshipBase::add()`, review and update the following test categories:
- Tests that expect `add()` to return `false` for existing relationships (now should return `true`)
- Tests that verify duplicate relationship prevention behavior
- Tests that depend on specific return values from relationship creation
- Integration tests that create multiple relationships and expect specific outcomes

Key test files to review:
- `Tests/Unit/Relationships/RelationshipBaseTest.php`
- `Tests/Unit/Relationships/OneToManyRelationshipTest.php` 
- `Tests/Unit/Relationships/ManyToManyRelationshipTest.php`
- Any model tests that use `addRelation()` method calls

### Phase 2: Authorization Enhancement (2-3 hours)

9. **Enhance AuthorizationService**
   - Modify `hasPermission()` for database-based lookups
   - Add model-action specific permission checking
   - Maintain backward compatibility with existing methods

**Code Example: AuthorizationService Enhancement**

```php
// In src/Services/AuthorizationService.php - Update hasPermission method

/**
 * Determine the action from route and request data
 * 
 * @param array $route The route configuration array
 * @param Request $request The HTTP request object
 * @return string The determined action
 */
protected function determineAction(array $route, Request $request): string
{
    // First check if route has explicit RBACAction
    if (isset($route['RBACAction'])) {
        $this->logger->debug('Using explicit RBACAction from route', [
            'action' => $route['RBACAction'],
            'route_path' => $route['path'] ?? 'unknown'
        ]);
        return $route['RBACAction'];
    }
    
    // Map HTTP methods to CRUD actions
    $httpMethod = strtoupper($request->getMethod());
    $actionMapping = [
        'GET' => 'read',
        'POST' => 'create', 
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete'
    ];
    
    $action = $actionMapping[$httpMethod] ?? 'read';
    
    $this->logger->debug('Mapped HTTP method to action', [
        'http_method' => $httpMethod,
        'action' => $action,
        'route_path' => $route['path'] ?? 'unknown'
    ]);
    
    return $action;
}

/**
 * Determine the component (model or controller) from route and request data
 * 
 * @param array $route The route configuration array  
 * @param Request $request The HTTP request object
 * @return string The determined component name
 */
protected function determineComponent(array $route, Request $request): string
{
    // First check if request has model parameter
    if ($request->has('model')) {
        $component = $request->get('model');
        $this->logger->debug('Using model from request as component', [
            'component' => $component,
            'route_path' => $route['path'] ?? 'unknown'
        ]);
        return $component;
    }
    
    // Fall back to apiClass from route
    $component = $route['apiClass'] ?? 'Unknown';
    
    $this->logger->debug('Using apiClass from route as component', [
        'component' => $component,
        'route_path' => $route['path'] ?? 'unknown'
    ]);
    
    return $component;
}

/**
 * Check if user has specific permission for a route and request
 * Updated method signature - currentUser cannot be null
 * 
 * @param array $route The route configuration array
 * @param Request $request The HTTP request object
 * @param \Gravitycar\Models\ModelBase $currentUser The current user (required)
 * @return bool True if user has permission, false otherwise
 */
public function hasPermission(array $route, Request $request, \Gravitycar\Models\ModelBase $currentUser): bool
{
    try {
        // Use helper methods to determine action and component
        $action = $this->determineAction($route, $request);
        $component = $this->determineComponent($route, $request);
        
        $this->logger->debug('Checking user permission via database lookup', [
            'user_id' => $currentUser->get('id'),
            'action' => $action,
            'component' => $component,
            'route_path' => $route['path'] ?? 'unknown'
        ]);
        
        // Get user roles using existing getUserRoles method
        $userRoles = $this->getUserRoles($currentUser);
        
        if (empty($userRoles)) {
            $this->logger->debug('User has no roles assigned', [
                'user_id' => $currentUser->get('id')
            ]);
            return false;
        }
        
        // Use ModelFactory to get empty Permissions model for queries
        $permissions = $this->modelFactory->new('Permissions');
        
        // Check each role for the required permission
        foreach ($userRoles as $role) {
            // Find permission records for this action, component, and role
            $foundPermissions = $permissions->find([
                'action' => $action,
                'component' => $component,
                'roles_permissions.role_id' => $role->get('id')
            ]);
            
            if (!empty($foundPermissions)) {
                $this->logger->debug('Permission granted via database lookup', [
                    'user_id' => $currentUser->get('id'),
                    'role_id' => $role->get('id'),
                    'role_name' => $role->get('name'),
                    'action' => $action,
                    'component' => $component,
                    'permission_count' => count($foundPermissions)
                ]);
                return true;
            }
        }
        
        $this->logger->debug('Permission denied - no matching database permissions', [
            'user_id' => $currentUser->get('id'),
            'action' => $action,
            'component' => $component,
            'roles' => array_map(fn($role) => $role->get('name'), $userRoles)
        ]);
        
        return false;
        
    } catch (\Exception $e) {
        $this->logger->error('Error checking user permission', [
            'user_id' => $currentUser->get('id'),
            'route_path' => $route['path'] ?? 'unknown',
            'http_method' => $request->getMethod(),
            'error' => $e->getMessage()
        ]);
        
        // Fail securely - deny access on error
        return false;
    }
}

/**
 * Get all permissions for a user across all models (for debugging/admin interfaces)
 * 
 * @param \Gravitycar\Models\ModelBase $user The user to check
 * @return array Array of permissions grouped by model
 */
public function getUserAllPermissions(\Gravitycar\Models\ModelBase $user): array
{
    try {
        $userRoles = $this->getUserRoles($user);
        
        if (empty($userRoles)) {
            return [];
        }
        
        // Use ModelFactory to get Permissions model for querying
        $permissionsModel = $this->modelFactory->new('Permissions');
        $allPermissions = [];
        
        foreach ($userRoles as $role) {
            // Find all permissions for this role using ModelBase find method
            $rolePermissions = $permissionsModel->find([
                'roles_permissions.role_id' => $role->get('id')
            ]);
            
            foreach ($rolePermissions as $permissionRecord) {
                $component = $permissionRecord['component'];
                $action = $permissionRecord['action'];
                $roleName = $role->get('name');
                
                if (!isset($allPermissions[$component])) {
                    $allPermissions[$component] = [];
                }
                
                if (!isset($allPermissions[$component][$action])) {
                    $allPermissions[$component][$action] = [];
                }
                
                if (!in_array($roleName, $allPermissions[$component][$action])) {
                    $allPermissions[$component][$action][] = $roleName;
                }
            }
        }
        
        // Sort results for consistent output
        ksort($allPermissions);
        foreach ($allPermissions as $component => $actions) {
            ksort($allPermissions[$component]);
        }
        
        return $allPermissions;
        
    } catch (\Exception $e) {
        $this->logger->error('Failed to get user permissions', [
            'user_id' => $user->get('id'),
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}
```

10. **Update Router Authentication**
   - Simplify `handleAuthentication()` to use new AuthorizationService::hasPermission() method directly  
   - Remove separate checkModelActionPermissions method (integrated into main permission check)
   - Maintain existing role-based checks for backward compatibility

**Code Example: Router Enhancement**

```php
// In src/Api/Router.php - Simplify handleAuthentication method

/**
 * Handle authentication and authorization for the route
 * Enhanced to support unified permission checking
 */
protected function handleAuthentication(array $route, Request $request): void {
    // Check if route requires authentication
    $allowedRoles = $route['allowedRoles'] ?? null;
    
    // Public routes (no authentication required)
    if ($allowedRoles === null || in_array('*', $allowedRoles) || in_array('all', $allowedRoles)) {
        return;
    }
    
    try {
        // Get current user from JWT token
        $currentUser = $this->currentUserProvider->getCurrentUser();
        
        if (!$currentUser) {
            throw new UnauthorizedException('Authentication required', [
                'route' => $route['path'],
                'method' => $request->getMethod()
            ]);
        }
        
        // Role-based authorization check (existing functionality - maintained for backward compatibility)
        $hasRequiredRole = false;
        foreach ($allowedRoles as $role) {
            if ($this->authorizationService->hasRole($currentUser, $role)) {
                $hasRequiredRole = true;
                break;
            }
        }
        
        if (!$hasRequiredRole) {
            throw new ForbiddenException('Insufficient role permissions', [
                'route' => $route['path'],
                'required_roles' => $allowedRoles,
                'user_id' => $currentUser->get('id')
            ]);
        }
        
        // Unified permission check using new hasPermission method  
        if (!$this->authorizationService->hasPermission($route, $request, $currentUser)) {
            throw new ForbiddenException('Insufficient permissions for this action', [
                'route' => $route['path'],
                'method' => $request->getMethod(),
                'user_id' => $currentUser->get('id')
            ]);
        }
        
    } catch (UnauthorizedException | ForbiddenException $e) {
        // Re-throw authentication/authorization exceptions
        throw $e;
    } catch (\Exception $e) {
        $this->logger->error('Authentication error: ' . $e->getMessage());
        throw new UnauthorizedException('Authentication failed', [
            'error' => $e->getMessage()
        ]);
    }
    }
}
```

### Phase 3: Model Metadata Integration (2-3 hours)

11. **Add Metadata Override Support**
   - Store `rolesAndActions` data in metadata cache for ModelBase retrieval
   - Ensure rolesAndActions arrays are preserved in metadata_cache.php file
   - No additional validation needed - ModelBase handles defaults and merging

**Code Example: Metadata Storage Enhancement**

```php
// In src/Metadata/MetadataEngine.php - Ensure rolesAndActions data is cached

/**
 * The existing scanAndLoadMetadata() method already handles rolesAndActions storage
 * No additional changes needed - rolesAndActions arrays from metadata files 
 * are automatically included in the cached metadata structure.
 * 
 * ModelBase::getRolesAndActions() will retrieve this data and merge with defaults.
 */

// Example metadata structure that gets cached:
// $metadata['models']['Users'] = [
//     'name' => 'Users',
//     'table' => 'users',  
//     'fields' => [...],
//     'rolesAndActions' => [        // ← This gets cached automatically
//         'admin' => ['*'],
//         'manager' => ['list', 'read'],
//         'user' => ['read']
//     ]
// ];
```

12. **Update Existing Models**
   - Add `rolesAndActions` overrides to critical models (Users, Movies, etc.)
   - Test permission inheritance and override behavior
   - Validate permission records are created correctly

**Code Example: Model Metadata with rolesAndActions Overrides**

```php
// Example 1: src/Models/users/users_metadata.php - Restrict user management
<?php

return [
    'name' => 'Users',
    'table' => 'users',
    'fields' => [
        'username' => [
            'name' => 'username',
            'type' => 'Text',
            'label' => 'Username',
            'required' => true,
            'unique' => true,
            'validationRules' => ['Required', 'Unique', 'Alphanumeric'],
        ],
        'email' => [
            'name' => 'email',
            'type' => 'Email',
            'label' => 'Email Address',
            'required' => true,
            'unique' => true,
            'validationRules' => ['Required', 'Email', 'Unique'],
        ],
        'password' => [
            'name' => 'password',
            'type' => 'Password',
            'label' => 'Password',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        // ... other fields
    ],
    
    // NEW: Override default permissions for user management
    'rolesAndActions' => [
        'admin' => ['*'], // Admin keeps full access
        'manager' => ['list', 'read', 'update'], // Managers cannot create/delete users
        'user' => ['read'], // Regular users can only read their own profile
        'guest' => [] // Guests have no access to user data
    ],
    
    'validationRules' => [],
    'relationships' => ['users_roles', 'users_permissions', 'users_google_oauth_tokens'],
    'ui' => [
        'listFields' => ['username', 'email', 'created_at'],
        'createFields' => ['username', 'email', 'password'],
        'editFields' => ['username', 'email'],
    ],
];

// Example 2: src/Models/movies/movies_metadata.php - Open content management
<?php

return [
    'name' => 'Movies',
    'table' => 'movies',
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'Text',
            'label' => 'Movie Title',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'release_year' => [
            'name' => 'release_year',
            'type' => 'Integer',
            'label' => 'Release Year',
            'required' => false,
            'validationRules' => ['Integer'],
        ],
        'synopsis' => [
            'name' => 'synopsis',
            'type' => 'BigText',
            'label' => 'Synopsis',
            'required' => false,
            'validationRules' => [],
        ],
        // ... other fields
    ],
    
    // NEW: Allow broader access to movie content
    'rolesAndActions' => [
        'admin' => ['*'], // Admin keeps full access
        'manager' => ['*'], // Managers get full access to content
        'user' => ['list', 'read', 'create', 'update'], // Users can contribute content but not delete
        // guest inherits default: [] (no access)
    ],
    
    'validationRules' => [],
    'relationships' => ['movies_movie_quotes'],
    'ui' => [
        'listFields' => ['poster_url', 'name', 'release_year'],
        'createFields' => ['name', 'release_year', 'synopsis'],
        'editFields' => ['name', 'release_year', 'synopsis'],
        'relatedItemsSections' => [
            'quotes' => [
                'title' => 'Movie Quotes',
                'relationship' => 'movies_movie_quotes',
                'displayColumns' => ['quote'],
                'actions' => ['create', 'edit', 'delete']
            ]
        ]
    ],
];

// Example 3: src/Models/roles/roles_metadata.php - Restrict role management
<?php

return [
    'name' => 'Roles',
    'table' => 'roles',
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'Text',
            'label' => 'Role Name',
            'required' => true,
            'unique' => true,
            'validationRules' => ['Required', 'Unique'],
        ],
        'description' => [
            'name' => 'description',
            'type' => 'BigText',
            'label' => 'Description',
            'required' => false,
            'validationRules' => [],
        ],
        // ... other fields
    ],
    
    // NEW: Highly restricted role management
    'rolesAndActions' => [
        'admin' => ['*'], // Only admins can manage roles
        'manager' => ['list', 'read'], // Managers can view but not modify
        'user' => [], // Users have no access to role management
        'guest' => [] // Guests have no access
    ],
    
    'validationRules' => [],
    'relationships' => ['users_roles', 'roles_permissions'],
    'ui' => [
        'listFields' => ['name', 'description', 'is_system_role', 'created_at'],
        'createFields' => ['name', 'description', 'is_oauth_default', 'is_system_role'],
        'editFields' => ['name', 'description', 'is_oauth_default', 'is_system_role'],
    ],
];

// Example 4: src/Models/permissions/permissions_metadata.php - Admin-only management
<?php

return [
    'name' => 'Permissions',
    'table' => 'permissions',
    'fields' => [
        'action' => [
            'name' => 'action',
            'type' => 'Text',
            'label' => 'Action/Operation',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'model' => [
            'name' => 'model',
            'type' => 'Text',
            'label' => 'Model Name',
            'required' => false,
            'defaultValue' => '',
            'validationRules' => [],
        ],
        // ... other fields
    ],
    
    // NEW: Permissions management is admin-only
    'rolesAndActions' => [
        'admin' => ['*'], // Only admins can manage permissions
        // All other roles inherit default: no access
    ],
    
    'validationRules' => [],
    'relationships' => ['roles_permissions', 'users_permissions'],
    'ui' => [
        'listFields' => ['action', 'model', 'description', 'is_route_permission', 'created_at'],
        'createFields' => ['action', 'model', 'description', 'allowed_roles', 'is_route_permission', 'route_pattern'],
    ],
];
```

13. **Enhance Users Model for Automatic Role Assignment**
   - Update Users model to automatically assign roles based on user_type field
   - Implement role assignment logic in create() and update() methods
   - Use ModelBase relationship methods to link users to roles

**Code Example: Users Model Enhancement**

```php
// In src/Models/users/Users.php - Add automatic role assignment

/**
 * Create a new user record with automatic role assignment
 */
public function create(): bool
{
    // First create the user record
    if (!parent::create()) {
        return false;
    }
    
    // Then assign role based on user_type field
    $this->assignRoleFromUserType();
    
    return true;
}

/**
 * Update user record and handle role changes
 */
public function update(): bool
{
    // Store current user_type to detect changes
    $oldUserType = $this->getOriginalValue('user_type');
    
    // Update the user record
    if (!parent::update()) {
        return false;
    }
    
    // If user_type changed, update role assignment
    $newUserType = $this->get('user_type');
    if ($oldUserType !== $newUserType) {
        $this->assignRoleFromUserType();
    }
    
    return true;
}

/**
 * Assign role to user based on user_type field value
 */
protected function assignRoleFromUserType(): void
{
    $userType = $this->get('user_type');
    
    if (empty($userType)) {
        $this->logger->debug('No user_type specified, skipping role assignment', [
            'user_id' => $this->get('id')
        ]);
        return;
    }
    
    try {
        // Find role by name using the same technique as AuthorizationService
        $role = $this->getRoleByName($userType);
        
        if (!$role) {
            $this->logger->warning('Role not found for user_type, skipping assignment', [
                'user_id' => $this->get('id'),
                'user_type' => $userType
            ]);
            return;
        }
        
        // Remove existing role assignments for this user
        $this->clearExistingRoles();
        
        // Add new role relationship
        if ($this->addRelation('users_roles', $role)) {
            $this->logger->info('Successfully assigned role to user', [
                'user_id' => $this->get('id'),
                'role_name' => $role->get('name'),
                'role_id' => $role->get('id')
            ]);
        } else {
            $this->logger->error('Failed to assign role to user', [
                'user_id' => $this->get('id'),
                'role_name' => $role->get('name'),
                'role_id' => $role->get('id')
            ]);
        }
        
    } catch (\Exception $e) {
        $this->logger->error('Error assigning role from user_type', [
            'user_id' => $this->get('id'),
            'user_type' => $userType,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get role by name (same technique as AuthorizationService)
 */
protected function getRoleByName(string $roleName): ?\Gravitycar\Models\ModelBase
{
    try {
        $rolesModel = $this->modelFactory->new('Roles');
        $roles = $rolesModel->find(['name' => $roleName]);
        
        if (!empty($roles)) {
            return $roles[0]; // Return first match
        }
        
        $this->logger->debug('Role not found by name', [
            'role_name' => $roleName
        ]);
        
        return null;
        
    } catch (\Exception $e) {
        $this->logger->error('Error retrieving role by name', [
            'role_name' => $roleName,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * Clear existing role assignments for this user
 */
protected function clearExistingRoles(): void
{
    try {
        // Get current user roles
        $currentRoles = $this->getRelatedModels('users_roles');
        
        // Remove each existing role relationship
        foreach ($currentRoles as $role) {
            $this->removeRelation('users_roles', $role);
        }
        
        $this->logger->debug('Cleared existing role assignments', [
            'user_id' => $this->get('id'),
            'cleared_roles_count' => count($currentRoles)
        ]);
        
    } catch (\Exception $e) {
        $this->logger->warning('Error clearing existing roles', [
            'user_id' => $this->get('id'),
            'error' => $e->getMessage()
        ]);
    }
}
```

14. **Update Existing Models**
   - Add `rolesAndActions` overrides to critical models (Users, Movies, etc.)
   - Test permission inheritance and override behavior
   - Validate permission records are created correctly

**Code Example: Model Metadata with rolesAndActions Overrides**
<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Models\users\Users;
use Gravitycar\Models\movies\Movies;
use Gravitycar\Contracts\MetadataEngineInterface;
use Monolog\Logger;

/**
 * Test ModelBase rolesAndActions functionality
 */
class ModelBaseRolesAndActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDefaultRolesAndActionsStructure()
    {
        // Create a mock model with no metadata overrides
        $mockMetadata = [
            'name' => 'TestModel',
            'fields' => []
            // No rolesAndActions override
        ];
        
        $model = $this->createMockModelWithMetadata($mockMetadata);
        
        $rolesAndActions = $model->getRolesAndActions();
        
        // Verify default structure
        $this->assertIsArray($rolesAndActions);
        $this->assertArrayHasKey('admin', $rolesAndActions);
        $this->assertArrayHasKey('manager', $rolesAndActions);
        $this->assertArrayHasKey('user', $rolesAndActions);
        $this->assertArrayHasKey('guest', $rolesAndActions);
        
        // Verify default actions
        $this->assertEquals(['*'], $rolesAndActions['admin']);
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['manager']);
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['user']);
        $this->assertEquals([], $rolesAndActions['guest']);
    }

    public function testPartialOverrideRolesAndActions()
    {
        // Test partial override - only modify 'user' and 'guest' roles
        $mockMetadata = [
            'name' => 'TestModel',
            'fields' => [],
            'rolesAndActions' => [
                'user' => ['list', 'read'], // Override: limit user access
                'guest' => ['list'] // Override: give guests some access
            ]
        ];
        
        $model = $this->createMockModelWithMetadata($mockMetadata);
        
        $rolesAndActions = $model->getRolesAndActions();
        
        // Verify admin and manager keep defaults
        $this->assertEquals(['*'], $rolesAndActions['admin']);
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['manager']);
        
        // Verify overrides applied
        $this->assertEquals(['list', 'read'], $rolesAndActions['user']);
        $this->assertEquals(['list'], $rolesAndActions['guest']);
    }

    public function testCompleteOverrideRolesAndActions()
    {
        // Test complete override - modify all roles
        $mockMetadata = [
            'name' => 'TestModel',
            'fields' => [],
            'rolesAndActions' => [
                'admin' => ['list', 'read', 'create', 'update', 'delete'], // Remove wildcard
                'manager' => ['list', 'read', 'update'], // Remove create/delete
                'user' => ['list', 'read'], // Remove write access
                'guest' => [], // Keep empty
                'editor' => ['list', 'read', 'create', 'update'] // Add new role
            ]
        ];
        
        $model = $this->createMockModelWithMetadata($mockMetadata);
        
        $rolesAndActions = $model->getRolesAndActions();
        
        // Verify all overrides applied
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['admin']);
        $this->assertEquals(['list', 'read', 'update'], $rolesAndActions['manager']);
        $this->assertEquals(['list', 'read'], $rolesAndActions['user']);
        $this->assertEquals([], $rolesAndActions['guest']);
        $this->assertEquals(['list', 'read', 'create', 'update'], $rolesAndActions['editor']);
    }

    public function testGetAllPossibleActions()
    {
        // Test with wildcard
        $mockMetadata = [
            'name' => 'TestModel',
            'fields' => [],
            'rolesAndActions' => [
                'admin' => ['*'],
                'user' => ['list', 'read']
            ]
        ];
        
        $model = $this->createMockModelWithMetadata($mockMetadata);
        $actions = $model->getAllPossibleActions();
        
        // Should return standard CRUD actions when wildcard present
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $actions);
        
        // Test without wildcard
        $mockMetadata = [
            'name' => 'TestModel',
            'fields' => [],
            'rolesAndActions' => [
                'manager' => ['list', 'create', 'update'],
                'user' => ['list', 'read']
            ]
        ];
        
        $model = $this->createMockModelWithMetadata($mockMetadata);
        $actions = $model->getAllPossibleActions();
        
        // Should return unique actions from all roles
        sort($actions); // Sort for consistent comparison
        $this->assertEquals(['create', 'list', 'read', 'update'], $actions);
    }

    public function testInvalidRolesAndActionsFormat()
    {
        // Test invalid format handling
        $mockMetadata = [
            'name' => 'TestModel',
            'fields' => [],
            'rolesAndActions' => [
                'user' => 'invalid_format' // Should be array, not string
            ]
        ];
        
        $model = $this->createMockModelWithMetadata($mockMetadata);
        
        // Should log warning and skip invalid role
        $rolesAndActions = $model->getRolesAndActions();
        
        // User role should be skipped, others should have defaults
        $this->assertEquals(['*'], $rolesAndActions['admin']);
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['manager']);
        // Invalid user role should be skipped, keeping default
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['user']);
    }

    /**
     * Helper method to create a mock model with specific metadata
     */
    protected function createMockModelWithMetadata(array $metadata): ModelBase
    {
        $mockLogger = $this->createMock(Logger::class);
        $mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        
        // Create anonymous class extending ModelBase for testing
        return new class($mockLogger, $mockMetadataEngine, $metadata) extends ModelBase {
            public function __construct($logger, $metadataEngine, $metadata) {
                $this->logger = $logger;
                $this->metadataEngine = $metadataEngine;
                $this->metadata = $metadata;
            }
        };
    }
}
```

13. **Integrate with setup.php Script**
   - Add PermissionsBuilder call to setup.php after existing components
   - Call permission rebuilding after MetadataEngine, APIRouteRegistry, and SchemaGenerator complete
   - Ensure proper error handling and logging

**Code Example: setup.php Integration**

```php
// In setup.php - Add after existing MetadataEngine, APIRouteRegistry, and SchemaGenerator calls

try {
    echo "Building permissions from metadata...\n";
    
    // Get PermissionsBuilder instance with proper dependencies
    $container = \Gravitycar\Core\ContainerConfig::getContainer();
    $permissionsBuilder = $container->get('permissions_builder');
    
    // Build all permissions (clears existing and rebuilds)
    $permissionsBuilder->buildAllPermissions();
    
    echo "✓ Permissions built successfully\n";
    
} catch (\Exception $e) {
    echo "✗ Permission building failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    // Log the error but don't fail the entire setup
    $logger = $container->get('logger');
    $logger->error('Permission building failed during setup', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo "Warning: Setup completed but permissions may not be current\n";
    echo "Run 'php setup.php' again or manually rebuild permissions\n";
}
```

### Phase 4: Testing and Documentation (2-3 hours)

15. **Comprehensive Testing**
   - Unit tests for all new classes and methods
   - Integration tests for end-to-end permission flows
   - Feature tests for API endpoint permission checking

16. **Documentation Updates**
   - Update API documentation with permission requirements
   - Create developer guide for model permission configuration
   - Document migration process for existing applications

### Phase 5: Performance Optimization (1-2 hours)

17. **Performance Testing and Optimization**
    - Profile permission check performance impact
    - Add database indexes for permission queries
    - Implement query optimization if needed

## Testing Strategy

### Unit Tests

1. **ModelBase Tests**
   - Default permissions structure validation
   - Metadata override merging logic
   - Edge cases for invalid metadata structures

2. **PermissionsBuilder Tests**
   - Permission record creation for various model configurations
   - Cleanup and rebuilding functionality
   - Error handling for invalid metadata

3. **AuthorizationService Tests**
   - Test new `hasPermission(array $route, Request $request, User $currentUser)` method signature
   - Test `determineAction()` helper method for both explicit RBACAction and HTTP method mapping
   - Test `determineComponent()` helper method for model extraction from request and route fallback  
   - Database permission lookup functionality via ModelFactory->new('Permissions')->find()
   - Backward compatibility with existing role-based checks

### Integration Tests

1. **Metadata-Permission Synchronization**
   - End-to-end metadata rebuild with permission creation
   - Override behavior verification
   - Cache consistency validation

2. **API Authorization Flow**
   - Route-based permission checking
   - HTTP method to action mapping
   - Authentication and authorization integration

### Feature Tests

1. **Complete RBAC Flow**
   - User role assignment
   - Model-specific permission configuration
   - API endpoint access control verification

2. **Override Behavior**
   - Model metadata permission overrides
   - Partial vs complete override scenarios
   - Multiple model configuration scenarios

## Documentation

### Developer Documentation

1. **Model Permission Configuration Guide**
   - How to define `rolesAndActions` in model metadata
   - Override syntax and behavior explanation
   - Best practices for permission design

2. **API Permission Reference**
   - Standard actions and their meanings
   - Permission requirement documentation for all endpoints
   - Role-based access examples

3. **Migration Guide**
   - Steps to upgrade existing Gravitycar applications
   - Permission migration considerations
   - Testing checklist for existing functionality

### User Documentation

1. **Administrator Guide**
   - Role and permission management via UI
   - Understanding action-based permissions
   - Troubleshooting permission issues

2. **API Documentation Updates**
   - Permission requirements for each endpoint
   - Authentication examples
   - Error response documentation

## Risks and Mitigations

### Technical Risks

1. **Performance Impact**
   - **Risk**: Database permission lookups may slow down API responses
   - **Mitigation**: 
     - Optimize database queries with proper indexes
     - Implement permission caching if needed
     - Profile and benchmark permission check performance

2. **Backward Compatibility**
   - **Risk**: Changes may break existing role-based authorization
   - **Mitigation**: 
     - Maintain existing `hasRole()` functionality
     - **BREAKING CHANGE**: `AuthorizationService::hasPermission()` method signature updated to `hasPermission(array $route, Request $request, User $currentUser)` - only used internally by Router, should not affect external code
     - Comprehensive regression testing
     - Gradual migration path for existing applications

3. **Metadata Complexity**
   - **Risk**: Complex override syntax may lead to configuration errors
   - **Mitigation**: 
     - Clear documentation and examples
     - Metadata validation during cache rebuild
     - Helpful error messages for invalid configurations

### Security Risks

1. **Privilege Escalation**
   - **Risk**: Permission override bugs could grant unauthorized access
   - **Mitigation**: 
     - Fail-secure principle in all permission checks
     - Comprehensive security testing
     - Code review for all permission-related changes

2. **Permission Bypass**
   - **Risk**: Router changes might inadvertently bypass permission checks
   - **Mitigation**: 
     - Mandatory permission checks for all authenticated routes
     - Integration testing for all API endpoints
     - Security audit of routing logic

### Operational Risks

1. **Migration Complexity**
   - **Risk**: Existing applications may have complex migration requirements
   - **Mitigation**: 
     - Detailed migration documentation
     - Support for both old and new permission systems during transition
     - Migration tools and scripts

2. **Permission Management Overhead**
   - **Risk**: Fine-grained permissions may increase administrative burden
   - **Mitigation**: 
     - Sensible default permissions for common use cases
     - UI tools for bulk permission management
     - Role templates for common scenarios

## Success Criteria

1. **Functional Completeness**
   - All models have configurable action-based permissions
   - Authorization service successfully checks model-action permissions
   - Router enforces permissions on all protected endpoints
   - Metadata override system works as specified

2. **Performance Acceptability**
   - Permission checks add <10ms to average API response time
   - Metadata rebuilding completes in reasonable time (<30 seconds for typical applications)
   - No significant impact on application startup time

3. **Security Assurance**
   - No unauthorized access possible through permission system
   - All permission checks fail securely on errors
   - Comprehensive audit trail for permission-related actions

4. **Maintainability**
   - All new code follows pure dependency injection principles
   - Comprehensive test coverage (>90% for new components)
   - Clear documentation for developers and administrators

5. **Backward Compatibility**
   - Existing applications continue to function without modification
   - Existing role-based checks continue to work
   - Migration path available for applications wanting to use new features

## Timeline

**Total Estimated Time: 11-16 hours**

- **Phase 1 (Core Infrastructure)**: 5-6 hours (includes permissions model field update and APIRouteRegistry enhancement)
- **Phase 2 (Authorization Enhancement)**: 2-3 hours  
- **Phase 3 (Model Metadata Integration and Setup.php)**: 2-3 hours (includes setup.php integration)
- **Phase 4 (Testing and Documentation)**: 2-3 hours
- **Phase 5 (Performance Optimization)**: 1-2 hours

## Dependencies

1. **Existing Components**
   - Stable `ModelBase` implementation
   - Working `AuthorizationService` with role checking
   - Existing `Permissions` and `Roles` models
   - Current setup.php script framework

2. **External Dependencies**
   - No new external library dependencies required
   - Existing PHPUnit testing framework
   - Current database schema with permissions and roles tables
   - Existing setup.php script structure

## Approval Requirements

- [ ] Architecture review by senior developer
- [ ] Security review of permission checking logic
- [ ] Performance impact assessment
- [ ] Documentation completeness verification
- [ ] Test coverage validation (minimum 90% for new components)