<?php

namespace Gravitycar\Services;

use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Models\ModelBase;
use Monolog\Logger;

/**
 * TestCurrentUserProvider
 * 
 * CurrentUserProvider implementation for unit tests.
 * Allows tests to configure specific user contexts or no user context.
 */
class TestCurrentUserProvider implements CurrentUserProviderInterface
{
    private Logger $logger;
    private ?ModelBase $testUser;
    private bool $hasAuthenticatedUser;
    
    public function __construct(
        Logger $logger,
        ?ModelBase $testUser = null,
        bool $hasAuthenticatedUser = false
    ) {
        $this->logger = $logger;
        $this->testUser = $testUser;
        $this->hasAuthenticatedUser = $hasAuthenticatedUser;
    }

    /**
     * Get the configured test user
     * 
     * @return ModelBase|null The configured test user or null
     */
    public function getCurrentUser(): ?ModelBase
    {
        return $this->testUser;
    }

    /**
     * Get the current user ID for audit trails
     * 
     * @return string|null The test user ID or 'system' if no test user
     */
    public function getCurrentUserId(): ?string
    {
        return $this->testUser?->get('id') ?? 'system';
    }

    /**
     * Check if test is configured with authenticated user
     * 
     * @return bool The configured authentication state
     */
    public function hasAuthenticatedUser(): bool
    {
        return $this->hasAuthenticatedUser;
    }

    /**
     * Configure the test user for this provider
     * 
     * @param ModelBase|null $user The user to use in tests
     * @param bool $isAuthenticated Whether this user should be considered authenticated
     */
    public function setTestUser(?ModelBase $user, bool $isAuthenticated = false): void
    {
        $this->testUser = $user;
        $this->hasAuthenticatedUser = $isAuthenticated;
    }
}
