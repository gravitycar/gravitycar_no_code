<?php
namespace Gravitycar\Models\emailqueue;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * EmailQueue model for the Gravitycar framework.
 *
 * Provides reliable email delivery infrastructure for the events feature.
 * All outbound emails (reminders, notifications) are queued here with
 * status tracking, retry support, and references to related events/reminders.
 * Only admins can access this model.
 */
class EmailQueue extends ModelBase
{
    /** @var int Maximum number of retry attempts before marking as failed. */
    public const MAX_RETRY_COUNT = 3;

    /** @var string Status value for emails waiting to be sent. */
    public const STATUS_PENDING = 'pending';

    /** @var string Status value for successfully sent emails. */
    public const STATUS_SENT = 'sent';

    /** @var string Status value for emails that exhausted all retries. */
    public const STATUS_FAILED = 'failed';

    /** @var string Status value for emails that were cancelled. */
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Retry backoff intervals in seconds, keyed by retry attempt number.
     *
     * @var array<int, int>
     */
    private const RETRY_BACKOFF_SECONDS = [
        1 => 300,    // 1st retry: 5 minutes
        2 => 1800,   // 2nd retry: 30 minutes
        3 => 7200,   // 3rd retry: 2 hours
    ];

    /**
     * Pure dependency injection constructor.
     */
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }

    /**
     * Find all pending emails that are ready to be sent.
     *
     * Returns EmailQueue records where status is 'pending' and
     * send_at is at or before the current UTC time. Used by the
     * email reminder cron job to find emails ready for delivery.
     *
     * @return array Array of EmailQueue model instances.
     */
    public function findPendingEmails(): array
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $validatedParams = [
            'filters' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => self::STATUS_PENDING],
                ['field' => 'send_at', 'operator' => 'lessThanOrEqual', 'value' => $now],
            ],
        ];

        $rows = $this->databaseConnector->findWithReactParams($this, $validatedParams);
        return $this->fromRows($rows);
    }

    /**
     * Mark an email as successfully sent.
     *
     * Sets the status to 'sent' and records the current UTC timestamp
     * in the sent_at field.
     *
     * @param string $emailId The UUID of the email queue record.
     * @return bool True if the update was saved, false if the record was not found.
     */
    public function markAsSent(string $emailId): bool
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $record = $this->findById($emailId);
        if (!$record) {
            $this->logger->warning('Cannot mark email as sent: record not found', [
                'email_id' => $emailId,
            ]);
            return false;
        }

        $record->set('status', self::STATUS_SENT);
        $record->set('sent_at', $now);

        return (bool) $record->update();
    }

    /**
     * Handle a failed send attempt with retry logic.
     *
     * Increments the retry count and stores the error message. If the
     * retry count has reached MAX_RETRY_COUNT, the status is set to
     * 'failed'. Otherwise the status remains 'pending' and send_at is
     * updated to a future time based on exponential backoff.
     *
     * @param string $emailId     The UUID of the email queue record.
     * @param string $errorMessage Description of the failure.
     * @return bool True if the update was saved, false if the record was not found.
     */
    public function markAsFailedOrRetry(string $emailId, string $errorMessage): bool
    {
        $record = $this->findById($emailId);
        if (!$record) {
            $this->logger->warning('Cannot mark email as failed/retry: record not found', [
                'email_id' => $emailId,
            ]);
            return false;
        }

        $currentRetryCount = (int) $record->get('retry_count');
        $newRetryCount = $currentRetryCount + 1;
        $record->set('retry_count', $newRetryCount);
        $record->set('error_message', $errorMessage);

        if ($newRetryCount >= self::MAX_RETRY_COUNT) {
            $record->set('status', self::STATUS_FAILED);
            $this->logger->info('Email marked as failed after max retries', [
                'email_id' => $emailId,
                'retry_count' => $newRetryCount,
                'error_message' => $errorMessage,
            ]);
            return (bool) $record->update();
        }

        $backoffSeconds = self::RETRY_BACKOFF_SECONDS[$newRetryCount] ?? 7200;
        $nextRetry = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("+{$backoffSeconds} seconds")
            ->format('Y-m-d H:i:s');
        $record->set('send_at', $nextRetry);

        $this->logger->info('Email scheduled for retry', [
            'email_id' => $emailId,
            'retry_count' => $newRetryCount,
            'next_retry_at' => $nextRetry,
            'backoff_seconds' => $backoffSeconds,
            'error_message' => $errorMessage,
        ]);

        return (bool) $record->update();
    }

    /**
     * Cancel all pending emails for a given event.
     *
     * Sets the status to 'cancelled' for every EmailQueue record that
     * references the specified event and currently has status 'pending'.
     *
     * @param string $eventId The UUID of the related event.
     * @return int The number of records that were cancelled.
     */
    public function cancelByEventId(string $eventId): int
    {
        $conditions = [
            'related_event_id' => ['operator' => 'equals', 'value' => $eventId],
            'status' => ['operator' => 'equals', 'value' => self::STATUS_PENDING],
        ];
        $pendingEmails = $this->find($conditions);

        $count = 0;
        foreach ($pendingEmails as $email) {
            $email->set('status', self::STATUS_CANCELLED);
            if ($email->save()) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->logger->info('Cancelled pending emails for event', [
                'event_id' => $eventId,
                'cancelled_count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Get the backoff interval in seconds for a given retry attempt.
     *
     * Public for testability. Falls back to 7200 seconds (2 hours)
     * for retry counts beyond the defined backoff schedule.
     *
     * @param int $retryCount The retry attempt number (1-based).
     * @return int Backoff interval in seconds.
     */
    public function getRetryBackoffSeconds(int $retryCount): int
    {
        return self::RETRY_BACKOFF_SECONDS[$retryCount] ?? 7200;
    }
}
