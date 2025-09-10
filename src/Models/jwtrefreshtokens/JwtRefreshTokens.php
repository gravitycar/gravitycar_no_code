<?php

namespace Gravitycar\Models\jwtrefreshtokens;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * JwtRefreshTokens Model
 * Manages JWT refresh tokens for user authentication
 */
class JwtRefreshTokens extends ModelBase
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
     * Clean up expired refresh tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $conn = $this->databaseConnector->getConnection();
        $queryBuilder = $conn->createQueryBuilder();
        
        $queryBuilder
            ->delete($this->getTableName())
            ->where('expires_at < NOW()');
            
        $result = $queryBuilder->executeStatement();
        
        return $result;
    }
    
    /**
     * Revoke all tokens for a specific user
     */
    public function revokeUserTokens(int $userId): int
    {
        $conn = $this->databaseConnector->getConnection();
        $queryBuilder = $conn->createQueryBuilder();
        
        $queryBuilder
            ->delete($this->getTableName())
            ->where('user_id = :user_id')
            ->setParameter('user_id', $userId);
            
        $result = $queryBuilder->executeStatement();
        
        return $result;
    }
    
    /**
     * Find token by hash
     */
    public function findByTokenHash(string $tokenHash): ?self
    {
        $tokens = $this->find(['token_hash' => $tokenHash]);
        return !empty($tokens) ? $tokens[0] : null;
    }
    
    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->get('expires_at');
        if (!$expiresAt) {
            return true;
        }
        
        return strtotime($expiresAt) < time();
    }
}
