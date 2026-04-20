<?php
namespace Gravitycar\Models\eventreminders;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * EventReminders model for the Gravitycar event organizer feature.
 *
 * Stores scheduled email reminders for events. Supports preset reminder
 * types (2 weeks, 1 week, 1 day before accepted_date) with auto-calculated
 * remind_at values, as well as custom date/time reminders. Provides
 * recalculation logic when an event's accepted_date changes.
 */
class EventReminders extends ModelBase
{
    /**
     * Mapping of preset reminder types to their day offsets before accepted_date.
     */
    private const REMINDER_TYPE_OFFSETS = [
        '2_weeks' => 14,
        '1_week'  => 7,
        '1_day'   => 1,
    ];

    /**
     * List of preset (non-custom) reminder types.
     */
    private const PRESET_REMINDER_TYPES = ['2_weeks', '1_week', '1_day'];

    /**
     * Pure dependency injection constructor.
     *
     * @param Logger $logger
     * @param MetadataEngineInterface $metadataEngine
     * @param FieldFactory $fieldFactory
     * @param DatabaseConnectorInterface $databaseConnector
     * @param RelationshipFactory $relationshipFactory
     * @param ModelFactory $modelFactory
     * @param CurrentUserProviderInterface $currentUserProvider
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
     * Create a new reminder record.
     *
     * For preset reminder types, auto-calculates remind_at from the
     * parent event's accepted_date. Custom reminders keep their
     * explicitly set remind_at value.
     *
     * @return bool True on success.
     */
    public function create(): bool
    {
        $reminderType = $this->get('reminder_type');

        if (in_array($reminderType, self::PRESET_REMINDER_TYPES, true)) {
            $acceptedDate = $this->fetchEventAcceptedDate();
            $remindAt = $this->calculateRemindAt($reminderType, $acceptedDate);
            $this->set('remind_at', $remindAt);
        }

        return parent::create();
    }

    /**
     * Calculate the remind_at datetime for a preset reminder type.
     *
     * Returns null if acceptedDate is null or reminderType is not a
     * recognized preset type.
     *
     * @param string $reminderType The preset reminder type key.
     * @param string|null $acceptedDate The event's accepted_date in UTC.
     * @return string|null The calculated remind_at datetime string, or null.
     */
    public function calculateRemindAt(string $reminderType, ?string $acceptedDate): ?string
    {
        if ($acceptedDate === null) {
            return null;
        }

        if (!isset(self::REMINDER_TYPE_OFFSETS[$reminderType])) {
            $this->logger->warning('Unknown preset reminder type', ['type' => $reminderType]);
            return null;
        }

        $offsetDays = self::REMINDER_TYPE_OFFSETS[$reminderType];
        $date = new \DateTimeImmutable($acceptedDate, new \DateTimeZone('UTC'));
        $remindDate = $date->modify("-{$offsetDays} days");

        return $remindDate->format('Y-m-d H:i:s');
    }

    /**
     * Recalculate remind_at for all preset, unsent reminders of an event.
     *
     * Called by the Accepted Date API endpoint (catalog item 11) when an
     * event's accepted_date changes. Custom reminders and already-sent
     * reminders are left unchanged.
     *
     * @param string $eventId The event's UUID.
     * @param string|null $newAcceptedDate The new accepted_date value.
     * @return int The number of reminders that were recalculated.
     */
    public function recalculateRemindersForEvent(string $eventId, ?string $newAcceptedDate): int
    {
        $allReminders = $this->listByEventId($eventId);
        $updatedCount = 0;

        foreach ($allReminders as $reminderData) {
            if (!$this->shouldRecalculate($reminderData)) {
                continue;
            }

            $newRemindAt = $this->calculateRemindAt(
                $reminderData['reminder_type'],
                $newAcceptedDate
            );

            $this->loadRecord($reminderData['id']);
            $this->set('remind_at', $newRemindAt);
            $this->update();
            $updatedCount++;
        }

        $this->logger->info('Recalculated reminders for event', [
            'event_id' => $eventId,
            'updated_count' => $updatedCount,
            'new_accepted_date' => $newAcceptedDate,
        ]);

        return $updatedCount;
    }

    /**
     * List all reminders for a given event.
     *
     * @param string $eventId The event's UUID.
     * @return array List of reminder records.
     */
    public function listByEventId(string $eventId): array
    {
        return $this->findRaw(['event_id' => $eventId]);
    }

    /**
     * Fetch the accepted_date from the parent event.
     *
     * @return string|null The event's accepted_date, or null if not set or event not found.
     */
    private function fetchEventAcceptedDate(): ?string
    {
        $eventId = $this->get('event_id');
        if (empty($eventId)) {
            return null;
        }

        $eventsModel = $this->modelFactory->new('Events');
        $result = $eventsModel->findById($eventId);

        if ($result === null) {
            $this->logger->warning('Event not found for reminder', ['event_id' => $eventId]);
            return null;
        }

        return $result->get('accepted_date');
    }

    /**
     * Determine if a reminder should be recalculated.
     *
     * A reminder should be recalculated if it is a preset type and has
     * not already been sent.
     *
     * @param array $reminderData The reminder record data.
     * @return bool True if the reminder should be recalculated.
     */
    private function shouldRecalculate(array $reminderData): bool
    {
        if ($reminderData['status'] === 'sent') {
            return false;
        }

        if (!in_array($reminderData['reminder_type'], self::PRESET_REMINDER_TYPES, true)) {
            return false;
        }

        return true;
    }

    /**
     * Load a reminder record into this model instance by ID.
     *
     * @param string $id The reminder record's UUID.
     * @return void
     * @throws GCException If the reminder record is not found.
     */
    private function loadRecord(string $id): void
    {
        $result = $this->findById($id);
        if ($result === null) {
            throw new GCException("Reminder not found: {$id}");
        }
    }
}
