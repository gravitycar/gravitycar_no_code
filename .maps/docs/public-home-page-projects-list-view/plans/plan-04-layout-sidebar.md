# Implementation Plan: Layout Sidebar Always-Render

## Spec Context

`Layout.tsx` currently gates `<NavigationSidebar>` behind `isAuthenticated === true`, which means guests see no sidebar at all. Spec §4.8 and AC-8 require that the sidebar be rendered for all users — authenticated and unauthenticated — because the backend `/navigation` endpoint already performs role-based link filtering server-side and returns only the links the caller is permitted to see.

Catalog item: **4. Layout Sidebar Always-Render**  
Specification sections: §4.8, §6 "Layout Sidebar Visibility (Change Required)"  
Acceptance criteria addressed: **AC-8**

---

## Dependencies

- **Blocked by**: nothing — this change is self-contained.
- **Blocks**: nothing — no other catalog item depends on this plan.
- **Uses**: `NavigationSidebar.tsx` (existing, unmodified), `useAuth()` hook (existing).

---

## File Changes

### Modified Files

- `gravitycar-frontend/src/components/layout/Layout.tsx` — remove the `{isAuthenticated && ...}` conditional wrapper from the `<NavigationSidebar>` element; remove `isAuthenticated` from the `useAuth()` destructure since it will no longer be referenced.

### No New Files

No new files are created.

---

## Implementation Details

### Layout.tsx

**File**: `gravitycar-frontend/src/components/layout/Layout.tsx`

**Current state** (lines 11 and 69–71):

```tsx
// Line 11 — useAuth destructure
const { user, logout, isAuthenticated } = useAuth();

// Lines 69–71 — sidebar with auth gate
{isAuthenticated && (
  <NavigationSidebar className="w-64 flex-shrink-0" />
)}
```

**Required state after change**:

```tsx
// Line 11 — remove isAuthenticated from destructure
const { user, logout } = useAuth();

// Lines 69–71 — sidebar rendered unconditionally
<NavigationSidebar className="w-64 flex-shrink-0" />
```

**The `isAuthenticated` variable at line 49** (header section) gates the "Welcome, {name}" / "Logout" block for the header. That condition is intentional and must NOT be removed — guests should not see a welcome message or logout button. Only the sidebar's `isAuthenticated` gate (line 69) is removed.

Because `isAuthenticated` is still used in the header block (line 49), it must remain in the `useAuth()` destructure. Only if the header usage is also removed (which it is not in this plan) would the destructure entry be dropped.

**Corrected approach** — `isAuthenticated` remains in the destructure because it is still needed for the header conditional:

```tsx
// No change to this line — isAuthenticated is still used at line 49
const { user, logout, isAuthenticated } = useAuth();

// Only this block changes:
// BEFORE:
{isAuthenticated && (
  <NavigationSidebar className="w-64 flex-shrink-0" />
)}

// AFTER:
<NavigationSidebar className="w-64 flex-shrink-0" />
```

**Complete diff summary**:

- Remove the `{isAuthenticated && (` opening wrapper on the sidebar block.
- Remove the corresponding `)}` closing wrapper.
- The `<NavigationSidebar className="w-64 flex-shrink-0" />` element remains unchanged.
- All other Layout code is unchanged.

---

## NavigationSidebar Graceful Rendering (No Changes Needed)

`NavigationSidebar.tsx` already handles all rendering states gracefully without any assumptions about authentication:

| State | What renders |
|-------|-------------|
| `isLoading === true` | Animated skeleton pulse inside a `<nav>` |
| `error !== null` | Error message with a "Retry" button inside a `<nav>` |
| `navigationData === null` (after load) | "No navigation data available" message inside a `<nav>` |
| Data loaded successfully | Full navigation tree with custom pages and model links |

The component calls `navigationService.getCurrentUserNavigation()` on mount and whenever `user` changes. For an unauthenticated guest, this call reaches the backend `/navigation` endpoint without an Authorization header. The backend returns a guest-role navigation payload. `NavigationSidebar` renders that payload normally.

**The spec explicitly states**: "No changes to `NavigationSidebar.tsx` are required." This plan confirms that is correct — the component is already safe to render for unauthenticated users.

---

## Error Handling

- If `getCurrentUserNavigation()` fails for a guest (e.g., network error), `NavigationSidebar` already shows an error state with a retry button. No additional error handling is required in `Layout.tsx`.
- The `NavigationSidebar` error state renders a valid `<nav>` element — it never throws or crashes, so unconditionally rendering it in `Layout.tsx` introduces no crash risk.

---

## Unit Test Specifications

### `Layout` component — sidebar visibility

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Unauthenticated user | `isAuthenticated = false`, `user = null` | `<NavigationSidebar>` IS rendered | Sidebar must show for guests (AC-8) |
| Authenticated user | `isAuthenticated = true`, `user = { ... }` | `<NavigationSidebar>` IS rendered | Sidebar must show for authenticated users (AC-8) |
| Unauthenticated user — header | `isAuthenticated = false` | "Welcome" message and Logout button NOT rendered | Header auth gate is intentional and unchanged |
| Authenticated user — header | `isAuthenticated = true`, `user = { first_name: 'Alice', ... }` | "Welcome, Alice" and Logout button ARE rendered | Existing header behavior unchanged |

### Key Scenario: Unauthenticated renders sidebar

**Setup**: Render `<Layout>` with `useAuth()` mocked to return `{ isAuthenticated: false, user: null, logout: jest.fn() }`. Mock `NavigationSidebar` as a simple `<div data-testid="navigation-sidebar" />`.

**Action**: Render `<Layout><div>content</div></Layout>`.

**Expected**: `screen.getByTestId('navigation-sidebar')` is present in the DOM — the sidebar is rendered regardless of auth state.

### Key Scenario: Authenticated renders sidebar

**Setup**: Same as above but with `{ isAuthenticated: true, user: { first_name: 'Alice', last_name: 'Smith', email: 'a@b.com' } }`.

**Expected**: Both `screen.getByTestId('navigation-sidebar')` and the text "Welcome, Alice Smith" are present. The sidebar render is not affected by the auth state.

---

## Notes

- This is the smallest change in the entire epic: two lines removed from `Layout.tsx` (the opening `{isAuthenticated && (` and the closing `)}` of the sidebar block).
- The `isAuthenticated` destructure in `useAuth()` on line 11 is kept because it is still used by the header's Welcome/Logout conditional on line 49. Do not remove it from the destructure.
- The spec explicitly prohibits modifying `NavigationSidebar.tsx` (§9 Explicit Constraints). This plan confirms no changes are needed there.
- This change can be built in parallel with Items 1, 3, 5, and 6 — it has no dependencies.
