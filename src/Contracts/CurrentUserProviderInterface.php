<?php

namespace Gravitycar\Contracts;

use Gravitycar\Models\ModelBase;

/**
 * CurrentUserProviderInterface
 * 
 * Provides access to the current user context without ServiceLocator dependencies.
 * This service handles authentication state, guest user fallbacks, and different
 * execution contexts (web, CLI, testing, etc.).
 */
interface CurrentUserProviderInterface
{
    /**
     * Get the current user (authenticated user or guest user fallback)
     * 
     * @return ModelBase|null The current user or null if no user context available
     */
    public function getCurrentUser(): ?ModelBase;

    /**
     * Get the current user ID for audit trails
     * 
     * @return string|null The current user ID, 'system' for non-authenticated contexts, or null if unavailable
     */
    public function getCurrentUserId(): ?string;

    /**
     * Check if there is an authenticated user (not guest)
     * 
     * @return bool True if there is an authenticated user, false for guest or no user
     */
    public function hasAuthenticatedUser(): bool;
}
