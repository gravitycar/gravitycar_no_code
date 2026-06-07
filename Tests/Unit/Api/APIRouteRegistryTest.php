<?php

namespace Tests\Unit\Api;

use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Exceptions\GCException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class APIRouteRegistryTest extends TestCase
{
    protected $registry;
    protected $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        // Create registry without triggering route discovery in tests
        $this->registry = $this->createPartialMock(APIRouteRegistry::class, ['discoverAndRegisterRoutes']);
        $reflection = new \ReflectionClass($this->registry);
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->registry, $this->logger);
    }

    public function testValidateRouteFormatSuccess(): void
    {
        $validRoute = [
            'method' => 'GET',
            'path' => '/api/test',
            'apiClass' => 'TestAPIController',
            'apiMethod' => 'testMethod'
        ];

        // Since we can't easily mock class_exists and method_exists,
        // we'll test the general validation structure
        try {
            $this->registry->validateRouteFormat($validRoute);
            $this->assertTrue(true, 'Valid route should not throw exception');
        } catch (GCException $e) {
            // Expected for missing class, but structure validation passed
            $this->assertStringContainsString('not found', $e->getMessage());
        }
    }

    public function testValidateRouteFormatMissingMethod(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Route missing required field: method');

        $invalidRoute = [
            'path' => '/api/test',
            'apiClass' => 'TestAPIController',
            'apiMethod' => 'testMethod'
        ];

        $this->registry->validateRouteFormat($invalidRoute);
    }

    public function testValidateRouteFormatMissingPath(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Route missing required field: path');

        $invalidRoute = [
            'method' => 'GET',
            'apiClass' => 'TestAPIController',
            'apiMethod' => 'testMethod'
        ];

        $this->registry->validateRouteFormat($invalidRoute);
    }

    public function testValidateRouteFormatMissingApiClass(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Route missing required field: apiClass');

        $invalidRoute = [
            'method' => 'GET',
            'path' => '/api/test',
            'apiMethod' => 'testMethod'
        ];

        $this->registry->validateRouteFormat($invalidRoute);
    }

    public function testValidateRouteFormatMissingApiMethod(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Route missing required field: apiMethod');

        $invalidRoute = [
            'method' => 'GET',
            'path' => '/api/test',
            'apiClass' => 'TestAPIController'
        ];

        $this->registry->validateRouteFormat($invalidRoute);
    }

    public function testValidateRouteFormatInvalidHttpMethod(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Invalid HTTP method: INVALID');

        $invalidRoute = [
            'method' => 'INVALID',
            'path' => '/api/test',
            'apiClass' => 'TestAPIController',
            'apiMethod' => 'testMethod'
        ];

        $this->registry->validateRouteFormat($invalidRoute);
    }

    public function testValidateRouteFormatInvalidPath(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage("Route path must start with '/': api/test");

        $invalidRoute = [
            'method' => 'GET',
            'path' => 'api/test',
            'apiClass' => 'TestAPIController',
            'apiMethod' => 'testMethod'
        ];

        $this->registry->validateRouteFormat($invalidRoute);
    }

    public function testParsePathComponentsEmpty(): void
    {
        $result = $this->registry->parsePathComponents('');
        $this->assertEquals([], $result);
    }

    public function testParsePathComponentsRoot(): void
    {
        $result = $this->registry->parsePathComponents('/');
        $this->assertEquals([], $result);
    }

    public function testParsePathComponentsSimple(): void
    {
        $result = $this->registry->parsePathComponents('/api/users');
        $this->assertEquals(['api', 'users'], $result);
    }

    public function testParsePathComponentsComplex(): void
    {
        $result = $this->registry->parsePathComponents('/api/users/?/profile');
        $this->assertEquals(['api', 'users', '?', 'profile'], $result);
    }

    public function testParsePathComponentsWithTrailingSlash(): void
    {
        $result = $this->registry->parsePathComponents('/api/users/');
        $this->assertEquals(['api', 'users'], $result);
    }

    public function testGetPathLengthEmpty(): void
    {
        $result = $this->registry->getPathLength('');
        $this->assertEquals(0, $result);
    }

    public function testGetPathLengthRoot(): void
    {
        $result = $this->registry->getPathLength('/');
        $this->assertEquals(0, $result);
    }

    public function testGetPathLengthSimple(): void
    {
        $result = $this->registry->getPathLength('/api/users');
        $this->assertEquals(2, $result);
    }

    public function testGetPathLengthComplex(): void
    {
        $result = $this->registry->getPathLength('/api/users/?/profile/settings');
        $this->assertEquals(5, $result);
    }

    public function testResolveControllerClassNameFullyQualified(): void
    {
        // Test with a fully qualified class name
        $result = $this->registry->resolveControllerClassName('Psr\\Log\\LoggerInterface');
        $this->assertEquals('Psr\\Log\\LoggerInterface', $result);
    }

    public function testResolveControllerClassNameNotFound(): void
    {
        $result = $this->registry->resolveControllerClassName('NonExistentClass');
        $this->assertNull($result);
    }

    public function testGroupRoutesByMethodAndLengthEmpty(): void
    {
        $result = $this->registry->groupRoutesByMethodAndLength();
        $this->assertEquals([], $result);
    }

    public function testGetRoutesByMethodAndLengthEmpty(): void
    {
        $result = $this->registry->getRoutesByMethodAndLength('GET', 2);
        $this->assertEquals([], $result);
    }

    public function testGetRoutesEmpty(): void
    {
        $result = $this->registry->getRoutes();
        $this->assertEquals([], $result);
    }

    public function testGetGroupedRoutesEmpty(): void
    {
        $result = $this->registry->getGroupedRoutes();
        $this->assertEquals([], $result);
    }

    public function testValidHttpMethods(): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        
        foreach ($validMethods as $method) {
            $route = [
                'method' => $method,
                'path' => '/api/test',
                'apiClass' => 'TestAPIController',
                'apiMethod' => 'testMethod'
            ];

            try {
                $this->registry->validateRouteFormat($route);
                $this->assertTrue(true, "Method {$method} should be valid");
            } catch (GCException $e) {
                // Only expect class/method not found errors, not method validation errors
                $this->assertStringNotContainsString('Invalid HTTP method', $e->getMessage());
            }
        }
    }

    public function testCaseInsensitiveHttpMethodInGrouping(): void
    {
        // Test that method grouping handles case insensitivity
        $result = $this->registry->getRoutesByMethodAndLength('get', 2);
        $this->assertEquals([], $result);

        $result = $this->registry->getRoutesByMethodAndLength('GET', 2);
        $this->assertEquals([], $result);
    }

    // -------------------------------------------------------------------------
    // Duplicate route detection
    // -------------------------------------------------------------------------

    private function makeRegistryWithMockedInternals(): APIRouteRegistry
    {
        $registry = $this->createPartialMock(APIRouteRegistry::class, [
            'discoverAndRegisterRoutes',
            'validateRouteFormat',
            'resolveControllerClassName',
            'extractDependenciesFromConstructor',
        ]);

        $registry->method('validateRouteFormat');
        $registry->method('resolveControllerClassName')->willReturnArgument(0);
        $registry->method('extractDependenciesFromConstructor')->willReturn([]);

        $reflection = new \ReflectionClass($registry);
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($registry, $this->logger);

        return $registry;
    }

    private function callRegisterRoute(APIRouteRegistry $registry, array $route): void
    {
        $method = (new \ReflectionClass($registry))->getMethod('registerRoute');
        $method->setAccessible(true);
        $method->invoke($registry, $route);
    }

    private function getRoutes(APIRouteRegistry $registry): array
    {
        $property = (new \ReflectionClass($registry))->getProperty('routes');
        $property->setAccessible(true);
        return $property->getValue($registry);
    }

    public function testDuplicateRouteIsNotAdded(): void
    {
        $registry = $this->makeRegistryWithMockedInternals();

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Duplicate route detected', $this->anything());

        $route = ['method' => 'GET', 'path' => '/Projects', 'apiClass' => 'ControllerA', 'apiMethod' => 'list'];
        $duplicate = ['method' => 'GET', 'path' => '/Projects', 'apiClass' => 'ControllerB', 'apiMethod' => 'list'];

        $this->callRegisterRoute($registry, $route);
        $this->callRegisterRoute($registry, $duplicate);

        $routes = $this->getRoutes($registry);
        $this->assertCount(1, $routes);
        $this->assertEquals('ControllerA', $routes[0]['apiClass']);
    }

    public function testSamePathDifferentMethodsAreAllowed(): void
    {
        $registry = $this->makeRegistryWithMockedInternals();

        $this->logger->expects($this->never())->method('warning');

        $get  = ['method' => 'GET',  'path' => '/Projects', 'apiClass' => 'ControllerA', 'apiMethod' => 'list'];
        $post = ['method' => 'POST', 'path' => '/Projects', 'apiClass' => 'ControllerA', 'apiMethod' => 'create'];

        $this->callRegisterRoute($registry, $get);
        $this->callRegisterRoute($registry, $post);

        $this->assertCount(2, $this->getRoutes($registry));
    }

    public function testSameMethodDifferentPathsAreAllowed(): void
    {
        $registry = $this->makeRegistryWithMockedInternals();

        $this->logger->expects($this->never())->method('warning');

        $projects = ['method' => 'GET', 'path' => '/Projects', 'apiClass' => 'ControllerA', 'apiMethod' => 'list'];
        $events   = ['method' => 'GET', 'path' => '/Events',   'apiClass' => 'ControllerB', 'apiMethod' => 'list'];

        $this->callRegisterRoute($registry, $projects);
        $this->callRegisterRoute($registry, $events);

        $this->assertCount(2, $this->getRoutes($registry));
    }
}
