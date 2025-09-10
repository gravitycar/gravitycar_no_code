<?php

namespace Gravitycar\Services;

use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\ModelBase;
use Gravitycar\Utils\GuestUserManager;
use Monolog\Logger;

/**
 * CurrentUserProvider
 * 
 * Provides access to the current user context without ServiceLocator dependencies.
 * Always returns current authentication state (no stale user objects).
 * Handles different execution contexts appropriately.
 */
class CurrentUserProvider implements CurrentUserProviderInterface
{
    private Logger $logger;
    private AuthenticationService $authService;
    private ModelFactory $modelFactory;
    private ?GuestUserManager $guestUserManager;
    
    public function __construct(
        Logger $logger,
        AuthenticationService $authService,
        ModelFactory $modelFactory,
        ?GuestUserManager $guestUserManager = null
    ) {
        $this->logger = $logger;
        $this->authService = $authService;
        $this->modelFactory = $modelFactory;
        $this->guestUserManager = $guestUserManager;
    }

    /**
     * Get the current user (authenticated user or guest user fallback)
     * 
     * @return ModelBase|null The current user or null if no user context available
     */
    public function getCurrentUser(): ?ModelBase
    {
        try {
            // Check for authenticated user first
            $authenticatedUser = $this->getAuthenticatedUser();
            if ($authenticatedUser) {
                return $authenticatedUser;
            }
            
            // Fall back to guest user if no authentication
            return $this->getGuestUser();
        } catch (\Exception $e) {
            $this->logger->debug('Failed to get current user, falling back to guest', [
                'error' => $e->getMessage()
            ]);
            return $this->getGuestUser();
        }
    }

    /**
     * Get the current user ID for audit trails
     * 
     * @return string|null The current user ID, 'system' for non-authenticated contexts, or null if unavailable
     */
    public function getCurrentUserId(): ?string
    {
        $currentUser = $this->getCurrentUser();
        return $currentUser?->get('id') ?? 'system';
    }

    /**
     * Check if there is an authenticated user (not guest)
     * 
     * @return bool True if there is an authenticated user, false for guest or no user
     */
    public function hasAuthenticatedUser(): bool
    {
        try {
            return $this->getAuthenticatedUser() !== null;
        } catch (\Exception $e) {
            $this->logger->debug('Failed to check authenticated user', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get the authenticated user from the authentication service
     * 
     * @return ModelBase|null
     */
    private function getAuthenticatedUser(): ?ModelBase
    {
        // Get JWT token from request headers
        $token = $this->getAuthTokenFromRequest();
        
        if (!$token) {
            return null;
        }
        
        // Validate token and return user
        return $this->authService->validateJwtToken($token);
    }

    /**
     * Get guest user fallback
     * 
     * @return ModelBase|null
     */
    private function getGuestUser(): ?ModelBase
    {
        try {
            if (!$this->guestUserManager) {
                $this->guestUserManager = new GuestUserManager();
            }
            
            return $this->guestUserManager->getGuestUser();
        } catch (\Exception $e) {
            $this->logger->debug('Failed to get guest user', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract JWT token from request headers
     * 
     * @return string|null
     */
    private function getAuthTokenFromRequest(): ?string
    {
        // Check Authorization header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
                return $matches[1];
            }
        }
        
        // Check for token in cookies as fallback
        if (isset($_COOKIE['auth_token'])) {
            return $_COOKIE['auth_token'];
        }
        
        return null;
    }
}
