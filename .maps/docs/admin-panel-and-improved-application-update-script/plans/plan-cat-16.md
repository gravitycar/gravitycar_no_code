# Implementation Plan: CAT-16 — AdminPage + App.tsx Route Registration

## Spec Context

`AdminPage.tsx` is the top-level page wrapper for the admin panel. It renders a section-based
layout containing a heading and the `CacheManagementPanel` component, with visual space for
future admin feature panels. The route `/admin` is added to `App.tsx`, protected by
`<ProtectedRoute requiredRole="admin">` and wrapped in `<Layout>`. This wires the entire admin
panel feature into the React SPA.

Catalog item: CAT-16  
Specification section: AC-27, AC-33, AC-34; Component 6 (React Admin Panel — AdminPage, route registration)  
Acceptance criteria addressed:
- AC-27: Route `/admin` is protected by `<ProtectedRoute requiredRole="admin">` and wrapped in `<Layout>` in `App.tsx`.
- AC-33: Admin panel layout is section-based, designed to accommodate future admin features beyond cache management.
- AC-34: No external UI libraries; all styling uses Tailwind CSS utility classes.

---

## Dependencies

- **Blocked by**: CAT-13 (`ProtectedRoute` component — imported in `App.tsx` for the `/admin` route guard)
- **Blocked by**: CAT-15 (`CacheManagementPanel` — rendered inside `AdminPage`)
- **Blocks**: nothing (CAT-16 is the terminal node in the frontend dependency chain)
- **Uses**:
  - `gravitycar-frontend/src/components/ProtectedRoute.tsx` (CAT-13)
  - `gravitycar-frontend/src/components/admin/CacheManagementPanel.tsx` (CAT-15)
  - `gravitycar-frontend/src/components/layout/Layout.tsx` — existing layout wrapper
  - `react-router-dom` `Route` — already imported in `App.tsx`
  - Tailwind CSS only

---

## File Changes

### New Files
- `gravitycar-frontend/src/pages/AdminPage.tsx` — admin panel page wrapper

### Modified Files
- `gravitycar-frontend/src/App.tsx` — add `/admin` route, import `ProtectedRoute` and `AdminPage`

---

## Implementation Details

### AdminPage.tsx

**File**: `gravitycar-frontend/src/pages/AdminPage.tsx`

**Pattern reference**: `ProjectsPage.tsx` is the established page-wrapper pattern:
```tsx
const ProjectsPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50">
      <ProjectsListView />
    </div>
  );
};
```

Key observations from `ProjectsPage.tsx`:
- No `<Layout>` wrapper inside the page component itself — `<Layout>` is applied in `App.tsx`.
- Outer `<div>` provides the full-screen gray background.
- The component is a thin wrapper: no state, no hooks, no data fetching.

**AdminPage** follows this same thin-wrapper pattern. It adds a page heading and section structure
to visually accommodate future admin panels (per AC-33).

**Exports**: default export `AdminPage`

**Props**: none

**Imports**:
```tsx
import React from 'react';
import CacheManagementPanel from '../components/admin/CacheManagementPanel';
```

**Full component**:

```tsx
import React from 'react';
import CacheManagementPanel from '../components/admin/CacheManagementPanel';

/**
 * AdminPage
 *
 * Top-level wrapper for the admin panel. Section-based layout so future
 * admin features (User Management, System Status, etc.) can be added as
 * additional <section> blocks without restructuring.
 *
 * Layout is applied by App.tsx — no <Layout> wrapper here.
 */
const AdminPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-8">Admin Panel</h1>

        <div className="space-y-8">
          {/* Cache Management section */}
          <CacheManagementPanel />

          {/* Future admin feature sections can be added here as additional components */}
        </div>
      </div>
    </div>
  );
};

export default AdminPage;
```

**Design decisions**:
- `max-w-4xl mx-auto` constrains width to a readable admin panel width (similar to the spec wireframe's single-column layout).
- `space-y-8` on the sections container gives vertical rhythm between panels, so adding a second
  `<UserManagementPanel />` or `<SystemStatusPanel />` section later requires only appending a
  component — no structural changes needed (AC-33).
- The comment `{/* Future admin feature sections... */}` makes the extension point explicit for
  the next developer.
- `px-4 py-8` provides consistent horizontal padding and top/bottom breathing room.
- No `<Layout>` wrapper — `<Layout>` is applied in `App.tsx`, consistent with all other pages
  in the project (`ProjectsPage`, `TriviaPage`, `DnDChatPage`, etc.).

**File length estimate**: ~30 lines. Well within the 300-line limit.

---

### App.tsx modification

**File**: `gravitycar-frontend/src/App.tsx`

**Current state**: 179 lines. Routes are defined in the `AppRoutes` component. The last named
route before the dynamic catch-all is the events block (lines 111–138).

**Changes required**:

**1. Add imports** (at the top of the file, with existing imports):

```tsx
import ProtectedRoute from './components/ProtectedRoute';
import AdminPage from './pages/AdminPage';
```

Insert these after the existing page imports (after `import UnauthorizedPage` / `import NotFoundPage`
block, before `import { NavigatorSetter }`). The existing import block ends around line 17.

**2. Add the `/admin` route** to `AppRoutes`, immediately before the dynamic catch-all route
(`/:modelName`) and after the events routes block.

The spec (Component 6) states the route registration form:
```
/admin → <Layout><ProtectedRoute requiredRole="admin"><AdminPage /></ProtectedRoute></Layout>
```

**Important**: Confirm whether `<Layout>` should be inside or outside `<ProtectedRoute>`.
Reading the existing pattern: all existing named routes wrap children in `<Layout>` directly in
`App.tsx` (e.g., `/trivia`, `/dnd-chat`, `/events`). `<ProtectedRoute>` is a new component (CAT-13)
that renders its `children` when the user is authenticated and has the correct role, or redirects
otherwise. The loading state of `ProtectedRoute` renders a spinner — this spinner does NOT need
to be inside `<Layout>` (the spinner is full-screen). So `<Layout>` should wrap `<ProtectedRoute>`,
but the spinner from `ProtectedRoute` will cover the full screen regardless. The spec section for
`AdminPage` says `<Layout>` is in `App.tsx` (not in `AdminPage`). Looking at the spec's route
registration example:

```
/admin → <Layout><ProtectedRoute requiredRole="admin"><AdminPage /></ProtectedRoute></Layout>
```

`<Layout>` is outermost. This means the Layout nav/shell is always rendered (even during the
loading spinner), and only the content area shows the spinner or the admin page content.
This is the correct approach — consistent with how other apps handle it (nav visible, content
loading). However, `ProtectedRoute`'s loading spinner uses `min-h-screen` which will fill the
content area, not the whole screen. This is fine.

**New route block** (insert before `/:modelName` catch-all, after events routes):

```tsx
{/* Admin Panel — admin role required */}
<Route
  path="/admin"
  element={
    <Layout>
      <ProtectedRoute requiredRole="admin">
        <AdminPage />
      </ProtectedRoute>
    </Layout>
  }
/>
```

**Exact insertion point**: After line 138 (end of events routes block, the closing `/>` of the
`propose-dates` route), before line 140 (the comment `{/* Dynamic Model Routes — catch-all... */}`).

The resulting `AppRoutes` function will have routes in this order:
1. `/login` — PublicRoute
2. `/` — root (ProjectsPage)
3. `/projects_showcase` — alias
4. `/unauthorized`
5. `/not-found`
6. `/trivia`
7. `/dnd-chat`
8. `/events`, `/events/:eventId/chart`, `/events/:eventId/propose-dates`
9. `/admin` ← **INSERT HERE**
10. `/:modelName` — dynamic catch-all
11. `*` — 404

This placement is correct: `/admin` must be before `/:modelName` so that the dynamic catch-all
does not match `/admin` first.

**No double-wrapping**: `AdminPage` does NOT contain `<Layout>` — it follows the `ProjectsPage`
pattern. `<Layout>` is applied once in `App.tsx`. `ProtectedRoute` renders `children` (the
`<AdminPage />`) when auth passes; it does not introduce any layout wrapper of its own.

---

## Error Handling

`AdminPage` itself has no error conditions — it is a pure layout component. All error handling
lives in `CacheManagementPanel` and `ConfirmRebuildModal`.

`ProtectedRoute` handles auth errors (redirect to `/login` or `/unauthorized`). These redirects
happen before `AdminPage` is rendered.

If `CacheManagementPanel` throws a runtime error, the app's existing `ErrorBoundary` (wrapping
`AppRoutes`) will catch it and render its fallback UI.

---

## Unit Test Specifications

**Test file**: `gravitycar-frontend/src/pages/AdminPage.test.tsx`

Use Vitest + React Testing Library. Mock `CacheManagementPanel` to avoid testing its internals.

```tsx
import { vi, describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import AdminPage from './AdminPage';

vi.mock('../components/admin/CacheManagementPanel', () => ({
  default: () => <div data-testid="cache-management-panel" />,
}));
```

### AdminPage rendering

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Renders heading | Default render | "Admin Panel" heading visible | AC-33 |
| Renders CacheManagementPanel | Default render | `data-testid="cache-management-panel"` in DOM | CAT-15 integration |
| No Layout wrapper inside | Default render | No `<nav>` or Layout chrome inside component | Pattern consistency |
| Has section container | Default render | Container div with `space-y-8` class (or similar) | AC-33 extension point |

### Key Scenario: Section-based layout accommodates future panels

**Setup**: Default render of `AdminPage`.  
**Expected**: The component renders `CacheManagementPanel` inside a container div. The container
has no hardcoded 1-column constraint that would break if a second panel were added.  
**Why**: AC-33 — the layout must accommodate future admin features. The `space-y-8` container
pattern allows additional child components to stack vertically without CSS changes.

---

**No dedicated App.tsx test update required**: `App.tsx` route integration is covered by
end-to-end or integration tests. The route itself follows the exact pattern of all other named
routes, so no new unit test logic is needed for the routing change.

---

## Notes

- `AdminPage` does NOT contain `<Layout>` — it follows the thin-wrapper pattern established by
  `ProjectsPage.tsx`, `TriviaPage.tsx`, and all other pages in the project. `<Layout>` is always
  applied in `App.tsx`.
- The `/admin` route must be placed BEFORE the `/:modelName` dynamic catch-all in `App.tsx`.
  Placing it after would cause React Router to match `/admin` as a model name and route to
  `DynamicModelRoute` instead of `AdminPage`.
- `ProtectedRoute` (CAT-13) wraps `<AdminPage />` — not the other way around. `<Layout>` wraps
  `<ProtectedRoute>` in `App.tsx`. This matches the spec's stated route form:
  `<Layout><ProtectedRoute requiredRole="admin"><AdminPage /></ProtectedRoute></Layout>`.
- The loading spinner in `ProtectedRoute` fills the content area (`min-h-screen`) when auth is
  loading. Because `<Layout>` is outside `<ProtectedRoute>`, the navigation bar remains visible
  during the loading state. This is the correct UX — the user sees the app shell while auth
  resolves.
- Tailwind classes used in `AdminPage`: `min-h-screen bg-gray-50` (outer), `max-w-4xl mx-auto
  px-4 py-8` (content container), `text-3xl font-bold text-gray-900 mb-8` (heading),
  `space-y-8` (sections container). All are pure Tailwind; no external libraries.
- File length target for `AdminPage.tsx`: ~30 lines. Well under the 300-line limit.
- `App.tsx` will grow from 179 to ~190 lines after this change. Still under the 300-line limit.
