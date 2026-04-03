<?php

declare(strict_types=1);

namespace Gravitycar\Services;

use Gravitycar\Core\Config;
use Gravitycar\Factories\ModelFactory;
use Monolog\Logger;

/**
 * Two-phase cron job: queues reminder emails for invitees, then sends them.
 */
class EmailReminderService
{
    private const REMINDER_STATUS_PENDING = 'pending';
    private const REMINDER_STATUS_SENT = 'sent';
    private const EMAIL_STATUS_PENDING = 'pending';

    private Logger $logger;
    private Config $config;
    private ModelFactory $modelFactory;
    private EmailSenderService $emailSenderService;

    public function __construct(
        Logger $logger,
        Config $config,
        ModelFactory $modelFactory,
        EmailSenderService $emailSenderService
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->modelFactory = $modelFactory;
        $this->emailSenderService = $emailSenderService;
    }

    /**
     * Run both phases: process reminders, then send queued emails.
     */
    public function run(): array
    {
        $this->logger->info('Email reminder cron job started');

        $reminderResults = $this->processReminders();
        $emailResults = $this->processEmailQueue();

        $this->logger->info('Email reminder cron job completed');

        return [
            'reminders' => $reminderResults,
            'emails' => $emailResults,
        ];
    }

    /** Find due reminders and queue emails for their invitees. */
    public function processReminders(): array
    {
        $reminders = $this->findDueReminders();
        $processed = 0;
        $emailsQueued = 0;

        foreach ($reminders as $reminderData) {
            $queued = $this->queueEmailsForReminder($reminderData);
            $this->markReminderAsSent($reminderData['id']);
            $emailsQueued += $queued;
            $processed++;
        }

        $this->logger->info('Processed reminders', [
            'reminders_processed' => $processed,
            'emails_queued' => $emailsQueued,
        ]);

        return [
            'reminders_processed' => $processed,
            'emails_queued' => $emailsQueued,
        ];
    }

    /** Send pending emails from the queue, respecting configured batch size. */
    public function processEmailQueue(): array
    {
        $emailQueueModel = $this->modelFactory->new('EmailQueue');
        $pendingEmails = $emailQueueModel->findPendingEmails();
        $batchSize = (int) $this->config->get('email_queue_batch_size', 20);

        $sent = 0;
        $failed = 0;

        $pendingEmails = array_slice($pendingEmails, 0, $batchSize);

        foreach ($pendingEmails as $emailModel) {
            $emailData = $this->extractEmailData($emailModel);
            $success = $this->emailSenderService->sendEmail($emailData);

            if ($success) {
                $emailQueueModel->markAsSent($emailData['id']);
                $sent++;
            } else {
                $failed++;
            }
        }

        $this->logger->info('Processed email queue', [
            'sent' => $sent,
            'failed' => $failed,
        ]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    /** Query pending reminders whose remind_at has passed. */
    private function findDueReminders(): array
    {
        $reminderModel = $this->modelFactory->new('EventReminders');
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $allPending = $reminderModel->findRaw([
            'status' => self::REMINDER_STATUS_PENDING,
            'remind_at' => '__NOT_NULL__',
        ]);

        return array_filter($allPending, function (array $row) use ($now) {
            return $row['remind_at'] <= $now;
        });
    }

    /** Queue emails for all invitees of a reminder's event. */
    private function queueEmailsForReminder(array $reminderData): int
    {
        $eventId = $reminderData['event_id'];
        $eventsModel = $this->modelFactory->new('Events');
        $eventRecord = $eventsModel->findById($eventId);

        if (!$eventRecord) {
            $this->logger->warning('Event not found for reminder', [
                'reminder_id' => $reminderData['id'],
                'event_id' => $eventId,
            ]);
            return 0;
        }

        $invitees = $this->getEventInvitees($eventId);
        if (empty($invitees)) {
            $this->logger->info('No invitees for event', ['event_id' => $eventId]);
            return 0;
        }

        $eventData = $this->extractEventData($eventRecord);
        $emailBody = $this->buildReminderEmailBody($eventData);
        $subject = 'Reminder: ' . ($eventData['name'] ?? 'Event');
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $queued = 0;

        foreach ($invitees as $invitee) {
            $emailQueueModel = $this->modelFactory->new('EmailQueue');
            $emailQueueModel->set('recipient_email', $invitee['email']);
            $emailQueueModel->set('recipient_user_id', $invitee['id']);
            $emailQueueModel->set('subject', $subject);
            $emailQueueModel->set('body', $emailBody);
            $emailQueueModel->set('status', self::EMAIL_STATUS_PENDING);
            $emailQueueModel->set('send_at', $now);
            $emailQueueModel->set('related_event_id', $eventId);
            $emailQueueModel->set('related_reminder_id', $reminderData['id']);

            if ($emailQueueModel->create()) {
                $queued++;
            }
        }

        return $queued;
    }

    /** Fetch all invited users for an event via the relationship API. */
    private function getEventInvitees(string $eventId): array
    {
        $eventsModel = $this->modelFactory->new('Events');
        $eventsModel->findById($eventId);

        $relatedUsers = $eventsModel->getRelatedModels('events_users_invitations');

        $invitees = [];
        foreach ($relatedUsers as $userModel) {
            $invitees[] = [
                'id' => $userModel->get('id'),
                'email' => $userModel->get('email'),
            ];
        }

        return $invitees;
    }

    /** Build an HTML email body from event data. */
    private function buildReminderEmailBody(array $eventData): string
    {
        $name = htmlspecialchars($eventData['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $location = htmlspecialchars($eventData['location'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(
            $eventData['description'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        );
        $acceptedDate = $eventData['accepted_date'] ?? 'TBD';

        return <<<HTML
        <h2>Event Reminder: {$name}</h2>
        <p><strong>Date:</strong> {$acceptedDate}</p>
        <p><strong>Location:</strong> {$location}</p>
        <p>{$description}</p>
        <p>An ICS calendar file is attached for your convenience.</p>
        HTML;
    }

    /** Update a reminder record to status=sent with a sent_at timestamp. */
    private function markReminderAsSent(string $reminderId): void
    {
        $reminderModel = $this->modelFactory->new('EventReminders');
        $result = $reminderModel->findById($reminderId);
        if (!$result) {
            return;
        }

        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');
        $reminderModel->set('status', self::REMINDER_STATUS_SENT);
        $reminderModel->set('sent_at', $now);
        $reminderModel->update();
    }

    /** Extract raw data from an email queue model instance. */
    private function extractEmailData(object $emailModel): array
    {
        return [
            'id' => $emailModel->get('id'),
            'recipient_email' => $emailModel->get('recipient_email'),
            'subject' => $emailModel->get('subject'),
            'body' => $emailModel->get('body'),
            'related_event_id' => $emailModel->get('related_event_id'),
            'related_reminder_id' => $emailModel->get('related_reminder_id'),
        ];
    }

    /** Extract raw data from an event model instance. */
    private function extractEventData(object $eventModel): array
    {
        return [
            'id' => $eventModel->get('id'),
            'name' => $eventModel->get('name'),
            'description' => $eventModel->get('description'),
            'location' => $eventModel->get('location'),
            'accepted_date' => $eventModel->get('accepted_date'),
            'duration_hours' => $eventModel->get('duration_hours'),
        ];
    }
}
