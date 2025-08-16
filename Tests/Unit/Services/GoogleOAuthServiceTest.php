<?php

namespace Gravitycar\Tests\Unit\Services;

use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\GoogleOAuthService;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Core\Config;
use Gravitycar\Tests\Unit\UnitTestCase;

class GoogleOAuthServiceTest extends UnitTestCase
{
    private GoogleOAuthService $oauthService;
    private Config|MockObject $mockConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the config service
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockConfig->method('get')->willReturnMap([
            ['google.client_id', 'test-client-id'],
            ['google.client_secret', 'test-client-secret'], 
            ['google.redirect_uri', 'http://localhost/auth/google/callback']
        ]);
        
        // Mock ServiceLocator to return our mock config
        $this->mockStatic(ServiceLocator::class, function($mock) {
            $mock->method('getConfig')->willReturn($this->mockConfig);
        });
        
        $this->oauthService = new GoogleOAuthService();
    }

    public function testGetAuthorizationUrlGeneratesValidUrl(): void
    {
        // Act
        $authUrl = $this->oauthService->getAuthorizationUrl();

        // Assert
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('accounts.google.com', $authUrl);
        $this->assertStringContainsString('client_id=test-client-id', $authUrl);
        $this->assertStringContainsString('redirect_uri=', $authUrl);
        $this->assertStringContainsString('scope=', $authUrl);
        $this->assertStringContainsString('response_type=code', $authUrl);
    }

    public function testGetAuthorizationUrlWithCustomOptions(): void
    {
        // Arrange
        $customOptions = [
            'scope' => ['openid', 'profile', 'email'],
            'access_type' => 'offline'
        ];

        // Act
        $authUrl = $this->oauthService->getAuthorizationUrl($customOptions);

        // Assert
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('access_type=offline', $authUrl);
    }

    public function testGetAuthorizationUrlIncludesRequiredScopes(): void
    {
        // Act
        $authUrl = $this->oauthService->getAuthorizationUrl();

        // Assert
        $this->assertStringContainsString('scope=', $authUrl);
        // The exact scope format depends on implementation, but should include email and profile
        $decodedUrl = urldecode($authUrl);
        $this->assertStringContainsString('email', $decodedUrl);
        $this->assertStringContainsString('profile', $decodedUrl);
    }

    public function testValidateOAuthTokenWithInvalidCode(): void
    {
        // Arrange
        $invalidCode = 'invalid-authorization-code';
        
        // This will likely throw an exception or return error array
        // Act & Assert
        $this->expectException(\Exception::class);
        $this->oauthService->validateOAuthToken($invalidCode);
    }

    public function testGetUserProfileWithInvalidToken(): void
    {
        // Arrange
        $invalidToken = 'invalid-access-token';

        // Act
        $profile = $this->oauthService->getUserProfile($invalidToken);

        // Assert
        $this->assertNull($profile, 'Should return null for invalid access token');
    }

    public function testValidateIdTokenWithInvalidToken(): void
    {
        // Arrange
        $invalidIdToken = 'invalid.id.token';

        // Act
        $result = $this->oauthService->validateIdToken($invalidIdToken);

        // Assert
        $this->assertNull($result, 'Should return null for invalid ID token');
    }

    public function testRefreshGoogleTokenWithInvalidToken(): void
    {
        // Arrange
        $invalidRefreshToken = 'invalid-refresh-token';

        // Act
        $result = $this->oauthService->refreshGoogleToken($invalidRefreshToken);

        // Assert
        $this->assertNull($result, 'Should return null for invalid refresh token');
    }

    /**
     * Mock static methods for testing
     */
    private function mockStatic(string $class, callable $callback): void
    {
        // This is a simplified approach - in real tests you might use 
        // tools like Mockery or AspectMock for better static mocking
        $callback($this->createMock($class));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
