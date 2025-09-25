<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\TestCase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test ModelBase rolesAndActions functionality
 */
class ModelBaseRolesAndActionsTest extends TestCase
{
    private ModelBase $testModel;
    private MockObject $mockLogger;
    private MockObject $mockMetadataEngine;
    private MockObject $mockFieldFactory;
    private MockObject $mockDatabaseConnector;
    private MockObject $mockRelationshipFactory;
    private MockObject $mockModelFactory;
    private MockObject $mockCurrentUserProvider;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create all required mocks
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockFieldFactory = $this->createMock(FieldFactory::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockRelationshipFactory = $this->createMock(RelationshipFactory::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
    }

    public function testDefaultRolesAndActionsStructure()
    {
        // Create a mock model with minimal required metadata
        $model = $this->createMockModelWithMetadata([
            'fields' => ['id' => ['type' => 'ID']], // Required for validation
        ]);
        
        $rolesAndActions = $model->getRolesAndActions();
        
        // Verify default structure
        $this->assertArrayHasKey('admin', $rolesAndActions);
        $this->assertArrayHasKey('manager', $rolesAndActions);
        $this->assertArrayHasKey('user', $rolesAndActions);
        $this->assertArrayHasKey('guest', $rolesAndActions);
        
        // Verify default permissions
        $this->assertEquals(['*'], $rolesAndActions['admin']);
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['manager']);
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['user']);
        $this->assertEquals([], $rolesAndActions['guest']); // Guest has no default permissions
    }

    public function testPartialOverrideRolesAndActions()
    {
        // Test partial override - only modify 'user' and 'guest' roles
        $metadata = [
            'fields' => ['id' => ['type' => 'ID']],
            'rolesAndActions' => [
                'user' => ['list', 'read'], // Restricted from defaults
                'guest' => ['list'] // Granted some access
            ]
        ];
        
        $model = $this->createMockModelWithMetadata($metadata);
        $rolesAndActions = $model->getRolesAndActions();
        
        // Verify overrides applied
        $this->assertEquals(['list', 'read'], $rolesAndActions['user']);
        $this->assertEquals(['list'], $rolesAndActions['guest']);
        
        // Verify non-overridden roles keep defaults
        $this->assertEquals(['*'], $rolesAndActions['admin']);
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['manager']);
    }

    public function testCompleteOverrideRolesAndActions()
    {
        // Test complete override - modify all roles including new ones
        $metadata = [
            'fields' => ['id' => ['type' => 'ID']],
            'rolesAndActions' => [
                'admin' => ['list', 'read', 'create', 'update', 'delete'], // Remove wildcard
                'manager' => ['list', 'read', 'create'],
                'user' => ['list', 'read'],
                'guest' => [],
                'editor' => ['list', 'read', 'create', 'update'] // New role
            ]
        ];
        
        $model = $this->createMockModelWithMetadata($metadata);
        $rolesAndActions = $model->getRolesAndActions();
        
        // Verify all overrides applied
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['admin']);
        $this->assertEquals(['list', 'read', 'create'], $rolesAndActions['manager']);
        $this->assertEquals(['list', 'read'], $rolesAndActions['user']);
        $this->assertEquals([], $rolesAndActions['guest']);
        $this->assertEquals(['list', 'read', 'create', 'update'], $rolesAndActions['editor']);
    }

    public function testGetAllPossibleActions()
    {
        // Test with wildcard - should return all standard actions
        $metadata = [
            'fields' => ['id' => ['type' => 'ID']],
            'rolesAndActions' => [
                'admin' => ['*'],
                'user' => ['read']
            ]
        ];
        $model = $this->createMockModelWithMetadata($metadata);
        
        $actions = $model->getAllPossibleActions();
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $actions);
        
        // Test with specific actions only (no wildcard)
        // First clear default roles to ensure clean test
        $metadata = [
            'fields' => ['id' => ['type' => 'ID']],
            'rolesAndActions' => [
                'user' => ['read', 'list'],
                'manager' => ['create', 'update']
            ]
        ];
        $model = $this->createMockModelWithMetadata($metadata);
        
        // Clear default roles and use only metadata roles
        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('rolesAndActions');
        $property->setAccessible(true);
        $property->setValue($model, [
            'user' => ['read', 'list'],
            'manager' => ['create', 'update']
        ]);
        
        $actions = $model->getAllPossibleActions();
        sort($actions); // Sort for consistent comparison
        $this->assertEquals(['create', 'list', 'read', 'update'], $actions);
    }

    public function testInvalidRolesAndActionsFormat()
    {
        // Test invalid format handling
        $metadata = [
            'fields' => ['id' => ['type' => 'ID']],
            'rolesAndActions' => [
                'user' => 'invalid_string_not_array', // Invalid format
                'manager' => ['valid', 'actions']
            ]
        ];
        
        $model = $this->createMockModelWithMetadata($metadata);
        
        $rolesAndActions = $model->getRolesAndActions();
        
        // Verify invalid role keeps default, valid role gets override
        $this->assertEquals(['list', 'read', 'create', 'update', 'delete'], $rolesAndActions['user']);
        $this->assertEquals(['valid', 'actions'], $rolesAndActions['manager']);
    }

    public function testEmptyMetadataHandling()
    {
        $model = $this->createMockModelWithMetadata([
            'fields' => ['id' => ['type' => 'ID']]
        ]);
        
        $rolesAndActions = $model->getRolesAndActions();
        
        // Should return defaults when metadata is empty
        $this->assertArrayHasKey('admin', $rolesAndActions);
        $this->assertEquals(['*'], $rolesAndActions['admin']);
    }

    public function testNullMetadataOverridesHandling()
    {
        $model = $this->createMockModelWithMetadata([
            'fields' => ['id' => ['type' => 'ID']],
            'rolesAndActions' => null
        ]);
        
        $rolesAndActions = $model->getRolesAndActions();
        
        // Should return defaults when rolesAndActions is null
        $this->assertArrayHasKey('admin', $rolesAndActions);
        $this->assertEquals(['*'], $rolesAndActions['admin']);
    }

    /**
     * Helper method to create a mock model with specific metadata
     */
    private function createMockModelWithMetadata(array $metadata): ModelBase
    {
        // Create a specific mock for this test that returns our metadata
        $mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $mockMetadataEngine->method('resolveModelName')
                          ->willReturn('TestModel');
        $mockMetadataEngine->method('getModelMetadata')
                          ->willReturn($metadata);
        
        // Create a concrete test model class for testing
        $model = new class(
            $this->mockLogger,
            $mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        ) extends ModelBase {
            public function getModelName(): string {
                return 'TestModel';
            }
        };
        
        return $model;
    }
}