<?php
namespace Gravitycar\Services;

use Gravitycar\Contracts\UserContextInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Models\ModelBase;

/**
 * UserContext
 * Manages current user context for the application
 */
class UserContext implements UserContextInterface
{
    private CurrentUserProviderInterface $currentUserProvider;
    
    public function __construct(CurrentUserProviderInterface $currentUserProvider)
    {
        $this->currentUserProvider = $currentUserProvider;
    }
    
    /**
     * Get the current authenticated user
     * @return ModelBase|null The current user or null if not authenticated
     */
    public function getCurrentUser(): ?ModelBase
    {
        return $this->currentUserProvider->getCurrentUser();
    }
}
