<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\CLICurrentUserProvider;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Psr\Log\LoggerInterface;

class CLICurrentUserProviderTest extends TestCase
{
    private CLICurrentUserProvider $provider;
    private LoggerInterface|MockObject $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Create provider with injected dependencies
        $this->provider = new CLICurrentUserProvider($this->mockLogger);
    }

    public function testImplementsCurrentUserProviderInterface(): void
    {
        $this->assertInstanceOf(CurrentUserProviderInterface::class, $this->provider);
    }

    public function testGetCurrentUserReturnsNull(): void
    {
        // CLI operations don't have user authentication context
        $result = $this->provider->getCurrentUser();
        
        $this->assertNull($result);
    }

    public function testGetCurrentUserIdReturnsSystem(): void
    {
        // CLI operations should return 'system' for audit trails
        $result = $this->provider->getCurrentUserId();
        
        $this->assertEquals('system', $result);
    }

    public function testHasAuthenticatedUserReturnsFalse(): void
    {
        // CLI operations never have authenticated users
        $result = $this->provider->hasAuthenticatedUser();
        
        $this->assertFalse($result);
    }

    public function testConstructorRequiresDependencies(): void
    {
        // Test that dependency injection is working correctly
        $logger = $this->createMock(LoggerInterface::class);
        
        $provider = new CLICurrentUserProvider($logger);
        
        $this->assertInstanceOf(CLICurrentUserProvider::class, $provider);
    }

    public function testAllMethodsConsistentWithCLIContext(): void
    {
        // Test that all methods work together consistently for CLI context
        $this->assertNull($this->provider->getCurrentUser());
        $this->assertEquals('system', $this->provider->getCurrentUserId());
        $this->assertFalse($this->provider->hasAuthenticatedUser());
    }
}