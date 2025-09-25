<?php

namespace Gravitycar\Tests\Unit\Services;

use Gravitycar\Tests\TestCase;
use Gravitycar\Services\PermissionsBuilder;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Exceptions\PermissionsBuilderException;
use Gravitycar\Models\ModelBase;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test PermissionsBuilder service functionality
 */
class PermissionsBuilderTest extends TestCase
{
    private ?PermissionsBuilder $permissionsBuilder = null;
    private MockObject $mockLogger;
    private MockObject $mockModelFactory;
    private MockObject $mockDatabaseConnector;
    private MockObject $mockMetadataEngine;
    private MockObject $mockAPIRouteRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create all required mocks
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockAPIRouteRegistry = $this->createMock(APIRouteRegistry::class);
    }

    private function createPermissionsBuilder(): PermissionsBuilder
    {
        if ($this->permissionsBuilder === null) {
            $this->permissionsBuilder = new PermissionsBuilder(
                $this->mockLogger,
                $this->mockModelFactory,
                $this->mockDatabaseConnector,
                $this->mockMetadataEngine,
                $this->mockAPIRouteRegistry
            );
        }
        return $this->permissionsBuilder;
    }

    public function testBuildPermissionsForModelSuccess()
    {
        // Mock model with rolesAndActions
        $mockModel = $this->createMock(ModelBase::class);
        $mockModel->method('getRolesAndActions')
            ->willReturn([
                'admin' => ['*'],
                'user' => ['read', 'list']
            ]);
        $mockModel->method('getAllPossibleActions')
            ->willReturn(['read', 'list']);
        
        // Mock Permissions model
        $mockPermissionsModel = $this->createMock(ModelBase::class);
        $mockPermissionsModel->method('find')
            ->willReturn([]); // No existing permissions
        $mockPermissionsModel->method('create')
            ->willReturn(true);
        
        // Mock Role models
        $mockAdminRole = $this->createMock(ModelBase::class);
        $mockUserRole = $this->createMock(ModelBase::class);
        
        // Mock Roles model to find roles by name
        $mockRolesModel = $this->createMock(ModelBase::class);
        $mockRolesModel->method('find')
            ->willReturnMap([
                [['name' => 'admin'], [], [], [$mockAdminRole]],
                [['name' => 'user'], [], [], [$mockUserRole]]
            ]);
        
        // Setup ModelFactory expectations
        $this->mockModelFactory->method('new')
            ->willReturnMap([
                ['TestModel', $mockModel],
                ['Permissions', $mockPermissionsModel],
                ['Roles', $mockRolesModel]
            ]);
        
        // Test the method
        $permissionsBuilder = $this->createPermissionsBuilder();
        $result = $permissionsBuilder->buildPermissionsForModel('TestModel');
        
        // Verify results
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testBuildPermissionsForModelHandlesExistingPermissions()
    {
        // Mock model
        $mockModel = $this->createMock(ModelBase::class);
        $mockModel->method('getRolesAndActions')
            ->willReturn(['admin' => ['read']]);
        $mockModel->method('getAllPossibleActions')
            ->willReturn(['read']);
        
        // Mock existing permission
        $existingPermission = $this->createMock(ModelBase::class);
        
        // Mock Permissions model that finds existing permission
        $mockPermissionsModel = $this->createMock(ModelBase::class);
        $mockPermissionsModel->method('find')
            ->willReturn([$existingPermission]); // Existing permission found

        // Mock Roles model to find admin role
        $mockAdminRole = $this->createMock(ModelBase::class);
        $mockRolesModel = $this->createMock(ModelBase::class);
        $mockRolesModel->method('find')
            ->willReturn([$mockAdminRole]);

        $this->mockModelFactory->method('new')
            ->willReturnMap([
                ['TestModel', $mockModel],
                ['Permissions', $mockPermissionsModel],
                ['Roles', $mockRolesModel]
            ]);        $permissionsBuilder = $this->createPermissionsBuilder();
        $result = $permissionsBuilder->buildPermissionsForModel('TestModel');
        
        $this->assertIsInt($result);
    }

    public function testBuildPermissionsForModelThrowsExceptionOnFailure()
    {
        // Mock ModelFactory to throw exception
        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('TestModel')
            ->willThrowException(new \Exception('Model creation failed'));
        
        $this->expectException(PermissionsBuilderException::class);
        $this->expectExceptionMessage('Failed to build permissions for model TestModel');
        
        $permissionsBuilder = $this->createPermissionsBuilder();
        $permissionsBuilder->buildPermissionsForModel('TestModel');
    }

    public function testBuildAllModelPermissionsSuccess()
    {
        // Mock MetadataEngine to return available models
        $this->mockMetadataEngine->expects($this->once())
            ->method('getAvailableModels')
            ->willReturn(['Users', 'Movies', 'Roles']);
        
        // Mock successful builds for each model
        $mockModel = $this->createMock(ModelBase::class);
        $mockModel->method('getRolesAndActions')
            ->willReturn(['admin' => ['*']]);
        $mockModel->method('getAllPossibleActions')
            ->willReturn(['read']);
        
        $mockPermissionsModel = $this->createMock(ModelBase::class);
        $mockPermissionsModel->method('find')
            ->willReturn([]);
        $mockPermissionsModel->method('create')
            ->willReturn(true);

        // Mock Roles model for finding roles by name (separate from model being built)
        $mockAdminRole = $this->createMock(ModelBase::class);
        $mockRolesForLookup = $this->createMock(ModelBase::class);
        $mockRolesForLookup->method('find')
            ->willReturn([$mockAdminRole]);

        $this->mockModelFactory->method('new')
            ->willReturnCallback(function($modelName) use ($mockModel, $mockPermissionsModel, $mockRolesForLookup) {
                switch($modelName) {
                    case 'Users':
                    case 'Movies': 
                        return $mockModel;
                    case 'Roles':
                        // When called for role lookup, return the lookup mock
                        return $mockRolesForLookup;
                    case 'Permissions':
                        return $mockPermissionsModel;
                    default:
                        return $mockModel;
                }
            });        $permissionsBuilder = $this->createPermissionsBuilder();
        $result = $permissionsBuilder->buildAllModelPermissions();
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testBuildAllControllerPermissionsSuccess()
    {
        // Mock APIRouteRegistry to return controllers
        $mockController = $this->createMock(\Gravitycar\Api\ApiControllerBase::class);
        $mockController->method('getRolesAndActions')
            ->willReturn(['admin' => ['execute']]);
        
        $this->mockAPIRouteRegistry->expects($this->once())
            ->method('getAllRegisteredControllers')
            ->willReturn(['TestController' => $mockController]);

        // Mock Permissions model
        $mockPermissionsModel = $this->createMock(ModelBase::class);
        $mockPermissionsModel->method('find')->willReturn([]);
        $mockPermissionsModel->method('create')->willReturn(true);
        
        // Mock Roles model for finding roles by name
        $mockAdminRole = $this->createMock(ModelBase::class);
        $mockRolesModel = $this->createMock(ModelBase::class);
        $mockRolesModel->method('find')->willReturn([$mockAdminRole]);
        
        $this->mockModelFactory->method('new')
            ->willReturnMap([
                ['Permissions', $mockPermissionsModel],
                ['Roles', $mockRolesModel]
            ]);
        
        $permissionsBuilder = $this->createPermissionsBuilder();
        $result = $permissionsBuilder->buildAllControllerPermissions();
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testClearExistingPermissions()
    {
        // Mock Permissions model for truncation
        $mockPermissionsModel = $this->createMock(ModelBase::class);
        $mockPermissionsModel->method('getTableName')
            ->willReturn('permissions');
        
        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('Permissions')
            ->willReturn($mockPermissionsModel);
        
        // Mock DatabaseConnector to expect truncate call
        $this->mockDatabaseConnector->expects($this->once())
            ->method('truncate')
            ->with($mockPermissionsModel);
        
        // Use reflection to test protected method
        $permissionsBuilder = $this->createPermissionsBuilder();
        $reflection = new \ReflectionClass($permissionsBuilder);
        $method = $reflection->getMethod('clearExistingPermissions');
        $method->setAccessible(true);
        
        // Should not throw exception
        $method->invoke($permissionsBuilder);
        
        $this->assertTrue(true); // If we reach here, no exception was thrown
    }

    public function testCreatePermissionRecordCreatesNewPermission()
    {
        $mockPermissionsModel = $this->createMock(ModelBase::class);
        $mockPermissionsModel->method('find')
            ->willReturn([]); // No existing permission
        $mockPermissionsModel->expects($this->exactly(5))
            ->method('set');  // Allow 5 calls to set() for different fields
        $mockPermissionsModel->expects($this->once())
            ->method('create')
            ->willReturn(true);
        
        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('Permissions')
            ->willReturn($mockPermissionsModel);
        
        // Use reflection to test protected method
        $permissionsBuilder = $this->createPermissionsBuilder();
        $reflection = new \ReflectionClass($permissionsBuilder);
        $method = $reflection->getMethod('createPermissionRecord');
        $method->setAccessible(true);
        
        $result = $method->invoke($permissionsBuilder, 'TestModel', 'read');
        
        $this->assertInstanceOf(ModelBase::class, $result);
    }

    public function testCreatePermissionRecordReturnsExistingPermission()
    {
        $existingPermission = $this->createMock(ModelBase::class);
        
        $mockPermissionsModel = $this->createMock(ModelBase::class);
        $mockPermissionsModel->method('find')
            ->willReturn([$existingPermission]); // Existing permission found
        
        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('Permissions')
            ->willReturn($mockPermissionsModel);
        
        // Use reflection to test protected method
        $permissionsBuilder = $this->createPermissionsBuilder();
        $reflection = new \ReflectionClass($permissionsBuilder);
        $method = $reflection->getMethod('createPermissionRecord');
        $method->setAccessible(true);
        
        $result = $method->invoke($permissionsBuilder, 'TestModel', 'read');
        
        $this->assertSame($existingPermission, $result);
    }

    public function testGenerateIdReturnsValidUuid()
    {
        // Use reflection to test protected method
        $permissionsBuilder = $this->createPermissionsBuilder();
        $reflection = new \ReflectionClass($permissionsBuilder);
        $method = $reflection->getMethod('generateId');
        $method->setAccessible(true);
        
        $id = $method->invoke($permissionsBuilder);
        
        $this->assertIsString($id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id);
    }
}