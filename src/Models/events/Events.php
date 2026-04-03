<?php
namespace Gravitycar\Models\events;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * Events model for the Gravitycar event organizer feature.
 *
 * Represents a scheduled gathering with fields for name, description,
 * location, duration, an accepted date, and optional linking to another
 * model's record. Provides computed properties for active status and
 * most popular proposed dates.
 */
class Events extends ModelBase
{
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
     * Determine if this event is "active".
     *
     * An event is active when it has at least one proposed date
     * in the future AND accepted_date is NULL.
     *
     * @return bool True if the event is active, false otherwise.
     */
    public function isActive(): bool
    {
        if ($this->get('accepted_date') !== null) {
            return false;
        }

        $eventId = $this->get('id');
        if (empty($eventId)) {
            return false;
        }

        return $this->hasFutureProposedDates($eventId);
    }

    /**
     * Override default ordering to sort active events first (AC-16).
     *
     * Active events (future proposed dates, no accepted_date) appear before
     * inactive events. Within each group, events are sorted by created_at DESC.
     *
     * @return string SQL ORDER BY clause.
     */
    public function getDefaultOrderBy(): string
    {
        $table = $this->getTableName();
        return "(
            CASE WHEN {$table}.accepted_date IS NULL
                 AND EXISTS (
                     SELECT 1 FROM event_proposed_dates epd
                     WHERE epd.event_id = {$table}.id
                     AND epd.proposed_date > NOW()
                     AND epd.deleted_at IS NULL
                 )
            THEN 0 ELSE 1 END
        ) ASC, {$table}.created_at DESC";
    }

    /**
     * Get the most popular proposed date(s) by availability count.
     *
     * Returns ALL tied dates when multiple dates share the highest count.
     *
     * @return array<int, array{proposed_date_id: string, proposed_date: string, vote_count: int}>
     */
    public function getMostPopularDates(): array
    {
        $eventId = $this->get('id');
        if (empty($eventId)) {
            return [];
        }

        $voteCounts = $this->fetchAvailabilityCounts($eventId);
        if (empty($voteCounts)) {
            return [];
        }

        $maxCount = (int) $voteCounts[0]['vote_count'];

        return $this->filterTopVotedDates($voteCounts, $maxCount);
    }

    /**
     * Check if the event has any proposed dates in the future.
     *
     * @param string $eventId The event's UUID.
     * @return bool True if at least one future proposed date exists.
     */
    protected function hasFutureProposedDates(string $eventId): bool
    {
        $proposedDatesModel = $this->modelFactory->new('EventProposedDates');
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $futureDates = $proposedDatesModel->findRaw(
            ['event_id' => $eventId],
            ['id'],
            ['where' => ['proposed_date > :now'], 'params' => ['now' => $now]]
        );

        return count($futureDates) > 0;
    }

    /**
     * Fetch availability vote counts per proposed date, ordered descending.
     *
     * NOTE: This uses DatabaseConnector->executeQuery() because the framework's
     * find() API does not support GROUP BY or computed columns like COUNT(*).
     * This is a known framework limitation; the raw SQL is routed through
     * DatabaseConnector as the single point for all SQL execution.
     *
     * @param string $eventId The event's UUID.
     * @return array<int, array{proposed_date_id: string, vote_count: string}>
     */
    protected function fetchAvailabilityCounts(string $eventId): array
    {
        return $this->databaseConnector->executeQuery(
            'SELECT ec.proposed_date_id, COUNT(*) as vote_count
             FROM event_commitments ec
             WHERE ec.event_id = :eventId
             AND ec.is_available = 1
             AND ec.deleted_at IS NULL
             GROUP BY ec.proposed_date_id
             ORDER BY vote_count DESC',
            ['eventId' => $eventId]
        );
    }

    /**
     * Filter vote count results to only those matching the max count,
     * then enrich with proposed date details using the framework's find() API.
     *
     * @param array $voteCounts The vote count results from fetchAvailabilityCounts.
     * @param int $maxCount The maximum vote count to filter by.
     * @return array<int, array{proposed_date_id: string, proposed_date: string, vote_count: int}>
     */
    protected function filterTopVotedDates(array $voteCounts, int $maxCount): array
    {
        $topIds = [];
        $voteCountMap = [];
        foreach ($voteCounts as $row) {
            if ((int) $row['vote_count'] < $maxCount) {
                break;
            }
            $topIds[] = $row['proposed_date_id'];
            $voteCountMap[$row['proposed_date_id']] = (int) $row['vote_count'];
        }

        if (empty($topIds)) {
            return [];
        }

        $proposedDatesModel = $this->modelFactory->new('EventProposedDates');
        $proposedDates = $proposedDatesModel->findRaw(
            ['id' => $topIds],
            ['id', 'proposed_date']
        );

        $result = [];
        foreach ($proposedDates as $pd) {
            $result[] = [
                'proposed_date_id' => $pd['id'],
                'proposed_date' => $pd['proposed_date'],
                'vote_count' => $voteCountMap[$pd['id']] ?? $maxCount,
            ];
        }

        return $result;
    }
}
