<?php

namespace Gravitycar\Services;

use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Models\ModelBase;
use Psr\Log\LoggerInterface;

/**
 * CLICurrentUserProvider
 * 
 * CurrentUserProvider implementation for command-line operations.
 * Always returns 'system' user context since CLI operations don't have
 * web authentication.
 */
class CLICurrentUserProvider implements CurrentUserProviderInterface
{
    private LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * CLI operations don't have user authentication context
     * 
     * @return ModelBase|null Always returns null for CLI context
     */
    public function getCurrentUser(): ?ModelBase
    {
        return null;
    }

    /**
     * Get the current user ID for audit trails
     * 
     * @return string Always returns 'system' for CLI operations
     */
    public function getCurrentUserId(): ?string
    {
        return 'system';
    }

    /**
     * CLI operations never have authenticated users
     * 
     * @return bool Always returns false
     */
    public function hasAuthenticatedUser(): bool
    {
        return false;
    }
}
