<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\UserContext;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Models\ModelBase;

class UserContextTest extends TestCase
{
    private UserContext $userContext;
    private CurrentUserProviderInterface|MockObject $mockCurrentUserProvider;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock
        $this->mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
        
        // Create service with injected dependency
        $this->userContext = new UserContext($this->mockCurrentUserProvider);
    }

    public function testGetCurrentUserReturnsUserFromProvider(): void
    {
        // Create a mock user
        $mockUser = $this->createMock(ModelBase::class);
        $mockUser->method('get')->with('id')->willReturn('user123');
        
        // Configure provider to return the mock user
        $this->mockCurrentUserProvider
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);
        
        // Test the method
        $result = $this->userContext->getCurrentUser();
        
        $this->assertSame($mockUser, $result);
    }

    public function testGetCurrentUserReturnsNullWhenNoUser(): void
    {
        // Configure provider to return null (no authenticated user)
        $this->mockCurrentUserProvider
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);
        
        // Test the method
        $result = $this->userContext->getCurrentUser();
        
        $this->assertNull($result);
    }

    public function testConstructorRequiresCurrentUserProvider(): void
    {
        // This test ensures dependency injection is working correctly
        $provider = $this->createMock(CurrentUserProviderInterface::class);
        $userContext = new UserContext($provider);
        
        $this->assertInstanceOf(UserContext::class, $userContext);
    }

    public function testImplementsUserContextInterface(): void
    {
        $this->assertInstanceOf(\Gravitycar\Contracts\UserContextInterface::class, $this->userContext);
    }
}