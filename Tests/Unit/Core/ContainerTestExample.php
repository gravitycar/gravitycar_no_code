<?php
namespace Gravitycar\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Core\ContainerConfig;
use Gravitycar\Factories\ModelFactory;
use Monolog\Logger;
use Monolog\Handler\TestHandler;

/**
 * Example test demonstrating how easy testing becomes with the container.
 * Shows how to inject mock services for isolated unit testing.
 */
class ContainerTestExample extends TestCase {

    protected function setUp(): void {
        // Reset container before each test
        ServiceLocator::reset();
    }

    public function testWithMockServices(): void {
        // Create mock services for testing
        $mockLogger = $this->createMock(Logger::class);
        $mockConfig = $this->createMock(\Gravitycar\Core\Config::class);

        // Configure expectations
        $mockLogger->expects($this->once())
                   ->method('info')
                   ->with('Test message');

        $mockConfig->expects($this->once())
                   ->method('get')
                   ->with('test.key')
                   ->willReturn('test.value');

        // Configure container with mocks
        $testContainer = ContainerConfig::configureForTesting([
            'logger' => $mockLogger,
            'config' => $mockConfig
        ]);

        // Now any service that uses logger or config will get our mocks
        $logger = ServiceLocator::getLogger();
        $config = ServiceLocator::getConfig();

        // Test our services
        $logger->info('Test message');
        $result = $config->get('test.key');

        $this->assertEquals('test.value', $result);
    }

    public function testRealServicesStillWork(): void {
        // When no mocks are provided, real services are used
        $logger = ServiceLocator::getLogger();
        $config = ServiceLocator::getConfig();

        // These are real instances, not mocks
        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertInstanceOf(\Gravitycar\Core\Config::class, $config);
    }

    public function testModelCreationWithContainer(): void {
        // Mock logger for model creation
        $mockLogger = $this->createMock(Logger::class);

        ContainerConfig::configureForTesting([
            'logger' => $mockLogger
        ]);

        // Create a model - it automatically gets the mocked logger
        $metadata = ['fields' => []];
        $model = ModelFactory::new('Installer');

        $this->assertInstanceOf(\Gravitycar\Models\installer\Installer::class, $model);
    }
}
