# Implementation Plan: Event_Proposed_Dates Model

## Spec Context

The Event_Proposed_Dates model stores candidate date/time options that users vote on for an event. Each record links a proposed datetime to a parent event via a RelatedRecord field. This model is the child side of the `events_event_proposed_dates` OneToMany relationship. It is a prerequisite for Event_Commitments (item 6), the Chart API (item 9), and the Event Admin Pages (item 17).

- **Catalog item**: 4 - Event_Proposed_Dates Model
- **Specification section**: Models section -- Event_Proposed_Dates
- **Acceptance criteria addressed**: AC-2 (admin can add and remove proposed date/times for an event)

## Dependencies

- **Blocked by**: Item 3 (Events Model) -- the `event_id` RelatedRecord field references the Events model, and the `events_event_proposed_dates` relationship references both models.
- **Uses**: `src/Core/ModelBase.php` (base class), `src/Models/events/events_metadata.php` (referenced by event_id field), `src/Models/movie_quote_trivia_questions/movie_quote_trivia_questions_metadata.php` (pattern reference for RelatedRecord fields)

## File Changes

### New Files

- `src/Models/event_proposed_dates/event_proposed_dates_metadata.php` -- Metadata definition for Event_Proposed_Dates
- `src/Models/event_proposed_dates/EventProposedDates.php` -- Model class extending ModelBase
- `src/Relationships/events_event_proposed_dates/events_event_proposed_dates_metadata.php` -- OneToMany relationship metadata
- `tests/Models/EventProposedDatesTest.php` -- Unit tests

### Modified Files

None.

## Implementation Details

### 1. Event_Proposed_Dates Metadata

**File**: `src/Models/event_proposed_dates/event_proposed_dates_metadata.php`

Follow the same structure as `src/Models/movie_quote_trivia_questions/movie_quote_trivia_questions_metadata.php` for the RelatedRecord field pattern.

```php
<?php
// Event_Proposed_Dates model metadata for Gravitycar framework
return [
    'name' => 'Event_Proposed_Dates',
    'table' => 'event_proposed_dates',
    'displayColumns' => ['proposed_date'],
    'fields' => [
        'event_id' => [
            'name' => 'event_id',
            'type' => 'RelatedRecord',
            'label' => 'Event',
            'required' => true,
            'relatedModel' => 'Events',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'event_display',
            'description' => 'The parent event this proposed date belongs to',
            'validationRules' => ['Required'],
        ],
        'event_display' => [
            'name' => 'event_display',
            'type' => 'Text',
            'label' => 'Event Name',
            'readOnly' => true,
            'isDBField' => false,
            'description' => 'Display name of the parent event',
            'validationRules' => [],
        ],
        'proposed_date' => [
            'name' => 'proposed_date',
            'type' => 'DateTime',
            'label' => 'Proposed Date',
            'required' => true,
            'description' => 'The candidate date and time for this event',
            'validationRules' => ['Required', 'DateTime'],
        ],
    ],
    'rolesAndActions' => [
        'admin' => ['*'],
        'user' => ['list', 'read'],
        'guest' => ['list', 'read'],
    ],
    'validationRules' => [],
    'relationships' => [],
    'apiRoutes' => [],
    'ui' => [
        'listFields' => ['event_display', 'proposed_date'],
        'createFields' => ['event_id', 'proposed_date'],
        'editFields' => ['event_id', 'proposed_date'],
    ],
];
```

**Key decisions:**
- `displayColumns` is `['proposed_date']` since the datetime is the primary identifier for a proposed date record.
- `event_id` uses `RelatedRecord` type with `relatedModel => 'Events'`, following the exact pattern from `core_fields_metadata.php` (created_by) and `movie_quote_trivia_questions_metadata.php` (movie_quote_id).
- `event_display` is a virtual (non-DB) text field that displays the event name, following the `displayFieldName` pattern from core fields.
- `guest` role gets `list` and `read` per spec (public read access).
- `relationships` is empty -- this model does not own any child relationships.

### 2. OneToMany Relationship Metadata

**File**: `src/Relationships/events_event_proposed_dates/events_event_proposed_dates_metadata.php`

Follow the same structure as `src/Relationships/movies_movie_quotes/movies_movie_quotes_metadata.php`.

```php
<?php
// Events to Event_Proposed_Dates OneToMany relationship metadata
return [
    'name' => 'events_event_proposed_dates',
    'type' => 'OneToMany',
    'modelOne' => 'Events',
    'modelMany' => 'Event_Proposed_Dates',
    'constraints' => [],
    'additionalFields' => [],
];
```

**Key decisions:**
- Follows the existing `movies_movie_quotes` pattern exactly.
- `additionalFields` is empty because this is a simple parent-child relationship with no extra pivot data.
- The relationship name `events_event_proposed_dates` matches what is already referenced in the Events model metadata `relationships` array (from plan-03).

### 3. EventProposedDates Model Class

**File**: `src/Models/event_proposed_dates/EventProposedDates.php`

**Namespace**: `Gravitycar\Models\event_proposed_dates`

**Extends**: `Gravitycar\Models\ModelBase`

This is a straightforward model with no custom business logic. The class only needs the standard 7-param constructor.

```php
<?php
namespace Gravitycar\Models\event_proposed_dates;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * EventProposedDates model class for Gravitycar framework.
 *
 * Represents a candidate date/time option for an event.
 * Users vote on these proposed dates via Event_Commitments.
 */
class EventProposedDates extends ModelBase
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
}
```

**Key decisions:**
- No custom methods needed. All CRUD is handled by ModelBase.
- Class name is `EventProposedDates` (PascalCase, no underscores) following the naming convention where the class name is the PascalCase version of the model name.
- No `$rolesAndActions` property override needed -- the metadata file defines roles and the framework reads them from there.

## Error Handling

- Field validation is handled by ModelBase using the `validationRules` defined in metadata (`Required`, `DateTime`).
- If `event_id` references a non-existent Events record, the framework's RelatedRecord field validation will reject it.
- Database exceptions from Doctrine DBAL propagate up to the caller (consistent with framework pattern).

## Unit Test Specifications

**File**: `tests/Models/EventProposedDatesTest.php`

Tests will mock `DatabaseConnectorInterface` and other dependencies. The model is instantiated with mocked dependencies following the 7-param DI pattern.

### Metadata Validation

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Metadata loads correctly | Load metadata file | Array with name='Event_Proposed_Dates' | Verify metadata file is valid PHP |
| Has required fields | Load metadata | Contains 'event_id' and 'proposed_date' keys | Core fields must exist |
| event_id is RelatedRecord | Load metadata | event_id type='RelatedRecord', relatedModel='Events' | Correct field type and target |
| proposed_date is DateTime | Load metadata | proposed_date type='DateTime', required=true | Correct field type |
| Correct roles | Load metadata | admin=['*'], user=['list','read'], guest=['list','read'] | Match spec roles |
| displayColumns set | Load metadata | displayColumns=['proposed_date'] | Primary display field |

### Model Instantiation

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Constructs successfully | Valid mocked dependencies | No exception thrown | Model can be created |
| Extends ModelBase | Instance check | instanceof ModelBase === true | Correct inheritance |

### Relationship Metadata

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Relationship metadata loads | Load relationship metadata file | Array with name='events_event_proposed_dates' | Valid PHP |
| Correct type | Load relationship metadata | type='OneToMany' | Correct relationship type |
| Correct models | Load relationship metadata | modelOne='Events', modelMany='Event_Proposed_Dates' | Links correct models |

### Key Scenario: Metadata Field Structure

**Setup**: Load the metadata file via `require`.
**Action**: Inspect the `fields` array.
**Expected**:
- `event_id` has keys: name, type, label, required, relatedModel, relatedFieldName, displayFieldName, description, validationRules.
- `proposed_date` has keys: name, type, label, required, description, validationRules.
- `event_display` has `isDBField => false` and `readOnly => true`.

### Key Scenario: Model Construction with Mocks

**Setup**:
```php
private function createEventProposedDatesModel(): EventProposedDates
{
    $logger = new Logger('test');
    $metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $fieldFactory = $this->createMock(FieldFactory::class);
    $databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    $relationshipFactory = $this->createMock(RelationshipFactory::class);
    $modelFactory = $this->createMock(ModelFactory::class);
    $currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

    $metadataEngine->method('getModelMetadata')
        ->willReturn(
            require __DIR__ . '/../../src/Models/event_proposed_dates/event_proposed_dates_metadata.php'
        );

    return new EventProposedDates(
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
**Action**: Call `createEventProposedDatesModel()`.
**Expected**: Returns an `EventProposedDates` instance without throwing.

## Notes

- The `events_event_proposed_dates` relationship name is already referenced in the Events model metadata `relationships` array (defined in plan-03). Creating this relationship metadata file completes that forward reference.
- The `event_proposed_dates` table will be auto-generated by SchemaGenerator from the metadata. Core fields (id, created_at, updated_at, deleted_at, created_by, updated_by, deleted_by) are added automatically.
- This model has no custom business logic -- all behavior comes from ModelBase. The Event_Commitments model (item 6) will reference this model's records via a RelatedRecord field.
- The Events model's `getMostPopularDates()` method (plan-03) queries this model's table directly. No coupling at the PHP class level.
