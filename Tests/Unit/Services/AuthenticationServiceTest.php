<?php

namespace Gravitycar\Tests\Unit\Services;

use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Core\Config;
use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Services\GoogleOAuthService;
use Psr\Log\LoggerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Gravitycar\Tests\Unit\UnitTestCase;

class AuthenticationServiceTest extends UnitTestCase
{
    private AuthenticationService $authService;
    private DatabaseConnectorInterface|MockObject $mockDatabase;
    private LoggerInterface|MockObject $mockLogger;
    private Config|MockObject $mockConfig;
    private ModelFactory|MockObject $mockModelFactory;
    private GoogleOAuthService|MockObject $mockGoogleOAuthService;
    private ModelBase|MockObject $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockDatabase = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockGoogleOAuthService = $this->createMock(GoogleOAuthService::class);
        $this->mockUser = $this->createMock(ModelBase::class);
        
        // Set up mock config to return test values
        $this->mockConfig->method('getEnv')->willReturnCallback(function($key, $default = null) {
            $envValues = [
                'JWT_SECRET_KEY' => 'test-secret-key-for-testing-only',
                'JWT_REFRESH_SECRET' => 'test-refresh-secret-for-testing-only',
                'JWT_ACCESS_TOKEN_LIFETIME' => '3600',
                'JWT_REFRESH_TOKEN_LIFETIME' => '86400',
                'APP_URL' => 'http://test.local'
            ];
            return $envValues[$key] ?? $default;
        });
        
        // Set up test environment variables (for backward compatibility)
        $_ENV['JWT_SECRET_KEY'] = 'test-secret-key-for-testing-only';
        $_ENV['JWT_REFRESH_SECRET'] = 'test-refresh-secret-for-testing-only';
        $_ENV['JWT_ACCESS_TOKEN_LIFETIME'] = '3600';
        $_ENV['JWT_REFRESH_TOKEN_LIFETIME'] = '86400';
        
        $this->authService = new AuthenticationService(
            $this->mockLogger,
            $this->mockDatabase,
            $this->mockConfig,
            $this->mockModelFactory,
            $this->mockGoogleOAuthService
        );
    }

    /**
     * Helper method to create a mock user with specified data
     */
    private function createUserMock(array $userData): MockObject
    {
        $user = $this->createMock(ModelBase::class);
        $user->method('get')->willReturnCallback(function($field) use ($userData) {
            return $userData[$field] ?? null;
        });
        return $user;
    }

    /**
     * Helper method to mock ModelFactory::new() calls
     */
    private function mockModelFactory(string $modelName, MockObject $mockModel): void
    {
        // Since we can't easily mock static calls in our current setup,
        // we'll need to test the integration at a higher level
        // This is a placeholder for proper static mocking
    }

    public function testGenerateJwtTokenCreatesValidToken(): void
    {
        // Arrange
        $this->mockUser->method('get')->willReturnMap([
            ['id', 123],
            ['email', 'test@example.com'],
            ['username', 'testuser']
        ]);

        // Act
        $token = $this->authService->generateJwtToken($this->mockUser);

        // Assert
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        
        // Verify token structure
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT should have 3 parts (header.payload.signature)');
        
        // Decode and verify payload (without verification for testing)
        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertEquals(123, $payload['user_id']);
        $this->assertEquals('test@example.com', $payload['email']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function testValidateJwtTokenWithValidToken(): void
    {
        // Skip until proper ModelFactory static mocking is implemented
        $this->markTestSkipped('Requires integration testing infrastructure for ModelFactory::retrieve() static calls');
    }

    public function testValidateJwtTokenWithInvalidTokenReturnsNull(): void
    {
        // Arrange
        $invalidToken = 'invalid.jwt.token';

        // Act
        $result = $this->authService->validateJwtToken($invalidToken);

        // Assert
        $this->assertNull($result);
    }

    public function testAuthenticateTraditionalWithValidCredentials(): void
    {
        // Since ModelFactory uses static methods and complex database interactions,
        // we'll test this as an integration test rather than pure unit test
        // For now, we'll skip this test until proper mocking infrastructure is in place
        $this->markTestSkipped('Requires integration testing infrastructure for ModelFactory static calls');
    }

    public function testAuthenticateTraditionalWithInvalidCredentials(): void
    {
        // Skip until proper ModelFactory mocking is implemented
        $this->markTestSkipped('Requires integration testing infrastructure for ModelFactory static calls');
    }

    public function testAuthenticateTraditionalWithInactiveUser(): void
    {
        // Skip until proper ModelFactory mocking is implemented
        $this->markTestSkipped('Requires integration testing infrastructure for ModelFactory static calls');
    }

    public function testRegisterUserWithValidData(): void
    {
        // Skip until proper ModelFactory mocking is implemented
        $this->markTestSkipped('Requires integration testing infrastructure for ModelFactory static calls');
    }

    public function testRegisterUserWithExistingEmail(): void
    {
        // Skip until proper ModelFactory mocking is implemented
        $this->markTestSkipped('Requires integration testing infrastructure for ModelFactory static calls');
    }

    public function testLogoutRevokesRefreshTokens(): void
    {
        // Skip until proper ModelFactory mocking is implemented
        $this->markTestSkipped('Requires integration testing infrastructure for ModelFactory static calls');
    }

    public function testGenerateTokensForUserReturnsCompleteTokenSet(): void
    {
        // Arrange
        $this->mockUser->method('get')->willReturnMap([
            ['id', 123],
            ['email', 'test@example.com'],
            ['username', 'testuser'],
            ['first_name', 'John'],
            ['last_name', 'Doe'],
            ['auth_provider', 'traditional'],
            ['is_active', true],
            ['last_login_method', 'traditional'],
            ['profile_picture_url', null]
        ]);

        // Mock storeRefreshToken to avoid database operations
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $this->mockDatabase->method('getConnection')->willReturn($connection);
        $stmt = $this->createMock(\Doctrine\DBAL\Statement::class);
        $connection->method('prepare')->willReturn($stmt);
        $stmt->method('executeQuery');

        // Act
        $result = $this->authService->generateTokensForUser($this->mockUser);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertEquals('Bearer', $result['token_type']);
    }

    public function testGenerateRefreshTokenCreatesUniqueToken(): void
    {
        // Arrange
        $this->mockUser->method('get')->with('id')->willReturn(123);

        // Act
        $token1 = $this->authService->generateRefreshToken($this->mockUser);
        
        // Add a small delay to ensure different timestamps
        usleep(1000000); // 1 second delay
        
        $token2 = $this->authService->generateRefreshToken($this->mockUser);

        // Assert
        $this->assertIsString($token1);
        $this->assertIsString($token2);
        $this->assertNotEquals($token1, $token2, 'Refresh tokens should be unique');
        
        // Verify tokens are valid JWTs
        $parts1 = explode('.', $token1);
        $parts2 = explode('.', $token2);
        $this->assertCount(3, $parts1, 'Refresh token should be valid JWT');
        $this->assertCount(3, $parts2, 'Refresh token should be valid JWT');
    }

    public function testRefreshJwtTokenWithValidRefreshToken(): void
    {
        // Skip until proper ModelFactory mocking is implemented
        $this->markTestSkipped('Requires integration testing infrastructure for ModelFactory static calls');
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
