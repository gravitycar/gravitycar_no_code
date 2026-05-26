# Test Results: Integration — 2026-05-25

## Summary
- Total tests (integration file): 22
- Passed: 22
- Failed: 0
- Skipped: 0

## Full Suite Summary
- Test files: 7 (1 pre-existing failure: NavigationSidebar.test.tsx uses `jest` instead of `vi` — not related to this epic)
- Total tests passing: 61
- Total tests failing: 0 (the pre-existing NavigationSidebar.test.tsx file fails to load due to `jest is not defined`, but all 61 executable tests pass)

## Test File
`gravitycar-frontend/src/__tests__/routing.integration.test.tsx`

## Passed Tests

### AC-1: Public home page at /
- renders ProjectsPage for an unauthenticated user — no redirect to /login
- renders ProjectsPage for an authenticated user at /

### AC-2: No /dashboard route — falls through to DynamicModelRoute
- navigating to /dashboard renders DynamicModelRoute (catch-all), not a dedicated dashboard
- navigating to /dashboard does NOT render a "Dashboard" component

### AC-3: No ProtectedRoute — routes render for unauthenticated users
- /unauthorized is accessible to unauthenticated users
- /projects_showcase renders for unauthenticated users (no ProtectedRoute)
- /:modelName catch-all renders for unauthenticated users (no ProtectedRoute)

### AC-6: /unauthorized page
- renders for an unauthenticated user with a permission-denied message
- renders a link back to / for unauthenticated users
- renders for an authenticated user with a link to /

### AC-7: PublicRoute redirect for authenticated users
- authenticated user at /login is redirected to / (not /dashboard)

### AC-7 + AC-14: PublicRoute preserves ?redirect= for authenticated users
- authenticated user at /login?redirect=%2Fevents is redirected to /events
- authenticated user at /login?redirect=https://evil.com is redirected to / (open redirect prevention)
- authenticated user at /login?redirect=//evil.com is redirected to / (protocol-relative rejection)

### AC-8: NavigationSidebar renders for all users
- NavigationSidebar is rendered for an unauthenticated user at /
- NavigationSidebar is rendered for an authenticated user at /
- NavigationSidebar is rendered for unauthenticated user at /unauthorized

### AC-9: No login redirect loop
- unauthenticated user at /login stays on /login (login page rendered)
- unauthenticated user at /login is NOT redirected to / or elsewhere

### AC-12: Deleted test routes are gone (hit catch-all)
- /metadata-test hits the DynamicModelRoute catch-all (no dedicated page)
- /test-related-record hits the DynamicModelRoute catch-all (no dedicated page)
- /movies-quotes-demo hits the DynamicModelRoute catch-all (no dedicated page)

## Failed Tests
None.

## Notes
- The `NavigationSidebar.test.tsx` pre-existing test file uses `jest.mock()` and `jest.fn()` instead of `vi.mock()` and `vi.fn()`, causing a `ReferenceError: jest is not defined`. This failure pre-dates this epic and is not related to these changes.
- Integration tests use a mirrored route table (`TestRoutes`) because `App.tsx` does not export `AppRoutes`. The mirror covers only the routes needed for the acceptance criteria.
