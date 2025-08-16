<?php

namespace Gravitycar\Models\google_oauth_tokens;

use Gravitycar\Models\ModelBase;

/**
 * GoogleOauthTokens Model
 * Manages Google OAuth tokens for users
 */
class GoogleOauthTokens extends ModelBase
{
    /**
     * Clean up expired Google tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
        $conn = $dbConnector->getConnection();
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
        $dbConnector = \Gravitycar\Core\ServiceLocator::getDatabaseConnector();
        $conn = $dbConnector->getConnection();
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
