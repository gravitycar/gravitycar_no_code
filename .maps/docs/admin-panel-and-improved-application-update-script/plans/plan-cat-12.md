# Implementation Plan: CAT-12 — handleAuthError Shared Utility

## Spec Context

The axios interceptor in `services/api.ts` currently duplicates 401/403 redirect logic in two places within the same interceptor (once for structured backend error responses, once for raw HTTP status codes — lines ~66-135). The new SSE streaming call in `ConfirmRebuildModal` uses `fetch` rather than axios, so it cannot rely on the axios interceptor at all and must handle 401/403 itself before reading the response body. This plan extracts the shared redirect logic into a single `handleAuthError(status)` utility that both the axios interceptor and the fetch path can call.

Catalog item: CAT-12  
Specification section: AC-31a; Constraint "Do NOT bypass the 401/403 redirect logic when using fetch; extract it into a shared utility"  
Acceptance criteria addressed:
- AC-31a: Before reading the SSE stream, the UI checks `response.status`. A 401 clears localStorage and redirects to `/login?redirect=<current path>`; a 403 navigates to `/unauthorized?redirect=<current path>` using `imperativeNavigate`. Both reuse `handleAuthError(status)` extracted from the axios interceptor.

---

## Dependencies

- **Blocked by**: none
- **Blocks**: CAT-14 (ConfirmRebuildModal calls `handleAuthError`)
- **Uses**:
  - `gravitycar-frontend/src/utils/navigate.ts` — exports `imperativeNavigate(path, options?)`
  - `gravitycar-frontend/src/services/api.ts` — existing interceptor to be refactored (import new utility)

---

## File Changes

### New Files
- `gravitycar-frontend/src/utils/authError.ts` — exports `handleAuthError(status: number): void`

### Modified Files
- `gravitycar-frontend/src/services/api.ts` — refactor the 401/403 branches in both interceptor paths to call `handleAuthError(status)` instead of duplicating the redirect logic inline

---

## Implementation Details

### `gravitycar-frontend/src/utils/authError.ts`

**Exports**:
- `handleAuthError(status: number): void` — named export, handles 401 and 403 statuses

**Behaviour by status**:

| Status | Action |
|--------|--------|
| 401 | Show `alert("Session expired. Please log in again.")`. Remove `auth_token` and `user` keys from `localStorage`. Build redirect param from `window.location.pathname + window.location.search`. Set `window.location.href = '/login?redirect=' + encodeURIComponent(redirect)` (hard reload — ensures React auth state is fully cleared, not soft navigation). |
| 403 | Guard: if `window.location.pathname !== '/unauthorized'`. Build redirect param. Call `imperativeNavigate('/unauthorized?redirect=' + encodeURIComponent(redirect), { replace: true })`. Uses `imperativeNavigate` (soft nav) because the user IS authenticated — only their role is insufficient. |
| anything else | No-op — function returns without side effects. |

**Code Example**:

```typescript
import { imperativeNavigate } from './navigate';

/**
 * Handles 401 (unauthenticated) and 403 (forbidden) HTTP status codes by
 * clearing auth state and navigating to the appropriate error page.
 *
 * Used by:
 * - The axios interceptor in services/api.ts (replaces duplicated inline logic)
 * - ConfirmRebuildModal before reading the SSE stream body
 *
 * For status codes other than 401 and 403, this function is a no-op.
 */
export function handleAuthError(status: number): void {
  const currentPath = window.location.pathname + window.location.search;
  const encodedRedirect = encodeURIComponent(currentPath);

  if (status === 401) {
    alert('Session expired. Please log in again.');
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    // Hard reload via window.location.href ensures React auth state is fully cleared.
    // imperativeNavigate (soft nav) would leave stale in-memory auth state in React context.
    window.location.href = `/login?redirect=${encodedRedirect}`;
    return;
  }

  if (status === 403) {
    if (window.location.pathname !== '/unauthorized') {
      // Soft nav for 403: user IS authenticated, only role is insufficient.
      imperativeNavigate(`/unauthorized?redirect=${encodedRedirect}`, { replace: true });
    }
    return;
  }
}
```

**Import needed**:
```typescript
import { imperativeNavigate } from './navigate';
```

---

### Refactoring `gravitycar-frontend/src/services/api.ts`

The existing interceptor has two parallel 401/403 handling paths. Both are replaced with a call to `handleAuthError`.

**Add import** at top of file (alongside existing `imperativeNavigate` import):
```typescript
import { handleAuthError } from '../utils/authError';
```

**Path 1 — structured backend error response** (lines ~66-90, inside `if (isBackendErrorResponse(...))`):

Replace the current blocks:
```typescript
// BEFORE (401 block):
if (backendError.status === 401) {
    const sessionExpired = ...;
    if (sessionExpired) { alert(...); }
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    window.location.href = '/login?redirect=' + encodeURIComponent(...);
    return Promise.reject(backendError);
}

// BEFORE (403 block):
if (backendError.status === 403) {
    if (window.location.pathname !== '/unauthorized') {
        const redirect = encodeURIComponent(...);
        imperativeNavigate(`/unauthorized?redirect=${redirect}`, { replace: true });
    }
    return Promise.reject(backendError);
}
```

With:
```typescript
// AFTER (401 block):
if (backendError.status === 401) {
    // handleAuthError(401) now includes the alert + localStorage clear + hard redirect.
    // Remove the inline alert and redirect — handleAuthError handles all of it.
    handleAuthError(401);
    return Promise.reject(backendError);
}

// AFTER (403 block):
if (backendError.status === 403) {
    handleAuthError(403);
    return Promise.reject(backendError);
}
```

**Path 2 — raw HTTP status fallback** (lines ~104-135, the `switch(status)` block):

Replace the `case 401:` and `case 403:` blocks:
```typescript
// AFTER (case 401):
case 401: {
    // handleAuthError(401) shows the alert and does the hard redirect.
    handleAuthError(401);
    break;
}

// AFTER (case 403):
case 403: {
    handleAuthError(403);
    return Promise.reject(new Error("Access denied. You don't have permission for this action."));
}
```

**Important**: The alert is now INSIDE `handleAuthError(401)` — it fires for ALL 401 responses
regardless of which code path triggered them (axios interceptor, fetch-based SSE, future callers).
The inline alert in `api.ts` must be removed to avoid double-alerting. The session-expired check
that was in the interceptor is no longer needed in `api.ts`; the generic "Session expired" alert
message in `handleAuthError` is sufficient for all 401 cases.

---

## Error Handling

- `handleAuthError` is a void function with no return value and no exceptions to throw.
- If `imperativeNavigate` has no navigator set (e.g., called before the React tree mounts), it falls back to `window.location.href = path` (see `navigate.ts` line 16) — this is acceptable.
- For any status other than 401 or 403, the function is a safe no-op.

---

## Unit Test Specifications

**Test file**: `gravitycar-frontend/src/utils/authError.test.ts`

### Setup (Vitest)

```typescript
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { handleAuthError } from './authError';
import * as navigate from './navigate';

// Mock imperativeNavigate
vi.mock('./navigate', () => ({
  imperativeNavigate: vi.fn(),
}));
```

### `handleAuthError(401)`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Shows alert | Mock `window.alert` | `alert('Session expired. Please log in again.')` called | User is informed before redirect |
| Clears auth_token | `localStorage.setItem('auth_token', 'tok')` | `localStorage.getItem('auth_token') === null` | Must clear token on 401 |
| Clears user | `localStorage.setItem('user', '{}')` | `localStorage.getItem('user') === null` | Must clear user on 401 |
| Uses hard redirect | `window.location = { pathname: '/admin', search: '' }` | `window.location.href` set to `'/login?redirect=%2Fadmin'` (NOT `imperativeNavigate`) | Hard reload clears React auth state |
| Includes search params | `window.location = { pathname: '/admin', search: '?foo=bar' }` | `window.location.href` set to `'/login?redirect=%2Fadmin%3Ffoo%3Dbar'` | Encode full path + query |

### `handleAuthError(403)`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Navigates to /unauthorized | `window.location.pathname = '/admin'` | `imperativeNavigate('/unauthorized?redirect=%2Fadmin', { replace: true })` | 403 redirects with replace |
| Does NOT clear localStorage | `localStorage.setItem('auth_token', 'tok')` | `localStorage.getItem('auth_token') === 'tok'` | 403 keeps session (user is logged in) |
| No-op when already on /unauthorized | `window.location.pathname = '/unauthorized'` | `imperativeNavigate` NOT called | Prevents redirect loop |

### `handleAuthError(other)`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| 200 — no-op | `handleAuthError(200)` | `imperativeNavigate` not called, localStorage unchanged | Only 401/403 handled |
| 500 — no-op | `handleAuthError(500)` | `imperativeNavigate` not called | Only 401/403 handled |
| 404 — no-op | `handleAuthError(404)` | `imperativeNavigate` not called | Only 401/403 handled |

### Key Scenario: api.ts interceptor uses handleAuthError for 401

**Note**: This is an integration-style check, not a unit test. The refactoring of `api.ts` is verified by confirming the import is present and the inline `localStorage.removeItem` calls in the 401 branch are removed. If a unit test is written for the interceptor, it should mock `handleAuthError` and assert it is called with status `401`.

---

## Notes

- The `alert('Session expired. Please log in again.')` is now INSIDE `handleAuthError(401)`. This ensures all 401 responses across all call sites (axios interceptor, fetch-based SSE, future callers) show the same alert without duplication. The inline alert in `api.ts`'s interceptor must be removed.
- `window.location.href` (hard reload) is used for 401 — not `imperativeNavigate` — because a hard reload fully clears React's in-memory auth context (the `useAuth` hook state). A soft nav would leave stale `isAuthenticated=true` state in memory until the next re-render cycle.
- `imperativeNavigate` IS used for 403 because the user is authenticated; only their role is insufficient. Soft navigation preserves the React session context (no re-login needed).
- `handleAuthError` does NOT return a Promise — it is synchronous. The axios interceptor continues to return `Promise.reject(...)` after calling it. The SSE fetch path checks the response status before starting to read the body, so no async handling is needed.
- TypeScript strict mode: `status` parameter is typed as `number`. No `any` usage.
- The function should be a named export (not default) so that callers import it explicitly: `import { handleAuthError } from '../utils/authError'`.
- Both the 401 localStorage removal keys (`'auth_token'` and `'user'`) must match exactly what `api.ts` and `useAuth.tsx` use. Verify the key names before finalizing — current code in `api.ts` uses `'auth_token'` and `'user'`.
