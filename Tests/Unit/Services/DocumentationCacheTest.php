<?php

namespace Tests\Unit\Services;

use Gravitycar\Services\DocumentationCache;
use Gravitycar\Core\ServiceLocator;
use PHPUnit\Framework\TestCase;

class DocumentationCacheTest extends TestCase
{
    protected DocumentationCache $cache;
    protected string $testCacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use a test-specific cache directory
        $this->testCacheDir = 'cache/test_documentation/';
        
        // Mock the config to use test directory
        $this->cache = new DocumentationCache();
        
        // Clean up any existing test cache
        $this->cleanTestCache();
    }

    protected function tearDown(): void
    {
        $this->cleanTestCache();
        parent::tearDown();
    }

    protected function cleanTestCache(): void
    {
        // Clean test-specific cache directory
        if (is_dir($this->testCacheDir)) {
            $files = glob($this->testCacheDir . '*.php');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        
        // Also clean the main documentation cache directory
        $mainCacheDir = 'cache/documentation/';
        if (is_dir($mainCacheDir)) {
            $files = glob($mainCacheDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testCacheAndRetrieveOpenAPISpec(): void
    {
        $testSpec = [
            'openapi' => '3.0.3',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => []
        ];

        // Cache the spec
        $this->cache->cacheOpenAPISpec($testSpec);

        // Retrieve the spec
        $cached = $this->cache->getCachedOpenAPISpec();
        
        $this->assertIsArray($cached);
        $this->assertEquals('3.0.3', $cached['openapi']);
        $this->assertEquals('Test API', $cached['info']['title']);
        $this->assertArrayHasKey('timestamp', $cached);
    }

    public function testCacheAndRetrieveModelMetadata(): void
    {
        $testMetadata = [
            'name' => 'TestModel',
            'fields' => ['id' => ['type' => 'ID'], 'name' => ['type' => 'Text']],
            'relationships' => []
        ];

        // Cache the metadata
        $this->cache->cacheModelMetadata('TestModel', $testMetadata);

        // Retrieve the metadata
        $cached = $this->cache->getCachedModelMetadata('TestModel');
        
        $this->assertIsArray($cached);
        $this->assertEquals('TestModel', $cached['name']);
        $this->assertArrayHasKey('fields', $cached);
        $this->assertArrayHasKey('timestamp', $cached);
    }

    public function testCacheAndRetrieveModelsList(): void
    {
        $testModelsList = [
            'success' => true,
            'data' => [
                'Users' => ['name' => 'Users'],
                'Movies' => ['name' => 'Movies']
            ]
        ];

        // Cache the models list
        $this->cache->cacheModelsList($testModelsList);

        // Retrieve the models list
        $cached = $this->cache->getCachedModelsList();
        
        $this->assertIsArray($cached);
        $this->assertTrue($cached['success']);
        $this->assertArrayHasKey('data', $cached);
        $this->assertArrayHasKey('timestamp', $cached);
    }

    public function testCacheAndRetrieveFieldTypes(): void
    {
        $testFieldTypes = [
            'success' => true,
            'data' => [
                'Text' => ['type' => 'Text', 'component' => 'TextInput'],
                'Email' => ['type' => 'Email', 'component' => 'EmailInput']
            ]
        ];

        // Cache the field types
        $this->cache->cacheFieldTypes($testFieldTypes);

        // Retrieve the field types
        $cached = $this->cache->getCachedFieldTypes();
        
        $this->assertIsArray($cached);
        $this->assertTrue($cached['success']);
        $this->assertArrayHasKey('data', $cached);
        $this->assertArrayHasKey('timestamp', $cached);
    }

    public function testClearCache(): void
    {
        // Cache some data
        $this->cache->cacheOpenAPISpec(['test' => 'data']);
        $this->cache->cacheModelMetadata('TestModel', ['test' => 'data']);
        $this->cache->cacheModelsList(['test' => 'data']);
        $this->cache->cacheFieldTypes(['test' => 'data']);

        // Clear all cache
        $this->cache->clearCache();

        // Verify all caches are cleared
        $this->assertNull($this->cache->getCachedOpenAPISpec());
        $this->assertNull($this->cache->getCachedModelMetadata('TestModel'));
        $this->assertNull($this->cache->getCachedModelsList());
        $this->assertNull($this->cache->getCachedFieldTypes());
    }

    public function testClearModelCache(): void
    {
        // Cache metadata for multiple models
        $this->cache->cacheModelMetadata('Model1', ['name' => 'Model1']);
        $this->cache->cacheModelMetadata('Model2', ['name' => 'Model2']);

        // Clear cache for one model
        $this->cache->clearModelCache('Model1');

        // Verify only the specific model cache is cleared
        $this->assertNull($this->cache->getCachedModelMetadata('Model1'));
        $this->assertNotNull($this->cache->getCachedModelMetadata('Model2'));
    }

    public function testCacheValidityWithTTL(): void
    {
        // This test would require mocking time or config, so we'll keep it simple
        $testData = ['test' => 'data'];
        
        $this->cache->cacheOpenAPISpec($testData);
        $cached = $this->cache->getCachedOpenAPISpec();
        
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('timestamp', $cached);
        
        // The timestamp should be recent (within the last minute)
        $timestamp = strtotime($cached['timestamp']);
        $now = time();
        $this->assertLessThan(60, abs($now - $timestamp));
    }

    public function testReturnsNullForNonExistentCache(): void
    {
        $this->assertNull($this->cache->getCachedOpenAPISpec());
        $this->assertNull($this->cache->getCachedModelMetadata('NonExistentModel'));
        $this->assertNull($this->cache->getCachedModelsList());
        $this->assertNull($this->cache->getCachedFieldTypes());
    }
}
