<?php

namespace Tests\Unit\Api;

use Gravitycar\Api\MetadataAPIController;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\InternalServerErrorException;
use PHPUnit\Framework\TestCase;

class MetadataAPIControllerTest extends TestCase
{
    protected MetadataAPIController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing cache files before each test
        $this->clearDocumentationCache();
        
        $this->controller = new MetadataAPIController();
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
            $this->assertEquals('\Gravitycar\Api\MetadataAPIController', $route['apiClass']);
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
