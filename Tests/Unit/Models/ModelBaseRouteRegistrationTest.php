<?php

namespace Tests\Unit\Models;

use Gravitycar\Models\users\Users;
use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\TestCase;

class ModelBaseRouteRegistrationTest extends TestCase
{
    protected Logger $logger;
    protected Users $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a real logger to avoid type issues
        $this->logger = new Logger('test');
        $this->logger->pushHandler(new NullHandler());
        
        // Create a test user model instance
        // Note: This will trigger metadata loading, which should include our new apiRoutes
        try {
            $this->userModel = new Users($this->logger);
        } catch (\Exception $e) {
            // If model creation fails (e.g., missing dependencies), skip the test
            $this->markTestSkipped('Could not create Users model: ' . $e->getMessage());
        }
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
        // Create a mock ModelBase with no apiRoutes in metadata
        $mockModel = $this->createPartialMock(ModelBase::class, ['loadMetadata']);
        $reflection = new \ReflectionClass($mockModel);
        
        // Set metadata without apiRoutes
        $metadataProperty = $reflection->getProperty('metadata');
        $metadataProperty->setAccessible(true);
        $metadataProperty->setValue($mockModel, [
            'fields' => ['id' => ['type' => 'ID']],
            'relationships' => []
        ]);
        
        $routes = $mockModel->registerRoutes();
        $this->assertIsArray($routes);
        $this->assertEmpty($routes, 'Should return empty array when no apiRoutes in metadata');
    }

    public function testInvalidApiRoutesMetadata(): void
    {
        // Create a mock ModelBase with invalid apiRoutes in metadata
        $mockModel = $this->createPartialMock(ModelBase::class, ['loadMetadata']);
        $reflection = new \ReflectionClass($mockModel);
        
        // Set metadata with invalid apiRoutes (not an array)
        $metadataProperty = $reflection->getProperty('metadata');
        $metadataProperty->setAccessible(true);
        $metadataProperty->setValue($mockModel, [
            'fields' => ['id' => ['type' => 'ID']],
            'relationships' => [],
            'apiRoutes' => 'invalid' // Should be array
        ]);
        
        $routes = $mockModel->registerRoutes();
        $this->assertIsArray($routes);
        $this->assertEmpty($routes, 'Should return empty array when apiRoutes is not an array');
    }
}
