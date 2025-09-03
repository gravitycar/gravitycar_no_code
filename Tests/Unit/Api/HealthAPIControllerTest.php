<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Api\HealthAPIController;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Core\Config;
use Gravitycar\Database\DatabaseConnector;
use Monolog\Logger;
use ReflectionClass;
use ReflectionMethod;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;

/**
 * Unit tests for HealthAPIController
 * Tests health monitoring endpoints and system diagnostics
 */
class HealthAPIControllerTest extends TestCase
{
    private HealthAPIController $controller;
    private MockObject $mockConfig;
    private MockObject $mockLogger;
    private MockObject $mockDatabase;
    private MockObject $mockConnection;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockDatabase = $this->createMock(DatabaseConnector::class);
        $this->mockConnection = $this->createMock(Connection::class);
        
        // Set up database mock chain
        $this->mockDatabase->method('getConnection')->willReturn($this->mockConnection);
        
        // Create controller instance
        $this->controller = new HealthAPIController();
        
        // Inject mocks using reflection
        $this->setPrivateProperty($this->controller, 'config', $this->mockConfig);
        $this->setPrivateProperty($this->controller, 'logger', $this->mockLogger);
        
        // Clear static cache before each test
        $this->setPrivateStaticProperty(HealthAPIController::class, 'cachedChecks', null);
        $this->setPrivateStaticProperty(HealthAPIController::class, 'lastCheckTime', null);
    }

    public function testRegisterRoutes(): void
    {
        $routes = $this->controller->registerRoutes();
        
        $this->assertIsArray($routes);
        $this->assertCount(2, $routes);
        
        // Test /health route
        $healthRoute = $routes[0];
        $this->assertEquals('GET', $healthRoute['method']);
        $this->assertEquals('/health', $healthRoute['path']);
        $this->assertEquals('\\Gravitycar\\Api\\HealthAPIController', $healthRoute['apiClass']);
        $this->assertEquals('getHealth', $healthRoute['apiMethod']);
        $this->assertEmpty($healthRoute['parameterNames']);
        
        // Test /ping route
        $pingRoute = $routes[1];
        $this->assertEquals('GET', $pingRoute['method']);
        $this->assertEquals('/ping', $pingRoute['path']);
        $this->assertEquals('\\Gravitycar\\Api\\HealthAPIController', $pingRoute['apiClass']);
        $this->assertEquals('getPing', $pingRoute['apiMethod']);
        $this->assertEmpty($pingRoute['parameterNames']);
    }

    public function testGetPing(): void
    {
        $result = $this->controller->getPing();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('timestamp', $result);
        
        // Verify timestamp format (ISO 8601)
        $timestamp = $result['timestamp'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $timestamp);
    }

    public function testGetHealthWithoutCaching(): void
    {
        // Configure mock to disable caching
        $this->mockConfig->method('get')->willReturnMap([
            ['health.enable_caching', true, false],
            ['health.check_database', true, true],
            ['health.database_timeout', 5, 5],
            ['health.expose_detailed_errors', false, false],
            ['cache.metadata_file', 'cache/metadata_cache.php', 'cache/metadata_cache.php'],
            ['cache.directory', 'cache', 'cache'],
            ['logging.directory', 'logs', 'logs'],
            ['health.memory_warning_percentage', 80, 80],
            ['app.version', '1.0.0', '1.0.0'],
            ['app.environment', 'development', 'testing'],
            ['health.enable_debug_info', false, false],
            ['health.metadata_stale_hours', 24, 24]
        ]);

        // Mock successful database check
        $mockResult = $this->createMock(Result::class);
        $this->mockConnection->method('executeQuery')->willReturn($mockResult);

        // Mock file system
        $this->mockFileSystem();

        $result = $this->controller->getHealth();
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('data', $result);
        
        $data = $result['data'];
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('uptime', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('environment', $data);
        
        // Verify checks structure
        $checks = $data['checks'];
        $this->assertArrayHasKey('metadata_cache', $checks);
        $this->assertArrayHasKey('file_system', $checks);
        $this->assertArrayHasKey('memory', $checks);
        $this->assertArrayHasKey('database', $checks);
    }

    public function testGetHealthWithCaching(): void
    {
        // Configure mock to enable caching
        $this->mockConfig->method('get')->willReturnMap([
            ['health.enable_caching', true, true]
        ]);

        // Set up cached result
        $cachedResult = ['cached' => true];
        $this->setPrivateStaticProperty(HealthAPIController::class, 'cachedChecks', $cachedResult);
        $this->setPrivateStaticProperty(HealthAPIController::class, 'lastCheckTime', microtime(true));

        $result = $this->controller->getHealth();
        
        $this->assertEquals($cachedResult, $result);
    }

    public function testGetHealthWithExpiredCache(): void
    {
        // Configure mock
        $this->mockConfig->method('get')->willReturnMap([
            ['health.enable_caching', true, true],
            ['health.check_database', true, false], // Disable database check for simplicity
            ['cache.metadata_file', 'cache/metadata_cache.php', 'cache/metadata_cache.php'],
            ['cache.directory', 'cache', 'cache'],
            ['logging.directory', 'logs', 'logs'],
            ['health.memory_warning_percentage', 80, 80],
            ['app.version', '1.0.0', '1.0.0'],
            ['app.environment', 'development', 'testing'],
            ['health.enable_debug_info', false, false],
            ['health.metadata_stale_hours', 24, 24]
        ]);

        // Set up expired cached result (31 seconds ago, cache TTL is 30)
        $cachedResult = ['cached' => true];
        $this->setPrivateStaticProperty(HealthAPIController::class, 'cachedChecks', $cachedResult);
        $this->setPrivateStaticProperty(HealthAPIController::class, 'lastCheckTime', microtime(true) - 31);

        // Mock file system
        $this->mockFileSystem();

        $result = $this->controller->getHealth();
        
        // Should perform new checks, not return cached result
        $this->assertNotEquals($cachedResult, $result);
        $this->assertTrue($result['success']);
    }

    public function testCheckDatabaseSuccess(): void
    {
        $this->mockConfig->method('get')->willReturnMap([
            ['health.database_timeout', 5, 5],
            ['health.expose_detailed_errors', false, false]
        ]);

        // Since ServiceLocator::getDatabaseConnector() is called directly,
        // this test would require mocking the ServiceLocator static method
        // For now, we'll test the method exists and has the right signature
        $method = $this->getPrivateMethod($this->controller, 'checkDatabase');
        $this->assertTrue($method->isPrivate());
        $this->assertEquals('checkDatabase', $method->getName());
        
        // In a production test, you would mock ServiceLocator or use dependency injection
        $this->markTestSkipped('Requires ServiceLocator mocking which needs more complex setup');
    }

    public function testCheckDatabaseFailure(): void
    {
        // This test requires mocking ServiceLocator static methods
        // which is complex in PHPUnit without additional tooling
        $this->markTestSkipped('Requires ServiceLocator mocking which needs more complex setup');
    }

    public function testCheckDatabaseFailureWithDetailedErrors(): void
    {
        // This test requires mocking ServiceLocator static methods
        // which is complex in PHPUnit without additional tooling
        $this->markTestSkipped('Requires ServiceLocator mocking which needs more complex setup');
    }

    public function testCheckMetadataCacheFileExists(): void
    {
        $this->mockConfig->method('get')->willReturnMap([
            ['cache.metadata_file', 'cache/metadata_cache.php', '/tmp/test_metadata_cache.php'],
            ['health.metadata_stale_hours', 24, 24]
        ]);

        // Create temporary test file
        $testFile = '/tmp/test_metadata_cache.php';
        file_put_contents($testFile, '<?php return [];');

        $method = $this->getPrivateMethod($this->controller, 'checkMetadataCache');
        $result = $method->invoke($this->controller);
        
        $this->assertEquals('healthy', $result['status']);
        $this->assertTrue($result['file_exists']);
        $this->assertArrayHasKey('file_size_kb', $result);
        $this->assertArrayHasKey('last_modified', $result);

        // Clean up
        unlink($testFile);
    }

    public function testCheckMetadataCacheFileMissing(): void
    {
        $this->mockConfig->method('get')->willReturnMap([
            ['cache.metadata_file', 'cache/metadata_cache.php', '/tmp/nonexistent_file.php']
        ]);

        $method = $this->getPrivateMethod($this->controller, 'checkMetadataCache');
        $result = $method->invoke($this->controller);
        
        $this->assertEquals('missing', $result['status']);
        $this->assertFalse($result['file_exists']);
    }

    public function testCheckMetadataCacheStaleFile(): void
    {
        $this->mockConfig->method('get')->willReturnMap([
            ['cache.metadata_file', 'cache/metadata_cache.php', '/tmp/test_stale_cache.php'],
            ['health.metadata_stale_hours', 24, 1] // 1 hour threshold
        ]);

        // Create file that's older than threshold
        $testFile = '/tmp/test_stale_cache.php';
        file_put_contents($testFile, '<?php return [];');
        touch($testFile, time() - 7200); // 2 hours ago

        $method = $this->getPrivateMethod($this->controller, 'checkMetadataCache');
        $result = $method->invoke($this->controller);
        
        $this->assertEquals('stale', $result['status']);
        $this->assertTrue($result['file_exists']);

        // Clean up
        unlink($testFile);
    }

    public function testCheckFileSystemHealthy(): void
    {
        $this->mockConfig->method('get')->willReturnMap([
            ['cache.directory', 'cache', '/tmp'],
            ['logging.directory', 'logs', '/tmp']
        ]);

        $method = $this->getPrivateMethod($this->controller, 'checkFileSystem');
        $result = $method->invoke($this->controller);
        
        $this->assertEquals('healthy', $result['status']);
        $this->assertTrue($result['cache_writable']);
        $this->assertTrue($result['logs_writable']);
    }

    public function testCheckMemoryHealthy(): void
    {
        $this->mockConfig->method('get')->willReturnMap([
            ['health.memory_warning_percentage', 80, 80]
        ]);

        $method = $this->getPrivateMethod($this->controller, 'checkMemory');
        $result = $method->invoke($this->controller);
        
        $this->assertContains($result['status'], ['healthy', 'warning']);
        $this->assertArrayHasKey('usage_mb', $result);
        $this->assertArrayHasKey('peak_mb', $result);
        $this->assertArrayHasKey('limit_mb', $result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertIsNumeric($result['usage_mb']);
        $this->assertIsNumeric($result['peak_mb']);
        $this->assertIsNumeric($result['percentage']);
    }

    public function testGetMemoryLimit(): void
    {
        $method = $this->getPrivateMethod($this->controller, 'getMemoryLimit');
        $result = $method->invoke($this->controller);
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testCalculateOverallStatusHealthy(): void
    {
        $checks = [
            'database' => ['status' => 'healthy'],
            'metadata_cache' => ['status' => 'healthy'],
            'file_system' => ['status' => 'healthy'],
            'memory' => ['status' => 'healthy']
        ];

        $method = $this->getPrivateMethod($this->controller, 'calculateOverallStatus');
        $result = $method->invoke($this->controller, $checks);
        
        $this->assertEquals('healthy', $result);
    }

    public function testCalculateOverallStatusDegraded(): void
    {
        $checks = [
            'database' => ['status' => 'healthy'],
            'metadata_cache' => ['status' => 'stale'],
            'file_system' => ['status' => 'healthy'],
            'memory' => ['status' => 'warning']
        ];

        $method = $this->getPrivateMethod($this->controller, 'calculateOverallStatus');
        $result = $method->invoke($this->controller, $checks);
        
        $this->assertEquals('degraded', $result);
    }

    public function testCalculateOverallStatusUnhealthy(): void
    {
        $checks = [
            'database' => ['status' => 'unhealthy'],
            'metadata_cache' => ['status' => 'healthy'],
            'file_system' => ['status' => 'healthy'],
            'memory' => ['status' => 'healthy']
        ];

        $method = $this->getPrivateMethod($this->controller, 'calculateOverallStatus');
        $result = $method->invoke($this->controller, $checks);
        
        $this->assertEquals('unhealthy', $result);
    }

    public function testCalculateUptime(): void
    {
        $method = $this->getPrivateMethod($this->controller, 'calculateUptime');
        $result = $method->invoke($this->controller);
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testSafeCheckSuccess(): void
    {
        $mockCallback = function() {
            return ['status' => 'healthy'];
        };

        $method = $this->getPrivateMethod($this->controller, 'safeCheck');
        $result = $method->invoke($this->controller, $mockCallback, 'test_check');
        
        $this->assertEquals(['status' => 'healthy'], $result);
    }

    public function testSafeCheckFailure(): void
    {
        $this->mockConfig->method('get')->willReturnMap([
            ['health.expose_detailed_errors', false, false]
        ]);

        $mockCallback = function() {
            throw new \Exception('Test error');
        };

        // Expect warning to be logged
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Health check failed: test_check'),
                $this->arrayHasKey('error')
            );

        $method = $this->getPrivateMethod($this->controller, 'safeCheck');
        $result = $method->invoke($this->controller, $mockCallback, 'test_check');
        
        $this->assertEquals('unhealthy', $result['status']);
        $this->assertEquals('Check failed', $result['error']);
    }

    public function testSafeCheckFailureWithDetailedErrors(): void
    {
        $this->mockConfig->method('get')->willReturnMap([
            ['health.expose_detailed_errors', false, true]
        ]);

        $errorMessage = 'Detailed test error';
        $mockCallback = function() use ($errorMessage) {
            throw new \Exception($errorMessage);
        };

        $method = $this->getPrivateMethod($this->controller, 'safeCheck');
        $result = $method->invoke($this->controller, $mockCallback, 'test_check');
        
        $this->assertEquals('unhealthy', $result['status']);
        $this->assertEquals($errorMessage, $result['error']);
    }

    // Helper methods

    private function setPrivateProperty(object $object, string $property, $value): void
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function setPrivateStaticProperty(string $className, string $property, $value): void
    {
        $reflection = new ReflectionClass($className);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }

    private function getPrivateMethod(object $object, string $method): ReflectionMethod
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method;
    }

    private function mockFileSystem(): void
    {
        // This would ideally use vfsStream or similar for proper file system mocking
        // For now, we'll use temp directories that should be writable
        // In a real implementation, consider using vfsStream for better isolation
    }
}
