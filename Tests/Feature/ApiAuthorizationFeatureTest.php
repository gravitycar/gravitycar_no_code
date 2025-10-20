<?php

namespace Gravitycar\Tests\Feature;

use Gravitycar\Tests\TestCase;
use Gravitycar\Api\Request;
use Gravitycar\Api\Router;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Core\ContainerConfig;

/**
 * Feature tests for API endpoint authorization using RBAC system
 */
class ApiAuthorizationFeatureTest extends TestCase
{
    private Router $router;
    private AuthorizationService $authorizationService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Get real services for feature testing
        $container = ContainerConfig::getContainer();
        $this->router = $container->get('router');
        $this->authorizationService = $container->get('authorization_service');
    }
    
    public function testRouterHasAuthorizationIntegration()
    {
        // Test that Router has the required methods for authorization
        $this->assertTrue(method_exists($this->router, 'handleAuthentication'));
        
        // Test that Router uses AuthorizationService
        $reflection = new \ReflectionClass($this->router);
        $properties = $reflection->getProperties();
        
        $hasAuthorizationService = false;
        foreach ($properties as $property) {
            if (str_contains($property->getType()?->getName() ?? '', 'AuthorizationService')) {
                $hasAuthorizationService = true;
                break;
            }
        }
        
        $this->assertTrue($hasAuthorizationService, 'Router should have AuthorizationService dependency');
    }
    
    public function testAuthorizationServiceHasRBACMethods()
    {
        // Test that AuthorizationService has both the original and new RBAC methods
        $this->assertTrue(method_exists($this->authorizationService, 'hasPermission'));
        $this->assertTrue(method_exists($this->authorizationService, 'hasPermissionForRoute'));
        $this->assertTrue(method_exists($this->authorizationService, 'getUserAllPermissions'));
        
        // Test original hasPermission method signature (backward compatibility)
        $reflection = new \ReflectionMethod($this->authorizationService, 'hasPermission');
        $parameters = $reflection->getParameters();
        $this->assertCount(3, $parameters, 'hasPermission should have 3 parameters');
        
        // Test new hasPermissionForRoute method signature (RBAC enhancement)
        $reflection = new \ReflectionMethod($this->authorizationService, 'hasPermissionForRoute');
        $parameters = $reflection->getParameters();
        $this->assertCount(3, $parameters, 'hasPermissionForRoute should have 3 parameters');
        
        // Test parameter types for hasPermissionForRoute
        $this->assertEquals('array', $parameters[0]->getType()?->getName(), 'First parameter should be array (route)');
        $this->assertEquals('Gravitycar\\Api\\Request', $parameters[1]->getType()?->getName(), 'Second parameter should be Request');
        $this->assertEquals('Gravitycar\\Models\\ModelBase', $parameters[2]->getType()?->getName(), 'Third parameter should be ModelBase (user)');
    }
    
    public function testDetermineActionFromRouteAndRequest()
    {
        // Test the determineAction helper method exists
        $reflection = new \ReflectionClass($this->authorizationService);
        
        try {
            $method = $reflection->getMethod('determineAction');
            $this->assertTrue($method->isProtected(), 'determineAction should be protected');
            
            // Make method accessible for testing
            $method->setAccessible(true);
            
            // Test explicit RBAC action
            $route = ['RBACAction' => 'custom_action'];
            $request = new Request('/test', [], 'GET');
            
            $action = $method->invoke($this->authorizationService, $route, $request);
            $this->assertEquals('custom_action', $action);
            
            // Test HTTP method mapping
            $route = []; // No explicit action
            $getRequest = new Request('/test', [], 'GET');
            $postRequest = new Request('/test', [], 'POST');
            $putRequest = new Request('/test', [], 'PUT');
            $deleteRequest = new Request('/test', [], 'DELETE');
            
            $this->assertEquals('read', $method->invoke($this->authorizationService, $route, $getRequest));
            $this->assertEquals('create', $method->invoke($this->authorizationService, $route, $postRequest));
            $this->assertEquals('update', $method->invoke($this->authorizationService, $route, $putRequest));
            $this->assertEquals('delete', $method->invoke($this->authorizationService, $route, $deleteRequest));
            
        } catch (\ReflectionException $e) {
            $this->fail('determineAction method should exist in AuthorizationService: ' . $e->getMessage());
        }
    }
    
    public function testDetermineComponentFromRouteAndRequest()
    {
        // Test the determineComponent helper method exists
        $reflection = new \ReflectionClass($this->authorizationService);
        
        try {
            $method = $reflection->getMethod('determineComponent');
            $this->assertTrue($method->isProtected(), 'determineComponent should be protected');
            
            // Make method accessible for testing
            $method->setAccessible(true);
            
            // Test model parameter extraction
            $route = [];
            $request = new Request('/Users', ['modelName'], 'GET', ['modelName' => 'Users']);
            // Set the API controller class name to make it a valid model request
            $request->setApiControllerClassName('Gravitycar\\Models\\api\\Api\\ModelBaseAPIController');
            
            $component = $method->invoke($this->authorizationService, $route, $request);
            $this->assertEquals('Users', $component);
            
            // Test apiClass fallback
            $route = ['apiClass' => 'TestController'];
            $requestWithoutModel = new Request('/test', [], 'GET');
            
            $component = $method->invoke($this->authorizationService, $route, $requestWithoutModel);
            $this->assertEquals('TestController', $component);
            
        } catch (\ReflectionException $e) {
            $this->fail('determineComponent method should exist in AuthorizationService: ' . $e->getMessage());
        }
    }
    
    public function testPermissionCheckWithMockData()
    {
        // This test uses mock data since we don't want to depend on actual database records
        
        $route = [
            'path' => '/Users/123',
            'method' => 'GET',
            'apiClass' => 'ModelBaseAPIController'
        ];
        
        $request = new Request('/Users/123', ['modelName', 'id'], 'GET', ['modelName' => 'Users', 'id' => '123']);
        
        // Create a mock user for testing
        $container = ContainerConfig::getContainer();
        $modelFactory = $container->get('model_factory');
        $mockUser = $modelFactory->new('Users');
        $mockUser->set('id', '123');
        $mockUser->set('username', 'testuser');
        
        // Test the permission check method exists and can be called
        try {
            $result = $this->authorizationService->hasPermissionForRoute($route, $request, $mockUser);
            $this->assertIsBool($result, 'hasPermissionForRoute should return boolean');
        } catch (\Exception $e) {
            // This might fail due to missing database records, which is expected
            $this->assertStringContainsString('permission', strtolower($e->getMessage()), 
                'Exception should be related to permissions');
        }
    }
    
    public function testGetUserAllPermissionsMethod()
    {
        // Test getUserAllPermissions method exists and returns proper structure
        $container = ContainerConfig::getContainer();
        $modelFactory = $container->get('model_factory');
        $mockUser = $modelFactory->new('Users');
        $mockUser->set('id', '123');
        
        try {
            $permissions = $this->authorizationService->getUserAllPermissions($mockUser);
            $this->assertIsArray($permissions, 'getUserAllPermissions should return array');
        } catch (\Exception $e) {
            // This might fail due to missing database records, which is expected
            $this->assertStringContainsString('permission', strtolower($e->getMessage()), 
                'Exception should be related to permissions');
        }
    }
}