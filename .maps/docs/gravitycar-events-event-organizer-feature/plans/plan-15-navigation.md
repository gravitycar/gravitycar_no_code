# Implementation Plan: Navigation Configuration

## Spec Context

The specification requires an "Events" section in the navigation sidebar with "Create Event" (admin only) and "List Events" sub-items, plus smart routing logic: if a user has exactly one upcoming event invitation, clicking "Events" redirects to that event's Chart of Goodness; otherwise, it shows a list. This fulfills AC-13.

Catalog item: 15 - Navigation Configuration
Specification section: Navigation
Acceptance criteria addressed: AC-13

## Dependencies

- **Blocked by**: Item 3 (Events Model) -- the Events model and its metadata must exist for navigation entries to reference it
- **Uses**: `src/Navigation/navigation_config.php`, `src/Services/NavigationBuilder.php`, `gravitycar-frontend/src/services/navigationService.ts`, `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx`

## File Changes

### Modified Files

- `src/Navigation/navigation_config.php` -- Add "Events" section with sub-items to `custom_pages`
- `src/Services/NavigationBuilder.php` -- Add 'Events' icon to `$iconMap`
- `gravitycar-frontend/src/services/navigationService.ts` -- Add `resolveEventsSmartRoute()` method for smart routing logic
- `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx` -- Add smart routing handler for the Events nav item
- `gravitycar-frontend/src/App.tsx` -- Add route for `/events/:eventId/chart` (Chart of Goodness page) and `/events` (list page)

### New Files

- `gravitycar-frontend/src/services/__tests__/navigationService.test.ts` -- Unit tests for smart routing logic

## Implementation Details

### 1. Backend: Navigation Config Entries

**File**: `src/Navigation/navigation_config.php`

Add three entries to `custom_pages` for the Events section. The navigation system already supports `roles` arrays for restricting visibility.

```php
// Add after the dnd_chat entry in custom_pages:
[
    'key' => 'events',
    'title' => 'Events',
    'url' => '/events',
    'icon' => '📅',
    'roles' => ['*'] // All roles can see Events
],
[
    'key' => 'events_create',
    'title' => 'Create Event',
    'url' => '/events?action=create',
    'icon' => '➕',
    'roles' => ['admin'] // Admin only
],
[
    'key' => 'events_list',
    'title' => 'List Events',
    'url' => '/events',
    'icon' => '📋',
    'roles' => ['*'] // All roles
],
```

**Design note**: The existing navigation system uses a flat list of `custom_pages`. There is no built-in hierarchical grouping (parent/child) in the current `CustomPage` type. Rather than restructure the entire navigation system, we use a convention: `events` is the parent item, and `events_create` / `events_list` are visually indented sub-items. The frontend will group items by prefix (`events_*` items are children of `events`).

### 2. Backend: Icon Map Update

**File**: `src/Services/NavigationBuilder.php`

Add `'Events' => '📅'` to the `$iconMap` array in `getModelIcon()`. This ensures that if the Events model also appears in the auto-generated model navigation, it gets the correct icon.

```php
$iconMap = [
    'Users' => '👥',
    'Movies' => '🎬',
    'Movie_Quotes' => '💬',
    'Roles' => '🔑',
    'Permissions' => '🛡️',
    'Books' => '📚',
    'Events' => '📅',
];
```

### 3. Frontend: Smart Routing Logic

**File**: `gravitycar-frontend/src/services/navigationService.ts`

Add a `resolveEventsSmartRoute()` method that determines where the user should be redirected when clicking "Events":

```typescript
/**
 * Resolve smart routing for the Events nav item.
 * - If user has exactly 1 upcoming event invitation -> return chart URL for that event
 * - Otherwise -> return events list URL
 *
 * An "upcoming event" is one where the user is invited AND
 * (has at least one proposed date in the future OR has an accepted_date in the future).
 */
async resolveEventsSmartRoute(): Promise<string> {
  try {
    const response = await (apiService as any).api.get('/events/smart-route');
    const data = response.data;

    if (data.success && data.data.redirect_to) {
      return data.data.redirect_to;
    }

    return '/events';
  } catch (error) {
    console.error('Failed to resolve events smart route:', error);
    return '/events';
  }
}
```

**Why a backend endpoint?** The smart routing logic requires querying the database for the user's upcoming event invitations with date filtering. Doing this purely on the frontend would require fetching all events + invitations + proposed dates just to count them. A dedicated lightweight backend endpoint is cleaner.

### 4. Backend: Smart Route API Endpoint

**File**: `src/Models/events/api/Api/SmartRouteController.php`

This is a lightweight GET endpoint at `/api/events/smart-route` that returns a redirect target:

```php
namespace Gravitycar\Models\events\api\Api;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Api\Request;
```

**Exports**:
- `registerRoutes(): array` -- Registers `GET /events/smart-route`
- `getSmartRoute(Request $request): array` -- Returns `{ redirect_to: string }`

**Logic**:
1. Get current user ID from `CurrentUserProvider`
2. Use the framework's relationship API to get events where the user is invited: load the User model, then call `$userModel->getRelatedModels('events_users_invitations')` to retrieve all Events the user is related to via invitations.
3. For each invited event, check if it has at least one proposed date in the future OR an accepted_date in the future (using `Events::isActive()` or a direct query on `event_proposed_dates`).
4. If exactly 1 upcoming event found, return `{ redirect_to: "/events/{event_id}/chart" }`
5. Otherwise return `{ redirect_to: "/events" }`

**PHP (using framework relationship API):**
```php
$usersModel = $this->modelFactory->create('Users');
$usersModel->findById($userId);
$invitedEvents = $usersModel->getRelatedModels('events_users_invitations');

$upcomingEventIds = [];
$now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

foreach ($invitedEvents as $eventModel) {
    $acceptedDate = $eventModel->get('accepted_date');
    if ($acceptedDate !== null && new \DateTimeImmutable($acceptedDate) > $now) {
        $upcomingEventIds[] = $eventModel->get('id');
        continue;
    }

    // Check for future proposed dates using framework API
    $proposedDatesModel = $this->modelFactory->new('Event_Proposed_Dates');
    $futureDates = $proposedDatesModel->findRaw(
        ['event_id' => $eventModel->get('id')],
        ['id'],
        [
            'where' => ['proposed_date > :now'],
            'params' => ['now' => $now->format('Y-m-d H:i:s')],
        ]
    );
    if (count($futureDates) > 0) {
        $upcomingEventIds[] = $eventModel->get('id');
    }
}
```

**Response shape**:
```json
{
  "success": true,
  "data": {
    "redirect_to": "/events/abc-123/chart",
    "upcoming_count": 1
  }
}
```

For guests (unauthenticated), the endpoint returns `/events` (no smart routing for guests).

### 5. Frontend: Navigation Sidebar - Events Grouping

**File**: `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx`

Modify the custom pages rendering to support grouping. Items with keys matching `{parent}_*` pattern are rendered as sub-items of the `{parent}` item:

1. In the custom pages rendering loop, detect items whose `key` starts with `events_` and group them under the `events` parent item.
2. When the user clicks the `events` parent link, call `navigationService.resolveEventsSmartRoute()` and navigate to the returned URL instead of the static `/events` URL.
3. Sub-items (`events_create`, `events_list`) link directly to their URLs without smart routing.

**Implementation approach**: Add a helper function `groupCustomPages(pages: CustomPage[])` that returns a structure with parent items and their children:

```typescript
interface GroupedCustomPage extends CustomPage {
  children?: CustomPage[];
}

function groupCustomPages(pages: CustomPage[]): GroupedCustomPage[] {
  const grouped: GroupedCustomPage[] = [];
  const childMap = new Map<string, CustomPage[]>();

  for (const page of pages) {
    const underscoreIndex = page.key.indexOf('_');
    if (underscoreIndex > 0) {
      const parentKey = page.key.substring(0, underscoreIndex);
      if (!childMap.has(parentKey)) {
        childMap.set(parentKey, []);
      }
      childMap.get(parentKey)!.push(page);
    } else {
      grouped.push({ ...page });
    }
  }

  for (const item of grouped) {
    const children = childMap.get(item.key);
    if (children) {
      item.children = children;
    }
  }

  return grouped;
}
```

The parent `events` item renders with an expand/collapse chevron (same pattern as model items). Children render indented below it.

When the parent `events` link is clicked:
```typescript
const handleEventsClick = async (e: React.MouseEvent) => {
  e.preventDefault();
  const targetUrl = await navigationService.resolveEventsSmartRoute();
  window.location.href = targetUrl;
};
```

### 6. Frontend: App.tsx Route Registration

**File**: `gravitycar-frontend/src/App.tsx`

Add routes for the events feature. The Chart of Goodness page component will be built in catalog item 16, but the route must be registered now:

```tsx
{/* Events Routes */}
<Route
  path="/events"
  element={
    <Layout>
      <DynamicModelRoute />
    </Layout>
  }
/>
<Route
  path="/events/:eventId/chart"
  element={
    <ProtectedRoute>
      <Layout>
        {/* ChartOfGoodness component - built in item 16 */}
        <div>Chart of Goodness placeholder</div>
      </Layout>
    </ProtectedRoute>
  }
/>
```

**Note**: The `/events` route does NOT use `ProtectedRoute` because guests can view the events list (AC-14). The `/events/:eventId/chart` route is protected because chart interaction requires authentication (guests access via the public chart endpoint in item 9, but the page itself loads for all).

Actually, re-reading the spec: guests CAN view the chart in read-only mode. So the chart route should also be accessible without authentication. We will wrap it with `Layout` only (no `ProtectedRoute`). The chart page component (item 16) will handle rendering differently for guests vs authenticated users.

```tsx
<Route
  path="/events/:eventId/chart"
  element={
    <Layout>
      {/* ChartOfGoodness component - placeholder until item 16 */}
      <div>Chart of Goodness - coming soon</div>
    </Layout>
  }
/>
```

## Error Handling

- Smart route API failure: frontend falls back to `/events` list page (never breaks navigation)
- Navigation config load failure: existing `NavigationBuilderException` handles this
- Missing Events model (during development before item 3): the `custom_pages` entries are static and do not depend on model existence; only the `$iconMap` addition is cosmetic

## Unit Test Specifications

### File: `gravitycar-frontend/src/services/__tests__/navigationService.test.ts`

#### `resolveEventsSmartRoute()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Single upcoming event | API returns `redirect_to: "/events/abc-123/chart"` | Returns `"/events/abc-123/chart"` | Smart routing redirects to chart |
| Zero upcoming events | API returns `redirect_to: "/events"` | Returns `"/events"` | Falls back to list |
| Multiple upcoming events | API returns `redirect_to: "/events"` | Returns `"/events"` | Falls back to list |
| API error | API throws error | Returns `"/events"` | Graceful fallback |

#### `groupCustomPages()`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| No children | `[{key:"dashboard"}, {key:"events"}]` | Both as top-level, no children | No grouping needed |
| With children | `[{key:"events"}, {key:"events_create"}, {key:"events_list"}]` | `events` has 2 children | Prefix grouping works |
| Orphan children | `[{key:"events_create"}]` (no parent) | Empty top-level, orphan is not shown | Graceful handling |

### File: `Tests/Unit/Api/SmartRouteControllerTest.php`

#### `getSmartRoute()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| User with 1 upcoming event | 1 invitation with future proposed date | `redirect_to` = chart URL | Smart routing |
| User with 0 upcoming events | No invitations | `redirect_to` = `/events` | Falls back to list |
| User with 3 upcoming events | 3 invitations with future dates | `redirect_to` = `/events` | Falls back to list |
| User with 1 past event only | 1 invitation, all dates in past, no accepted_date | `redirect_to` = `/events` | Past events are not "upcoming" |
| User with 1 event with future accepted_date | 1 invitation, no future proposed dates, but accepted_date is future | `redirect_to` = chart URL | accepted_date counts as upcoming |
| Guest (unauthenticated) | No current user | `redirect_to` = `/events` | No smart routing for guests |

## Notes

- The `custom_pages` approach for navigation is simpler than modifying the model-based navigation, since Events needs custom behavior (smart routing) that models do not have.
- The `groupCustomPages` helper uses a key-prefix convention. This is a lightweight approach that does not require schema changes to the `CustomPage` type. If the framework later needs deep hierarchical nav, the type can be extended.
- Active events (future proposed dates, no accepted_date) should be sorted first in the events list. This sorting is handled by the Events list API endpoint (standard CRUD with custom ordering), not by the navigation layer.
- The SmartRouteController is placed in `src/Models/events/api/Api/` to follow the auto-discovery pattern used by `APIRouteRegistry`.
