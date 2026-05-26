# Implementation Catalog: Public Home Page — Projects List View

**Epic**: Public Home Page / Projects List View
**Epic ID**: 48
**Spec**: `.maps/docs/public-home-page-projects-list-view/specification/spec.md`
**Date**: 2026-05-25

---

## Catalog Items

### 1. Navigation Singleton Module

- **Purpose**: Implements the `navigate.ts` utility module and `NavigatorSetter` component that allow imperative React Router navigation from outside the component tree (e.g., from the axios interceptor in `api.ts`). Exports `setNavigator(fn)` and `imperativeNavigate(path, options)`.
- **Scope**:
  - CREATE `gravitycar-frontend/src/utils/navigate.ts` — module-level navigation singleton with `setNavigator` and `imperativeNavigate` exports
- **Blocks**: Item 2 (403 Interceptor Update), Item 7 (App.tsx Route Overhaul)
- **Blocked by**: nothing
- **Acceptance Criteria**: AC-5 (prerequisite), AC-13 (prerequisite)

---

### 2. 403 Interceptor and XDEBUG_TRIGGER Fix in api.ts

- **Purpose**: Updates the axios response interceptor in `api.ts` to (a) redirect to `/unauthorized` on 403 responses using `imperativeNavigate` from the navigation singleton, with a loop-guard for when the user is already on `/unauthorized`; (b) wrap the `XDEBUG_TRIGGER` param append in `if (import.meta.env.DEV)` to suppress it in production; (c) update the 401 interceptor to append `?redirect=<original-path>` to the `/login` redirect URL.
- **Scope**:
  - MODIFY `gravitycar-frontend/src/services/api.ts`
- **Blocks**: nothing (no other items depend on this)
- **Blocked by**: Item 1 (Navigation Singleton Module) — needs `imperativeNavigate`
- **Acceptance Criteria**: AC-4, AC-5, AC-11, AC-13

---

### 3. Unauthorized Page

- **Purpose**: Creates the `/unauthorized` page component — a simple, public, Layout-wrapped page that displays a "you don't have permission" message and a link back to `/`. Registers the route in `App.tsx`.
- **Scope**:
  - CREATE `gravitycar-frontend/src/pages/UnauthorizedPage.tsx` — new page component (no auth requirement, no ProtectedRoute)
- **Blocks**: Item 7 (App.tsx Route Overhaul) — the route entry is added there, but the component must exist first
- **Blocked by**: nothing
- **Acceptance Criteria**: AC-6, AC-13

---

### 4. Layout Sidebar Always-Render

- **Purpose**: Removes the `isAuthenticated` condition from `Layout.tsx` that gates the `NavigationSidebar`, so the sidebar is rendered for all users (authenticated and unauthenticated). Unauthenticated users receive their backend-filtered guest navigation links.
- **Scope**:
  - MODIFY `gravitycar-frontend/src/components/layout/Layout.tsx`
- **Blocks**: nothing
- **Blocked by**: nothing
- **Acceptance Criteria**: AC-8

---

### 5. PublicRoute Redirect Target Update

- **Purpose**: Changes the `PublicRoute` component in `App.tsx` to redirect authenticated users to `/` instead of `/dashboard`, since `/dashboard` is being removed. Also passes through a valid `?redirect=` query param when redirecting away from `/login`.
- **Scope**:
  - MODIFY `gravitycar-frontend/src/App.tsx` (PublicRoute component only — isolated change within the file)
- **Blocks**: Item 7 (App.tsx Route Overhaul) — this change will be part of the same file edit, but logically it is a standalone concern
- **Blocked by**: nothing
- **Acceptance Criteria**: AC-7, AC-9

---

### 6. Post-Login Redirect in Login Component

- **Purpose**: Updates the `Login` component to read the `?redirect=` query parameter from the URL after a successful login. If the value begins with `/` it navigates there; otherwise (or if absent) navigates to `/`. Handles both credential login and Google login paths.
- **Scope**:
  - MODIFY `gravitycar-frontend/src/components/auth/Login.tsx`
- **Blocks**: nothing
- **Blocked by**: nothing (operates independently; Item 2 writes the `?redirect=` param, but the Login component can be updated independently)
- **Acceptance Criteria**: AC-14

---

### 7. App.tsx Route Overhaul

- **Purpose**: Performs the full route table restructuring in `App.tsx`:
  - Adds `NavigatorSetter` rendered at app root (calls `setNavigator` on mount)
  - Adds `/` route rendering `<Layout><ProjectsPage /></Layout>` with no guard
  - Removes `/dashboard` route
  - Removes `/metadata-test`, `/test-related-record`, `/movies-quotes-demo` routes
  - Removes all `ProtectedRoute` wrappers from remaining routes
  - Removes the `ProtectedRoute` component definition
  - Removes the `Navigate to="/dashboard"` default root redirect
  - Adds `/unauthorized` route rendering `<Layout><UnauthorizedPage /></Layout>` with no guard
  - Updates the 404 catch-all to link back to `/` instead of `/dashboard`
  - Removes unused imports (`Dashboard`, `MetadataTestPage`, `TestRelatedRecord`, `MoviesQuotesRelationshipDemo`)
- **Scope**:
  - MODIFY `gravitycar-frontend/src/App.tsx`
- **Blocks**: nothing
- **Blocked by**: Item 1 (NavigatorSetter needs `setNavigator` from navigation singleton), Item 3 (UnauthorizedPage component must exist), Item 5 (PublicRoute redirect target is part of this same file)
- **Acceptance Criteria**: AC-1, AC-2, AC-3, AC-7, AC-9, AC-10, AC-12

---

### 8. Delete Dashboard.tsx

- **Purpose**: Deletes the `Dashboard.tsx` page component file, which is no longer referenced after the route overhaul in Item 7.
- **Scope**:
  - DELETE `gravitycar-frontend/src/pages/Dashboard.tsx`
- **Blocks**: nothing
- **Blocked by**: Item 7 (App.tsx Route Overhaul — must remove the import before the file can safely be deleted)
- **Acceptance Criteria**: AC-2

---

## Dependency Summary

```
Item 1 (Navigation Singleton)
  └── blocks Item 2 (403 Interceptor in api.ts)
  └── blocks Item 7 (App.tsx Route Overhaul — NavigatorSetter)

Item 3 (UnauthorizedPage)
  └── blocks Item 7 (App.tsx Route Overhaul — needs component to import)

Item 7 (App.tsx Route Overhaul)
  └── blocks Item 8 (Delete Dashboard.tsx — must remove import first)

Items 4, 5, 6 have no blocking dependencies and can be built in parallel with each other and with Items 1, 2, 3.
```

---

## Build Order (Suggested)

| Wave | Items | Rationale |
|------|-------|-----------|
| 1 | 1, 3, 4, 6 | No dependencies; can all be built in parallel |
| 2 | 2, 5 | Item 2 needs Item 1; Item 5 is prep for Item 7 |
| 3 | 7 | Needs Items 1, 3 complete; incorporates Item 5 changes |
| 4 | 8 | Needs Item 7 complete (import removed first) |
