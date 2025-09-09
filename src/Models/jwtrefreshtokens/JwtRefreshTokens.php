<?php

namespace Gravitycar\Models\jwtrefreshtokens;

use Gravitycar\Models\ModelBase;

/**
 * JwtRefreshTokens Model
 * Manages JWT refresh tokens for user authentication
 */
class JwtRefreshTokens extends ModelBase
{
    /**
     * Clean up expired refresh tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $dbConnector = $this->getDatabaseConnector();
        $conn = $dbConnector->getConnection();
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
        $dbConnector = $this->getDatabaseConnector();
        $conn = $dbConnector->getConnection();
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
