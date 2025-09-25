# RBAC Migration Guide

## Overview

This guide helps developers migrate existing Gravitycar Framework applications to use the new RBAC (Role-Based Access Control) system with fine-grained action-based permissions.

## Migration Timeline

The RBAC system is designed for **zero-downtime migration** with full backward compatibility:

- **Phase 1**: Assessment and Planning (1-2 hours)
- **Phase 2**: Permission Configuration (2-4 hours)  
- **Phase 3**: Code Updates (4-8 hours)
- **Phase 4**: Testing and Validation (2-4 hours)
- **Phase 5**: Deployment and Monitoring (1-2 hours)

**Total Estimated Time: 10-20 hours** (varies by application complexity)

## Pre-Migration Assessment

### 1. Analyze Current Authorization

Inventory your current authorization patterns:

```bash
# Find old-style authorization checks
grep -r "user_type.*===" src/
grep -r "hasRole" src/
grep -r "admin\|manager\|user" src/ | grep -i auth
```

Document findings:
- Which models have custom authorization logic?
- What user types/roles are currently used?
- Are there any custom permission checks?

### 2. Identify User Types/Roles

Review your current user management:

```sql
-- Check existing user types
SELECT DISTINCT user_type FROM users;

-- Check existing roles (if using roles table)
SELECT name, description FROM roles;
```

Common patterns to look for:
- User type field values (`admin`, `manager`, `user`, `guest`)
- Custom role names
- Permission-like fields in user table

### 3. Review Model Security Requirements

For each model, identify:
- Who should be able to create/read/update/delete records?
- Are there workflow-based permissions needed?
- Do you need field-level security?
- Are there relationship-based permissions?

## Phase 1: Backup and Preparation

### 1. Create Backup

```bash
# Backup database
mysqldump -u username -p gravitycar_db > backup_pre_rbac_$(date +%Y%m%d_%H%M%S).sql

# Backup application code
tar -czf backup_app_$(date +%Y%m%d_%H%M%S).tar.gz src/ docs/

# Backup current metadata cache
cp cache/metadata_cache.php backup_metadata_cache_$(date +%Y%m%d_%H%M%S).php
```

### 2. Set Up Development Environment

```bash
# Create feature branch
git checkout -b feature/rbac_migration

# Ensure all dependencies are updated
composer install
```

### 3. Run Pre-Migration Tests

```bash
# Run existing tests to establish baseline
php vendor/bin/phpunit
```

Document any failing tests - these need to work after migration.

## Phase 2: Configure Model Permissions

### 1. Start with Restrictive Defaults

Create a model permission template based on your security requirements:

```php
// Template for highly secure models (users, roles, permissions)
'rolesAndActions' => [
    'admin' => ['*'],
    'manager' => ['list', 'read'],
    'user' => [],
    'guest' => []
]

// Template for content models (movies, articles, etc.)
'rolesAndActions' => [
    'admin' => ['*'],
    'manager' => ['list', 'read', 'create', 'update', 'delete'],
    'user' => ['list', 'read', 'create', 'update'], // Own content only
    'guest' => ['list', 'read']
]

// Template for public data models
'rolesAndActions' => [
    'admin' => ['*'],
    'manager' => ['list', 'read', 'create', 'update', 'delete'],
    'user' => ['list', 'read', 'create', 'update', 'delete'],
    'guest' => ['list', 'read']
]
```

### 2. Update Model Metadata Files

For each model, add `rolesAndActions` to its metadata:

```php
<?php
// Example: src/Models/users/users_metadata.php

return [
    'name' => 'Users',
    'table' => 'users',
    'fields' => [
        // ... existing fields
    ],
    
    // NEW: Add RBAC permissions
    'rolesAndActions' => [
        'admin' => ['*'], // Full access
        'manager' => ['list', 'read'], // Management oversight
        'user' => ['read'], // Own profile only
        'guest' => [] // No access to user data
    ],
    
    // ... rest of existing configuration
];
```

### 3. Prioritize Critical Models

Start with security-critical models:

1. **Users** - Highly restrictive
2. **Roles** - Admin only
3. **Permissions** - Admin only
4. **Payment/Financial** - Admin/manager only
5. **Content models** - Based on workflow needs

### 4. Handle Special Cases

#### Hierarchical Data

For models with owner relationships:

```php
'rolesAndActions' => [
    'admin' => ['*'],
    'manager' => ['list', 'read', 'create', 'update', 'delete'], // All records
    'user' => ['read', 'update'], // Own records only (enforced in controller)
    'guest' => []
]
```

#### Workflow Models

For approval/publishing workflows:

```php
'rolesAndActions' => [
    'admin' => ['*'],
    'editor' => ['list', 'read', 'create', 'update', 'submit'],
    'reviewer' => ['list', 'read', 'review', 'approve', 'reject'],
    'publisher' => ['list', 'read', 'publish', 'unpublish'],
    'author' => ['list', 'read', 'create', 'update'], // Own content
    'guest' => ['list', 'read'] // Published content only
]
```

## Phase 3: Build Initial Permissions

### 1. Run Metadata Cache Rebuild

```bash
# This will build permissions from your new metadata
php setup.php
```

Verify permissions were created:

```bash
# Check logs for permission building
tail -20 logs/gravitycar.log | grep -i permission

# Check database
mysql -u username -p -D gravitycar_db -e "SELECT COUNT(*) FROM permissions;"
mysql -u username -p -D gravitycar_db -e "SELECT component, action, COUNT(*) as role_count FROM permissions p JOIN roles_permissions rp ON p.id = rp.permission_id GROUP BY component, action ORDER BY component, action;"
```

### 2. Verify Permission Records

Create a verification script:

```php
<?php
// tmp/verify_permissions.php
chdir('../');
require_once 'vendor/autoload.php';

use Gravitycar\Core\ContainerConfig;

$container = ContainerConfig::getContainer();
$modelFactory = $container->get('model_factory');

// Check permissions for each model
$modelsToCheck = ['Users', 'Movies', 'Roles', 'Permissions'];

foreach ($modelsToCheck as $modelName) {
    echo "\n=== $modelName Permissions ===\n";
    
    $permissionsModel = $modelFactory->new('Permissions');
    $permissions = $permissionsModel->findByFields(['component' => $modelName]);
    
    foreach ($permissions as $permission) {
        $action = $permission->get('action');
        $roles = $permission->getRelated('roles_permissions');
        $roleNames = array_map(fn($role) => $role->get('name'), $roles);
        
        echo "Action: $action, Roles: " . implode(', ', $roleNames) . "\n";
    }
}
```

```bash
php tmp/verify_permissions.php
```

## Phase 4: Update Application Code

### 1. Replace Authorization Checks

#### Pattern 1: Simple User Type Checks

```php
// ❌ Before: Direct user_type checking
if ($user->get('user_type') === 'admin') {
    // admin-only logic
}

// ✅ After: Role-based checking
$authService = $container->get('authorization_service');
if ($authService->hasRole($user, 'admin')) {
    // admin-only logic
}
```

#### Pattern 2: Permission-Based Checks

```php
// ❌ Before: Manual permission logic
if (in_array($user->get('user_type'), ['admin', 'manager'])) {
    // Can modify users
}

// ✅ After: Action-based permission
$request = new Request(/* ... */);
$route = ['apiClass' => 'UserController'];
if ($authService->hasPermissionForRoute($route, $request, $user)) {
    // Can modify users
}
```

#### Pattern 3: Model-Specific Permissions

```php
// ❌ Before: Hardcoded logic
function canEditMovie($user, $movie) {
    return $user->get('user_type') === 'admin' || 
           $movie->get('created_by') === $user->get('id');
}

// ✅ After: RBAC with ownership logic in controller
function canEditMovie($user, $movie) {
    $authService = $container->get('authorization_service');
    
    // Check basic permission first
    if (!$authService->hasRole($user, ['admin', 'manager', 'user'])) {
        return false;
    }
    
    // Admin can edit anything
    if ($authService->hasRole($user, 'admin')) {
        return true;
    }
    
    // Others can only edit own content
    return $movie->get('created_by') === $user->get('id');
}
```

### 2. Update API Controllers

#### Add Permission Checks to Controllers

```php
<?php
// Example: src/Api/MovieController.php

class MovieController extends ApiControllerBase {
    
    public function update(Request $request): array {
        // Get current user
        $currentUser = $this->getCurrentUser($request);
        if (!$currentUser) {
            throw new UnauthorizedException('Authentication required');
        }
        
        // Check permissions
        $authService = $this->container->get('authorization_service');
        if (!$authService->hasPermissionForRoute($this->currentRoute, $request, $currentUser)) {
            throw new ForbiddenException('Insufficient permissions to update movies');
        }
        
        // Additional ownership check for non-admin users
        if (!$authService->hasRole($currentUser, 'admin')) {
            $movieId = $request->get('id');
            $movie = $this->modelFactory->retrieve('Movies', $movieId);
            
            if ($movie->get('created_by') !== $currentUser->get('id')) {
                throw new ForbiddenException('Can only update own movies');
            }
        }
        
        // Proceed with update logic
        return parent::update($request);
    }
}
```

### 3. Update Frontend Permission Checks

#### Update React Components

```javascript
// ❌ Before: Hardcoded user type checks
const canEditMovie = user.user_type === 'admin';

// ✅ After: Permission-based checks
const canEditMovie = user.permissions?.Movies?.includes('update') || 
                    user.roles?.includes('admin');
```

#### Update Permission Context

```javascript
// Create permission checking utilities
const usePermissions = () => {
    const { user } = useAuth();
    
    const hasRole = (role) => {
        return user?.roles?.includes(role) || false;
    };
    
    const hasPermission = (model, action) => {
        return user?.permissions?.[model]?.includes(action) || 
               hasRole('admin') || false;
    };
    
    return { hasRole, hasPermission };
};

// Use in components
const MovieList = () => {
    const { hasPermission } = usePermissions();
    const canCreateMovie = hasPermission('Movies', 'create');
    
    return (
        <div>
            {canCreateMovie && (
                <button onClick={createMovie}>Create Movie</button>
            )}
        </div>
    );
};
```

## Phase 5: Testing Strategy

### 1. Unit Testing

Test permission configurations:

```php
<?php
// Tests/Unit/Migration/PermissionMigrationTest.php

class PermissionMigrationTest extends TestCase {
    
    public function testUsersModelPermissions() {
        $container = ContainerConfig::getContainer();
        $modelFactory = $container->get('model_factory');
        
        $usersModel = $modelFactory->new('Users');
        $rolesAndActions = $usersModel->getRolesAndActions();
        
        // Test admin has full access
        $this->assertEquals(['*'], $rolesAndActions['admin']);
        
        // Test user has limited access
        $this->assertEquals(['read'], $rolesAndActions['user']);
        
        // Test guest has no access
        $this->assertEquals([], $rolesAndActions['guest']);
    }
    
    public function testPermissionRecordsExist() {
        $container = ContainerConfig::getContainer();
        $modelFactory = $container->get('model_factory');
        
        $permissionsModel = $modelFactory->new('Permissions');
        
        // Test Users permissions exist
        $userPermissions = $permissionsModel->findByFields(['component' => 'Users']);
        $this->assertGreaterThan(0, count($userPermissions));
        
        // Test all standard actions are covered
        $actions = array_map(fn($p) => $p->get('action'), $userPermissions);
        $expectedActions = ['list', 'read', 'create', 'update', 'delete'];
        
        foreach ($expectedActions as $action) {
            $this->assertContains($action, $actions, "Missing permission for action: $action");
        }
    }
}
```

### 2. Integration Testing

Test full authorization workflow:

```php
<?php
// Tests/Integration/Migration/AuthorizationWorkflowTest.php

class AuthorizationWorkflowTest extends TestCase {
    
    public function testUserCanOnlyAccessOwnRecords() {
        $container = ContainerConfig::getContainer();
        $modelFactory = $container->get('model_factory');
        $authService = $container->get('authorization_service');
        
        // Create test users
        $user1 = $this->createTestUser('user', 'user1@test.com');
        $user2 = $this->createTestUser('user', 'user2@test.com');
        
        // Create test movie owned by user1
        $movie = $modelFactory->new('Movies');
        $movie->set('name', 'Test Movie');
        $movie->set('created_by', $user1->get('id'));
        $movie->save();
        
        // Test user1 can access their movie
        $route = ['apiClass' => 'MovieController'];
        $request = $this->createMockRequest('GET', '/Movies/' . $movie->get('id'));
        
        $this->assertTrue($authService->hasPermissionForRoute($route, $request, $user1));
        
        // Additional ownership validation would be in controller
    }
    
    public function testAdminHasUnrestrictedAccess() {
        $container = ContainerConfig::getContainer();
        $authService = $container->get('authorization_service');
        
        $adminUser = $this->createTestUser('admin', 'admin@test.com');
        
        // Test admin can access any endpoint
        $routes = [
            ['apiClass' => 'UserController'],
            ['apiClass' => 'MovieController'],
            ['apiClass' => 'RoleController']
        ];
        
        foreach ($routes as $route) {
            $request = $this->createMockRequest('GET', '/');
            $hasPermission = $authService->hasPermissionForRoute($route, $request, $adminUser);
            
            $this->assertTrue($hasPermission, "Admin should have access to {$route['apiClass']}");
        }
    }
}
```

### 3. API Testing

Test endpoints with different user roles:

```php
<?php
// Tests/Feature/Migration/ApiPermissionTest.php

class ApiPermissionTest extends TestCase {
    
    public function testUsersEndpointPermissions() {
        // Test admin can list users
        $adminToken = $this->getAuthToken('admin', 'admin@test.com');
        $response = $this->makeAuthenticatedRequest('GET', '/Users', [], $adminToken);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Test regular user cannot list users
        $userToken = $this->getAuthToken('user', 'user@test.com');
        $response = $this->makeAuthenticatedRequest('GET', '/Users', [], $userToken);
        $this->assertEquals(403, $response->getStatusCode());
        
        // Test guest cannot access users at all
        $response = $this->makeRequest('GET', '/Users');
        $this->assertEquals(401, $response->getStatusCode());
    }
    
    public function testMoviesEndpointPermissions() {
        $userToken = $this->getAuthToken('user', 'user@test.com');
        
        // Test user can list movies (if configured)
        $response = $this->makeAuthenticatedRequest('GET', '/Movies', [], $userToken);
        $expectedStatus = $this->userCanListMovies() ? 200 : 403;
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        
        // Test user can create movies (if configured)
        $movieData = ['name' => 'Test Movie'];
        $response = $this->makeAuthenticatedRequest('POST', '/Movies', $movieData, $userToken);
        $expectedStatus = $this->userCanCreateMovies() ? 201 : 403;
        $this->assertEquals($expectedStatus, $response->getStatusCode());
    }
    
    private function userCanListMovies(): bool {
        return $this->getPermissionForRole('user', 'Movies', 'list');
    }
    
    private function getPermissionForRole(string $role, string $model, string $action): bool {
        $container = ContainerConfig::getContainer();
        $modelFactory = $container->get('model_factory');
        
        $testModel = $modelFactory->new($model);
        $rolesAndActions = $testModel->getRolesAndActions();
        
        return in_array('*', $rolesAndActions[$role] ?? []) || 
               in_array($action, $rolesAndActions[$role] ?? []);
    }
}
```

## Phase 6: Gradual Rollout

### 1. Feature Flag Strategy

Implement feature flags to gradually roll out RBAC:

```php
<?php
// src/Config/FeatureFlags.php

class FeatureFlags {
    public static function isRBACEnabled(): bool {
        return Config::get('features.rbac_enabled', false);
    }
    
    public static function useRBACForModel(string $model): bool {
        $enabledModels = Config::get('features.rbac_models', []);
        return in_array($model, $enabledModels);
    }
}

// Usage in controllers
if (FeatureFlags::isRBACEnabled() && FeatureFlags::useRBACForModel('Users')) {
    // Use new RBAC permission checking
    if (!$authService->hasPermissionForRoute($route, $request, $user)) {
        throw new ForbiddenException('Insufficient permissions');
    }
} else {
    // Fall back to legacy authorization
    if ($user->get('user_type') !== 'admin') {
        throw new ForbiddenException('Admin required');
    }
}
```

Configuration:
```php
// config.php
'features' => [
    'rbac_enabled' => true,
    'rbac_models' => ['Users', 'Roles'] // Start with critical models
]
```

### 2. Monitoring and Rollback

#### Set up Monitoring

```php
// Monitor permission denials
$this->logger->warning('RBAC permission denied', [
    'user_id' => $user->get('id'),
    'user_roles' => $authService->getUserRoles($user),
    'component' => $component,
    'action' => $action,
    'route' => $request->getPath(),
    'method' => $request->getMethod()
]);

// Monitor authorization errors
$this->logger->error('Authorization check failed', [
    'user_id' => $user->get('id'),
    'error' => $e->getMessage(),
    'component' => $component,
    'action' => $action
]);
```

#### Create Rollback Plan

```bash
# Rollback script: scripts/rollback_rbac.sh
#!/bin/bash

echo "Rolling back RBAC migration..."

# 1. Disable RBAC features
sed -i 's/rbac_enabled.*true/rbac_enabled" => false/' config.php

# 2. Restore metadata cache backup
cp backup_metadata_cache_*.php cache/metadata_cache.php

# 3. Remove RBAC-specific database records (optional)
mysql -u username -p gravitycar_db -e "TRUNCATE permissions;"
mysql -u username -p gravitycar_db -e "TRUNCATE roles_permissions;"

# 4. Restart services
sudo systemctl restart apache2

echo "RBAC rollback completed. Application using legacy authorization."
```

## Phase 7: Cleanup and Optimization

### 1. Remove Legacy Code

After successful migration, clean up old authorization code:

```bash
# Find and remove old patterns
grep -r "user_type.*===" src/ --include="*.php" > legacy_auth_patterns.txt

# Review each occurrence and replace with RBAC calls
```

### 2. Optimize Permission Queries

Add database indexes for performance:

```sql
-- Optimize permission lookups
CREATE INDEX idx_permissions_component_action ON permissions(component, action);
CREATE INDEX idx_roles_permissions_lookup ON roles_permissions(role_id, permission_id);
CREATE INDEX idx_users_roles_lookup ON users_roles(user_id, role_id);
```

### 3. Cache User Permissions

Implement user permission caching:

```php
<?php
// src/Services/UserPermissionCache.php

class UserPermissionCache {
    private array $cache = [];
    
    public function getUserPermissions(string $userId): array {
        if (!isset($this->cache[$userId])) {
            $this->cache[$userId] = $this->loadUserPermissions($userId);
        }
        return $this->cache[$userId];
    }
    
    public function clearUserCache(string $userId): void {
        unset($this->cache[$userId]);
    }
}
```

## Troubleshooting Common Issues

### Issue 1: Permissions Not Taking Effect

**Symptoms**: Changes to `rolesAndActions` metadata not reflected in API access

**Solution**:
```bash
# Rebuild permissions
php setup.php

# Verify permissions were created
mysql -u username -p -D gravitycar_db -e "SELECT component, action, COUNT(*) FROM permissions GROUP BY component, action;"
```

### Issue 2: Users Losing Access After Migration

**Symptoms**: Previously accessible features now return 403 Forbidden

**Diagnosis**:
```php
// tmp/debug_user_permissions.php
$container = ContainerConfig::getContainer();
$authService = $container->get('authorization_service');
$modelFactory = $container->get('model_factory');

$user = $modelFactory->retrieve('Users', 'user-id-here');
$permissions = $authService->getUserAllPermissions($user);

print_r($permissions);
```

**Solution**: 
1. Check user has correct roles assigned
2. Check roles have required permissions
3. Verify `rolesAndActions` metadata is correct

### Issue 3: API Tests Failing

**Symptoms**: API tests returning 403 instead of 200

**Solution**:
```php
// Update test setup to create users with proper roles
protected function createTestUser(string $role): ModelBase {
    $user = $this->modelFactory->new('Users');
    $user->set('username', 'test_' . $role);
    $user->set('email', $role . '@test.com');
    $user->set('user_type', $role);
    $user->save();
    
    // Ensure role assignment happens
    if (method_exists($user, 'assignRoleFromUserType')) {
        $user->assignRoleFromUserType();
    }
    
    return $user;
}
```

### Issue 4: Performance Degradation

**Symptoms**: API requests slower after RBAC implementation

**Investigation**:
```bash
# Check slow query log
sudo tail -f /var/log/mysql/mysql-slow.log

# Profile specific permission queries
```

**Solution**:
1. Add database indexes (see optimization section)
2. Implement permission caching
3. Optimize database queries

## Post-Migration Validation

### 1. Security Audit

- [ ] All sensitive endpoints require authentication
- [ ] Permission checks are in place for all CRUD operations  
- [ ] Default permissions follow principle of least privilege
- [ ] Admin-only operations are properly protected
- [ ] User data access is properly restricted

### 2. Functionality Verification

- [ ] All existing features work for authorized users
- [ ] Unauthorized access is properly denied
- [ ] Error messages are informative but not revealing sensitive info
- [ ] User experience is maintained for authorized operations

### 3. Performance Validation

- [ ] API response times are within acceptable limits
- [ ] Database query performance is optimized
- [ ] Memory usage is reasonable
- [ ] No new bottlenecks introduced

## Success Metrics

### Security Metrics
- Zero unauthorized access incidents
- All admin operations properly protected
- Audit trail for all permission changes
- No privilege escalation vulnerabilities

### Performance Metrics
- <10ms additional latency per request
- <1MB additional memory usage per request
- Database query count increase <20%
- No timeout errors under normal load

### User Experience Metrics
- No disruption to legitimate user workflows
- Clear error messages for permission denials
- Consistent behavior across all interfaces
- Fast permission check responses

## Long-term Maintenance

### 1. Regular Security Reviews

Schedule quarterly reviews:
- Audit user role assignments
- Review model permission configurations  
- Check for privilege escalation issues
- Validate new feature permissions

### 2. Permission Documentation

Maintain documentation:
- Update API documentation with permission requirements
- Document custom permission workflows
- Keep migration guide current
- Train team on RBAC concepts

### 3. Monitoring and Alerting

Set up monitoring:
- Alert on excessive permission denials
- Monitor for authorization failures
- Track permission performance metrics
- Log security-related events

This completes the comprehensive RBAC migration guide. The migration maintains backward compatibility while providing a clear path to enhanced security through fine-grained permissions.