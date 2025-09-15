<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\ReportGenerator;
use Psr\Log\LoggerInterface;
use Gravitycar\Core\Config;

class ReportGeneratorTest extends TestCase
{
    private LoggerInterface|MockObject $mockLogger;
    private Config|MockObject $mockConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockConfig = $this->createMock(Config::class);
    }

    public function testConstructorLogsInitialization(): void
    {
        $reportType = 'sales';
        
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with("ReportGenerator created for type: {$reportType}");
        
        new ReportGenerator($this->mockLogger, $this->mockConfig, $reportType);
    }

    public function testConstructorWithOptions(): void
    {
        $reportType = 'analytics';
        $options = ['period' => 'monthly', 'format' => 'pdf'];
        
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with("ReportGenerator created for type: {$reportType}");
        
        $generator = new ReportGenerator($this->mockLogger, $this->mockConfig, $reportType, $options);
        
        $this->assertInstanceOf(ReportGenerator::class, $generator);
    }

    public function testGenerateReportLogsAndOutputs(): void
    {
        $reportType = 'user-activity';
        
        // Expect constructor logging
        $this->mockLogger->expects($this->exactly(2))
            ->method('info');
        
        $generator = new ReportGenerator($this->mockLogger, $this->mockConfig, $reportType);
        
        // Capture output
        $this->expectOutputString("📊 Generated {$reportType} report\n");
        
        $generator->generateReport();
    }

    public function testGenerateReportWithDifferentTypes(): void
    {
        $reportType = 'financial';
        
        // Constructor call
        $this->mockLogger->expects($this->exactly(2))
            ->method('info');
        
        $generator = new ReportGenerator($this->mockLogger, $this->mockConfig, $reportType);
        
        $this->expectOutputString("📊 Generated {$reportType} report\n");
        
        $generator->generateReport();
    }

    public function testConstructorRequiresDependencies(): void
    {
        // Test that dependency injection is working correctly
        $logger = $this->createMock(LoggerInterface::class);
        $config = $this->createMock(Config::class);
        $reportType = 'test-report';
        
        $generator = new ReportGenerator($logger, $config, $reportType);
        
        $this->assertInstanceOf(ReportGenerator::class, $generator);
    }

    public function testConstructorWithEmptyOptions(): void
    {
        $reportType = 'inventory';
        $options = [];
        
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with("ReportGenerator created for type: {$reportType}");
        
        $generator = new ReportGenerator($this->mockLogger, $this->mockConfig, $reportType, $options);
        
        $this->assertInstanceOf(ReportGenerator::class, $generator);
    }
}