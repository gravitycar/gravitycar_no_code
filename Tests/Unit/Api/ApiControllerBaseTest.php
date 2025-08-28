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

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = $this->createMock(Logger::class);
        
        // Create concrete implementation for testing
        $this->controller = new MockApiControllerForApiControllerBaseTest();
        
        // Mock the logger using reflection since ServiceLocator is used
        $this->setPrivateProperty($this->controller, 'logger', $this->logger);
    }

    public function testConstructorSetsLogger(): void
    {
        $controller = new MockApiControllerForApiControllerBaseTest();
        
        $logger = $this->getPrivateProperty($controller, 'logger');
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testRegisterRoutesIsAbstract(): void
    {
        $reflection = new ReflectionClass(ApiControllerBase::class);
        $method = $reflection->getMethod('registerRoutes');
        
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

    public function testRegisterRoutesReturnType(): void
    {
        $routes = $this->controller->registerRoutes();
        
        $this->assertIsArray($routes);
        $this->assertEmpty($routes); // Mock implementation returns empty array
    }

    // Helper methods
    private function setPrivateProperty(object $object, string $property, $value): void
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function getPrivateProperty(object $object, string $property)
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    private function getPrivateMethod(object $object, string $method): \ReflectionMethod
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method;
    }
}

// Mock concrete implementation for testing
class MockApiControllerForApiControllerBaseTest extends ApiControllerBase
{
    public function registerRoutes(): array
    {
        return [];
    }
}
