<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\TestCurrentUserProvider;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Models\ModelBase;
use Psr\Log\LoggerInterface;

class TestCurrentUserProviderTest extends TestCase
{
    private TestCurrentUserProvider $provider;
    private LoggerInterface|MockObject $mockLogger;
    private ModelBase|MockObject $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockUser = $this->createMock(ModelBase::class);
        
        // Create provider with injected dependencies
        $this->provider = new TestCurrentUserProvider($this->mockLogger);
    }

    public function testImplementsCurrentUserProviderInterface(): void
    {
        $this->assertInstanceOf(CurrentUserProviderInterface::class, $this->provider);
    }

    public function testDefaultConstructorWithNoUser(): void
    {
        $provider = new TestCurrentUserProvider($this->mockLogger);
        
        $this->assertNull($provider->getCurrentUser());
        $this->assertEquals('system', $provider->getCurrentUserId());
        $this->assertFalse($provider->hasAuthenticatedUser());
    }

    public function testConstructorWithTestUser(): void
    {
        $this->mockUser->method('get')->with('id')->willReturn('test-user-123');
        
        $provider = new TestCurrentUserProvider($this->mockLogger, $this->mockUser, true);
        
        $this->assertSame($this->mockUser, $provider->getCurrentUser());
        $this->assertEquals('test-user-123', $provider->getCurrentUserId());
        $this->assertTrue($provider->hasAuthenticatedUser());
    }

    public function testSetTestUserWithAuthenticatedUser(): void
    {
        $this->mockUser->method('get')->with('id')->willReturn('user-456');
        
        $this->provider->setTestUser($this->mockUser, true);
        
        $this->assertSame($this->mockUser, $this->provider->getCurrentUser());
        $this->assertEquals('user-456', $this->provider->getCurrentUserId());
        $this->assertTrue($this->provider->hasAuthenticatedUser());
    }

    public function testSetTestUserWithUnauthenticatedUser(): void
    {
        $this->mockUser->method('get')->with('id')->willReturn('user-789');
        
        $this->provider->setTestUser($this->mockUser, false);
        
        $this->assertSame($this->mockUser, $this->provider->getCurrentUser());
        $this->assertEquals('user-789', $this->provider->getCurrentUserId());
        $this->assertFalse($this->provider->hasAuthenticatedUser());
    }

    public function testSetTestUserToNull(): void
    {
        // First set a user
        $this->provider->setTestUser($this->mockUser, true);
        
        // Then clear it
        $this->provider->setTestUser(null, false);
        
        $this->assertNull($this->provider->getCurrentUser());
        $this->assertEquals('system', $this->provider->getCurrentUserId());
        $this->assertFalse($this->provider->hasAuthenticatedUser());
    }

    public function testGetCurrentUserIdWithNullUser(): void
    {
        $this->provider->setTestUser(null);
        
        $result = $this->provider->getCurrentUserId();
        
        $this->assertEquals('system', $result);
    }

    public function testConstructorRequiresDependencies(): void
    {
        // Test that dependency injection is working correctly
        $logger = $this->createMock(LoggerInterface::class);
        $user = $this->createMock(ModelBase::class);
        
        $provider = new TestCurrentUserProvider($logger, $user, true);
        
        $this->assertInstanceOf(TestCurrentUserProvider::class, $provider);
    }
}