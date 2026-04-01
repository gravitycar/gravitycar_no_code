# Implementation Plan: DateTime Timezone Support - Frontend

## Spec Context

The specification requires all DateTime values to be stored in UTC in the database but displayed to users in their configured timezone. The `user_timezone` field already exists on the TypeScript `User` interface (line 18 of `gravitycar-frontend/src/types/index.ts`) and will be populated by the backend after catalog item 1 is complete. This plan updates the `DateTimePicker` component to perform UTC-to-local and local-to-UTC conversions using the authenticated user's timezone.

Catalog item: 2 - DateTime Timezone Support - Frontend
Specification section: Framework Enhancement: DateTime Timezone Support
Acceptance criteria addressed: AC-20 (frontend half)

## Dependencies

- **Blocked by**: Item 1 (DateTime Timezone Support - Backend) -- `user_timezone` must be included in `AuthenticationService.formatUserData()` so it flows through `useAuth().user.user_timezone`.
- **Uses**: `useAuth` hook (`gravitycar-frontend/src/hooks/useAuth.tsx`), `User` interface (`gravitycar-frontend/src/types/index.ts`), the browser's `Intl.DateTimeFormat` API for timezone conversion.

## File Changes

### Modified Files
- `gravitycar-frontend/src/components/fields/DateTimePicker.tsx` -- Add timezone conversion logic using `useAuth()` to obtain the user's timezone. Convert UTC values to user timezone on display, convert user-local input back to UTC on save. Import shared utilities from `gravitycar-frontend/src/utils/timezone.ts`.

### New Files
- `gravitycar-frontend/src/utils/timezone.ts` -- Shared timezone utility functions (`localDateTimeToUTC`, `formatDateTimeInTimezone`, `getUserTimezone`) used by both DateTimePicker and ChartOfGoodness.
- `gravitycar-frontend/src/components/fields/__tests__/DateTimePicker.test.tsx` -- Unit tests for timezone conversion behavior.

## Implementation Details

### DateTimePicker.tsx

**File**: `gravitycar-frontend/src/components/fields/DateTimePicker.tsx`

**Summary of changes**:

1. Import `useAuth` from `../../hooks/useAuth`.
2. Extract a `getUserTimezone()` helper that reads `useAuth().user?.user_timezone` and falls back to `Intl.DateTimeFormat().resolvedOptions().timeZone` (browser default) when the user is not authenticated or `user_timezone` is empty.
3. Rewrite `formatDateTimeForInput()` to convert a UTC ISO string to the user's local timezone before formatting as `YYYY-MM-DDTHH:mm`.
4. Rewrite `handleDateTimeChange()` to interpret the `datetime-local` input value as being in the user's timezone and convert it to a UTC ISO string before calling `onChange()`.
5. Rewrite `formatDisplayValue()` to render the date/time in the user's timezone using `Intl.DateTimeFormat`.

**Key design decision**: Use the `Intl.DateTimeFormat` API exclusively for timezone conversion. This avoids adding a third-party library (like `date-fns-tz` or `luxon`) and keeps the bundle lean. The `Intl` API is supported in all modern browsers.

**Timezone conversion approach**:

```typescript
import { useAuth } from '../../hooks/useAuth';

// Inside the component:
const { user } = useAuth();
const userTimezone = user?.user_timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
```

**UTC to user timezone (for display in input)**:

```typescript
const formatDateTimeForInput = (dateTimeValue: any): string => {
  if (!dateTimeValue) return '';

  try {
    const date = new Date(dateTimeValue);
    if (isNaN(date.getTime())) return '';

    // Format the UTC date in the user's timezone
    const formatter = new Intl.DateTimeFormat('en-CA', {
      timeZone: userTimezone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });

    const parts = formatter.formatToParts(date);
    const get = (type: string) => parts.find(p => p.type === type)?.value || '';
    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
  } catch {
    return '';
  }
};
```

**User timezone to UTC (on save)**:

The `datetime-local` input gives us a string like `"2026-06-15T19:00"` which represents the user's local time in their configured timezone. We need to convert this to UTC:

```typescript
const handleDateTimeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
  const dateTimeValue = e.target.value;
  if (dateTimeValue === '') {
    onChange(null);
    return;
  }

  // dateTimeValue is in the user's timezone. Convert to UTC.
  const utcDate = localDateTimeToUTC(dateTimeValue, userTimezone);
  onChange(utcDate.toISOString());
};
```

The `localDateTimeToUTC` helper creates a Date by computing the UTC offset for the target timezone:

```typescript
/**
 * Convert a "YYYY-MM-DDTHH:mm" string (interpreted in targetTimezone) to a UTC Date.
 * Uses Intl.DateTimeFormat to determine the offset of targetTimezone at that moment.
 */
const localDateTimeToUTC = (localDateTimeStr: string, targetTimezone: string): Date => {
  // Step 1: Parse as if it were UTC (just to get a reference point)
  const naiveDate = new Date(localDateTimeStr + 'Z');

  // Step 2: Format that same instant in the target timezone to find offset
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: targetTimezone,
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hour12: false,
  });

  const parts = formatter.formatToParts(naiveDate);
  const get = (type: string) => parseInt(parts.find(p => p.type === type)?.value || '0', 10);

  const tzDate = new Date(Date.UTC(
    get('year'), get('month') - 1, get('day'),
    get('hour'), get('minute'), get('second')
  ));

  // The offset (in ms) is the difference between the UTC instant and how it looks in targetTimezone
  const offsetMs = tzDate.getTime() - naiveDate.getTime();

  // Step 3: Parse the local datetime string as UTC, then subtract the offset to get true UTC
  const localAsUTC = new Date(localDateTimeStr + 'Z');
  return new Date(localAsUTC.getTime() - offsetMs);
};
```

**Read-only display formatting**:

```typescript
const formatDisplayValue = (dateTimeValue: any): string => {
  if (!dateTimeValue) return '-';

  try {
    const date = new Date(dateTimeValue);
    if (isNaN(date.getTime())) return String(dateTimeValue);

    return date.toLocaleString(undefined, {
      timeZone: userTimezone,
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  } catch {
    return String(dateTimeValue);
  }
};
```

**Complete component structure** (unchanged parts stay the same):
- The JSX template remains identical. No changes to the HTML structure, CSS classes, labels, error display, or help text.
- The only changes are: (a) the `useAuth` import and call at the top of the component, (b) the `userTimezone` derivation, (c) the three rewritten functions above, and (d) the `localDateTimeToUTC` helper defined inside the component or extracted above the component as a module-level function.

**Placement of `localDateTimeToUTC`**: Extract this and other timezone utilities into a shared module at `gravitycar-frontend/src/utils/timezone.ts` so they can be imported by both DateTimePicker and ChartOfGoodness (plan-16).

### Shared Timezone Utility

**File**: `gravitycar-frontend/src/utils/timezone.ts`

This module exports the following shared functions:

```typescript
/**
 * Get the user's configured timezone, falling back to browser default.
 */
export const getUserTimezone = (userTimezone?: string | null): string => {
  return userTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
};

/**
 * Convert a "YYYY-MM-DDTHH:mm" string (interpreted in targetTimezone) to a UTC Date.
 */
export const localDateTimeToUTC = (localDateTimeStr: string, targetTimezone: string): Date => {
  // ... (same implementation as shown above)
};

/**
 * Format a UTC ISO date string for display in the given timezone.
 * Returns a formatted string using Intl.DateTimeFormat.
 */
export const formatDateTimeInTimezone = (
  utcDateStr: string,
  timezone: string,
  options?: Intl.DateTimeFormatOptions
): string => {
  const date = new Date(utcDateStr);
  if (isNaN(date.getTime())) return String(utcDateStr);

  const defaultOptions: Intl.DateTimeFormatOptions = {
    timeZone: timezone,
    dateStyle: 'medium',
    timeStyle: 'short',
  };

  return new Intl.DateTimeFormat(undefined, { ...defaultOptions, ...options }).format(date);
};

/**
 * Format a UTC date for use in datetime-local input (YYYY-MM-DDTHH:mm) in the user's timezone.
 */
export const formatDateTimeForInput = (utcDateStr: string, timezone: string): string => {
  // ... (same implementation as formatDateTimeForInput above, using timezone parameter)
};
```

DateTimePicker imports from this module:
```typescript
import { getUserTimezone, localDateTimeToUTC, formatDateTimeForInput, formatDateTimeInTimezone } from '../../utils/timezone';
```

## Error Handling

- **Invalid timezone string in `user_timezone`**: If `Intl.DateTimeFormat` throws a `RangeError` for an invalid timezone, the `try/catch` blocks in each function will gracefully fall back to returning empty string (for input) or the raw string value (for display). This prevents the component from crashing.
- **Unauthenticated user (guest)**: When `user` is null, `userTimezone` falls back to the browser's detected timezone via `Intl.DateTimeFormat().resolvedOptions().timeZone`. Guests still see dates in their local timezone.
- **Null/undefined/empty datetime value**: All three functions check for falsy values at the top and return appropriate defaults (`''` or `'-'`).

## Unit Test Specifications

**File**: `gravitycar-frontend/src/components/fields/__tests__/DateTimePicker.test.tsx`

**Test framework**: Use the project's existing test setup (Vitest + React Testing Library, or Jest -- follow whatever `gravitycar-frontend/package.json` configures).

**Mocking**: Mock `useAuth` to return controlled `user` objects with specific `user_timezone` values.

```typescript
jest.mock('../../../hooks/useAuth', () => ({
  useAuth: jest.fn(),
}));
```

### `localDateTimeToUTC()` (exported helper)

| Case | Input | Timezone | Expected | Why |
|------|-------|----------|----------|-----|
| US Eastern standard time | `"2026-01-15T14:00"` | `"America/New_York"` | `2026-01-15T19:00:00.000Z` | EST is UTC-5 |
| US Eastern daylight time | `"2026-06-15T14:00"` | `"America/New_York"` | `2026-06-15T18:00:00.000Z` | EDT is UTC-4 |
| UTC timezone | `"2026-06-15T14:00"` | `"UTC"` | `2026-06-15T14:00:00.000Z` | No offset |
| Asia/Tokyo | `"2026-06-15T14:00"` | `"Asia/Tokyo"` | `2026-06-15T05:00:00.000Z` | JST is UTC+9 |

### `formatDateTimeForInput()` (via rendered component)

| Case | Stored UTC Value | User Timezone | Expected Input Value | Why |
|------|-----------------|---------------|---------------------|-----|
| EST conversion | `"2026-01-15T19:00:00.000Z"` | `"America/New_York"` | `"2026-01-15T14:00"` | UTC-5 in winter |
| EDT conversion | `"2026-06-15T18:00:00.000Z"` | `"America/New_York"` | `"2026-06-15T14:00"` | UTC-4 in summer |
| Null value | `null` | `"America/New_York"` | `""` | Empty for null |
| Invalid date | `"not-a-date"` | `"America/New_York"` | `""` | Graceful fallback |

### `formatDisplayValue()` (read-only mode)

| Case | Stored UTC Value | User Timezone | Expected Contains | Why |
|------|-----------------|---------------|-------------------|-----|
| Renders in user tz | `"2026-01-15T19:00:00.000Z"` | `"America/New_York"` | Contains "Jan 15, 2026" and "2:00 PM" (or locale equivalent) | Correct tz conversion |
| Null value | `null` | any | `"-"` | Dash for missing dates |

### Component integration tests

| Case | Action | Expected | Why |
|------|--------|----------|-----|
| Displays converted time | Render with UTC value + mocked timezone | Input shows local time | UTC-to-local display works |
| Saves as UTC | User picks a datetime in the input | `onChange` called with UTC ISO string | Local-to-UTC save works |
| Guest fallback | Render with no authenticated user | Uses browser timezone (no crash) | Graceful unauthenticated behavior |
| Invalid timezone | Mock `user_timezone` = `"Invalid/Zone"` | Component renders without crashing | Error resilience |

### Key Scenario: Round-trip conversion

**Setup**: Mock `useAuth` returning `user.user_timezone = "America/Los_Angeles"`.
**Action**: Render DateTimePicker with `value="2026-07-04T03:00:00.000Z"`. Verify input shows `"2026-07-03T20:00"` (PDT = UTC-7). Then simulate user changing the input to `"2026-07-04T12:00"`. Verify `onChange` is called with `"2026-07-04T19:00:00.000Z"`.
**Expected**: The round-trip preserves correctness -- display converts UTC to local, save converts local back to UTC.

## Notes

- The `datetime-local` HTML input does not have a concept of timezone -- it simply shows/accepts `YYYY-MM-DDTHH:mm`. Our conversion logic bridges this gap by interpreting the input value in the user's timezone.
- The `Intl.DateTimeFormat` API handles daylight saving time transitions correctly because it uses the IANA timezone database.
- The `'en-CA'` locale is used for `formatToParts` because it produces `YYYY-MM-DD` date format, which simplifies parsing. This is only for the internal conversion; the display format uses the user's locale.
- No new npm dependencies are required.
- The `localDateTimeToUTC` function should be exported from the module so tests can import and test it directly, in addition to testing through the component.
