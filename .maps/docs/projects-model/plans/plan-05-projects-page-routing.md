# Implementation Plan: ProjectsPage + App.tsx Route

## Spec Context

This plan fulfills Specification sections 9 and 10: adding the public `/projects_showcase` frontend route
and the thin `ProjectsPage.tsx` wrapper that renders `ProjectsListView`. The route must be publicly
accessible (no `<ProtectedRoute>`) so unauthenticated guests can browse the Projects showcase.

Catalog item: Item 5 — ProjectsPage + App.tsx Route  
Specification sections: §9 (App.tsx Route), §10 (ProjectsPage Thin Wrapper)  
Acceptance criteria addressed: AC #3 (guest can navigate to `/projects_showcase` without redirect)

---

## Dependencies

- **Blocked by**: Plan 06 (ProjectsListView) — `ProjectsPage.tsx` imports `ProjectsListView` from
  `../components/projects/ProjectsListView`. That component must exist before this page can be built.
- **Uses**: `gravitycar-frontend/src/components/layout/Layout.tsx` (already exists)
- **Uses**: `gravitycar-frontend/src/App.tsx` (already exists — to be modified)

---

## File Changes

### New Files

- `gravitycar-frontend/src/pages/ProjectsPage.tsx` — thin page wrapper; renders `<ProjectsListView />`
  inside a container `<div>`.

### Modified Files

- `gravitycar-frontend/src/App.tsx` — add one import and one `<Route>` for `/projects_showcase`

---

## Implementation Details

### File 1: ProjectsPage.tsx (new)

**File**: `gravitycar-frontend/src/pages/ProjectsPage.tsx`

**Pattern**: Mirror `TriviaPage.tsx` exactly — thin wrapper, no local state, no business logic.

**Exports**:
- `ProjectsPage` (default export) — `React.FC` component

**Complete file content**:

```tsx
import React from 'react';
import ProjectsListView from '../components/projects/ProjectsListView';

/**
 * Projects Page Component
 * Full page wrapper for the Projects Showcase
 * Integrates with the main application layout and navigation
 */
const ProjectsPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50">
      <ProjectsListView />
    </div>
  );
};

export default ProjectsPage;
```

**Key decisions**:
- Container class `min-h-screen bg-gray-50` matches TriviaPage.tsx exactly
- No `<Layout>` wrapper here — Layout is applied in App.tsx (same as TriviaPage)
- No `<ProtectedRoute>` wrapper — public route, no auth required
- All state (selected project, modal open/close) lives in `ProjectsListView`, not here

---

### File 2: App.tsx (modify)

**File**: `gravitycar-frontend/src/App.tsx`

**Two changes required**:

#### Change 1 — Add import (line 13, after the existing TriviaPage import)

Current line 13:
```tsx
import TriviaPage from './pages/TriviaPage';
```

Add immediately after (new line 14):
```tsx
import ProjectsPage from './pages/ProjectsPage';
```

#### Change 2 — Add route (after the `/trivia` route block, before the D&D chat route)

Insert the following block after the closing `/>` of the `/trivia` route (after line 118 in the
current file) and before the D&D RAG Chat route comment:

```tsx
      {/* Projects Showcase Route - public, no ProtectedRoute */}
      <Route
        path="/projects_showcase"
        element={
          <Layout>
            <ProjectsPage />
          </Layout>
        }
      />
```

**Full context — surrounding lines for precise insertion**:

```tsx
      {/* Movie Quote Trivia Game Route */}
      <Route
        path="/trivia"
        element={
          <ProtectedRoute>
            <Layout>
              <TriviaPage />
            </Layout>
          </ProtectedRoute>
        }
      />

      {/* Projects Showcase Route - public, no ProtectedRoute */}
      <Route
        path="/projects_showcase"
        element={
          <Layout>
            <ProjectsPage />
          </Layout>
        }
      />

      {/* D&D RAG Chat Route */}
      <Route
        path="/dnd-chat"
        element={
          <ProtectedRoute>
            <Layout>
              <DnDChatPage />
            </Layout>
          </ProtectedRoute>
        }
      />
```

**Why no `<ProtectedRoute>`**: The spec explicitly requires the Projects showcase to be accessible
to unauthenticated (guest) users. Compare to the existing `/events` route (lines 133-143 in
App.tsx) which also wraps only in `<Layout>` without `<ProtectedRoute>`.

**Why placed before `/:modelName`**: The dynamic `/:modelName` catch-all route must remain AFTER
all specific routes. `/projects_showcase` is a specific named route and must appear before the
dynamic route to take precedence.

---

## Error Handling

No special error handling needed in `ProjectsPage.tsx` — it is a thin wrapper only. All data
fetching and error states are handled in `ProjectsListView`.

---

## Unit Test Specifications

These two files are thin wrappers with no logic; functional tests are sufficient. No unit tests
are required for `ProjectsPage.tsx` or the routing change in `App.tsx`. The acceptance criteria
for this item (AC #3) is validated by an end-to-end test: confirm that navigating to
`/projects_showcase` without a valid auth token does NOT redirect to `/login`.

### Manual Verification Checklist

| Check | Expected |
|-------|----------|
| Navigate to `/projects_showcase` logged in | Projects page renders, `ProjectsListView` is visible |
| Navigate to `/projects_showcase` logged out | Projects page renders (no redirect to `/login`) |
| Navigate to `/trivia` | Trivia page still works (no regression) |
| Navigate to `/events` | Events page still works (no regression) |
| Navigate to `/dashboard` logged out | Redirects to `/login` (ProtectedRoute still works) |

---

## Notes

- The route path `/projects_showcase` (with underscore) is specified in the spec. This avoids
  collision with the admin CRUD route `/Projects` (uppercase) handled by the dynamic `/:modelName`
  route.
- The `/:modelName` dynamic route continues to handle admin CRUD for Projects at `/Projects`
  (uppercase) — no change needed there.
- `ProjectsPage.tsx` follows TriviaPage.tsx closely. If TriviaPage ever gets refactored, review
  ProjectsPage for consistency.
