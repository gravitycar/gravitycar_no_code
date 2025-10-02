<?php

namespace Gravitycar\Models\permissions;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * Permissions Model
 * Manages permissions for authorization system
 */
class Permissions extends ModelBase
{
    
    /**
     * Pure dependency injection constructor
     */
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

    /**
     * Find permission by action and model
     */
    public function findByActionAndModel(string $action, string $model = ''): ?self
    {
        $permissions = $this->find([
            'action' => $action,
            'model' => $model
        ], [], ['limit' => 1]);
        
        return !empty($permissions) ? $permissions[0] : null;
    }
    
    /**
     * Get all roles that have this permission
     */
    public function getRoles(): array
    {
        try {
            // Use the framework's relationship system to get related role records
            return $this->getRelated('roles_permissions');
        } 
        catch (\Exception $e) {
            $this->logger->error('Failed to get roles for permission', [
                'permission_id' => $this->get('id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [];
        }
    }
    
    /**
     * Create standard CRUD permissions for a model
     */
    public static function createModelPermissions(string $modelName): array
    {
        $permissions = [];
        $actions = ['create', 'read', 'update', 'delete', 'list', 'restore'];
        
        foreach ($actions as $action) {
            // Use ContainerConfig to create properly injected model instance
            $permission = \Gravitycar\Core\ContainerConfig::createModel(self::class);
            $permission->set('action', $action);
            $permission->set('model', $modelName);
            $permission->set('description', ucfirst($action) . ' ' . $modelName . ' records');
            
            if ($permission->create()) {
                $permissions[] = $permission;
            }
        }
        
        return $permissions;
    }
}
