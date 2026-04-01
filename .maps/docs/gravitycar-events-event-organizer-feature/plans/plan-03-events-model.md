# Implementation Plan: Events Model

## Spec Context

The Events model is the primary model for the event organizer feature. It represents a scheduled gathering with fields for name, description, location, duration, an accepted date, and optional linking to another model's record. It also has two computed properties: `is_active` (event has future proposed dates and no accepted_date) and `most_popular_dates` (proposed dates with highest availability count, including ties). This model is the foundation that nearly all other catalog items depend on.

- **Catalog item**: 3 - Events Model
- **Specification section**: Models section -- Events
- **Acceptance criteria addressed**: AC-1 (admin can create an event), AC-16 (events with future proposed dates and no accepted_date are "active" and sorted first)

## Dependencies

- **Blocked by**: None
- **Uses**: `src/Models/ModelBase.php` (base class), `src/Models/users/users_metadata.php` and `src/Models/users/Users.php` (pattern reference), `Gravitycar\Factories\ModelFactory` (for querying related models in computed properties)

## File Changes

### New Files

- `src/Models/events/events_metadata.php` -- Metadata definition for the Events model
- `src/Models/events/Events.php` -- Model class extending ModelBase with computed property methods
- `tests/Models/EventsTest.php` -- Unit tests for the Events model

### Modified Files

None.

## Implementation Details

### 1. Events Metadata

**File**: `src/Models/events/events_metadata.php`

This file returns a PHP array defining the Events model. Follow the exact structure of `src/Models/users/users_metadata.php`.

```php
<?php
// Events model metadata for Gravitycar framework
return [
    'name' => 'Events',
    'table' => 'events',
    'displayColumns' => ['name'],
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'Text',
            'label' => 'Event Name',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'description' => [
            'name' => 'description',
            'type' => 'Text',
            'label' => 'Description',
            'required' => false,
            'validationRules' => [],
        ],
        'location' => [
            'name' => 'location',
            'type' => 'Text',
            'label' => 'Location',
            'required' => false,
            'validationRules' => [],
        ],
        'duration_hours' => [
            'name' => 'duration_hours',
            'type' => 'Integer',
            'label' => 'Duration (Hours)',
            'required' => false,
            'defaultValue' => 3,
            'validationRules' => [],
        ],
        'accepted_date' => [
            'name' => 'accepted_date',
            'type' => 'DateTime',
            'label' => 'Accepted Date',
            'required' => false,
            'validationRules' => ['DateTime'],
        ],
        'linked_model_name' => [
            'name' => 'linked_model_name',
            'type' => 'Text',
            'label' => 'Linked Model',
            'required' => false,
            'validationRules' => [],
        ],
        'linked_record_id' => [
            'name' => 'linked_record_id',
            'type' => 'ID',
            'label' => 'Linked Record ID',
            'required' => false,
            'validationRules' => [],
        ],
    ],
    'rolesAndActions' => [
        'admin' => ['*'],
        'user' => ['list', 'read'],
        'guest' => ['list', 'read'],
    ],
    'validationRules' => [],
    'relationships' => [
        'events_event_proposed_dates',
        'events_users_invitations',
        'events_event_commitments',
        'events_event_reminders',
    ],
    'apiRoutes' => [],
    'ui' => [
        'listFields' => ['name', 'location', 'accepted_date', 'duration_hours'],
        'createFields' => ['name', 'description', 'location', 'duration_hours', 'linked_model_name', 'linked_record_id'],
        'editFields' => ['name', 'description', 'location', 'duration_hours', 'accepted_date', 'linked_model_name', 'linked_record_id'],
    ],
];
```

**Key decisions:**
- `displayColumns` is `['name']` since the event name is the primary identifier.
- `linked_record_id` uses type `ID` (UUID format) to match the framework's UUID primary keys.
- `duration_hours` uses `Integer` type with `defaultValue` of 3 per the spec.
- `relationships` lists all four relationships that will reference Events (created in later catalog items). The framework tolerates forward references in the relationships array; they are resolved lazily.
- `guest` role gets `list` and `read` per spec (public read access for unauthenticated visitors).

### 2. Events Model Class

**File**: `src/Models/events/Events.php`

**Namespace**: `Gravitycar\Models\events`

**Extends**: `Gravitycar\Models\ModelBase`

**Constructor**: Same 7-param DI pattern as `Users.php` -- passes all params to `parent::__construct()`.

**Public methods**:

```php
public function isActive(): bool
```
Returns `true` when the event has at least one proposed date in the future AND `accepted_date` is NULL. Returns `false` otherwise.

**Logic:**
1. If `accepted_date` is not NULL, return `false`.
2. Query the `event_proposed_dates` table for records where `event_id` matches this event's ID and `proposed_date > NOW()`.
3. If at least one such record exists, return `true`. Otherwise return `false`.

```php
public function getMostPopularDates(): array
```
Returns an array of proposed date records that have the highest count of commitments where `is_available = true`. When multiple dates are tied, ALL tied dates are returned.

**Logic:**
1. Use `$this->databaseConnector->getConnection()` to run a Doctrine DBAL query.
2. Query: SELECT `proposed_date_id`, COUNT(*) as `vote_count` FROM `event_commitments` WHERE `event_id` = :eventId AND `is_available` = 1 AND `deleted_at` IS NULL GROUP BY `proposed_date_id` ORDER BY `vote_count` DESC.
3. Find the maximum `vote_count` from the results.
4. Filter to only rows matching that maximum.
5. For each matching `proposed_date_id`, fetch the proposed date record from `event_proposed_dates`.
6. Return an array of associative arrays: `['proposed_date_id' => string, 'proposed_date' => string, 'vote_count' => int]`.
7. If no commitments exist, return an empty array.

**Code example:**

```php
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

class Events extends ModelBase
{
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
     * @return string SQL ORDER BY clause
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
     */
    protected function hasFutureProposedDates(string $eventId): bool
    {
        $proposedDatesModel = $this->modelFactory->new('Event_Proposed_Dates');
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
     * @return array<int, array{proposed_date_id: string, proposed_date: string, vote_count: int}>
     */
    protected function filterTopVotedDates(array $voteCounts, int $maxCount): array
    {
        // Extract the IDs of proposed dates that are tied for the top vote count
        $topIds = [];
        $voteCountMap = [];
        foreach ($voteCounts as $row) {
            if ((int) $row['vote_count'] < $maxCount) {
                break; // Results are ordered DESC, so once we drop below max we are done
            }
            $topIds[] = $row['proposed_date_id'];
            $voteCountMap[$row['proposed_date_id']] = (int) $row['vote_count'];
        }

        if (empty($topIds)) {
            return [];
        }

        // Batch-fetch all winning proposed dates using the framework's find() API.
        // Passing an array as criteria value produces an IN(...) clause.
        $proposedDatesModel = $this->modelFactory->new('Event_Proposed_Dates');
        $proposedDates = $proposedDatesModel->findRaw(
            ['id' => $topIds],
            ['id', 'proposed_date']
        );

        // Combine proposed date details with vote counts
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
```

**Key decisions:**
- `isActive()` uses `ModelBase::findRaw()` via the Event_Proposed_Dates model — no raw SQL.
- `filterTopVotedDates()` uses `ModelBase::findRaw()` with an array of IDs (produces IN clause) — no raw SQL.
- `fetchAvailabilityCounts()` uses `DatabaseConnector->executeQuery()` for its GROUP BY/COUNT aggregation, which cannot be expressed through the framework's find() API. This is a known framework limitation; the raw SQL is routed through DatabaseConnector as the single point for all SQL execution.
- `getDefaultOrderBy()` uses raw SQL for its CASE WHEN subquery, which is too model-specific for the generic API. This is acceptable as an exception.
- Helper methods (`hasFutureProposedDates`, `fetchAvailabilityCounts`, `filterTopVotedDates`) keep complexity low and each method has a single responsibility.
- Soft-delete awareness: all queries include `deleted_at IS NULL`.

## Error Handling

- `isActive()` returns `false` if the event has no ID (unsaved event).
- `getMostPopularDates()` returns an empty array if the event has no ID or no commitments exist.
- Database exceptions from Doctrine DBAL will propagate up to the caller (consistent with framework pattern -- ModelBase does not catch DBAL exceptions internally).

## Unit Test Specifications

**File**: `tests/Models/EventsTest.php`

Tests will mock `DatabaseConnectorInterface` to control query results. The Events model is instantiated with mocked dependencies following the 7-param DI pattern.

### `Events.getDefaultOrderBy()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Returns ORDER BY string | Call getDefaultOrderBy() | String contains CASE WHEN with subquery and created_at DESC | AC-16: active events sorted first |
| Active events first | 2 events: one active, one inactive | Active event appears before inactive in list results | Active-first sorting |

### `Events.isActive()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Active event | accepted_date=NULL, 1 future proposed date | `true` | Happy path: future dates, no accepted date |
| Inactive: accepted date set | accepted_date="2026-05-01 19:00:00", future proposed dates exist | `false` | accepted_date being set makes it inactive |
| Inactive: no future dates | accepted_date=NULL, 0 future proposed dates | `false` | No future dates means inactive |
| Inactive: no ID | Event with no ID set | `false` | Unsaved event cannot be active |

### Key Scenario: Active Event

**Setup**: Create Events instance. Mock `get('accepted_date')` to return `null`. Mock `get('id')` to return a UUID. Mock `modelFactory->new('Event_Proposed_Dates')` to return a model whose `findRaw()` returns one row (one future proposed date).
**Action**: Call `isActive()`.
**Expected**: Returns `true`.

### `Events.getMostPopularDates()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Single winner | 3 dates, one with highest count | Array with 1 entry | Clear winner |
| Tied dates | 3 dates, two tied for highest | Array with 2 entries | Ties return all |
| No commitments | No commitment records | Empty array | No votes yet |
| No ID | Event with no ID | Empty array | Unsaved event |

### Key Scenario: Tied Most Popular Dates

**Setup**: Create Events instance with a valid ID. Mock `fetchAllAssociative` to return:
```
[
  ['proposed_date_id' => 'uuid-1', 'vote_count' => '5'],
  ['proposed_date_id' => 'uuid-2', 'vote_count' => '5'],
  ['proposed_date_id' => 'uuid-3', 'vote_count' => '3'],
]
```
Mock `fetchAssociative` for uuid-1 and uuid-2 to return their proposed_date values.
**Action**: Call `getMostPopularDates()`.
**Expected**: Returns array with 2 entries, both with `vote_count` of 5. uuid-3 is excluded.

### Test Helper Setup

All tests should use a shared helper to create the Events model with mocked dependencies:

```php
private function createEventsModel(): Events
{
    $logger = new Logger('test');
    $metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $fieldFactory = $this->createMock(FieldFactory::class);
    $databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    $relationshipFactory = $this->createMock(RelationshipFactory::class);
    $modelFactory = $this->createMock(ModelFactory::class);
    $currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

    // Configure metadataEngine to return events metadata
    $metadataEngine->method('getModelMetadata')
        ->willReturn(require __DIR__ . '/../../src/Models/events/events_metadata.php');

    return new Events(
        $logger,
        $metadataEngine,
        $fieldFactory,
        $databaseConnector,
        $relationshipFactory,
        $modelFactory,
        $currentUserProvider
    );
}
```

## Notes

- The `relationships` array in metadata references relationship names that will be created by catalog items 4, 5, 6, and 7. The framework resolves these lazily, so forward references are safe.
- The `created_by` field is automatically added by `CoreFieldsMetadata` to all models -- no need to define it in the metadata. It serves as the event owner per the spec.
- The `linked_record_id` field uses the `ID` type rather than `RelatedRecord` because it is a polymorphic reference (the target model varies based on `linked_model_name`). A `RelatedRecord` field requires a fixed target model.
- The `most_popular_dates` computation queries `event_commitments` and `event_proposed_dates` tables directly. These tables will be created by catalog items 4 and 6. The Events model code will not fail if those tables do not yet exist at class definition time -- the queries only run when `getMostPopularDates()` is called.
