<?php

namespace Gravitycar\Models\roles;

use Gravitycar\Models\ModelBase;

/**
 * Roles Model
 * Manages user roles for authorization
 */
class Roles extends ModelBase
{
    /**
     * Get default OAuth role
     */
    public static function getDefaultOAuthRole(): ?self
    {
        $role = new self();
        $roles = $role->find(['is_oauth_default' => true], [], ['limit' => 1]);
        
        return !empty($roles) ? $roles[0] : null;
    }
    
    /**
     * Get all permissions for this role
     */
    public function getPermissions(): array
    {
        $dbConnector = $this->getDatabaseConnector();
        $conn = $dbConnector->getConnection();
        $queryBuilder = $conn->createQueryBuilder();
        
        $queryBuilder
            ->select('p.*')
            ->from('permissions', 'p')
            ->join('p', 'role_permissions', 'rp', 'p.id = rp.permission_id')
            ->where('rp.role_id = :role_id')
            ->setParameter('role_id', $this->get('id'));
            
        $result = $queryBuilder->executeQuery();
        
        return $result->fetchAllAssociative();
    }
    
    /**
     * Add permission to this role
     */
    public function addPermission(int $permissionId): bool
    {
        $dbConnector = $this->getDatabaseConnector();
        $conn = $dbConnector->getConnection();
        
        // Check if permission is already assigned
        $queryBuilder = $conn->createQueryBuilder();
        $queryBuilder
            ->select('COUNT(*)')
            ->from('role_permissions')
            ->where('role_id = :role_id')
            ->andWhere('permission_id = :permission_id')
            ->setParameter('role_id', $this->get('id'))
            ->setParameter('permission_id', $permissionId);
            
        $result = $queryBuilder->executeQuery();
        $count = $result->fetchOne();
        
        if ($count > 0) {
            return true; // Already exists
        }
        
        // Insert new role-permission relationship
        $insertBuilder = $conn->createQueryBuilder();
        $insertBuilder
            ->insert('role_permissions')
            ->setValue('role_id', ':role_id')
            ->setValue('permission_id', ':permission_id')
            ->setParameter('role_id', $this->get('id'))
            ->setParameter('permission_id', $permissionId);
            
        $result = $insertBuilder->executeStatement();
        
        return $result > 0;
    }
}
