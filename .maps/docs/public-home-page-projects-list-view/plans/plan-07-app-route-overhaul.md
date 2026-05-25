# Implementation Plan: App.tsx Route Overhaul

## Spec Context

This plan performs the complete route table restructuring of `App.tsx` as described in §3 of
the specification. It is the culminating change for this epic: it integrates the
`NavigatorSetter` from Item 1, the `UnauthorizedPage` from Item 3, and the updated
`PublicRoute` logic from Item 5, then rewrites the route table to match the new public-first
model described in §4.1–§4.3 and §4.6.

Catalog item: 7 — App.tsx Route Overhaul  
Specification sections: §3, §4.1, §4.2, §4.3, §4.6, §4.7  
Acceptance criteria addressed: AC-1, AC-2, AC-3, AC-7, AC-9, AC-10, AC-12

---

## Dependencies

- **Blocked by**:
  - Item 1 (Navigation Singleton) — `NavigatorSetter` and `setNavigator` must be defined in
    `gravitycar-frontend/src/utils/navigate.ts` before this plan imports them
  - Item 3 (Unauthorized Page) — `UnauthorizedPage` must exist at
    `gravitycar-frontend/src/pages/UnauthorizedPage.tsx` before this plan imports it
- **Incorporates**: Item 5 (PublicRoute Redirect Target Update) — the updated `PublicRoute`
  component defined in plan-05 is built into this file as part of the overhaul, not separately
- **Blocks**: Item 8 (Delete Dashboard.tsx) — `App.tsx` must remove the `Dashboard` import
  before the file can safely be deleted

---

## File Changes

### New Files

None — all new files are created by prerequisite plans.

### Modified Files

- `gravitycar-frontend/src/App.tsx` — full route table rewrite; see Implementation Details

### Deleted Imports (no longer needed after this change)

| Import | Symbol | Reason |
|--------|--------|--------|
| `./pages/Dashboard` | `Dashboard` | `/dashboard` route removed (AC-2) |
| `./pages/MetadataTestPage` | `MetadataTestPage` | `/metadata-test` route deleted (AC-12) |
| `./pages/TestRelatedRecord` | `TestRelatedRecord` | `/test-related-record` route deleted (AC-12) |
| `./pages/MoviesQuotesRelationshipDemo` | `MoviesQuotesRelationshipDemo` | `/movies-quotes-demo` route deleted (AC-12) |

---

## Implementation Details

### Step 1 — Update the `react-router-dom` import

Add `useSearchParams` (required by the updated `PublicRoute` from plan-05):

```tsx
// Before
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';

// After
import { BrowserRouter as Router, Routes, Route, Navigate, useSearchParams } from 'react-router-dom';
```

### Step 2 — Update the page/component import block

Remove the four deleted-route imports. Add two new imports (`NavigatorSetter` from the
navigation singleton, `UnauthorizedPage` from the new page):

**Final import block** (only lines that change from the current file are marked):

```tsx
import { BrowserRouter as Router, Routes, Route, Navigate, useSearchParams } from 'react-router-dom';
import { AuthProvider, useAuth } from './hooks/useAuth';
import { NotificationProvider } from './contexts/NotificationContext';
import { ErrorBoundary } from './components/error/ErrorBoundary';
import Layout from './components/layout/Layout';
import Login from './components/auth/Login';
// REMOVED: Dashboard, MetadataTestPage, TestRelatedRecord, MoviesQuotesRelationshipDemo
import DynamicModelRoute from './components/routing/DynamicModelRoute';
import GenericCrudPage from './components/crud/GenericCrudPage';
import TriviaPage from './pages/TriviaPage';
import ProjectsPage from './pages/ProjectsPage';
import DnDChatPage from './pages/DnDChatPage';
import ChartOfGoodness from './pages/ChartOfGoodness';
import BatchProposeDates from './pages/BatchProposeDates';
import UnauthorizedPage from './pages/UnauthorizedPage';                    // NEW
import { NavigatorSetter } from './utils/navigate';                         // NEW
import { getRedirectPath } from './utils/redirectPath';                     // NEW
import './App.css';
```

### Step 3 — Remove the `ProtectedRoute` component definition

Delete the entire `ProtectedRoute` block (lines 21–33 in the current file):

```tsx
// DELETE this entire block:
const ProtectedRoute = ({ children }: { children: React.ReactNode }) => {
  const { isAuthenticated, isLoading } = useAuth();
  if (isLoading) { ... }
  return isAuthenticated ? <>{children}</> : <Navigate to="/login" replace />;
};
```

No replacement is needed. Route-level auth enforcement is fully removed (AC-3).

### Step 4 — Replace the `PublicRoute` component definition

Replace the current `PublicRoute` (which redirects to `/dashboard`) with the updated version
from plan-05 (which redirects to `/` with optional `?redirect=` pass-through):

```tsx
// Public Route Component (only accessible when NOT authenticated)
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

Key changes from plan-05:
- Added `useSearchParams` call
- Validation delegated to `getRedirectPath()` from `utils/redirectPath` (shared with `Login.tsx` and `GoogleSignInButton.tsx`)
- `<Navigate to={getRedirectPath(searchParams)} replace />` replaces the hardcoded `/dashboard` target

### Step 5 — Rewrite the `AppRoutes` component

Replace the entire `AppRoutes` function body with the new route table:

```tsx
const AppRoutes = () => {
  return (
    <Routes>
      {/* Login — public only (redirect authenticated users away) */}
      <Route
        path="/login"
        element={
          <PublicRoute>
            <Login />
          </PublicRoute>
        }
      />

      {/* Root — public home page (no auth requirement) */}
      <Route
        path="/"
        element={
          <Layout>
            <ProjectsPage />
          </Layout>
        }
      />

      {/* Projects Showcase alias — kept for backwards compatibility */}
      <Route
        path="/projects_showcase"
        element={
          <Layout>
            <ProjectsPage />
          </Layout>
        }
      />

      {/* Unauthorized — public (403 interceptor navigates here) */}
      <Route
        path="/unauthorized"
        element={
          <Layout>
            <UnauthorizedPage />
          </Layout>
        }
      />

      {/* Trivia Game */}
      <Route
        path="/trivia"
        element={
          <Layout>
            <TriviaPage />
          </Layout>
        }
      />

      {/* D&D RAG Chat */}
      <Route
        path="/dnd-chat"
        element={
          <Layout>
            <DnDChatPage />
          </Layout>
        }
      />

      {/* Events Routes */}
      <Route
        path="/events"
        element={
          <Layout>
            <GenericCrudPage
              modelName="Events"
              title="Events"
              description="Manage events in your system"
            />
          </Layout>
        }
      />
      <Route
        path="/events/:eventId/chart"
        element={
          <Layout>
            <ChartOfGoodness />
          </Layout>
        }
      />
      <Route
        path="/events/:eventId/propose-dates"
        element={
          <Layout>
            <BatchProposeDates />
          </Layout>
        }
      />

      {/* Dynamic Model Routes — catch-all for any model using GenericCrudPage */}
      {/* Must be placed AFTER all specific routes but BEFORE the 404 route */}
      <Route
        path="/:modelName"
        element={
          <Layout>
            <DynamicModelRoute />
          </Layout>
        }
      />

      {/* 404 — catch-all */}
      <Route
        path="*"
        element={
          <div className="min-h-screen flex items-center justify-center">
            <div className="text-center">
              <h1 className="text-4xl font-bold text-gray-900 mb-4">404</h1>
              <p className="text-gray-600 mb-4">Page not found</p>
              <a href="/" className="text-blue-600 hover:text-blue-800">
                Go to Home Page
              </a>
            </div>
          </div>
        }
      />
    </Routes>
  );
};
```

**Changes from the current route table:**

| Change | Detail |
|--------|--------|
| `/` route | Changed from `<Navigate to="/dashboard" replace />` to `<Layout><ProjectsPage /></Layout>` (no guard) |
| `/dashboard` route | **Deleted** (AC-2) |
| `/metadata-test` route | **Deleted** (AC-12) |
| `/test-related-record` route | **Deleted** (AC-12) |
| `/movies-quotes-demo` route | **Deleted** (AC-12) |
| `/unauthorized` route | **Added** — `<Layout><UnauthorizedPage /></Layout>` (no guard) |
| `/trivia` route | `ProtectedRoute` wrapper **removed** |
| `/dnd-chat` route | `ProtectedRoute` wrapper **removed** |
| `/events/:eventId/propose-dates` route | `ProtectedRoute` wrapper **removed** |
| `/:modelName` catch-all | `ProtectedRoute` wrapper **removed** |
| 404 catch-all link | Changed from `/dashboard` to `/` |

### Step 6 — Update the `App` function to render `NavigatorSetter`

Add `<NavigatorSetter />` inside the `<Router>` context but outside `<Routes>`. `NavigatorSetter`
must be a sibling of `<AppRoutes />` (both inside `<Router>`) so it has access to
`useNavigate()`:

```tsx
function App() {
  return (
    <ErrorBoundary>
      <NotificationProvider>
        <AuthProvider>
          <Router>
            <NavigatorSetter />
            <AppRoutes />
          </Router>
        </AuthProvider>
      </NotificationProvider>
    </ErrorBoundary>
  );
}
```

`<NavigatorSetter />` renders `null` — it has no visual output. Placing it before
`<AppRoutes />` is cosmetically consistent (side-effect setup before content), but order does
not matter functionally since both are siblings inside the same `<Router>`.

---

## Final Route Table (authoritative)

| Path | Component | Guard | Notes |
|------|-----------|-------|-------|
| `/login` | `<Login />` | `PublicRoute` | Redirects authenticated users to `/` (or `?redirect=` target) |
| `/` | `<ProjectsPage />` | none | AC-1: public home page |
| `/projects_showcase` | `<ProjectsPage />` | none | Backwards-compatibility alias |
| `/unauthorized` | `<UnauthorizedPage />` | none | AC-6: 403 landing page |
| `/trivia` | `<TriviaPage />` | none | AC-3: ProtectedRoute removed |
| `/dnd-chat` | `<DnDChatPage />` | none | AC-3: ProtectedRoute removed |
| `/events` | `<GenericCrudPage modelName="Events" .../>` | none | Was already unguarded |
| `/events/:eventId/chart` | `<ChartOfGoodness />` | none | Was already unguarded |
| `/events/:eventId/propose-dates` | `<BatchProposeDates />` | none | AC-3: ProtectedRoute removed |
| `/:modelName` | `<DynamicModelRoute />` | none | AC-3: ProtectedRoute removed |
| `*` | 404 inline | none | Link target updated to `/` |

Routes **NOT present** after this change (AC-2, AC-12):

- `/dashboard`
- `/metadata-test`
- `/test-related-record`
- `/movies-quotes-demo`

---

## Error Handling

- No new runtime error paths are introduced. All error handling for API failures is in
  `api.ts` (handled by Items 2 and 6).
- The 404 catch-all continues to handle unrecognised paths. Navigating to `/dashboard` after
  this change will fall through to `/:modelName` (the DynamicModelRoute), which will attempt a
  backend lookup for model "dashboard" and receive a 404 or error from the API — acceptable
  per AC-2 and spec §7 ("it is acceptable for /dashboard to fall through to the catch-all").

---

## Unit Test Specifications

Most test coverage for this plan is at the component level (plan-01, plan-03, plan-05 each
specify their own tests). The App-level tests verify route integration.

### Route rendering

| Case | Action | Expected | AC |
|------|--------|----------|----|
| Visit `/` unauthenticated | Render App with MemoryRouter at `/`, auth=false | `ProjectsPage` renders | AC-1 |
| Visit `/` authenticated | Render App with MemoryRouter at `/`, auth=true | `ProjectsPage` renders | AC-1 |
| Visit `/dashboard` | Render App with MemoryRouter at `/dashboard` | Falls through to DynamicModelRoute or 404 — NOT Dashboard | AC-2 |
| Visit `/metadata-test` | Render App with MemoryRouter at `/metadata-test` | Falls through to DynamicModelRoute or 404 | AC-12 |
| Visit `/unauthorized` unauthenticated | Render App at `/unauthorized`, auth=false | `UnauthorizedPage` renders, no redirect | AC-6 |
| Visit `/login` authenticated | Render App at `/login`, auth=true | Redirect to `/` | AC-7 |
| No ProtectedRoute class | Inspect App source or rendered tree | No `ProtectedRoute` in the component tree | AC-3 |

### Key Scenario: Root route is public

**Setup**: Mount App with `MemoryRouter initialEntries={['/']}`. Mock `useAuth()` to return
`{ isAuthenticated: false, isLoading: false }`.  
**Action**: Render.  
**Expected**: `ProjectsPage` component renders. No `<Navigate to="/login">` occurs.  
**Why**: Confirms AC-1 (no auth requirement on root route).

### Key Scenario: Dashboard route is gone

**Setup**: Mount App with `MemoryRouter initialEntries={['/dashboard']}`.  
**Action**: Render.  
**Expected**: `Dashboard` component is NOT rendered. The route resolves to `DynamicModelRoute`
(via `/:modelName`) or the 404 catch-all.  
**Why**: Confirms AC-2 (no `/dashboard` route in the table).

### Key Scenario: NavigatorSetter is in the tree

**Setup**: Mount App.  
**Action**: Inspect rendered component tree.  
**Expected**: `NavigatorSetter` is rendered as a sibling of `AppRoutes` inside `Router`.  
**Why**: Ensures the navigation singleton is registered on app startup.

---

## Notes

- **`ProtectedRoute` removal is intentional and safe**: Frontend route guards are UX-only.
  Actual authorization is enforced by the backend API. The axios interceptor (plan-02) handles
  401/403 responses and redirects users appropriately.
- **`/:modelName` catch-all without ProtectedRoute**: This is intentional. If an
  unauthenticated user navigates to `/:modelName`, the backend API will return 401 and the
  interceptor will redirect to `/login?redirect=/<modelName>`. The page may briefly render an
  empty state before the redirect fires — this is acceptable (spec §7.2).
- **Order of routes in `AppRoutes` matters**: `/`, `/projects_showcase`, `/unauthorized`,
  `/trivia`, `/dnd-chat`, `/events`, `/events/:eventId/chart`,
  `/events/:eventId/propose-dates` must all appear BEFORE `/:modelName` to prevent the
  dynamic catch-all from matching them first. The existing `/:modelName` placement strategy
  is preserved.
- **404 link updated**: The inline 404 JSX at the `*` route linked to `/dashboard`. That link
  is updated to `/` (Home Page) since `/dashboard` no longer exists.
- **No new dependencies**: All imports are from existing project files or previously-installed
  packages (`react-router-dom`).
- **File length**: The resulting `App.tsx` will be shorter than the current file (fewer routes,
  no `ProtectedRoute` definition). Well under the 300-line project limit.
- **`NavigatorSetter` placement**: It is rendered inside `<Router>` (required — calls
  `useNavigate()`) and outside `<Routes>` (not a route element). As a sibling of `<AppRoutes />`
  it satisfies both constraints.
