# RBAC Developer Guide - Model Permission Configuration

## Overview

The Gravitycar Framework RBAC (Role-Based Access Control) system provides fine-grained action-based permissions at the model level. This guide explains how to configure and use the RBAC system for developers working with the framework.

## Core Concepts

### Roles and Actions

The RBAC system works with two main concepts:

1. **Roles**: Groups of users with similar permissions (e.g., admin, manager, user, guest)
2. **Actions**: Specific operations that can be performed (e.g., list, read, create, update, delete)

### Default Permissions Structure

Every model inherits a default permissions structure from `ModelBase`:

```php
protected array $rolesAndActions = [
    'admin' => ['*'], // Admin can perform all actions
    'manager' => ['list', 'read', 'create', 'update', 'delete'],
    'user' => ['list', 'read', 'create', 'update', 'delete'], 
    'guest' => [] // Guest has no default permissions
];
```

The `*` wildcard grants access to all standard CRUD actions: `list`, `read`, `create`, `update`, `delete`.

## Configuring Model Permissions

### Method 1: Metadata Override (Recommended)

Add a `rolesAndActions` section to your model's metadata file to override specific role permissions:

```php
<?php
// src/Models/users/users_metadata.php

return [
    'name' => 'Users',
    'table' => 'users',
    'fields' => [
        // ... your fields
    ],
    
    // Override default permissions for user management
    'rolesAndActions' => [
        'admin' => ['*'], // Admin keeps full access
        'manager' => ['list', 'read'], // Restricted management access
        'user' => ['read'], // Users can only read their own data
        'guest' => [] // Guests have no access to user data
    ],
    
    // ... rest of metadata
];
```

### Method 2: Class Override (Advanced)

Override the `$rolesAndActions` property directly in your model class:

```php
<?php
// src/Models/movies/Movies.php

namespace Gravitycar\Models\movies;
use Gravitycar\Models\ModelBase;

class Movies extends ModelBase {
    // Override default permissions
    protected array $rolesAndActions = [
        'admin' => ['*'],
        'manager' => ['list', 'read', 'create', 'update', 'delete'],
        'user' => ['list', 'read', 'create'], // Users can create movies
        'guest' => ['list', 'read'] // Guests can browse movies
    ];
    
    // ... your custom methods
}
```

## Permission Override Behavior

### Partial Override

Only specify roles you want to modify. Unspecified roles keep their default permissions:

```php
'rolesAndActions' => [
    'user' => ['list', 'read'], // Only modify user permissions
    'guest' => ['list'] // Only modify guest permissions
    // admin and manager keep defaults
]
```

### Complete Override

Specify all roles to completely replace the default structure:

```php
'rolesAndActions' => [
    'admin' => ['*'],
    'editor' => ['list', 'read', 'create', 'update'], // New role
    'viewer' => ['list', 'read'], // New role
    'guest' => []
]
```

### Custom Actions

Define custom actions beyond the standard CRUD operations:

```php
'rolesAndActions' => [
    'admin' => ['*'],
    'manager' => ['list', 'read', 'approve', 'reject'], // Custom actions
    'user' => ['list', 'read', 'submit'], // Custom submit action
    'guest' => []
]
```

## Standard Actions Reference

| Action | Description | HTTP Method | API Endpoint |
|--------|-------------|-------------|--------------|
| `list` | View multiple records | GET | `/ModelName` |
| `read` | View single record | GET | `/ModelName/{id}` |
| `create` | Create new record | POST | `/ModelName` |
| `update` | Modify existing record | PUT | `/ModelName/{id}` |
| `delete` | Remove record | DELETE | `/ModelName/{id}` |
| `*` | All standard actions | Any | Any |

## API Route Permission Checking

### Automatic Permission Checking

The Router automatically checks permissions for all authenticated routes:

1. **HTTP Method Mapping**: `GET` → `read`, `POST` → `create`, `PUT` → `update`, `DELETE` → `delete`
2. **Model Extraction**: Model name extracted from URL or request parameters
3. **User Role Lookup**: Current user's roles retrieved from database
4. **Permission Verification**: Database lookup to verify role has required permission

### Explicit Action Specification

Override the default HTTP method mapping with explicit `RBACAction`:

```php
// In APIController registerRoutes() method
[
    'method' => 'POST',
    'path' => '/Movies/{id}/approve',
    'apiClass' => self::class,
    'apiMethod' => 'approveMovie',
    'parameterNames' => ['modelName', 'id'],
    'RBACAction' => 'approve' // Explicit action instead of 'create'
]
```

## Database Schema

### Permissions Table

Permissions are stored in the `permissions` table with these key fields:

- `component`: Model name (e.g., "Users", "Movies") or controller class name
- `action`: Action name (e.g., "read", "create", "custom_action")
- Links to roles via `roles_permissions` many-to-many relationship

### Permission Building

Permissions are automatically built from metadata during system setup:

```bash
php setup.php  # Rebuilds all permissions from current metadata
```

### Manual Permission Building

Use the PermissionsBuilder service directly:

```php
$container = \Gravitycar\Core\ContainerConfig::getContainer();
$permissionsBuilder = $container->get('permissions_builder');

// Build permissions for specific model
$permissionsBuilder->buildPermissionsForModel('Users');

// Build permissions for all models
$permissionsBuilder->buildAllModelPermissions();

// Clear and rebuild all permissions
$permissionsBuilder->buildAllPermissions();
```

## User Role Assignment

### Automatic Role Assignment

Users are automatically assigned roles based on their `user_type` field:

```php
// When creating/updating users
$user = $modelFactory->new('Users');
$user->set('username', 'john_doe');
$user->set('email', 'john@example.com');
$user->set('user_type', 'manager'); // Automatically assigns 'manager' role
$user->save();
```

### Manual Role Assignment

Assign roles directly via the `users_roles` relationship:

```php
// Get user and role models
$user = $modelFactory->retrieve('Users', $userId);
$adminRole = $rolesModel->findByFields(['name' => 'admin'])[0];

// Add relationship
$user->addRelation('users_roles', $adminRole);
```

## Authorization Service Methods

### Check Specific Permission

```php
$authService = $container->get('authorization_service');

// Check if user has specific role
$hasRole = $authService->hasRole($user, 'admin');

// Check route-based permission
$hasPermission = $authService->hasPermissionForRoute($route, $request, $user);

// Get all user permissions (for debugging/admin interfaces)
$allPermissions = $authService->getUserAllPermissions($user);
```

### Permission Check Flow

1. Extract action from route (HTTP method mapping or explicit RBACAction)
2. Extract component/model from request
3. Get user's roles from `users_roles` relationship
4. Query permissions table for matching component + action
5. Check if any user role has the required permission

## Best Practices

### 1. Use Metadata Overrides

Prefer metadata file overrides over class property overrides for easier maintenance:

```php
// ✅ Good: metadata file override
'rolesAndActions' => [
    'user' => ['list', 'read']
]

// ❌ Less preferred: class override
protected array $rolesAndActions = [
    'user' => ['list', 'read']
];
```

### 2. Follow Principle of Least Privilege

Grant minimum necessary permissions:

```php
// ✅ Good: specific permissions
'rolesAndActions' => [
    'user' => ['read', 'update'], // Only what's needed
]

// ❌ Too broad: unnecessary permissions
'rolesAndActions' => [
    'user' => ['*'], // Too much access
]
```

### 3. Document Custom Actions

When using custom actions, document their purpose:

```php
'rolesAndActions' => [
    'manager' => ['list', 'read', 'approve', 'reject'], // approve/reject for content moderation
    'user' => ['list', 'read', 'submit'], // submit for content creation workflow
]
```

### 4. Test Permission Changes

Always test permission changes thoroughly:

```bash
# Run permission-related tests
php vendor/bin/phpunit Tests/Unit/Services/PermissionsBuilderTest.php
php vendor/bin/phpunit Tests/Integration/Services/RBACIntegrationTest.php
php vendor/bin/phpunit Tests/Feature/Api/ApiAuthorizationFeatureTest.php
```

### 5. Use Descriptive Role Names

Create roles with clear, meaningful names:

```php
'rolesAndActions' => [
    'content_moderator' => ['list', 'read', 'approve', 'reject'],
    'content_creator' => ['list', 'read', 'create', 'update'],
    'content_viewer' => ['list', 'read']
]
```

## Common Patterns

### Content Management System

```php
// For content models (articles, posts, etc.)
'rolesAndActions' => [
    'admin' => ['*'],
    'editor' => ['list', 'read', 'create', 'update', 'publish'],
    'author' => ['list', 'read', 'create', 'update'], // Own content only
    'moderator' => ['list', 'read', 'approve', 'reject'],
    'subscriber' => ['list', 'read']
]
```

### E-commerce System

```php
// For product models
'rolesAndActions' => [
    'admin' => ['*'],
    'inventory_manager' => ['list', 'read', 'create', 'update'],
    'sales_rep' => ['list', 'read'],
    'customer' => ['list', 'read']
]

// For order models  
'rolesAndActions' => [
    'admin' => ['*'],
    'order_manager' => ['list', 'read', 'update', 'fulfill', 'cancel'],
    'customer' => ['read'], // Own orders only
]
```

### User Management System

```php
// For user models
'rolesAndActions' => [
    'admin' => ['*'],
    'hr_manager' => ['list', 'read', 'create', 'update'],
    'team_lead' => ['list', 'read'], // Team members only
    'user' => ['read', 'update'] // Own profile only
]
```

## Troubleshooting

### Permission Denied Errors

1. **Check user roles**: Verify user has correct role assignment
2. **Check permissions**: Ensure permissions exist for model + action + role
3. **Rebuild permissions**: Run `php setup.php` to refresh permissions
4. **Check metadata**: Verify `rolesAndActions` configuration is correct

### Debug Permission Checking

Enable debug logging to see permission check details:

```php
// In model metadata or config
'app' => [
    'debug' => true // Enable debug logging
]
```

Check logs for permission check details:

```bash
tail -f logs/gravitycar.log | grep -i permission
```

### Common Issues

1. **Permissions not updating**: Run `php setup.php` after metadata changes
2. **User not assigned to role**: Check `users_roles` relationship
3. **Custom actions not working**: Ensure custom actions are listed in `rolesAndActions`
4. **Wildcard not working**: Verify `*` is used correctly in array format: `['*']`

## Migration from Legacy Authorization

### Updating Existing Code

Replace old authorization checks:

```php
// ❌ Old way
if ($user->get('user_type') === 'admin') {
    // admin logic
}

// ✅ New way
if ($authService->hasRole($user, 'admin')) {
    // admin logic
}
```

### Gradual Migration

1. **Phase 1**: Add `rolesAndActions` to metadata files
2. **Phase 2**: Run `php setup.php` to build permissions
3. **Phase 3**: Update authorization checks to use AuthorizationService
4. **Phase 4**: Remove old authorization logic

The RBAC system maintains backward compatibility, so old role checks continue to work during migration.

## Performance Considerations

### Permission Caching

- Model metadata is cached in `cache/metadata_cache.php`
- Permission records are stored in database for fast lookup
- User roles are cached during session

### Optimization Tips

1. **Minimize database queries**: Permission checks use optimized joins
2. **Use appropriate indexes**: Database indexes on permissions table
3. **Cache user permissions**: Consider session-level permission caching
4. **Batch permission checks**: Group multiple permission checks when possible

## Security Considerations

### Fail-Secure Principle

The system fails securely on errors:

```php
// Permission check failures default to denied access
try {
    return $authService->hasPermissionForRoute($route, $request, $user);
} catch (\Exception $e) {
    $this->logger->error('Permission check failed', ['error' => $e->getMessage()]);
    return false; // Deny access on error
}
```

### Audit Logging

All permission checks are logged for security auditing:

```php
$this->logger->info('Permission check', [
    'user_id' => $user->get('id'),
    'component' => $component,
    'action' => $action,
    'result' => $hasPermission
]);
```

### Input Validation

- Component names validated against existing models
- Action names validated against configured actions
- User roles validated against existing roles

## Testing Your RBAC Configuration

### Unit Tests

Test your model's permission configuration:

```php
public function testModelPermissions()
{
    $model = $this->modelFactory->new('MyModel');
    $rolesAndActions = $model->getRolesAndActions();
    
    // Test specific role permissions
    $this->assertArrayHasKey('admin', $rolesAndActions);
    $this->assertEquals(['*'], $rolesAndActions['admin']);
    
    // Test custom permissions
    $this->assertEquals(['list', 'read'], $rolesAndActions['user']);
}
```

### Integration Tests

Test permission building and authorization:

```php
public function testPermissionFlow()
{
    // Build permissions
    $this->permissionsBuilder->buildPermissionsForModel('MyModel');
    
    // Create test user
    $user = $this->createTestUser('user');
    
    // Test authorization
    $hasPermission = $this->authService->hasRole($user, 'user');
    $this->assertTrue($hasPermission);
}
```

### Feature Tests

Test API endpoint access control:

```php
public function testApiAccess()
{
    // Test authenticated request
    $response = $this->makeAuthenticatedRequest('GET', '/MyModel');
    $this->assertEquals(200, $response->getStatusCode());
    
    // Test unauthorized request
    $response = $this->makeUnauthenticatedRequest('DELETE', '/MyModel/123');
    $this->assertEquals(403, $response->getStatusCode());
}
```

This completes the developer guide for RBAC model permission configuration.