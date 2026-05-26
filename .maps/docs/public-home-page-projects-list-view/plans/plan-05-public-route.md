# Implementation Plan: PublicRoute Redirect Target Update

## Spec Context

The `PublicRoute` component in `App.tsx` currently redirects authenticated users away from `/login` to `/dashboard`. Since `/dashboard` is being removed in this epic, the redirect target must be changed to `/`. Additionally, when an unauthenticated user is sent to `/login` via the 401 interceptor's `?redirect=` query param, `PublicRoute` must pass that param through so the `Login` component can redirect the user back to their original destination after a successful login.

Catalog item: **5 — PublicRoute Redirect Target Update**  
Specification sections: §4.7  
Acceptance criteria addressed: AC-7, AC-9

---

## Dependencies

- **Blocked by**: nothing (no other catalog items must be complete before this change can be made)
- **Blocks**: Catalog Item 7 (App.tsx Route Overhaul) — this change is made in `App.tsx` and is incorporated as part of that larger overhaul; this plan defines the precise logic for the `PublicRoute` component so Item 7 can implement it correctly
- **Uses**:
  - `react-router-dom` — `useSearchParams` (already imported indirectly via Router; needs to be imported if not already present in the component scope)
  - `useAuth()` from `./hooks/useAuth` — already used in the existing `PublicRoute`

---

## File Changes

### New Files

None. (`gravitycar-frontend/src/utils/redirectPath.ts` is created by Catalog Item 6 — this plan consumes it.)

### Modified Files

- `gravitycar-frontend/src/App.tsx` — Update the `PublicRoute` component definition only (lines 36–48 in the current file). No other part of `App.tsx` is touched by this plan; the broader route overhaul is handled by Catalog Item 7.

---

## Implementation Details

### PublicRoute Component

**File**: `gravitycar-frontend/src/App.tsx`

**Current implementation** (lines 36–48):

```tsx
const PublicRoute = ({ children }: { children: React.ReactNode }) => {
  const { isAuthenticated, isLoading } = useAuth();
  
  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-lg text-gray-600">Loading...</div>
      </div>
    );
  }
  
  return !isAuthenticated ? <>{children}</> : <Navigate to="/dashboard" replace />;
};
```

**Required changes**:

1. Add `useSearchParams` to the `react-router-dom` import at the top of the file.
2. Inside `PublicRoute`, call `useSearchParams()` to read the `?redirect=` param from the current URL.
3. Build the redirect destination: if a `redirect` param is present and its value starts with `/`, use that value; otherwise use `/`.
4. Replace `<Navigate to="/dashboard" replace />` with `<Navigate to={redirectTo} replace />`.

**New import to add** (alongside existing imports):
```typescript
import { getRedirectPath } from './utils/redirectPath';
```

**Updated component**:

```tsx
const PublicRoute = ({ children }: { children: React.ReactNode }) => {
  const { isAuthenticated, isLoading } = useAuth();
  const [searchParams] = useSearchParams();

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-lg text-gray-600">Loading...</div>
      </div>
    );
  }

  return !isAuthenticated ? <>{children}</> : <Navigate to={getRedirectPath(searchParams)} replace />;
};
```

**Key decisions**:

- `useSearchParams()` is the correct React Router v6 hook for reading query params inside a component. It is available from `react-router-dom` without any new dependencies.
- The `redirectParam.startsWith('/')` guard prevents open redirect attacks. Any value that is an absolute URL (e.g., `https://evil.com`) will be ignored and the user will be sent to `/`.
- `replace` is kept on `<Navigate>` so the `/login` URL is not pushed onto the browser history stack when an authenticated user is bounced away.
- `useSearchParams` requires the component to be rendered inside a `<Router>` context. `PublicRoute` is only ever rendered inside `AppRoutes`, which is inside `<Router>` — no issue.

**Import change** (in the existing `react-router-dom` import line):

```tsx
// Before
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';

// After
import { BrowserRouter as Router, Routes, Route, Navigate, useSearchParams } from 'react-router-dom';
```

---

## Error Handling

- No runtime errors are expected from this change.
- Redirect validation (including protocol-relative URL rejection) is handled inside `getRedirectPath()` in `utils/redirectPath.ts`. No inline guard logic is needed in `PublicRoute`.

---

## Unit Test Specifications

Tests should be written for the `PublicRoute` component behavior. Since `PublicRoute` is an inline component in `App.tsx`, tests should either (a) test the behavior through the rendered route tree, or (b) extract `PublicRoute` into its own file for unit testing. For this plan, tests use the rendered route approach with `MemoryRouter`.

### `PublicRoute` behavior

| Case | Auth State | URL | Expected Render | Why |
|------|------------|-----|-----------------|-----|
| Unauthenticated user visits `/login` | `isAuthenticated=false` | `/login` | Renders `children` | Normal public access |
| Authenticated user visits `/login`, no redirect param | `isAuthenticated=true` | `/login` | Redirects to `/` | AC-7: no `/dashboard` |
| Authenticated user visits `/login` with valid redirect | `isAuthenticated=true` | `/login?redirect=%2Ftrivia` | Redirects to `/trivia` | AC-9: pass-through |
| Authenticated user visits `/login` with absolute URL redirect | `isAuthenticated=true` | `/login?redirect=https%3A%2F%2Fevil.com` | Redirects to `/` | Open redirect prevention |
| Authenticated user visits `/login` with protocol-relative redirect | `isAuthenticated=true` | `/login?redirect=%2F%2Fevil.com` | Redirects to `/` | Open redirect prevention |
| Auth still loading | `isLoading=true` | `/login` | Renders loading spinner | No premature redirect |

### Key Scenario: Authenticated user with valid redirect param

**Setup**: Mock `useAuth()` to return `{ isAuthenticated: true, isLoading: false }`. Render `PublicRoute` inside `MemoryRouter` with initial entry `/login?redirect=%2Ftrivia`.

**Action**: Mount the component.

**Expected**: The component renders `<Navigate to="/trivia" replace />`, causing the router to navigate to `/trivia`.

### Key Scenario: Open redirect protection

**Setup**: Mock `useAuth()` to return `{ isAuthenticated: true, isLoading: false }`. Render `PublicRoute` inside `MemoryRouter` with initial entry `/login?redirect=https%3A%2F%2Fevil.com%2Fsteal`.

**Action**: Mount the component.

**Expected**: The component renders `<Navigate to="/" replace />` — the absolute URL is rejected and the user is sent to `/`.

---

## Notes

- This is a minimal, self-contained change. The only file touched is `App.tsx`, and the only component modified is `PublicRoute`.
- The broader `App.tsx` changes (removing `ProtectedRoute`, updating the route table, etc.) are handled separately in Catalog Item 7. This plan is intentionally scoped to `PublicRoute` only so it can be reviewed and built independently.
- The `useSearchParams` hook requires React Router v6, which is already in use in this project (confirmed by `BrowserRouter`, `Routes`, `Route`, `Navigate` usage).
- Spec §4.7 states: "An authenticated user who visits `/login` SHALL be redirected to `/`, unless a valid `?redirect=` query param is present — in that case they SHALL be redirected to the value of `?redirect=` instead." This plan fulfills that requirement exactly.
- The `/login` route itself is only used by `PublicRoute`. The `?redirect=` param written by the 401 interceptor (Catalog Item 2) and read here in `PublicRoute` and in the `Login` component (Catalog Item 6) form a complete round-trip redirect flow: 401 → `/login?redirect=/original` → user logs in → navigated to `/original`.
