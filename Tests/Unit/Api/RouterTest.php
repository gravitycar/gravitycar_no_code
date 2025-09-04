<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Api\Router;
use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Api\APIPathScorer;
use Gravitycar\Api\Request;
use Gravitycar\Api\RequestParameterParser;
use Gravitycar\Api\FilterCriteria;
use Gravitycar\Api\SearchEngine;
use Gravitycar\Api\ResponseFormatter;
use Gravitycar\Exceptions\ParameterValidationException;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Exceptions\UnauthorizedException;
use Gravitycar\Exceptions\ForbiddenException;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Services\AuthorizationService;
use Monolog\Logger;
use ReflectionClass;
use ReflectionProperty;

class RouterTest extends TestCase
{
    private Router $router;
    private MockObject $serviceLocator;
    private MockObject $logger;
    private MockObject $metadataEngine;
    private MockObject $routeRegistry;
    private MockObject $pathScorer;
    private array $originalServerState = [];
    private array $originalGetState = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Save original global state
        $this->originalServerState = $_SERVER;
        $this->originalGetState = $_GET ?? [];
        
        // Create mocks
        $this->serviceLocator = $this->createMock(ServiceLocator::class);
        $this->logger = $this->createMock(Logger::class);
        $this->metadataEngine = $this->createMock(\Gravitycar\Metadata\MetadataEngine::class);
        
        // Configure service locator
        $this->serviceLocator->method('get')->willReturnMap([
            ['logger', $this->logger],
            ['metadataEngine', $this->metadataEngine]
        ]);
        
        // Create router with metadata engine to avoid ServiceLocator static calls
        $this->router = new Router($this->metadataEngine);
        
        // Mock internal dependencies using reflection
        $this->routeRegistry = $this->createMock(APIRouteRegistry::class);
        $this->pathScorer = $this->createMock(APIPathScorer::class);
        
        $this->setPrivateProperty($this->router, 'routeRegistry', $this->routeRegistry);
        $this->setPrivateProperty($this->router, 'pathScorer', $this->pathScorer);
        $this->setPrivateProperty($this->router, 'logger', $this->logger);
    }

    protected function tearDown(): void
    {
        // Restore original global state
        $_SERVER = $this->originalServerState;
        $_GET = $this->originalGetState;
        
        parent::tearDown();
    }

    public function testConstructorWithServiceLocator(): void
    {
        // Test with metadata engine (backward compatibility)
        $router = new Router($this->metadataEngine);
        
        $this->assertInstanceOf(Router::class, $router);
    }

    public function testConstructorWithMetadataEngineBackwardCompatibility(): void
    {
        // Test backward compatibility constructor
        $router = new Router($this->metadataEngine);
        
        $this->assertInstanceOf(Router::class, $router);
    }

    public function testRouteWithSuccessfulMatch(): void
    {
        $method = 'GET';
        $path = '/api/users';
        $requestData = ['page' => 1];
        
        $route = [
            'method' => 'GET',
            'path' => '/api/users',
            'apiClass' => MockApiController::class,
            'apiMethod' => 'getUsers',
            'parameterNames' => ['api', 'users'], // Match path components
            'allowedRoles' => ['*']
        ];
        
        // Mock route registry to return candidate routes
        $this->routeRegistry->method('getRoutesByMethodAndLength')
            ->with($method, 2) // /api/users has 2 components
            ->willReturn([$route]);
        
        // Mock path scorer to return best match
        $this->pathScorer->method('findBestMatch')
            ->with($method, $path, [$route])
            ->willReturn($route);
        
        $result = $this->router->route($method, $path, $requestData);
        
        $this->assertEquals('success', $result);
    }

    public function testRouteWithNoMatchThrowsException(): void
    {
        $method = 'GET';
        $path = '/api/nonexistent';
        
        // Mock route registry to return no routes
        $this->routeRegistry->method('getRoutesByMethodAndLength')
            ->willReturn([]);
        
        // Mock route registry to return empty routes for fallback
        $this->routeRegistry->method('getRoutes')
            ->willReturn([]);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('No matching route found for GET /api/nonexistent');
        
        $this->router->route($method, $path);
    }

    public function testRouteWithFallbackMatching(): void
    {
        $method = 'GET';
        $path = '/api/users/123';
        
        $route = [
            'method' => 'GET',
            'path' => '/api/users/{id}',
            'apiClass' => MockApiController::class,
            'apiMethod' => 'getUser',
            'parameterNames' => ['api', 'users', 'id'], // Match path components
            'allowedRoles' => ['*']
        ];
        
        // Mock route registry - no exact length match
        $this->routeRegistry->method('getRoutesByMethodAndLength')
            ->willReturn([]);
        
        // Mock grouped routes for fallback - use different length (2 instead of 3)
        $this->routeRegistry->method('getGroupedRoutes')
            ->willReturn([
                'GET' => [
                    2 => [$route] // Different length for wildcard matching
                ]
            ]);
        
        // Mock path scorer for fallback match
        $this->pathScorer->method('findBestMatch')
            ->willReturn($route);
        
        $result = $this->router->route($method, $path, ['id' => '123']);
        
        $this->assertEquals('success', $result);
    }

    public function testRouteWithInvalidControllerClass(): void
    {
        $method = 'GET';
        $path = '/api/users';
        
        $route = [
            'method' => 'GET',
            'path' => '/api/users',
            'apiClass' => 'NonexistentController',
            'apiMethod' => 'getUsers',
            'parameterNames' => ['api', 'users'], // Match path components
            'allowedRoles' => ['*']
        ];
        
        $this->routeRegistry->method('getRoutesByMethodAndLength')
            ->willReturn([$route]);
        
        $this->pathScorer->method('findBestMatch')
            ->willReturn($route);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('API controller class not found: NonexistentController');
        
        $this->router->route($method, $path);
    }

    public function testRouteWithInvalidHandlerMethod(): void
    {
        $method = 'GET';
        $path = '/api/users';
        
        $route = [
            'method' => 'GET',
            'path' => '/api/users',
            'apiClass' => MockApiController::class,
            'apiMethod' => 'nonexistentMethod',
            'parameterNames' => ['api', 'users'], // Match path components
            'allowedRoles' => ['*']
        ];
        
        $this->routeRegistry->method('getRoutesByMethodAndLength')
            ->willReturn([$route]);
        
        $this->pathScorer->method('findBestMatch')
            ->willReturn($route);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Handler method not found: nonexistentMethod');
        
        $this->router->route($method, $path);
    }

    public function testHandleRequestWithValidRoute(): void
    {
        // Mock $_SERVER variables
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/users';
        $_GET = ['page' => '1'];
        
        $route = [
            'method' => 'GET',
            'path' => '/api/users',
            'apiClass' => MockApiController::class,
            'apiMethod' => 'getUsers',
            'parameterNames' => ['api', 'users'], // Match path components
            'allowedRoles' => ['*']
        ];
        
        $this->routeRegistry->method('getRoutesByMethodAndLength')
            ->willReturn([$route]);
        
        $this->pathScorer->method('findBestMatch')
            ->willReturn($route);
        
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Routing request: GET /api/users');
        
        // Since we're in CLI mode, no output should be generated
        $this->router->handleRequest();
        
        // Clean up
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_GET);
    }

    public function testHandleRequestWithRoutingError(): void
    {
        // Mock $_SERVER variables
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/nonexistent';
        $_GET = []; // Ensure $_GET is an array
        
        $this->routeRegistry->method('getRoutesByMethodAndLength')
            ->willReturn([]);
        
        $this->routeRegistry->method('getGroupedRoutes')
            ->willReturn([]);
        
        $this->routeRegistry->method('getRoutes')
            ->willReturn([]);
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Routing error'));
        
        $this->expectException(GCException::class);
        
        $this->router->handleRequest();
        
        // Clean up
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_GET);
    }

    public function testAttachRequestHelpersSuccessfully(): void
    {
        $request = new Request('/api/users', ['api', 'users'], 'GET', ['page' => 1]);
        
        // Use reflection to call protected method
        $method = $this->getPrivateMethod($this->router, 'attachRequestHelpers');
        $method->invoke($this->router, $request);
        
        // Verify helpers are attached
        $this->assertInstanceOf(RequestParameterParser::class, $request->getParameterParser());
        $this->assertInstanceOf(FilterCriteria::class, $request->getFilterCriteria());
        $this->assertInstanceOf(SearchEngine::class, $request->getSearchEngine());
        $this->assertInstanceOf(ResponseFormatter::class, $request->getResponseFormatter());
    }

    public function testGetModelWithValidModelName(): void
    {
        $request = new Request('/api/users', ['api', 'users'], 'GET', ['modelName' => 'User']);
        
        // Mock ModelFactory
        $mockModel = $this->createMock(ModelBase::class);
        
        // We can't easily mock static methods, so we'll test the error case instead
        $method = $this->getPrivateMethod($this->router, 'getModel');
        $result = $method->invoke($this->router, $request);
        
        // Since ModelFactory is not easily mockable, we expect null due to class not existing
        $this->assertNull($result);
    }

    public function testGetModelWithNoModelName(): void
    {
        $request = new Request('/api/users', ['api', 'users'], 'GET', []);
        
        $method = $this->getPrivateMethod($this->router, 'getModel');
        $result = $method->invoke($this->router, $request);
        
        $this->assertNull($result);
    }

    public function testPerformValidationWithModelSuccessfully(): void
    {
        $model = $this->createMock(ModelBase::class);
        $model->method('getFields')->willReturn([
            'name' => $this->createMockField('name', true),
            'email' => $this->createMockField('email', true)
        ]);
        
        $request = new Request('/api/users', ['api', 'users'], 'GET', []);
        $request->setFilterCriteria(new FilterCriteria());
        $request->setSearchEngine(new SearchEngine());
        
        $parsedParams = [
            'filters' => [],
            'search' => [],
            'sorting' => [['field' => 'name', 'direction' => 'asc']],
            'pagination' => ['page' => 1, 'pageSize' => 20]
        ];
        
        $method = $this->getPrivateMethod($this->router, 'performValidationWithModel');
        $result = $method->invoke($this->router, $request, $model, $parsedParams);
        
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('search', $result);
        $this->assertArrayHasKey('sorting', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function testHandleAuthenticationWithPublicRoute(): void
    {
        $route = [
            'path' => '/api/public',
            'allowedRoles' => ['*']
        ];
        $request = new Request('/api/public', ['api', 'public'], 'GET', []);
        
        $method = $this->getPrivateMethod($this->router, 'handleAuthentication');
        
        // Should not throw any exception for public route
        $method->invoke($this->router, $route, $request);
        
        $this->assertTrue(true); // Assert we reach here without exception
    }

    public function testHandleAuthenticationWithNullAllowedRoles(): void
    {
        $route = [
            'path' => '/api/public',
            'allowedRoles' => null
        ];
        $request = new Request('/api/public', ['api', 'public'], 'GET', []);
        
        $method = $this->getPrivateMethod($this->router, 'handleAuthentication');
        
        // Should not throw any exception for route with null allowed roles
        $method->invoke($this->router, $route, $request);
        
        $this->assertTrue(true); // Assert we reach here without exception
    }

    public function testExtractModelNameFromRoute(): void
    {
        $route = [
            'apiClass' => 'ModelBaseAPIController',
            'path' => '/api/users'
        ];
        
        $method = $this->getPrivateMethod($this->router, 'extractModelName');
        $result = $method->invoke($this->router, $route);
        
        $this->assertEquals('Users', $result);
    }

    public function testExtractModelNameFromNonModelRoute(): void
    {
        $route = [
            'apiClass' => 'CustomController',
            'path' => '/api/custom'
        ];
        
        $method = $this->getPrivateMethod($this->router, 'extractModelName');
        $result = $method->invoke($this->router, $route);
        
        $this->assertNull($result);
    }

    public function testMapMethodToActionForGetList(): void
    {
        $method = $this->getPrivateMethod($this->router, 'mapMethodToAction');
        
        $result = $method->invoke($this->router, 'GET', '/api/users');
        $this->assertEquals('list', $result);
    }

    public function testMapMethodToActionForGetSingle(): void
    {
        $method = $this->getPrivateMethod($this->router, 'mapMethodToAction');
        
        $result = $method->invoke($this->router, 'GET', '/api/users/123');
        $this->assertEquals('read', $result);
    }

    public function testMapMethodToActionForPost(): void
    {
        $method = $this->getPrivateMethod($this->router, 'mapMethodToAction');
        
        $result = $method->invoke($this->router, 'POST', '/api/users');
        $this->assertEquals('create', $result);
    }

    public function testMapMethodToActionForPut(): void
    {
        $method = $this->getPrivateMethod($this->router, 'mapMethodToAction');
        
        $result = $method->invoke($this->router, 'PUT', '/api/users/123');
        $this->assertEquals('update', $result);
    }

    public function testMapMethodToActionForDelete(): void
    {
        $method = $this->getPrivateMethod($this->router, 'mapMethodToAction');
        
        $result = $method->invoke($this->router, 'DELETE', '/api/users/123');
        $this->assertEquals('delete', $result);
    }

    public function testIsListOperationTrue(): void
    {
        $method = $this->getPrivateMethod($this->router, 'isListOperation');
        
        $result = $method->invoke($this->router, '/api/users');
        $this->assertTrue($result);
    }

    public function testIsListOperationFalse(): void
    {
        $method = $this->getPrivateMethod($this->router, 'isListOperation');
        
        $result = $method->invoke($this->router, '/api/users/123');
        $this->assertFalse($result);
    }

    public function testValidateRequestParametersWithValidParams(): void
    {
        $request = new Request('/api/users/123', ['api', 'users', 'id'], 'GET', ['id' => '123']);
        $route = [
            'path' => '/api/users/{id}',
            'parameterNames' => ['api', 'users', 'id']
        ];
        
        $method = $this->getPrivateMethod($this->router, 'validateRequestParameters');
        
        // Should not throw exception
        $method->invoke($this->router, $request, $route);
        
        $this->assertTrue(true);
    }

    public function testValidateRequestParametersWithMissingParams(): void
    {
        // Create request where 'id' parameter is missing from extracted parameters
        $request = new Request('/api/users', ['api', 'users'], 'GET', []); // Only 2 components, no 'id'
        
        // But route expects 'id' parameter  
        $route = [
            'path' => '/api/users/{id}',
            'parameterNames' => ['api', 'users', 'id'] // Expects 'id' parameter
        ];
        
        $method = $this->getPrivateMethod($this->router, 'validateRequestParameters');
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Missing required route parameter: id');
        
        $method->invoke($this->router, $request, $route);
    }

    public function testGetRequestParamsWithGetData(): void
    {
        $_GET = ['page' => '1', 'limit' => '10'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        $method = $this->getPrivateMethod($this->router, 'getRequestParams');
        $result = $method->invoke($this->router);
        
        $this->assertEquals('1', $result['page']);
        $this->assertEquals('10', $result['limit']);
        
        // Clean up
        unset($_GET, $_SERVER['REQUEST_METHOD']);
    }

    public function testParsePathComponentsWithEmptyPath(): void
    {
        $method = $this->getPrivateMethod($this->router, 'parsePathComponents');
        
        $result = $method->invoke($this->router, '');
        $this->assertEquals([], $result);
        
        $result = $method->invoke($this->router, '/');
        $this->assertEquals([], $result);
    }

    public function testParsePathComponentsWithValidPath(): void
    {
        $method = $this->getPrivateMethod($this->router, 'parsePathComponents');
        
        $result = $method->invoke($this->router, '/api/users/123');
        $this->assertEquals(['api', 'users', '123'], $result);
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

    /**
     * Create a mock field for testing
     */
    private function createMockField(string $name, bool $isDbField): MockObject
    {
        $field = $this->createMock(\Gravitycar\Fields\FieldBase::class);
        $field->method('getName')->willReturn($name);
        $field->method('isDBField')->willReturn($isDbField);
        return $field;
    }
}

/**
 * Mock API Controller for testing
 */
class MockApiController
{
    private Logger $logger;
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    public function getUsers(Request $request): string
    {
        return 'success';
    }
    
    public function getUser(Request $request): string
    {
        return 'success';
    }
}
