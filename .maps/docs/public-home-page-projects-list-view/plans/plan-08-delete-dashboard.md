# Implementation Plan: Delete Dashboard.tsx

## Spec Context

This plan removes the `Dashboard.tsx` page component from the frontend codebase. The
`/dashboard` route has been deleted and the `Dashboard` import has been removed from
`App.tsx` by plan-07. Once that is done, `Dashboard.tsx` is an orphaned file with no
remaining importers in the production codebase. This plan verifies that state and then
deletes the file.

Catalog item: 8 — Delete Dashboard.tsx  
Specification sections: §4.2  
Acceptance criteria addressed: AC-2 (`Dashboard.tsx` SHALL NOT exist in the codebase)

---

## Dependencies

- **Blocked by**: Item 7 (App.tsx Route Overhaul) — `App.tsx` must have its `Dashboard`
  import and `/dashboard` route removed before this file can safely be deleted. If the file
  were deleted before plan-07 runs, `App.tsx` would fail to compile.
- **Uses**: None beyond the file itself.

---

## File Changes

### Deleted Files

- `gravitycar-frontend/src/pages/Dashboard.tsx` — Orphaned dashboard component; deleted
  per §4.2 and AC-2.

### Modified Files

- `gravitycar-frontend/src/components/routing/DynamicModelRoute.tsx` — The error-state JSX
  at line 19 contains `href="/dashboard"` and `Go to Dashboard`. This is a dead link since
  `/dashboard` no longer exists. Update to point to `/` with the text `Go to Home Page`.

---

## Pre-Deletion Verification

Before deleting the file, confirm no remaining importers exist. The verification search
confirms:

| File | Reference | Status after plan-07 |
|------|-----------|---------------------|
| `gravitycar-frontend/src/App.tsx` | `import Dashboard from './pages/Dashboard'` and `<Dashboard />` usage | **Removed by plan-07** |
| `gravitycar-frontend/src/components/routing/DynamicModelRoute.tsx` | `href="/dashboard"` link text only — NOT an import of the module | Requires text update (see below); does NOT block deletion |
| `gravitycar-frontend/src/components/navigation/__tests__/NavigationSidebar.test.tsx` | String literal `'Dashboard'` in test mock data and assertions | Test data for a navigation link label — NOT an import of the module; no change needed |

Key finding: `DynamicModelRoute.tsx` contains an `href="/dashboard"` link but does NOT
import `Dashboard` as a module. It is a stale link target, not a module dependency.
`NavigationSidebar.test.tsx` uses the string `'Dashboard'` only as mock navigation item
data, not as a reference to the `Dashboard` component.

**After plan-07 runs, `Dashboard.tsx` has zero module importers.** The file is safe to
delete.

---

## Implementation Details

### Step 1 — Verify no module imports remain

Before deleting, run the following check to confirm the only remaining references are the
non-blocking ones identified above:

```bash
grep -rn "from.*pages/Dashboard\|import.*Dashboard" \
  gravitycar-frontend/src/ \
  --include="*.tsx" --include="*.ts"
```

Expected output: **no results**. If any results are returned, stop and resolve the
remaining import before proceeding.

### Step 2 — Delete Dashboard.tsx

```bash
rm gravitycar-frontend/src/pages/Dashboard.tsx
```

The file is a self-contained component with no side effects. Deleting it leaves no
orphaned exports or broken module graph (all importers were removed in plan-07).

### Step 3 — Update DynamicModelRoute.tsx error fallback link

`DynamicModelRoute.tsx` contains the following error-state JSX (rendered when `modelName`
is falsy — an edge case that should never occur in normal routing):

```tsx
// Before — line 19 (stale link target)
<a href="/dashboard" className="text-blue-600 hover:text-blue-800">
  Go to Dashboard
</a>

// After — updated to home page
<a href="/" className="text-blue-600 hover:text-blue-800">
  Go to Home Page
</a>
```

**File**: `gravitycar-frontend/src/components/routing/DynamicModelRoute.tsx`

This change removes the last reference to `/dashboard` anywhere in the codebase (outside
of test mock data, which refers to a navigation link label, not the route).

### Step 4 — No NavigationSidebar test changes required

The `NavigationSidebar.test.tsx` references to `'Dashboard'` are string literals used as
mock navigation link labels in test data (e.g., `{ title: 'Dashboard', path: '/dashboard' }`).
These are testing the sidebar's rendering behaviour with arbitrary mock data, not
importing or exercising the `Dashboard` component. They do not reference the deleted file
and do not need to change.

---

## Error Handling

- If Step 1 finds remaining module imports, do NOT proceed with deletion. Investigate which
  plan failed to remove its `Dashboard` import and resolve that first.
- There are no runtime error paths introduced by this change. Deleting a file that is no
  longer imported has no effect at runtime.

---

## Unit Test Specifications

No new unit tests are required for this plan. The act of file deletion is validated by:

1. The pre-deletion grep check (Step 1) — confirms zero module importers.
2. The AC-2 acceptance test in plan-07's test suite — "Visit `/dashboard`" confirms
   `Dashboard` component does not render at that route.
3. A post-deletion build/compile check — TypeScript will error if any import of the
   deleted file was missed.

### Regression check for DynamicModelRoute

| Case | Action | Expected | Why |
|------|--------|----------|----|
| No modelName param | Render `DynamicModelRoute` with no `modelName` param | Shows "Invalid Model" fallback with link `href="/"` labeled "Go to Home Page" | Confirms stale `/dashboard` link is replaced |

**Key Scenario: Invalid model fallback link**

**Setup**: Mount `DynamicModelRoute` with `useParams` mocked to return `{ modelName: undefined }`.  
**Action**: Render the component.  
**Expected**: A link with `href="/"` and text "Go to Home Page" is present in the output.  
**Why**: Confirms the stale `/dashboard` href was updated (AC-2: no route in the app SHALL
reference `/dashboard`).

---

## Notes

- **Dashboard.tsx contents summary**: The file is a 267-line self-contained functional
  component. It imports `useAuth`, `apiService`, and local types. It has no exports other
  than `export default Dashboard`. No other file re-exports it or re-uses its internals.
  Deletion is clean.
- **Test mock data**: `NavigationSidebar.test.tsx` uses the string `'Dashboard'` purely as
  mock content for navigation link rendering tests. These tests will continue to pass after
  deletion — they test the sidebar's rendering behaviour, not the Dashboard component.
- **No 404 page required**: Per spec §7 and AC-2, it is acceptable for `/dashboard` to fall
  through to the `/:modelName` dynamic route catch-all, which will attempt a backend lookup
  for a model named "dashboard". No dedicated 404 handling is needed for this removed route.
- **Build verification**: After completing all steps, a TypeScript compilation check
  (`tsc --noEmit` or equivalent) should confirm zero errors related to the deleted file.
