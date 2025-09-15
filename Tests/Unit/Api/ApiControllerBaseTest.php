<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Monolog\Logger;
use ReflectionClass;

class ApiControllerBaseTest extends TestCase
{
    private MockApiControllerForApiControllerBaseTest $controller;
    private MockObject $logger;
    private MockObject $modelFactory;
    private MockObject $databaseConnector;
    private MockObject $metadataEngine;
    private MockObject $config;
    private MockObject $currentUserProvider;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks for all dependencies
        $this->logger = $this->createMock(Logger::class);
        $this->modelFactory = $this->createMock(ModelFactory::class);
        $this->databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->metadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
        
        // Create concrete implementation for testing with all dependencies
        $this->controller = new MockApiControllerForApiControllerBaseTest(
            $this->logger,
            $this->modelFactory,
            $this->databaseConnector,
            $this->metadataEngine,
            $this->config,
            $this->currentUserProvider
        );
    }

    public function testConstructorWithAllDependencies(): void
    {
        $controller = new MockApiControllerForApiControllerBaseTest(
            $this->logger,
            $this->modelFactory,
            $this->databaseConnector,
            $this->metadataEngine,
            $this->config,
            $this->currentUserProvider
        );
        
        $this->assertInstanceOf(MockApiControllerForApiControllerBaseTest::class, $controller);
        $this->assertEquals($this->logger, $this->getPrivateProperty($controller, 'logger'));
        $this->assertEquals($this->modelFactory, $this->getPrivateProperty($controller, 'modelFactory'));
        $this->assertEquals($this->databaseConnector, $this->getPrivateProperty($controller, 'databaseConnector'));
        $this->assertEquals($this->metadataEngine, $this->getPrivateProperty($controller, 'metadataEngine'));
        $this->assertEquals($this->config, $this->getPrivateProperty($controller, 'config'));
        $this->assertEquals($this->currentUserProvider, $this->getPrivateProperty($controller, 'currentUserProvider'));
    }

    public function testConstructorWithNullDependencies(): void
    {
        // Test backwards compatibility with null dependencies
        $controller = new MockApiControllerForApiControllerBaseTest();
        
        $this->assertInstanceOf(MockApiControllerForApiControllerBaseTest::class, $controller);
        $this->assertNull($this->getPrivateProperty($controller, 'logger'));
        $this->assertNull($this->getPrivateProperty($controller, 'modelFactory'));
        $this->assertNull($this->getPrivateProperty($controller, 'databaseConnector'));
        $this->assertNull($this->getPrivateProperty($controller, 'metadataEngine'));
        $this->assertNull($this->getPrivateProperty($controller, 'config'));
        $this->assertNull($this->getPrivateProperty($controller, 'currentUserProvider'));
    }

    public function testRegisterRoutesIsAbstract(): void
    {
        $reflection = new ReflectionClass(ApiControllerBase::class);
        $method = $reflection->getMethod('registerRoutes');
        
        $this->assertTrue($method->isAbstract());
    }

    public function testGetCurrentUserWithValidProvider(): void
    {
        $mockUser = $this->createMock(\Gravitycar\Models\ModelBase::class);
        
        $this->currentUserProvider->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);
        
        $method = $this->getPrivateMethod($this->controller, 'getCurrentUser');
        $result = $method->invoke($this->controller);
        
        $this->assertSame($mockUser, $result);
    }

    public function testGetCurrentUserWithNullProvider(): void
    {
        $controller = new MockApiControllerForApiControllerBaseTest();
        
        $method = $this->getPrivateMethod($controller, 'getCurrentUser');
        $result = $method->invoke($controller);
        
        $this->assertNull($result);
    }

    public function testGetCurrentUserReturnsNull(): void
    {
        $this->currentUserProvider->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);
        
        $method = $this->getPrivateMethod($this->controller, 'getCurrentUser');
        $result = $method->invoke($this->controller);
        
        $this->assertNull($result);
    }

    public function testRegisterRoutesReturnType(): void
    {
        $routes = $this->controller->registerRoutes();
        $this->assertIsArray($routes);
        $this->assertEmpty($routes); // Mock implementation returns empty array
    }

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
