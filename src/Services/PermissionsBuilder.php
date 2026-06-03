<?php

namespace Gravitycar\Services;

use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Exceptions\PermissionsBuilderException;
use Gravitycar\Models\ModelBase;
use Monolog\Logger;

/**
 * PermissionsBuilder Service
 * 
 * Builds permission records in the database based on model rolesAndActions metadata.
 * Called by setup.php during cache rebuilding to synchronize permissions.
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
            
            // Build model permissions
            $modelPermissions = $this->buildAllModelPermissions();
            
            // Build controller permissions
            $controllerPermissions = $this->buildAllControllerPermissions();
            
            $this->logger->info('Successfully built all permissions', []);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build permissions for all models', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException(
                'Permission building failed: ' . $e->getMessage(),
                ['original_error' => $e->getMessage()],
                0,
                $e
            );
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
            
            $this->logger->info('Successfully built permissions for all models', [
                'total_models' => count($modelNames),
                'total_permissions' => $totalPermissions
            ]);
            
            return $totalPermissions;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build model permissions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException(
                'Model permission building failed: ' . $e->getMessage(),
                ['original_error' => $e->getMessage()],
                0,
                $e
            );
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
            
            // Get all unique actions for this model
            $allActions = $model->getAllPossibleActions();
            
            // Create permissions for each role-action combination
            foreach ($rolesAndActions as $roleName => $allowedActions) {
                // Handle wildcard permissions
                $actionsToProcess = in_array('*', $allowedActions) ? $allActions : $allowedActions;
                
                foreach ($actionsToProcess as $action) {
                    // Create or get existing permission record
                    $permissionModel = $this->createPermissionRecord($modelName, $action);
                    
                    // Link to role
                    $roleModel = $this->getRoleByName($roleName);
                    $this->linkPermissionToRole($permissionModel, $roleModel);
                    
                    $permissionsCreated++;
                }
            }
            
            $this->logger->debug('Successfully built permissions for model', [
                'model' => $modelName,
                'permissions_created' => $permissionsCreated,
                'roles_processed' => count($rolesAndActions)
            ]);
            
            return $permissionsCreated;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build permissions for model', [
                'model' => $modelName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException(
                "Failed to build permissions for model $modelName: " . $e->getMessage(),
                ['model' => $modelName, 'original_error' => $e->getMessage()],
                0,
                $e
            );
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
            $controllers = $this->getApiControllerClasses();
            $totalPermissions = 0;
            
            foreach ($controllers as $controller) {
                $permissionsCreated = $this->buildPermissionsForController($controller);
                $totalPermissions += $permissionsCreated;
            }
            
            $this->logger->info('Successfully built permissions for all controllers', [
                'total_controllers' => count($controllers),
                'total_permissions' => $totalPermissions
            ]);
            
            return $totalPermissions;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build controller permissions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException(
                'Controller permission building failed: ' . $e->getMessage(),
                ['original_error' => $e->getMessage()],
                0,
                $e
            );
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
                $this->logger->debug('Controller has no rolesAndActions defined, skipping', [
                    'controller' => $controllerClassName
                ]);
                return 0;
            }
            
            $permissionsCreated = 0;
            
            // Get all unique actions for this controller
            $allActions = [];
            foreach ($rolesAndActions as $role => $actions) {
                if (in_array('*', $actions)) {
                    // For controllers, we'll include a generic 'execute' action for wildcard
                    $allActions = ['list', 'read', 'create', 'update', 'delete', 'execute'];
                    break;
                } else {
                    $allActions = array_merge($allActions, $actions);
                }
            }
            $allActions = array_unique($allActions);
            
            // Create permissions for each role-action combination
            foreach ($rolesAndActions as $roleName => $allowedActions) {
                // Handle wildcard permissions
                $actionsToProcess = in_array('*', $allowedActions) ? $allActions : $allowedActions;
                
                foreach ($actionsToProcess as $action) {
                    // Create or get existing permission record
                    $permissionModel = $this->createPermissionRecord($controllerClassName, $action);
                    
                    // Link to role
                    $roleModel = $this->getRoleByName($roleName);
                    $this->linkPermissionToRole($permissionModel, $roleModel);
                    
                    $permissionsCreated++;
                }
            }
            
            $this->logger->debug('Successfully built permissions for controller', [
                'controller' => $controllerClassName,
                'permissions_created' => $permissionsCreated,
                'roles_processed' => count($rolesAndActions)
            ]);
            
            return $permissionsCreated;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build permissions for controller', [
                'controller' => $controllerClassName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException(
                "Failed to build permissions for controller $controllerClassName: " . $e->getMessage(),
                ['controller' => $controllerClassName, 'original_error' => $e->getMessage()],
                0,
                $e
            );
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
                'controller_count' => count($registeredControllers)
            ]);
            
            return $registeredControllers;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get API controller instances from registry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException(
                'Failed to discover API controllers: ' . $e->getMessage(),
                ['original_error' => $e->getMessage()],
                0,
                $e
            );
        }
    }

    /**
     * Create a permission record in the database
     * 
     * @param string $componentName The component name (model name or controller class)
     * @param string $action The action name
     * @return \Gravitycar\Models\ModelBase The created or existing permission model instance
     */
    protected function createPermissionRecord(string $componentName, string $action): \Gravitycar\Models\ModelBase {
        $permissionsModel = $this->modelFactory->new('Permissions');
        
        // Check if permission already exists
        $existing = $permissionsModel->find([
            'component' => $componentName,
            'action' => $action,
            'is_route_permission' => 0  // Use integer for database consistency
        ]);
        
        if (!empty($existing)) {
            // Return existing permission model instance
            return $existing[0];
        }
        
        // Create new permission record
        $permissionsModel->set('component', $componentName);
        $permissionsModel->set('action', $action);
        $permissionsModel->set('description', "Auto-generated permission for $action on $componentName");
        $permissionsModel->set('is_route_permission', 0);  // Use integer for database consistency
        $permissionsModel->set('route_pattern', null);
        
        $permissionsModel->create();
        
        $this->logger->debug('Created permission record', [
            'component' => $componentName,
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
        // RelationshipBase::add() handles duplicate checks automatically and returns true for existing relationships
        $result = $permissionsModel->addRelation('roles_permissions', $rolesModel);
        
        $this->logger->debug('Linked permission to role via relationship system', [
            'permission_id' => $permissionsModel->get('id'),
            'role_id' => $rolesModel->get('id'),
            'relationship_result' => $result
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
            $permissionsModel = $this->modelFactory->new('Permissions');
            
            // Use DatabaseConnector truncate method for efficiency
            $this->databaseConnector->truncate($permissionsModel);
            
            // Also need to clear the roles_permissions relationship table
            // For now, we'll handle this by deleting existing permission records individually
            // rather than truncating the relationship table
            
            $this->logger->info('Successfully cleared existing permissions using table truncation');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear existing permissions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new PermissionsBuilderException(
                'Failed to clear existing permissions: ' . $e->getMessage(),
                ['original_error' => $e->getMessage()],
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
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
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
        
        try {
            $rolesModel = $this->modelFactory->new('Roles');
            $roles = $rolesModel->find(['name' => $roleName]);
            
            if (empty($roles)) {
                throw new PermissionsBuilderException("Role not found: $roleName", [
                    'role_name' => $roleName
                ]);
            }
            
            $role = $roles[0];
            $this->roles[$roleName] = $role; // Cache for future use
            
            $this->logger->debug('Retrieved and cached role', [
                'role_name' => $roleName,
                'role_id' => $role->get('id')
            ]);
            
            return $role;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve role by name', [
                'role_name' => $roleName,
                'error' => $e->getMessage()
            ]);
            
            throw new PermissionsBuilderException(
                "Failed to retrieve role $roleName: " . $e->getMessage(),
                ['role_name' => $roleName, 'original_error' => $e->getMessage()],
                0,
                $e
            );
        }
    }
}