<?php

namespace Gravitycar\Services;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\UserContextInterface;
use Gravitycar\Services\UserContext;
use Monolog\Logger;

/**
 * AuthorizationService
 * Handles role-based access control and permission checking
 */
class AuthorizationService
{
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
        // Backward compatibility: use ServiceLocator if dependencies not provided
        $this->logger = $logger ?? $this->logger;
        $this->modelFactory = $modelFactory ?? ServiceLocator::getModelFactory();
        $this->databaseConnector = $databaseConnector ?? $this->databaseConnector;
        $this->userContext = $userContext ?? new UserContext();
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission, string $model = '', \Gravitycar\Models\ModelBase $user = null): bool
    {
        // Auto-resolve current user if not provided
        if ($user === null) {
            $user = $this->userContext->getCurrentUser();
            if (!$user) {
                $this->logger->debug('No current user found for permission check');
                return false;
            }
        }
        
        try {
            $this->logger->debug('Checking user permission', [
                'user_id' => $user->get('id'),
                'permission' => $permission,
                'model' => $model
            ]);
            
            // Get user roles
            $userRoles = $this->getUserRoles($user);
            
            if (empty($userRoles)) {
                $this->logger->debug('User has no roles assigned', [
                    'user_id' => $user->get('id')
                ]);
                return false;
            }
            
            // Check if any role has the required permission
            foreach ($userRoles as $role) {
                if ($this->roleHasPermission($role, $permission, $model)) {
                    $this->logger->debug('Permission granted via role', [
                        'user_id' => $user->get('id'),
                        'role_id' => $role->get('id'),
                        'role_name' => $role->get('name'),
                        'permission' => $permission,
                        'model' => $model
                    ]);
                    return true;
                }
            }
            
            $this->logger->debug('Permission denied - no matching role permissions', [
                'user_id' => $user->get('id'),
                'permission' => $permission,
                'model' => $model,
                'roles' => array_map(fn($role) => $role->get('name'), $userRoles)
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error('Error checking user permission', [
                'user_id' => $user->get('id'),
                'permission' => $permission,
                'model' => $model,
                'error' => $e->getMessage()
            ]);
            
            // Fail securely - deny access on error
            return false;
        }
    }
    
    /**
     * Check if user has a specific role
     */
    public function hasRole(\Gravitycar\Models\ModelBase $user, string $roleName): bool
    {
        return $this->hasAnyRole($user, [$roleName]);
    }
    
    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole(\Gravitycar\Models\ModelBase $user, array $requiredRoles): bool
    {
        try {
            $userRoles = $this->getUserRoles($user);
            $userRoleNames = array_map(fn($role) => $role->get('name'), $userRoles);
            
            $hasRole = !empty(array_intersect($userRoleNames, $requiredRoles));
            
            $this->logger->debug('Role check result', [
                'user_id' => $user->get('id'),
                'user_roles' => $userRoleNames,
                'required_roles' => $requiredRoles,
                'has_role' => $hasRole
            ]);
            
            return $hasRole;
            
        } catch (\Exception $e) {
            $this->logger->error('Error checking user roles', [
                'user_id' => $user->get('id'),
                'required_roles' => $requiredRoles,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Authorize request based on route configuration
     */
    public function authorizeByRoute(array $route): bool
    {
        try {
            // Get current user
            $currentUser = $this->userContext->getCurrentUser();
            
            // Check if route has permission configuration
            if (!isset($route['allowedRoles'])) {
                $this->logger->warning('Route has no permission configuration - access denied', [
                    'route' => $route['path'] ?? 'unknown',
                    'method' => $route['method'] ?? 'unknown'
                ]);
                return false;
            }
            
            $allowedRoles = $route['allowedRoles'];
            
            // Public routes (allow all)
            if (in_array('*', $allowedRoles) || in_array('all', $allowedRoles)) {
                $this->logger->debug('Public route access allowed', [
                    'route' => $route['path'] ?? 'unknown',
                    'method' => $route['method'] ?? 'unknown'
                ]);
                return true;
            }
            
            // Authenticated routes require a user
            if (!$currentUser) {
                $this->logger->info('Authentication required for route', [
                    'route' => $route['path'] ?? 'unknown',
                    'method' => $route['method'] ?? 'unknown',
                    'allowed_roles' => $allowedRoles
                ]);
                return false;
            }
            
            // Check if user has any of the required roles
            return $this->hasAnyRole($currentUser, $allowedRoles);
            
        } catch (\Exception $e) {
            $this->logger->error('Error in route authorization', [
                'route' => $route['path'] ?? 'unknown',
                'method' => $route['method'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get all roles assigned to a user
     */
    public function getUserRoles(\Gravitycar\Models\ModelBase $user): array
    {
        try {
            // Get user roles through many-to-many relationship
            $dbConnector = $this->databaseConnector;
            $connection = $dbConnector->getConnection();
            
            $sql = "
                SELECT r.* 
                FROM roles r 
                INNER JOIN users_roles ur ON r.id = ur.role_id 
                WHERE ur.user_id = ?
            ";
            
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeQuery([$user->get('id')]);
            $roleRows = $result->fetchAllAssociative();
            
            $roles = [];
            foreach ($roleRows as $roleRow) {
                $role = $this->modelFactory->new('Roles');
                $role->populateFromRow($roleRow);
                $roles[] = $role;
            }
            
            return $roles;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user roles', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Check if a role has a specific permission
     */
    public function roleHasPermission(\Gravitycar\Models\ModelBase $role, string $permission, string $model = ''): bool
    {
        try {
            // Get role permissions through many-to-many relationship
            $dbConnector = $this->databaseConnector;
            $connection = $dbConnector->getConnection();
            
            $sql = "
                SELECT p.* 
                FROM permissions p 
                INNER JOIN roles_permissions rp ON p.id = rp.permission_id 
                WHERE rp.role_id = ? AND p.action = ? AND p.model = ?
            ";
            
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeQuery([$role->get('id'), $permission, $model]);
            $permissionRows = $result->fetchAllAssociative();
            
            return !empty($permissionRows);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to check role permission', [
                'role_id' => $role->get('id'),
                'permission' => $permission,
                'model' => $model,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Assign role to user
     */
    public function assignRoleToUser(\Gravitycar\Models\ModelBase $user, \Gravitycar\Models\ModelBase $role): bool
    {
        try {
            $dbConnector = $this->databaseConnector;
            $connection = $dbConnector->getConnection();
            
            // Check if assignment already exists
            $stmt = $connection->prepare("SELECT id FROM users_roles WHERE user_id = ? AND role_id = ?");
            $result = $stmt->executeQuery([$user->get('id'), $role->get('id')]);
            $existingRows = $result->fetchAllAssociative();
            
            if (!empty($existingRows)) {
                $this->logger->debug('Role already assigned to user', [
                    'user_id' => $user->get('id'),
                    'role_id' => $role->get('id')
                ]);
                return true;
            }
            
            // Create new assignment
            $stmt = $connection->prepare("INSERT INTO users_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())");
            $stmt->executeStatement([$user->get('id'), $role->get('id')]);
            
            $this->logger->info('Role assigned to user', [
                'user_id' => $user->get('id'),
                'role_id' => $role->get('id'),
                'role_name' => $role->get('name')
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to assign role to user', [
                'user_id' => $user->get('id'),
                'role_id' => $role->get('id'),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get default role for OAuth users
     */
    public function getDefaultOAuthRole(): ?\Gravitycar\Models\ModelBase
    {
        try {
            $roleModel = $this->modelFactory->new('Roles');
            $roles = $roleModel->find(['is_oauth_default' => true]);
            
            return !empty($roles) ? $roles[0] : null;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get default OAuth role', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Initialize default roles and permissions
     */
    public function initializeDefaultRolesAndPermissions(): void
    {
        try {
            $this->logger->info('Initializing default roles and permissions');
            
            // Create default roles
            $this->createDefaultRoles();
            
            // Create default permissions
            $this->createDefaultPermissions();
            
            // Assign permissions to roles
            $this->assignDefaultPermissions();
            
            $this->logger->info('Default roles and permissions initialized successfully');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize default roles and permissions', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Create default roles
     */
    private function createDefaultRoles(): void
    {
        $defaultRoles = [
            ['name' => 'admin', 'description' => 'System Administrator', 'is_oauth_default' => false],
            ['name' => 'user', 'description' => 'Regular User', 'is_oauth_default' => true],
            ['name' => 'manager', 'description' => 'Manager', 'is_oauth_default' => false],
            ['name' => 'guest', 'description' => 'Guest User', 'is_oauth_default' => false]
        ];
        
        foreach ($defaultRoles as $roleData) {
            $roleModel = $this->modelFactory->new('Roles');
            $existing = $roleModel->find(['name' => $roleData['name']]);
            
            if (empty($existing)) {
                $roleModel->set('name', $roleData['name']);
                $roleModel->set('description', $roleData['description']);
                $roleModel->set('is_oauth_default', $roleData['is_oauth_default']);
                $roleModel->create();
                
                $this->logger->debug('Created default role', [
                    'role_name' => $roleData['name']
                ]);
            }
        }
    }
    
    /**
     * Create default permissions
     */
    private function createDefaultPermissions(): void
    {
        $defaultPermissions = [
            // Global permissions
            ['name' => 'system.admin', 'model' => '', 'description' => 'Full system administration'],
            ['name' => 'api.access', 'model' => '', 'description' => 'Basic API access'],
            
            // User model permissions
            ['name' => 'create', 'model' => 'Users', 'description' => 'Create new users'],
            ['name' => 'read', 'model' => 'Users', 'description' => 'View user profiles'],
            ['name' => 'update', 'model' => 'Users', 'description' => 'Update user profiles'],
            ['name' => 'delete', 'model' => 'Users', 'description' => 'Delete users'],
            ['name' => 'list', 'model' => 'Users', 'description' => 'List all users'],
            
            // Generic model permissions (for other models)
            ['name' => 'create', 'model' => '*', 'description' => 'Create any model'],
            ['name' => 'read', 'model' => '*', 'description' => 'Read any model'],
            ['name' => 'update', 'model' => '*', 'description' => 'Update any model'],
            ['name' => 'delete', 'model' => '*', 'description' => 'Delete any model'],
            ['name' => 'list', 'model' => '*', 'description' => 'List any model']
        ];
        
        foreach ($defaultPermissions as $permissionData) {
            $permissionModel = $this->modelFactory->new('Permissions');
            $existing = $permissionModel->find([
                'action' => $permissionData['name'],
                'model' => $permissionData['model']
            ]);
            
            if (empty($existing)) {
                $permissionModel->set('action', $permissionData['name']);
                $permissionModel->set('model', $permissionData['model']);
                $permissionModel->set('description', $permissionData['description']);
                $permissionModel->create();
                
                $this->logger->debug('Created default permission', [
                    'permission_name' => $permissionData['name'],
                    'model' => $permissionData['model']
                ]);
            }
        }
    }
    
    /**
     * Assign default permissions to roles
     */
    private function assignDefaultPermissions(): void
    {
        // Admin gets all permissions
        $this->assignPermissionsToRole('admin', [
            'system.admin',
            'api.access',
            'create:Users',
            'read:Users',
            'update:Users',
            'delete:Users',
            'list:Users',
            'create:*',
            'read:*',
            'update:*',
            'delete:*',
            'list:*'
        ]);
        
        // User gets basic permissions
        $this->assignPermissionsToRole('user', [
            'api.access',
            'read:Users',
            'update:Users'
        ]);
        
        // Manager gets extended permissions
        $this->assignPermissionsToRole('manager', [
            'api.access',
            'create:Users',
            'read:Users',
            'update:Users',
            'list:Users',
            'read:*',
            'update:*'
        ]);
    }
    
    /**
     * Assign permissions to a role
     */
    private function assignPermissionsToRole(string $roleName, array $permissions): void
    {
        try {
            $roleModel = $this->modelFactory->new('Roles');
            $roles = $roleModel->find(['name' => $roleName]);
            
            if (empty($roles)) {
                $this->logger->warning('Role not found for permission assignment', [
                    'role_name' => $roleName
                ]);
                return;
            }
            
            $role = $roles[0];
            $dbConnector = $this->databaseConnector;
            $connection = $dbConnector->getConnection();
            
            foreach ($permissions as $permission) {
                // Parse permission format (name:model or just name)
                $parts = explode(':', $permission);
                $permissionName = $parts[0];
                $model = $parts[1] ?? '';
                
                // Find permission
                $permissionModel = $this->modelFactory->new('Permissions');
                $permissionInstances = $permissionModel->find([
                    'action' => $permissionName,
                    'model' => $model
                ]);
                
                if (empty($permissionInstances)) {
                    $this->logger->warning('Permission not found for role assignment', [
                        'permission' => $permission,
                        'role_name' => $roleName
                    ]);
                    continue;
                }
                
                $permissionInstance = $permissionInstances[0];
                
                // Check if assignment already exists
                $stmt = $connection->prepare("SELECT id FROM roles_permissions WHERE role_id = ? AND permission_id = ?");
                $result = $stmt->executeQuery([$role->get('id'), $permissionInstance->get('id')]);
                $existing = $result->fetchAllAssociative();
                
                if (empty($existing)) {
                    // Create assignment
                    $stmt = $connection->prepare("INSERT INTO roles_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
                    $stmt->executeStatement([$role->get('id'), $permissionInstance->get('id')]);
                    
                    $this->logger->debug('Assigned permission to role', [
                        'role_name' => $roleName,
                        'permission' => $permission
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to assign permissions to role', [
                'role_name' => $roleName,
                'permissions' => $permissions,
                'error' => $e->getMessage()
            ]);
        }
    }
}
