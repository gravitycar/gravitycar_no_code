<?php
namespace Tests\Unit\Models\Api\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Models\Api\Api\ModelBaseAPIController;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Api\Request;
use Monolog\Logger;

/**
 * Unit tests for ModelBaseAPIController
 * Tests     public function testCreate    public function testUpdate_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('PUT');
        $this->controller->update($request);
    }dModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('POST');
        $this->controller->create($request);
    }eric API controller that provides CRUD operations for all ModelBase classes
 */
class ModelBaseAPIControllerTest extends TestCase {
    
    /** @var ModelBaseAPIController */
    private ModelBaseAPIController $controller;
    
    /** @var Logger */
    private Logger $logger;

    protected function setUp(): void {
        parent::setUp();
        
        // Create a real logger for these tests
        $this->logger = new Logger('test');
        
        // Create controller instance
        $this->controller = new ModelBaseAPIController($this->logger);
    }

    /**
     * Helper method to create a Request object for testing
     */
    private function createRequest(string $url, array $parameterNames, string $method = 'GET', array $requestData = []): Request
    {
        return new Request($url, $parameterNames, $method, $requestData);
    }

    /**
     * Helper method to create a Request with invalid/missing modelName
     */
    private function createInvalidModelNameRequest(string $method = 'GET', array $requestData = []): Request
    {
        // Create a request where modelName will be null/empty
        return new Request('/', [], $method, $requestData);
    }

    public function testRegisterRoutes(): void {
        $routes = $this->controller->registerRoutes();
        
        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);
        
        // Verify we have all expected route definitions
        $this->assertCount(11, $routes); // All routes from the specification
        
        // Check first route structure
        $firstRoute = $routes[0];
        $this->assertArrayHasKey('method', $firstRoute);
        $this->assertArrayHasKey('path', $firstRoute);
        $this->assertArrayHasKey('parameterNames', $firstRoute);
        $this->assertArrayHasKey('apiClass', $firstRoute);
        $this->assertArrayHasKey('apiMethod', $firstRoute);
        
        // Verify specific routes exist
        $listRoute = array_filter($routes, fn($r) => $r['apiMethod'] === 'list');
        $this->assertNotEmpty($listRoute);
        
        $retrieveRoute = array_filter($routes, fn($r) => $r['apiMethod'] === 'retrieve');
        $this->assertNotEmpty($retrieveRoute);
        
        $createRoute = array_filter($routes, fn($r) => $r['apiMethod'] === 'create');
        $this->assertNotEmpty($createRoute);
        
        $updateRoute = array_filter($routes, fn($r) => $r['apiMethod'] === 'update');
        $this->assertNotEmpty($updateRoute);
        
        $deleteRoute = array_filter($routes, fn($r) => $r['apiMethod'] === 'delete');
        $this->assertNotEmpty($deleteRoute);
        
        $listRelatedRoute = array_filter($routes, fn($r) => $r['apiMethod'] === 'listRelated');
        $this->assertNotEmpty($listRelatedRoute);
        
        $linkRoute = array_filter($routes, fn($r) => $r['apiMethod'] === 'link');
        $this->assertNotEmpty($linkRoute);
        
        $unlinkRoute = array_filter($routes, fn($r) => $r['apiMethod'] === 'unlink');
        $this->assertNotEmpty($unlinkRoute);
    }

    public function testRegisterRoutes_VerifyWildcardPatterns(): void {
        $routes = $this->controller->registerRoutes();
        
        // Verify all paths use ? wildcards for maximum flexibility
        foreach ($routes as $route) {
            $path = $route['path'];
            // Should contain ? characters for wildcards
            $this->assertStringContainsString('?', $path, "Route path should use ? wildcards: {$path}");
        }
    }

    public function testRegisterRoutes_VerifyParameterNames(): void {
        $routes = $this->controller->registerRoutes();
        
        // Find routes by method and path pattern  
        $listRoute = null;
        $retrieveRoute = null;
        $linkRoute = null;
        
        foreach ($routes as $route) {
            if ($route['apiMethod'] === 'list') {
                $listRoute = $route;
            } elseif ($route['apiMethod'] === 'retrieve') {
                $retrieveRoute = $route;
            } elseif ($route['apiMethod'] === 'link') {
                $linkRoute = $route;
            }
        }
        
        // Verify we found the routes
        $this->assertNotNull($listRoute, 'List route should be found');
        $this->assertNotNull($retrieveRoute, 'Retrieve route should be found');
        $this->assertNotNull($linkRoute, 'Link route should be found');
        
        // Verify parameter names
        $this->assertEquals(['modelName'], $listRoute['parameterNames']);
        $this->assertEquals(['modelName', 'id'], $retrieveRoute['parameterNames']);
        $this->assertEquals(['modelName', 'id', '', 'relationshipName', 'idToLink'], $linkRoute['parameterNames']);
    }

    public function testValidateModelName_ValidName(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateModelName');
        $method->setAccessible(true);
        
        // 'Users' is a valid model, so this should not throw an exception
        $method->invoke($this->controller, 'Users');
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function testValidateModelName_EmptyName(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateModelName');
        $method->setAccessible(true);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        $method->invoke($this->controller, '');
    }

    public function testValidateModelName_InvalidFormat(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateModelName');
        $method->setAccessible(true);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Invalid model name format');
        $method->invoke($this->controller, '123Invalid');
    }

    public function testValidateId_EmptyId(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateId');
        $method->setAccessible(true);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('ID is required');
        $method->invoke($this->controller, '');
    }

    public function testValidateId_ValidId(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateId');
        $method->setAccessible(true);
        
        // Should not throw exception
        $method->invoke($this->controller, '123');
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function testValidateRelationshipName_EmptyName(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateRelationshipName');
        $method->setAccessible(true);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Relationship name is required');
        $method->invoke($this->controller, '');
    }

    public function testValidateRelationshipName_InvalidFormat(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateRelationshipName');
        $method->setAccessible(true);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Invalid relationship name format');
        $method->invoke($this->controller, '123Invalid');
    }

    public function testExtractModelNameFromClass(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('extractModelNameFromClass');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, 'Gravitycar\\Models\\users\\Users');
        $this->assertEquals('Users', $result);
        
        $result = $method->invoke($this->controller, 'SimpleClass');
        $this->assertEquals('SimpleClass', $result);
    }

    public function testGetRequestData_ArrayInput(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getRequestData');
        $method->setAccessible(true);
        
        $testData = ['name' => 'test', 'value' => 123];
        $result = $method->invoke($this->controller, $testData);
        $this->assertEquals($testData, $result);
    }

    public function testGetRequestData_EmptyInput(): void {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getRequestData');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->controller, null);
        $this->assertIsArray($result);
    }

    public function testList_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Missing required parameter: modelName');
        
        $request = $this->createInvalidModelNameRequest();
        $this->controller->list($request);
    }

    public function testRetrieve_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Missing required parameter: modelName');
        
        $request = $this->createInvalidModelNameRequest();
        $this->controller->retrieve($request);
    }

    public function testCreate_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('POST', ['name' => 'test']);
        $this->controller->create($request);
    }

    public function testUpdate_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('PUT', ['name' => 'test']);
        $this->controller->update($request);
    }

    public function testDelete_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('DELETE');
        $this->controller->delete($request);
    }

    public function testRestore_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('POST');
        $this->controller->restore($request);
    }

    public function testListRelated_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('GET');
        $this->controller->listRelated($request);
    }

    public function testLink_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('POST');
        $this->controller->link($request);
    }

    public function testUnlink_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('POST');
        $this->controller->unlink($request);
    }

    public function testCreateAndLink_InvalidModelName(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model name is required');
        
        $request = $this->createInvalidModelNameRequest('POST');
        $this->controller->createAndLink($request);
    }
}
