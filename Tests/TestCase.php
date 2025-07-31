<?php

namespace Gravitycar\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Gravitycar\Core\Config;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Aura\Di\Container;

/**
 * Base test case class for all Gravitycar tests.
 * Provides common functionality and setup for test cases.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected Config $config;
    protected ?DatabaseConnector $db = null;
    protected ?Container $serviceLocator = null;
    protected Logger $logger;
    protected TestHandler $testHandler;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test logger first
        $this->testHandler = new TestHandler();
        $this->logger = new Logger('test');
        $this->logger->pushHandler($this->testHandler);

        // Create config with logger first, then load test configuration
        $this->config = new Config($this->logger);
        
        // Override config values for testing
        $this->config->set('database.host', 'localhost');
        $this->config->set('database.database', 'gravitycar_test');
        $this->config->set('database.username', 'test');
        $this->config->set('database.password', 'test');
        $this->config->set('app.env', 'testing');
        $this->config->set('app.debug', true);

        // For testing, we'll use the actual ServiceLocator but won't try to modify it
        // The ServiceLocator uses Aura DI container which should be configured elsewhere
        try {
            $this->serviceLocator = ServiceLocator::getContainer();
        } catch (\Exception $e) {
            // If ServiceLocator fails, we'll skip it for basic unit tests
            $this->serviceLocator = null;
        }
    }

    protected function tearDown(): void
    {
        // Clean up - no need to reset ServiceLocator as it's a static container
        parent::tearDown();
    }

    /**
     * Assert that a log message was recorded with the specified level.
     */
    protected function assertLoggedMessage(string $level, string $message): void
    {
        $records = $this->testHandler->getRecords();

        foreach ($records as $record) {
            if ($record['level_name'] === strtoupper($level) &&
                strpos($record['message'], $message) !== false) {
                $this->assertTrue(true);
                return;
            }
        }

        $this->fail("Expected log message '{$message}' with level '{$level}' was not found.");
    }

    /**
     * Get all log records from the test handler.
     */
    protected function getLogRecords(): array
    {
        return $this->testHandler->getRecords();
    }

    /**
     * Clear all log records.
     */
    protected function clearLogRecords(): void
    {
        $this->testHandler->clear();
    }
}
