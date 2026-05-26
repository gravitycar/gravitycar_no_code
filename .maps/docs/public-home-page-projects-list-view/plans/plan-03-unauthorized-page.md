# Implementation Plan: Unauthorized Page

## Spec Context

This plan fulfills §4.6 of the specification, which requires a new `/unauthorized` page that is
accessible to both authenticated and unauthenticated users. It displays a generic
"you don't have permission" message and provides a link back to `/`. The page is the
navigation target when the 403 interceptor in `api.ts` fires, as specified in §4.5. It must
be wrapped in `<Layout>` and must NOT use `ProtectedRoute`.

Catalog item: 3 — Unauthorized Page
Specification sections: §4.6, §4.5 (consumer context)
Acceptance criteria addressed: AC-6, AC-13 (guard condition lives in `api.ts`, not here —
this plan is responsible only for the page component itself)

---

## Dependencies

- **Blocked by**: nothing — this component is standalone; it only uses React, React Router's
  `<Link>`, and the `<Layout>` wrapper that already exists
- **Blocks**: Catalog Item 7 (App.tsx Route Overhaul) — `App.tsx` must import
  `UnauthorizedPage` before registering the `/unauthorized` route
- **Uses**:
  - `gravitycar-frontend/src/components/layout/Layout.tsx` — existing Layout wrapper
    (the route in `App.tsx` wraps this page in `<Layout>`, same as all other pages; the
    component itself does NOT import Layout — `App.tsx` applies the wrapper)
  - React Router DOM `<Link>` — for the "Go to home page" navigation element

---

## File Changes

### New Files

- `gravitycar-frontend/src/pages/UnauthorizedPage.tsx` — new page component

### Modified Files

None — route registration (`/unauthorized`) is handled in Catalog Item 7 (App.tsx Route
Overhaul) and is out of scope for this plan.

---

## Implementation Details

### UnauthorizedPage Component

**File**: `gravitycar-frontend/src/pages/UnauthorizedPage.tsx`

**Exports**:
- `default UnauthorizedPage` — React functional component, no props

**Pattern reference**: Follow `ProjectsPage.tsx` — a minimal functional component with a
single outer `div` using Tailwind classes, no auth hooks, no state.

**Rendering approach**:
- Outer `div` with `min-h-screen bg-gray-50` (matches `ProjectsPage` background)
- Centered content card using `flex flex-col items-center justify-center` with top padding
- A visual icon or large status indicator (a styled "403" or a lock symbol rendered with
  a plain Unicode character `🚫` is acceptable — but per project constraints no emoji should
  be used in files; use a text-based or SVG approach)
- Heading text: "Access Denied"
- Body text: "You don't have permission to view this page."
- A `<Link to="/">` styled as a button that reads "Go to Home Page"

**Styling approach** (Tailwind CSS only — no Shadcn, no Radix, no external UI):
- Outer wrapper: `min-h-screen bg-gray-50 flex flex-col items-center justify-center py-12`
- Content card: `bg-white rounded-lg shadow-md p-8 max-w-md w-full text-center`
- Icon area: `text-6xl text-red-400 mb-4` containing a simple SVG lock or an HTML entity
  (e.g., `&#128274;` is an emoji — avoid). Use a plain inline SVG icon instead (see Code
  Example below).
- Heading: `text-2xl font-bold text-gray-900 mb-2`
- Body paragraph: `text-gray-600 mb-6`
- Link/button: `inline-block bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700
  transition-colors text-sm font-medium`

**Code Example**:

```tsx
import React from 'react';
import { Link } from 'react-router-dom';

/**
 * UnauthorizedPage
 *
 * Rendered at /unauthorized when the axios 403 interceptor fires.
 * Accessible to both authenticated and unauthenticated users.
 * No ProtectedRoute wrapper — the route in App.tsx is public.
 */
const UnauthorizedPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center py-12">
      <div className="bg-white rounded-lg shadow-md p-8 max-w-md w-full text-center">

        {/* Lock icon — inline SVG, no external library */}
        <div className="flex justify-center mb-4">
          <svg
            className="w-16 h-16 text-red-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
            />
          </svg>
        </div>

        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          Access Denied
        </h1>

        <p className="text-gray-600 mb-6">
          You don't have permission to view this page.
        </p>

        <Link
          to="/"
          className="inline-block bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium"
        >
          Go to Home Page
        </Link>

      </div>
    </div>
  );
};

export default UnauthorizedPage;
```

---

## Error Handling

This component has no API calls and no async logic, so there are no error conditions to handle.

---

## Unit Test Specifications

The component is a pure presentational component with no state or side effects. Tests use
React Testing Library (if available in the project) or shallow rendering. Do not test log
output per project guidelines.

### `UnauthorizedPage` render tests

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Renders without crashing | Mount component with MemoryRouter | No exception thrown | Basic smoke test |
| Displays heading | Mount component | Element with text "Access Denied" is present | AC-6 |
| Displays permission message | Mount component | Text containing "don't have permission" is present | AC-6 |
| Renders home link | Mount component | An `<a>` element with `href="/"` is present | AC-6 (link to `/`) |
| Home link text | Mount component | Link text is "Go to Home Page" | AC-6 |
| No auth requirement | Mount without any auth context / unauthenticated wrapper | Component renders without redirect or error | AC-6 (guest access) |

### Key Scenario: Home link navigates to `/`

**Setup**: Render `<MemoryRouter><UnauthorizedPage /></MemoryRouter>`  
**Action**: Inspect rendered output for anchor href  
**Expected**: The `<Link to="/">` renders as `<a href="/">` pointing to the root  
**Why**: Confirms AC-6 requirement that the page provides a navigation element back to `/`

---

## Notes

- The `<Layout>` wrapper is applied in `App.tsx` at the route level (same pattern used for
  all other pages including `ProjectsPage`). `UnauthorizedPage` itself does NOT import or
  render `<Layout>` — the route definition handles that.
- The SVG path used for the lock icon is the Heroicons `lock-closed` outline path, which is
  license-free and requires no npm package when inlined.
- `<Link>` from `react-router-dom` is used (not `<a href>`) to keep navigation within the
  React Router context and avoid a full page reload. This is consistent with how other pages
  in the codebase link between routes.
- The component is intentionally minimal — no state, no effects, no auth hooks. This ensures
  it renders correctly regardless of auth state (AC-6, §7.4).
- The `replace: true` option on the 403 interceptor navigation (handled in Catalog Item 2)
  means the browser history entry for the blocked page is replaced by `/unauthorized`, so
  clicking "Go to Home Page" returns the user to the page before the blocked one, not into a
  loop. This page has no responsibility for that behavior — it is handled upstream.
