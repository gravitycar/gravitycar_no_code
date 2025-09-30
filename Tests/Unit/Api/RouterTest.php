<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Api\Router;
use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Api\APIPathScorer;
use Gravitycar\Api\ApiControllerBase;
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
use Gravitycar\Factories\APIControllerFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;
use ReflectionClass;
use ReflectionProperty;

// Include mock controller for testing
require_once __DIR__ . '/MockApiController.php';

class RouterTest extends TestCase
{
    private Router $router;
    private MockObject $logger;
    private MockObject $metadataEngine;
    private MockObject $routeRegistry;
    private MockObject $pathScorer;
    private MockObject $controllerFactory;
    private MockObject $modelFactory;
    private MockObject $authenticationService;
    private MockObject $authorizationService;
    private MockObject $currentUserProvider;
    private array $originalServerState = [];
    private array $originalGetState = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Save original global state
        $this->originalServerState = $_SERVER;
        $this->originalGetState = $_GET ?? [];
        
        // Create all 9 required mocks for Router constructor
        $this->logger = $this->createMock(Logger::class);
        $this->metadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->routeRegistry = $this->createMock(APIRouteRegistry::class);
        $this->pathScorer = $this->createMock(APIPathScorer::class);
        $this->controllerFactory = $this->createMock(APIControllerFactory::class);
        
        // Configure controller factory to throw exception so Router falls back to direct instantiation
        $this->controllerFactory->method('createControllerWithDependencyList')
            ->willThrowException(new \Exception('Mock factory failure'));
            
        $this->modelFactory = $this->createMock(ModelFactory::class);
        $this->authenticationService = $this->createMock(AuthenticationService::class);
        $this->authorizationService = $this->createMock(AuthorizationService::class);
        $this->currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
        
        // Create router with all required dependencies for pure DI
        $this->router = new Router(
            $this->logger,
            $this->metadataEngine,
            $this->routeRegistry,
            $this->pathScorer,
            $this->controllerFactory,
            $this->modelFactory,
            $this->authenticationService,
            $this->authorizationService,
            $this->currentUserProvider
        );
    }

    protected function tearDown(): void
    {
        // Restore original global state
        $_SERVER = $this->originalServerState;
        $_GET = $this->originalGetState;
        
        parent::tearDown();
    }

    public function testConstructorWithAllDependencies(): void
    {
        // Test that router can be constructed with all 9 dependencies
        $router = new Router(
            $this->logger,
            $this->metadataEngine,
            $this->routeRegistry,
            $this->pathScorer,
            $this->controllerFactory,
            $this->modelFactory,
            $this->authenticationService,
            $this->authorizationService,
            $this->currentUserProvider
        );
        
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
            'apiClass' => 'MockApiController',
            'apiMethod' => 'getUsers',
            'parameterNames' => ['api', 'users'] // Match path components
        ];
        
        // Mock authentication - return a mock user
        $mockUser = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $mockUser->method('get')->with('id')->willReturn('test-user-id');
        $this->currentUserProvider->method('getCurrentUser')->willReturn($mockUser);
        
        // Mock authorization - allow the request
        $this->authorizationService->method('hasPermissionForRoute')->willReturn(true);
        
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
            'apiClass' => 'MockApiController',
            'apiMethod' => 'getUser',
            'parameterNames' => ['api', 'users', 'id'] // Match path components
        ];
        
        // Mock authentication - return a mock user
        $mockUser = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $mockUser->method('get')->with('id')->willReturn('test-user-id');
        $this->currentUserProvider->method('getCurrentUser')->willReturn($mockUser);
        
        // Mock authorization - allow the request
        $this->authorizationService->method('hasPermissionForRoute')->willReturn(true);
        
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
            'parameterNames' => ['api', 'users'] // Match path components
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
            'apiClass' => 'MockApiController',
            'apiMethod' => 'actuallyNonexistentMethod',
            'parameterNames' => ['api', 'users'] // Match path components
        ];
        
        $this->routeRegistry->method('getRoutesByMethodAndLength')
            ->willReturn([$route]);
        
        $this->pathScorer->method('findBestMatch')
            ->willReturn($route);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Handler method not found: actuallyNonexistentMethod');
        
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
            'apiClass' => 'MockApiController',
            'apiMethod' => 'getUsers',
            'parameterNames' => ['api', 'users'] // Match path components
        ];
        
        // Mock authentication - return a mock user
        $mockUser = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $mockUser->method('get')->with('id')->willReturn('test-user-id');
        $this->currentUserProvider->method('getCurrentUser')->willReturn($mockUser);
        
        // Mock authorization - allow the request
        $this->authorizationService->method('hasPermissionForRoute')->willReturn(true);
        
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
        
        // Mock ModelFactory to return a mock ModelBase (since method must return ModelBase, not null)
        $mockModel = $this->createMock(ModelBase::class);
        $this->modelFactory->method('new')->willReturn($mockModel);
        
        $method = $this->getPrivateMethod($this->router, 'getModel');
        $result = $method->invoke($this->router, $request);
        
        // Since ModelFactory returns a mock ModelBase, we expect the mock object
        $this->assertInstanceOf(ModelBase::class, $result);
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
    
    /**
     * Helper method to access private/protected methods using reflection
     */
    private function getPrivateMethod($object, string $methodName): \ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
    
    /**
     * Helper method to set private/protected properties using reflection
     */
    private function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
    
    /**
     * Helper method to create mock field objects
     */
    private function createMockField(string $name, bool $required = false): MockObject
    {
        $field = $this->createMock(\Gravitycar\Fields\FieldBase::class);
        $field->method('getName')->willReturn($name);
        $field->method('isRequired')->willReturn($required);
        $field->method('isDBField')->willReturn(true);
        return $field;
    }
}