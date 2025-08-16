<?php

namespace Gravitycar\Services;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Token\AccessToken;

/**
 * GoogleOAuthService
 * Handles Google OAuth 2.0 integration
 */
class GoogleOAuthService
{
    private Google $provider;
    
    public function __construct()
    {
        $config = ServiceLocator::getConfig();
        
        $this->provider = new Google([
            'clientId'     => $config->get('google.client_id'),
            'clientSecret' => $config->get('google.client_secret'),
            'redirectUri'  => $config->get('google.redirect_uri'),
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
        $logger = ServiceLocator::getLogger();
        
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
     */
    public function getUserProfile(string $googleToken): ?array
    {
        $logger = ServiceLocator::getLogger();
        
        try {
            // Create access token object
            $accessToken = new AccessToken(['access_token' => $googleToken]);
            
            // Get user profile
            $resourceOwner = $this->provider->getResourceOwner($accessToken);
            $profile = $resourceOwner->toArray();
            
            // Normalize profile data
            $normalizedProfile = [
                'id' => $profile['sub'] ?? $profile['id'] ?? null,
                'email' => $profile['email'] ?? null,
                'first_name' => $profile['given_name'] ?? null,
                'last_name' => $profile['family_name'] ?? null,
                'full_name' => $profile['name'] ?? null,
                'picture' => $profile['picture'] ?? null,
                'email_verified' => $profile['email_verified'] ?? false,
                'locale' => $profile['locale'] ?? null,
            ];
            
            $logger->debug('Google user profile retrieved', [
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
        $logger = ServiceLocator::getLogger();
        
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
        $logger = ServiceLocator::getLogger();
        
        try {
            // Use Google's discovery document to get public keys
            $discoveryUrl = 'https://accounts.google.com/.well-known/openid_configuration';
            $discovery = json_decode(file_get_contents($discoveryUrl), true);
            
            if (!$discovery || !isset($discovery['jwks_uri'])) {
                throw new GCException('Failed to get Google discovery document');
            }
            
            // Get public keys
            $jwksUrl = $discovery['jwks_uri'];
            $jwks = json_decode(file_get_contents($jwksUrl), true);
            
            if (!$jwks || !isset($jwks['keys'])) {
                throw new GCException('Failed to get Google public keys');
            }
            
            // TODO: Implement full JWT verification with Google's public keys
            // For now, we'll use a simpler approach via Google's tokeninfo endpoint
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
        $logger = ServiceLocator::getLogger();
        $config = ServiceLocator::getConfig();
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
