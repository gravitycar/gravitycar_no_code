<?php

namespace Gravitycar\Tests\Unit\Services;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Services\EmailReminderService;
use Gravitycar\Services\EmailSenderService;
use Gravitycar\Core\Config;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\ModelBase;
use Gravitycar\Models\emailqueue\EmailQueue;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for EmailReminderService.
 *
 * Covers processReminders() and processEmailQueue().
 */
class EmailReminderServiceTest extends UnitTestCase
{
    private ModelFactory&MockObject $mockModelFactory;
    private Config&MockObject $mockConfig;
    private EmailSenderService&MockObject $mockEmailSender;
    private EmailReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockEmailSender = $this->createMock(EmailSenderService::class);

        $this->mockConfig->method('get')->willReturnCallback(function ($key, $default = null) {
            if ($key === 'email_queue_batch_size') {
                return 20;
            }
            return $default;
        });

        $this->service = new EmailReminderService(
            $this->logger,
            $this->mockConfig,
            $this->mockModelFactory,
            $this->mockEmailSender
        );
    }

    public function testProcessRemindersReturnsZeroWhenNoDueReminders(): void
    {
        $mockReminderModel = $this->createMock(ModelBase::class);
        $mockReminderModel->method('findRaw')->willReturn([]);

        $this->mockModelFactory->method('new')
            ->willReturnCallback(function ($modelName) use ($mockReminderModel) {
                if ($modelName === 'EventReminders') {
                    return $mockReminderModel;
                }
                return $this->createMock(ModelBase::class);
            });

        $result = $this->service->processReminders();

        $this->assertSame(0, $result['reminders_processed']);
        $this->assertSame(0, $result['emails_queued']);
    }

    public function testProcessEmailQueueSendsEmails(): void
    {
        $mockEmailModel1 = $this->createMock(ModelBase::class);
        $mockEmailModel1->method('get')->willReturnCallback(function ($field) {
            $data = [
                'id' => 'email-1',
                'recipient_email' => 'user@example.com',
                'subject' => 'Reminder: Event',
                'body' => '<h2>Reminder</h2>',
                'related_event_id' => 'evt-1',
                'related_reminder_id' => 'rem-1',
            ];
            return $data[$field] ?? null;
        });

        $mockEmailQueueModel = $this->createMock(EmailQueue::class);
        $mockEmailQueueModel->method('findPendingEmails')->willReturn([$mockEmailModel1]);
        $mockEmailQueueModel->method('markAsSent')->willReturn(true);

        $this->mockModelFactory->method('new')
            ->willReturnCallback(function ($modelName) use ($mockEmailQueueModel) {
                if ($modelName === 'EmailQueue') {
                    return $mockEmailQueueModel;
                }
                return $this->createMock(ModelBase::class);
            });

        $this->mockEmailSender->method('sendEmail')->willReturn(true);

        $result = $this->service->processEmailQueue();

        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, $result['failed']);
    }

    public function testProcessEmailQueueHandlesFailures(): void
    {
        $mockEmailModel1 = $this->createMock(ModelBase::class);
        $mockEmailModel1->method('get')->willReturnCallback(function ($field) {
            $data = [
                'id' => 'email-1',
                'recipient_email' => 'user@example.com',
                'subject' => 'Reminder',
                'body' => '<h2>Reminder</h2>',
                'related_event_id' => null,
                'related_reminder_id' => null,
            ];
            return $data[$field] ?? null;
        });

        $mockEmailQueueModel = $this->createMock(EmailQueue::class);
        $mockEmailQueueModel->method('findPendingEmails')->willReturn([$mockEmailModel1]);

        $this->mockModelFactory->method('new')
            ->willReturnCallback(function ($modelName) use ($mockEmailQueueModel) {
                if ($modelName === 'EmailQueue') {
                    return $mockEmailQueueModel;
                }
                return $this->createMock(ModelBase::class);
            });

        $this->mockEmailSender->method('sendEmail')->willReturn(false);

        $result = $this->service->processEmailQueue();

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['failed']);
    }

    public function testProcessEmailQueueRespectsConfigBatchSize(): void
    {
        $mockConfig = $this->createMock(Config::class);
        $mockConfig->method('get')->willReturnCallback(function ($key, $default = null) {
            if ($key === 'email_queue_batch_size') {
                return 1;
            }
            return $default;
        });

        $service = new EmailReminderService(
            $this->logger,
            $mockConfig,
            $this->mockModelFactory,
            $this->mockEmailSender
        );

        $mockEmail1 = $this->createMock(ModelBase::class);
        $mockEmail1->method('get')->willReturnCallback(fn($f) => match($f) {
            'id' => 'e1', 'recipient_email' => 'a@b.com', 'subject' => 'S',
            'body' => 'B', default => null,
        });

        $mockEmail2 = $this->createMock(ModelBase::class);
        $mockEmail2->method('get')->willReturnCallback(fn($f) => match($f) {
            'id' => 'e2', 'recipient_email' => 'c@d.com', 'subject' => 'S',
            'body' => 'B', default => null,
        });

        $mockQueueModel = $this->createMock(EmailQueue::class);
        $mockQueueModel->method('findPendingEmails')->willReturn([$mockEmail1, $mockEmail2]);
        $mockQueueModel->method('markAsSent')->willReturn(true);

        $this->mockModelFactory->method('new')
            ->willReturnCallback(function ($name) use ($mockQueueModel) {
                if ($name === 'EmailQueue') {
                    return $mockQueueModel;
                }
                return $this->createMock(ModelBase::class);
            });

        $this->mockEmailSender->method('sendEmail')->willReturn(true);

        $result = $service->processEmailQueue();

        // Only 1 email should be sent due to batch size of 1
        $this->assertSame(1, $result['sent']);
    }

    public function testRunReturnsBothReminderAndEmailResults(): void
    {
        $mockReminderModel = $this->createMock(ModelBase::class);
        $mockReminderModel->method('findRaw')->willReturn([]);

        $mockEmailQueueModel = $this->createMock(EmailQueue::class);
        $mockEmailQueueModel->method('findPendingEmails')->willReturn([]);

        $this->mockModelFactory->method('new')
            ->willReturnCallback(function ($name) use ($mockReminderModel, $mockEmailQueueModel) {
                if ($name === 'EventReminders') {
                    return $mockReminderModel;
                }
                if ($name === 'EmailQueue') {
                    return $mockEmailQueueModel;
                }
                return $this->createMock(ModelBase::class);
            });

        $result = $this->service->run();

        $this->assertArrayHasKey('reminders', $result);
        $this->assertArrayHasKey('emails', $result);
    }
}
