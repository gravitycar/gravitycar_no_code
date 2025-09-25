<?php

namespace Gravitycar\Models\googleoauthtokens;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * GoogleOauthTokens Model
 * Manages Google OAuth tokens for users
 */
class GoogleOauthTokens extends ModelBase
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
     * Clean up expired Google tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $conn = $this->databaseConnector->getConnection();
        $queryBuilder = $conn->createQueryBuilder();
        
        $queryBuilder
            ->delete($this->getTableName())
            ->where('token_expires_at < NOW()');
            
        $result = $queryBuilder->executeStatement();
        
        return $result;
    }
    
    /**
     * Find active token for user
     */
    public function findActiveTokenForUser(int $userId): ?self
    {
        $tokens = $this->find([
            'user_id' => $userId,
            'revoked_at' => null
        ], [], [
            'orderBy' => ['created_at' => 'DESC'],
            'limit' => 1
        ]);
        
        return !empty($tokens) ? $tokens[0] : null;
    }
    
    /**
     * Revoke all tokens for a specific user
     */
    public function revokeUserTokens(int $userId): int
    {
        $conn = $this->databaseConnector->getConnection();
        $queryBuilder = $conn->createQueryBuilder();
        
        $queryBuilder
            ->update($this->getTableName())
            ->set('revoked_at', 'NOW()')
            ->where('user_id = :user_id')
            ->andWhere('revoked_at IS NULL')
            ->setParameter('user_id', $userId);
            
        $result = $queryBuilder->executeStatement();
        
        return $result;
    }
    
    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->get('token_expires_at');
        if (!$expiresAt) {
            return true;
        }
        
        return strtotime($expiresAt) < time();
    }
}
