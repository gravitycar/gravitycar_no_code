<?php

declare(strict_types=1);

namespace Gravitycar\Services;

use Gravitycar\Core\Config;
use Gravitycar\Factories\ModelFactory;
use Monolog\Logger;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends individual emails via PHPMailer with ICS attachment support.
 *
 * Handles SMTP configuration, email composition, and failure reporting.
 * Delegates retry logic to the EmailQueue model.
 */
class EmailSenderService
{
    private Logger $logger;
    private Config $config;
    private ModelFactory $modelFactory;
    private IcsGeneratorService $icsGeneratorService;

    /**
     * @param Logger $logger Monolog logger instance.
     * @param Config $config Application configuration.
     * @param ModelFactory $modelFactory Factory for creating model instances.
     * @param IcsGeneratorService $icsGeneratorService ICS calendar generator.
     */
    public function __construct(
        Logger $logger,
        Config $config,
        ModelFactory $modelFactory,
        IcsGeneratorService $icsGeneratorService
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->modelFactory = $modelFactory;
        $this->icsGeneratorService = $icsGeneratorService;
    }

    /**
     * Send a single email via PHPMailer.
     *
     * On failure, delegates retry logic to EmailQueue::markAsFailedOrRetry().
     *
     * @param array $emailData The email queue record data.
     * @return bool True if sent successfully.
     */
    public function sendEmail(array $emailData): bool
    {
        $mailer = $this->createMailer();

        try {
            $mailer->addAddress($emailData['recipient_email']);
            $mailer->Subject = $emailData['subject'];
            $mailer->isHTML(true);
            $mailer->Body = $emailData['body'];
            $mailer->AltBody = strip_tags($emailData['body']);

            $icsContent = $this->getIcsForEmail($emailData);
            if ($icsContent !== null) {
                $mailer->addStringAttachment(
                    $icsContent,
                    'event.ics',
                    'base64',
                    'text/calendar'
                );
            }

            $mailer->send();

            $this->logger->info('Email sent successfully', [
                'email_id' => $emailData['id'],
                'recipient' => $emailData['recipient_email'],
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Email send failed', [
                'email_id' => $emailData['id'],
                'recipient' => $emailData['recipient_email'],
                'error' => $e->getMessage(),
            ]);

            $emailQueueModel = $this->modelFactory->new('EmailQueue');
            $emailQueueModel->markAsFailedOrRetry(
                $emailData['id'],
                $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Load the related event and generate ICS content for an email.
     *
     * @param array $emailData The email queue record data.
     * @return string|null ICS content or null.
     */
    private function getIcsForEmail(array $emailData): ?string
    {
        $eventId = $emailData['related_event_id'] ?? null;
        if (empty($eventId)) {
            return null;
        }

        $eventsModel = $this->modelFactory->new('Events');
        $eventRecord = $eventsModel->findById($eventId);
        if (!$eventRecord) {
            return null;
        }

        $eventData = [
            'id' => $eventRecord->get('id'),
            'name' => $eventRecord->get('name'),
            'description' => $eventRecord->get('description'),
            'location' => $eventRecord->get('location'),
            'accepted_date' => $eventRecord->get('accepted_date'),
            'duration_hours' => $eventRecord->get('duration_hours'),
        ];

        return $this->generateIcsAttachment($eventData);
    }

    /**
     * Generate ICS content for an event (best-effort).
     *
     * Returns null if the event has no accepted_date or generation fails.
     *
     * @param array $eventData Event details.
     * @return string|null ICS content or null.
     */
    private function generateIcsAttachment(array $eventData): ?string
    {
        if (empty($eventData['accepted_date'])) {
            return null;
        }

        try {
            return $this->icsGeneratorService->generateIcsContent($eventData);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate ICS attachment', [
                'event_id' => $eventData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create and configure a PHPMailer instance from config.
     *
     * Protected to allow test subclasses to override with a mock.
     *
     * @return PHPMailer Configured mailer instance.
     */
    protected function createMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $this->config->get('smtp_host');
        $mailer->Port = (int) $this->config->get('smtp_port', 587);
        $mailer->SMTPAuth = true;
        $mailer->Username = $this->config->get('smtp_username');
        $mailer->Password = $this->config->get('smtp_password');
        $mailer->SMTPSecure = $this->config->get('smtp_encryption', 'tls');
        $mailer->setFrom(
            $this->config->get('email_from_address', 'noreply@gravitycar.com'),
            $this->config->get('email_from_name', 'Gravitycar Events')
        );
        $mailer->CharSet = 'UTF-8';

        return $mailer;
    }
}
