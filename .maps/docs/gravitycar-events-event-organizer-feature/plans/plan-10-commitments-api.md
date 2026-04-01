# Implementation Plan: Commitments API Endpoints (Upsert + Accept All)

## Spec Context

This plan covers two custom API endpoints for managing user commitments on events. The PUT endpoint allows per-cell upsert of individual commitment records (INSERT or UPDATE on the unique constraint), and the POST accept-all endpoint sets `is_available = true` for every proposed date on an event in a single action. Both endpoints enforce invitation-gated access (only invited users or admins) and own-row authorization (users can only toggle their own checkboxes). The spec explicitly prohibits the delete-all-then-reinsert pattern; per-cell upsert is required.

- **Catalog item**: 10 - Commitments API Endpoints (Upsert + Accept All)
- **Specification section**: API Endpoints -- PUT /api/events/{event_id}/commitments, POST /api/events/{event_id}/accept-all, Authorization section
- **Acceptance criteria addressed**: AC-5 (user can toggle availability, changes persist), AC-6 (accept-all marks all dates available), AC-15 (users cannot modify another user's commitments)

## Dependencies

- **Blocked by**: Item 6 (Event_Commitments Model) -- needs the event_commitments table with the composite unique constraint, and the EventCommitments model class
- **Uses**: `src/Api/ApiControllerBase.php` (base class), `src/Models/events/api/Api/ChartController.php` (reuses `isUserInvited` and `getUserRoles` pattern from plan-09), database tables for events, event_proposed_dates, events_users_invitations, event_commitments

## File Changes

### New Files

- `src/Models/events/api/Api/CommitmentsController.php` -- Custom API controller for PUT upsert and POST accept-all
- `tests/Api/CommitmentsControllerTest.php` -- Unit tests

### Modified Files

- None

## Implementation Details

### 1. CommitmentsController

**File**: `src/Models/events/api/Api/CommitmentsController.php`

**Namespace**: `Gravitycar\Models\events\api\Api`

**Extends**: `Gravitycar\Api\ApiControllerBase`

**Route Registration:**

```php
public function registerRoutes(): array
{
    return [
        [
            'method' => 'PUT',
            'path' => '/events/{event_id}/commitments',
            'apiClass' => self::class,
            'apiMethod' => 'upsertCommitments',
            'parameterNames' => ['event_id'],
            'rbacAction' => 'update',
        ],
        [
            'method' => 'POST',
            'path' => '/events/{event_id}/accept-all',
            'apiClass' => self::class,
            'apiMethod' => 'acceptAll',
            'parameterNames' => ['event_id'],
            'rbacAction' => 'create',
        ],
    ];
}
```

**Roles and Actions:**

```php
protected array $rolesAndActions = [
    'admin' => ['update', 'create'],
    'user' => ['update', 'create'],
    'guest' => [],
];
```

Only authenticated users (admin or user) can access these endpoints. Guests are excluded entirely.

### 2. Shared Authorization Logic

Both endpoints share the same authorization pattern: verify the event exists, verify the current user is authenticated, verify the user is invited (or is admin). This is extracted into a single helper.

```php
/**
 * Validate that the current user can modify commitments for this event.
 * Returns the event ID and the current user's ID.
 *
 * @throws BadRequestException If event_id is missing
 * @throws NotFoundException If event does not exist
 * @throws UnauthorizedException If user is not authenticated
 * @throws ForbiddenException If user is not invited and not admin
 */
protected function validateCommitmentAccess(string $eventId): array
{
    // 1. Verify event exists using ModelFactory
    $eventsModel = $this->modelFactory->retrieve('Events', $eventId);
    if ($eventsModel === null) {
        throw new NotFoundException(
            'Event not found',
            ['event_id' => $eventId]
        );
    }

    // 2. Require authentication
    $currentUser = $this->getCurrentUser();
    if ($currentUser === null) {
        throw new UnauthorizedException(
            'Authentication required to modify commitments'
        );
    }
    $currentUserId = $currentUser->get('id');

    // 3. Check admin bypass or invitation-gated access
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

    return [
        'eventId' => $eventId,
        'currentUserId' => $currentUserId,
        'isAdmin' => $isAdmin,
    ];
}
```

**Helper methods** (same pattern as ChartController from plan-09):

```php
protected function isUserInvited(string $eventId, string $userId): bool
{
    $eventsModel = $this->modelFactory->create('Events');
    $eventsModel->findById($eventId);

    $usersModel = $this->modelFactory->create('Users');
    $usersModel->findById($userId);

    return $eventsModel->hasRelation('events_users_invitations', $usersModel);
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

**Key decision**: The `isUserInvited` method uses the framework's `ModelBase::hasRelation()` to check invitation status instead of raw SQL against the join table. The `RelationshipBase` class handles all table/column naming internally.

### 3. PUT /api/events/{event_id}/commitments -- Upsert

**Method signature:**

```php
public function upsertCommitments(Request $request): array
```

**Request body format:**

```json
{
    "commitments": [
        { "proposed_date_id": "pd-uuid-1", "is_available": true },
        { "proposed_date_id": "pd-uuid-2", "is_available": false }
    ]
}
```

**Logic:**

1. Extract `event_id` from the URL path via `$request->get('event_id')`.
2. Call `validateCommitmentAccess($eventId)` to get the authenticated user's ID and verify access.
3. Parse `commitments` array from request body via `$request->getRequestData()`.
4. Validate the input: `commitments` must be a non-empty array, each entry must have `proposed_date_id` (string) and `is_available` (boolean).
5. Validate all `proposed_date_id` values belong to this event (batch query).
6. For each commitment entry, perform per-cell upsert using DBAL.
7. Return the count of updated and created records.

**Input Validation:**

```php
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
```

**Proposed Date Validation:**

```php
protected function validateProposedDatesExist(
    string $eventId,
    array $proposedDateIds
): void {
    $proposedDatesModel = $this->modelFactory->new('Event_Proposed_Dates');

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
```

**Per-Cell Upsert Logic:**

```php
protected function upsertSingleCommitment(
    string $eventId,
    string $userId,
    string $proposedDateId,
    bool $isAvailable
): string {
    $commitmentsModel = $this->modelFactory->new('Event_Commitments');

    // Check for existing record using framework find
    $existing = $commitmentsModel->findFirst([
        'event_id' => $eventId,
        'user_id' => $userId,
        'proposed_date_id' => $proposedDateId,
    ]);

    if ($existing !== null) {
        // UPDATE existing record
        $existing->set('is_available', $isAvailable);
        $existing->update();
        return 'updated';
    }

    // INSERT new record
    $newCommitment = $this->modelFactory->new('Event_Commitments');
    $newCommitment->set('event_id', $eventId);
    $newCommitment->set('user_id', $userId);
    $newCommitment->set('proposed_date_id', $proposedDateId);
    $newCommitment->set('is_available', $isAvailable);
    $newCommitment->create();
    return 'created';
}
```

**Key decision**: The upsert is done per-cell via SELECT-then-INSERT/UPDATE rather than MySQL's `INSERT ... ON DUPLICATE KEY UPDATE`. This avoids coupling to MySQL-specific syntax and works with Doctrine DBAL's portable API. The unique constraint at the DB level is a safety net; the application-level check is the primary mechanism.

**Complete upsertCommitments method:**

```php
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
```

### 4. POST /api/events/{event_id}/accept-all

**Method signature:**

```php
public function acceptAll(Request $request): array
```

**Logic:**

1. Extract `event_id` from URL, validate access (same as upsert).
2. Fetch all proposed_date_ids for the event.
3. For each proposed date, upsert a commitment with `is_available = true` using the same `upsertSingleCommitment` helper.
4. Return the count of updated/created records.

```php
public function acceptAll(Request $request): array
{
    $eventId = $request->get('event_id');
    if (empty($eventId)) {
        throw new BadRequestException('Event ID is required');
    }

    $accessInfo = $this->validateCommitmentAccess($eventId);
    $userId = $accessInfo['currentUserId'];

    // Fetch all proposed dates for this event using framework API
    $proposedDatesModel = $this->modelFactory->new('Event_Proposed_Dates');
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
```

## Error Handling

- **Missing event_id**: Throws `BadRequestException` (HTTP 400).
- **Event not found**: Throws `NotFoundException` (HTTP 404) with event_id context.
- **Not authenticated**: Throws `UnauthorizedException` (HTTP 401). Guests cannot modify commitments.
- **Not invited**: Throws `ForbiddenException` (HTTP 403) with event_id and user_id context.
- **Invalid request body** (missing/malformed commitments array): Throws `BadRequestException` with descriptive message identifying the invalid field.
- **Invalid proposed_date_id** (does not belong to event): Throws `BadRequestException` with the invalid IDs listed.
- **Database errors**: Doctrine DBAL exceptions propagate to the framework's global exception handler (HTTP 500).
- All exceptions use framework-specific exception classes extending `GCException`.

## Unit Test Specifications

**File**: `tests/Api/CommitmentsControllerTest.php`

**Namespace**: `Gravitycar\Tests\Api`

### Route Registration

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Registers two routes | Call registerRoutes() | Array with 2 entries | Both endpoints registered |
| PUT route correct | Check first entry | PUT /events/{event_id}/commitments | Upsert route |
| POST route correct | Check second entry | POST /events/{event_id}/accept-all | Accept-all route |
| Guests excluded | Check rolesAndActions | guest => [] | AC-15 no guest writes |

### Authorization (validateCommitmentAccess)

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Admin can access any event | Admin user, event exists | Returns access info | Admin bypass |
| Invited user can access | Non-admin, user is invited | Returns access info | Invitation-gated |
| Non-invited user rejected | Non-admin, not invited | Throws ForbiddenException | AC-15 |
| Guest rejected | currentUser is null | Throws UnauthorizedException | Guests cannot modify |
| Non-existent event | Event not found in DB | Throws NotFoundException | 404 for missing event |

### PUT Upsert - Input Validation

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Missing commitments key | `{}` | Throws BadRequestException | Required field |
| Empty commitments array | `{"commitments": []}` | Throws BadRequestException | Must be non-empty |
| Missing proposed_date_id | `{"commitments": [{"is_available": true}]}` | Throws BadRequestException | Required field per entry |
| Missing is_available | `{"commitments": [{"proposed_date_id": "x"}]}` | Throws BadRequestException | Required field per entry |
| Non-boolean is_available | `{"commitments": [{"proposed_date_id": "x", "is_available": "yes"}]}` | Throws BadRequestException | Must be boolean |
| Invalid proposed_date_id | Valid format but ID not in event | Throws BadRequestException | Foreign key validation |

### PUT Upsert - Per-Cell Logic

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Create new commitment | No existing record | INSERT executed, returns created=1 | New cell |
| Update existing commitment | Existing record found | UPDATE executed, returns updated=1 | Toggle existing cell |
| Mixed create and update | 1 existing + 1 new | Returns created=1, updated=1 | Batch handling |
| Set is_available=false | Existing record with true | UPDATE sets false | Toggle off |

### POST Accept-All

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| All dates accepted | 3 proposed dates, none committed | created=3, updated=0 | All new |
| Some already accepted | 2 of 3 already true | created=1, updated=2 | Mixed state |
| No proposed dates | Event has no dates | created=0, updated=0 | Edge case |

### Own-Row Authorization (AC-15)

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| User commits for self | user_id from auth matches | Succeeds | Own-row allowed |
| Upsert always uses auth user ID | Request body has no user_id field | user_id taken from auth context | Cannot spoof user_id |

### Key Scenario: Upsert Creates and Updates

**Setup**: Create CommitmentsController with mocked admin user (id='usr-1'). Mock event 'evt-1' exists via `modelFactory->retrieve('Events', 'evt-1')`. Mock 2 proposed dates: pd-1, pd-2. Mock `findFirst()`: for pd-1 returns existing commitment model, for pd-2 returns null (no existing record).

**Action**: Call `upsertCommitments()` with commitments=[{proposed_date_id: 'pd-1', is_available: false}, {proposed_date_id: 'pd-2', is_available: true}].

**Expected**: DB `update` called once for pd-1, DB `insert` called once for pd-2. Response: `{created: 1, updated: 1}`.

### Key Scenario: Non-Invited User Rejected

**Setup**: Mock non-admin user 'usr-99'. Mock event 'evt-1' exists. Mock invitation check returns 0 (not invited).

**Action**: Call `upsertCommitments()` with event_id='evt-1'.

**Expected**: Throws `ForbiddenException` before any DB writes. No commitment records modified.

### Test Helper Setup

```php
private function createCommitmentsController(
    ?object $mockCurrentUser = null
): CommitmentsController {
    $logger = new Logger('test');
    $modelFactory = $this->createMock(ModelFactory::class);
    $databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
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

    return new CommitmentsController(
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

- The `user_id` for commitments is ALWAYS taken from the authenticated user context (`$this->getCurrentUser()->get('id')`), never from the request body. This is the core mechanism for AC-15 (own-row authorization). The request body only contains `proposed_date_id` and `is_available`.
- The `upsertSingleCommitment` method is shared between the PUT and POST endpoints to avoid code duplication. It handles both INSERT and UPDATE paths.
- The upsert uses `ModelBase::findFirst()` to check for existing records, then `ModelBase::update()` or `ModelBase::create()` for persistence, avoiding raw SQL entirely. The DB-level unique constraint on (event_id, user_id, proposed_date_id) from plan-06 serves as a safety net.
- Boolean values for `is_available` are stored as integers (1/0) in MySQL via DBAL. The `? 1 : 0` conversion is explicit.
- The `Ramsey\Uuid\Uuid` library (already a Composer dependency via the framework) generates UUIDs for new commitment records, consistent with the framework's UUID-based primary keys.
- The framework's relationship API (`hasRelation()`) handles join table column naming internally, so no hardcoded column names are needed for invitation checks.
- The `isUserAdmin` helper uses the framework's `ModelBase::getRelatedModels()` to fetch roles via the `users_roles` relationship, avoiding raw SQL against the join table.
- Admin users can access any event's endpoints without being invited, but their commits still use their own user_id (own-row applies to everyone).
