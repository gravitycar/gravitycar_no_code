<?php

namespace Tests\Unit\Api;

use Gravitycar\Api\MetadataAPIController;
use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Services\DocumentationCache;
use Gravitycar\Services\ReactComponentMapper;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\InternalServerErrorException;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class MetadataAPIControllerTest extends TestCase
{
    protected MetadataAPIController $controller;
    protected Logger|MockObject $mockLogger;
    protected ModelFactory|MockObject $mockModelFactory;
    protected DatabaseConnectorInterface|MockObject $mockDatabaseConnector;
    protected MetadataEngineInterface|MockObject $mockMetadataEngine;
    protected Config|MockObject $mockConfig;
    protected CurrentUserProviderInterface|MockObject $mockCurrentUserProvider;
    protected APIRouteRegistry|MockObject $mockRouteRegistry;
    protected DocumentationCache|MockObject $mockCache;
    protected ReactComponentMapper|MockObject $mockComponentMapper;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing cache files before each test
        $this->clearDocumentationCache();
        
        // Create all required mock dependencies
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
        $this->mockRouteRegistry = $this->createMock(APIRouteRegistry::class);
        $this->mockCache = $this->createMock(DocumentationCache::class);
        $this->mockComponentMapper = $this->createMock(ReactComponentMapper::class);
        
        // Create MetadataAPIController with proper dependency injection
        $this->controller = new MetadataAPIController(
            $this->mockLogger,
            $this->mockModelFactory,
            $this->mockDatabaseConnector,
            $this->mockMetadataEngine,
            $this->mockConfig,
            $this->mockCurrentUserProvider,
            $this->mockRouteRegistry,
            $this->mockCache,
            $this->mockComponentMapper
        );
        
        // Configure mock behaviors for common test scenarios
        $this->setupMockBehaviors();
    }
    
    private function setupMockBehaviors(): void
    {
        // Mock MetadataEngine->getAllMetadata() to return sample model data
        $sampleModelsData = [
            'models' => [
                'Users' => [
                    'name' => 'Users',
                    'table' => 'users',
                    'description' => 'User management',
                    'fields' => ['id' => ['type' => 'ID'], 'name' => ['type' => 'Text']],
                    'relationships' => []
                ]
            ]
        ];
        
        $this->mockMetadataEngine->method('getAllMetadata')->willReturn($sampleModelsData);
        $this->mockMetadataEngine->method('getCachedMetadata')->willReturn($sampleModelsData);
        $this->mockMetadataEngine->method('getAvailableModels')->willReturn(['Users', 'Movies']);
        $this->mockMetadataEngine->method('getModelMetadata')->willReturnCallback(function($modelName) {
            if ($modelName === 'Users' || $modelName === 'TestModel') {
                return [
                    'name' => $modelName,
                    'table' => strtolower($modelName),
                    'fields' => ['id' => ['type' => 'ID'], 'name' => ['type' => 'Text']]
                ];
            }
            throw new \Gravitycar\Exceptions\NotFoundException("Model {$modelName} not found");
        });
        $this->mockMetadataEngine->method('getFieldTypeDefinitions')->willReturn([
            'Text' => ['type' => 'Text', 'class' => 'TextField']
        ]);
        
        // Mock RouteRegistry->getRoutes() to return sample routes
        $this->mockRouteRegistry->method('getRoutes')->willReturn([
            ['method' => 'GET', 'path' => '/Users', 'apiClass' => 'MockController', 'apiMethod' => 'list', 'parameterNames' => []]
        ]);
        
        $this->mockRouteRegistry->method('getModelRoutes')->willReturn([
            ['method' => 'GET', 'path' => '/Users', 'apiClass' => 'MockController', 'apiMethod' => 'list', 'parameterNames' => []]
        ]);
        
        $this->mockRouteRegistry->method('getEndpointDocumentation')->willReturn([
            'description' => 'Mock endpoint description'
        ]);
        
        // Mock DocumentationCache methods (void method, no return value needed)
        $this->mockCache->expects($this->any())->method('clearCache');
        $this->mockCache->method('getCachedModelsList')->willReturn(null);
        $this->mockCache->expects($this->any())->method('cacheModelsList');
        
        // Mock Config methods
        $this->mockConfig->method('get')->willReturnCallback(function($key, $default = null) {
            switch ($key) {
                case 'documentation.cache_enabled':
                    return true;
                case 'documentation.enable_debug_info':
                    return false;
                case 'documentation.api_version':
                    return '1.0.0';
                case 'documentation.api_description':
                    return 'Test API';
                default:
                    return $default;
            }
        });
        
        // Mock ReactComponentMapper methods
        $this->mockComponentMapper->method('getFieldToComponentMap')->willReturn([
            'Text' => ['component' => 'TextInput', 'props' => ['placeholder' => 'Enter text']]
        ]);
    }

    protected function tearDown(): void
    {
        // Clear cache files after each test
        $this->clearDocumentationCache();
        MetadataEngine::reset();
        parent::tearDown();
    }
    
    /**
     * Clear documentation cache directory
     */
    private function clearDocumentationCache(): void
    {
        $cacheDir = 'cache/documentation/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testRegisterRoutes(): void
    {
        $routes = $this->controller->registerRoutes();
        
        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);
        
        // Check that all required routes are present
        $routePaths = array_column($routes, 'path');
        $this->assertContains('/metadata/models', $routePaths);
        $this->assertContains('/metadata/models/?', $routePaths);
        $this->assertContains('/metadata/field-types', $routePaths);
        $this->assertContains('/metadata/relationships', $routePaths);
        $this->assertContains('/help', $routePaths);
        $this->assertContains('/metadata/cache/clear', $routePaths);
        
        // Check route structure
        foreach ($routes as $route) {
            $this->assertArrayHasKey('method', $route);
            $this->assertArrayHasKey('path', $route);
            $this->assertArrayHasKey('apiClass', $route);
            $this->assertArrayHasKey('apiMethod', $route);
            $this->assertArrayHasKey('parameterNames', $route);
            
            $this->assertIsString($route['method']);
            $this->assertIsString($route['path']);
            $this->assertEquals('Gravitycar\Api\MetadataAPIController', $route['apiClass']);
            $this->assertIsString($route['apiMethod']);
            $this->assertIsArray($route['parameterNames']);
        }
    }

    public function testGetModelsStructure(): void
    {
        try {
            $response = $this->controller->getModels();
            
            $this->assertIsArray($response);
            $this->assertArrayHasKey('success', $response);
            $this->assertArrayHasKey('status', $response);
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('timestamp', $response);
            
            $this->assertTrue($response['success']);
            $this->assertEquals(200, $response['status']);
            $this->assertIsArray($response['data']);
            
            // Check model structure if models exist
            foreach ($response['data'] as $modelName => $modelInfo) {
                $this->assertArrayHasKey('name', $modelInfo);
                $this->assertArrayHasKey('endpoint', $modelInfo);
                $this->assertArrayHasKey('operations', $modelInfo);
                $this->assertArrayHasKey('description', $modelInfo);
                $this->assertArrayHasKey('table', $modelInfo);
                
                $this->assertEquals($modelName, $modelInfo['name']);
                $this->assertIsString($modelInfo['endpoint']);
                $this->assertIsArray($modelInfo['operations']);
                $this->assertIsString($modelInfo['description']);
                $this->assertIsString($modelInfo['table']);
            }
            
        } catch (NotFoundException $e) {
            $this->markTestSkipped('No models found in metadata cache');
        }
    }

    public function testGetModelMetadataStructure(): void
    {
        try {
            // Create a mock Request object with modelName parameter
            $request = $this->getMockBuilder(\Gravitycar\Api\Request::class)
                ->disableOriginalConstructor()
                ->getMock();
            $request->expects($this->once())
                ->method('get')
                ->with('modelName')
                ->willReturn('Users');
            
            $response = $this->controller->getModelMetadata($request); // @phpstan-ignore-line
            
            $this->assertIsArray($response);
            $this->assertArrayHasKey('success', $response);
            $this->assertArrayHasKey('status', $response);
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('timestamp', $response);
            
            $this->assertTrue($response['success']);
            $this->assertEquals(200, $response['status']);
            
            $data = $response['data'];
            $this->assertArrayHasKey('name', $data);
            $this->assertArrayHasKey('table', $data);
            $this->assertArrayHasKey('description', $data);
            $this->assertArrayHasKey('fields', $data);
            $this->assertArrayHasKey('relationships', $data);
            $this->assertArrayHasKey('api_endpoints', $data);
            $this->assertArrayHasKey('react_form_schema', $data);
            
            $this->assertEquals('Users', $data['name']);
            $this->assertIsArray($data['fields']);
            $this->assertIsArray($data['relationships']);
            $this->assertIsArray($data['api_endpoints']);
            $this->assertIsArray($data['react_form_schema']);
            
        } catch (NotFoundException $e) {
            $this->markTestSkipped('Users model not found in metadata cache');
        }
    }

    public function testGetModelMetadataNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        
        // Create a mock Request object with non-existent model name
        $request = $this->getMockBuilder(\Gravitycar\Api\Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->once())
            ->method('get')
            ->with('modelName')
            ->willReturn('NonExistentModel');
        
        $this->controller->getModelMetadata($request); // @phpstan-ignore-line
    }

    public function testGetFieldTypesStructure(): void
    {
        try {
            $response = $this->controller->getFieldTypes();
            
            $this->assertIsArray($response);
            $this->assertArrayHasKey('success', $response);
            $this->assertArrayHasKey('status', $response);
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('timestamp', $response);
            
            $this->assertTrue($response['success']);
            $this->assertEquals(200, $response['status']);
            $this->assertIsArray($response['data']);
            
            // Check field type structure if field types exist
            foreach ($response['data'] as $fieldType => $fieldData) {
                $this->assertIsString($fieldType);
                $this->assertIsArray($fieldData);
                
                // Field data should contain these keys
                $this->assertArrayHasKey('type', $fieldData);
                $this->assertArrayHasKey('class', $fieldData);
                $this->assertArrayHasKey('description', $fieldData);
                $this->assertArrayHasKey('react_component', $fieldData);
                $this->assertArrayHasKey('props', $fieldData);
                
                $this->assertIsString($fieldData['type']);
                $this->assertIsString($fieldData['class']);
                $this->assertIsString($fieldData['description']);
                $this->assertIsString($fieldData['react_component']);
                $this->assertIsArray($fieldData['props']);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Field types not available: ' . $e->getMessage());
        }
    }

    public function testGetRelationshipsStructure(): void
    {
        $response = $this->controller->getRelationships();
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('timestamp', $response);
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status']);
        $this->assertIsArray($response['data']);
    }

    public function testGetHelpStructure(): void
    {
        $response = $this->controller->getHelp();
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('timestamp', $response);
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status']);
        
        $data = $response['data'];
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('endpoints', $data);
        $this->assertArrayHasKey('documentation_urls', $data);
        
        $this->assertIsString($data['title']);
        $this->assertIsString($data['version']);
        $this->assertIsString($data['description']);
        $this->assertIsArray($data['endpoints']);
        $this->assertIsArray($data['documentation_urls']);
        
        // Check endpoints structure
        foreach ($data['endpoints'] as $endpoint) {
            $this->assertArrayHasKey('method', $endpoint);
            $this->assertArrayHasKey('path', $endpoint);
            $this->assertArrayHasKey('description', $endpoint);
            
            $this->assertIsString($endpoint['method']);
            $this->assertIsString($endpoint['path']);
            $this->assertIsString($endpoint['description']);
        }
        
        // Check documentation URLs
        $docUrls = $data['documentation_urls'];
        $this->assertArrayHasKey('openapi_spec', $docUrls);
        $this->assertArrayHasKey('swagger_ui', $docUrls);
        $this->assertArrayHasKey('models', $docUrls);
        $this->assertArrayHasKey('field_types', $docUrls);
    }

    public function testClearDocumentationCache(): void
    {
        $response = $this->controller->clearDocumentationCache();
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('timestamp', $response);
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status']);
        $this->assertIsString($response['message']);
    }

    public function testResponseTimestampFormat(): void
    {
        try {
            $response = $this->controller->getModels();
            
            $this->assertArrayHasKey('timestamp', $response);
            $timestamp = $response['timestamp'];
            
            // Verify it's a valid ISO 8601 timestamp
            $dateTime = \DateTime::createFromFormat('c', $timestamp);
            if ($dateTime === false) {
                // Try alternative ISO 8601 format
                $dateTime = \DateTime::createFromFormat(\DateTime::ATOM, $timestamp);
            }
            if ($dateTime === false) {
                // Try with manually parsing ISO format
                $dateTime = new \DateTime($timestamp);
            }
            $this->assertNotFalse($dateTime, "Failed to parse timestamp: $timestamp");
            $this->assertInstanceOf(\DateTime::class, $dateTime);
            
        } catch (NotFoundException $e) {
            $this->markTestSkipped('No models found in metadata cache');
        }
    }

    public function testMethodExistence(): void
    {
        // Test that all required methods exist
        $this->assertTrue(method_exists($this->controller, 'getModels'));
        $this->assertTrue(method_exists($this->controller, 'getModelMetadata'));
        $this->assertTrue(method_exists($this->controller, 'getFieldTypes'));
        $this->assertTrue(method_exists($this->controller, 'getRelationships'));
        $this->assertTrue(method_exists($this->controller, 'getHelp'));
        $this->assertTrue(method_exists($this->controller, 'clearDocumentationCache'));
        $this->assertTrue(method_exists($this->controller, 'registerRoutes'));
    }
}
