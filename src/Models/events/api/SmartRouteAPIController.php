<?php

namespace Gravitycar\Models\events\api;

use Gravitycar\Api\Request;
use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Monolog\Logger;

/**
 * Lightweight API controller for smart routing of the Events nav item.
 * Returns a redirect target URL based on the current user's upcoming event invitations.
 * If the user has exactly one upcoming event, redirects to its Chart of Goodness;
 * otherwise redirects to the events list.
 */
class SmartRouteAPIController extends ApiControllerBase
{
    use EventAccessTrait;

    /**
     * All roles get read access to the smart route endpoint.
     */
    protected array $rolesAndActions = [
        'admin' => ['read'],
        'user' => ['read'],
        'guest' => ['read'],
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
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config, $currentUserProvider);
    }

    /**
     * Register the smart route endpoint.
     *
     * @return array Route definitions for APIRouteRegistry
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/Events/smart-route',
                'apiClass' => self::class,
                'apiMethod' => 'getSmartRoute',
                'parameterNames' => [],
                'rbacAction' => 'read',
            ],
        ];
    }

    /**
     * Determine the smart route redirect target for the current user.
     *
     * Logic:
     * - Guest (unauthenticated): always /events
     * - Authenticated user with exactly 1 upcoming event: /events/{id}/chart
     * - Otherwise: /events
     *
     * @param Request $request The incoming API request
     * @return array Response payload with redirect_to URL
     */
    public function getSmartRoute(Request $request): array
    {
        $currentUser = $this->getCurrentUser();
        if ($currentUser === null) {
            return $this->buildResponse('/events', 0);
        }

        $upcomingEventIds = $this->findUpcomingEventIds($currentUser);

        if (count($upcomingEventIds) === 1) {
            $redirectTo = '/events/' . $upcomingEventIds[0] . '/chart';
            return $this->buildResponse($redirectTo, 1);
        }

        return $this->buildResponse('/events', count($upcomingEventIds));
    }

    /**
     * Find IDs of upcoming events the user is invited to.
     * An event is "upcoming" if it has a future accepted_date
     * or at least one proposed date in the future.
     *
     * @param \Gravitycar\Models\ModelBase $currentUser The authenticated user model
     * @return array List of event ID strings
     */
    protected function findUpcomingEventIds(\Gravitycar\Models\ModelBase $currentUser): array
    {
        $invitedEvents = $currentUser->getRelatedModels('events_users_invitations');
        $upcomingEventIds = [];
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach ($invitedEvents as $eventModel) {
            if ($this->isEventUpcoming($eventModel, $now)) {
                $upcomingEventIds[] = $eventModel->get('id');
            }
        }

        return $upcomingEventIds;
    }

    /**
     * Check if an event is upcoming based on accepted_date or future proposed dates.
     *
     * @param \Gravitycar\Models\ModelBase $eventModel The event model instance
     * @param \DateTimeImmutable $now Current UTC time
     * @return bool True if the event is upcoming
     */
    protected function isEventUpcoming(\Gravitycar\Models\ModelBase $eventModel, \DateTimeImmutable $now): bool
    {
        $acceptedDate = $eventModel->get('accepted_date');
        if ($acceptedDate !== null && new \DateTimeImmutable($acceptedDate) > $now) {
            return true;
        }

        $proposedDatesModel = $this->modelFactory->new('EventProposedDates');
        $futureDates = $proposedDatesModel->findRaw(
            ['event_id' => $eventModel->get('id')],
            ['id'],
            [
                'where' => ['proposed_date > :now'],
                'params' => ['now' => $now->format('Y-m-d H:i:s')],
            ]
        );

        return count($futureDates) > 0;
    }

    /**
     * Build the standard response payload.
     *
     * @param string $redirectTo The target URL
     * @param int $upcomingCount Number of upcoming events found
     * @return array Response array
     */
    protected function buildResponse(string $redirectTo, int $upcomingCount): array
    {
        $this->logger->debug('Smart route resolved', [
            'redirect_to' => $redirectTo,
            'upcoming_count' => $upcomingCount,
        ]);

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'redirect_to' => $redirectTo,
                'upcoming_count' => $upcomingCount,
            ],
            'timestamp' => date('c'),
        ];
    }
}
