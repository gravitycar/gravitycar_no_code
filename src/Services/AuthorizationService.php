<?php

namespace Gravitycar\Services;

use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\UserContextInterface;
use Gravitycar\Api\Request;
use Gravitycar\Exceptions\NotFoundException;
use Psr\Log\LoggerInterface;

/**
 * AuthorizationService
 * Handles role-based access control and permission checking with pure dependency injection
 */
class AuthorizationService
{
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
     * Get all roles assigned to a user
     */
    public function getUserRoles(\Gravitycar\Models\ModelBase $user): array
    {
        try {
            // Use the proper relationship system instead of raw SQL
            // Find roles through the users_roles many-to-many relationship
            $rolesModel = $this->modelFactory->new('Roles');
            
            // Use relationship criteria with dot notation to find roles 
            // that are related to this user through the users_roles relationship            
            // Find roles using DatabaseConnector with relationship criteria
            return $rolesModel->find(['users_roles.users_id' => $user->get('id')]);
            
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
            // Use the proper relationship system instead of raw SQL
            // Find permissions through the roles_permissions many-to-many relationship
            $permissionsModel = $this->modelFactory->new('Permissions');
            
            // Use relationship criteria with dot notation to find permissions 
            // that are related to this role through the roles_permissions relationship
            $criteria = [
                'roles_permissions.roles_id' => $role->get('id'),
                'action' => $permission,
                'model' => $model
            ];
            
            // Find permissions using DatabaseConnector with relationship criteria
            $permissionRows = $this->databaseConnector->find($permissionsModel, $criteria);
            
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
            // Use the model's addRelation() method to handle relationship creation
            $success = $user->addRelation('users_roles', $role);
            
            if ($success) {
                $this->logger->info('Role assigned to user', [
                    'user_id' => $user->get('id'),
                    'role_id' => $role->get('id'),
                    'role_name' => $role->get('name')
                ]);
            } else {
                $this->logger->warning('Failed to assign role to user (relationship may already exist)', [
                    'user_id' => $user->get('id'),
                    'role_id' => $role->get('id'),
                    'role_name' => $role->get('name')
                ]);
            }
            
            return $success;
            
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
                
                // Use the model's addRelation() method to handle relationship creation
                $success = $role->addRelation('roles_permissions', $permissionInstance);
                
                if ($success) {
                    $this->logger->debug('Assigned permission to role', [
                        'role_name' => $roleName,
                        'permission' => $permission
                    ]);
                } else {
                    $this->logger->debug('Permission assignment skipped (relationship may already exist)', [
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
            
            throw $e;
        }
    }

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
                'RBACAction' => $route['RBACAction'],
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
        // First check if request has a valid model parameter
        if ($request->has('modelName') && !empty($request->get('modelName'))) {
            try {
                $this->modelFactory->new($request->get('modelName'));
            } catch (\Exception $e) {
                throw new NotFoundException('Model not found: ' . $request->get('modelName'));
            }

            $component = $request->get('modelName');
            $this->logger->debug('Using model from request as component', [
                'component' => $component,
                'route_path' => $route['path'] ?? 'unknown'
            ]);
            return $component;
        }
        
        // Fall back to apiClass from route
        $component = $request->getApiControllerClassName() ?: ($route['apiClass'] ?? 'Unknown');
        
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
    public function hasPermissionForRoute(array $route, Request $request, \Gravitycar\Models\ModelBase $currentUser): bool
    {
        try {
            // Use helper methods to determine action and component
            $action = $this->determineAction($route, $request);
            $component = $this->determineComponent($route, $request);
            
            $this->logger->debug('Checking enhanced user permission', [
                'user_id' => $currentUser->get('id'),
                'action' => $action,
                'component' => $component,
                'route_path' => $route['path'] ?? 'unknown'
            ]);
            
            // Get user roles
            $userRoles = $this->getUserRoles($currentUser);
            
            if (empty($userRoles)) {
                $this->logger->info('User has no roles assigned', [
                    'user_id' => $currentUser->get('id')
                ]);
                return false;
            }
            
            // Check permissions via database lookup
            foreach ($userRoles as $role) {
                if ($this->checkDatabasePermission($role, $component, $action)) {
                    $this->logger->debug('Enhanced permission granted via role', [
                        'user_id' => $currentUser->get('id'),
                        'role_id' => $role->get('id'),
                        'role_name' => $role->get('name'),
                        'action' => $action,
                        'component' => $component
                    ]);
                    return true;
                }
            }
            
            $this->logger->debug('Enhanced permission denied - no matching database permissions', [
                'user_id' => $currentUser->get('id'),
                'action' => $action,
                'component' => $component,
                'roles' => array_map(fn($role) => $role->get('name'), $userRoles)
            ]);
            
            return false;
        } catch (NotFoundException $e) {
            // Re-throw not found exceptions for proper handling
            throw $e;   
        } catch (\Exception $e) {
            $this->logger->error('Error checking enhanced user permission', [
                'user_id' => $currentUser->get('id'),
                'route_path' => $route['path'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fail securely - deny access on error
            return false;
        }
    }

    /**
     * Check if role has permission for component-action in database
     * 
     * @param \Gravitycar\Models\ModelBase $role The role to check
     * @param string $component The component name
     * @param string $action The action name
     * @return bool True if permission exists
     */
    protected function checkDatabasePermission(\Gravitycar\Models\ModelBase $role, string $component, string $action): bool
    {
        try {
            // Use ModelFactory to get Permissions model and search for permissions
            $permissionsModel = $this->modelFactory->new('Permissions');
            
            $this->logger->debug("Searching for permission to $action on $component for role {$role->get('name')}");
            // Find permissions that match component and action
            $permissions = $permissionsModel->find([
                'component' => $component,
                'action' => $action,
                'roles_permissions.roles_id' => $role->get('id') // Join condition
            ]);
            
            if (empty($permissions)) {
                $this->logger->warning('No permissions found for component-action', [
                    'component' => $component,
                    'action' => $action
                ]);
                return false;
            }
            $this->logger->debug("Found permission to $action on $component for role {$role->get('name')}", ['permission_id' => $permissions[0]->get('id')]);
            return true;            
        } catch (\Exception $e) {
            $this->logger->error('Error checking database permission', [
                'role_id' => $role->get('id'),
                'component' => $component,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            
            // Fail securely
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
            $allPermissions = [];
            
            foreach ($userRoles as $role) {
                $rolePermissions = $role->getRelatedModels('roles_permissions');
                
                foreach ($rolePermissions as $permission) {
                    $component = $permission->get('component');
                    $action = $permission->get('action');
                    
                    if (!isset($allPermissions[$component])) {
                        $allPermissions[$component] = [];
                    }
                    
                    if (!in_array($action, $allPermissions[$component])) {
                        $allPermissions[$component][] = $action;
                    }
                }
            }
            
            $this->logger->debug('Retrieved all user permissions', [
                'user_id' => $user->get('id'),
                'permissions_count' => count($allPermissions)
            ]);
            
            return $allPermissions;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user permissions', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
}
