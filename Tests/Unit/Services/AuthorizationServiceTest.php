<?php

namespace Gravitycar\Tests\Unit\Services;

use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Models\ModelBase;
use Monolog\Logger;
use Gravitycar\Tests\Unit\UnitTestCase;

class AuthorizationServiceTest extends UnitTestCase
{
    private AuthorizationService $authzService;
    private DatabaseConnector|MockObject $mockDatabase;
    private Logger|MockObject $mockLogger;
    private ModelBase|MockObject $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockDatabase = $this->createMock(DatabaseConnector::class);
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockUser = $this->createMock(ModelBase::class);
        
        $this->authzService = new AuthorizationService($this->mockDatabase, $this->mockLogger);
    }

    public function testHasPermissionWithValidUserAndPermission(): void
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
        
        // Mock user has admin role
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'admin', 'id' => 1]
        ]);
        
        // Mock permission exists for admin role
        $this->mockDatabase->method('select')
            ->with('permissions', ['action' => 'create', 'model' => 'Users'])
            ->willReturn([
                [
                    'id' => 1,
                    'action' => 'create',
                    'model' => 'Users',
                    'description' => 'Create users'
                ]
            ]);

        // Mock role has permission
        $this->mockDatabase->method('query')
            ->willReturn([
                ['permission_id' => 1, 'role_id' => 1]
            ]);

        // Act
        $hasPermission = $this->authzService->hasPermission('create', 'Users', $this->mockUser);

        // Assert
        $this->assertTrue($hasPermission);
    }

    public function testHasPermissionWithNoMatchingPermission(): void
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
        
        // Mock user has user role
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'user', 'id' => 2]
        ]);
        
        // Mock no permission exists
        $this->mockDatabase->method('select')
            ->with('permissions', ['action' => 'delete', 'model' => 'Users'])
            ->willReturn([]);

        // Act
        $hasPermission = $this->authzService->hasPermission('delete', 'Users', $this->mockUser);

        // Assert
        $this->assertFalse($hasPermission);
    }

    public function testHasRoleWithValidRole(): void
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
        
        // Mock user has admin and user roles
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'admin', 'id' => 1],
            ['name' => 'user', 'id' => 2]
        ]);

        // Act
        $hasRole = $this->authzService->hasRole($this->mockUser, 'admin');

        // Assert
        $this->assertTrue($hasRole);
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
        // Arrange
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        // Mock user roles query
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $this->mockDatabase->method('getConnection')->willReturn($connection);
        
        $stmt = $this->createMock(\Doctrine\DBAL\Statement::class);
        $connection->method('prepare')->willReturn($stmt);
        
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $stmt->method('executeQuery')->willReturn($result);
        
        // Mock user has user role
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'user', 'id' => 2]
        ]);

        // Act
        $hasAnyRole = $this->authzService->hasAnyRole($this->mockUser, ['admin', 'user', 'manager']);

        // Assert
        $this->assertTrue($hasAnyRole);
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
        // Arrange
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        // Mock user roles query
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $this->mockDatabase->method('getConnection')->willReturn($connection);
        
        $stmt = $this->createMock(\Doctrine\DBAL\Statement::class);
        $connection->method('prepare')->willReturn($stmt);
        
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $stmt->method('executeQuery')->willReturn($result);
        
        // Mock user has admin role
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'admin', 'id' => 1]
        ]);
        
        // Mock global admin permission exists
        $this->mockDatabase->method('select')
            ->with('permissions', ['action' => 'system.admin', 'model' => ''])
            ->willReturn([
                [
                    'id' => 99,
                    'action' => 'system.admin',
                    'model' => '',
                    'description' => 'Full system administration'
                ]
            ]);

        // Mock role has permission
        $this->mockDatabase->method('query')
            ->willReturn([
                ['permission_id' => 99, 'role_id' => 1]
            ]);

        // Act
        $hasPermission = $this->authzService->hasPermission('system.admin', '', $this->mockUser);

        // Assert
        $this->assertTrue($hasPermission);
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
        // Arrange
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        // Mock user roles query
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $this->mockDatabase->method('getConnection')->willReturn($connection);
        
        $stmt = $this->createMock(\Doctrine\DBAL\Statement::class);
        $connection->method('prepare')->willReturn($stmt);
        
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $stmt->method('executeQuery')->willReturn($result);
        
        // Mock user has some role
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'user', 'id' => 2]
        ]);
        
        // Mock permission does not exist in database
        $this->mockDatabase->method('select')
            ->with('permissions', ['action' => 'nonexistent', 'model' => 'SomeModel'])
            ->willReturn([]);

        // Act
        $hasPermission = $this->authzService->hasPermission('nonexistent', 'SomeModel', $this->mockUser);

        // Assert
        $this->assertFalse($hasPermission, 'Should deny by default when permission does not exist');
    }

    public function testGetUserRolesReturnsCorrectRoles(): void
    {
        // This tests the protected getUserRoles method indirectly through hasRole
        // Arrange
        $this->mockUser->method('get')->with('id')->willReturn(123);
        
        // Mock user roles query
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $this->mockDatabase->method('getConnection')->willReturn($connection);
        
        $stmt = $this->createMock(\Doctrine\DBAL\Statement::class);
        $connection->method('prepare')->willReturn($stmt);
        
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $stmt->method('executeQuery')->willReturn($result);
        
        // Mock multiple roles
        $result->method('fetchAllAssociative')->willReturn([
            ['name' => 'admin', 'id' => 1],
            ['name' => 'user', 'id' => 2],
            ['name' => 'manager', 'id' => 3]
        ]);

        // Act & Assert
        $this->assertTrue($this->authzService->hasRole($this->mockUser, 'admin'));
        $this->assertTrue($this->authzService->hasRole($this->mockUser, 'user'));
        $this->assertTrue($this->authzService->hasRole($this->mockUser, 'manager'));
        $this->assertFalse($this->authzService->hasRole($this->mockUser, 'guest'));
    }
}
