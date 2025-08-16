<?php

namespace Gravitycar\Services;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Models\ModelBase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Monolog\Logger;

/**
 * AuthenticationService
 * Handles Google OAuth and traditional authentication with JWT token management
 */
class AuthenticationService
{
    private DatabaseConnector $database;
    private Logger $logger;
    private string $jwtSecret;
    private string $refreshSecret;
    private int $accessTokenLifetime;
    private int $refreshTokenLifetime;
    
    public function __construct(DatabaseConnector $database, Logger $logger)
    {
        $this->database = $database;
        $this->logger = $logger;
        
        // Get JWT configuration from environment
        $this->jwtSecret = $_ENV['JWT_SECRET_KEY'] ?? 'default-secret-change-in-production';
        $this->refreshSecret = $_ENV['JWT_REFRESH_SECRET'] ?? 'default-refresh-secret-change-in-production';
        $this->accessTokenLifetime = (int)($_ENV['JWT_ACCESS_TOKEN_LIFETIME'] ?? 3600); // 1 hour
        $this->refreshTokenLifetime = (int)($_ENV['JWT_REFRESH_TOKEN_LIFETIME'] ?? 2592000); // 30 days
    }
    
    /**
     * Authenticate user with Google OAuth token
     */
    public function authenticateWithGoogle(string $googleToken): ?array
    {
        try {
            $this->logger->info('Starting Google OAuth authentication');
            
            // Get Google OAuth service
            $googleOAuth = ServiceLocator::get(GoogleOAuthService::class);
            
            // Validate Google token and get user profile
            $userProfile = $googleOAuth->getUserProfile($googleToken);
            
            if (!$userProfile) {
                $this->logger->warning('Google token validation failed');
                return null;
            }
            
            // Find or create user
            $user = $this->findOrCreateGoogleUser($userProfile);
            
            if (!$user) {
                $this->logger->error('Failed to find or create Google user');
                return null;
            }
            
            // Generate JWT tokens
            $accessToken = $this->generateJwtToken($user);
            $refreshToken = $this->generateRefreshToken($user);
            
            // Store refresh token
            $this->storeRefreshToken($user, $refreshToken);
            
            $this->logger->info('Google OAuth authentication successful', [
                'user_id' => $user->get('id'),
                'email' => $user->get('email')
            ]);
            
            return [
                'user' => $this->formatUserData($user),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $this->accessTokenLifetime,
                'token_type' => 'Bearer'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Google OAuth authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Traditional username/password authentication
     */
    public function authenticateTraditional(string $username, string $password): ?array
    {
        try {
            $this->logger->info('Starting traditional authentication', ['username' => $username]);
            
            // Find user by username or email
            $user = $this->findUserByCredentials($username);
            
            if (!$user) {
                $this->logger->warning('User not found for traditional authentication', ['username' => $username]);
                return null;
            }
            
            // Verify password
            $passwordHash = $user->get('password_hash');
            if (!$passwordHash || !password_verify($password, $passwordHash)) {
                $this->logger->warning('Password verification failed', ['username' => $username]);
                return null;
            }
            
            // Generate JWT tokens
            $accessToken = $this->generateJwtToken($user);
            $refreshToken = $this->generateRefreshToken($user);
            
            // Store refresh token
            $this->storeRefreshToken($user, $refreshToken);
            
            // Update last login
            $user->set('last_login_method', 'traditional');
            $user->update();
            
            $this->logger->info('Traditional authentication successful', [
                'user_id' => $user->get('id'),
                'username' => $username
            ]);
            
            return [
                'user' => $this->formatUserData($user),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $this->accessTokenLifetime,
                'token_type' => 'Bearer'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Traditional authentication failed', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Generate JWT access token
     */
    public function generateJwtToken(ModelBase $user): string
    {
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'gravitycar',
            'aud' => $_ENV['APP_URL'] ?? 'gravitycar',
            'iat' => time(),
            'exp' => time() + $this->accessTokenLifetime,
            'user_id' => $user->get('id'),
            'email' => $user->get('email'),
            'auth_provider' => $user->get('auth_provider')
        ];
        
        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }
    
    /**
     * Generate refresh token
     */
    public function generateRefreshToken(ModelBase $user): string
    {
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'gravitycar',
            'aud' => $_ENV['APP_URL'] ?? 'gravitycar',
            'iat' => time(),
            'exp' => time() + $this->refreshTokenLifetime,
            'user_id' => $user->get('id'),
            'type' => 'refresh'
        ];
        
        return JWT::encode($payload, $this->refreshSecret, 'HS256');
    }
    
    /**
     * Validate JWT token and return user
     */
    public function validateJwtToken(string $token): ?ModelBase
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            if (!isset($decoded->user_id)) {
                return null;
            }
            
            // Load and return user
            $user = ModelFactory::retrieve('Users', $decoded->user_id);
            
            if (!$user || !$user->get('is_active')) {
                return null;
            }
            
            return $user;
            
        } catch (\Exception $e) {
            $this->logger->debug('JWT token validation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Refresh JWT token using refresh token
     */
    public function refreshJwtToken(string $refreshToken): ?array
    {
        try {
            $decoded = JWT::decode($refreshToken, new Key($this->refreshSecret, 'HS256'));
            
            if (!isset($decoded->user_id) || $decoded->type !== 'refresh') {
                return null;
            }
            
            // Verify refresh token exists in database
            if (!$this->verifyRefreshToken($decoded->user_id, $refreshToken)) {
                return null;
            }
            
            // Load user
            $user = ModelFactory::retrieve('Users', $decoded->user_id);
            if (!$user || !$user->get('is_active')) {
                return null;
            }
            
            // Generate new tokens
            $newAccessToken = $this->generateJwtToken($user);
            $newRefreshToken = $this->generateRefreshToken($user);
            
            // Update stored refresh token
            $this->updateRefreshToken($user, $refreshToken, $newRefreshToken);
            
            return [
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'expires_in' => $this->accessTokenLifetime,
                'token_type' => 'Bearer'
            ];
            
        } catch (\Exception $e) {
            $this->logger->warning('Token refresh failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Revoke refresh token (logout)
     */
    public function revokeRefreshToken(string $refreshToken): bool
    {
        try {
            $token = ModelFactory::new('JwtRefreshTokens');
            $tokens = $token->find(['token_hash' => hash('sha256', $refreshToken)]);
            
            if (empty($tokens)) {
                return false;
            }
            
            $foundToken = $tokens[0];
            $foundToken->set('is_revoked', true);
            return $foundToken->update();
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to revoke refresh token', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Find or create user from Google profile
     */
    private function findOrCreateGoogleUser(array $userProfile): ?ModelBase
    {
        // Try to find existing user by Google ID
        $user = ModelFactory::new('Users');
        $existingUsers = $user->find(['google_id' => $userProfile['id']]);
        
        if (!empty($existingUsers)) {
            $user = $existingUsers[0];
            $this->syncGoogleProfile($user, $userProfile);
            return $user;
        }
        
        // Try to find by email
        $existingUsersByEmail = $user->find(['email' => $userProfile['email']]);
        
        if (!empty($existingUsersByEmail)) {
            $user = $existingUsersByEmail[0];
            // Link Google account to existing user
            $user->set('google_id', $userProfile['id']);
            $user->set('auth_provider', 'google');
            $this->syncGoogleProfile($user, $userProfile);
            $user->update();
            return $user;
        }
        
        // Create new user
        return $this->createUserFromGoogleProfile($userProfile);
    }
    
    /**
     * Create new user from Google profile
     */
    private function createUserFromGoogleProfile(array $userProfile): ?ModelBase
    {
        try {
            $user = ModelFactory::new('Users');
            
            $user->set('google_id', $userProfile['id']);
            $user->set('email', $userProfile['email']);
            $user->set('first_name', $userProfile['given_name'] ?? '');
            $user->set('last_name', $userProfile['family_name'] ?? '');
            $user->set('auth_provider', 'google');
            $user->set('email_verified_at', date('Y-m-d H:i:s'));
            $user->set('is_active', true);
            $user->set('last_login_method', 'google');
            $user->set('last_google_sync', date('Y-m-d H:i:s'));
            
            if (isset($userProfile['picture'])) {
                $user->set('profile_picture_url', $userProfile['picture']);
            }
            
            $user->create();
            
            // Assign default OAuth role
            $this->assignDefaultOAuthRole($user);
            
            $this->logger->info('Created new user from Google profile', [
                'user_id' => $user->get('id'),
                'email' => $userProfile['email']
            ]);
            
            return $user;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create user from Google profile', [
                'error' => $e->getMessage(),
                'profile' => $userProfile
            ]);
            return null;
        }
    }
    
    /**
     * Sync user with Google profile data
     */
    private function syncGoogleProfile(ModelBase $user, array $userProfile): void
    {
        try {
            $updated = false;
            
            // Update profile fields if they've changed
            $fieldsToSync = [
                'first_name' => $userProfile['given_name'] ?? '',
                'last_name' => $userProfile['family_name'] ?? '',
                'profile_picture_url' => $userProfile['picture'] ?? ''
            ];
            
            foreach ($fieldsToSync as $field => $value) {
                if ($value && $user->get($field) !== $value) {
                    $user->set($field, $value);
                    $updated = true;
                }
            }
            
            // Always update sync timestamp and login method
            $user->set('last_google_sync', date('Y-m-d H:i:s'));
            $user->set('last_login_method', 'google');
            $updated = true;
            
            if ($updated) {
                $user->update();
            }
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to sync Google profile', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Find user by username or email
     */
    private function findUserByCredentials(string $username): ?ModelBase
    {
        $user = ModelFactory::new('Users');
        
        // Try username first
        $users = $user->find(['username' => $username]);
        if (!empty($users)) {
            return $users[0];
        }
        
        // Try email
        $users = $user->find(['email' => $username]);
        if (!empty($users)) {
            return $users[0];
        }
        
        return null;
    }
    
    /**
     * Store refresh token in database
     */
    private function storeRefreshToken(ModelBase $user, string $refreshToken): void
    {
        try {
            $token = ModelFactory::new('JwtRefreshTokens');
            $token->set('user_id', $user->get('id'));
            $token->set('token_hash', hash('sha256', $refreshToken));
            $token->set('expires_at', date('Y-m-d H:i:s', time() + $this->refreshTokenLifetime));
            $token->set('is_revoked', false);
            $token->create();
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to store refresh token', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Verify refresh token exists and is valid
     */
    private function verifyRefreshToken(int $userId, string $refreshToken): bool
    {
        try {
            $token = ModelFactory::new('JwtRefreshTokens');
            $tokens = $token->find([
                'user_id' => $userId,
                'token_hash' => hash('sha256', $refreshToken),
                'is_revoked' => false
            ]);
            
            if (empty($tokens)) {
                return false;
            }
            
            // Check if token is expired
            $foundToken = $tokens[0];
            $expiresAt = $foundToken->get('expires_at');
            
            return strtotime($expiresAt) > time();
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to verify refresh token', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Update refresh token in database
     */
    private function updateRefreshToken(ModelBase $user, string $oldToken, string $newToken): void
    {
        try {
            // Find and revoke old token
            $token = ModelFactory::new('JwtRefreshTokens');
            $tokens = $token->find(['token_hash' => hash('sha256', $oldToken)]);
            
            if (!empty($tokens)) {
                $oldTokenModel = $tokens[0];
                $oldTokenModel->set('is_revoked', true);
                $oldTokenModel->update();
            }
            
            // Store new token
            $this->storeRefreshToken($user, $newToken);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update refresh token', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Assign default OAuth role to new user
     */
    private function assignDefaultOAuthRole(ModelBase $user): void
    {
        try {
            // Find default OAuth role
            $role = ModelFactory::new('Roles');
            $defaultRoles = $role->find(['is_oauth_default' => true]);
            
            if (empty($defaultRoles)) {
                $this->logger->warning('No default OAuth role found');
                return;
            }
            
            $defaultRole = $defaultRoles[0];
            
            // Use ModelBase relationship method to add the role to the user
            $success = $user->addRelation('roles', $defaultRole, ['assigned_at' => date('Y-m-d H:i:s')]);
            
            if ($success) {
                $this->logger->info('Assigned default OAuth role to user', [
                    'user_id' => $user->get('id'),
                    'role_id' => $defaultRole->get('id')
                ]);
            } else {
                $this->logger->error('Failed to assign default OAuth role via relationship', [
                    'user_id' => $user->get('id'),
                    'role_id' => $defaultRole->get('id')
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to assign default OAuth role', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Logout user by revoking refresh token
     */
    public function logout(ModelBase $user): bool
    {
        try {
            // Find and revoke all active refresh tokens for this user
            $token = ModelFactory::new('JwtRefreshTokens');
            $tokens = $token->find([
                'user_id' => $user->get('id'),
                'is_revoked' => false
            ]);
            
            foreach ($tokens as $tokenModel) {
                $tokenModel->set('is_revoked', true);
                $tokenModel->update();
            }
            
            $this->logger->info('User logged out', ['user_id' => $user->get('id')]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to logout user', [
                'user_id' => $user->get('id'),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Register new user with traditional authentication
     */
    public function registerUser(array $userData): ?ModelBase
    {
        try {
            $this->logger->info('Registering new user', ['email' => $userData['email'] ?? 'unknown']);
            
            // Validate required fields
            if (empty($userData['email']) || empty($userData['password'])) {
                throw new \InvalidArgumentException('Email and password are required');
            }
            
            // Check if user already exists
            $existingUser = $this->findUserByCredentials($userData['email']);
            if ($existingUser) {
                throw new \InvalidArgumentException('User already exists with this email');
            }
            
            // Create new user
            $user = ModelFactory::new('Users');
            
            $user->set('email', $userData['email']);
            $user->set('username', $userData['username'] ?? $userData['email']);
            $user->set('password_hash', password_hash($userData['password'], PASSWORD_DEFAULT));
            $user->set('first_name', $userData['first_name'] ?? '');
            $user->set('last_name', $userData['last_name'] ?? '');
            $user->set('auth_provider', 'traditional');
            $user->set('is_active', true);
            $user->set('last_login_method', 'traditional');
            
            $user->create();
            
            // Assign default role
            $this->assignDefaultRole($user, 'traditional');
            
            $this->logger->info('User registered successfully', [
                'user_id' => $user->get('id'),
                'email' => $userData['email']
            ]);
            
            return $user;
            
        } catch (\Exception $e) {
            $this->logger->error('User registration failed', [
                'email' => $userData['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Generate JWT tokens for user
     */
    public function generateTokensForUser(ModelBase $user): array
    {
        $accessToken = $this->generateJwtToken($user);
        $refreshToken = $this->generateRefreshToken($user);
        
        // Store refresh token
        $this->storeRefreshToken($user, $refreshToken);
        
        return [
            'user' => $this->formatUserData($user),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->accessTokenLifetime,
            'token_type' => 'Bearer'
        ];
    }
    
    /**
     * Format user data for API response
     */
    private function formatUserData(ModelBase $user): array
    {
        return [
            'id' => $user->get('id'),
            'email' => $user->get('email'),
            'username' => $user->get('username'),
            'first_name' => $user->get('first_name'),
            'last_name' => $user->get('last_name'),
            'auth_provider' => $user->get('auth_provider'),
            'last_login_method' => $user->get('last_login_method'),
            'profile_picture_url' => $user->get('profile_picture_url'),
            'is_active' => $user->get('is_active')
        ];
    }
    
    /**
     * Assign default role to new user based on auth method
     */
    private function assignDefaultRole(ModelBase $user, string $authMethod): void
    {
        try {
            if ($authMethod === 'google') {
                // For Google auth, use the OAuth-specific method
                $this->assignDefaultOAuthRole($user);
                return;
            }
            
            // Find user role for traditional registration
            $role = ModelFactory::new('Roles');
            $userRoles = $role->find(['name' => 'user']);
            
            if (empty($userRoles)) {
                $this->logger->warning('No default user role found');
                return;
            }
            
            $userRole = $userRoles[0];
            
            // Use ModelBase relationship method to add the role to the user
            $success = $user->addRelation('roles', $userRole, ['assigned_at' => date('Y-m-d H:i:s')]);
            
            if ($success) {
                $this->logger->info('Assigned default role to user', [
                    'user_id' => $user->get('id'),
                    'role_id' => $userRole->get('id'),
                    'auth_method' => $authMethod
                ]);
            } else {
                $this->logger->error('Failed to assign default role via relationship', [
                    'user_id' => $user->get('id'),
                    'role_id' => $userRole->get('id'),
                    'auth_method' => $authMethod
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to assign default role', [
                'user_id' => $user->get('id'),
                'auth_method' => $authMethod,
                'error' => $e->getMessage()
            ]);
        }
    }
}
