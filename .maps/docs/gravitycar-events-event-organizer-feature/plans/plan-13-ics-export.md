# Implementation Plan: ICS Export Endpoint + Composer Dependency

## Spec Context

The ICS Export endpoint (GET /api/events/{event_id}/ics) generates an RFC 5545-compliant iCalendar file for an event's accepted date. It uses the `spatie/icalendar-generator` library to produce a VEVENT containing DTSTART (accepted_date), DTEND (accepted_date + duration_hours), SUMMARY, DESCRIPTION, LOCATION, UID, and DTSTAMP. The endpoint returns HTTP 404 when no accepted_date is set, and is accessible by admin and invited users only.

- **Catalog item**: 13 - ICS Export Endpoint + Composer Dependency
- **Specification section**: ICS Export section, API Endpoints -- GET /api/events/{event_id}/ics
- **Acceptance criteria addressed**: AC-9 (ICS file valid per RFC 5545, DTEND from duration_hours, imports into calendar apps)

## Dependencies

- **Blocked by**: Item 3 (Events Model -- provides the events table and Events model class with accepted_date, duration_hours, name, description, location fields)
- **Uses**: `src/Api/ApiControllerBase.php` (base class), `src/Factories/ModelFactory.php` (to load Events model), `src/Models/events/api/Api/ChartController.php` (pattern reference for access control helpers: `isUserInvited`, `getUserRoles`), `spatie/icalendar-generator` (Composer dependency)
- **Blocks**: Item 14 (Email Reminder Cron Job -- reuses ICS generation logic for email attachments)

## File Changes

### New Files

- `src/Models/events/api/Api/IcsExportController.php` -- Custom API controller for the ICS export endpoint
- `src/Services/IcsGeneratorService.php` -- Service class encapsulating ICS generation logic (reusable by item 14 for email attachments)
- `tests/Api/IcsExportControllerTest.php` -- Unit tests for the controller
- `tests/Services/IcsGeneratorServiceTest.php` -- Unit tests for the service

### Modified Files

- `composer.json` -- Add `spatie/icalendar-generator` to `require` section

## Implementation Details

### 1. Composer Dependency

Add to `composer.json` `require` section:

```json
"spatie/icalendar-generator": "^2.0"
```

Run `composer require spatie/icalendar-generator` to install and update `composer.lock`.

### 2. IcsGeneratorService

**File**: `src/Services/IcsGeneratorService.php`

**Namespace**: `Gravitycar\Services`

This service encapsulates the ICS generation logic so it can be reused by both the ICS export endpoint (this item) and the email reminder cron job (item 14) for attaching ICS files to reminder emails.

**Constructor Dependencies:**

```php
public function __construct(
    Logger $logger = null,
    Config $config = null
)
```

**Exports:**

- `generateIcsContent(array $eventData): string` -- Returns raw ICS string for a given event

**Input data shape:**

```php
// $eventData array:
[
    'id' => string,           // Event UUID, used for UID
    'name' => string,         // SUMMARY
    'description' => ?string, // DESCRIPTION (nullable)
    'location' => ?string,    // LOCATION (nullable)
    'accepted_date' => string, // ISO datetime string in UTC, used for DTSTART
    'duration_hours' => int,   // Used to calculate DTEND (default: 3)
]
```

**Code Example:**

```php
use Spatie\ICalendar\Calendar;
use Spatie\ICalendar\Components\Event;

public function generateIcsContent(array $eventData): string
{
    $this->validateEventData($eventData);

    $dtStart = new \DateTimeImmutable($eventData['accepted_date'], new \DateTimeZone('UTC'));
    $durationHours = $eventData['duration_hours'] ?? 3;
    $dtEnd = $dtStart->modify("+{$durationHours} hours");

    $uid = $eventData['id'] . '@gravitycar.com';

    $event = Event::create()
        ->name($eventData['name'])
        ->uniqueIdentifier($uid)
        ->startsAt($dtStart)
        ->endsAt($dtEnd);

    if (!empty($eventData['description'])) {
        $event->description($eventData['description']);
    }

    if (!empty($eventData['location'])) {
        $event->address($eventData['location']);
    }

    $calendar = Calendar::create()
        ->productIdentifier('-//Gravitycar//Event Organizer//EN')
        ->event($event);

    $this->logger?->info('ICS content generated', [
        'event_id' => $eventData['id'],
        'dtstart' => $dtStart->format('c'),
        'dtend' => $dtEnd->format('c'),
    ]);

    return $calendar->get();
}

protected function validateEventData(array $eventData): void
{
    if (empty($eventData['id'])) {
        throw new BadRequestException('Event ID is required for ICS generation');
    }
    if (empty($eventData['name'])) {
        throw new BadRequestException('Event name is required for ICS generation');
    }
    if (empty($eventData['accepted_date'])) {
        throw new BadRequestException('Accepted date is required for ICS generation');
    }
}
```

### 3. IcsExportController

**File**: `src/Models/events/api/Api/IcsExportController.php`

**Namespace**: `Gravitycar\Models\events\api\Api`

**Extends**: `Gravitycar\Api\ApiControllerBase`

**Roles and Actions:**

```php
protected array $rolesAndActions = [
    'admin' => ['read'],
    'user' => ['read'],
    'guest' => [],  // Guests cannot download ICS (spec: admin, invited users only)
];
```

**Route Registration:**

```php
public function registerRoutes(): array
{
    return [
        [
            'method' => 'GET',
            'path' => '/events/{event_id}/ics',
            'apiClass' => self::class,
            'apiMethod' => 'getIcs',
            'parameterNames' => ['event_id'],
            'rbacAction' => 'read',
        ],
    ];
}
```

**Access Control:**

Follow the same pattern as ChartController and MostPopularDateController:

```php
protected function validateAccess(string $eventId): array
{
    $eventsModel = $this->modelFactory->create('Events');
    $event = $eventsModel->findById($eventId);
    if ($event === null) {
        throw new NotFoundException('Event not found', ['event_id' => $eventId]);
    }

    $currentUser = $this->getCurrentUser();

    // Guests (unauthenticated) cannot access ICS export
    if ($currentUser === null) {
        throw new ForbiddenException(
            'Authentication required to download ICS file',
            ['event_id' => $eventId]
        );
    }

    $roles = $this->getUserRoles($currentUser);
    $isAdmin = in_array('admin', $roles, true);

    if (!$isAdmin) {
        $isInvited = $this->isUserInvited($eventId, $currentUser->get('id'));
        if (!$isInvited) {
            throw new ForbiddenException(
                'You are not invited to this event',
                ['event_id' => $eventId, 'user_id' => $currentUser->get('id')]
            );
        }
    }

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

**User Roles Helper** (same pattern as ChartController):

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
public function getIcs(Request $request): array
{
    $eventId = $request->getParameter('event_id');
    if (empty($eventId)) {
        throw new BadRequestException('Event ID is required');
    }

    $accessInfo = $this->validateAccess($eventId);
    $event = $accessInfo['event'];

    $acceptedDate = $event->get('accepted_date');
    if (empty($acceptedDate)) {
        throw new NotFoundException(
            'No accepted date set for this event. ICS export is only available after a date has been accepted.',
            ['event_id' => $eventId]
        );
    }

    $icsService = new IcsGeneratorService($this->logger, $this->config);
    $eventData = [
        'id' => $eventId,
        'name' => $event->get('name'),
        'description' => $event->get('description'),
        'location' => $event->get('location'),
        'accepted_date' => $acceptedDate,
        'duration_hours' => $event->get('duration_hours') ?? 3,
    ];
    $icsContent = $icsService->generateIcsContent($eventData);

    $this->logger->info('ICS file generated for download', [
        'event_id' => $eventId,
    ]);

    return [
        'raw_response' => true,
        'content_type' => 'text/calendar; charset=utf-8',
        'headers' => [
            'Content-Disposition' => 'attachment; filename="' . $this->sanitizeFilename($event->get('name')) . '.ics"',
        ],
        'body' => $icsContent,
        'status' => 200,
    ];
}

protected function sanitizeFilename(string $name): string
{
    $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
    return substr($sanitized, 0, 100);
}
```

**Key decisions:**

- The controller returns a `raw_response` array rather than the standard JSON envelope. The Router/response handler must detect `raw_response => true` and output the body directly with the specified Content-Type and headers. If the framework does not yet support raw responses, a minimal check should be added in the Router (see Notes section).
- The ICS generation logic is extracted to `IcsGeneratorService` so item 14 (email reminders) can reuse it to attach ICS files to reminder emails without depending on the controller.
- Guest access is denied (unlike the chart and most-popular-date endpoints which allow guest read access). The spec explicitly says ICS is for "admin, invited users" only.
- The `sanitizeFilename` helper strips non-alphanumeric characters from the event name for the Content-Disposition filename.
- `duration_hours` defaults to 3 if null (matching the Events model field default from the spec).

### 4. composer.json Modification

```json
"require": {
    ...existing dependencies...,
    "spatie/icalendar-generator": "^2.0"
}
```

## Error Handling

- **Missing event_id parameter**: Throws `BadRequestException` (HTTP 400).
- **Event not found** (invalid event_id or soft-deleted): Throws `NotFoundException` (HTTP 404) with event_id in context.
- **No accepted_date set**: Throws `NotFoundException` (HTTP 404) with descriptive message. This is a 404 per the spec, not a 400, because the ICS "resource" does not exist yet.
- **Unauthenticated user (guest)**: Throws `ForbiddenException` (HTTP 403). Guests cannot download ICS files.
- **Not invited** (authenticated non-admin, not in invitations): Throws `ForbiddenException` (HTTP 403) with event_id and user_id in context.
- **Invalid event data for ICS generation** (missing name): Throws `BadRequestException` (HTTP 400). Should not occur in practice since name is required on Events.
- **Spatie library exceptions**: Caught and wrapped in a `GCException` with logging. Unlikely in practice given validated input.

## Unit Test Specifications

### File: `tests/Services/IcsGeneratorServiceTest.php`

**Namespace**: `Gravitycar\Tests\Services`

#### IcsGeneratorService.generateIcsContent()

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Valid full event | All fields populated | ICS string contains VCALENDAR, VEVENT, DTSTART, DTEND, SUMMARY, DESCRIPTION, LOCATION, UID | Happy path |
| Missing description | description is null | ICS string has no DESCRIPTION line | Optional field omitted |
| Missing location | location is null | ICS string has no LOCATION line | Optional field omitted |
| Default duration | duration_hours is null | DTEND = DTSTART + 3 hours | Spec default |
| Custom duration | duration_hours = 5 | DTEND = DTSTART + 5 hours | Custom duration applied |
| Missing event ID | id is empty | Throws BadRequestException | Validation |
| Missing name | name is empty | Throws BadRequestException | Validation |
| Missing accepted_date | accepted_date is empty | Throws BadRequestException | Validation |

#### Key Scenario: Valid ICS with Duration Calculation

**Setup**: Create IcsGeneratorService with mock logger.
**Input**:
```php
[
    'id' => 'evt-abc-123',
    'name' => 'Game Night',
    'description' => 'Board games at the usual place',
    'location' => '123 Main St',
    'accepted_date' => '2026-04-15 18:00:00',
    'duration_hours' => 4,
]
```
**Action**: Call `generateIcsContent($eventData)`.
**Expected**: Returned string contains:
- `BEGIN:VCALENDAR`
- `PRODID:-//Gravitycar//Event Organizer//EN`
- `BEGIN:VEVENT`
- `UID:evt-abc-123@gravitycar.com`
- DTSTART corresponding to 2026-04-15T18:00:00Z
- DTEND corresponding to 2026-04-15T22:00:00Z (18:00 + 4 hours)
- `SUMMARY:Game Night`
- `DESCRIPTION:Board games at the usual place`
- `LOCATION:123 Main St`
- `END:VEVENT`
- `END:VCALENDAR`

#### Key Scenario: Minimal Event (No Description, No Location, Default Duration)

**Setup**: Create IcsGeneratorService with mock logger.
**Input**:
```php
[
    'id' => 'evt-minimal',
    'name' => 'Quick Meeting',
    'description' => null,
    'location' => null,
    'accepted_date' => '2026-05-01 10:00:00',
    'duration_hours' => null,
]
```
**Action**: Call `generateIcsContent($eventData)`.
**Expected**: DTEND is 3 hours after DTSTART (13:00:00Z). No DESCRIPTION or LOCATION lines in output.

### File: `tests/Api/IcsExportControllerTest.php`

**Namespace**: `Gravitycar\Tests\Api`

#### Route Registration

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Registers route | Call registerRoutes() | Array with one entry: GET /events/{event_id}/ics | Route discoverable |
| Correct HTTP method | Check route | method='GET' | Only GET supported |
| Has event_id param | Check parameterNames | Contains 'event_id' | URL param extraction |

#### Access Control

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Admin can access | Mock admin user, event with accepted_date | Returns raw ICS response | Admin access |
| Invited user can access | Mock non-admin invited user, event with accepted_date | Returns raw ICS response | Invitation-gated |
| Non-invited user rejected | Mock non-admin, NOT invited | Throws ForbiddenException | Authorization |
| Guest rejected | currentUser returns null | Throws ForbiddenException | No guest access for ICS |
| Non-existent event | findById returns null | Throws NotFoundException | 404 for missing event |
| Missing event_id | Empty event_id in request | Throws BadRequestException | Input validation |

#### ICS-Specific Behavior

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| No accepted_date | Event exists but accepted_date is null | Throws NotFoundException | Spec: 404 when no accepted_date |
| Returns text/calendar | Valid request | content_type = 'text/calendar; charset=utf-8' | RFC 5545 Content-Type |
| Returns attachment header | Valid request | Content-Disposition contains .ics filename | Downloadable file |
| Filename from event name | Event named "Game Night" | Filename contains "Game_Night.ics" | Sanitized name |
| raw_response flag set | Valid request | raw_response = true | Bypass JSON envelope |

#### Key Scenario: Guest Access Denied

**Setup**: Create IcsExportController with mocked dependencies. Mock `currentUserProvider->getCurrentUser()` returns null. Mock `modelFactory->create('Events')` returns model whose `findById` returns valid event with accepted_date set.
**Action**: Call `getIcs()` with Request containing event_id='evt-1'.
**Expected**: Throws `ForbiddenException` with message "Authentication required to download ICS file".

#### Key Scenario: No Accepted Date Returns 404

**Setup**: Create controller with admin user mock. Mock event with id='evt-1', accepted_date=null.
**Action**: Call `getIcs()` with event_id='evt-1'.
**Expected**: Throws `NotFoundException` with message containing "No accepted date set".

#### Test Helper Setup

```php
private function createController(
    ?object $mockCurrentUser = null
): IcsExportController {
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

    return new IcsExportController(
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

- **Raw response support**: The controller returns `['raw_response' => true, 'body' => ..., 'content_type' => ...]`. If the Router does not yet support raw (non-JSON) responses, a small change to the Router's response handling will be needed: check for `raw_response` in the return array and output the body with the appropriate Content-Type header instead of wrapping in JSON. This is a minimal change (5-10 lines) and should be addressed during build.
- **Access control helpers duplication**: The `isUserInvited` and `getUserRoles` methods are duplicated across ChartController, MostPopularDateController, CommitmentsController, and now IcsExportController. With 4 controllers sharing the pattern, extracting into a shared `EventAccessControlTrait` is justified during build. The trait should live at `src/Models/events/api/Api/EventAccessControlTrait.php`.
- **Spatie API version**: The `spatie/icalendar-generator` v2.x uses `Spatie\ICalendar\Calendar` and `Spatie\ICalendar\Components\Event`. Verify the exact namespace during `composer require` as it may be `Spatie\IcalendarGenerator\...` instead. Adjust imports accordingly.
- **IcsGeneratorService reuse**: Item 14 (Email Reminder Cron Job) will import `IcsGeneratorService` to attach ICS content to reminder emails. The service is intentionally stateless and accepts an array rather than a model instance, so it can be called from any context.
- **Datetime handling**: All datetimes are stored in UTC in the database (per spec). The ICS file uses UTC datetimes directly. No timezone conversion is needed in this endpoint.
- The framework's relationship API (`hasRelation()`) handles join table column naming internally, so no hardcoded column names are needed for invitation checks.
