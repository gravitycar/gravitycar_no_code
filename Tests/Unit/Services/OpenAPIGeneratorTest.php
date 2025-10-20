<?php

namespace Tests\Unit\Services;

use Gravitycar\Services\OpenAPIGenerator;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Core\Config;
use Gravitycar\Services\ReactComponentMapper;
use Gravitycar\Services\DocumentationCache;
use Gravitycar\Services\OpenAPIPermissionFilter;
use Gravitycar\Services\OpenAPIModelRouteBuilder;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class OpenAPIGeneratorTest extends TestCase
{
    protected OpenAPIGenerator $generator;
    protected LoggerInterface|MockObject $mockLogger;
    protected MetadataEngineInterface|MockObject $mockMetadataEngine;
    protected FieldFactory|MockObject $mockFieldFactory;
    protected DatabaseConnectorInterface|MockObject $mockDatabaseConnector;
    protected Config|MockObject $mockConfig;
    protected ReactComponentMapper|MockObject $mockComponentMapper;
    protected DocumentationCache|MockObject $mockCache;
    protected OpenAPIPermissionFilter|MockObject $mockPermissionFilter;
    protected OpenAPIModelRouteBuilder|MockObject $mockModelRouteBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing cache files before each test
        $this->clearDocumentationCache();
        
        // Create all mocks
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockFieldFactory = $this->createMock(FieldFactory::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockComponentMapper = $this->createMock(ReactComponentMapper::class);
        $this->mockCache = $this->createMock(DocumentationCache::class);
        $this->mockPermissionFilter = $this->createMock(OpenAPIPermissionFilter::class);
        $this->mockModelRouteBuilder = $this->createMock(OpenAPIModelRouteBuilder::class);
        
        // Configure mock config
        $this->mockConfig->method('get')->willReturnCallback(function($key, $default = null) {
            $configValues = [
                'documentation.cache_enabled' => false, // Disable cache for tests
                'documentation.openapi_version' => '3.0.3',
                'documentation.api_title' => 'Test API',
                'documentation.api_version' => '1.0.0',
                'documentation.api_description' => 'Test API Description',
                'documentation.validate_generated_schemas' => false // Disable validation for tests
            ];
            return $configValues[$key] ?? $default;
        });
        
        // Create service with injected dependencies
        $this->generator = new OpenAPIGenerator(
            $this->mockLogger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockConfig,
            $this->mockComponentMapper,
            $this->mockCache,
            $this->mockPermissionFilter,
            $this->mockModelRouteBuilder
        );
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

    public function testGenerateSpecificationStructure(): void
    {
        $spec = $this->generator->generateSpecification();
        
        $this->assertIsArray($spec);
        
        // Check required OpenAPI 3.0.3 fields
        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('servers', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('tags', $spec);
        
        // Check OpenAPI version
        $this->assertEquals('3.0.3', $spec['openapi']);
        
        // Check info structure
        $info = $spec['info'];
        $this->assertArrayHasKey('title', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('contact', $info);
        
        // Check servers structure
        $this->assertIsArray($spec['servers']);
        $this->assertNotEmpty($spec['servers']);
        foreach ($spec['servers'] as $server) {
            $this->assertArrayHasKey('url', $server);
            $this->assertArrayHasKey('description', $server);
        }
        
        // Check paths structure
        $this->assertIsArray($spec['paths']);
        
        // Check components structure
        $components = $spec['components'];
        $this->assertArrayHasKey('schemas', $components);
        $this->assertIsArray($components['schemas']);
        
        // Check tags structure
        $this->assertIsArray($spec['tags']);
    }

    public function testGenerateInfoSection(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $generateInfoMethod = $reflection->getMethod('generateInfo');
        $generateInfoMethod->setAccessible(true);
        
        $info = $generateInfoMethod->invoke($this->generator);
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('title', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('contact', $info);
        
        $this->assertIsString($info['title']);
        $this->assertIsString($info['version']);
        $this->assertIsString($info['description']);
        $this->assertIsArray($info['contact']);
    }

    public function testGenerateServersSection(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $generateServersMethod = $reflection->getMethod('generateServers');
        $generateServersMethod->setAccessible(true);
        
        $servers = $generateServersMethod->invoke($this->generator);
        
        $this->assertIsArray($servers);
        $this->assertNotEmpty($servers);
        
        foreach ($servers as $server) {
            $this->assertArrayHasKey('url', $server);
            $this->assertArrayHasKey('description', $server);
            $this->assertIsString($server['url']);
            $this->assertIsString($server['description']);
        }
    }

    public function testGeneratePathsSection(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $generatePathsMethod = $reflection->getMethod('generatePaths');
        $generatePathsMethod->setAccessible(true);
        
        $paths = $generatePathsMethod->invoke($this->generator);
        
        $this->assertIsArray($paths);
        
        // Each path should have HTTP methods as keys
        foreach ($paths as $path => $methods) {
            $this->assertIsString($path);
            $this->assertIsArray($methods);
            
            foreach ($methods as $method => $operation) {
                $this->assertContains($method, ['get', 'post', 'put', 'patch', 'delete']);
                $this->assertIsArray($operation);
                
                // Check operation structure
                $this->assertArrayHasKey('summary', $operation);
                $this->assertArrayHasKey('operationId', $operation);
                $this->assertArrayHasKey('tags', $operation);
                $this->assertArrayHasKey('responses', $operation);
                
                $this->assertIsString($operation['summary']);
                $this->assertIsString($operation['operationId']);
                $this->assertIsArray($operation['tags']);
                $this->assertIsArray($operation['responses']);
            }
        }
    }

    public function testGenerateComponentsWithCommonSchemas(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $generateComponentsMethod = $reflection->getMethod('generateComponents');
        $generateComponentsMethod->setAccessible(true);
        
        $components = $generateComponentsMethod->invoke($this->generator);
        
        $this->assertIsArray($components);
        $this->assertArrayHasKey('schemas', $components);
        
        $schemas = $components['schemas'];
        
        // Check for common schemas
        $this->assertArrayHasKey('ApiResponse', $schemas);
        $this->assertArrayHasKey('ValidationError', $schemas);
        $this->assertArrayHasKey('Pagination', $schemas);
        
        // Check ApiResponse schema
        $apiResponse = $schemas['ApiResponse'];
        $this->assertEquals('object', $apiResponse['type']);
        $this->assertArrayHasKey('properties', $apiResponse);
        $this->assertArrayHasKey('success', $apiResponse['properties']);
        $this->assertArrayHasKey('message', $apiResponse['properties']);
        $this->assertArrayHasKey('timestamp', $apiResponse['properties']);
        
        // Check ValidationError schema
        $validationError = $schemas['ValidationError'];
        $this->assertEquals('object', $validationError['type']);
        $this->assertArrayHasKey('properties', $validationError);
        $this->assertArrayHasKey('success', $validationError['properties']);
        $this->assertArrayHasKey('errors', $validationError['properties']);
        
        // Check Pagination schema
        $pagination = $schemas['Pagination'];
        $this->assertEquals('object', $pagination['type']);
        $this->assertArrayHasKey('properties', $pagination);
        $this->assertArrayHasKey('page', $pagination['properties']);
        $this->assertArrayHasKey('pageSize', $pagination['properties']);
        $this->assertArrayHasKey('total', $pagination['properties']);
        $this->assertArrayHasKey('totalPages', $pagination['properties']);
    }

    public function testExtractModelNameFromRoute(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $extractMethod = $reflection->getMethod('extractModelNameFromRoute');
        $extractMethod->setAccessible(true);
        
        // Test route with apiClass containing model name
        $routeWithApiClass = [
            'apiClass' => 'Gravitycar\\Models\\Users\\Api\\UsersAPIController',
            'path' => '/users'
        ];
        $modelName = $extractMethod->invoke($this->generator, $routeWithApiClass);
        $this->assertEquals('Users', $modelName);
        
        // Test route with path-based extraction
        $routeWithPath = [
            'apiClass' => 'SomeController',
            'path' => '/movies/123'
        ];
        $modelName = $extractMethod->invoke($this->generator, $routeWithPath);
        $this->assertEquals('movies', $modelName);
        
        // Test special endpoints
        $metadataRoute = [
            'apiClass' => 'MetadataAPIController',
            'path' => '/metadata/models'
        ];
        $modelName = $extractMethod->invoke($this->generator, $metadataRoute);
        $this->assertEquals('', $modelName);
    }

    public function testGenerateOperationSummary(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $generateSummaryMethod = $reflection->getMethod('generateOperationSummary');
        $generateSummaryMethod->setAccessible(true);
        
        // Test GET list operation
        $getListRoute = ['method' => 'GET', 'path' => '/users'];
        $summary = $generateSummaryMethod->invoke($this->generator, $getListRoute, 'Users');
        $this->assertEquals('List Users records', $summary);
        
        // Test GET single operation
        $getSingleRoute = ['method' => 'GET', 'path' => '/users/{id}'];
        $summary = $generateSummaryMethod->invoke($this->generator, $getSingleRoute, 'Users');
        $this->assertEquals('Get a specific Users record', $summary);
        
        // Test POST operation
        $postRoute = ['method' => 'POST', 'path' => '/users'];
        $summary = $generateSummaryMethod->invoke($this->generator, $postRoute, 'Users');
        $this->assertEquals('Create a new Users record', $summary);
        
        // Test PUT operation
        $putRoute = ['method' => 'PUT', 'path' => '/users/{id}'];
        $summary = $generateSummaryMethod->invoke($this->generator, $putRoute, 'Users');
        $this->assertEquals('Update a Users record', $summary);
        
        // Test DELETE operation
        $deleteRoute = ['method' => 'DELETE', 'path' => '/users/{id}'];
        $summary = $generateSummaryMethod->invoke($this->generator, $deleteRoute, 'Users');
        $this->assertEquals('Delete a Users record', $summary);
    }

    public function testGenerateOperationId(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $generateIdMethod = $reflection->getMethod('generateOperationId');
        $generateIdMethod->setAccessible(true);
        
        $route = ['method' => 'GET', 'path' => '/users/{id}'];
        $operationId = $generateIdMethod->invoke($this->generator, $route);
        
        $this->assertIsString($operationId);
        $this->assertEquals('get_users_id', $operationId);
    }

    public function testGenerateBasicFieldSchema(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $generateSchemaMethod = $reflection->getMethod('generateBasicFieldSchema');
        $generateSchemaMethod->setAccessible(true);
        
        // Test Text field
        $textField = ['type' => 'Text', 'description' => 'A text field'];
        $schema = $generateSchemaMethod->invoke($this->generator, $textField);
        $this->assertEquals('string', $schema['type']);
        $this->assertEquals('A text field', $schema['description']);
        
        // Test Integer field
        $integerField = ['type' => 'Integer'];
        $schema = $generateSchemaMethod->invoke($this->generator, $integerField);
        $this->assertEquals('integer', $schema['type']);
        
        // Test Boolean field
        $booleanField = ['type' => 'Boolean'];
        $schema = $generateSchemaMethod->invoke($this->generator, $booleanField);
        $this->assertEquals('boolean', $schema['type']);
        
        // Test Email field
        $emailField = ['type' => 'Email'];
        $schema = $generateSchemaMethod->invoke($this->generator, $emailField);
        $this->assertEquals('string', $schema['type']);
        $this->assertEquals('email', $schema['format']);
        
        // Test Enum field with options
        $enumField = ['type' => 'Enum', 'options' => ['option1' => 'Option 1', 'option2' => 'Option 2']];
        $schema = $generateSchemaMethod->invoke($this->generator, $enumField);
        $this->assertEquals('string', $schema['type']);
        $this->assertArrayHasKey('enum', $schema);
        $this->assertEquals(['option1', 'option2'], $schema['enum']);
    }

    public function testValidateOpenAPISpec(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $validateMethod = $reflection->getMethod('validateOpenAPISpec');
        $validateMethod->setAccessible(true);
        
        // Valid spec
        $validSpec = [
            'openapi' => '3.0.3',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => []
        ];
        
        // Should not throw exception
        $validateMethod->invoke($this->generator, $validSpec);
        $this->assertTrue(true); // If we get here, validation passed
        
        // Invalid spec - missing openapi
        $invalidSpec1 = [
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => []
        ];
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAPI specification missing required field: openapi');
        $validateMethod->invoke($this->generator, $invalidSpec1);
    }

    public function testGenerateTags(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $generateTagsMethod = $reflection->getMethod('generateTags');
        $generateTagsMethod->setAccessible(true);
        
        $tags = $generateTagsMethod->invoke($this->generator);
        
        $this->assertIsArray($tags);
        $this->assertNotEmpty($tags);
        
        // Should always have 'General' tag
        $tagNames = array_column($tags, 'name');
        $this->assertContains('General', $tagNames);
        
        // Each tag should have name and description
        foreach ($tags as $tag) {
            $this->assertArrayHasKey('name', $tag);
            $this->assertArrayHasKey('description', $tag);
            $this->assertIsString($tag['name']);
            $this->assertIsString($tag['description']);
        }
    }
}
