# Implementation Plan: CAT-13 — ProtectedRoute Component

## Spec Context

`ProtectedRoute` is a reusable React component that wraps protected routes in the SPA. It encapsulates three guard behaviors: (1) while auth is still loading, render a spinner to avoid a flash-redirect; (2) once loaded, redirect unauthenticated users to `/login`; (3) redirect authenticated users who lack the required role to `/unauthorized`. The `/admin` route in `App.tsx` uses it as `<ProtectedRoute requiredRole="admin">`. The existing `PublicRoute` component (defined inline in `App.tsx`) is the established pattern to follow.

Catalog item: CAT-13  
Specification section: AC-27a through AC-27f; Component 6 (React Admin Panel) — ProtectedRoute  
Acceptance criteria addressed:
- AC-27a: New `ProtectedRoute` component created at `gravitycar-frontend/src/components/ProtectedRoute.tsx`
- AC-27b: Props `children: ReactNode` (required) and `requiredRole?: string` (optional)
- AC-27c: While `isLoading` is true, render a loading spinner; auth/role checks execute only once `isLoading` is false
- AC-27d: If not authenticated (and not loading), redirect to `/login` with `?redirect=<current path>`
- AC-27e: If authenticated but `requiredRole` is set and `user.user_type !== requiredRole`, redirect to `/unauthorized` with `?redirect=<current path>`
- AC-27f: If authenticated and role check passes (or no role required), render `children`

---

## Dependencies

- **Blocked by**: none
- **Blocks**: CAT-16 (AdminPage route registration uses `<ProtectedRoute requiredRole="admin">`)
- **Uses**:
  - `gravitycar-frontend/src/hooks/useAuth.tsx` — `useAuth()` hook providing `{ user, isAuthenticated, isLoading }`
  - `react-router-dom` — `Navigate`, `useLocation` (for building the redirect param)
  - React built-ins — `ReactNode`
  - Tailwind CSS only (no external UI library)

---

## File Changes

### New Files
- `gravitycar-frontend/src/components/ProtectedRoute.tsx` — route guard component

### Modified Files
- none (App.tsx is modified in CAT-16 which imports and uses ProtectedRoute)

---

## Implementation Details

### ProtectedRoute

**File**: `gravitycar-frontend/src/components/ProtectedRoute.tsx`

**Props interface**:
```typescript
interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredRole?: string;
}
```

**Logic flow** (in order):

1. Call `useAuth()` to get `{ user, isAuthenticated, isLoading }`.
2. Call `useLocation()` to get the current path for building the redirect query param.
3. Build `redirectParam`: `encodeURIComponent(location.pathname + location.search)`.
4. **Loading state**: if `isLoading === true`, return the spinner JSX. Do not evaluate auth or role yet.
5. **Unauthenticated state**: if `!isAuthenticated`, return `<Navigate to={'/login?redirect=' + redirectParam} replace />`.
6. **Insufficient role state**: if `requiredRole` is set AND `user?.user_type !== requiredRole`, return `<Navigate to={'/unauthorized?redirect=' + redirectParam} replace />`.
7. **Authenticated + role OK**: return `<>{children}</>`.

**Established pattern reference**: `PublicRoute` in `App.tsx` (lines ~21-34) follows the same shape:
- `useAuth()` → destructure `isAuthenticated`, `isLoading`
- `useSearchParams()` for query params (ProtectedRoute uses `useLocation()` instead, since it needs the full pathname)
- Loading state renders a `<div>` with centered text
- Returns `<>{children}</>` or a `<Navigate>` component

**Spinner design** (Tailwind only, no library):
```tsx
<div className="min-h-screen flex items-center justify-center">
  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
</div>
```
This matches the spinner pattern used elsewhere in the project (center-screen, animate-spin on a bordered div).

**Code Example**:

```tsx
import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredRole?: string;
}

/**
 * ProtectedRoute
 *
 * Guards a route behind authentication and an optional role check.
 *
 * States:
 *   1. isLoading  → render spinner (auth check in progress, avoid flash-redirect)
 *   2. !isAuthenticated → Navigate to /login?redirect=<current path>
 *   3. requiredRole set and user.user_type !== requiredRole → Navigate to /unauthorized?redirect=<current path>
 *   4. authenticated + role OK → render children
 *
 * Usage:
 *   <ProtectedRoute requiredRole="admin">
 *     <AdminPage />
 *   </ProtectedRoute>
 */
const ProtectedRoute = ({ children, requiredRole }: ProtectedRouteProps): React.ReactElement => {
  const { user, isAuthenticated, isLoading } = useAuth();
  const location = useLocation();
  const redirectParam = encodeURIComponent(location.pathname + location.search);

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to={`/login?redirect=${redirectParam}`} replace />;
  }

  if (requiredRole !== undefined && user?.user_type !== requiredRole) {
    return <Navigate to={`/unauthorized?redirect=${redirectParam}`} replace />;
  }

  return <>{children}</>;
};

export default ProtectedRoute;
```

**Key implementation notes**:
- Use `requiredRole !== undefined` (not `!requiredRole`) so that an empty string `''` would still trigger the role check — though in practice `requiredRole` will always be a non-empty role name like `'admin'`.
- Use `replace` on all `<Navigate>` calls so the guard redirect doesn't pollute browser history.
- The redirect param includes `location.search` so users who land on e.g. `/admin?tab=cache` with a deep link are sent back to exactly that URL after login.
- `useLocation` (not `useSearchParams`) is correct here because we need the full pathname to build the redirect, not to read an existing param.
- Return type is `React.ReactElement` (not `JSX.Element | null`) — all four code paths return a valid element.

---

## Error Handling

- No exceptions are thrown. All guard conditions produce a clean render outcome.
- If `useAuth()` throws (used outside `AuthProvider`), the error propagates naturally and would be caught by the app's `ErrorBoundary`. This is acceptable — `ProtectedRoute` must be inside `AuthProvider`.
- If `user` is null but `isAuthenticated` is somehow true (should not happen in practice), the role check `user?.user_type !== requiredRole` evaluates `undefined !== requiredRole` which is `true`, redirecting to `/unauthorized` — a safe fallback.

---

## Unit Test Specifications

**Test file**: `gravitycar-frontend/src/components/ProtectedRoute.test.tsx`

**Setup (Vitest + React Testing Library)**:

```tsx
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { vi } from 'vitest';
import ProtectedRoute from './ProtectedRoute';

// Mock useAuth
vi.mock('../hooks/useAuth', () => ({ useAuth: vi.fn() }));
import { useAuth } from '../hooks/useAuth';
```

### Loading state

| Case | Auth state | Props | Expected render |
|------|-----------|-------|----------------|
| Shows spinner while loading | `isLoading: true` | (any) | Spinner div rendered; children NOT rendered |
| Spinner uses animate-spin | `isLoading: true` | (any) | Element with `animate-spin` class exists |
| Does not redirect while loading | `isLoading: true, isAuthenticated: false` | (any) | No navigation to `/login` |

### Unauthenticated redirect

| Case | Auth state | Props | Expected |
|------|-----------|-------|---------|
| Redirects to /login | `isLoading: false, isAuthenticated: false` | no requiredRole | `<Navigate to="/login?redirect=...">` rendered |
| Redirect includes current path | `isLoading: false, isAuthenticated: false` | path `/admin` | redirect param contains `%2Fadmin` |
| Does not render children | `isLoading: false, isAuthenticated: false` | children `<div>secret</div>` | "secret" not in document |

### Role check — insufficient role

| Case | Auth state | Props | Expected |
|------|-----------|-------|---------|
| Redirects to /unauthorized | `isLoading: false, isAuthenticated: true, user: { user_type: 'user' }` | `requiredRole="admin"` | Navigate to `/unauthorized?redirect=...` |
| Redirect includes current path | same | path `/admin` | redirect param contains `%2Fadmin` |
| Does not render children | same | children `<div>secret</div>` | "secret" not in document |

### Role check — correct role

| Case | Auth state | Props | Expected |
|------|-----------|-------|---------|
| Renders children | `isLoading: false, isAuthenticated: true, user: { user_type: 'admin' }` | `requiredRole="admin"` | `<div>protected content</div>` rendered |
| No requiredRole — renders children | `isLoading: false, isAuthenticated: true, user: { user_type: 'user' }` | no requiredRole | children rendered |

### Key Scenario: Flash-redirect prevention

**Setup**: `isLoading = true`, `isAuthenticated = false` (user not yet verified)  
**Action**: Render `<ProtectedRoute><div>secret</div></ProtectedRoute>`  
**Expected**: No redirect; spinner is shown; "secret" is not in the document  
**Why**: Without the loading guard, an unauthenticated render while `checkAuth()` is in progress would redirect the user to `/login` even when they have a valid token. The spinner prevents this flicker.

### Key Scenario: Role redirect uses replace

**Setup**: Authenticated user with `user_type: 'user'`, `requiredRole="admin"`, render in `MemoryRouter`  
**Action**: Render `<ProtectedRoute requiredRole="admin"><div /></ProtectedRoute>`  
**Expected**: The `Navigate` component is called with `replace` prop  
**Why**: Prevents the unauthorized redirect from polluting browser history.

---

## Notes

- Component lives in `src/components/` (not `src/components/auth/` or a subdirectory) per spec AC-27a which specifies `gravitycar-frontend/src/components/ProtectedRoute.tsx`.
- Default export (not named) — consistent with how `PublicRoute` is defined and how page components are exported in this project.
- Tailwind CSS only: the spinner is a pure-CSS animated div. No icon libraries, no Shadcn `Spinner` component.
- `user_type` is the field on the `User` type used for role checking — confirmed in `useAuth.tsx` where `user: User | null` and the `User` type (from `src/types`) includes `user_type`.
- The component does not perform its own API calls; it reads state exclusively from `useAuth()`. This keeps it thin and testable.
