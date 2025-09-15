<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\AnalyticsService;
use Psr\Log\LoggerInterface;
use Gravitycar\Core\Config;

class AnalyticsServiceTest extends TestCase
{
    private AnalyticsService $analyticsService;
    private LoggerInterface|MockObject $mockLogger;
    private Config|MockObject $mockConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockConfig = $this->createMock(Config::class);
        
        // Create service with injected dependencies
        $this->analyticsService = new AnalyticsService($this->mockLogger, $this->mockConfig);
    }

    public function testConstructorLogsInitialization(): void
    {
        // Expect logger to be called during constructor
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockConfig = $this->createMock(Config::class);
        
        $mockLogger->expects($this->once())
            ->method('info')
            ->with('AnalyticsService initialized as singleton');
        
        new AnalyticsService($mockLogger, $mockConfig);
    }

    public function testTrackEventLogsAndOutputsMessage(): void
    {
        $eventName = 'user_login';
        
        // Expect logger to be called
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with("Tracking event: {$eventName}");
        
        // Capture output
        $this->expectOutputString("📈 Event tracked: {$eventName}\n");
        
        $this->analyticsService->trackEvent($eventName);
    }

    public function testTrackEventWithDifferentEvents(): void
    {
        $eventName = 'page_view';
        
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with("Tracking event: {$eventName}");
        
        $this->expectOutputString("📈 Event tracked: {$eventName}\n");
        
        $this->analyticsService->trackEvent($eventName);
    }

    public function testConstructorRequiresDependencies(): void
    {
        // Test that dependency injection is working correctly
        $logger = $this->createMock(LoggerInterface::class);
        $config = $this->createMock(Config::class);
        
        $analyticsService = new AnalyticsService($logger, $config);
        
        $this->assertInstanceOf(AnalyticsService::class, $analyticsService);
    }

    public function testMultipleEventTracking(): void
    {
        // Test tracking multiple events in sequence
        $events = ['event1', 'event2', 'event3'];
        $expectedOutput = "📈 Event tracked: event1\n📈 Event tracked: event2\n📈 Event tracked: event3\n";
        
        // Configure logger mock to expect multiple calls
        $this->mockLogger->expects($this->exactly(3))
            ->method('info');
        
        $this->expectOutputString($expectedOutput);
        
        foreach ($events as $event) {
            $this->analyticsService->trackEvent($event);
        }
    }
}