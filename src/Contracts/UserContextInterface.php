<?php
namespace Gravitycar\Contracts;

use Gravitycar\Models\ModelBase;

/**
 * UserContextInterface
 * Provides access to the current authenticated user context
 */
interface UserContextInterface
{
    /**
     * Get the current authenticated user
     * @return ModelBase|null The current user or null if not authenticated
     */
    public function getCurrentUser(): ?ModelBase;
}
