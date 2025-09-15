<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\NotificationService;
use Gravitycar\Services\EmailService;
use Psr\Log\LoggerInterface;

class NotificationServiceTest extends TestCase
{
    private NotificationService $notificationService;
    private LoggerInterface|MockObject $mockLogger;
    private EmailService|MockObject $mockEmailService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockEmailService = $this->createMock(EmailService::class);
        
        // Create service with injected dependencies
        $this->notificationService = new NotificationService($this->mockLogger, $this->mockEmailService);
    }

    public function testConstructorLogsInitialization(): void
    {
        // Expect logger to be called during constructor
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockEmailService = $this->createMock(EmailService::class);
        
        $mockLogger->expects($this->once())
            ->method('info')
            ->with('NotificationService created with auto-wired EmailService');
        
        new NotificationService($mockLogger, $mockEmailService);
    }

    public function testSendWelcomeNotificationDelegatesToEmailService(): void
    {
        $email = 'user@example.com';
        
        // Expect email service to be called with correct parameters
        $this->mockEmailService->expects($this->once())
            ->method('sendEmail')
            ->with($email, 'Welcome to the platform!');
        
        // Capture output
        $this->expectOutputString("🔔 Welcome notification sent\n");
        
        $this->notificationService->sendWelcomeNotification($email);
    }

    public function testSendWelcomeNotificationWithDifferentEmail(): void
    {
        $email = 'admin@company.com';
        
        $this->mockEmailService->expects($this->once())
            ->method('sendEmail')
            ->with($email, 'Welcome to the platform!');
        
        $this->expectOutputString("🔔 Welcome notification sent\n");
        
        $this->notificationService->sendWelcomeNotification($email);
    }

    public function testConstructorRequiresDependencies(): void
    {
        // This test ensures dependency injection is working correctly
        $logger = $this->createMock(LoggerInterface::class);
        $emailService = $this->createMock(EmailService::class);
        
        $notificationService = new NotificationService($logger, $emailService);
        
        $this->assertInstanceOf(NotificationService::class, $notificationService);
    }

    public function testEmailServiceIntegration(): void
    {
        // Test that NotificationService properly uses EmailService
        $email = 'test@integration.com';
        
        // Set up expectations
        $this->mockEmailService->expects($this->once())
            ->method('sendEmail')
            ->with($this->equalTo($email), $this->equalTo('Welcome to the platform!'));
        
        // Capture output
        $this->expectOutputString("🔔 Welcome notification sent\n");
        
        // Execute
        $this->notificationService->sendWelcomeNotification($email);
    }
}