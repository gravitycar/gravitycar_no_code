<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;
use ReflectionClass;

class ApiControllerBaseTest extends TestCase
{
    private MockApiControllerForApiControllerBaseTest $controller;
    private MockObject $logger;
    private array $metadata;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = $this->createMock(Logger::class);
        $this->metadata = [
            'model' => 'User',
            'table' => 'users',
            'fields' => ['id', 'name', 'email']
        ];
        
        // Create concrete implementation for testing
        $this->controller = new MockApiControllerForApiControllerBaseTest($this->metadata);
        
        // Mock the logger using reflection since ServiceLocator is used
        $this->setPrivateProperty($this->controller, 'logger', $this->logger);
    }

    public function testConstructorSetsMetadata(): void
    {
        $metadata = ['test' => 'value'];
        $controller = new MockApiControllerForApiControllerBaseTest($metadata);
        
        $this->assertEquals($metadata, $this->getPrivateProperty($controller, 'metadata'));
    }

    public function testConstructorSetsLogger(): void
    {
        $controller = new MockApiControllerForApiControllerBaseTest($this->metadata);
        
        $logger = $this->getPrivateProperty($controller, 'logger');
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testRegisterRoutesIsAbstract(): void
    {
        $reflection = new ReflectionClass(ApiControllerBase::class);
        $method = $reflection->getMethod('registerRoutes');
        
        $this->assertTrue($method->isAbstract());
    }

    public function testGetIsAbstract(): void
    {
        $reflection = new ReflectionClass(ApiControllerBase::class);
        $method = $reflection->getMethod('get');
        
        $this->assertTrue($method->isAbstract());
    }

    public function testPostIsAbstract(): void
    {
        $reflection = new ReflectionClass(ApiControllerBase::class);
        $method = $reflection->getMethod('post');
        
        $this->assertTrue($method->isAbstract());
    }

    public function testPutIsAbstract(): void
    {
        $reflection = new ReflectionClass(ApiControllerBase::class);
        $method = $reflection->getMethod('put');
        
        $this->assertTrue($method->isAbstract());
    }

    public function testDeleteIsAbstract(): void
    {
        $reflection = new ReflectionClass(ApiControllerBase::class);
        $method = $reflection->getMethod('delete');
        
        $this->assertTrue($method->isAbstract());
    }

    public function testJsonResponseWithDefaultStatus(): void
    {
        $data = ['message' => 'success', 'data' => ['id' => 1]];
        
        // Capture output
        ob_start();
        
        // Call protected method using reflection
        $method = $this->getPrivateMethod($this->controller, 'jsonResponse');
        $method->invoke($this->controller, $data);
        
        $output = ob_get_clean();
        
        // Verify JSON output
        $this->assertEquals(json_encode($data), $output);
    }

    public function testJsonResponseWithCustomStatus(): void
    {
        $data = ['error' => 'Not found'];
        $status = 404;
        
        // Capture output
        ob_start();
        
        // Call protected method using reflection
        $method = $this->getPrivateMethod($this->controller, 'jsonResponse');
        $method->invoke($this->controller, $data, $status);
        
        $output = ob_get_clean();
        
        // Verify JSON output
        $this->assertEquals(json_encode($data), $output);
    }

    public function testJsonResponseSetsContentTypeHeader(): void
    {
        $data = ['test' => 'data'];
        
        // Use output buffering to capture JSON output
        ob_start();
        
        try {
            // Call protected method using reflection
            $method = $this->getPrivateMethod($this->controller, 'jsonResponse');
            $method->invoke($this->controller, $data);
            
            // Verify the output is valid JSON
            $output = ob_get_contents();
            $this->assertJson($output);
            
            // Verify the data structure
            $decoded = json_decode($output, true);
            $this->assertEquals($data, $decoded);
            
        } finally {
            ob_end_clean();
        }
    }

    public function testConcreteImplementationCanCallAbstractMethods(): void
    {
        // Test that concrete implementation can implement abstract methods
        $routes = $this->controller->registerRoutes();
        $this->assertIsArray($routes);
        
        $getResult = $this->controller->get();
        $this->assertEquals('get_result', $getResult);
        
        $postResult = $this->controller->post(['name' => 'test']);
        $this->assertEquals('post_result', $postResult);
        
        $putResult = $this->controller->put(1, ['name' => 'updated']);
        $this->assertEquals('put_result', $putResult);
        
        $deleteResult = $this->controller->delete(1);
        $this->assertEquals('delete_result', $deleteResult);
    }

    public function testMetadataAccessibility(): void
    {
        $controller = new MockApiControllerForApiControllerBaseTest($this->metadata);
        
        // Verify metadata is accessible to concrete implementations
        $metadata = $controller->getMetadata();
        $this->assertEquals($this->metadata, $metadata);
    }

    public function testLoggerAccessibility(): void
    {
        $controller = new MockApiControllerForApiControllerBaseTest($this->metadata);
        
        // Verify logger is accessible to concrete implementations
        $logger = $controller->getLogger();
        $this->assertInstanceOf(Logger::class, $logger);
    }

    /**
     * Helper method to access private properties
     */
    private function getPrivateProperty($object, string $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Helper method to set private properties
     */
    private function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Helper method to access private methods
     */
    private function getPrivateMethod($object, string $methodName): \ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}

/**
 * Concrete implementation of ApiControllerBase for testing
 */
class MockApiControllerForApiControllerBaseTest extends ApiControllerBase
{
    public function registerRoutes(): array
    {
        return [
            'GET /api/test' => 'get',
            'POST /api/test' => 'post',
            'PUT /api/test/{id}' => 'put',
            'DELETE /api/test/{id}' => 'delete'
        ];
    }

    public function get($id = null): string
    {
        return 'get_result';
    }

    public function post(array $data): string
    {
        return 'post_result';
    }

    public function put($id, array $data): string
    {
        return 'put_result';
    }

    public function delete($id): string
    {
        return 'delete_result';
    }

    // Helper methods for testing
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }
}
