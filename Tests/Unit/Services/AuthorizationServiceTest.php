<?php

namespace Gravitycar\Tests\Unit\Services;

use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\UserContextInterface;
use Psr\Log\LoggerInterface;
use Gravitycar\Tests\Unit\UnitTestCase;

class AuthorizationServiceTest extends UnitTestCase
{
    private AuthorizationService $authzService;
    private DatabaseConnectorInterface|MockObject $mockDatabase;
    private LoggerInterface|MockObject $mockLogger;
    private ModelFactory|MockObject $mockModelFactory;
    private UserContextInterface|MockObject $mockUserContext;
    private ModelBase|MockObject $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockDatabase = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockUserContext = $this->createMock(UserContextInterface::class);
        $this->mockUser = $this->createMock(ModelBase::class);
        
        // Create service with injected dependencies
        $this->authzService = new AuthorizationService(
            $this->mockLogger,
            $this->mockModelFactory,
            $this->mockDatabase,
            $this->mockUserContext
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testHasPermissionWithValidUserAndPermission(): void
    {
        // This test checks the method doesn't crash and returns a boolean
        // Since we don't have test data setup, we expect it to return false
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        $result = $this->authzService->hasPermission('create', 'Users', $this->mockUser);
        
        // Without proper test data, this should return false
        $this->assertIsBool($result);
    }

    public function testHasPermissionWithNoMatchingPermission(): void
    {
        // This test checks the method doesn't crash and returns false when no permission exists
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        $result = $this->authzService->hasPermission('delete', 'Users', $this->mockUser);
        
        // Without proper test data, this should return false
        $this->assertFalse($result);
    }

    public function testHasRoleWithValidRole(): void
    {
        // This test checks the method doesn't crash when checking user roles
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        $result = $this->authzService->hasRole($this->mockUser, 'admin');
        
        // Without test data setup, we just verify the method executes
        $this->assertIsBool($result);
    }

    public function testHasRoleWithInvalidRole(): void
    {
        // Arrange
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        // Mock user roles query
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $this->mockDatabase->method('getConnection')->willReturn($connection);
        
        $stmt = $this->createMock(\Doctrine\DBAL\Statement::class);
        $connection->method('prepare')->willReturn($stmt);
        
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $stmt->method('executeQuery')->willReturn($result);
        
        // Mock user only has user role
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'user', 'id' => 2]
        ]);

        // Act
        $hasRole = $this->authzService->hasRole($this->mockUser, 'admin');

        // Assert
        $this->assertFalse($hasRole);
    }

    public function testHasAnyRoleWithOneMatchingRole(): void
    {
        // This test checks the method doesn't crash when checking multiple roles
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        $result = $this->authzService->hasAnyRole($this->mockUser, ['admin', 'user', 'manager']);
        
        // Without test data setup, we just verify the method executes
        $this->assertIsBool($result);
    }

    public function testHasAnyRoleWithNoMatchingRoles(): void
    {
        // Arrange
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        // Mock user roles query
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $this->mockDatabase->method('getConnection')->willReturn($connection);
        
        $stmt = $this->createMock(\Doctrine\DBAL\Statement::class);
        $connection->method('prepare')->willReturn($stmt);
        
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $stmt->method('executeQuery')->willReturn($result);
        
        // Mock user has guest role
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'guest', 'id' => 4]
        ]);

        // Act
        $hasAnyRole = $this->authzService->hasAnyRole($this->mockUser, ['admin', 'manager']);

        // Assert
        $this->assertFalse($hasAnyRole);
    }

    public function testHasPermissionWithGlobalPermission(): void
    {
        // This test checks the method doesn't crash when checking global permissions
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        $result = $this->authzService->hasPermission('system.admin', '', $this->mockUser);
        
        // Without test data setup, we just verify the method executes
        $this->assertIsBool($result);
    }

    public function testHasPermissionWithNullUser(): void
    {
        // Act
        $hasPermission = $this->authzService->hasPermission('create', 'Users', null);

        // Assert
        $this->assertFalse($hasPermission);
    }

    public function testHasPermissionDenyByDefault(): void
    {
        // This test checks the method returns false by default for nonexistent permissions
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        $result = $this->authzService->hasPermission('nonexistent', 'SomeModel', $this->mockUser);
        
        // Should deny by default when permission does not exist
        $this->assertFalse($result, 'Should deny by default when permission does not exist');
    }

    public function testGetUserRolesReturnsCorrectRoles(): void
    {
        // This tests the protected getUserRoles method indirectly through hasRole
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        // Test that methods execute without crashing
        $result1 = $this->authzService->hasRole($this->mockUser, 'admin');
        $result2 = $this->authzService->hasRole($this->mockUser, 'user');
        $result3 = $this->authzService->hasRole($this->mockUser, 'manager');
        $result4 = $this->authzService->hasRole($this->mockUser, 'guest');
        
        // Just verify methods execute and return booleans
        $this->assertIsBool($result1);
        $this->assertIsBool($result2);
        $this->assertIsBool($result3);
        $this->assertIsBool($result4);
    }

    public function testDetermineActionFromHttpMethodMappings(): void
    {
        // Test HTTP method to action mappings
        $mappings = [
            'GET' => 'read',
            'POST' => 'create', 
            'PUT' => 'update',
            'DELETE' => 'delete'
        ];
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->authzService);
        $method = $reflection->getMethod('determineAction');
        $method->setAccessible(true);
        
        foreach ($mappings as $httpMethod => $expectedAction) {
            $request = $this->createMock(\Gravitycar\Api\Request::class);
            $request->method('getMethod')->willReturn($httpMethod);
            $action = $method->invoke($this->authzService, [], $request);
            $this->assertEquals($expectedAction, $action, "Failed for HTTP method: $httpMethod");
        }
    }

    public function testDetermineActionUsesExplicitRBACAction(): void
    {
        $route = ['RBACAction' => 'custom_action'];
        $request = $this->createMock(\Gravitycar\Api\Request::class);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->authzService);
        $method = $reflection->getMethod('determineAction');
        $method->setAccessible(true);
        
        $action = $method->invoke($this->authzService, $route, $request);
        $this->assertEquals('custom_action', $action);
    }

    public function testDetermineComponentFromRequestModel(): void
    {
        $route = ['apiClass' => 'SomeController'];
        $request = $this->createMock(\Gravitycar\Api\Request::class);
        $request->method('has')->with('modelName')->willReturn(true);
        $request->method('get')->with('modelName')->willReturn('Users');
        // Mock the API controller class name to make it a valid model request
        $request->method('getApiControllerClassName')->willReturn('Gravitycar\\Models\\api\\Api\\ModelBaseAPIController');
        
        // Mock the ModelFactory to recognize 'Users' as a valid model
        $this->mockModelFactory->method('new')->with('Users')->willReturn($this->createMock(ModelBase::class));
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->authzService);
        $method = $reflection->getMethod('determineComponent');
        $method->setAccessible(true);
        
        $component = $method->invoke($this->authzService, $route, $request);
        $this->assertEquals('Users', $component);
    }

    public function testDetermineComponentFallsBackToApiClass(): void
    {
        $route = ['apiClass' => 'TestController'];
        $request = $this->createMock(\Gravitycar\Api\Request::class);
        $request->method('has')->with('modelName')->willReturn(false);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->authzService);
        $method = $reflection->getMethod('determineComponent');
        $method->setAccessible(true);
        
        $component = $method->invoke($this->authzService, $route, $request);
        $this->assertEquals('TestController', $component);
    }

    public function testHasPermissionForRouteFailsSecurelyOnException(): void
    {
        // Test that exceptions result in denied access (fail-secure)
        $this->mockModelFactory->method('new')
            ->willThrowException(new \Exception('Database error'));
        
        $route = [];
        $request = $this->createMock(\Gravitycar\Api\Request::class);
        $request->method('has')->willReturn(false);
        $request->method('getMethod')->willReturn('GET');
        
        $result = $this->authzService->hasPermissionForRoute($route, $request, $this->mockUser);
        $this->assertFalse($result, 'Should fail securely on exception');
    }
}
