# Implementation Plan: Event_Commitments Model

## Spec Context

The Event_Commitments model is the ternary relationship model that records a specific user's availability for a specific proposed date on a specific event. Each record represents one cell in the Chart of Goodness grid. The combination of (event_id, user_id, proposed_date_id) must be unique. The `is_available` boolean indicates whether the user can attend on that date. This model is a prerequisite for the Chart API (item 9), Commitments API (item 10), and Most Popular Date endpoint (item 12).

- **Catalog item**: 6 - Event_Commitments Model
- **Specification section**: Models section -- Event_Commitments
- **Acceptance criteria addressed**: AC-5 (user can toggle availability, changes persist), AC-15 (users cannot modify another user's commitments)

## Dependencies

- **Blocked by**: Item 3 (Events Model), Item 4 (Event_Proposed_Dates Model), Item 5 (Event_Invitations Relationship) -- the three RelatedRecord fields reference these models, and the unique constraint spans all three foreign keys.
- **Uses**: `src/Models/ModelBase.php` (base class), `src/Models/event_proposed_dates/event_proposed_dates_metadata.php` (pattern reference for RelatedRecord fields), `src/Schema/SchemaGenerator.php` (needs enhancement for composite unique constraints on model tables)

## File Changes

### New Files

- `src/Models/event_commitments/event_commitments_metadata.php` -- Metadata definition with fields, roles, and composite unique constraint declaration
- `src/Models/event_commitments/EventCommitments.php` -- Model class extending ModelBase with unique constraint enforcement
- `src/Relationships/events_event_commitments/events_event_commitments_metadata.php` -- OneToMany relationship metadata (Events -> Event_Commitments)
- `tests/Models/EventCommitmentsTest.php` -- Unit tests

### Modified Files

- `src/Schema/SchemaGenerator.php` -- Add support for a `uniqueConstraints` metadata key on model tables so the composite unique index is created automatically.

## Implementation Details

### 1. Event_Commitments Metadata

**File**: `src/Models/event_commitments/event_commitments_metadata.php`

Follow the same structure as `src/Models/event_proposed_dates/event_proposed_dates_metadata.php` for RelatedRecord fields.

```php
<?php
// Event_Commitments model metadata for Gravitycar framework
return [
    'name' => 'Event_Commitments',
    'table' => 'event_commitments',
    'displayColumns' => ['event_display', 'user_display', 'is_available'],
    'fields' => [
        'event_id' => [
            'name' => 'event_id',
            'type' => 'RelatedRecord',
            'label' => 'Event',
            'required' => true,
            'relatedModel' => 'Events',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'event_display',
            'description' => 'The event this commitment belongs to',
            'validationRules' => ['Required'],
        ],
        'event_display' => [
            'name' => 'event_display',
            'type' => 'Text',
            'label' => 'Event Name',
            'readOnly' => true,
            'isDBField' => false,
            'description' => 'Display name of the event',
            'validationRules' => [],
        ],
        'user_id' => [
            'name' => 'user_id',
            'type' => 'RelatedRecord',
            'label' => 'User',
            'required' => true,
            'relatedModel' => 'Users',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'user_display',
            'description' => 'The invited user making this commitment',
            'validationRules' => ['Required'],
        ],
        'user_display' => [
            'name' => 'user_display',
            'type' => 'Text',
            'label' => 'User',
            'readOnly' => true,
            'isDBField' => false,
            'description' => 'Display name of the user',
            'validationRules' => [],
        ],
        'proposed_date_id' => [
            'name' => 'proposed_date_id',
            'type' => 'RelatedRecord',
            'label' => 'Proposed Date',
            'required' => true,
            'relatedModel' => 'Event_Proposed_Dates',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'proposed_date_display',
            'description' => 'The proposed date this commitment is for',
            'validationRules' => ['Required'],
        ],
        'proposed_date_display' => [
            'name' => 'proposed_date_display',
            'type' => 'Text',
            'label' => 'Proposed Date',
            'readOnly' => true,
            'isDBField' => false,
            'description' => 'Display value of the proposed date',
            'validationRules' => [],
        ],
        'is_available' => [
            'name' => 'is_available',
            'type' => 'Boolean',
            'label' => 'Available',
            'required' => true,
            'defaultValue' => false,
            'description' => 'Whether the user can attend on this date',
            'validationRules' => ['Required'],
        ],
    ],
    'rolesAndActions' => [
        'admin' => ['*'],
        'user' => ['create', 'read', 'update', 'list'],
        'guest' => [],
    ],
    'validationRules' => [],
    'relationships' => [],
    'apiRoutes' => [],
    'uniqueConstraints' => [
        'uniq_event_user_date' => ['event_id', 'user_id', 'proposed_date_id'],
    ],
    'ui' => [
        'listFields' => ['event_display', 'user_display', 'proposed_date_display', 'is_available'],
        'createFields' => ['event_id', 'user_id', 'proposed_date_id', 'is_available'],
        'editFields' => ['is_available'],
    ],
];
```

**Key decisions:**
- `displayColumns` uses `['event_display', 'user_display', 'is_available']` since a commitment record is identified by its event, user, and availability status.
- Three RelatedRecord fields (`event_id`, `user_id`, `proposed_date_id`) each with a virtual display field, following the pattern from `event_proposed_dates_metadata.php`.
- `is_available` uses `Boolean` type with `defaultValue => false` per spec.
- `rolesAndActions`: `user` gets `create, read, update, list` per spec. Row-level authorization (own-row editing) is enforced in the API controller (item 10), not in metadata. `guest` gets no access per spec.
- `uniqueConstraints` is a new metadata key (see SchemaGenerator enhancement below). It declares the composite unique index on (event_id, user_id, proposed_date_id).
- `editFields` only includes `is_available` because users should only toggle availability, not change which event/user/date the record belongs to.

### 2. OneToMany Relationship Metadata

**File**: `src/Relationships/events_event_commitments/events_event_commitments_metadata.php`

Follow the same structure as `src/Relationships/events_event_proposed_dates/events_event_proposed_dates_metadata.php` (from plan-04).

```php
<?php
// Events to Event_Commitments OneToMany relationship metadata
return [
    'name' => 'events_event_commitments',
    'type' => 'OneToMany',
    'modelOne' => 'Events',
    'modelMany' => 'Event_Commitments',
    'constraints' => [],
    'additionalFields' => [],
];
```

**Key decisions:**
- Follows the `events_event_proposed_dates` pattern exactly.
- The relationship name `events_event_commitments` matches what is already referenced in the Events model metadata `relationships` array (from plan-03).

### 3. EventCommitments Model Class

**File**: `src/Models/event_commitments/EventCommitments.php`

**Namespace**: `Gravitycar\Models\event_commitments`

**Extends**: `Gravitycar\Models\ModelBase`

This model overrides `validateForPersistence()` to enforce the composite unique constraint at the application level before database operations.

```php
<?php
namespace Gravitycar\Models\event_commitments;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

class EventCommitments extends ModelBase
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
     * Override to add composite unique constraint validation before create.
     */
    protected function validateForPersistence(): bool
    {
        if (!parent::validateForPersistence()) {
            return false;
        }

        return $this->validateUniqueCommitment();
    }

    /**
     * Ensure no existing record has the same (event_id, user_id, proposed_date_id).
     *
     * On create: checks if any non-deleted record with this combination exists.
     * On update: checks if any non-deleted record with this combination exists
     *            that is NOT the current record (by ID).
     */
    protected function validateUniqueCommitment(): bool
    {
        $eventId = $this->get('event_id');
        $userId = $this->get('user_id');
        $proposedDateId = $this->get('proposed_date_id');

        if (empty($eventId) || empty($userId) || empty($proposedDateId)) {
            return true; // Required validation handles missing fields
        }

        $existingId = $this->findExistingCommitment(
            $eventId,
            $userId,
            $proposedDateId
        );

        if ($existingId === null) {
            return true;
        }

        $currentId = $this->get('id');
        if ($currentId !== null && $existingId === $currentId) {
            return true; // Updating the same record is fine
        }

        $this->logger->warning('Duplicate commitment rejected', [
            'event_id' => $eventId,
            'user_id' => $userId,
            'proposed_date_id' => $proposedDateId,
        ]);

        throw new GCException(
            'A commitment already exists for this user on this proposed date',
            [
                'event_id' => $eventId,
                'user_id' => $userId,
                'proposed_date_id' => $proposedDateId,
            ]
        );
    }

    /**
     * Query for an existing commitment with the same ternary key.
     *
     * @return string|null The ID of the existing record, or null if none found.
     */
    protected function findExistingCommitment(
        string $eventId,
        string $userId,
        string $proposedDateId
    ): ?string {
        $results = $this->findRaw(
            [
                'event_id' => $eventId,
                'user_id' => $userId,
                'proposed_date_id' => $proposedDateId,
            ],
            ['id']
        );

        if (empty($results)) {
            return null;
        }

        return (string) $results[0]['id'];
    }
}
```

**Key decisions:**
- Overrides `validateForPersistence()` to call the parent validation first, then enforce the composite unique constraint.
- The `findExistingCommitment()` method uses `ModelBase::findRaw()` to query via the framework API rather than raw SQL.
- On update, the method allows updating the same record (checks `$currentId === $existingId`).
- Throws `GCException` with context fields for duplicate violations, consistent with the framework's error handling pattern.
- Soft-delete awareness: the query includes `deleted_at IS NULL` so soft-deleted records don't block new commitments.

### 4. SchemaGenerator Enhancement

**File**: `src/Schema/SchemaGenerator.php`

**What changes**: Add support for a `uniqueConstraints` key in model metadata. After creating columns in `createModelTable()`, read `uniqueConstraints` from metadata and call `$table->addUniqueIndex()` for each entry.

**Code to add** at the end of `createModelTable()` (after the primary key block, around line 209):

```php
// Add composite unique constraints from metadata
$uniqueConstraints = $modelMeta['uniqueConstraints'] ?? [];
foreach ($uniqueConstraints as $constraintName => $columns) {
    $table->addUniqueIndex($columns, $constraintName);
}
```

This is a small, focused change (4 lines). It also benefits any future model that needs composite unique constraints, making it a proper framework-level enhancement with more than one potential use case.

Also add the same logic in `updateModelTable()` so that constraints are applied when updating existing tables.

## Error Handling

- `validateUniqueCommitment()` throws `GCException` if a duplicate (event_id, user_id, proposed_date_id) combination is detected. The exception includes all three IDs for debugging context.
- If any of the three required fields are empty, the method returns `true` (passes) and lets the `Required` validation rule handle the error on those fields.
- The database-level unique index (from SchemaGenerator) provides a second layer of defense. If the application-level check is somehow bypassed, the database will reject the duplicate insert.
- Database exceptions from Doctrine DBAL propagate up to the caller (consistent with framework pattern).

## Unit Test Specifications

**File**: `tests/Models/EventCommitmentsTest.php`

**Namespace**: `Gravitycar\Tests\Models`

### Metadata Validation

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Metadata loads correctly | Load metadata file | Array with name='Event_Commitments' | Verify metadata file is valid PHP |
| Has required fields | Load metadata | Contains event_id, user_id, proposed_date_id, is_available | All spec fields present |
| event_id is RelatedRecord to Events | Load metadata | event_id type='RelatedRecord', relatedModel='Events' | Correct field type and target |
| user_id is RelatedRecord to Users | Load metadata | user_id type='RelatedRecord', relatedModel='Users' | Correct field type and target |
| proposed_date_id is RelatedRecord | Load metadata | proposed_date_id type='RelatedRecord', relatedModel='Event_Proposed_Dates' | Correct target |
| is_available is Boolean | Load metadata | is_available type='Boolean', defaultValue=false | Default false per spec |
| Correct roles | Load metadata | admin=['*'], user=['create','read','update','list'], guest=[] | Match spec roles |
| uniqueConstraints defined | Load metadata | uniqueConstraints has 'uniq_event_user_date' key with 3 columns | Composite unique declared |

### Model Instantiation

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Constructs successfully | Valid mocked dependencies | No exception thrown | Model can be created |
| Extends ModelBase | Instance check | instanceof ModelBase === true | Correct inheritance |

### Unique Constraint Validation (validateUniqueCommitment)

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Create with no existing record | Mock findExistingCommitment returns null | validateForPersistence returns true | Happy path: no duplicate |
| Create with existing duplicate | Mock findExistingCommitment returns 'existing-uuid' | Throws GCException | Duplicate rejected |
| Update same record (no conflict) | Current ID matches existing ID | validateForPersistence returns true | Self-update is allowed |
| Update with conflict from different record | Current ID differs from existing ID | Throws GCException | Different record has this combo |
| Missing required field skips check | event_id is empty | validateForPersistence returns true (Required rule handles it) | Null-safe early return |

### Key Scenario: Duplicate Commitment Rejected

**Setup**: Create EventCommitments instance with mocked dependencies. Mock `findRaw()` to return `[['id' => 'existing-uuid-123']]` (an existing record). Set model fields: event_id='evt-1', user_id='usr-1', proposed_date_id='pd-1'. Do NOT set the model's own ID (simulating a create).

**Action**: Call `validateForPersistence()` (via reflection or by calling `create()`).

**Expected**: Throws `GCException` with message "A commitment already exists for this user on this proposed date" and context containing all three IDs.

### Key Scenario: Self-Update Allowed

**Setup**: Create EventCommitments instance. Set model fields: id='commit-uuid-1', event_id='evt-1', user_id='usr-1', proposed_date_id='pd-1'. Mock `findRaw()` to return `[['id' => 'commit-uuid-1']]` (same ID).

**Action**: Call `validateForPersistence()`.

**Expected**: Returns `true` (no exception). The existing record has the same ID as the current record.

### Relationship Metadata Tests

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Relationship metadata loads | Load file | Array with name='events_event_commitments' | Valid PHP |
| Type is OneToMany | Load metadata | type='OneToMany' | Correct relationship type |
| Correct models | Load metadata | modelOne='Events', modelMany='Event_Commitments' | Links correct models |

### Test Helper Setup

```php
private function createEventCommitmentsModel(
    ?Connection $mockConnection = null
): EventCommitments {
    $logger = new Logger('test');
    $metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $fieldFactory = $this->createMock(FieldFactory::class);
    $databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    $relationshipFactory = $this->createMock(RelationshipFactory::class);
    $modelFactory = $this->createMock(ModelFactory::class);
    $currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

    $metadataEngine->method('getModelMetadata')
        ->willReturn(
            require __DIR__ . '/../../src/Models/event_commitments/event_commitments_metadata.php'
        );

    if ($mockConnection !== null) {
        $databaseConnector->method('getConnection')
            ->willReturn($mockConnection);
    }

    return new EventCommitments(
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

- The `events_event_commitments` relationship name is already referenced in the Events model metadata `relationships` array (from plan-03). Creating the relationship metadata file completes that forward reference.
- The `event_commitments` table will be auto-generated by SchemaGenerator from the metadata. Core fields (id, created_at, updated_at, deleted_at, created_by, updated_by, deleted_by) are added automatically.
- The `uniqueConstraints` metadata key is new to the framework. The SchemaGenerator enhancement (4 lines in `createModelTable`) is minimal and generic enough to benefit future models. The application-level check in `validateUniqueCommitment()` provides an additional defense layer with a user-friendly error message.
- Row-level authorization (users can only modify their own commitments, AC-15) is NOT implemented in this model class. It will be enforced by the CommitmentsController (catalog item 10) which checks that `user_id` matches the authenticated user. The model's `rolesAndActions` only governs action-level access.
- The Events model's `getMostPopularDates()` method (plan-03) and `fetchAvailabilityCounts()` query this model's table directly via DBAL. No PHP-level coupling between the two model classes.
- The Commitments API (item 10) will use the `findExistingCommitment()` method for its upsert logic (INSERT or UPDATE on the unique constraint). This method is `protected` but can be accessed by a subclass or the API controller can query directly.
