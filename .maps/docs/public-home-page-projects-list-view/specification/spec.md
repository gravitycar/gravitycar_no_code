# Specification: Public Home Page — Projects List View

**Epic**: Public Home Page / Projects List View  
**Epic ID**: 48  
**Status**: Draft  
**Author**: Architect Agent  
**Date**: 2026-05-25

---

## 1. Problem Statement

The application currently has no public-facing home page. The root path (`/`) redirects to `/dashboard`, which is protected behind authentication. Unauthenticated visitors (the general public) are immediately redirected to `/login` and cannot view anything about the site.

The portfolio's Projects showcase already exists at `/projects_showcase` as a public, unauthenticated route. The goal is to promote this public showcase to the root URL (`/`) and eliminate the dashboard-centric default so that the site presents itself publicly by default. Authenticated users continue to have full access to all protected areas via the navigation sidebar.

---

## 2. Goals

1. Make `/` render `ProjectsPage` publicly — no authentication required.
2. Remove `/dashboard` and its `Dashboard` component entirely.
3. Remove `ProtectedRoute` wrappers from all routes (API-layer enforcement replaces route-guard enforcement).
4. Enforce 403 forbidden responses in the API service by redirecting to a new `/unauthorized` page.
5. Confirm that 401 interception (already implemented) continues to redirect to `/login` and clears auth state.
6. Maintain `PublicRoute` on `/login` to redirect already-authenticated users away from the login screen.
7. Preserve the guest navigation experience: guests see the navigation sidebar with backend-filtered links appropriate for their role; no user-specific header items are shown.
8. Ensure no flash-of-content on initial load for either authenticated or unauthenticated users.

---

## 3. Scope

### In Scope

- `gravitycar-frontend/src/App.tsx` — route table changes, removal of `/dashboard`, change `/` target, removal of `ProtectedRoute` wrappers, deletion of `/metadata-test` / `/test-related-record` / `/movies-quotes-demo` routes, addition of `NavigatorSetter` component rendered at app root
- `gravitycar-frontend/src/components/layout/Layout.tsx` — remove the `isAuthenticated` condition that gates `NavigationSidebar`; always render the sidebar so guests receive their backend-filtered navigation links
- `gravitycar-frontend/src/services/api.ts` — add 403 response interceptor branch that calls `imperativeNavigate('/unauthorized', { replace: true })`; wrap `XDEBUG_TRIGGER` in `if (import.meta.env.DEV)`
- `gravitycar-frontend/src/utils/navigate.ts` — NEW module-level navigation singleton that exports `setNavigator(fn)` and `imperativeNavigate(path, options)`; the `NavigatorSetter` component calls `setNavigator` with the `useNavigate()` result, and the axios interceptor calls `imperativeNavigate`
- `gravitycar-frontend/src/pages/Dashboard.tsx` — DELETE this file
- `gravitycar-frontend/src/pages/UnauthorizedPage.tsx` — NEW page rendered at `/unauthorized`
- `PublicRoute` component — update redirect target from `/dashboard` to `/` (since `/dashboard` is removed); pass through any `?redirect=` query param when redirecting authenticated users away from `/login`
- `gravitycar-frontend/src/pages/Login.tsx` (or equivalent login component) — after successful login, read `?redirect=` query param and navigate there instead of `/`; validate that the redirect value is a relative path before following it

### Out of Scope

- Backend authentication or authorization changes
- Changes to `ProjectsListView`, `ProjectDetailModal`, or any other Projects component behavior
- Role-based access control additions beyond what already exists
- The `/projects_showcase` alias route (it remains, pointing at `ProjectsPage`)
- Any backend navigation endpoint changes

---

## 4. Functional Requirements

### 4.1 Root Route `/`

- The root path SHALL render `ProjectsPage` without any authentication requirement.
- The root path SHALL NOT be wrapped in `ProtectedRoute`.
- The root path SHALL be wrapped in `<Layout>` (same as `/projects_showcase`).
- Unauthenticated visitors SHALL see the full projects grid immediately on arrival.
- Authenticated users SHALL see the full projects grid with the navigation sidebar (same as any other Layout-wrapped page).

### 4.2 Dashboard Removal

- The `/dashboard` route SHALL be removed from the route table in `App.tsx`.
- The `Dashboard.tsx` page component SHALL be deleted.
- No route or component in the application SHALL reference `/dashboard` after this change.

### 4.3 ProtectedRoute Removal

- All `ProtectedRoute` wrappers SHALL be removed from all route definitions in `App.tsx`.
- Pages that were previously protected SHALL continue to work correctly for authenticated users.
- Pages that were previously protected SHALL receive a 401 or 403 response from the backend API when called without valid credentials; the axios interceptor handles the redirect.
- The `ProtectedRoute` component definition itself SHALL be removed from `App.tsx`.
- The following routes SHALL be deleted from `App.tsx` entirely (not merely have their `ProtectedRoute` wrapper removed):
  - `/metadata-test` and its associated page component
  - `/test-related-record` and its associated page component
  - `/movies-quotes-demo` and its associated page component

### 4.4 401 Response Handling (Update)

- The axios response interceptor in `api.ts` already handles 401 responses by clearing `localStorage` and redirecting to `/login`.
- This redirect SHALL be updated to append the current path as a `?redirect=` query parameter so that after login the user is returned to the page they were trying to access: `window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search)`.
- After the 401 handler runs, the interceptor SHALL continue to return `Promise.reject(error)` to halt further promise chain execution.

### 4.5 403 Response Handling (New Behavior)

- The axios response interceptor in `api.ts` SHALL be updated to handle 403 responses.
- On a 403 response, the interceptor SHALL call `imperativeNavigate('/unauthorized', { replace: true })` to navigate to the `/unauthorized` page. The `replace: true` option prevents the blocked page from remaining in browser history (back-trap prevention).
- The `imperativeNavigate` function is a module-level singleton exported from a navigation singleton module. The 403 interceptor SHALL NOT use `window.location.href` for this redirect — React Router navigation must be used to avoid a full page reload and to keep router history clean.
- The interceptor SHALL NOT redirect to `/unauthorized` if `window.location.pathname` is already `/unauthorized`. This guard prevents an infinite redirect loop if any API call made from the `/unauthorized` page itself returns 403.
- On a 403 response, the interceptor SHALL NOT clear `localStorage` or log the user out — the user is authenticated but simply lacks permission.
- On a 403 response, the interceptor SHALL return `Promise.reject(error)` after the redirect to halt further promise chain execution.
- The interceptor SHALL NOT redirect to `/login` on a 403 — that is reserved for 401 (unauthenticated).
- Note: `window.location.href = '/login'` used by the 401 interceptor branch is UNCHANGED — only the 403 handler uses the React Router `imperativeNavigate` approach.

### 4.6 Unauthorized Page (`/unauthorized`)

- A new page SHALL be created at the path `/unauthorized`.
- The page SHALL NOT be wrapped in `ProtectedRoute`.
- The page SHALL be wrapped in `<Layout>`.
- The page SHALL display a clear message that the user does not have permission to access the requested resource.
- The page SHALL provide a link or button to return to the home page (`/`).
- If the user is authenticated, the navigation sidebar SHALL be visible (standard Layout behavior).
- If the user is unauthenticated, the page SHALL still be renderable — a guest may receive a 403 on a resource that the backend considers forbidden to guests with no auth at all.

### 4.7 PublicRoute Update

- The `PublicRoute` component currently redirects authenticated users to `/dashboard`.
- This redirect target SHALL be changed to `/` (the new public home page).
- The `PublicRoute` SHALL continue to wrap the `/login` route only.
- An authenticated user who visits `/login` SHALL be redirected to `/`, unless a valid `?redirect=` query param is present — in that case they SHALL be redirected to the value of `?redirect=` instead.

### 4.8 Navigation Sidebar for Guests

- The `Layout` component currently renders `NavigationSidebar` only when `isAuthenticated === true`. This condition SHALL be removed.
- The `NavigationSidebar` SHALL be rendered for all users — authenticated and unauthenticated alike.
- The sidebar is populated by the backend `/navigation` endpoint, which already returns role-appropriate links based on the caller's identity (or guest role if unauthenticated). No changes to `NavigationSidebar.tsx` are required.
- Unauthenticated users visiting `/` SHALL see the sidebar with their guest-appropriate navigation links.

### 4.9 Flash-of-Content Prevention

- The existing `isLoading` pattern in `AuthProvider` (starts `true`, resolves after `checkAuth()` completes) already handles flash-of-content for the `ProtectedRoute` guard.
- After removing `ProtectedRoute`, the public pages do not need a loading gate — they render immediately regardless of auth state.
- For remaining cases where a component conditionally shows authenticated-only UI elements (e.g., edit buttons), those components SHALL read `isLoading` from `useAuth()` and defer rendering auth-conditional UI until `isLoading` is `false`.
- The root page `/` renders `ProjectsPage`, which has no auth-conditional UI — no flash risk.

### 4.10 Post-Login Redirect

- After a successful login, the `Login` component SHALL read the `?redirect=` query parameter from the current URL.
- If a valid `?redirect=` value is present, the user SHALL be navigated to that path instead of `/`.
- A valid redirect value is one that begins with `/` (a relative path). Any value that does not begin with `/` SHALL be ignored and the user SHALL be sent to `/` (open redirect prevention).
- If no `?redirect=` param is present, the user SHALL be sent to `/` (existing behavior).

### 4.11 Development-Only XDEBUG_TRIGGER Parameter

- The `api.ts` request interceptor currently appends an `XDEBUG_TRIGGER` query parameter to all outgoing requests for debugging purposes.
- This parameter SHALL be wrapped in `if (import.meta.env.DEV)` so that it is only appended when the application is running in development mode.
- In production builds, no `XDEBUG_TRIGGER` parameter SHALL be added to any API request.

---

## 5. Non-Functional Requirements

- No new dependencies shall be added to the frontend project.
- All changes are confined to the frontend codebase (`gravitycar-frontend/src/`).
- The `/unauthorized` page must be reachable by both authenticated and unauthenticated users.
- Removing `ProtectedRoute` does not reduce actual security — the backend API is the enforcement boundary. This requirement is explicitly acknowledged: frontend route guards are UX only.

---

## 6. Technical Context

### Existing Pattern: No-Guard Public Route
The `/projects_showcase` route is already public: it has no `ProtectedRoute` wrapper, is wrapped in `<Layout>`, and renders `ProjectsPage`. The new `/` route SHALL follow the identical pattern.

### Existing Pattern: Auth Context Shape
`useAuth()` provides `{ isAuthenticated, isLoading, user, login, logout, checkAuth }`. `isLoading` is `true` during the initial `checkAuth()` call on app mount; it resolves to `false` after the `/auth/me` API call completes. This pattern is already in place and does not need modification.

### Existing Pattern: axios Response Interceptor in `api.ts`
The current interceptor has conditional branches for:
- Network error (no response): rejects with "Network error"
- 401: clears localStorage, redirects to `/login` via `window.location.href`, shows alert if session expired — **unchanged**
- 403: rejects with "Access denied" — **this is the branch to update**
- Other HTTP errors: rejects with status-specific message

The 403 branch update SHALL call `imperativeNavigate('/unauthorized', { replace: true })` (from the navigation singleton) before the `Promise.reject(error)` in that branch. It SHALL also check `window.location.pathname !== '/unauthorized'` before navigating to prevent redirect loops. Note: `window.location.href = '/login'` for 401 is left unchanged.

### Navigation Singleton Pattern (`navigate.ts` / `NavigatorSetter`)
Axios interceptors execute outside of React's component scope, so `useNavigate()` cannot be called directly from `api.ts`. The solution uses a module-level singleton:

- A new utility module (e.g., `gravitycar-frontend/src/utils/navigate.ts`) exports two functions:
  - `setNavigator(fn)` — stores a reference to a React Router `NavigateFunction`
  - `imperativeNavigate(path, options)` — calls the stored function, or falls back to `window.location.href` if the navigator has not yet been set (rare startup edge case)
- A new `NavigatorSetter` component is rendered once inside `App.tsx` (outside the route definitions). It calls `useNavigate()` and on mount calls `setNavigator()` with the result.
- The 403 interceptor in `api.ts` imports and calls `imperativeNavigate('/unauthorized', { replace: true })`.
- This pattern keeps `api.ts` free of React component code while enabling proper React Router navigation from the interceptor.

### Existing Pattern: PublicRoute
`PublicRoute` currently redirects authenticated users to `/dashboard`. Since `/dashboard` is being removed, the redirect target MUST be changed to `/` to prevent a routing dead end.

### Layout Sidebar Visibility (Change Required)
`Layout.tsx` currently renders `<NavigationSidebar>` only when `isAuthenticated === true`. This condition SHALL be removed so the sidebar is always rendered. The sidebar is safe to show for unauthenticated users because the backend `/navigation` endpoint returns only the links the caller is permitted to see — it handles guest role filtering server-side.

---

## 7. Edge Cases

### 7.1 Authenticated User Visits `/`
Authenticated users arrive at `/` and see `ProjectsPage` with the full sidebar. This is the intended behavior — the projects showcase is a valid landing page for authenticated users too.

### 7.2 Unauthenticated User Visits a Previously-Protected Route
Without `ProtectedRoute`, the page will render and immediately fire API requests. The API will return 401 (if the endpoint requires auth) or 403 (if the endpoint requires a specific role). The axios interceptor handles both: 401 clears state and redirects to `/login`; 403 redirects to `/unauthorized`. The page component may briefly render before the interceptor fires — this is acceptable for non-sensitive UI elements (empty states, loading spinners).

### 7.3 Login Redirect Loop
Without careful handling, redirecting an unauthenticated user to `/login` and then back could loop. This is prevented by:
- `PublicRoute` on `/login` redirects authenticated users to `/` (not `/dashboard`, which is removed).
- The 401 interceptor sends unauthenticated users to `/login`.
- The `/login` route has no API calls that would trigger a 401.
- There is no loop risk.

### 7.4 Guest Navigates Directly to `/unauthorized`
This is valid — guests may arrive here via a 403 from the interceptor. The page SHALL render without any auth requirement and SHALL show a sensible message with a link back to `/`.

### 7.5 ProjectsPage API Call Without Auth Token
`ProjectsListView` calls `GET /Projects` via `apiService.getList()`. The API service attaches `Authorization: Bearer <token>` only when a token exists in `localStorage`. For unauthenticated users no Authorization header is sent. The backend `/Projects` endpoint already supports unauthenticated access (evidenced by `/projects_showcase` working publicly today). No backend changes are required.

### 7.6 Browser Refresh on a Previously-Protected Page
After removing `ProtectedRoute`, a user who bookmarks `/trivia` and refreshes will see the page render, fire API calls, get 401 back (if their session expired), and be redirected to `/login`. The experience is slightly different from the previous behavior (spinner then redirect) but functionally correct: the user ends up at `/login`. After successful login, they are redirected to `/` rather than back to their original URL — this is an accepted limitation of the current auth flow.

### 7.7 ProjectDetailModal Auth Check
`ProjectDetailModal` was not found to contain auth-gated features in the research phase. If it does contain any auth-conditional actions (e.g., edit/delete buttons shown only to authenticated users), those buttons SHALL read `isAuthenticated` from `useAuth()` directly rather than relying on route-level guards.

### 7.8 Duplicate Route: `/projects_showcase` and `/`
Both `/` and `/projects_showcase` will render `ProjectsPage`. This is intentional. The `/projects_showcase` route is kept as an alias so that any existing links to it continue to work. No consolidation of the two routes is required.

---

## 8. Acceptance Criteria

### AC-1: Public Home Page
- An unauthenticated visitor who navigates to `/` SHALL see the Projects grid (tiles with screenshots, titles, status badges).
- No login redirect SHALL occur when navigating to `/`.

### AC-2: Dashboard Removed
- Navigating to `/dashboard` SHALL result in a 404 or the catch-all route rendering (no dedicated dashboard page exists).
- The `Dashboard.tsx` file SHALL not exist in the codebase.
- No route in `App.tsx` SHALL reference `/dashboard`.

### AC-3: No ProtectedRoute Wrappers
- `ProtectedRoute` component definition SHALL not exist in `App.tsx`.
- No route definition in `App.tsx` SHALL use `ProtectedRoute`.

### AC-4: 401 Interceptor (Confirm)
- When any API call returns a 401 response, `localStorage` SHALL be cleared and the browser SHALL navigate to `/login`.
- This behavior SHALL be unchanged from the pre-existing implementation.

### AC-5: 403 Interceptor (New)
- When any API call returns a 403 response, the browser SHALL navigate to `/unauthorized`.
- The user SHALL NOT be logged out (localStorage SHALL retain `auth_token` and `user`).
- The promise chain SHALL be halted (component does not receive a resolved value).

### AC-6: Unauthorized Page
- A page SHALL exist at the path `/unauthorized`.
- It SHALL be accessible to both authenticated and unauthenticated users.
- It SHALL display a message indicating the user lacks permission.
- It SHALL include a navigation element returning the user to `/`.

### AC-7: PublicRoute Redirect Target
- An authenticated user who visits `/login` SHALL be redirected to `/` (not `/dashboard`).

### AC-8: Guest Navigation
- An unauthenticated user viewing `/` SHALL see the navigation sidebar populated with their guest-appropriate links (as returned by the backend `/navigation` endpoint).
- An authenticated user viewing `/` SHALL see the navigation sidebar populated with their role-appropriate links.

### AC-9: No Login Redirect Loop
- Navigating to `/login` as an unauthenticated user SHALL not produce any redirect loop.
- Navigating to `/login` as an authenticated user SHALL redirect to `/` exactly once.

### AC-10: Flash-of-Content
- An unauthenticated user navigating to `/` SHALL see the projects grid without any intermediate login redirect or flash.
- An authenticated user navigating to any former protected route SHALL see the page content without a flash-to-spinner-to-content sequence (since loading state is checked by the AuthProvider on startup, not per-route).

### AC-11: XDEBUG_TRIGGER Not Present in Production
- In a production build of the frontend, API requests SHALL NOT include the `XDEBUG_TRIGGER` query parameter.
- In a development build, the `XDEBUG_TRIGGER` parameter SHALL continue to be appended as before.

### AC-12: Deleted Test Routes Are Gone
- The routes `/metadata-test`, `/test-related-record`, and `/movies-quotes-demo` SHALL NOT exist in `App.tsx`.
- Navigating to any of these paths in the browser SHALL result in the catch-all route handling (not a dedicated page).

### AC-14: Post-Login Redirect
- An unauthenticated user who is redirected to `/login` after a 401 response SHALL, after successful login, be navigated to the original URL they were trying to access.
- If the `?redirect=` value does not start with `/`, the user SHALL be sent to `/` instead (open redirect prevention).
- If no `?redirect=` param is present, the user SHALL be sent to `/`.

### AC-13: 403 Redirect Loop Guard
- When the user is already on the `/unauthorized` page and a background API call returns 403, the browser SHALL NOT redirect again to `/unauthorized`.
- The interceptor SHALL silently reject the promise without triggering navigation when `window.location.pathname` is already `/unauthorized`.

---

## 9. Explicit Constraints (DO NOT)

- Do NOT add authentication requirements to `ProjectsPage` or `ProjectsListView`.
- Do NOT modify `NavigationSidebar.tsx` — it already renders whatever links the backend returns; no changes needed there.
- Do NOT redirect on 403 to `/login` — that would incorrectly log the user out.
- Do NOT redirect on 403 to `/` silently — the user needs to know access was denied.
- Do NOT clear `localStorage` on a 403 response.
- Do NOT create a new `ProtectedRoute`-like component to replace the removed one.
- Do NOT change the `/projects_showcase` alias — keep it working alongside `/`.
- Do NOT modify backend files as part of this change.
- Do NOT add any new npm dependencies.

---

## 10. Open Questions

1. ~~**`/unauthorized` page content depth**: Should `/unauthorized` display the path that was denied (e.g., "You don't have access to /admin/users"), or a generic message only?~~ **RESOLVED**: Generic message only — "you don't have permission" with a link back to `/`. No path-specific context is displayed. This is reflected in §4.6 and AC-6.

2. ~~**After-login redirect to original URL**~~ **RESOLVED**: Implemented. The 401 interceptor appends `?redirect=<original-path>` to the `/login` URL. The `Login` component reads this param after successful login and navigates there. `PublicRoute` also passes through the redirect param. Relative paths only; absolute URLs are rejected (open redirect prevention). See §4.4, §4.7, §4.10, and AC-14.

3. ~~**Catch-all route behavior for `/dashboard`**~~ **RESOLVED**: Acceptable for `/dashboard` to fall through to the `/:modelName` catch-all, which will return a backend 404 or error. No dedicated 404 page is needed for this epic. This is reflected in AC-2.
