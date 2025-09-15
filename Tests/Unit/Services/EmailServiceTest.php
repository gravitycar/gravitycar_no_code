<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\EmailService;
use Psr\Log\LoggerInterface;
use Gravitycar\Core\Config;

class EmailServiceTest extends TestCase
{
    private EmailService $emailService;
    private LoggerInterface|MockObject $mockLogger;
    private Config|MockObject $mockConfig;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockConfig = $this->createMock(Config::class);
        
        // Create service with injected dependencies
        $this->emailService = new EmailService($this->mockLogger, $this->mockConfig);
    }

    public function testConstructorLogsInitialization(): void
    {
        // Expect logger to be called during constructor
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockConfig = $this->createMock(Config::class);
        
        $mockLogger->expects($this->once())
            ->method('info')
            ->with('EmailService created with auto-wired dependencies');
        
        new EmailService($mockLogger, $mockConfig);
    }

    public function testSendEmailLogsOperation(): void
    {
        // Expect logger to be called when sending email
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Sending email to: test@example.com, subject: Test Subject');
        
        // Capture output
        $this->expectOutputString("📧 Email sent to test@example.com with subject: Test Subject\n");
        
        $this->emailService->sendEmail('test@example.com', 'Test Subject');
    }

    public function testSendEmailWithDifferentParameters(): void
    {
        $to = 'user@domain.com';
        $subject = 'Welcome Message';
        
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with("Sending email to: {$to}, subject: {$subject}");
        
        $this->expectOutputString("📧 Email sent to {$to} with subject: {$subject}\n");
        
        $this->emailService->sendEmail($to, $subject);
    }

    public function testConstructorRequiresDependencies(): void
    {
        // This test ensures dependency injection is working correctly
        $logger = $this->createMock(LoggerInterface::class);
        $config = $this->createMock(Config::class);
        
        $emailService = new EmailService($logger, $config);
        
        $this->assertInstanceOf(EmailService::class, $emailService);
    }
}