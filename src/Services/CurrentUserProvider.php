<?php

namespace Gravitycar\Services;

use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Contracts\UserContextInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\ModelBase;
use Gravitycar\Utils\GuestUserManager;
use Gravitycar\Core\Config;
use Gravitycar\Exceptions\SessionExpiredException;
use Monolog\Logger;

/**
 * CurrentUserProvider
 * 
 * Provides access to the current user context without ServiceLocator dependencies.
 * Always returns current authentication state (no stale user objects).
 * Handles different execution contexts appropriately.
 * Implements inactivity-based session timeout.
 */
class CurrentUserProvider implements CurrentUserProviderInterface, UserContextInterface
{
    private Logger $logger;
    private AuthenticationService $authService;
    private ModelFactory $modelFactory;
    private Config $config;
    private DatabaseConnectorInterface $databaseConnector;
    private ?GuestUserManager $guestUserManager;
    
    public function __construct(
        Logger $logger,
        AuthenticationService $authService,
        ModelFactory $modelFactory,
        Config $config,
        DatabaseConnectorInterface $databaseConnector,
        ?GuestUserManager $guestUserManager = null
    ) {
        $this->logger = $logger;
        $this->authService = $authService;
        $this->modelFactory = $modelFactory;
        $this->config = $config;
        $this->databaseConnector = $databaseConnector;
        $this->guestUserManager = $guestUserManager;
    }

    /**
     * Get the current user (authenticated user or guest user fallback)
     * 
     * @return ModelBase|null The current user or null if no user context available
     * @throws SessionExpiredException When session expires due to inactivity
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
        } catch (SessionExpiredException $e) {
            // Re-throw session expiration exceptions so they can be handled properly
            throw $e;
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
     * @throws SessionExpiredException When session expires due to inactivity
     */
    private function getAuthenticatedUser(): ?ModelBase
    {
        // Get JWT token from request headers
        $token = $this->getAuthTokenFromRequest();
        
        if (!$token) {
            return null;
        }
        
        // Validate token and get user
        $user = $this->authService->validateJwtToken($token);
        
        if (!$user) {
            return null;
        }
        
        // Check inactivity timeout
        if (!$this->isWithinActivityWindow($user)) {
            $this->logger->info('User session expired due to inactivity', [
                'user_id' => $user->get('id'),
                'last_activity' => $user->get('last_activity')
            ]);
            
            throw new SessionExpiredException(
                'Your session has expired due to inactivity',
                ['code' => 'SESSION_EXPIRED']
            );
        }
        
        // Update last activity timestamp (debounce to avoid excessive writes)
        $this->updateLastActivity($user);
        
        return $user;
    }

    /**
     * Check if user's last activity is within allowed window
     * 
     * @param ModelBase $user
     * @return bool
     */
    private function isWithinActivityWindow(ModelBase $user): bool
    {
        $lastActivity = $user->get('last_activity');
        
        if (!$lastActivity) {
            // No last_activity recorded, allow access (backward compatibility)
            return true;
        }
        
        $lastActivityTime = strtotime($lastActivity);
        $currentTime = time();
        $inactivityTimeout = $this->config->get('auth.inactivity_timeout', 3600);
        
        $timeSinceActivity = $currentTime - $lastActivityTime;
        
        return $timeSinceActivity <= $inactivityTimeout;
    }

    /**
     * Update user's last activity timestamp with debouncing
     * Only update if last activity was more than configured seconds ago
     * 
     * Uses DatabaseConnector directly to bypass ModelBase audit trail and avoid
     * circular dependency (update → setAuditFieldsForUpdate → getCurrentUserId → infinite loop)
     * 
     * @param ModelBase $user
     * @return void
     */
    private function updateLastActivity(ModelBase $user): void
    {
        try {
            $lastActivity = $user->get('last_activity');
            $currentTime = time();
            $debounceInterval = $this->config->get('auth.activity_debounce', 60);
            
            // Debounce: only update if last update was > debounce interval ago
            if ($lastActivity) {
                $lastActivityTime = strtotime($lastActivity);
                if (($currentTime - $lastActivityTime) < $debounceInterval) {
                    // Too soon to update again
                    return;
                }
            }
            
            // Update last_activity field and persist directly via DatabaseConnector
            // This bypasses ModelBase::update() and its audit field logic
            $user->set('last_activity', date('Y-m-d H:i:s', $currentTime));
            $this->databaseConnector->update($user);
            
            $this->logger->debug('Updated user last_activity', [
                'user_id' => $user->get('id'),
                'last_activity' => $user->get('last_activity')
            ]);
            
        } catch (\Exception $e) {
            // Log but don't fail the request if activity update fails
            $this->logger->error('Failed to update last_activity', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
        }
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
