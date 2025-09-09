<?php
namespace Gravitycar\Services;

use Gravitycar\Contracts\UserContextInterface;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Models\ModelBase;

/**
 * UserContext
 * Manages current user context for the application
 */
class UserContext implements UserContextInterface
{
    /**
     * Get the current authenticated user
     * @return ModelBase|null The current user or null if not authenticated
     */
    public function getCurrentUser(): ?ModelBase
    {
        return ServiceLocator::getCurrentUser();
    }
}
