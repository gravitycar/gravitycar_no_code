<?php

declare(strict_types=1);

namespace Gravitycar\Models\events\api;

use Gravitycar\Api\Request;
use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\ForbiddenException;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Gravitycar\Models\ModelBase;
use Gravitycar\Services\IcsGeneratorService;
use Monolog\Logger;

/**
 * API controller for the ICS calendar export endpoint.
 *
 * Returns an RFC 5545-compliant .ics file for an event's accepted date.
 * Access restricted to admin and invited users only (no guest access).
 */
class IcsExportAPIController extends ApiControllerBase
{
    use EventAccessTrait;

    /** @var int Maximum filename length for Content-Disposition header. */
    private const MAX_FILENAME_LENGTH = 100;

    /**
     * Admin and authenticated users can read; guests are denied.
     */
    protected array $rolesAndActions = [
        'admin' => ['read'],
        'user' => ['read'],
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
     * Register the ICS export route.
     *
     * @return array Route definitions for APIRouteRegistry.
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/Events/{event_id}/ics',
                'apiClass' => self::class,
                'apiMethod' => 'getIcs',
                'parameterNames' => ['event_id'],
                'rbacAction' => 'read',
            ],
        ];
    }

    /**
     * Generate and return an ICS file for the given event.
     *
     * @param Request $request The incoming API request.
     * @return array Raw response with text/calendar content type.
     * @throws BadRequestException If event_id is missing.
     * @throws NotFoundException If event not found or no accepted date.
     * @throws ForbiddenException If user is not authenticated or not invited.
     */
    public function getIcs(Request $request): array
    {
        $eventId = $request->get('event_id');
        if (empty($eventId)) {
            throw new BadRequestException('Event ID is required');
        }

        $event = $this->validateIcsAccess($eventId);

        $acceptedDate = $event->get('accepted_date');
        if (empty($acceptedDate)) {
            throw new NotFoundException(
                'No accepted date set for this event. ICS export is only available after a date has been accepted.',
                ['event_id' => $eventId]
            );
        }

        $icsService = new IcsGeneratorService($this->logger, $this->config);
        $eventData = [
            'id' => $eventId,
            'name' => $event->get('name'),
            'description' => $event->get('description'),
            'location' => $event->get('location'),
            'accepted_date' => $acceptedDate,
            'duration_hours' => $event->get('duration_hours') ?? 3,
        ];
        $icsContent = $icsService->generateIcsContent($eventData);

        $filename = $this->sanitizeFilename($event->get('name'));

        $this->logger?->info('ICS file generated for download', [
            'event_id' => $eventId,
        ]);

        return [
            'raw_response' => true,
            'content_type' => 'text/calendar; charset=utf-8',
            'headers' => [
                'Content-Disposition' => 'attachment; filename="' . $filename . '.ics"',
            ],
            'body' => $icsContent,
            'status' => 200,
        ];
    }

    /**
     * Validate that the current user can access the ICS export.
     *
     * Guests (unauthenticated users) are denied. Admins have full access.
     * Non-admin users must be invited to the event.
     *
     * @param string $eventId The event ID to validate access for.
     * @return ModelBase The loaded event model.
     * @throws NotFoundException If the event does not exist.
     * @throws ForbiddenException If the user is not authenticated or not invited.
     */
    protected function validateIcsAccess(string $eventId): ModelBase
    {
        $eventsModel = $this->modelFactory->new('Events');
        $event = $eventsModel->findById($eventId);
        if ($event === null) {
            throw new NotFoundException(
                'Event not found',
                ['event_id' => $eventId]
            );
        }

        $currentUser = $this->getCurrentUser();
        if ($currentUser === null) {
            throw new ForbiddenException(
                'Authentication required to download ICS file',
                ['event_id' => $eventId]
            );
        }

        $currentUserId = $currentUser->get('id');
        $isAdmin = $this->isUserAdmin($currentUserId);

        if (!$isAdmin) {
            $isInvited = $this->isUserInvited($eventId, $currentUserId);
            if (!$isInvited) {
                throw new ForbiddenException(
                    'You are not invited to this event',
                    ['event_id' => $eventId, 'user_id' => $currentUserId]
                );
            }
        }

        return $event;
    }

    /**
     * Sanitize a string for use as a filename in Content-Disposition header.
     *
     * @param string $name The raw event name.
     * @return string Sanitized filename (alphanumeric, underscores, hyphens).
     */
    protected function sanitizeFilename(string $name): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        return substr($sanitized, 0, self::MAX_FILENAME_LENGTH);
    }
}
