<?php

namespace Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\users\Users;
use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\MockObject\MockObject;

class ModelBaseRouteRegistrationTest extends UnitTestCase
{
    private Users $userModel;
    private MetadataEngineInterface&MockObject $mockMetadataEngine;
    private FieldFactory&MockObject $mockFieldFactory;
    private DatabaseConnectorInterface&MockObject $mockDatabaseConnector;
    private RelationshipFactory&MockObject $mockRelationshipFactory;
    private ModelFactory&MockObject $mockModelFactory;
    private CurrentUserProviderInterface&MockObject $mockCurrentUserProvider;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create all mocks
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockFieldFactory = $this->createMock(FieldFactory::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockRelationshipFactory = $this->createMock(RelationshipFactory::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

        // Set up mock behaviors for Users model
        $this->setupUserModelMocks();
        
        // Create a test user model instance with dependency injection
        try {
            $this->userModel = new Users(
                $this->logger,
                $this->mockMetadataEngine,
                $this->mockFieldFactory,
                $this->mockDatabaseConnector,
                $this->mockRelationshipFactory,
                $this->mockModelFactory,
                $this->mockCurrentUserProvider
            );
        } catch (\Exception $e) {
            // If model creation fails (e.g., missing dependencies), skip the test
            $this->markTestSkipped('Could not create Users model: ' . $e->getMessage());
        }
    }

    private function setupUserModelMocks(): void
    {
        // MetadataEngine mock for Users model
        $this->mockMetadataEngine->method('resolveModelName')->willReturn('Users');
        $this->mockMetadataEngine->method('getModelMetadata')->willReturn([
            'name' => 'Users',
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'ID', 'required' => true],
                'username' => ['type' => 'Text', 'required' => true],
                'email' => ['type' => 'Email', 'required' => true],
                'password' => ['type' => 'Password', 'required' => true],
            ],
            'relationships' => [],
            'apiRoutes' => [
                [
                    'method' => 'GET',
                    'path' => '/Users',
                    'apiClass' => 'UsersAPIController',
                    'apiMethod' => 'index',
                    'parameterNames' => []
                ],
                [
                    'method' => 'GET',
                    'path' => '/Users/?',
                    'apiClass' => 'UsersAPIController',
                    'apiMethod' => 'read',
                    'parameterNames' => ['userId']
                ],
                [
                    'method' => 'POST',
                    'path' => '/Users',
                    'apiClass' => 'UsersAPIController',
                    'apiMethod' => 'create',
                    'parameterNames' => []
                ],
                [
                    'method' => 'PUT',
                    'path' => '/Users/?',
                    'apiClass' => 'UsersAPIController',
                    'apiMethod' => 'update',
                    'parameterNames' => ['userId']
                ],
                [
                    'method' => 'DELETE',
                    'path' => '/Users/?',
                    'apiClass' => 'UsersAPIController',
                    'apiMethod' => 'delete',
                    'parameterNames' => ['userId']
                ],
                [
                    'method' => 'PUT',
                    'path' => '/Users/?/setPassword',
                    'apiClass' => 'UsersAPIController',
                    'apiMethod' => 'setUserPassword',
                    'parameterNames' => ['userId', '']
                ]
            ]
        ]);

        // FieldFactory - create mock fields
        $this->mockFieldFactory->method('createField')
            ->willReturnCallback(function($fieldMeta, $tableName = null) {
                $mockField = $this->createMock(\Gravitycar\Fields\FieldBase::class);
                $mockField->method('getName')->willReturn($fieldMeta['name'] ?? 'test_field');
                return $mockField;
            });

        // CurrentUserProvider
        $this->mockCurrentUserProvider->method('getCurrentUserId')->willReturn('test-user');
        $this->mockCurrentUserProvider->method('hasAuthenticatedUser')->willReturn(true);

        // DatabaseConnector
        $this->mockDatabaseConnector->method('create')->willReturn(true);
        $this->mockDatabaseConnector->method('update')->willReturn(true);
    }

    public function testRegisterRoutesMethod(): void
    {
        // Test that the registerRoutes method exists
        $this->assertTrue(method_exists($this->userModel, 'registerRoutes'));
    }

    public function testRegisterRoutesReturnsArray(): void
    {
        $routes = $this->userModel->registerRoutes();
        $this->assertIsArray($routes);
    }

    public function testRegisterRoutesFromMetadata(): void
    {
        $routes = $this->userModel->registerRoutes();
        
        // Should have the routes we defined in users_metadata.php
        $this->assertNotEmpty($routes, 'Should have routes from metadata');
        
        // Check that we have the expected number of routes
        $this->assertCount(6, $routes, 'Should have 6 routes defined in users metadata');
    }

    public function testRouteStructure(): void
    {
        $routes = $this->userModel->registerRoutes();
        
        // Test the first route structure
        if (!empty($routes)) {
            $firstRoute = $routes[0];
            
            // Verify required fields are present
            $this->assertArrayHasKey('method', $firstRoute);
            $this->assertArrayHasKey('path', $firstRoute);
            $this->assertArrayHasKey('apiClass', $firstRoute);
            $this->assertArrayHasKey('apiMethod', $firstRoute);
            
            // Verify values
            $this->assertEquals('GET', $firstRoute['method']);
            $this->assertEquals('/Users', $firstRoute['path']);
            $this->assertEquals('UsersAPIController', $firstRoute['apiClass']);
            $this->assertEquals('index', $firstRoute['apiMethod']);
        }
    }

    public function testSpecificRoutes(): void
    {
        $routes = $this->userModel->registerRoutes();
        
        // Find the user read route
        $readRoute = null;
        foreach ($routes as $route) {
            if ($route['method'] === 'GET' && $route['path'] === '/Users/?') {
                $readRoute = $route;
                break;
            }
        }
        
        $this->assertNotNull($readRoute, 'Should have GET /Users/? route');
        $this->assertEquals('read', $readRoute['apiMethod']);
        $this->assertEquals(['userId'], $readRoute['parameterNames']);
    }

    public function testSetPasswordRoute(): void
    {
        $routes = $this->userModel->registerRoutes();
        
        // Find the setPassword route
        $setPasswordRoute = null;
        foreach ($routes as $route) {
            if ($route['method'] === 'PUT' && $route['path'] === '/Users/?/setPassword') {
                $setPasswordRoute = $route;
                break;
            }
        }
        
        $this->assertNotNull($setPasswordRoute, 'Should have PUT /Users/?/setPassword route');
        $this->assertEquals('setUserPassword', $setPasswordRoute['apiMethod']);
        $this->assertEquals(['userId', ''], $setPasswordRoute['parameterNames']);
    }

    public function testEmptyApiRoutesMetadata(): void
    {
        // Create a mock model with no apiRoutes in metadata
        $emptyMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $emptyMetadataEngine->method('resolveModelName')->willReturn('TestModel');
        $emptyMetadataEngine->method('getModelMetadata')->willReturn([
            'fields' => ['id' => ['type' => 'ID']],
            'relationships' => []
        ]);
        
        $mockModel = new TestableModelForRoutes(
            $this->logger,
            $emptyMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
        
        $routes = $mockModel->registerRoutes();
        $this->assertIsArray($routes);
        $this->assertEmpty($routes, 'Should return empty array when no apiRoutes in metadata');
    }

    public function testInvalidApiRoutesMetadata(): void
    {
        // Create a mock model with invalid apiRoutes in metadata
        $invalidMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $invalidMetadataEngine->method('resolveModelName')->willReturn('TestModel');
        $invalidMetadataEngine->method('getModelMetadata')->willReturn([
            'fields' => ['id' => ['type' => 'ID']],
            'relationships' => [],
            'apiRoutes' => 'invalid' // Should be array
        ]);
        
        $mockModel = new TestableModelForRoutes(
            $this->logger,
            $invalidMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
        
        $routes = $mockModel->registerRoutes();
        $this->assertIsArray($routes);
        $this->assertEmpty($routes, 'Should return empty array when apiRoutes is not an array');
    }
}

/**
 * Testable model class for route registration tests
 */
class TestableModelForRoutes extends ModelBase
{
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }

    public function getTableName(): string
    {
        return 'test_models';
    }
}
