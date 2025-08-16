<?php

namespace Gravitycar\Tests\Unit\Api;

use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Api\AuthController;
use Gravitycar\Api\Request;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Services\GoogleOAuthService;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Models\ModelBase;
use Monolog\Logger;
use Gravitycar\Tests\Unit\UnitTestCase;

class AuthControllerTest extends UnitTestCase
{
    private AuthController $controller;
    private AuthenticationService|MockObject $mockAuthService;
    private GoogleOAuthService|MockObject $mockOAuthService;
    private Logger|MockObject $mockLogger;
    private Request|MockObject $mockRequest;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockAuthService = $this->createMock(AuthenticationService::class);
        $this->mockOAuthService = $this->createMock(GoogleOAuthService::class);
        $this->mockRequest = $this->createMock(Request::class);
        
        // Mock ServiceLocator to return our mocks
        $this->mockStatic(ServiceLocator::class, function($mock) {
            $mock->method('getAuthenticationService')->willReturn($this->mockAuthService);
            $mock->method('get')->with('google_oauth_service')->willReturn($this->mockOAuthService);
        });
        
        $this->controller = new AuthController($this->mockLogger);
    }

    public function testGetGoogleAuthUrlReturnsAuthorizationUrl(): void
    {
        // Arrange
        $expectedUrl = 'https://accounts.google.com/oauth/authorize?client_id=test&redirect_uri=...';
        $this->mockOAuthService->method('getAuthorizationUrl')
            ->willReturn($expectedUrl);

        // Act
        $result = $this->controller->getGoogleAuthUrl($this->mockRequest, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('authorization_url', $result);
        $this->assertEquals($expectedUrl, $result['authorization_url']);
    }

    public function testAuthenticateWithGoogleWithValidCode(): void
    {
        // Arrange
        $this->mockRequest->method('get')->willReturnMap([
            ['code', 'valid-auth-code'],
            ['state', 'valid-state']
        ]);

        $oauthResult = [
            'access_token' => 'google-access-token',
            'id_token' => 'google-id-token'
        ];
        
        $this->mockOAuthService->method('validateOAuthToken')
            ->with('valid-auth-code', 'valid-state')
            ->willReturn($oauthResult);

        $authResult = [
            'user' => ['id' => 123, 'email' => 'test@gmail.com'],
            'access_token' => 'jwt-access-token',
            'refresh_token' => 'jwt-refresh-token'
        ];

        $this->mockAuthService->method('authenticateWithGoogle')
            ->with('google-access-token')
            ->willReturn($authResult);

        // Act
        $result = $this->controller->authenticateWithGoogle($this->mockRequest);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals(123, $result['user']['id']);
    }

    public function testAuthenticateWithGoogleWithMissingCode(): void
    {
        // Arrange
        $this->mockRequest->method('get')->willReturnMap([
            ['code', null],
            ['state', 'valid-state']
        ]);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Authorization code is required');

        // Act
        $this->controller->authenticateWithGoogle($this->mockRequest);
    }

    public function testAuthenticateTraditionalWithValidCredentials(): void
    {
        // Arrange
        $this->mockRequest->method('get')->willReturnMap([
            ['email', 'test@example.com'],
            ['password', 'password123']
        ]);

        $authResult = [
            'user' => ['id' => 456, 'email' => 'test@example.com'],
            'access_token' => 'jwt-access-token',
            'refresh_token' => 'jwt-refresh-token'
        ];

        $this->mockAuthService->method('authenticateTraditional')
            ->with('test@example.com', 'password123')
            ->willReturn($authResult);

        // Act
        $result = $this->controller->authenticateTraditional($this->mockRequest);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals(456, $result['user']['id']);
    }

    public function testAuthenticateTraditionalWithInvalidCredentials(): void
    {
        // Arrange
        $this->mockRequest->method('get')->willReturnMap([
            ['email', 'test@example.com'],
            ['password', 'wrongpassword']
        ]);

        $this->mockAuthService->method('authenticateTraditional')
            ->with('test@example.com', 'wrongpassword')
            ->willReturn(null);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid credentials');

        // Act
        $this->controller->authenticateTraditional($this->mockRequest);
    }

    public function testAuthenticateTraditionalWithMissingCredentials(): void
    {
        // Arrange
        $this->mockRequest->method('get')->willReturnMap([
            ['email', null],
            ['password', 'password123']
        ]);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email and password are required');

        // Act
        $this->controller->authenticateTraditional($this->mockRequest);
    }

    public function testRegisterWithValidData(): void
    {
        // Arrange
        $userData = [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];

        $this->mockRequest->method('get')->willReturnCallback(function($key) use ($userData) {
            return $userData[$key] ?? null;
        });

        $mockUser = $this->createMock(ModelBase::class);
        $this->mockAuthService->method('registerUser')
            ->with($userData)
            ->willReturn($mockUser);

        $authResult = [
            'user' => ['id' => 789, 'email' => 'newuser@example.com'],
            'access_token' => 'jwt-access-token',
            'refresh_token' => 'jwt-refresh-token'
        ];

        $this->mockAuthService->method('generateTokensForUser')
            ->with($mockUser)
            ->willReturn($authResult);

        // Act
        $result = $this->controller->register($this->mockRequest, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals(789, $result['user']['id']);
    }

    public function testRegisterWithExistingEmail(): void
    {
        // Arrange
        $userData = [
            'email' => 'existing@example.com',
            'password' => 'password123'
        ];

        $this->mockRequest->method('get')->willReturnCallback(function($key) use ($userData) {
            return $userData[$key] ?? null;
        });

        $this->mockAuthService->method('registerUser')
            ->willReturn(null); // User registration failed

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User registration failed');

        // Act
        $this->controller->register($this->mockRequest, []);
    }

    public function testRefreshTokenWithValidToken(): void
    {
        // Arrange
        $this->mockRequest->method('get')
            ->with('refresh_token')
            ->willReturn('valid-refresh-token');

        $refreshResult = [
            'access_token' => 'new-jwt-access-token',
            'refresh_token' => 'new-jwt-refresh-token',
            'expires_in' => 3600
        ];

        $this->mockAuthService->method('refreshJwtToken')
            ->with('valid-refresh-token')
            ->willReturn($refreshResult);

        // Act
        $result = $this->controller->refreshToken($this->mockRequest, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals('new-jwt-access-token', $result['access_token']);
    }

    public function testRefreshTokenWithInvalidToken(): void
    {
        // Arrange
        $this->mockRequest->method('get')
            ->with('refresh_token')
            ->willReturn('invalid-refresh-token');

        $this->mockAuthService->method('refreshJwtToken')
            ->with('invalid-refresh-token')
            ->willReturn(null);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid refresh token');

        // Act
        $this->controller->refreshToken($this->mockRequest, []);
    }

    public function testLogoutWithAuthenticatedUser(): void
    {
        // Arrange
        $mockUser = $this->createMock(ModelBase::class);
        
        $this->mockStatic(ServiceLocator::class, function($mock) use ($mockUser) {
            $mock->method('getCurrentUser')->willReturn($mockUser);
        });

        $this->mockAuthService->method('logout')
            ->with($mockUser)
            ->willReturn(true);

        // Act
        $result = $this->controller->logout($this->mockRequest, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Successfully logged out', $result['message']);
    }

    public function testLogoutWithUnauthenticatedUser(): void
    {
        // Arrange
        $this->mockStatic(ServiceLocator::class, function($mock) {
            $mock->method('getCurrentUser')->willReturn(null);
        });

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not authenticated');

        // Act
        $this->controller->logout($this->mockRequest, []);
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
}
