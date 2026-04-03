<?php

namespace Gravitycar\Tests\Unit\Services;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Core\Config;
use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Services\GoogleOAuthService;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for AuthenticationService::formatUserData() including user_timezone.
 */
class AuthenticationServiceFormatUserDataTest extends UnitTestCase
{
    private AuthenticationService $authService;
    private Config&MockObject $mockConfig;
    private ModelFactory&MockObject $mockModelFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockDatabase = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $mockGoogleOAuthService = $this->createMock(GoogleOAuthService::class);

        $this->mockConfig->method('getEnv')->willReturnCallback(function ($key, $default = null) {
            $envValues = [
                'JWT_SECRET_KEY' => 'test-secret-key-for-testing-only',
                'JWT_REFRESH_SECRET' => 'test-refresh-secret-for-testing-only',
                'JWT_ACCESS_TOKEN_LIFETIME' => '3600',
                'JWT_REFRESH_TOKEN_LIFETIME' => '86400',
                'APP_URL' => 'http://test.local',
            ];
            return $envValues[$key] ?? $default;
        });

        $_ENV['JWT_SECRET_KEY'] = 'test-secret-key-for-testing-only';
        $_ENV['JWT_REFRESH_SECRET'] = 'test-refresh-secret-for-testing-only';
        $_ENV['JWT_ACCESS_TOKEN_LIFETIME'] = '3600';
        $_ENV['JWT_REFRESH_TOKEN_LIFETIME'] = '86400';

        $this->authService = new AuthenticationService(
            $mockLogger,
            $mockDatabase,
            $this->mockConfig,
            $this->mockModelFactory,
            $mockGoogleOAuthService
        );
    }

    private function createUserMock(array $userData): MockObject
    {
        $user = $this->createMock(ModelBase::class);
        $user->method('get')->willReturnCallback(function ($field) use ($userData) {
            return $userData[$field] ?? null;
        });
        return $user;
    }

    private function invokeFormatUserData(ModelBase $user): array
    {
        $reflection = new \ReflectionMethod($this->authService, 'formatUserData');
        $reflection->setAccessible(true);
        return $reflection->invoke($this->authService, $user);
    }

    public function testFormatUserDataIncludesUserTimezone(): void
    {
        $mockUser = $this->createUserMock([
            'id' => 'test-uuid-123',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'auth_provider' => 'local',
            'last_login_method' => 'local',
            'profile_picture_url' => null,
            'is_active' => true,
            'user_timezone' => 'America/New_York',
        ]);

        $result = $this->invokeFormatUserData($mockUser);

        $this->assertArrayHasKey('user_timezone', $result);
        $this->assertSame('America/New_York', $result['user_timezone']);
    }

    public function testFormatUserDataWithUtcDefault(): void
    {
        $mockUser = $this->createUserMock([
            'id' => 'test-uuid-456',
            'email' => 'utc@example.com',
            'username' => 'utcuser',
            'first_name' => 'Utc',
            'last_name' => 'User',
            'auth_provider' => 'local',
            'last_login_method' => 'local',
            'profile_picture_url' => null,
            'is_active' => true,
            'user_timezone' => 'UTC',
        ]);

        $result = $this->invokeFormatUserData($mockUser);

        $this->assertSame('UTC', $result['user_timezone']);
    }

    public function testFormatUserDataContainsAllExpectedKeys(): void
    {
        $mockUser = $this->createUserMock([
            'id' => 'test-uuid-789',
            'email' => 'all@example.com',
            'username' => 'allfields',
            'first_name' => 'All',
            'last_name' => 'Fields',
            'auth_provider' => 'local',
            'last_login_method' => 'local',
            'profile_picture_url' => 'https://example.com/pic.jpg',
            'is_active' => true,
            'user_timezone' => 'Europe/London',
        ]);

        $result = $this->invokeFormatUserData($mockUser);

        $expectedKeys = [
            'id', 'email', 'username', 'first_name', 'last_name',
            'auth_provider', 'last_login_method', 'profile_picture_url',
            'is_active', 'user_timezone',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    public function testFormatUserDataHasExactlyTenKeys(): void
    {
        $mockUser = $this->createUserMock([
            'id' => 'test-uuid',
            'email' => 'count@example.com',
            'username' => 'countuser',
            'first_name' => 'Count',
            'last_name' => 'User',
            'auth_provider' => 'local',
            'last_login_method' => 'local',
            'profile_picture_url' => null,
            'is_active' => true,
            'user_timezone' => 'UTC',
        ]);

        $result = $this->invokeFormatUserData($mockUser);

        $this->assertCount(10, $result);
    }
}
