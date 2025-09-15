<?php

namespace Gravitycar\Services;

use Gravitycar\Core\Config;
use Gravitycar\Exceptions\GCException;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

/**
 * GoogleOAuthService
 * Handles Google OAuth 2.0 integration
 */
class GoogleOAuthService
{
    private Google $provider;
    private Config $config;
    private LoggerInterface $logger;
    
    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        
        $this->provider = new Google([
            'clientId'     => $this->config->get('google.client_id'),
            'clientSecret' => $this->config->get('google.client_secret'),
            'redirectUri'  => $this->config->get('google.redirect_uri'),
        ]);
    }
    
    /**
     * Get Google OAuth authorization URL
     */
    public function getAuthorizationUrl(array $options = []): string
    {
        $defaultOptions = [
            'scope' => ['openid', 'profile', 'email']
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        return $this->provider->getAuthorizationUrl($options);
    }
    
    /**
     * Validate Google OAuth token and return user data
     */
    public function validateOAuthToken(string $code, string $state = null): array
    {
        $logger = $this->logger;
        
        try {
            // Get access token from authorization code
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);
            
            // Get user profile from Google
            $resourceOwner = $this->provider->getResourceOwner($accessToken);
            $profile = $resourceOwner->toArray();
            
            $logger->info('Google OAuth token validated successfully', [
                'google_id' => $profile['sub'] ?? null,
                'email' => $profile['email'] ?? null
            ]);
            
            return [
                'access_token' => $accessToken,
                'profile' => $profile
            ];
            
        } catch (\Exception $e) {
            $logger->error('Google OAuth token validation failed', [
                'error' => $e->getMessage(),
                'code_preview' => substr($code, 0, 10) . '...'
            ]);
            
            throw new GCException('Google OAuth validation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }
    
    /**
     * Get user profile from Google token
     * This method handles Google Identity Services JWT credentials
     */
    public function getUserProfile(string $googleToken): ?array
    {
        $logger = $this->logger;
        
        try {
            // The token from Google Identity Services is a JWT credential (ID token)
            // We need to validate it and extract user information
            $tokenInfo = $this->validateIdToken($googleToken);
            
            if (!$tokenInfo) {
                $logger->warning('Google token validation failed');
                return null;
            }
            
            // Normalize profile data from JWT payload
            $normalizedProfile = [
                'id' => $tokenInfo['sub'] ?? null,
                'email' => $tokenInfo['email'] ?? null,
                'first_name' => $tokenInfo['given_name'] ?? null,
                'last_name' => $tokenInfo['family_name'] ?? null,
                'full_name' => $tokenInfo['name'] ?? null,
                'picture' => $tokenInfo['picture'] ?? null,
                'email_verified' => ($tokenInfo['email_verified'] ?? false) === true || ($tokenInfo['email_verified'] ?? '') === 'true',
                'locale' => $tokenInfo['locale'] ?? null,
            ];
            
            $logger->debug('Google user profile retrieved from JWT', [
                'google_id' => $normalizedProfile['id'],
                'email' => $normalizedProfile['email'],
                'email_verified' => $normalizedProfile['email_verified']
            ]);
            
            return $normalizedProfile;
            
        } catch (\Exception $e) {
            $logger->error('Failed to get Google user profile', [
                'error' => $e->getMessage(),
                'token_preview' => substr($googleToken, 0, 20) . '...'
            ]);
            
            return null;
        }
    }
    
    /**
     * Refresh Google token
     */
    public function refreshGoogleToken(string $refreshToken): ?array
    {
        $logger = $this->logger;
        
        try {
            $newAccessToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken
            ]);
            
            $logger->info('Google token refreshed successfully');
            
            return [
                'access_token' => $newAccessToken->getToken(),
                'refresh_token' => $newAccessToken->getRefreshToken() ?: $refreshToken,
                'expires_at' => $newAccessToken->getExpires()
            ];
            
        } catch (\Exception $e) {
            $logger->error('Failed to refresh Google token', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Validate Google ID token (for direct client-side integration)
     */
    public function validateIdToken(string $idToken): ?array
    {
        $logger = $this->logger;
        
        try {
            // Use Google's tokeninfo endpoint directly - more reliable than discovery
            return $this->verifyTokenViaGoogleAPI($idToken);
            
        } catch (\Exception $e) {
            $logger->error('Google ID token validation failed', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Verify token using Google's tokeninfo endpoint
     */
    private function verifyTokenViaGoogleAPI(string $token): ?array
    {
        $logger = $this->logger;
        $config = $this->config;
        $clientId = $config->get('google.client_id');
        
        try {
            $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);
            $response = file_get_contents($url);
            
            if ($response === false) {
                throw new GCException('Failed to contact Google tokeninfo endpoint');
            }
            
            $tokenInfo = json_decode($response, true);
            
            if (!$tokenInfo || isset($tokenInfo['error'])) {
                throw new GCException('Invalid token: ' . ($tokenInfo['error_description'] ?? 'Unknown error'));
            }
            
            // Verify audience (client ID)
            if ($tokenInfo['aud'] !== $clientId) {
                throw new GCException('Token audience mismatch');
            }
            
            // Verify expiration
            if (time() >= $tokenInfo['exp']) {
                throw new GCException('Token expired');
            }
            
            $logger->debug('Google ID token verified successfully', [
                'google_id' => $tokenInfo['sub'],
                'email' => $tokenInfo['email']
            ]);
            
            return $tokenInfo;
            
        } catch (\Exception $e) {
            $logger->error('Google tokeninfo verification failed', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}
