# Implementation Plan: Chart of Goodness API Endpoint

## Spec Context

The Chart of Goodness API endpoint (GET /api/events/{event_id}/chart) is a custom endpoint that assembles the full grid data for a given event: proposed dates as columns, invited users as rows, and commitments as cells. It bypasses the Event_Commitments model's `rolesAndActions` and implements its own access control: admin full access, invited users can view, and guests get read-only access. User display names are rendered using the `displayColumns` metadata property from the Users model. This endpoint is the data source for the Chart of Goodness React UI (item 16).

- **Catalog item**: 9 - Chart of Goodness API Endpoint
- **Specification section**: API Endpoints -- GET /api/events/{event_id}/chart, Authorization section
- **Acceptance criteria addressed**: AC-4 (grid displays proposed dates vs invited users with availability), AC-14 (guests can view chart in read-only mode), AC-18 (user display names use displayColumns metadata)

## Dependencies

- **Blocked by**: Item 3 (Events Model), Item 4 (Event_Proposed_Dates Model), Item 5 (Event_Invitations Relationship), Item 6 (Event_Commitments Model)
- **Uses**: `src/Api/ApiControllerBase.php` (base class), `src/Api/AuthController.php` (pattern reference for custom controllers), `src/Factories/ModelFactory.php` (to load models), `src/Models/ModelBase.php` (getCurrentUser, model operations), database tables for events, event_proposed_dates, events_users_invitations, event_commitments

## File Changes

### New Files

- `src/Models/events/api/Api/ChartController.php` -- Custom API controller for the chart endpoint
- `tests/Api/ChartControllerTest.php` -- Unit tests

### Modified Files

- None

## Implementation Details

### 1. ChartController

**File**: `src/Models/events/api/Api/ChartController.php`

**Namespace**: `Gravitycar\Models\events\api\Api`

**Extends**: `Gravitycar\Api\ApiControllerBase`

This controller registers a single route and implements custom access control that is independent of the Event_Commitments model's `rolesAndActions`.

**Route Registration:**

```php
public function registerRoutes(): array
{
    return [
        [
            'method' => 'GET',
            'path' => '/events/{event_id}/chart',
            'apiClass' => self::class,
            'apiMethod' => 'getChart',
            'parameterNames' => ['event_id'],
            'rbacAction' => 'read',
        ],
    ];
}
```

**Roles and Actions:**

```php
protected array $rolesAndActions = [
    'admin' => ['read'],
    'user' => ['read'],
    'guest' => ['read'],
];
```

All three roles get `read` access. The controller's own `getChart()` method performs additional invitation-gated authorization for non-admin authenticated users.

**Core Method Signature:**

```php
public function getChart(Request $request): array
```

**Access Control Logic (within `getChart`):**

1. Load the event by `event_id`. Return 404 if not found.
2. Get current user via `$this->getCurrentUser()`. May be null (guest).
3. If current user is admin: allow full access, skip invitation check.
4. If current user is authenticated non-admin: query the `events_users_invitations` join table to verify the user is invited. Return 403 if not invited.
5. If current user is null (guest): allow read-only access (spec says guests can view chart).

```php
protected function validateChartAccess(string $eventId): array
{
    // Load event
    $eventsModel = $this->modelFactory->create('Events');
    $event = $eventsModel->findById($eventId);
    if ($event === null) {
        throw new NotFoundException('Event not found', ['event_id' => $eventId]);
    }

    $currentUser = $this->getCurrentUser();
    $isAdmin = false;
    $isInvited = false;
    $currentUserId = null;

    if ($currentUser !== null) {
        $currentUserId = $currentUser->get('id');
        $roles = $this->getUserRoles($currentUser);
        $isAdmin = in_array('admin', $roles, true);

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
```

**Invitation Check Helper (using framework relationship API):**

```php
protected function isUserInvited(string $eventId, string $userId): bool
{
    $eventsModel = $this->modelFactory->create('Events');
    $eventsModel->findById($eventId);

    $usersModel = $this->modelFactory->create('Users');
    $usersModel->findById($userId);

    return $eventsModel->hasRelation('events_users_invitations', $usersModel);
}
```

**Key decision**: Uses the framework's `ModelBase::hasRelation()` method instead of raw SQL against the join table. The `RelationshipBase` class handles all table/column naming internally, so we do not need to hardcode `events_id`, `users_id`, or the table name `events_users_invitations` in SQL.

**User Roles Helper:**

```php
protected function getUserRoles($currentUser): array
{
    // Use the framework's relationship API to fetch roles via users_roles
    $roleModels = $currentUser->getRelatedModels('users_roles');
    $roles = [];
    foreach ($roleModels as $roleModel) {
        $roles[] = $roleModel->get('name');
    }
    return $roles;
}
```

**Data Assembly (the main work of `getChart`):**

The method assembles three data sets via direct DBAL queries, then structures them for grid rendering.

**Step 1 -- Fetch proposed dates (columns):**

```php
protected function fetchProposedDates(string $eventId): array
{
    $proposedDatesModel = $this->modelFactory->new('Event_Proposed_Dates');
    return $proposedDatesModel->findRaw(
        ['event_id' => $eventId],
        ['id', 'proposed_date'],
        ['orderBy' => ['proposed_date' => 'ASC']]
    );
}
```

**Step 2 -- Fetch invited users (rows) with display names (using framework relationship API):**

This step uses the framework's `getRelatedModels()` method to fetch invited users, then extracts display columns from Users metadata.

```php
protected function fetchInvitedUsers(string $eventId): array
{
    $usersMetadata = $this->metadataEngine->getModelMetadata('Users');
    $displayColumns = $usersMetadata['displayColumns'] ?? ['username'];

    // Use framework relationship API to get invited users
    $eventsModel = $this->modelFactory->create('Events');
    $eventsModel->findById($eventId);
    $relatedUsers = $eventsModel->getRelatedModels('events_users_invitations');

    // Extract display column values from each related user model
    $result = [];
    foreach ($relatedUsers as $userModel) {
        $userData = ['id' => $userModel->get('id')];
        foreach ($displayColumns as $col) {
            $userData[$col] = $userModel->get($col);
        }
        $result[] = $userData;
    }

    // Sort by id for consistent ordering
    usort($result, fn($a, $b) => strcmp($a['id'], $b['id']));

    return $result;
}
```

**Key decision**: Uses `ModelBase::getRelatedModels()` instead of raw SQL with JOIN against the `events_users_invitations` table. The `RelationshipBase` class handles all table/column naming internally. The `displayColumns` from Users metadata (e.g., `['first_name', 'last_name']` or `['username']`) are fetched dynamically. The frontend receives these raw column values and can concatenate them for display. This satisfies AC-18 without hardcoding any name format.

**Step 3 -- Fetch all commitments (cells):**

```php
protected function fetchCommitments(string $eventId): array
{
    $commitmentsModel = $this->modelFactory->new('Event_Commitments');
    $rows = $commitmentsModel->findRaw(
        ['event_id' => $eventId],
        ['user_id', 'proposed_date_id', 'is_available']
    );

    // Index by "user_id:proposed_date_id" for O(1) cell lookups
    $indexed = [];
    foreach ($rows as $row) {
        $key = $row['user_id'] . ':' . $row['proposed_date_id'];
        $indexed[$key] = (bool) $row['is_available'];
    }
    return $indexed;
}
```

**Response Structure:**

```php
return [
    'success' => true,
    'status' => 200,
    'data' => [
        'event' => [
            'id' => $event->get('id'),
            'name' => $event->get('name'),
            'description' => $event->get('description'),
            'location' => $event->get('location'),
            'duration_hours' => $event->get('duration_hours'),
            'accepted_date' => $event->get('accepted_date'),
            'linked_model_name' => $event->get('linked_model_name'),
            'linked_record_id' => $event->get('linked_record_id'),
            'created_by' => $event->get('created_by'),
        ],
        'proposed_dates' => $proposedDates,
        'users' => $invitedUsers,
        'user_display_columns' => $displayColumns,
        'commitments' => $commitments,
        'current_user_id' => $currentUserId,
        'is_admin' => $isAdmin,
    ],
    'timestamp' => date('c'),
];
```

**Key decisions on response structure:**
- `proposed_dates`: Array of `{id, proposed_date}` objects, ordered by date ascending. These become grid columns.
- `users`: Array of `{id, ...displayColumns}` objects. Each user has `id` plus whatever fields `displayColumns` specifies. These become grid rows.
- `user_display_columns`: The array of column names from Users metadata so the frontend knows which fields to concatenate for display.
- `commitments`: An object keyed by `"user_id:proposed_date_id"` with boolean values. This flat map allows O(1) lookup for each cell. Absent keys mean `false` (no commitment record exists).
- `current_user_id`: The authenticated user's ID (null for guests). The frontend uses this to know which row is editable.
- `is_admin`: Boolean so the frontend can show/hide admin controls.
- `event`: Core event fields needed for the header area.

### 2. Complete getChart Method

```php
public function getChart(Request $request): array
{
    $eventId = $request->getParameter('event_id');
    if (empty($eventId)) {
        throw new BadRequestException('Event ID is required');
    }

    $accessInfo = $this->validateChartAccess($eventId);
    $event = $accessInfo['event'];

    $proposedDates = $this->fetchProposedDates($eventId);

    $usersMetadata = $this->metadataEngine->getModelMetadata('Users');
    $displayColumns = $usersMetadata['displayColumns'] ?? ['username'];
    $invitedUsers = $this->fetchInvitedUsers($eventId);

    $commitments = $this->fetchCommitments($eventId);

    $this->logger->info('Chart data assembled', [
        'event_id' => $eventId,
        'proposed_dates_count' => count($proposedDates),
        'invited_users_count' => count($invitedUsers),
        'commitments_count' => count($commitments),
    ]);

    return [
        'success' => true,
        'status' => 200,
        'data' => [
            'event' => [
                'id' => $event->get('id'),
                'name' => $event->get('name'),
                'description' => $event->get('description'),
                'location' => $event->get('location'),
                'duration_hours' => $event->get('duration_hours'),
                'accepted_date' => $event->get('accepted_date'),
                'linked_model_name' => $event->get('linked_model_name'),
                'linked_record_id' => $event->get('linked_record_id'),
                'created_by' => $event->get('created_by'),
            ],
            'proposed_dates' => $proposedDates,
            'users' => $invitedUsers,
            'user_display_columns' => $displayColumns,
            'commitments' => $commitments,
            'current_user_id' => $accessInfo['currentUserId'],
            'is_admin' => $accessInfo['isAdmin'],
        ],
        'timestamp' => date('c'),
    ];
}
```

## Error Handling

- **Event not found** (invalid `event_id`): Throws `NotFoundException` (HTTP 404) with the event_id in context.
- **Not invited** (authenticated non-admin user not in invitations): Throws `ForbiddenException` (HTTP 403) with event_id and user_id in context.
- **Missing event_id parameter**: Throws `BadRequestException` (HTTP 400).
- **Database errors**: Doctrine DBAL exceptions propagate up to the framework's global exception handler, which logs and returns HTTP 500.
- All exceptions use framework-specific exception classes that extend `GCException` for consistent logging and context.

## Unit Test Specifications

**File**: `tests/Api/ChartControllerTest.php`

**Namespace**: `Gravitycar\Tests\Api`

### Route Registration

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Registers chart route | Call registerRoutes() | Returns array with one entry: GET /events/{event_id}/chart | Route is discoverable |
| Route has correct method | Check route entry | method='GET' | Only GET is supported |
| Route has event_id param | Check parameterNames | Contains 'event_id' | URL parameter extraction |

### Access Control (validateChartAccess)

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Admin can access any event | Mock admin user, event exists | Returns access info with isAdmin=true | Admin bypass |
| Invited user can access | Mock non-admin user, user is invited | Returns access info with isInvited implied | Invitation-gated access |
| Non-invited user rejected | Mock non-admin user, user NOT invited | Throws ForbiddenException | AC-14 enforcement |
| Guest can access (null user) | currentUser returns null | Returns access info with currentUserId=null | AC-14 guest read-only |
| Non-existent event | Mock event findById returns null | Throws NotFoundException | 404 for missing event |

### Key Scenario: Non-Invited User Rejected

**Setup**: Create ChartController with mocked dependencies. Mock `currentUserProvider` to return a user with id='usr-99'. Mock `hasRelation('events_users_invitations', ...)` to return `false` (not invited). Mock `modelFactory->create('Events')` to return a model whose `findById` returns a valid event.

**Action**: Call `getChart()` with Request containing event_id='evt-1'.

**Expected**: Throws `ForbiddenException` with message "You are not invited to this event" and context containing event_id and user_id.

### Key Scenario: Guest Read-Only Access

**Setup**: Create ChartController with mocked dependencies. Mock `currentUserProvider` to return null (guest). Mock `modelFactory->create('Events')` to return a valid event. Mock DBAL queries for proposed dates, users, and commitments.

**Action**: Call `getChart()` with Request containing event_id='evt-1'.

**Expected**: Returns success response with `current_user_id=null` and `is_admin=false`. No ForbiddenException thrown.

### Data Assembly

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Proposed dates ordered by date | Mock 3 dates in random order | Response proposed_dates ordered ASC | Columns in chronological order |
| Users include displayColumns | Mock Users metadata with displayColumns=['first_name','last_name'] | Each user object has id, first_name, last_name | AC-18 display names |
| Commitments indexed correctly | Mock 2 commitments | commitments map has keys "uid:pdid" with boolean values | O(1) cell lookup |
| Empty event (no dates/users) | Mock empty arrays | Response has empty proposed_dates, users, commitments | Edge case: new event |
| user_display_columns in response | Mock Users metadata | Response includes user_display_columns array | Frontend knows how to render names |

### Key Scenario: Full Chart Assembly

**Setup**: Create ChartController with admin user. Mock event with id='evt-1', name='Book Club'. Mock 2 proposed dates: pd-1 (2026-04-10 19:00), pd-2 (2026-04-17 19:00). Mock 2 invited users: usr-1 (Alice), usr-2 (Bob). Mock commitments: usr-1:pd-1=true, usr-1:pd-2=false, usr-2:pd-1=true.

**Action**: Call `getChart()`.

**Expected**:
- `data.event.name` = 'Book Club'
- `data.proposed_dates` has 2 entries ordered by date
- `data.users` has 2 entries with display column values
- `data.commitments` = `{'usr-1:pd-1': true, 'usr-1:pd-2': false, 'usr-2:pd-1': true}`
- `data.current_user_id` = admin user's ID
- `data.is_admin` = true

### Response Structure

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Success response format | Valid request | Has success=true, status=200, data, timestamp | Consistent API format |
| Event fields included | Valid event | data.event has id, name, description, location, etc. | Header area needs event info |
| Linked model fields included | Event with linked_model_name | data.event has linked_model_name and linked_record_id | For linked record display |

### Test Helper Setup

```php
private function createChartController(
    ?object $mockCurrentUser = null,
    ?array $mockRoles = null
): ChartController {
    $logger = new Logger('test');
    $modelFactory = $this->createMock(ModelFactory::class);
    $databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
    $metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $config = $this->createMock(Config::class);
    $currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

    if ($mockCurrentUser !== null) {
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($mockCurrentUser);
    }

    return new ChartController(
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

- The controller uses the framework's relationship API (`hasRelation()`, `getRelatedModels()`) to query the `events_users_invitations` relationship instead of raw DBAL queries against the join table. This keeps the code decoupled from join table column naming conventions.
- The `commitments` map uses a `"user_id:proposed_date_id"` string key rather than a nested object. This is more compact for JSON serialization and allows O(1) lookup in both PHP and JavaScript.
- The `user_display_columns` field in the response tells the frontend which columns from each user object to concatenate for display. This satisfies AC-18 without hardcoding any name format. For example, if `displayColumns` is `['first_name', 'last_name']`, the frontend would render "Alice Smith".
- The framework's relationship API (`hasRelation()`, `getRelatedModels()`) handles join table column naming internally, so no hardcoded column names are needed in this controller.
- The `getUserRoles()` helper uses the framework's `ModelBase::getRelatedModels()` to fetch roles via the `users_roles` relationship, avoiding raw SQL against the join table.
- This endpoint does NOT go through Event_Commitments' `rolesAndActions`. The `guest:[]` setting on that model only prevents direct CRUD operations on commitment records; it does not restrict this chart endpoint from reading commitment data for display.
- The `findById()` method on ModelBase is assumed to return null for soft-deleted or non-existent records. Verify this behavior during implementation.
