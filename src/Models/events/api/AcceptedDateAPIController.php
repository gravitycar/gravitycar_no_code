<?php

namespace Gravitycar\Models\events\api;

use Gravitycar\Api\Request;
use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\ForbiddenException;
use Gravitycar\Exceptions\UnauthorizedException;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Monolog\Logger;

/**
 * Custom API controller for setting an event's accepted date.
 *
 * Provides a PUT endpoint that allows an admin to set the event's
 * accepted_date to a proposed date's datetime value. Triggers
 * recalculation of preset reminders when accepted_date changes.
 */
class AcceptedDateAPIController extends ApiControllerBase
{
    use EventAccessTrait;

    /**
     * Only admins can set the accepted date.
     */
    protected array $rolesAndActions = [
        'admin' => ['*'],
        'user' => [],
        'guest' => [],
    ];

    /**
     * @param Logger|null $logger
     * @param ModelFactory|null $modelFactory
     * @param DatabaseConnectorInterface|null $databaseConnector
     * @param MetadataEngineInterface|null $metadataEngine
     * @param Config|null $config
     * @param CurrentUserProviderInterface|null $currentUserProvider
     */
    public function __construct(
        ?Logger $logger = null,
        ?ModelFactory $modelFactory = null,
        ?DatabaseConnectorInterface $databaseConnector = null,
        ?MetadataEngineInterface $metadataEngine = null,
        ?Config $config = null,
        ?CurrentUserProviderInterface $currentUserProvider = null
    ) {
        parent::__construct(
            $logger,
            $modelFactory,
            $databaseConnector,
            $metadataEngine,
            $config,
            $currentUserProvider
        );
    }

    /**
     * Register the accepted-date route.
     *
     * @return array Route definitions for APIRouteRegistry
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'PUT',
                'path' => '/Events/{event_id}/accepted-date',
                'apiClass' => self::class,
                'apiMethod' => 'setAcceptedDate',
                'parameterNames' => ['event_id'],
                'rbacAction' => 'update',
            ],
            [
                'method' => 'DELETE',
                'path' => '/Events/{event_id}/accepted-date',
                'apiClass' => self::class,
                'apiMethod' => 'revokeAcceptedDate',
                'parameterNames' => ['event_id'],
                'rbacAction' => 'update',
            ],
        ];
    }

    /**
     * Set the accepted date for an event from a proposed date.
     *
     * Resolves the proposed_date_id to its datetime value, updates the
     * event's accepted_date field, and triggers reminder recalculation.
     *
     * @param Request $request The incoming API request
     * @return array Response payload with accepted_date and recalculation count
     * @throws BadRequestException If event_id or proposed_date_id is missing/invalid
     * @throws NotFoundException If event does not exist
     * @throws UnauthorizedException If user is not authenticated
     * @throws ForbiddenException If user is not an admin
     */
    public function setAcceptedDate(Request $request): array
    {
        $eventId = $request->get('event_id');
        if (empty($eventId)) {
            throw new BadRequestException('Event ID is required');
        }

        $this->requireAdmin();

        $this->validateEventExists($eventId);

        $proposedDateId = $this->extractProposedDateId($request);

        $acceptedDate = $this->resolveProposedDate($eventId, $proposedDateId);

        $this->updateEventAcceptedDate($eventId, $acceptedDate);

        $remindersUpdated = $this->safeRecalculateReminders($eventId, $acceptedDate);
        $remindersCreated = $this->createAutoReminders($eventId, $acceptedDate);

        $this->logger->info('Accepted date set for event', [
            'event_id' => $eventId,
            'proposed_date_id' => $proposedDateId,
            'accepted_date' => $acceptedDate,
            'reminders_recalculated' => $remindersUpdated,
            'reminders_created' => $remindersCreated,
        ]);

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'event_id' => $eventId,
                'accepted_date' => $acceptedDate,
                'reminders_recalculated' => $remindersUpdated,
                'reminders_created' => $remindersCreated,
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * Revoke the accepted date for an event and soft-delete its reminders.
     *
     * Clears the event's accepted_date field and soft-deletes all
     * EventReminders linked to the event.
     *
     * @param Request $request The incoming API request
     * @return array Response payload with revocation details
     * @throws BadRequestException If event_id is missing
     * @throws NotFoundException If the event does not exist
     * @throws UnauthorizedException If user is not authenticated
     * @throws ForbiddenException If user is not an admin
     */
    public function revokeAcceptedDate(Request $request): array
    {
        $eventId = $request->get('event_id');
        if (empty($eventId)) {
            throw new BadRequestException('Event ID is required');
        }

        $this->requireAdmin();
        $this->validateEventExists($eventId);

        $this->updateEventAcceptedDate($eventId, null);

        $remindersDeleted = $this->softDeleteRemindersForEvent($eventId);

        $this->logger->info('Accepted date revoked for event', [
            'event_id' => $eventId,
            'reminders_deleted' => $remindersDeleted,
        ]);

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'event_id' => $eventId,
                'reminders_deleted' => $remindersDeleted,
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * Soft-delete all EventReminders for a given event.
     *
     * @param string $eventId The event ID
     * @return int Number of reminders soft-deleted
     */
    protected function softDeleteRemindersForEvent(string $eventId): int
    {
        $remindersModel = $this->modelFactory->new('EventReminders');
        $reminders = $remindersModel->findRaw(
            ['event_id' => $eventId],
            ['id']
        );

        $deletedCount = 0;
        foreach ($reminders as $row) {
            $reminder = $this->modelFactory->retrieve('EventReminders', $row['id']);
            if ($reminder !== null) {
                $reminder->delete();
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Automatically create reminders when an accepted date is set.
     *
     * Always creates a 1-day-before reminder. If the accepted date is
     * more than 7 days in the future, also creates a 1-week-before reminder.
     *
     * @param string $eventId The event ID
     * @param string $acceptedDate The accepted date in UTC (Y-m-d H:i:s)
     * @return int Number of reminders created
     */
    protected function createAutoReminders(string $eventId, string $acceptedDate): int
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $eventDate = new \DateTimeImmutable($acceptedDate, new \DateTimeZone('UTC'));
        $daysUntilEvent = (int) $now->diff($eventDate)->days;
        // diff->days is always positive; check if event is in the future
        if ($eventDate <= $now) {
            $daysUntilEvent = 0;
        }

        $typesToCreate = ['1_day'];
        if ($daysUntilEvent > 7) {
            $typesToCreate[] = '1_week';
        }

        $created = 0;
        foreach ($typesToCreate as $type) {
            try {
                $reminder = $this->modelFactory->new('EventReminders');
                $reminder->set('event_id', $eventId);
                $reminder->set('reminder_type', $type);
                $reminder->set('status', 'pending');
                $reminder->create();
                $created++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to create auto-reminder', [
                    'event_id' => $eventId,
                    'reminder_type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /**
     * Verify the current user is an authenticated admin.
     *
     * @return string The authenticated admin's user ID
     * @throws UnauthorizedException If user is not authenticated
     * @throws ForbiddenException If user is not an admin
     */
    protected function requireAdmin(): string
    {
        $currentUser = $this->getCurrentUser();
        if ($currentUser === null) {
            throw new UnauthorizedException(
                'Authentication required to set accepted date'
            );
        }

        $currentUserId = $currentUser->get('id');
        if (!$this->isUserAdmin($currentUserId)) {
            throw new ForbiddenException(
                'Only admins can set the accepted date',
                ['user_id' => $currentUserId]
            );
        }

        return $currentUserId;
    }

    /**
     * Validate that the specified event exists.
     *
     * @param string $eventId The event ID to validate
     * @throws NotFoundException If the event does not exist
     */
    protected function validateEventExists(string $eventId): void
    {
        $eventsModel = $this->modelFactory->retrieve('Events', $eventId);
        if ($eventsModel === null) {
            throw new NotFoundException(
                'Event not found',
                ['event_id' => $eventId]
            );
        }
    }

    /**
     * Extract and validate the proposed_date_id from the request body.
     *
     * @param Request $request The incoming API request
     * @return string The validated proposed date ID
     * @throws BadRequestException If proposed_date_id is missing or invalid
     */
    protected function extractProposedDateId(Request $request): string
    {
        $requestData = $request->getRequestData();
        $proposedDateId = $requestData['proposed_date_id'] ?? null;

        if (empty($proposedDateId) || !is_string($proposedDateId)) {
            throw new BadRequestException(
                'Request must include a valid "proposed_date_id" string'
            );
        }

        return $proposedDateId;
    }

    /**
     * Resolve a proposed date ID to its datetime value for a given event.
     *
     * @param string $eventId The event ID the proposed date must belong to
     * @param string $proposedDateId The proposed date record ID
     * @return string The proposed_date datetime value
     * @throws BadRequestException If proposed date not found or wrong event
     */
    protected function resolveProposedDate(
        string $eventId,
        string $proposedDateId
    ): string {
        $proposedDatesModel = $this->modelFactory->new('EventProposedDates');
        $results = $proposedDatesModel->findRaw(
            ['id' => $proposedDateId, 'event_id' => $eventId],
            ['proposed_date']
        );

        if (empty($results)) {
            throw new BadRequestException(
                'Proposed date not found or does not belong to this event',
                ['proposed_date_id' => $proposedDateId, 'event_id' => $eventId]
            );
        }

        return $results[0]['proposed_date'];
    }

    /**
     * Update the event's accepted_date field.
     *
     * @param string $eventId The event ID to update
     * @param string|null $acceptedDate The datetime value to set, or null to clear
     */
    protected function updateEventAcceptedDate(
        string $eventId,
        ?string $acceptedDate
    ): void {
        $eventsModel = $this->modelFactory->retrieve('Events', $eventId);
        $eventsModel->set('accepted_date', $acceptedDate);
        $eventsModel->update();
    }

    /**
     * Recalculate reminders for the event, treating failures as non-fatal.
     *
     * Logs errors but does not fail the request if recalculation throws.
     * Returns -1 on failure to signal the issue to the caller.
     *
     * @param string $eventId The event ID
     * @param string $newAcceptedDate The new accepted_date value
     * @return int Number of reminders recalculated, or -1 on failure
     */
    protected function safeRecalculateReminders(
        string $eventId,
        string $newAcceptedDate
    ): int {
        try {
            $remindersModel = $this->modelFactory->new('EventReminders');
            return $remindersModel->recalculateRemindersForEvent(
                $eventId,
                $newAcceptedDate
            );
        } catch (GCException $e) {
            $this->logger->error('Reminder recalculation failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return -1;
        }
    }
}
