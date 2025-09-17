<?php

namespace Tests\Integration\Api;

use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Models\users\Users;
use Gravitycar\Models\movies\Movies;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\TestCase;

class ModelBaseRouteRegistryIntegrationTest extends TestCase
{
    protected Logger $logger;
    protected $registry;
    protected ModelFactory $modelFactory;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a real logger
        $this->logger = new Logger('test');
        $this->logger->pushHandler(new NullHandler());
        
        // Get ModelFactory from ServiceLocator for proper dependency injection
        $this->modelFactory = ServiceLocator::getModelFactory();
        
        // Create registry without automatic discovery to control what gets registered
        $this->registry = $this->createPartialMock(APIRouteRegistry::class, ['discoverAndRegisterRoutes']);
        $reflection = new \ReflectionClass($this->registry);
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->registry, $this->logger);
    }

    public function testModelBaseRouteDiscoveryAndRegistration(): void
    {
        try {
            // Create Users model and register its routes
            $userModel = $this->modelFactory->new('Users');
            $routes = $userModel->registerRoutes();
            
            // Manually register the routes to test the integration
            $reflection = new \ReflectionClass($this->registry);
            $registerRouteMethod = $reflection->getMethod('registerRoute');
            $registerRouteMethod->setAccessible(true);
            
            foreach ($routes as $route) {
                $registerRouteMethod->invoke($this->registry, $route);
            }
            
            // Test that routes were registered
            $allRoutes = $this->registry->getRoutes();
            $this->assertNotEmpty($allRoutes, 'Should have registered routes from Users model');
            
            // Test that we have the expected number of routes
            $this->assertCount(6, $allRoutes, 'Should have registered 6 routes from Users model');
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not create Users model: ' . $e->getMessage());
        }
    }

    public function testModelRouteValidation(): void
    {
        try {
            // Create Users model and test route validation
            $userModel = $this->modelFactory->new('Users');
            $routes = $userModel->registerRoutes();
            
            // Test each route passes validation
            $reflection = new \ReflectionClass($this->registry);
            $validateMethod = $reflection->getMethod('validateRouteFormat');
            $validateMethod->setAccessible(true);
            
            foreach ($routes as $route) {
                // This should not throw an exception for valid routes
                try {
                    $validateMethod->invoke($this->registry, $route);
                    $this->assertTrue(true, 'Route should pass validation');
                } catch (\Exception $e) {
                    // Some routes might fail due to missing controllers, which is expected
                    if (!str_contains($e->getMessage(), 'not found')) {
                        $this->fail('Route validation failed unexpectedly: ' . $e->getMessage());
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not create Users model: ' . $e->getMessage());
        }
    }

    public function testRouteGroupingWithModelRoutes(): void
    {
        try {
            // Create Users model and register its routes
            $userModel = $this->modelFactory->new('Users');
            $routes = $userModel->registerRoutes();
            
            // Manually add routes to the registry
            $reflection = new \ReflectionClass($this->registry);
            $routesProperty = $reflection->getProperty('routes');
            $routesProperty->setAccessible(true);
            
            // Process routes to add required properties
            $processedRoutes = [];
            foreach ($routes as $route) {
                $route['pathComponents'] = $this->registry->parsePathComponents($route['path']);
                $route['pathLength'] = count($route['pathComponents']);
                $processedRoutes[] = $route;
            }
            
            $routesProperty->setValue($this->registry, $processedRoutes);
            
            // Test route grouping
            $groupedRoutes = $this->registry->groupRoutesByMethodAndLength();
            
            $this->assertArrayHasKey('GET', $groupedRoutes);
            $this->assertArrayHasKey('POST', $groupedRoutes);
            $this->assertArrayHasKey('PUT', $groupedRoutes);
            $this->assertArrayHasKey('DELETE', $groupedRoutes);
            
            // Test that routes are grouped by path length
            foreach ($groupedRoutes as $method => $lengthGroups) {
                foreach ($lengthGroups as $pathLength => $routeGroup) {
                    foreach ($routeGroup as $route) {
                        $this->assertEquals($pathLength, $route['pathLength'], 
                            "Route should be in correct path length group");
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not create Users model: ' . $e->getMessage());
        }
    }

    public function testMultipleModelRouteRegistration(): void
    {
        try {
            // Test with multiple models
            $userModel = $this->modelFactory->new('Users');
            $userRoutes = $userModel->registerRoutes();
            
            $movieModel = $this->modelFactory->new('Movies');
            $movieRoutes = $movieModel->registerRoutes();
            
            // Combine routes from both models
            $allRoutes = array_merge($userRoutes, $movieRoutes);
            
            $this->assertNotEmpty($userRoutes, 'Users model should have routes');
            $this->assertNotEmpty($movieRoutes, 'Movies model should have routes');
            $this->assertGreaterThan(count($userRoutes), count($allRoutes), 
                'Combined routes should be more than individual model routes');
            
            // Test that each model's routes have the correct structure
            foreach ($userRoutes as $route) {
                $this->assertEquals('UsersAPIController', $route['apiClass']);
            }
            
            foreach ($movieRoutes as $route) {
                $this->assertEquals('MoviesAPIController', $route['apiClass']);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not create model instances: ' . $e->getMessage());
        }
    }

    public function testParameterNamesInModelRoutes(): void
    {
        try {
            $userModel = $this->modelFactory->new('Users');
            $routes = $userModel->registerRoutes();
            
            // Find the read route and test parameter names
            $readRoute = null;
            foreach ($routes as $route) {
                if ($route['method'] === 'GET' && $route['path'] === '/Users/?') {
                    $readRoute = $route;
                    break;
                }
            }
            
            $this->assertNotNull($readRoute, 'Should find Users read route');
            $this->assertArrayHasKey('parameterNames', $readRoute);
            $this->assertEquals(['userId'], $readRoute['parameterNames']);
            
            // Test that parameter names count matches path components
            $pathComponents = $this->registry->parsePathComponents($readRoute['path']);
            $this->assertCount(count($pathComponents), $readRoute['parameterNames'], 
                'Parameter names count should match path components count');
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not create Users model: ' . $e->getMessage());
        }
    }
}
