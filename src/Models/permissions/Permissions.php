<?php

namespace Gravitycar\Models\permissions;

use Gravitycar\Models\ModelBase;

/**
 * Permissions Model
 * Manages permissions for authorization system
 */
class Permissions extends ModelBase
{
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
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
        $conn = $dbConnector->getConnection();
        $queryBuilder = $conn->createQueryBuilder();
        
        $queryBuilder
            ->select('r.*')
            ->from('roles', 'r')
            ->join('r', 'role_permissions', 'rp', 'r.id = rp.role_id')
            ->where('rp.permission_id = :permission_id')
            ->setParameter('permission_id', $this->get('id'));
            
        $result = $queryBuilder->executeQuery();
        
        return $result->fetchAllAssociative();
    }
    
    /**
     * Create standard CRUD permissions for a model
     */
    public static function createModelPermissions(string $modelName): array
    {
        $permissions = [];
        $actions = ['create', 'read', 'update', 'delete', 'list', 'restore'];
        
        foreach ($actions as $action) {
            $permission = new self();
            $permission->set('action', $action);
            $permission->set('model', $modelName);
            $permission->set('description', ucfirst($action) . ' ' . $modelName . ' records');
            
            if ($permission->create()) {
                $permissions[] = $permission;
            }
        }
        
        return $permissions;
    }
    
    /**
     * Parse allowed roles from JSON string
     */
    public function getAllowedRolesArray(): array
    {
        $allowedRoles = $this->get('allowed_roles');
        if (empty($allowedRoles)) {
            return [];
        }
        
        $decoded = json_decode($allowedRoles, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    /**
     * Set allowed roles as JSON string
     */
    public function setAllowedRoles(array $roles): void
    {
        $this->set('allowed_roles', json_encode($roles));
    }
}
