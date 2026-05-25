# Implementation Plan: 403 Interceptor and XDEBUG_TRIGGER Fix

## Spec Context

This plan implements three targeted changes to the axios response/request interceptors in `gravitycar-frontend/src/services/api.ts`:

1. **403 interceptor (new)**: When a 403 response is received, call `imperativeNavigate('/unauthorized', { replace: true })` instead of (or in addition to) returning a rejection with a generic "Access denied" message. A loop guard prevents re-navigating if the current path is already `/unauthorized`.
2. **401 interceptor (update)**: The existing 401 redirect to `/login` is updated to append `?redirect=<original-path>` so that after login the user returns to the page they were trying to access.
3. **XDEBUG_TRIGGER guard**: The request interceptor that appends `XDEBUG_TRIGGER` is wrapped in `if (import.meta.env.DEV)` so the parameter is only sent in development mode.

Catalog item: 2 — 403 Interceptor and XDEBUG_TRIGGER Fix in api.ts  
Specification sections: §4.4, §4.5, §4.11, §6 (axios Response Interceptor, Navigation Singleton Pattern)  
Acceptance criteria addressed: AC-4, AC-5, AC-11, AC-13

---

## Dependencies

- **Blocked by**: Item 1 (Navigation Singleton Module) — `imperativeNavigate` must be importable from `gravitycar-frontend/src/utils/navigate.ts` before this file can be modified
- **Blocks**: nothing — no other catalog items depend on changes to `api.ts`
- **Uses**:
  - `gravitycar-frontend/src/utils/navigate.ts` (from Item 1) — `imperativeNavigate`
  - `window.location.pathname` — for the 403 loop guard
  - `window.location.pathname + window.location.search` — appended to the 401 redirect URL
  - `import.meta.env.DEV` — Vite built-in boolean (true in dev server, false in production build)

---

## File Changes

### New Files

None.

### Modified Files

- `gravitycar-frontend/src/services/api.ts` — three surgical edits described below

---

## Implementation Details

### Change 1: Add Import for `imperativeNavigate`

**Location**: Top of file, alongside existing imports.

Add one import line after the existing `../utils/errors` import:

```typescript
import { imperativeNavigate } from '../utils/navigate';
```

The final import block at the top of the constructor should look like:

```typescript
import axios from 'axios';
import type { AxiosInstance, AxiosResponse } from 'axios';
import type { ... } from '../types';
import type { NavigationItem } from '../types/navigation';
import { ApiError, isBackendErrorResponse } from '../utils/errors';
import { imperativeNavigate } from '../utils/navigate';
```

---

### Change 2: Wrap XDEBUG_TRIGGER in `if (import.meta.env.DEV)`

**Location**: Request interceptor inside the `ApiService` constructor — lines 32–45 in the current file.

**Current code** (lines 39–43):

```typescript
      // Add XDEBUG_TRIGGER to all requests for debugging
      if (!config.params) {
        config.params = {};
      }
      config.params.XDEBUG_TRIGGER = 'mike';
```

**Replace with**:

```typescript
      // Add XDEBUG_TRIGGER only in development builds
      if (import.meta.env.DEV) {
        if (!config.params) {
          config.params = {};
        }
        config.params.XDEBUG_TRIGGER = 'mike';
      }
```

The surrounding comment is updated to reflect the new conditional. The logic is identical to before inside the `if` — just gated on `import.meta.env.DEV`.

---

### Change 3: Update 401 Handler to Append `?redirect=`

There are **two** 401 handling locations in the current file. Both must be updated.

#### 3a. Inside the `isBackendErrorResponse` branch (lines 63–77)

**Current code** (the redirect line, ~line 76):

```typescript
            window.location.href = '/login';
```

**Replace with**:

```typescript
            window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
```

The surrounding context (localStorage.removeItem calls, sessionExpired check, alert) is UNCHANGED.

#### 3b. Inside the fallback HTTP error `switch` — `case 401` block (lines 92–108)

**Current code** (the redirect line, ~line 106):

```typescript
            window.location.href = '/login';
```

**Replace with**:

```typescript
            window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
```

Again, the surrounding context (localStorage.removeItem calls, sessionExpired check, alert, message assignment) is UNCHANGED.

---

### Change 4: Update 403 Handler to Call `imperativeNavigate`

There is **one** 403 handling location in the current file — inside the fallback HTTP error `switch` block.

#### 4a. Inside the `isBackendErrorResponse` branch

The current `isBackendErrorResponse` branch (lines 59–82) handles 401 but does NOT have an explicit 403 check — 403s fall through to the generic `console.error` + `Promise.reject(backendError)` at line 80. A new explicit 403 check must be added inside this block, **before** the `console.error` line.

**Location**: Inside the `if (error.response.data && isBackendErrorResponse(error.response.data))` block, after the existing `if (backendError.status === 401)` block closes.

**Add after the 401 block**:

```typescript
          // Handle forbidden errors — navigate to /unauthorized (no localStorage clear)
          if (backendError.status === 403) {
            if (window.location.pathname !== '/unauthorized') {
              imperativeNavigate('/unauthorized', { replace: true });
            }
            return Promise.reject(backendError);
          }
```

#### 4b. Inside the fallback HTTP error `switch` — `case 403` block (line 109–111)

**Current code**:

```typescript
          case 403:
            message = 'Access denied. You don\'t have permission for this action.';
            break;
```

**Replace with**:

```typescript
          case 403:
            if (window.location.pathname !== '/unauthorized') {
              imperativeNavigate('/unauthorized', { replace: true });
            }
            return Promise.reject(new Error('Access denied. You don\'t have permission for this action.'));
```

Notes:
- The `return Promise.reject(...)` replaces the `break` — this exits early from the switch AND the interceptor callback, so the `console.error` and generic `Promise.reject(new Error(message))` at the end of the switch block are NOT reached for 403s.
- `localStorage` is NOT cleared — per AC-5 and §4.5 the user remains authenticated.
- No alert is shown — per §4.5 the interceptor navigates silently.
- The loop guard `window.location.pathname !== '/unauthorized'` prevents an infinite redirect loop per AC-13 and §4.5.

---

## Complete Picture: Response Interceptor After Changes

For clarity, here is the logical flow of the response interceptor after all changes are applied:

```
Error received
├── No response object → reject("Network error…")
├── isBackendErrorResponse(error.response.data) == true
│   ├── status 401 → clear localStorage, redirect to /login?redirect=…, reject(backendError)
│   ├── status 403 → if not already on /unauthorized, imperativeNavigate('/unauthorized', { replace: true })
│   │                  → reject(backendError)
│   └── other → console.error, reject(backendError)
└── fallback switch on error.response.status
    ├── 400 → reject(Error("Bad request…"))
    ├── 401 → clear localStorage, redirect to /login?redirect=…, reject(Error(message))
    ├── 403 → if not already on /unauthorized, imperativeNavigate('/unauthorized', {replace:true})
    │          → reject(Error("Access denied…"))
    ├── 404 → reject(Error("Resource not found."))
    └── 500 → reject(Error("Server error…"))
```

---

## Error Handling

- **`imperativeNavigate` called before `NavigatorSetter` mounts**: Falls back to `window.location.href = '/unauthorized'` — the user still reaches the right page; history `replace` semantics are lost in this narrow startup edge case. This is acceptable per §6.
- **403 loop guard**: If any API call on `/unauthorized` returns 403, the guard `window.location.pathname !== '/unauthorized'` short-circuits and only `Promise.reject` runs — no navigation occurs. AC-13 is satisfied.
- **401 redirect URL encoding**: `encodeURIComponent` is applied to the full `pathname + search` string, which correctly encodes special characters and existing query params.

---

## Unit Test Specifications

These tests are for the **interceptor logic only**. The test file should be `gravitycar-frontend/src/services/__tests__/api.interceptors.test.ts` (or alongside the existing service tests, following project conventions).

Because `ApiService` is a class that registers interceptors in its constructor, tests should instantiate `ApiService` directly (or mock `axios.create` to capture the registered interceptors) and invoke the error handler callback with mock error objects.

### Request Interceptor — XDEBUG_TRIGGER

| Case | `import.meta.env.DEV` | Input `config.params` | Expected `config.params` | Why |
|------|-----------------------|-----------------------|--------------------------|-----|
| Dev mode, no existing params | `true` | `undefined` | `{ XDEBUG_TRIGGER: 'mike' }` | Param added in dev |
| Dev mode, existing params | `true` | `{ foo: 'bar' }` | `{ foo: 'bar', XDEBUG_TRIGGER: 'mike' }` | Merged with existing |
| Production mode | `false` | `undefined` | `{}` (or undefined if no token) | Param NOT added in prod |
| Production mode, existing params | `false` | `{ foo: 'bar' }` | `{ foo: 'bar' }` | Param NOT added in prod |

### Response Interceptor — 401 Handling

| Case | Setup | Expected `window.location.href` | Why |
|------|-------|---------------------------------|-----|
| 401 backend error, path `/events` | `window.location.pathname = '/events'`, `window.location.search = ''` | `/login?redirect=%2Fevents` | redirect param appended |
| 401 fallback, path `/events?page=2` | `window.location.pathname = '/events'`, `window.location.search = '?page=2'` | `/login?redirect=%2Fevents%3Fpage%3D2` | path + search encoded |
| 401 backend error, session expired | message contains 'inactivity' | alert called AND redirect to `/login?redirect=…` | Alert still fires |

### Response Interceptor — 403 Handling

| Case | Setup | Expected behavior | Why |
|------|-------|-------------------|-----|
| 403 backend error, path `/admin` | `window.location.pathname = '/admin'` | `imperativeNavigate('/unauthorized', { replace: true })` called; Promise rejected | Happy path |
| 403 fallback, path `/admin` | `window.location.pathname = '/admin'` | `imperativeNavigate('/unauthorized', { replace: true })` called; Promise rejected | Fallback branch same behavior |
| 403 loop guard — backend error | `window.location.pathname = '/unauthorized'` | `imperativeNavigate` NOT called; Promise rejected only | AC-13: no loop |
| 403 loop guard — fallback | `window.location.pathname = '/unauthorized'` | `imperativeNavigate` NOT called; Promise rejected only | AC-13: no loop |
| 403 does not clear localStorage | `auth_token` set before call | `auth_token` still present after interceptor runs | AC-5: user not logged out |

**Key Scenario: 403 Loop Guard**  
**Setup**: `window.location.pathname = '/unauthorized'`; mock `imperativeNavigate` as a jest spy.  
**Action**: Trigger the response interceptor with a 403 error (both `isBackendErrorResponse` branch and fallback switch branch, separately).  
**Expected**: The `imperativeNavigate` spy is NOT called; `Promise.reject` is returned; `localStorage` is untouched.

**Key Scenario: 401 redirect URL includes path and search**  
**Setup**: `Object.defineProperty(window, 'location', { value: { pathname: '/trivia', search: '?game=123', href: '' }, writable: true })`.  
**Action**: Trigger the response interceptor with a 401 error.  
**Expected**: `window.location.href === '/login?redirect=%2Ftrivia%3Fgame%3D123'`.

---

## Notes

- **`import.meta.env.DEV`**: This is a Vite built-in. It is `true` when the dev server is running (`vite dev`) and `false` in production builds (`vite build`). No polyfill or configuration change is needed — it works out of the box in this project.
- **Two 403 handling locations**: The current interceptor has two code paths (the `isBackendErrorResponse` branch and the fallback switch). Both must handle 403 consistently. Do not remove either branch — they serve different error response shapes from the backend.
- **`case 403` uses `return` instead of `break`**: Using `return Promise.reject(...)` exits the entire interceptor callback early. This is safe and cleaner than setting `message` and falling through to the shared `console.error` + `return Promise.reject(new Error(message))` at the end of the switch.
- **No changes to 401 `window.location.href` assignment method**: Per §4.5, the 401 branch continues to use `window.location.href` (full page reload) while the 403 branch uses `imperativeNavigate` (React Router soft navigation). This asymmetry is intentional and documented in the spec.
- **File length**: `api.ts` is currently ~810 lines. These changes add ~10 lines net. The file will remain under the 300-line-per-file limit concern does not apply here as `api.ts` is an existing file well over that guideline — no splitting is required for this plan's changes.
- **ESLint**: No new `eslint-disable` comments are needed. `import.meta.env.DEV` is a standard Vite pattern already used in the project.
