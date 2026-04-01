# Implementation Plan: Chart of Goodness UI (React Page)

## Spec Context

The Chart of Goodness is the primary UI for the events feature: a custom React page (not GenericCrudPage) that displays a grid of proposed dates (columns) vs invited users (rows) with interactive checkboxes for the current user and read-only indicators for others. It includes a most popular date banner (with tie support), accepted date banner with ICS download, admin controls, an Accept All button, and responsive layout. Guests see a fully read-only view.

- **Catalog item**: 16 - Chart of Goodness UI (React Page)
- **Specification section**: UI Components -- Chart of Goodness
- **Acceptance criteria addressed**: AC-4, AC-5, AC-6, AC-7, AC-8, AC-9, AC-14, AC-18

## Dependencies

- **Blocked by**: Item 9 (Chart API -- provides GET /api/events/{event_id}/chart), Item 10 (Commitments API -- provides PUT upsert + POST accept-all), Item 12 (Most Popular Date API -- provides GET most-popular-date), Item 15 (Navigation Config -- provides Events nav entries and route)
- **Uses**: `gravitycar-frontend/src/services/api.ts` (apiService for HTTP calls), `gravitycar-frontend/src/hooks/useAuth.tsx` (useAuth for current user/auth state), `gravitycar-frontend/src/components/layout/Layout.tsx` (page layout wrapper), `gravitycar-frontend/src/App.tsx` (route registration), `react-router-dom` (useParams for event_id)

## File Changes

### New Files

- `gravitycar-frontend/src/pages/ChartOfGoodness.tsx` -- Main page component (~250 lines)
- `gravitycar-frontend/src/pages/__tests__/ChartOfGoodness.test.tsx` -- Unit tests

### Modified Files

- `gravitycar-frontend/src/App.tsx` -- Add route for `/events/:eventId/chart`
- `gravitycar-frontend/src/services/api.ts` -- Add chart/commitments/most-popular-date/ICS API methods

## Implementation Details

### 1. API Service Methods

**File**: `gravitycar-frontend/src/services/api.ts`

Add the following methods to the `ApiService` class:

```typescript
// Chart data
async getEventChart(eventId: string): Promise<ApiResponse<ChartData>> {
  const response = await this.api.get(`/api/events/${eventId}/chart`);
  return response.data;
}

// Upsert commitments
async upsertCommitments(
  eventId: string,
  commitments: Array<{ proposed_date_id: string; is_available: boolean }>
): Promise<ApiResponse<{ created: number; updated: number }>> {
  const response = await this.api.put(
    `/api/events/${eventId}/commitments`,
    { commitments }
  );
  return response.data;
}

// Accept all dates
async acceptAllDates(
  eventId: string
): Promise<ApiResponse<{ created: number; updated: number }>> {
  const response = await this.api.post(`/api/events/${eventId}/accept-all`);
  return response.data;
}

// Most popular date(s)
async getMostPopularDate(
  eventId: string
): Promise<ApiResponse<MostPopularDateData>> {
  const response = await this.api.get(`/api/events/${eventId}/most-popular-date`);
  return response.data;
}

// ICS download (returns blob URL)
async downloadIcs(eventId: string): Promise<void> {
  const response = await this.api.get(`/api/events/${eventId}/ics`, {
    responseType: 'blob',
  });
  const blob = new Blob([response.data], { type: 'text/calendar' });
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'event.ics';
  link.click();
  window.URL.revokeObjectURL(url);
}
```

### 2. TypeScript Interfaces

**File**: `gravitycar-frontend/src/types/index.ts` (add to existing file)

```typescript
export interface ChartEvent {
  id: string;
  name: string;
  description: string | null;
  location: string | null;
  duration_hours: number;
  accepted_date: string | null;
  linked_model_name: string | null;
  linked_record_id: string | null;
  created_by: string;
}

export interface ProposedDate {
  id: string;
  proposed_date: string;
}

export interface ChartUser {
  id: string;
  [key: string]: string; // dynamic display columns
}

export interface ChartData {
  event: ChartEvent;
  proposed_dates: ProposedDate[];
  users: ChartUser[];
  user_display_columns: string[];
  commitments: Record<string, boolean>; // "userId:proposedDateId" -> boolean
  current_user_id: string | null;
  is_admin: boolean;
}

export interface MostPopularDateEntry {
  proposed_date_id: string;
  proposed_date: string;
  vote_count: number;
}

export interface MostPopularDateData {
  event_id: string;
  most_popular_dates: MostPopularDateEntry[];
  tied: boolean;
}
```

### 3. Route Registration

**File**: `gravitycar-frontend/src/App.tsx`

Add a new route BEFORE the `/:modelName` catch-all. The chart page is accessible to both authenticated and unauthenticated users (guests get read-only), so it does NOT use `ProtectedRoute`.

```tsx
import ChartOfGoodness from './pages/ChartOfGoodness';

// Add above the /:modelName catch-all route:
<Route
  path="/events/:eventId/chart"
  element={
    <Layout>
      <ChartOfGoodness />
    </Layout>
  }
/>
```

**Key decision**: No `ProtectedRoute` wrapper because guests (unauthenticated) can view the chart in read-only mode per AC-14. The `Layout` wrapper still applies for consistent page structure; `NavigationSidebar` already conditionally hides when `isAuthenticated` is false.

### 4. ChartOfGoodness Page Component

**File**: `gravitycar-frontend/src/pages/ChartOfGoodness.tsx`

**Imports**:
```typescript
import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { apiService } from '../services/api';
import { getUserTimezone, formatDateTimeInTimezone } from '../utils/timezone';
import type { ChartData, MostPopularDateData } from '../types';
```

**Component structure** (functional component with hooks):

```typescript
const ChartOfGoodness: React.FC = () => {
  const { eventId } = useParams<{ eventId: string }>();
  const { user, isAuthenticated } = useAuth();
  const userTimezone = getUserTimezone(user?.user_timezone);
```

**State:**
```typescript
const [chartData, setChartData] = useState<ChartData | null>(null);
const [popularData, setPopularData] = useState<MostPopularDateData | null>(null);
const [loading, setLoading] = useState(true);
const [error, setError] = useState<string | null>(null);
const [savingCells, setSavingCells] = useState<Set<string>>(new Set());
```

**Data fetching** (useEffect on mount + eventId change):

```typescript
const fetchData = useCallback(async () => {
  if (!eventId) return;
  setLoading(true);
  setError(null);
  try {
    const [chartResponse, popularResponse] = await Promise.all([
      apiService.getEventChart(eventId),
      apiService.getMostPopularDate(eventId),
    ]);
    setChartData(chartResponse.data);
    setPopularData(popularResponse.data);
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Failed to load chart';
    setError(message);
  } finally {
    setLoading(false);
  }
}, [eventId]);

useEffect(() => { fetchData(); }, [fetchData]);
```

**Toggle handler** (optimistic UI with rollback):

```typescript
const handleToggle = async (proposedDateId: string) => {
  if (!chartData || !chartData.current_user_id || !eventId) return;
  const cellKey = `${chartData.current_user_id}:${proposedDateId}`;
  const currentValue = chartData.commitments[cellKey] ?? false;
  const newValue = !currentValue;

  // Optimistic update
  setChartData(prev => {
    if (!prev) return prev;
    return {
      ...prev,
      commitments: { ...prev.commitments, [cellKey]: newValue },
    };
  });
  setSavingCells(prev => new Set(prev).add(cellKey));

  try {
    await apiService.upsertCommitments(eventId, [
      { proposed_date_id: proposedDateId, is_available: newValue },
    ]);
    // Refresh popular date after toggle
    const popularResponse = await apiService.getMostPopularDate(eventId);
    setPopularData(popularResponse.data);
  } catch {
    // Rollback on failure
    setChartData(prev => {
      if (!prev) return prev;
      return {
        ...prev,
        commitments: { ...prev.commitments, [cellKey]: currentValue },
      };
    });
  } finally {
    setSavingCells(prev => {
      const next = new Set(prev);
      next.delete(cellKey);
      return next;
    });
  }
};
```

**Accept All handler:**

```typescript
const handleAcceptAll = async () => {
  if (!chartData || !chartData.current_user_id || !eventId) return;
  try {
    await apiService.acceptAllDates(eventId);
    await fetchData(); // Reload everything
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Failed to accept all';
    setError(message);
  }
};
```

**ICS Download handler:**

```typescript
const handleIcsDownload = async () => {
  if (!eventId) return;
  try {
    await apiService.downloadIcs(eventId);
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Failed to download ICS';
    setError(message);
  }
};
```

**User display name helper** (uses displayColumns from API, per AC-18):

```typescript
const getUserDisplayName = (chartUser: ChartUser): string => {
  if (!chartData) return '';
  return chartData.user_display_columns
    .map(col => chartUser[col] ?? '')
    .filter(Boolean)
    .join(' ');
};
```

**Date formatting helper (timezone-aware via shared utility from Plan-02):**

```typescript
const formatProposedDate = (dateStr: string): { dayOfWeek: string; monthDay: string; time: string } => {
  const date = new Date(dateStr);
  const dayOfWeek = new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    timeZone: userTimezone,
  }).format(date);
  const monthDay = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    timeZone: userTimezone,
  }).format(date);
  const time = new Intl.DateTimeFormat('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: userTimezone,
  }).format(date);
  return { dayOfWeek, monthDay, time };
};

const formatFullDate = (dateStr: string): string => {
  return formatDateTimeInTimezone(dateStr, userTimezone);
};
```

**Key decision**: All date formatting in the Chart of Goodness uses `user_timezone` from `useAuth()` via the shared `getUserTimezone()` utility (from `gravitycar-frontend/src/utils/timezone.ts`, defined in Plan-02), with `Intl.DateTimeFormat` for timezone-aware rendering. This ensures consistent timezone handling across the DateTimePicker and ChartOfGoodness components.

**JSX Render Structure:**

```tsx
if (loading) return <div className="p-8 text-center text-gray-500">Loading chart...</div>;
if (error) return <div className="p-8 text-center text-red-600">{error}</div>;
if (!chartData) return <div className="p-8 text-center text-gray-500">No chart data available.</div>;

const { event, proposed_dates, users, commitments, current_user_id, is_admin } = chartData;
const isGuest = !isAuthenticated;
const canEdit = !isGuest && current_user_id !== null;

return (
  <div className="p-4 sm:p-6 lg:p-8 max-w-full">
    {/* Header Area */}
    <EventHeader event={event} />

    {/* Accepted Date Banner */}
    {event.accepted_date && <AcceptedDateBanner ... />}

    {/* Most Popular Date Banner */}
    {popularData && popularData.most_popular_dates.length > 0 && !event.accepted_date && (
      <MostPopularBanner ... />
    )}

    {/* Accept All button */}
    {canEdit && !event.accepted_date && (
      <button onClick={handleAcceptAll} className="...">Accept All Dates</button>
    )}

    {/* Grid Table */}
    <ChartGrid ... />

    {/* Admin Controls */}
    {is_admin && <AdminControls ... />}
  </div>
);
```

**Key decision**: The component is structured as one page file with inline sub-sections rather than separate component files. The page is under 300 lines. If it exceeds the limit, the grid, banners, and admin controls can be extracted to `gravitycar-frontend/src/components/events/` sub-components.

### 5. Inline Sub-Sections (rendered within ChartOfGoodness)

**Event Header** (inline JSX block):
- Displays `event.name` as h1, `event.description` as paragraph, `event.location` with a map-pin icon.
- If `event.linked_model_name` and `event.linked_record_id` exist, render a link. Linked record image display is deferred to Item 18 (Model Linking UI).

**Accepted Date Banner:**
- Green background banner showing the accepted date formatted nicely.
- "Export to Calendar (.ics)" button calling `handleIcsDownload`.
- Only shown when `event.accepted_date` is not null.

```tsx
<div className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center justify-between">
  <div>
    <span className="font-semibold text-green-800">Accepted Date: </span>
    <span className="text-green-700">{formatFullDate(event.accepted_date)}</span>
  </div>
  <button onClick={handleIcsDownload}
    className="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
    Export to Calendar (.ics)
  </button>
</div>
```

**Most Popular Date Banner:**
- Blue/yellow background banner above the grid.
- Shows all tied dates when `popularData.tied` is true.
- Format: "Most popular: Sat Mar 14 @ 7pm (5 votes)" or "Most popular: Sat Mar 14 @ 7pm, Sun Mar 15 @ 7pm (tied, 5 votes each)".

```tsx
<div className="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
  <span className="font-semibold text-blue-800">Most Popular: </span>
  <span className="text-blue-700">
    {popularData.most_popular_dates.map(d => formatShortDate(d.proposed_date)).join(', ')}
    {popularData.tied
      ? ` (tied, ${popularData.most_popular_dates[0].vote_count} votes each)`
      : ` (${popularData.most_popular_dates[0].vote_count} votes)`}
  </span>
</div>
```

**Chart Grid** (the core table):

```tsx
<div className="overflow-x-auto">
  <table className="min-w-full border-collapse border border-gray-300">
    <thead>
      <tr>
        <th className="border border-gray-300 p-2 bg-gray-50 sticky left-0 z-10">Guest</th>
        {proposed_dates.map(pd => {
          const fmt = formatProposedDate(pd.proposed_date);
          return (
            <th key={pd.id} className="border border-gray-300 p-2 bg-gray-50 text-center min-w-[100px]">
              <div className="text-xs text-gray-500">{fmt.dayOfWeek}</div>
              <div className="text-sm font-medium">{fmt.monthDay}</div>
              <div className="text-xs text-gray-500">{fmt.time}</div>
              {is_admin && !event.accepted_date && (
                <button className="mt-1 text-xs text-indigo-600 hover:underline"
                  onClick={() => handleSetAcceptedDate(pd.id)}>
                  Set as Accepted
                </button>
              )}
            </th>
          );
        })}
      </tr>
    </thead>
    <tbody>
      {users.map(u => {
        const isCurrentUser = u.id === current_user_id;
        return (
          <tr key={u.id} className={isCurrentUser ? 'bg-yellow-50' : ''}>
            <td className="border border-gray-300 p-2 font-medium sticky left-0 bg-white z-10">
              {getUserDisplayName(u)}
            </td>
            {proposed_dates.map(pd => {
              const cellKey = `${u.id}:${pd.id}`;
              const isAvailable = commitments[cellKey] ?? false;
              const isSaving = savingCells.has(cellKey);

              return (
                <td key={pd.id} className="border border-gray-300 p-2 text-center">
                  {isCurrentUser && canEdit && !event.accepted_date ? (
                    <button
                      onClick={() => handleToggle(pd.id)}
                      disabled={isSaving}
                      className={`w-8 h-8 rounded ${
                        isAvailable ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400'
                      } ${isSaving ? 'opacity-50' : 'hover:opacity-80'}`}
                    >
                      {isAvailable ? '✓' : ''}
                    </button>
                  ) : (
                    <span className={`inline-block w-8 h-8 rounded ${
                      isAvailable ? 'bg-green-500' : 'bg-gray-200'
                    }`} />
                  )}
                </td>
              );
            })}
          </tr>
        );
      })}
    </tbody>
  </table>
</div>
```

**Key decisions for the grid:**
- Current user's row is highlighted with `bg-yellow-50`.
- Current user's cells render as clickable buttons (toggle checkboxes). Others render as read-only colored squares.
- Guest mode (`isGuest`): no editable cells, no Accept All, no admin controls.
- When `event.accepted_date` is set, all cells become read-only (event is finalized).
- `overflow-x-auto` on the table wrapper enables horizontal scrolling on narrow viewports (responsiveness requirement).
- First column (Guest name) uses `sticky left-0` to stay visible during horizontal scroll.

**Admin Controls:**
- Shown only when `is_admin` is true.
- "Set Accepted Date" button in each column header (shown above in grid).
- Links to manage proposed dates, invitations, and reminders (navigate to GenericCrudPage for those models).

```tsx
{is_admin && (
  <div className="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
    <h3 className="font-semibold text-gray-700 mb-2">Admin Controls</h3>
    <div className="flex gap-4 flex-wrap">
      <a href={`/event_proposed_dates?event_id=${event.id}`}
        className="text-indigo-600 hover:underline text-sm">Manage Proposed Dates</a>
      <a href={`/event_invitations?event_id=${event.id}`}
        className="text-indigo-600 hover:underline text-sm">Manage Invitations</a>
      <a href={`/event_reminders?event_id=${event.id}`}
        className="text-indigo-600 hover:underline text-sm">Manage Reminders</a>
    </div>
  </div>
)}
```

**Set Accepted Date handler (admin only):**

```typescript
const handleSetAcceptedDate = async (proposedDateId: string) => {
  if (!eventId || !is_admin) return;
  if (!window.confirm('Set this as the accepted date for the event?')) return;
  try {
    await apiService.setAcceptedDate(eventId, proposedDateId);
    await fetchData();
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Failed to set accepted date';
    setError(message);
  }
};
```

This requires one additional API method:

```typescript
// In ApiService class
async setAcceptedDate(eventId: string, proposedDateId: string): Promise<ApiResponse<unknown>> {
  const response = await this.api.put(
    `/api/events/${eventId}/accepted-date`,
    { proposed_date_id: proposedDateId }
  );
  return response.data;
}
```

### 6. Responsive Layout

- The grid uses `overflow-x-auto` for horizontal scrolling on narrow viewports.
- The first column (Guest names) is `sticky left-0` so it remains visible when scrolling.
- Banners and header stack vertically and use responsive padding (`p-4 sm:p-6 lg:p-8`).
- No card-based alternate layout is implemented initially; horizontal scroll is the primary responsive strategy. A card layout can be added later if user testing reveals issues.

## Error Handling

- **Network errors on chart load**: Display error message in red text, replacing the grid.
- **Toggle save failure**: Rollback optimistic update, cell returns to previous state. No alert; the visual rollback communicates the failure.
- **Accept All failure**: Display error message above the grid.
- **ICS download failure**: Display error message.
- **Set Accepted Date failure**: Display error message.
- **Missing eventId URL param**: Show "No chart data available" message (eventId from useParams is undefined).

## Unit Test Specifications

**File**: `gravitycar-frontend/src/pages/__tests__/ChartOfGoodness.test.tsx`

Use React Testing Library (`@testing-library/react`) with jest mocks for apiService and useAuth.

### Rendering States

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Loading state | Mock API with pending promise | Shows "Loading chart..." | Loading indicator |
| Error state | Mock API rejects | Shows error message in red | Error display |
| Empty event | Chart data with no dates/users | Shows header but empty grid | Edge case |
| Full chart renders | Chart data with 2 dates, 2 users | Table has 2 column headers + 2 rows | Core grid render |

### User Interactions

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Toggle own cell | Click checkbox in current user row | API upsertCommitments called, cell toggles | AC-5 |
| Cannot toggle other user cell | Other user's row | No button element, only read-only indicator | AC-15 |
| Accept All button | Click Accept All | API acceptAllDates called, data refreshes | AC-6 |
| ICS download | Click Export to Calendar | API downloadIcs called | AC-9 |

### Banners

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Most popular single | popularData with 1 date | Banner shows date + vote count, no "tied" | AC-7 |
| Most popular tied | popularData with 2 dates, tied=true | Banner shows both dates + "tied, N votes each" | AC-7 |
| Accepted date banner | event.accepted_date set | Green banner with date + ICS button | AC-8 |
| No popular banner when accepted | accepted_date set | Most popular banner hidden | UI clarity |

### Guest Mode (AC-14)

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Guest sees grid | isAuthenticated=false, chart loads | Grid renders with read-only indicators | AC-14 |
| Guest has no Accept All | isAuthenticated=false | No Accept All button | Guest read-only |
| Guest has no admin controls | isAuthenticated=false | No admin controls section | Guest read-only |

### Admin Controls

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Admin sees controls | is_admin=true in chart data | Admin Controls section renders | Admin UI |
| Non-admin no controls | is_admin=false | No Admin Controls section | Role-gated |
| Set Accepted Date | Admin clicks Set as Accepted, confirms | API setAcceptedDate called | AC-8 |

### Display Names (AC-18)

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Display columns render | user_display_columns=['first_name','last_name'], user has both | Row shows "Alice Smith" | AC-18 |
| Single display column | user_display_columns=['username'], user has username | Row shows "alice123" | AC-18 fallback |

### Key Scenario: Toggle and Rollback

**Setup**: Render ChartOfGoodness with authenticated user (usr-1). Chart data has usr-1 with pd-1 = false. Mock `upsertCommitments` to reject.
**Action**: Click the toggle button in usr-1's pd-1 cell.
**Expected**: Cell optimistically shows green/checked. After rejection, cell rolls back to gray/unchecked.

### Key Scenario: Guest Read-Only View

**Setup**: Render ChartOfGoodness with `useAuth` returning `{ user: null, isAuthenticated: false }`. Mock `getEventChart` returning valid chart data with `current_user_id: null, is_admin: false`.
**Action**: Component renders.
**Expected**: Grid renders all cells as read-only indicators (no buttons). No "Accept All" button. No admin controls section. Header and banners still display.

## Notes

- The component does NOT use `useModelMetadata` since the chart page is a custom page, not a GenericCrudPage. All data comes from the chart and most-popular-date API endpoints.
- The `user_display_columns` array from the chart API response is used to construct display names dynamically per AC-18. No hardcoded name format.
- Optimistic UI on toggle provides instant feedback. Only the toggled cell shows a saving state (reduced opacity). Rollback on failure is silent.
- The `setAcceptedDate` API method (plan-11) is called from the admin "Set as Accepted" button. The confirmation dialog prevents accidental clicks.
- When `accepted_date` is set, all cells become read-only and the Accept All button is hidden. The chart becomes an archival view.
- The ICS download uses a blob response and triggers a browser download rather than opening a new tab.
- Linked record display in the header (image from linked model) is deferred to Item 18 (Model Linking UI). The header renders event info only.
- All date formatting uses `Intl.DateTimeFormat` with the `user_timezone` from `useAuth()` (via the shared `getUserTimezone()` utility from `gravitycar-frontend/src/utils/timezone.ts`, defined in Plan-02). This ensures dates in the chart grid, banners, and accepted date display are rendered in the user's configured timezone, consistent with the DateTimePicker component.
