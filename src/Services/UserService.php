<?php

namespace Gravitycar\Services;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;

/**
 * UserService
 * Handles user management operations
 */
class UserService
{
    /**
     * Create new user with traditional registration
     */
    public function createUser(array $userData): \Gravitycar\Models\ModelBase
    {
        $logger = ServiceLocator::getLogger();
        
        try {
            $user = ModelFactory::new('Users');
            
            // Set user data
            foreach ($userData as $field => $value) {
                if ($user->hasField($field)) {
                    if ($field === 'password') {
                        // Hash password
                        $user->set($field, password_hash($value, PASSWORD_DEFAULT));
                    } else {
                        $user->set($field, $value);
                    }
                }
            }
            
            // Set defaults for new users
            $user->set('auth_provider', 'local');
            $user->set('is_active', true);
            $user->set('email_verified_at', date('Y-m-d H:i:s')); // Assume verified for manual creation
            
            if ($user->create()) {
                $logger->info('User created successfully', [
                    'user_id' => $user->get('id'),
                    'username' => $user->get('username'),
                    'email' => $user->get('email')
                ]);
                
                // Assign default role
                $this->assignDefaultRole($user);
                
                return $user;
            } else {
                throw new GCException('Failed to create user in database');
            }
            
        } catch (\Exception $e) {
            $logger->error('User creation failed', [
                'error' => $e->getMessage(),
                'user_data' => array_diff_key($userData, ['password' => '']) // Exclude password from logs
            ]);
            
            throw new GCException('User creation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }
    
    /**
     * Update existing user
     */
    public function updateUser(int $userId, array $userData): \Gravitycar\Models\ModelBase
    {
        $logger = ServiceLocator::getLogger();
        
        try {
            $user = ModelFactory::retrieve('Users', $userId);
            
            if (!$user) {
                throw new GCException('User not found');
            }
            
            // Update user data
            foreach ($userData as $field => $value) {
                if ($user->hasField($field) && $field !== 'id') {
                    if ($field === 'password' && !empty($value)) {
                        // Hash new password
                        $user->set($field, password_hash($value, PASSWORD_DEFAULT));
                    } else {
                        $user->set($field, $value);
                    }
                }
            }
            
            if ($user->update()) {
                $logger->info('User updated successfully', [
                    'user_id' => $userId,
                    'updated_fields' => array_keys($userData)
                ]);
                
                return $user;
            } else {
                throw new GCException('Failed to update user in database');
            }
            
        } catch (\Exception $e) {
            $logger->error('User update failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            throw new GCException('User update failed: ' . $e->getMessage(), [], 0, $e);
        }
    }
    
    /**
     * Get user by username and password (for traditional login)
     */
    public function getUserByCredentials(string $username, string $password): ?\Gravitycar\Models\ModelBase
    {
        $logger = ServiceLocator::getLogger();
        
        try {
            $user = ModelFactory::new('Users');
            $users = $user->find(['username' => $username], [], ['limit' => 1]);
            
            if (empty($users)) {
                $logger->debug('User not found by username', ['username' => $username]);
                return null;
            }
            
            $foundUser = $users[0];
            $storedPasswordHash = $foundUser->get('password');
            
            if (empty($storedPasswordHash)) {
                $logger->debug('User has no password (OAuth-only account)', [
                    'user_id' => $foundUser->get('id'),
                    'username' => $username
                ]);
                return null;
            }
            
            if (password_verify($password, $storedPasswordHash)) {
                $logger->debug('Password verification successful', [
                    'user_id' => $foundUser->get('id'),
                    'username' => $username
                ]);
                return $foundUser;
            } else {
                $logger->debug('Password verification failed', ['username' => $username]);
                return null;
            }
            
        } catch (\Exception $e) {
            $logger->error('Failed to get user by credentials', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Get user by ID
     */
    public function getUserById(int $userId): ?\Gravitycar\Models\ModelBase
    {
        try {
            return ModelFactory::retrieve('Users', $userId);
        } catch (\Exception $e) {
            ServiceLocator::getLogger()->error('Failed to get user by ID', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Find user by email address
     */
    public function findUserByEmail(string $email): ?\Gravitycar\Models\ModelBase
    {
        try {
            $user = ModelFactory::new('Users');
            $users = $user->find(['email' => $email], [], ['limit' => 1]);
            
            return !empty($users) ? $users[0] : null;
            
        } catch (\Exception $e) {
            ServiceLocator::getLogger()->error('Failed to find user by email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Find user by Google ID
     */
    public function findUserByGoogleId(string $googleId): ?\Gravitycar\Models\ModelBase
    {
        try {
            $user = ModelFactory::new('Users');
            $users = $user->find(['google_id' => $googleId], [], ['limit' => 1]);
            
            return !empty($users) ? $users[0] : null;
            
        } catch (\Exception $e) {
            ServiceLocator::getLogger()->error('Failed to find user by Google ID', [
                'google_id' => $googleId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Create user from Google OAuth profile
     */
    public function createUserFromGoogleProfile(array $googleProfile): \Gravitycar\Models\ModelBase
    {
        $logger = ServiceLocator::getLogger();
        
        try {
            $user = ModelFactory::new('Users');
            
            // Map Google profile to user fields
            $user->set('google_id', $googleProfile['id']);
            $user->set('email', $googleProfile['email']);
            $user->set('username', $googleProfile['email']); // Use email as username
            $user->set('first_name', $googleProfile['first_name'] ?? '');
            $user->set('last_name', $googleProfile['last_name'] ?? '');
            $user->set('auth_provider', 'google');
            $user->set('is_active', true);
            $user->set('profile_picture_url', $googleProfile['picture'] ?? '');
            $user->set('last_google_sync', date('Y-m-d H:i:s'));
            
            // Set email verification if Google says it's verified
            if ($googleProfile['email_verified']) {
                $user->set('email_verified_at', date('Y-m-d H:i:s'));
            }
            
            // Set default user type
            $config = ServiceLocator::getConfig();
            $defaultRole = $config->get('oauth.default_role', 'user');
            $user->set('user_type', $defaultRole);
            
            if ($user->create()) {
                $logger->info('User created from Google profile', [
                    'user_id' => $user->get('id'),
                    'google_id' => $googleProfile['id'],
                    'email' => $googleProfile['email']
                ]);
                
                // Assign default OAuth role
                $this->assignDefaultOAuthRole($user);
                
                return $user;
            } else {
                throw new GCException('Failed to create user from Google profile');
            }
            
        } catch (\Exception $e) {
            $logger->error('Failed to create user from Google profile', [
                'google_id' => $googleProfile['id'] ?? null,
                'email' => $googleProfile['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            
            throw new GCException('Failed to create Google user: ' . $e->getMessage(), [], 0, $e);
        }
    }
    
    /**
     * Sync user profile with Google data
     */
    public function syncUserWithGoogleProfile(\Gravitycar\Models\ModelBase $user, array $googleProfile): \Gravitycar\Models\ModelBase
    {
        $logger = ServiceLocator::getLogger();
        
        try {
            // Update profile fields from Google
            $fieldsToSync = [
                'first_name' => $googleProfile['first_name'] ?? null,
                'last_name' => $googleProfile['last_name'] ?? null,
                'profile_picture_url' => $googleProfile['picture'] ?? null,
            ];
            
            $updated = false;
            foreach ($fieldsToSync as $field => $value) {
                if ($value && $user->get($field) !== $value) {
                    $user->set($field, $value);
                    $updated = true;
                }
            }
            
            // Update email verification status
            if ($googleProfile['email_verified'] && !$user->get('email_verified_at')) {
                $user->set('email_verified_at', date('Y-m-d H:i:s'));
                $updated = true;
            }
            
            if ($updated) {
                $user->set('last_google_sync', date('Y-m-d H:i:s'));
                $user->update();
                
                $logger->info('User profile synced with Google', [
                    'user_id' => $user->get('id'),
                    'synced_fields' => array_keys(array_filter($fieldsToSync))
                ]);
            }
            
            return $user;
            
        } catch (\Exception $e) {
            $logger->error('Failed to sync user with Google profile', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
            
            // Return user unchanged if sync fails
            return $user;
        }
    }
    
    /**
     * Assign default role to new user
     */
    private function assignDefaultRole(\Gravitycar\Models\ModelBase $user): void
    {
        try {
            $config = ServiceLocator::getConfig();
            $defaultRoleName = $config->get('oauth.default_role', 'user');
            
            // Find the default role
            $roleModel = ModelFactory::new('Roles');
            $roles = $roleModel->find(['name' => $defaultRoleName], [], ['limit' => 1]);
            
            if (!empty($roles)) {
                $role = $roles[0];
                $this->assignUserRole($user, $role);
            }
            
        } catch (\Exception $e) {
            ServiceLocator::getLogger()->error('Failed to assign default role', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Assign default OAuth role to new OAuth user
     */
    private function assignDefaultOAuthRole(\Gravitycar\Models\ModelBase $user): void
    {
        try {
            // Find the OAuth default role
            $roleModel = ModelFactory::new('Roles');
            $roles = $roleModel->find(['is_oauth_default' => true], [], ['limit' => 1]);
            
            if (!empty($roles)) {
                $role = $roles[0];
                $this->assignUserRole($user, $role);
            } else {
                // Fallback to regular default role
                $this->assignDefaultRole($user);
            }
            
        } catch (\Exception $e) {
            ServiceLocator::getLogger()->error('Failed to assign default OAuth role', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Assign role to user
     */
    private function assignUserRole(\Gravitycar\Models\ModelBase $user, \Gravitycar\Models\ModelBase $role): void
    {
        try {
            $dbConnector = ServiceLocator::getDatabaseConnector();
            $conn = $dbConnector->getConnection();
            
            // Check if role is already assigned
            $queryBuilder = $conn->createQueryBuilder();
            $queryBuilder
                ->select('COUNT(*)')
                ->from('user_roles')
                ->where('user_id = :user_id')
                ->andWhere('role_id = :role_id')
                ->setParameter('user_id', $user->get('id'))
                ->setParameter('role_id', $role->get('id'));
                
            $result = $queryBuilder->executeQuery();
            $count = $result->fetchOne();
            
            if ($count > 0) {
                return; // Already assigned
            }
            
            // Insert user-role relationship
            $insertBuilder = $conn->createQueryBuilder();
            $insertBuilder
                ->insert('user_roles')
                ->setValue('user_id', ':user_id')
                ->setValue('role_id', ':role_id')
                ->setValue('assigned_at', 'NOW()')
                ->setParameter('user_id', $user->get('id'))
                ->setParameter('role_id', $role->get('id'));
                
            $insertBuilder->executeStatement();
            
            ServiceLocator::getLogger()->info('Role assigned to user', [
                'user_id' => $user->get('id'),
                'role_id' => $role->get('id'),
                'role_name' => $role->get('name')
            ]);
            
        } catch (\Exception $e) {
            ServiceLocator::getLogger()->error('Failed to assign role to user', [
                'user_id' => $user->get('id'),
                'role_id' => $role->get('id'),
                'error' => $e->getMessage()
            ]);
        }
    }
}
