<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\CurrentUserProvider;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Core\Config;
use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\SessionExpiredException;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Monolog\Logger;

/**
 * Test inactivity-based session timeout functionality
 */
class InactivitySessionTimeoutTest extends TestCase
{
    private CurrentUserProvider $provider;
    private Logger|MockObject $mockLogger;
    private AuthenticationService|MockObject $mockAuthService;
    private ModelFactory|MockObject $mockModelFactory;
    private Config|MockObject $mockConfig;
    private DatabaseConnectorInterface|MockObject $mockDatabaseConnector;
    private ModelBase|MockObject $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockAuthService = $this->createMock(AuthenticationService::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockUser = $this->createMock(ModelBase::class);
        
        // Default config values
        $this->mockConfig->method('get')
            ->willReturnCallback(function($key, $default) {
                if ($key === 'auth.inactivity_timeout') {
                    return 3600; // 1 hour
                }
                if ($key === 'auth.activity_debounce') {
                    return 60; // 60 seconds
                }
                return $default;
            });
    }

    /**
     * Create CurrentUserProvider with mocked dependencies and simulated request
     */
    private function createProvider(bool $hasAuthToken = false, ?string $userId = null): CurrentUserProvider
    {
        // Simulate Authorization header if token provided
        if ($hasAuthToken) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-jwt-token';
        } else {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }
        
        // Setup auth service to return user if token is valid
        if ($userId) {
            $this->mockAuthService->method('validateJwtToken')
                ->willReturn($this->mockUser);
            
            $this->mockUser->method('get')
                ->willReturnCallback(function($field) use ($userId) {
                    if ($field === 'id') {
                        return $userId;
                    }
                    return null;
                });
        } else {
            $this->mockAuthService->method('validateJwtToken')
                ->willReturn(null);
        }
        
        return new CurrentUserProvider(
            $this->mockLogger,
            $this->mockAuthService,
            $this->mockModelFactory,
            $this->mockConfig,
            $this->mockDatabaseConnector
        );
    }

    public function testUserWithinActivityWindowIsAllowed(): void
    {
        // Set last_activity to 10 minutes ago (within 1 hour window)
        // But outside debounce window, so it WILL update
        $tenMinutesAgo = date('Y-m-d H:i:s', time() - 600);
        
        $this->mockUser->method('get')
            ->willReturnCallback(function($field) use ($tenMinutesAgo) {
                if ($field === 'id') {
                    return 'user-123';
                }
                if ($field === 'last_activity') {
                    return $tenMinutesAgo;
                }
                return null;
            });
        
        // User should be updated (outside debounce window but within activity window)
        $this->mockUser->expects($this->once())
            ->method('set')
            ->with('last_activity', $this->anything());
        
        $this->mockDatabaseConnector->expects($this->once())
            ->method('update')
            ->with($this->mockUser);
        
        $provider = $this->createProvider(true, 'user-123');
        $currentUser = $provider->getCurrentUser();
        
        $this->assertNotNull($currentUser);
    }

    public function testUserOutsideActivityWindowIsRejected(): void
    {
        // Set last_activity to 2 hours ago (outside 1 hour window)
        $twoHoursAgo = date('Y-m-d H:i:s', time() - 7200);
        
        $this->mockUser->method('get')
            ->willReturnCallback(function($field) use ($twoHoursAgo) {
                if ($field === 'id') {
                    return 'user-123';
                }
                if ($field === 'last_activity') {
                    return $twoHoursAgo;
                }
                return null;
            });
        
        $this->expectException(SessionExpiredException::class);
        $this->expectExceptionMessage('Your session has expired due to inactivity');
        
        $provider = $this->createProvider(true, 'user-123');
        $provider->getCurrentUser();
    }

    public function testUserWithNoLastActivityIsAllowed(): void
    {
        // No last_activity set (backward compatibility)
        $this->mockUser->method('get')
            ->willReturnCallback(function($field) {
                if ($field === 'id') {
                    return 'user-123';
                }
                if ($field === 'last_activity') {
                    return null;
                }
                return null;
            });
        
        // Should update last_activity for first time
        $this->mockUser->expects($this->once())
            ->method('set')
            ->with('last_activity', $this->anything());
        
        $this->mockDatabaseConnector->expects($this->once())
            ->method('update')
            ->with($this->mockUser);
        
        $provider = $this->createProvider(true, 'user-123');
        $currentUser = $provider->getCurrentUser();
        
        $this->assertNotNull($currentUser);
    }

    public function testActivityUpdateDebouncing(): void
    {
        // Set last_activity to 30 seconds ago (within 60 second debounce)
        $thirtySecondsAgo = date('Y-m-d H:i:s', time() - 30);
        
        $this->mockUser->method('get')
            ->willReturnCallback(function($field) use ($thirtySecondsAgo) {
                if ($field === 'id') {
                    return 'user-123';
                }
                if ($field === 'last_activity') {
                    return $thirtySecondsAgo;
                }
                return null;
            });
        
        // Should NOT update (within debounce window)
        $this->mockDatabaseConnector->expects($this->never())->method('update');
        
        $provider = $this->createProvider(true, 'user-123');
        $currentUser = $provider->getCurrentUser();
        
        $this->assertNotNull($currentUser);
    }

    public function testActivityUpdateAfterDebounce(): void
    {
        // Set last_activity to 70 seconds ago (outside 60 second debounce)
        $seventySecondsAgo = date('Y-m-d H:i:s', time() - 70);
        
        $this->mockUser->method('get')
            ->willReturnCallback(function($field) use ($seventySecondsAgo) {
                if ($field === 'id') {
                    return 'user-123';
                }
                if ($field === 'last_activity') {
                    return $seventySecondsAgo;
                }
                return null;
            });
        
        // Should update (outside debounce window)
        $this->mockUser->expects($this->once())
            ->method('set')
            ->with('last_activity', $this->anything());
        
        $this->mockDatabaseConnector->expects($this->once())
            ->method('update')
            ->with($this->mockUser);
        
        $provider = $this->createProvider(true, 'user-123');
        $currentUser = $provider->getCurrentUser();
        
        $this->assertNotNull($currentUser);
    }

    public function testActivityUpdateLogsErrors(): void
    {
        // Set last_activity to 70 seconds ago (should trigger update)
        $seventySecondsAgo = date('Y-m-d H:i:s', time() - 70);
        
        $this->mockUser->method('get')
            ->willReturnCallback(function($field) use ($seventySecondsAgo) {
                if ($field === 'id') {
                    return 'user-123';
                }
                if ($field === 'last_activity') {
                    return $seventySecondsAgo;
                }
                return null;
            });
        
        // Simulate update failure
        $this->mockDatabaseConnector->method('update')
            ->willThrowException(new \Exception('Database error'));
        
        // Should log error
        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with('Failed to update last_activity', $this->anything());
        
        // Should NOT throw exception (graceful failure)
        $provider = $this->createProvider(true, 'user-123');
        $currentUser = $provider->getCurrentUser();
        
        $this->assertNotNull($currentUser);
    }

    public function testCustomInactivityTimeout(): void
    {
        // Override config to 15 minutes timeout
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockConfig->method('get')
            ->willReturnCallback(function($key, $default) {
                if ($key === 'auth.inactivity_timeout') {
                    return 900; // 15 minutes
                }
                if ($key === 'auth.activity_debounce') {
                    return 60;
                }
                return $default;
            });
        
        // Set last_activity to 20 minutes ago (outside 15 minute window)
        $twentyMinutesAgo = date('Y-m-d H:i:s', time() - 1200);
        
        $this->mockUser->method('get')
            ->willReturnCallback(function($field) use ($twentyMinutesAgo) {
                if ($field === 'id') {
                    return 'user-123';
                }
                if ($field === 'last_activity') {
                    return $twentyMinutesAgo;
                }
                return null;
            });
        
        $this->expectException(SessionExpiredException::class);
        
        $provider = $this->createProvider(true, 'user-123');
        $provider->getCurrentUser();
    }
}
