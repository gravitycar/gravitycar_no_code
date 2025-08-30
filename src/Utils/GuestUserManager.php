<?php

namespace Gravitycar\Utils;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Guest User Manager
 * 
 * Handles creation and retrieval of the guest user account for unauthenticated
 * users in parts of the framework that don't require authentication.
 */
class GuestUserManager
{
    private const GUEST_EMAIL = 'guest@gravitycar.com';
    private const GUEST_USERNAME = 'guest@gravitycar.com';
    
    private Logger $logger;
    private static ?ModelBase $cachedGuestUser = null;
    
    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Get the guest user, creating it if it doesn't exist
     * 
     * @return ModelBase The guest user model instance
     * @throws GCException If guest user cannot be created or retrieved
     */
    public function getGuestUser(): ModelBase
    {
        // Return cached guest user if available
        if (self::$cachedGuestUser !== null) {
            return self::$cachedGuestUser;
        }
        
        try {
            // Try to find existing guest user
            $guestUser = $this->findExistingGuestUser();
            
            if ($guestUser) {
                $this->logger->debug('Found existing guest user', [
                    'guest_user_id' => $guestUser->get('id'),
                    'email' => $guestUser->get('email')
                ]);
                self::$cachedGuestUser = $guestUser;
                return $guestUser;
            }
            
            // Create new guest user if none exists
            $guestUser = $this->createGuestUser();
            
            $this->logger->info('Created new guest user', [
                'guest_user_id' => $guestUser->get('id'),
                'email' => $guestUser->get('email')
            ]);
            
            self::$cachedGuestUser = $guestUser;
            return $guestUser;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get or create guest user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new GCException('Guest user service unavailable: ' . $e->getMessage(), [
                'original_error' => $e->getMessage()
            ], 0, $e);
        }
    }
    
    /**
     * Find existing guest user by email
     * 
     * @return ModelBase|null The guest user if found, null otherwise
     */
    private function findExistingGuestUser(): ?ModelBase
    {
        try {
            $userModel = ModelFactory::new('Users');
            $guestUsers = $userModel->find(['email' => self::GUEST_EMAIL], [], ['limit' => 1]);
            
            if (!empty($guestUsers)) {
                return $guestUsers[0];
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->warning('Error searching for existing guest user', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Create a new guest user account
     * 
     * @return ModelBase The newly created guest user
     * @throws GCException If guest user creation fails
     */
    private function createGuestUser(): ModelBase
    {
        try {
            $guestUser = ModelFactory::new('Users');
            
            // Set guest user fields
            $guestUser->set('username', self::GUEST_USERNAME);
            $guestUser->set('email', self::GUEST_EMAIL);
            $guestUser->set('password', $this->generateSecureGuestPassword());
            $guestUser->set('first_name', 'Guest');
            $guestUser->set('last_name', 'User');
            $guestUser->set('auth_provider', 'system');
            $guestUser->set('is_active', true);
            $guestUser->set('last_login_method', 'guest');
            
            // Mark as system-created user
            if ($guestUser->hasField('is_system_user')) {
                $guestUser->set('is_system_user', true);
            }
            
            // Set email as verified for system user
            if ($guestUser->hasField('email_verified_at')) {
                $guestUser->set('email_verified_at', date('Y-m-d H:i:s'));
            }
            
            // Create the user record
            $created = $guestUser->create();
            
            if (!$created) {
                throw new GCException('Failed to create guest user record in database');
            }
            
            return $guestUser;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create guest user', [
                'error' => $e->getMessage(),
                'email' => self::GUEST_EMAIL
            ]);
            throw new GCException('Guest user creation failed: ' . $e->getMessage(), [
                'guest_email' => self::GUEST_EMAIL,
                'original_error' => $e->getMessage()
            ], 0, $e);
        }
    }
    
    /**
     * Generate a secure, complex password for the guest user
     * 
     * The guest user password should be extremely secure since it's a system account
     * that could potentially be targeted. We generate a long, complex password that
     * would be impossible for anyone to guess or brute force.
     * 
     * @return string Hashed password ready for database storage
     */
    private function generateSecureGuestPassword(): string
    {
        // Generate a very long, complex password (128 characters)
        $length = 128;
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        // Add some additional entropy with current timestamp and random data
        $password .= '_' . time() . '_' . bin2hex(random_bytes(16));
        
        // Hash the password for storage
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Check if a user is the guest user
     * 
     * @param ModelBase $user The user to check
     * @return bool True if the user is the guest user, false otherwise
     */
    public function isGuestUser(ModelBase $user): bool
    {
        return $user->get('email') === self::GUEST_EMAIL;
    }
    
    /**
     * Clear the cached guest user (useful for testing)
     */
    public static function clearCache(): void
    {
        self::$cachedGuestUser = null;
    }
    
    /**
     * Get the guest user email constant
     * 
     * @return string The guest user email
     */
    public static function getGuestEmail(): string
    {
        return self::GUEST_EMAIL;
    }
    
    /**
     * Get the guest user username constant
     * 
     * @return string The guest user username
     */
    public static function getGuestUsername(): string
    {
        return self::GUEST_USERNAME;
    }
}
