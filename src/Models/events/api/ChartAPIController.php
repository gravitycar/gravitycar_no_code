<?php

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
use Monolog\Logger;

/**
 * Custom API controller for the Chart of Goodness endpoint.
 * Assembles grid data: proposed dates as columns, invited users as rows,
 * and commitments as cells. Implements its own access control independent
 * of the EventCommitments model's rolesAndActions.
 */
class ChartAPIController extends ApiControllerBase
{
    /**
     * All three roles get read access. The controller's own getChart()
     * method performs additional invitation-gated authorization.
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
     * Register the chart route.
     *
     * @return array Route definitions for APIRouteRegistry
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/Events/{event_id}/chart',
                'apiClass' => self::class,
                'apiMethod' => 'getChart',
                'parameterNames' => ['event_id'],
                'rbacAction' => 'read',
            ],
        ];
    }

    /**
     * Assemble and return the full chart grid data for a given event.
     *
     * @param Request $request The incoming API request
     * @return array The chart response payload
     * @throws BadRequestException If event_id is missing
     * @throws NotFoundException If the event does not exist
     * @throws ForbiddenException If a non-admin user is not invited
     */
    public function getChart(Request $request): array
    {
        $eventId = $request->get('event_id');
        if (empty($eventId)) {
            throw new BadRequestException('Event ID is required');
        }

        $accessInfo = $this->validateChartAccess($eventId);
        $event = $accessInfo['event'];

        $proposedDates = $this->fetchProposedDates($eventId);

        $usersMetadata = $this->metadataEngine->getModelMetadata('Users');
        $displayColumns = $usersMetadata['displayColumns'] ?? ['username'];
        $invitedUsers = $this->fetchInvitedUsers($eventId, $displayColumns);

        $commitments = $this->fetchCommitments($eventId);

        $this->logger->info('Chart data assembled', [
            'event_id' => $eventId,
            'proposed_dates_count' => count($proposedDates),
            'invited_users_count' => count($invitedUsers),
            'commitments_count' => count($commitments),
        ]);

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'event' => $this->buildEventPayload($event),
                'proposed_dates' => $proposedDates,
                'users' => $invitedUsers,
                'user_display_columns' => $displayColumns,
                'commitments' => $commitments,
                'current_user_id' => $accessInfo['currentUserId'],
                'is_admin' => $accessInfo['isAdmin'],
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * Validate that the current user has access to view the chart.
     *
     * @param string $eventId The event ID to check access for
     * @return array Access info with keys: event, currentUserId, isAdmin
     * @throws NotFoundException If event not found
     * @throws ForbiddenException If authenticated non-admin user is not invited
     */
    protected function validateChartAccess(string $eventId): array
    {
        $eventsModel = $this->modelFactory->new('Events');
        $event = $eventsModel->findById($eventId);
        if ($event === null) {
            throw new NotFoundException('Event not found', ['event_id' => $eventId]);
        }

        $currentUser = $this->getCurrentUser();
        $isAdmin = false;
        $currentUserId = null;

        if ($currentUser !== null) {
            $currentUserId = $currentUser->get('id');
            $roles = $this->getUserRoles($currentUser);
            $isAdmin = in_array('admin', $roles, true);

            if (!$isAdmin) {
                $isInvited = $this->isUserInvited($eventId, $currentUserId);
                if (!$isInvited) {
                    throw new ForbiddenException(
                        'You are not invited to this event',
                        ['event_id' => $eventId, 'user_id' => $currentUserId]
                    );
                }
            }
        }
        // Guests (null currentUser) are allowed read-only access

        return [
            'event' => $event,
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
        ];
    }

    /**
     * Check if a user is invited to the given event.
     *
     * @param string $eventId The event ID
     * @param string $userId The user ID to check
     * @return bool True if the user is invited
     */
    protected function isUserInvited(string $eventId, string $userId): bool
    {
        $eventsModel = $this->modelFactory->new('Events');
        $eventsModel->findById($eventId);

        $usersModel = $this->modelFactory->new('Users');
        $usersModel->findById($userId);

        return $eventsModel->hasRelation('events_users_invitations', $usersModel);
    }

    /**
     * Get the role names for a given user.
     *
     * @param ModelBase $currentUser The user model instance
     * @return array List of role name strings
     */
    protected function getUserRoles(ModelBase $currentUser): array
    {
        $roleModels = $currentUser->getRelatedModels('users_roles');
        $roles = [];
        foreach ($roleModels as $roleModel) {
            $roles[] = $roleModel->get('name');
        }
        return $roles;
    }

    /**
     * Fetch proposed dates for an event, ordered chronologically.
     *
     * @param string $eventId The event ID
     * @return array Raw rows with id and proposed_date
     */
    protected function fetchProposedDates(string $eventId): array
    {
        $proposedDatesModel = $this->modelFactory->new('EventProposedDates');
        return $proposedDatesModel->findRaw(
            ['event_id' => $eventId],
            ['id', 'proposed_date'],
            ['orderBy' => ['proposed_date' => 'ASC']]
        );
    }

    /**
     * Fetch invited users with display column values.
     *
     * @param string $eventId The event ID
     * @param array $displayColumns Column names from Users metadata
     * @return array User data arrays with id and display column values
     */
    protected function fetchInvitedUsers(string $eventId, array $displayColumns): array
    {
        $eventsModel = $this->modelFactory->new('Events');
        $eventsModel->findById($eventId);
        $relatedUsers = $eventsModel->getRelatedModels('events_users_invitations');

        $result = [];
        foreach ($relatedUsers as $userModel) {
            $userData = ['id' => $userModel->get('id')];
            foreach ($displayColumns as $col) {
                $userData[$col] = $userModel->get($col);
            }
            $result[] = $userData;
        }

        usort($result, fn($a, $b) => strcmp($a['id'], $b['id']));

        return $result;
    }

    /**
     * Fetch all commitments for an event, indexed for O(1) cell lookup.
     *
     * @param string $eventId The event ID
     * @return array Map of "user_id:proposed_date_id" => bool
     */
    protected function fetchCommitments(string $eventId): array
    {
        $commitmentsModel = $this->modelFactory->new('EventCommitments');
        $rows = $commitmentsModel->findRaw(
            ['event_id' => $eventId],
            ['user_id', 'proposed_date_id', 'is_available']
        );

        $indexed = [];
        foreach ($rows as $row) {
            $key = $row['user_id'] . ':' . $row['proposed_date_id'];
            $indexed[$key] = (bool) $row['is_available'];
        }
        return $indexed;
    }

    /**
     * Build the event data payload for the response.
     *
     * @param ModelBase $event The loaded event model
     * @return array Event fields for the response
     */
    protected function buildEventPayload(ModelBase $event): array
    {
        return [
            'id' => $event->get('id'),
            'name' => $event->get('name'),
            'description' => $event->get('description'),
            'location' => $event->get('location'),
            'duration_hours' => $event->get('duration_hours'),
            'accepted_date' => $event->get('accepted_date'),
            'linked_model_name' => $event->get('linked_model_name'),
            'linked_record_id' => $event->get('linked_record_id'),
            'created_by' => $event->get('created_by'),
        ];
    }
}
