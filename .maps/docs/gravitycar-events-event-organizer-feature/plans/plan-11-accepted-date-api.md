# Implementation Plan: Accepted Date API Endpoint

## Spec Context

This plan covers the PUT /api/events/{event_id}/accepted-date endpoint. An admin sets the event's `accepted_date` field to a specified proposed date's datetime value. When `accepted_date` is set or changed, the endpoint triggers recalculation of `remind_at` on all preset reminders for the event, except those with status='sent'. This is the integration point between the Events model (item 3) and the Event_Reminders model (item 7).

- **Catalog item**: 11 - Accepted Date API Endpoint
- **Specification section**: API Endpoints -- PUT /api/events/{event_id}/accepted-date, Reminder Lifecycle under Event_Reminders
- **Acceptance criteria addressed**: AC-8 (admin can set accepted date), AC-19 (preset reminders recalculated on accepted_date change, custom and sent reminders unaffected)

## Dependencies

- **Blocked by**: Item 3 (Events Model) -- needs the events table and Events model class; Item 7 (Event_Reminders Model) -- needs the EventReminders model class with `recalculateRemindersForEvent()` method
- **Uses**: `src/Api/ApiControllerBase.php` (base class), `src/Api/Request.php` (request handling), `src/Models/events/Events.php`, `src/Models/event_reminders/EventReminders.php`, `src/Factories/ModelFactory.php`, exception classes from `src/Exceptions/`

## File Changes

### New Files

- `src/Models/events/api/Api/AcceptedDateController.php` -- Custom API controller for PUT accepted-date
- `Tests/Api/AcceptedDateControllerTest.php` -- Unit tests

### Modified Files

- None

## Implementation Details

### 1. AcceptedDateController

**File**: `src/Models/events/api/Api/AcceptedDateController.php`

**Namespace**: `Gravitycar\Models\events\api\Api`

**Extends**: `Gravitycar\Api\ApiControllerBase`

**Roles and Actions:**

```php
protected array $rolesAndActions = [
    'admin' => ['update'],
    'user' => [],
    'guest' => [],
];
```

Only admins can set the accepted date. All other roles are denied.

**Route Registration:**

```php
public function registerRoutes(): array
{
    return [
        [
            'method' => 'PUT',
            'path' => '/events/{event_id}/accepted-date',
            'apiClass' => self::class,
            'apiMethod' => 'setAcceptedDate',
            'parameterNames' => ['event_id'],
            'rbacAction' => 'update',
        ],
    ];
}
```

### 2. Authorization Logic

The endpoint is admin-only. The controller verifies the current user is authenticated and has the admin role.

```php
protected function requireAdmin(): string
{
    $currentUser = $this->getCurrentUser();
    if ($currentUser === null) {
        throw new UnauthorizedException(
            'Authentication required to set accepted date'
        );
    }

    $currentUserId = $currentUser->get('id');
    if (!$this->isUserAdmin($currentUserId)) {
        throw new ForbiddenException(
            'Only admins can set the accepted date',
            ['user_id' => $currentUserId]
        );
    }

    return $currentUserId;
}

protected function isUserAdmin(string $userId): bool
{
    $usersModel = $this->modelFactory->retrieve('Users', $userId);
    if ($usersModel === null) {
        return false;
    }
    $roleModels = $usersModel->getRelatedModels('users_roles');
    foreach ($roleModels as $roleModel) {
        if ($roleModel->get('name') === 'admin') {
            return true;
        }
    }
    return false;
}
```

### 3. PUT /api/events/{event_id}/accepted-date

**Method signature:**

```php
public function setAcceptedDate(Request $request): array
```

**Request body format:**

```json
{
    "proposed_date_id": "pd-uuid-123"
}
```

The endpoint resolves the `proposed_date_id` to its `proposed_date` datetime value, then sets that as the event's `accepted_date`.

**Logic:**

1. Extract `event_id` from URL via `$request->get('event_id')`.
2. Call `requireAdmin()` to verify the current user is an authenticated admin.
3. Validate the event exists and is not deleted.
4. Parse `proposed_date_id` from request body.
5. Validate the proposed date exists and belongs to this event.
6. Fetch the `proposed_date` datetime value from the Event_Proposed_Dates record.
7. Update the event's `accepted_date` field to that datetime value.
8. Call `EventReminders::recalculateRemindersForEvent()` to update preset reminders.
9. Return a success response with the new accepted_date and reminder recalculation count.

**Complete method:**

```php
public function setAcceptedDate(Request $request): array
{
    $eventId = $request->get('event_id');
    if (empty($eventId)) {
        throw new BadRequestException('Event ID is required');
    }

    $this->requireAdmin();

    $this->validateEventExists($eventId);

    $requestData = $request->getRequestData();
    $proposedDateId = $requestData['proposed_date_id'] ?? null;
    if (empty($proposedDateId) || !is_string($proposedDateId)) {
        throw new BadRequestException(
            'Request must include a valid "proposed_date_id" string'
        );
    }

    $acceptedDate = $this->resolveProposedDate($eventId, $proposedDateId);

    $this->updateEventAcceptedDate($eventId, $acceptedDate);

    $remindersUpdated = $this->recalculateReminders($eventId, $acceptedDate);

    $this->logger->info('Accepted date set for event', [
        'event_id' => $eventId,
        'proposed_date_id' => $proposedDateId,
        'accepted_date' => $acceptedDate,
        'reminders_recalculated' => $remindersUpdated,
    ]);

    return [
        'success' => true,
        'status' => 200,
        'data' => [
            'event_id' => $eventId,
            'accepted_date' => $acceptedDate,
            'reminders_recalculated' => $remindersUpdated,
        ],
        'timestamp' => date('c'),
    ];
}
```

### 4. Helper Methods

**Validate event exists:**

```php
protected function validateEventExists(string $eventId): void
{
    $eventsModel = $this->modelFactory->retrieve('Events', $eventId);
    if ($eventsModel === null) {
        throw new NotFoundException(
            'Event not found',
            ['event_id' => $eventId]
        );
    }
}
```

**Resolve proposed date to datetime value:**

```php
protected function resolveProposedDate(
    string $eventId,
    string $proposedDateId
): string {
    $proposedDatesModel = $this->modelFactory->new('Event_Proposed_Dates');
    $results = $proposedDatesModel->findRaw(
        ['id' => $proposedDateId, 'event_id' => $eventId],
        ['proposed_date']
    );

    if (empty($results)) {
        throw new BadRequestException(
            'Proposed date not found or does not belong to this event',
            ['proposed_date_id' => $proposedDateId, 'event_id' => $eventId]
        );
    }

    return $results[0]['proposed_date'];
}
```

**Update event's accepted_date:**

```php
protected function updateEventAcceptedDate(
    string $eventId,
    string $acceptedDate
): void {
    $eventsModel = $this->modelFactory->retrieve('Events', $eventId);
    $eventsModel->set('accepted_date', $acceptedDate);
    $eventsModel->update();
}
```

**Recalculate reminders via EventReminders model:**

```php
protected function recalculateReminders(
    string $eventId,
    string $newAcceptedDate
): int {
    $remindersModel = $this->modelFactory->create('Event_Reminders');
    return $remindersModel->recalculateRemindersForEvent(
        $eventId,
        $newAcceptedDate
    );
}
```

This method delegates to `EventReminders::recalculateRemindersForEvent()` from plan-07. That method handles:
- Fetching all reminders for the event
- Filtering to preset types (not custom) with status != 'sent'
- Recalculating `remind_at` based on the new accepted_date
- Returning the count of updated reminders

## Error Handling

- **Missing event_id**: Throws `BadRequestException` (HTTP 400).
- **Event not found**: Throws `NotFoundException` (HTTP 404) with event_id context.
- **Not authenticated**: Throws `UnauthorizedException` (HTTP 401).
- **Not admin**: Throws `ForbiddenException` (HTTP 403) with user_id context.
- **Missing or invalid proposed_date_id**: Throws `BadRequestException` (HTTP 400) with descriptive message.
- **Proposed date not found or wrong event**: Throws `BadRequestException` (HTTP 400) with both IDs in context.
- **Reminder recalculation failure**: `GCException` from `recalculateRemindersForEvent` is caught and logged. The accepted_date update still succeeds; reminder failure is non-fatal. Wrap the `recalculateReminders` call in a try/catch:

```php
try {
    $remindersUpdated = $this->recalculateReminders($eventId, $acceptedDate);
} catch (\Gravitycar\Exceptions\GCException $e) {
    $this->logger->error('Reminder recalculation failed', [
        'event_id' => $eventId,
        'error' => $e->getMessage(),
    ]);
    $remindersUpdated = -1;
}
```

- **Database errors**: Doctrine DBAL exceptions propagate to the framework's global exception handler (HTTP 500).
- All custom exceptions use framework-specific classes extending `GCException`.

## Unit Test Specifications

**File**: `Tests/Api/AcceptedDateControllerTest.php`

**Namespace**: `Gravitycar\Tests\Api`

### Route Registration

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Registers one route | Call registerRoutes() | Array with 1 entry | Single endpoint |
| PUT route correct | Check entry | PUT /events/{event_id}/accepted-date | Correct method+path |
| Admin-only roles | Check rolesAndActions | admin=>[update], user=>[], guest=>[] | Only admin access |

### Authorization

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Admin can set date | Admin user authenticated | Proceeds to set date | Admin allowed |
| Non-admin rejected | Authenticated user role | Throws ForbiddenException | AC-8 admin only |
| Guest rejected | currentUser is null | Throws UnauthorizedException | Must be authenticated |

### Input Validation

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Missing proposed_date_id | `{}` | Throws BadRequestException | Required field |
| Non-string proposed_date_id | `{"proposed_date_id": 123}` | Throws BadRequestException | Must be string |
| Empty proposed_date_id | `{"proposed_date_id": ""}` | Throws BadRequestException | Cannot be empty |
| Proposed date not in event | Valid UUID but wrong event_id | Throws BadRequestException | Must belong to event |
| Event not found | Non-existent event_id | Throws NotFoundException | Event must exist |

### Core Logic

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Sets accepted_date | Valid event + proposed date | events.accepted_date = proposed_date value | AC-8 |
| Returns accepted_date in response | Valid request | Response data includes accepted_date string | Confirm to caller |
| Triggers reminder recalculation | Event has preset reminders | recalculateRemindersForEvent called | AC-19 |
| Returns recalculation count | 2 reminders recalculated | reminders_recalculated = 2 | Inform admin |
| Handles no reminders | Event has no reminders | reminders_recalculated = 0 | Graceful no-op |

### Reminder Recalculation Integration (AC-19)

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Preset pending recalculated | 1_week reminder, status=pending | remind_at updated | Spec requirement |
| Preset failed recalculated | 2_weeks reminder, status=failed | remind_at updated | Failed can be retried |
| Sent reminders skipped | 1_day reminder, status=sent | remind_at unchanged | Spec: sent not recalculated |
| Custom reminders skipped | custom reminder, status=pending | remind_at unchanged | Spec: custom never recalculated |
| Recalculation error non-fatal | recalculateRemindersForEvent throws | accepted_date still set, error logged | Graceful degradation |

### Key Scenario: Set Accepted Date with Mixed Reminders

**Setup**: Create AcceptedDateController with mocked admin user. Mock event 'evt-1' exists. Mock proposed date 'pd-3' with proposed_date = '2026-07-15 19:00:00', belonging to evt-1. Mock EventReminders model via modelFactory returning 3 as the recalculated count.

**Action**: Call `setAcceptedDate()` with event_id='evt-1', body=`{"proposed_date_id": "pd-3"}`.

**Expected**:
- DB `update` called on events table: accepted_date = '2026-07-15 19:00:00'
- `recalculateRemindersForEvent('evt-1', '2026-07-15 19:00:00')` called once
- Response: `{success: true, data: {event_id: 'evt-1', accepted_date: '2026-07-15 19:00:00', reminders_recalculated: 3}}`

### Key Scenario: Non-Admin Rejected

**Setup**: Mock non-admin user 'usr-5'. Mock event 'evt-1' exists.

**Action**: Call `setAcceptedDate()` with event_id='evt-1'.

**Expected**: Throws `ForbiddenException` before any DB writes. No event record modified, no reminder recalculation triggered.

### Test Helper Setup

```php
private function createAcceptedDateController(
    ?object $mockCurrentUser = null,
    ?object $mockConnection = null,
    ?object $mockModelFactory = null
): AcceptedDateController {
    $logger = new Logger('test');
    $modelFactory = $mockModelFactory ?? $this->createMock(ModelFactory::class);
    $databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    if ($mockConnection !== null) {
        $databaseConnector->method('getConnection')
            ->willReturn($mockConnection);
    }
    $metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $config = $this->createMock(Config::class);
    $currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

    if ($mockCurrentUser !== null) {
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($mockCurrentUser);
    } else {
        $currentUserProvider->method('getCurrentUser')
            ->willReturn(null);
    }

    return new AcceptedDateController(
        $logger,
        $modelFactory,
        $databaseConnector,
        $metadataEngine,
        $config,
        $currentUserProvider
    );
}
```

## Notes

- The endpoint resolves `proposed_date_id` to an actual datetime value rather than storing the proposed_date_id directly. The spec says "sets the accepted_date field on the event to a specified proposed_date_id's datetime value." This means `accepted_date` stores the datetime, not a foreign key.
- The `isUserAdmin` helper uses the framework's `ModelBase::getRelatedModels()` to fetch roles via the `users_roles` relationship, matching the pattern in CommitmentsController (plan-10). Consider extracting this into a shared trait or base class during implementation if the pattern is used in 3+ controllers.
- Reminder recalculation is treated as non-fatal. If `recalculateRemindersForEvent` throws, the accepted_date update is already committed. The error is logged and the response includes `reminders_recalculated: -1` to signal the issue. This avoids wrapping both operations in a transaction, which would couple event updates to reminder updates.
- All datetime values are stored in UTC per spec (AC-20). The `proposed_date` value fetched from event_proposed_dates is already in UTC.
- The controller is auto-discovered by `APIRouteRegistry` because it lives in `src/Models/events/api/Api/` and extends `ApiControllerBase`.
