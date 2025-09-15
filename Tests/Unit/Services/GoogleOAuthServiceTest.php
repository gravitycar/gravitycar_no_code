<?php

namespace Gravitycar\Tests\Unit\Services;

use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\GoogleOAuthService;
use Gravitycar\Core\Config;
use Psr\Log\LoggerInterface;
use Gravitycar\Tests\Unit\UnitTestCase;

class GoogleOAuthServiceTest extends UnitTestCase
{
    private GoogleOAuthService $oauthService;
    private Config|MockObject $mockConfig;
    private LoggerInterface|MockObject $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks for dependencies
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Set up mock config to return test values
        $this->mockConfig->method('get')->willReturnCallback(function($key, $default = null) {
            $testValues = [
                'google.client_id' => 'test-client-id',
                'google.client_secret' => 'test-client-secret',
                'google.redirect_uri' => 'http://localhost/auth/callback'
            ];
            return $testValues[$key] ?? $default;
        });
        
        // Create service with injected dependencies
        $this->oauthService = new GoogleOAuthService($this->mockConfig, $this->mockLogger);
    }

    public function testGetAuthorizationUrlGeneratesValidUrl(): void
    {
        // Act
        $authUrl = $this->oauthService->getAuthorizationUrl();

        // Assert
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('accounts.google.com', $authUrl);
        $this->assertStringContainsString('client_id=', $authUrl);
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

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
