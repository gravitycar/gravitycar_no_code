# Implementation Plan: Most Popular Date API Endpoint

## Spec Context

The Most Popular Date API endpoint (GET /api/events/{event_id}/most-popular-date) returns the proposed date(s) with the highest count of commitments where `is_available = true`. When multiple dates are tied for the highest count, ALL tied dates are returned as an array. This endpoint is accessible by admin, invited users, and guests (read-only). The Chart of Goodness UI (item 16) uses this endpoint to display the "Most Popular Date" banner above the grid. The core computation logic already exists in the `Events.getMostPopularDates()` method (item 3); this endpoint wraps it with access control and HTTP response formatting.

- **Catalog item**: 12 - Most Popular Date API Endpoint
- **Specification section**: API Endpoints -- GET /api/events/{event_id}/most-popular-date
- **Acceptance criteria addressed**: AC-7 (most popular date calculated correctly, tied dates all shown)

## Dependencies

- **Blocked by**: Item 6 (Event_Commitments Model -- provides the `event_commitments` table queried for vote counts)
- **Uses**: `src/Api/ApiControllerBase.php` (base class), `src/Models/events/Events.php` (getMostPopularDates method from item 3), `src/Factories/ModelFactory.php` (to instantiate Events model), `src/Models/events/api/Api/ChartController.php` (pattern reference for access control helpers: `isUserInvited`, `getUserRoles`, `validateChartAccess`)

## File Changes

### New Files

- `src/Models/events/api/Api/MostPopularDateController.php` -- Custom API controller for the most-popular-date endpoint
- `tests/Api/MostPopularDateControllerTest.php` -- Unit tests

### Modified Files

None.

## Implementation Details

### 1. MostPopularDateController

**File**: `src/Models/events/api/Api/MostPopularDateController.php`

**Namespace**: `Gravitycar\Models\events\api\Api`

**Extends**: `Gravitycar\Api\ApiControllerBase`

This controller registers a single GET route and reuses the same access control pattern established in ChartController (item 9): admin has full access, authenticated non-admin users must be invited, and guests get read-only access.

**Roles and Actions:**

```php
protected array $rolesAndActions = [
    'admin' => ['read'],
    'user' => ['read'],
    'guest' => ['read'],
];
```

**Route Registration:**

```php
public function registerRoutes(): array
{
    return [
        [
            'method' => 'GET',
            'path' => '/events/{event_id}/most-popular-date',
            'apiClass' => self::class,
            'apiMethod' => 'getMostPopularDate',
            'parameterNames' => ['event_id'],
            'rbacAction' => 'read',
        ],
    ];
}
```

**Access Control:**

Follow the same pattern as ChartController's `validateChartAccess`:

```php
protected function validateAccess(string $eventId): array
{
    $eventsModel = $this->modelFactory->create('Events');
    $event = $eventsModel->findById($eventId);
    if ($event === null) {
        throw new NotFoundException('Event not found', ['event_id' => $eventId]);
    }

    $currentUser = $this->getCurrentUser();
    $isAdmin = false;

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

    return ['event' => $event, 'isAdmin' => $isAdmin];
}
```

**Invitation Check Helper (using framework relationship API, same as ChartController):**

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

**User Roles Helper** (same as ChartController):

```php
protected function getUserRoles($currentUser): array
{
    $roleModels = $currentUser->getRelatedModels('users_roles');
    $roles = [];
    foreach ($roleModels as $roleModel) {
        $roles[] = $roleModel->get('name');
    }
    return $roles;
}
```

**Core Method:**

```php
public function getMostPopularDate(Request $request): array
{
    $eventId = $request->getParameter('event_id');
    if (empty($eventId)) {
        throw new BadRequestException('Event ID is required');
    }

    $accessInfo = $this->validateAccess($eventId);
    $event = $accessInfo['event'];

    $mostPopularDates = $event->getMostPopularDates();

    $this->logger->info('Most popular date(s) retrieved', [
        'event_id' => $eventId,
        'tied_count' => count($mostPopularDates),
    ]);

    return [
        'success' => true,
        'status' => 200,
        'data' => [
            'event_id' => $eventId,
            'most_popular_dates' => $mostPopularDates,
            'tied' => count($mostPopularDates) > 1,
        ],
        'timestamp' => date('c'),
    ];
}
```

**Response structure details:**
- `most_popular_dates`: Array of `{proposed_date_id: string, proposed_date: string, vote_count: int}` objects. Empty array if no commitments exist.
- `tied`: Boolean indicating whether multiple dates share the highest count. Convenience field for the frontend banner (e.g., "Most popular: Sat Mar 14 @ 7pm, Sun Mar 15 @ 7pm (tied, 5 votes each)").
- `event_id`: Echo back the requested event ID for client convenience.

**Key decisions:**
- Delegates to `Events.getMostPopularDates()` (built in item 3) rather than duplicating the query logic. The Events model method handles the vote counting, tie detection, and proposed date enrichment.
- The `tied` boolean is a convenience field. The frontend can also derive it from `most_popular_dates.length > 1`, but including it explicitly simplifies conditional rendering.
- Access control mirrors ChartController exactly. Both endpoints serve the Chart of Goodness UI and share the same authorization model (admin, invited, guest).

### 2. Full Class Structure

```php
<?php
namespace Gravitycar\Models\events\api\Api;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\ForbiddenException;
use Gravitycar\Exceptions\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

class MostPopularDateController extends ApiControllerBase
{
    protected array $rolesAndActions = [
        'admin' => ['read'],
        'user' => ['read'],
        'guest' => ['read'],
    ];

    public function registerRoutes(): array { /* as above */ }
    public function getMostPopularDate(Request $request): array { /* as above */ }
    protected function validateAccess(string $eventId): array { /* as above */ }
    protected function isUserInvited(string $eventId, string $userId): bool { /* as above */ }
    protected function getUserRoles($currentUser): array { /* as above */ }
}
```

## Error Handling

- **Missing event_id parameter**: Throws `BadRequestException` (HTTP 400).
- **Event not found** (invalid event_id or soft-deleted): Throws `NotFoundException` (HTTP 404) with event_id in context.
- **Not invited** (authenticated non-admin, not in invitations): Throws `ForbiddenException` (HTTP 403) with event_id and user_id in context.
- **Database errors**: Doctrine DBAL exceptions propagate to the framework's global exception handler (HTTP 500 with logging).
- All exceptions extend `GCException` for consistent logging and context.

## Unit Test Specifications

**File**: `tests/Api/MostPopularDateControllerTest.php`

**Namespace**: `Gravitycar\Tests\Api`

### Route Registration

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Registers route | Call registerRoutes() | Array with one entry: GET /events/{event_id}/most-popular-date | Route discoverable |
| Correct HTTP method | Check route | method='GET' | Only GET supported |
| Has event_id param | Check parameterNames | Contains 'event_id' | URL param extraction |

### Access Control

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Admin can access | Mock admin user, event exists | Returns success response | Admin bypass |
| Invited user can access | Mock non-admin invited user | Returns success response | Invitation-gated |
| Non-invited user rejected | Mock non-admin, NOT invited | Throws ForbiddenException | Authorization enforcement |
| Guest can access | currentUser returns null | Returns success response with data | AC-7 guest read |
| Non-existent event | findById returns null | Throws NotFoundException | 404 for missing event |
| Missing event_id | Empty event_id in request | Throws BadRequestException | Input validation |

### Key Scenario: Non-Invited User Rejected

**Setup**: Create MostPopularDateController with mocked dependencies. Mock `currentUserProvider` to return a user with id='usr-99'. Mock `hasRelation('events_users_invitations', ...)` to return `false` (not invited). Mock `modelFactory->create('Events')` returning a model whose `findById` returns a valid event.
**Action**: Call `getMostPopularDate()` with Request containing event_id='evt-1'.
**Expected**: Throws `ForbiddenException` with message "You are not invited to this event".

### Most Popular Date Results

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Single winner | getMostPopularDates returns 1 entry with vote_count=5 | most_popular_dates has 1 item, tied=false | Clear winner |
| Tied dates | getMostPopularDates returns 2 entries each with vote_count=5 | most_popular_dates has 2 items, tied=true | AC-7: all ties shown |
| No commitments | getMostPopularDates returns empty array | most_popular_dates=[], tied=false | Edge case: no votes yet |
| Three-way tie | getMostPopularDates returns 3 entries each with vote_count=3 | most_popular_dates has 3 items, tied=true | Multiple ties |

### Key Scenario: Tied Dates Response

**Setup**: Create controller with admin user. Mock event with id='evt-1'. Mock `event->getMostPopularDates()` to return:
```php
[
    ['proposed_date_id' => 'pd-1', 'proposed_date' => '2026-04-10 19:00:00', 'vote_count' => 5],
    ['proposed_date_id' => 'pd-2', 'proposed_date' => '2026-04-17 19:00:00', 'vote_count' => 5],
]
```
**Action**: Call `getMostPopularDate()` with event_id='evt-1'.
**Expected**: Response `data.most_popular_dates` contains both entries. `data.tied` is `true`. `data.event_id` is 'evt-1'.

### Key Scenario: No Commitments Yet

**Setup**: Create controller with admin user. Mock event. Mock `event->getMostPopularDates()` to return `[]`.
**Action**: Call `getMostPopularDate()` with event_id='evt-1'.
**Expected**: Response `data.most_popular_dates` is empty array. `data.tied` is `false`.

### Response Structure

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Success format | Valid request | success=true, status=200, data, timestamp | Consistent API format |
| Data includes event_id | Valid request | data.event_id matches request | Client convenience |
| Data includes tied flag | Tied dates | data.tied=true | Frontend banner logic |

### Test Helper Setup

```php
private function createController(
    ?object $mockCurrentUser = null,
    ?array $mockRoles = null
): MostPopularDateController {
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

    return new MostPopularDateController(
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

- The access control helpers (`isUserInvited`, `getUserRoles`) are duplicated from ChartController (item 9). During implementation, if both controllers exist, consider extracting these into a shared trait (e.g., `EventAccessControlTrait`) to reduce duplication. However, do NOT create an abstraction prematurely -- wait until at least 3 controllers share the pattern (ChartController, MostPopularDateController, CommitmentsController all do).
- The `getMostPopularDates()` method on the Events model (item 3) already handles: counting votes, finding the max, filtering ties, and enriching with proposed date details. This controller is intentionally thin -- it validates access and delegates to the model.
- The framework's relationship API (`hasRelation()`) handles join table column naming internally, so no hardcoded column names are needed for invitation checks.
- The `tied` field in the response uses `count($mostPopularDates) > 1` which is `false` for empty arrays (0 > 1 = false). This is correct: an event with no commitments has no "tie" to display.
