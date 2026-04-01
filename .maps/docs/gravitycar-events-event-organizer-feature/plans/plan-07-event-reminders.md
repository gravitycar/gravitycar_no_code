# Implementation Plan: Event_Reminders Model

## Spec Context

The Event_Reminders model stores scheduled email reminders for events. Admins create reminders at preset intervals (2 weeks, 1 week, 1 day before accepted_date) or at a custom date/time. For preset types, `remind_at` is auto-calculated from the event's `accepted_date`. When `accepted_date` changes, all preset reminders (except those already sent) are recalculated. Custom reminders are never recalculated. A background cron job (catalog item 14) will process these reminders.

- **Catalog item**: 7 - Event_Reminders Model
- **Specification section**: Models section -- Event_Reminders, Email Reminders section
- **Acceptance criteria addressed**: AC-10 (admin can create reminders with auto-calculated remind_at), AC-19 (preset reminders recalculated on accepted_date change, custom unaffected)

## Dependencies

- **Blocked by**: Item 3 (Events Model) -- needs the Events model and its `accepted_date` field
- **Uses**: `src/Models/ModelBase.php` (base class), `src/Models/events/Events.php` (to fetch accepted_date for auto-calculation), `Gravitycar\Factories\ModelFactory` (to instantiate Events model for lookups)
- **Blocks**: Item 11 (Accepted Date API Endpoint), Item 14 (Email Reminder Cron Job)

## File Changes

### New Files

- `src/Models/event_reminders/event_reminders_metadata.php` -- Metadata definition for Event_Reminders
- `src/Models/event_reminders/EventReminders.php` -- Model class with reminder lifecycle logic
- `src/Relationships/events_event_reminders/events_event_reminders_metadata.php` -- OneToMany relationship metadata (Events -> Event_Reminders)
- `tests/Models/EventRemindersTest.php` -- Unit tests

### Modified Files

None.

## Implementation Details

### 1. Event_Reminders Metadata

**File**: `src/Models/event_reminders/event_reminders_metadata.php`

Follow the exact structure of `src/Models/users/users_metadata.php` and `src/Models/movies/movies_metadata.php`.

```php
<?php
// Event_Reminders model metadata for Gravitycar framework
return [
    'name' => 'Event_Reminders',
    'table' => 'event_reminders',
    'displayColumns' => ['reminder_type', 'status'],
    'fields' => [
        'event_id' => [
            'name' => 'event_id',
            'type' => 'RelatedRecord',
            'label' => 'Event',
            'required' => true,
            'relatedModel' => 'Events',
            'validationRules' => ['Required'],
        ],
        'reminder_type' => [
            'name' => 'reminder_type',
            'type' => 'Enum',
            'label' => 'Reminder Type',
            'required' => true,
            'options' => [
                '2_weeks' => '2 Weeks Before',
                '1_week' => '1 Week Before',
                '1_day' => '1 Day Before',
                'custom' => 'Custom Date/Time',
            ],
            'validationRules' => ['Required', 'Options'],
        ],
        'remind_at' => [
            'name' => 'remind_at',
            'type' => 'DateTime',
            'label' => 'Remind At',
            'required' => false,
            'nullable' => true,
            'description' => 'Auto-calculated for preset types; manually set for custom',
            'validationRules' => ['DateTime'],
        ],
        'sent_at' => [
            'name' => 'sent_at',
            'type' => 'DateTime',
            'label' => 'Sent At',
            'required' => false,
            'nullable' => true,
            'readOnly' => true,
            'validationRules' => ['DateTime'],
        ],
        'status' => [
            'name' => 'status',
            'type' => 'Enum',
            'label' => 'Status',
            'required' => true,
            'defaultValue' => 'pending',
            'options' => [
                'pending' => 'Pending',
                'sent' => 'Sent',
                'failed' => 'Failed',
            ],
            'validationRules' => ['Required', 'Options'],
        ],
    ],
    'rolesAndActions' => [
        'admin' => ['*'],
        'user' => ['list', 'read'],
        'guest' => [],
    ],
    'validationRules' => [],
    'relationships' => ['events_event_reminders'],
    'ui' => [
        'listFields' => ['event_id', 'reminder_type', 'remind_at', 'status', 'sent_at'],
        'createFields' => ['event_id', 'reminder_type', 'remind_at'],
        'editFields' => ['event_id', 'reminder_type', 'remind_at', 'status'],
    ],
];
```

**Key metadata decisions:**
- `remind_at` is nullable (NULL when no accepted_date set for preset types)
- `sent_at` is readOnly (set programmatically by the cron job, not by the user)
- `status` defaults to "pending"
- Only `createFields` includes `remind_at` because for preset types it will be auto-calculated (but UI should show it for custom type)

### 2. Events-Event_Reminders Relationship Metadata

**File**: `src/Relationships/events_event_reminders/events_event_reminders_metadata.php`

Follow the same structure as `src/Relationships/events_event_proposed_dates/events_event_proposed_dates_metadata.php` (from plan-04).

```php
<?php
// Events to Event_Reminders OneToMany relationship metadata
return [
    'name' => 'events_event_reminders',
    'type' => 'OneToMany',
    'modelOne' => 'Events',
    'modelMany' => 'Event_Reminders',
    'constraints' => [],
    'additionalFields' => [],
];
```

**Key decisions:**
- Follows the existing `events_event_proposed_dates` pattern exactly.
- `additionalFields` is empty because this is a simple parent-child relationship with no extra pivot data.
- The relationship name `events_event_reminders` matches what is already referenced in the Events model metadata `relationships` array (from plan-03).

### 3. EventReminders Model Class

**File**: `src/Models/event_reminders/EventReminders.php`

**Namespace**: `Gravitycar\Models\event_reminders`

**Extends**: `Gravitycar\Models\ModelBase`

**Constants:**
```php
private const REMINDER_TYPE_OFFSETS = [
    '2_weeks' => 14,  // days
    '1_week'  => 7,
    '1_day'   => 1,
];

private const PRESET_REMINDER_TYPES = ['2_weeks', '1_week', '1_day'];
```

**Constructor**: Standard 7-param DI pattern (same as Movies), calls `parent::__construct(...)`.

**Key Methods:**

#### `create(): bool`

Override `ModelBase::create()`. Before calling `parent::create()`:
1. Read `reminder_type` from the instance.
2. If `reminder_type` is a preset type (in `PRESET_REMINDER_TYPES`):
   - Fetch the parent event's `accepted_date` via `fetchEventAcceptedDate()`.
   - If `accepted_date` is not null, call `calculateRemindAt()` and set `remind_at`.
   - If `accepted_date` is null, ensure `remind_at` is null.
3. If `reminder_type` is "custom", leave `remind_at` as-is (admin must have set it).
4. Call `parent::create()`.

```php
public function create(): bool
{
    $reminderType = $this->get('reminder_type');

    if (in_array($reminderType, self::PRESET_REMINDER_TYPES, true)) {
        $acceptedDate = $this->fetchEventAcceptedDate();
        $remindAt = $this->calculateRemindAt($reminderType, $acceptedDate);
        $this->set('remind_at', $remindAt);
    }

    return parent::create();
}
```

#### `calculateRemindAt(string $reminderType, ?string $acceptedDate): ?string`

Pure calculation method. Returns null if `$acceptedDate` is null.

```php
public function calculateRemindAt(string $reminderType, ?string $acceptedDate): ?string
{
    if ($acceptedDate === null) {
        return null;
    }

    if (!isset(self::REMINDER_TYPE_OFFSETS[$reminderType])) {
        $this->logger->warning('Unknown preset reminder type', ['type' => $reminderType]);
        return null;
    }

    $offsetDays = self::REMINDER_TYPE_OFFSETS[$reminderType];
    $date = new \DateTimeImmutable($acceptedDate, new \DateTimeZone('UTC'));
    $remindDate = $date->modify("-{$offsetDays} days");

    return $remindDate->format('Y-m-d H:i:s');
}
```

#### `fetchEventAcceptedDate(): ?string`

Fetches the parent event's `accepted_date`. Uses `ModelFactory` to load the Events model, then reads the record by `event_id`.

```php
private function fetchEventAcceptedDate(): ?string
{
    $eventId = $this->get('event_id');
    if (empty($eventId)) {
        return null;
    }

    $eventsModel = $this->modelFactory->create('Events');
    $eventData = $eventsModel->read($eventId);

    if (empty($eventData)) {
        $this->logger->warning('Event not found for reminder', ['event_id' => $eventId]);
        return null;
    }

    return $eventData['accepted_date'] ?? null;
}
```

#### `recalculateRemindersForEvent(string $eventId, ?string $newAcceptedDate): int`

**Static-like method called by the Accepted Date API endpoint (item 11).** Fetches all reminders for the given event, filters to preset types with status != "sent", and recalculates their `remind_at`.

```php
public function recalculateRemindersForEvent(string $eventId, ?string $newAcceptedDate): int
{
    $allReminders = $this->listByEventId($eventId);
    $updatedCount = 0;

    foreach ($allReminders as $reminderData) {
        if (!$this->shouldRecalculate($reminderData)) {
            continue;
        }

        $newRemindAt = $this->calculateRemindAt(
            $reminderData['reminder_type'],
            $newAcceptedDate
        );

        $this->loadRecord($reminderData['id']);
        $this->set('remind_at', $newRemindAt);
        $this->update();
        $updatedCount++;
    }

    $this->logger->info('Recalculated reminders for event', [
        'event_id' => $eventId,
        'updated_count' => $updatedCount,
        'new_accepted_date' => $newAcceptedDate,
    ]);

    return $updatedCount;
}
```

#### `shouldRecalculate(array $reminderData): bool`

Returns true if the reminder is a preset type AND status is not "sent".

```php
private function shouldRecalculate(array $reminderData): bool
{
    if ($reminderData['status'] === 'sent') {
        return false;
    }

    if (!in_array($reminderData['reminder_type'], self::PRESET_REMINDER_TYPES, true)) {
        return false;
    }

    return true;
}
```

#### `listByEventId(string $eventId): array`

Queries all reminders for a given event_id. Uses DBAL query builder via `$this->databaseConnector`.

```php
public function listByEventId(string $eventId): array
{
    return $this->findRaw(['event_id' => $eventId]);
}
```

#### `loadRecord(string $id): void`

Loads a reminder record into the model instance by ID (uses `ModelBase::read()` and sets fields).

```php
private function loadRecord(string $id): void
{
    $data = $this->read($id);
    if (empty($data)) {
        throw new \Gravitycar\Exceptions\GCException("Reminder not found: {$id}");
    }
    foreach ($data as $fieldName => $value) {
        if ($this->hasField($fieldName)) {
            $this->set($fieldName, $value);
        }
    }
}
```

## Error Handling

- **Event not found**: `fetchEventAcceptedDate()` logs a warning and returns null; reminder is created with `remind_at = null`.
- **Unknown reminder type**: `calculateRemindAt()` logs a warning and returns null.
- **Reminder not found during recalculation**: `loadRecord()` throws `GCException`. The `recalculateRemindersForEvent` caller (Accepted Date endpoint) should catch and log.
- **Database errors**: Doctrine DBAL exceptions propagate up; logged by the calling layer.

## Unit Test Specifications

**File**: `tests/Models/EventRemindersTest.php`

### `calculateRemindAt()`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| 2 weeks preset | type="2_weeks", accepted="2026-06-15 19:00:00" | "2026-06-01 19:00:00" | 14 days before |
| 1 week preset | type="1_week", accepted="2026-06-15 19:00:00" | "2026-06-08 19:00:00" | 7 days before |
| 1 day preset | type="1_day", accepted="2026-06-15 19:00:00" | "2026-06-14 19:00:00" | 1 day before |
| Null accepted_date | type="2_weeks", accepted=null | null | No date to calculate from |
| Custom type | type="custom", accepted="2026-06-15 19:00:00" | null | Custom type not in offsets map |

### `shouldRecalculate()`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Preset pending | type="1_week", status="pending" | true | Should recalculate |
| Preset failed | type="2_weeks", status="failed" | true | Failed reminders should recalculate |
| Preset sent | type="1_day", status="sent" | false | Already sent, skip |
| Custom pending | type="custom", status="pending" | false | Custom never recalculated |
| Custom sent | type="custom", status="sent" | false | Custom + sent, doubly skip |

### `create()` override

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Preset with accepted_date | type="1_week", event has accepted_date | remind_at auto-calculated | Auto-calc on create |
| Preset without accepted_date | type="2_weeks", event has no accepted_date | remind_at is null | No date available yet |
| Custom type | type="custom", remind_at="2026-06-10 10:00:00" | remind_at unchanged | Custom keeps explicit value |

### `recalculateRemindersForEvent()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Mixed reminders | 3 preset pending + 1 custom + 1 sent | Returns 3, only preset pending updated | Sent and custom skipped |
| All sent | 2 preset sent | Returns 0 | Nothing to recalculate |
| No reminders | Empty event | Returns 0 | Graceful no-op |
| Null new accepted_date | 2 preset pending, newAccepted=null | remind_at set to null on both | Accepted date cleared |

### Key Scenario: Accepted Date Change Recalculation

**Setup**: Create an event with `accepted_date = "2026-07-01 18:00:00"`. Create 3 reminders:
  - R1: type="2_weeks", status="pending" (remind_at = 2026-06-17 18:00:00)
  - R2: type="1_week", status="sent" (remind_at = 2026-06-24 18:00:00, sent_at set)
  - R3: type="custom", status="pending" (remind_at = 2026-06-25 12:00:00)

**Action**: Call `recalculateRemindersForEvent(eventId, "2026-07-15 18:00:00")`

**Expected**:
  - R1 remind_at updated to "2026-07-01 18:00:00" (14 days before new date)
  - R2 remind_at unchanged at "2026-06-24 18:00:00" (status=sent, skipped)
  - R3 remind_at unchanged at "2026-06-25 12:00:00" (custom type, skipped)
  - Return value: 1

## Notes

- All DateTime values are stored in UTC per the spec (AC-20). The `calculateRemindAt` method explicitly uses `DateTimeZone('UTC')`.
- The `recalculateRemindersForEvent()` method is the primary integration point for catalog item 11 (Accepted Date API Endpoint). That endpoint will call this method after updating the event's `accepted_date`.
- The cron job (catalog item 14) will query for reminders where `status = 'pending'` AND `remind_at IS NOT NULL` AND `remind_at <= NOW()`. This model does not implement cron logic -- it only provides the data and recalculation methods.
- The spec mentions "cancelled" as a possible status value in the task description, but the actual spec section for Event_Reminders lists only "pending", "sent", "failed". This plan follows the spec. If "cancelled" is needed later, it can be added to the Enum options.
