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
use Monolog\Logger;

/**
 * Custom API controller for the Most Popular Date endpoint.
 * Returns the proposed date(s) with the highest vote count for a given event.
 * When multiple dates are tied, all tied dates are returned.
 * Uses EventAccessTrait for shared authorization helpers.
 */
class MostPopularDateAPIController extends ApiControllerBase
{
    use EventAccessTrait;

    /**
     * All three roles get read access. Guest read-only access is allowed.
     * Additional invitation-gated authorization is performed in validateReadAccess().
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
     * Register the most-popular-date route.
     *
     * @return array Route definitions for APIRouteRegistry
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/events/{event_id}/most-popular-date',
                'apiClass' => self::class,
                'apiMethod' => 'getMostPopularDate',
                'parameterNames' => ['event_id'],
                'rbacAction' => 'read',
            ],
        ];
    }

    /**
     * Return the most popular date(s) for a given event.
     * Delegates to Events::getMostPopularDates() for the core computation.
     *
     * @param Request $request The incoming API request
     * @return array The response payload with most popular dates
     * @throws BadRequestException If event_id is missing
     * @throws NotFoundException If the event does not exist
     * @throws ForbiddenException If a non-admin user is not invited
     */
    public function getMostPopularDate(Request $request): array
    {
        $eventId = $request->get('event_id');
        if (empty($eventId)) {
            throw new BadRequestException('Event ID is required');
        }

        $accessInfo = $this->validateReadAccess($eventId);
        $event = $accessInfo['event'];

        $mostPopularDates = $event->getMostPopularDates();

        $this->logger->info('Most popular date(s) retrieved', [
            'event_id' => $eventId,
            'tied_count' => count($mostPopularDates),
        ]);

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'event_id' => $eventId,
                'most_popular_dates' => $mostPopularDates,
                'tied' => count($mostPopularDates) > 1,
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * Validate that the current user has read access to this event.
     * Admin and invited users have access. Guests (null currentUser) get read-only access.
     *
     * @param string $eventId The event ID to check access for
     * @return array Access info with keys: event, currentUserId, isAdmin
     * @throws NotFoundException If event not found
     * @throws ForbiddenException If authenticated non-admin user is not invited
     */
    protected function validateReadAccess(string $eventId): array
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
        }
        // Guests (null currentUser) are allowed read-only access

        return [
            'event' => $event,
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
        ];
    }
}
