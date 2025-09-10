<?php

namespace Gravitycar\Models\roles;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * Roles Model
 * Manages user roles for authorization
 */
class Roles extends ModelBase
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
     * Get default OAuth role
     */
    public static function getDefaultOAuthRole(): ?self
    {
        // Use ContainerConfig to create properly injected model instance
        $role = \Gravitycar\Core\ContainerConfig::createModel(self::class);
        $roles = $role->find(['is_oauth_default' => true], [], ['limit' => 1]);
        
        return !empty($roles) ? $roles[0] : null;
    }
    
    /**
     * Get all permissions for this role
     */
    public function getPermissions(): array
    {
        $conn = $this->databaseConnector->getConnection();
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
        $conn = $this->databaseConnector->getConnection();
        
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
