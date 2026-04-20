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
 * Custom API controller for batch-creating proposed dates on an event.
 * Accepts an array of UTC datetime strings and creates one
 * EventProposedDates record per datetime, skipping duplicates.
 */
class BatchProposedDatesAPIController extends ApiControllerBase
{
    use EventAccessTrait;

    /** @var string Expected datetime format from frontend */
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Only admins can batch-create proposed dates.
     */
    protected array $rolesAndActions = [
        'admin' => ['create'],
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
     * Register batch proposed dates route.
     *
     * @return array Route definitions for APIRouteRegistry
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'POST',
                'path' => '/Events/{event_id}/proposed-dates/batch',
                'apiClass' => self::class,
                'apiMethod' => 'batchCreate',
                'parameterNames' => ['event_id'],
                'rbacAction' => 'create',
            ],
        ];
    }

    /**
     * Batch-create proposed date records for an event.
     *
     * @param Request $request The incoming API request
     * @return array Response payload with created/skipped counts
     * @throws BadRequestException If input is invalid
     */
    public function batchCreate(Request $request): array
    {
        $eventId = $request->get('event_id');
        if (empty($eventId)) {
            throw new BadRequestException('Event ID is required');
        }

        $this->validateEventExists($eventId);
        $requestData = $request->getRequestData();
        $dates = $this->validateDatesInput($requestData);
        $existingDates = $this->fetchExistingDates($eventId);

        $created = 0;
        $skipped = 0;

        foreach ($dates as $dateStr) {
            if (in_array($dateStr, $existingDates, true)) {
                $skipped++;
                continue;
            }

            $this->createProposedDate($eventId, $dateStr);
            $created++;
        }

        $this->logger->info('Batch proposed dates created', [
            'event_id' => $eventId,
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * Validate that the event exists.
     *
     * @param string $eventId The event ID
     * @throws BadRequestException If event does not exist
     */
    protected function validateEventExists(string $eventId): void
    {
        $event = $this->modelFactory->retrieve('Events', $eventId);
        if ($event === null) {
            throw new BadRequestException(
                'Event not found',
                ['event_id' => $eventId]
            );
        }
    }

    /**
     * Validate and extract the dates array from request data.
     *
     * @param array $requestData The parsed request body
     * @return array Validated datetime strings
     * @throws BadRequestException If input is malformed
     */
    protected function validateDatesInput(array $requestData): array
    {
        $dates = $requestData['dates'] ?? null;
        if (!is_array($dates) || empty($dates)) {
            throw new BadRequestException(
                'Request must include a non-empty "dates" array'
            );
        }

        $validated = [];
        foreach ($dates as $index => $dateStr) {
            if (!is_string($dateStr)) {
                throw new BadRequestException(
                    "dates[{$index}] must be a string"
                );
            }

            $parsed = \DateTimeImmutable::createFromFormat(
                self::DATETIME_FORMAT,
                $dateStr,
                new \DateTimeZone('UTC')
            );
            if ($parsed === false) {
                throw new BadRequestException(
                    "dates[{$index}] must be in Y-m-d H:i:s format",
                    ['value' => $dateStr]
                );
            }

            $validated[] = $parsed->format(self::DATETIME_FORMAT);
        }

        return $validated;
    }

    /**
     * Fetch all existing proposed dates for an event as formatted strings.
     *
     * @param string $eventId The event ID
     * @return array Array of datetime strings already proposed
     */
    protected function fetchExistingDates(string $eventId): array
    {
        $model = $this->modelFactory->new('EventProposedDates');
        $rows = $model->findRaw(
            ['event_id' => $eventId],
            ['proposed_date']
        );

        return array_map(
            fn(array $row): string => $row['proposed_date'],
            $rows
        );
    }

    /**
     * Create a single EventProposedDates record.
     *
     * @param string $eventId The parent event ID
     * @param string $dateStr The proposed datetime in Y-m-d H:i:s format
     */
    protected function createProposedDate(string $eventId, string $dateStr): void
    {
        $model = $this->modelFactory->new('EventProposedDates');
        $model->set('event_id', $eventId);
        $model->set('proposed_date', $dateStr);
        $model->create();
    }
}
