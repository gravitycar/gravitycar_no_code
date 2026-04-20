<?php

namespace Gravitycar\Models\events\api;

use Gravitycar\Api\Request;
use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Monolog\Logger;

/**
 * Custom API controller for event commitment endpoints.
 * Provides PUT upsert for per-cell commitment toggling and
 * POST accept-all for marking all proposed dates as available.
 * Enforces invitation-gated access and own-row authorization.
 */
class CommitmentsAPIController extends ApiControllerBase
{
    use EventAccessTrait;

    /**
     * Only authenticated users (admin or user) can modify commitments.
     * Guests are excluded entirely per AC-15.
     */
    protected array $rolesAndActions = [
        'admin' => ['update', 'create'],
        'user' => ['update'],
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
     * Register commitment routes.
     *
     * @return array Route definitions for APIRouteRegistry
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'PUT',
                'path' => '/Events/{event_id}/commitments',
                'apiClass' => self::class,
                'apiMethod' => 'upsertCommitments',
                'parameterNames' => ['event_id'],
                'rbacAction' => 'update',
            ],
            [
                'method' => 'POST',
                'path' => '/Events/{event_id}/accept-all',
                'apiClass' => self::class,
                'apiMethod' => 'acceptAll',
                'parameterNames' => ['event_id'],
                'rbacAction' => 'create',
            ],
        ];
    }

    /**
     * Upsert one or more commitment records for the authenticated user.
     *
     * @param Request $request The incoming API request
     * @return array Response payload with created/updated counts
     * @throws BadRequestException If event_id is missing or input is invalid
     */
    public function upsertCommitments(Request $request): array
    {
        $eventId = $request->get('event_id');
        if (empty($eventId)) {
            throw new BadRequestException('Event ID is required');
        }

        $accessInfo = $this->validateCommitmentAccess($eventId);
        $userId = $accessInfo['currentUserId'];

        $requestData = $request->getRequestData();
        $validated = $this->validateCommitmentInput($requestData);

        $proposedDateIds = array_column($validated, 'proposed_date_id');
        $this->validateProposedDatesExist($eventId, $proposedDateIds);

        $results = ['created' => 0, 'updated' => 0];
        foreach ($validated as $entry) {
            $action = $this->upsertSingleCommitment(
                $eventId,
                $userId,
                $entry['proposed_date_id'],
                $entry['is_available']
            );
            $results[$action]++;
        }

        $this->logger->info('Commitments upserted', [
            'event_id' => $eventId,
            'user_id' => $userId,
            'created' => $results['created'],
            'updated' => $results['updated'],
        ]);

        return [
            'success' => true,
            'status' => 200,
            'data' => $results,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Accept all proposed dates for the authenticated user on an event.
     *
     * @param Request $request The incoming API request
     * @return array Response payload with created/updated counts
     * @throws BadRequestException If event_id is missing
     */
    public function acceptAll(Request $request): array
    {
        $eventId = $request->get('event_id');
        if (empty($eventId)) {
            throw new BadRequestException('Event ID is required');
        }

        $accessInfo = $this->validateCommitmentAccess($eventId);
        $userId = $accessInfo['currentUserId'];

        $proposedDatesModel = $this->modelFactory->new('EventProposedDates');
        $proposedDates = $proposedDatesModel->findRaw(
            ['event_id' => $eventId],
            ['id']
        );

        if (empty($proposedDates)) {
            return [
                'success' => true,
                'status' => 200,
                'data' => ['created' => 0, 'updated' => 0],
                'timestamp' => date('c'),
            ];
        }

        $results = ['created' => 0, 'updated' => 0];
        foreach ($proposedDates as $pd) {
            $action = $this->upsertSingleCommitment(
                $eventId,
                $userId,
                $pd['id'],
                true
            );
            $results[$action]++;
        }

        $this->logger->info('Accept-all commitments', [
            'event_id' => $eventId,
            'user_id' => $userId,
            'created' => $results['created'],
            'updated' => $results['updated'],
        ]);

        return [
            'success' => true,
            'status' => 200,
            'data' => $results,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Validate the commitments input array from the request body.
     *
     * @param array $requestData The parsed request body
     * @return array Validated commitment entries
     * @throws BadRequestException If input is malformed
     */
    protected function validateCommitmentInput(array $requestData): array
    {
        $commitments = $requestData['commitments'] ?? null;
        if (!is_array($commitments) || empty($commitments)) {
            throw new BadRequestException(
                'Request must include a non-empty "commitments" array'
            );
        }

        $validated = [];
        foreach ($commitments as $index => $entry) {
            if (!isset($entry['proposed_date_id']) || !is_string($entry['proposed_date_id'])) {
                throw new BadRequestException(
                    "commitments[{$index}] must have a string proposed_date_id"
                );
            }
            if (!isset($entry['is_available']) || !is_bool($entry['is_available'])) {
                throw new BadRequestException(
                    "commitments[{$index}] must have a boolean is_available"
                );
            }
            $validated[] = [
                'proposed_date_id' => $entry['proposed_date_id'],
                'is_available' => $entry['is_available'],
            ];
        }

        return $validated;
    }

    /**
     * Validate that all proposed date IDs belong to the specified event.
     *
     * @param string $eventId The event ID
     * @param array $proposedDateIds Array of proposed date ID strings
     * @throws BadRequestException If any proposed date ID is invalid
     */
    protected function validateProposedDatesExist(
        string $eventId,
        array $proposedDateIds
    ): void {
        $proposedDatesModel = $this->modelFactory->new('EventProposedDates');

        foreach ($proposedDateIds as $pdId) {
            $results = $proposedDatesModel->findRaw(
                ['id' => $pdId, 'event_id' => $eventId],
                ['id']
            );
            if (empty($results)) {
                throw new BadRequestException(
                    'One or more proposed_date_id values are invalid for this event',
                    ['event_id' => $eventId, 'proposed_date_ids' => $proposedDateIds]
                );
            }
        }
    }

    /**
     * Upsert a single commitment record (SELECT then INSERT or UPDATE).
     *
     * @param string $eventId The event ID
     * @param string $userId The authenticated user's ID
     * @param string $proposedDateId The proposed date ID
     * @param bool $isAvailable Whether the user is available
     * @return string 'created' or 'updated'
     */
    protected function upsertSingleCommitment(
        string $eventId,
        string $userId,
        string $proposedDateId,
        bool $isAvailable
    ): string {
        $commitmentsModel = $this->modelFactory->new('EventCommitments');

        $existing = $commitmentsModel->findFirst([
            'event_id' => $eventId,
            'user_id' => $userId,
            'proposed_date_id' => $proposedDateId,
        ]);

        if ($existing !== null) {
            $existing->set('is_available', $isAvailable ? 1 : 0);
            $existing->update();
            return 'updated';
        }

        $newCommitment = $this->modelFactory->new('EventCommitments');
        $newCommitment->set('event_id', $eventId);
        $newCommitment->set('user_id', $userId);
        $newCommitment->set('proposed_date_id', $proposedDateId);
        $newCommitment->set('is_available', $isAvailable ? 1 : 0);
        $newCommitment->create();

        return 'created';
    }
}
