# Implementation Plan: useModelActions Hook

## Spec Context

This plan implements spec §7.0 — the `useModelActions` custom hook. The hook extracts the
`expandedModel` state, `getVisibleActions()`, `handleActionClick()`, and `setExpandedModel`
from `NavigationSidebar.tsx` into a standalone shared hook. Both `NavigationSidebar` (for
ungrouped top-level items) and `NavGroupSection` (for grouped sub-items) will call
`useModelActions()` independently; each instance maintains its own isolated state so that
expanding an action sub-menu in a group does not affect the expanded state in ungrouped items.

Catalog item: Item 4 — Create `useModelActions` hook
Specification section: §7.0, §11 (existing patterns)
Acceptance criteria addressed: AC-7, AC-11 (hook provides the state primitives)

---

## Dependencies

- **Blocked by**: Item 3 (plan-3-frontend-types) — hook signature uses `NavigationItem` and
  `NavigationAction` from `navigation.ts`; those types must be finalised before this file is
  written.
- **Blocks**: Item 5 (plan-5-NavGroupSection) — `NavGroupSection` and the updated
  `NavigationSidebar` both import this hook.
- **Uses**:
  - `react` — `useState` from the React standard library
  - `react-router-dom` — `useLocation`, `useNavigate` (already used in `NavigationSidebar`)
  - `gravitycar-frontend/src/types/navigation.ts` — `NavigationItem`, `NavigationAction`

---

## File Changes

### New Files

- `gravitycar-frontend/src/hooks/useModelActions.ts` — the hook implementation

### Modified Files

- `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx` — replace inline
  state and handlers with hook call; remove three function definitions and the
  `expandedModel` / `setExpandedModel` `useState` declaration

---

## Implementation Details

### `useModelActions` Hook

**File**: `gravitycar-frontend/src/hooks/useModelActions.ts`

**Exports**:
- `useModelActions(): UseModelActionsReturn` — the default named export

**Return type interface**:

```typescript
export interface UseModelActionsReturn {
  expandedModel: string | null;
  setExpandedModel: (name: string | null) => void;
  getVisibleActions: (item: NavigationItem) => NavigationAction[];
  handleActionClick: (action: NavigationAction, item: NavigationItem) => void;
}
```

Note: `handleActionClick` takes a full `NavigationItem` as its second argument (not just a
`string` modelName). This matches the spec's hook interface and gives the handler access to
`item.url` if needed in future. The existing `NavigationSidebar` call site passes
`model.name` as a string today — see the call-site update section below for the exact change.

**Full hook code**:

```typescript
import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { NavigationItem, NavigationAction } from '../types/navigation';

export interface UseModelActionsReturn {
  expandedModel: string | null;
  setExpandedModel: (name: string | null) => void;
  getVisibleActions: (item: NavigationItem) => NavigationAction[];
  handleActionClick: (action: NavigationAction, item: NavigationItem) => void;
}

export function useModelActions(): UseModelActionsReturn {
  const [expandedModel, setExpandedModel] = useState<string | null>(null);
  const location = useLocation();
  const navigate = useNavigate();

  const getVisibleActions = (item: NavigationItem): NavigationAction[] => {
    if (!item.actions) return [];
    return item.actions.filter((action) => {
      if (action.action === 'create') return item.permissions?.create !== false;
      return true;
    });
  };

  const handleActionClick = (action: NavigationAction, item: NavigationItem): void => {
    if (action.action === 'create') {
      const expectedPath = `/${item.name.toLowerCase()}`;
      if (location.pathname === expectedPath) {
        window.dispatchEvent(new CustomEvent('navigation-create', {
          detail: { modelName: item.name }
        }));
      } else {
        navigate(expectedPath + '?action=create');
      }
    } else if (action.url) {
      navigate(action.url);
    }
    setExpandedModel(null);
  };

  return { expandedModel, setExpandedModel, getVisibleActions, handleActionClick };
}
```

**Key differences from the current `NavigationSidebar` inline code**:

1. `handleActionClick` now takes `item: NavigationItem` instead of `modelName: string` —
   gives access to `item.name` (identical value) and `item.url` for future use.
2. `window.location.href` assignments replaced with `navigate()` calls — consistent with
   the React Router pattern already used elsewhere in the sidebar.
3. `setExpandedModel(null)` is called at the end of every `handleActionClick` branch —
   closes the action sub-menu after any action is taken (clean UX; matches spec intent).

---

### Lines Removed from `NavigationSidebar.tsx`

The following lines are extracted into the hook and must be **deleted** from
`NavigationSidebar.tsx` after the hook call is added.

| Lines | Content removed |
|-------|----------------|
| 17 | `const [expandedModel, setExpandedModel] = useState<string | null>(null);` |
| 42–44 | `const handleModelClick = (modelKey: string) => { setExpandedModel(expandedModel === modelKey ? null : modelKey); };` |
| 56–62 | `const getVisibleActions = (model: NavigationItem): NavigationAction[] => { ... };` |
| 64–84 | `const handleActionClick = (action: NavigationAction, modelName: string) => { ... };` |

After removal, `useState` may still be imported (it is used for `expandedCustomPage`,
`isLoading`, and `error`). Do not remove the `useState` import.

---

### How `NavigationSidebar` Calls the Hook After Refactor

**New import** (add to the existing import block near the top of the file):

```typescript
import { useModelActions } from '../../hooks/useModelActions';
```

**Hook call** — add immediately after the existing `useState` declarations (after line 20,
before the `useEffect` at line 22):

```typescript
const { expandedModel, setExpandedModel, getVisibleActions, handleActionClick } =
  useModelActions();
```

**`handleModelClick` replacement** — the current `handleModelClick` function (lines 42–44)
implemented a toggle. After the hook is introduced, replace every call to
`handleModelClick(model.name)` in the JSX with an inline toggle using the hook's
`setExpandedModel`:

```tsx
// Replace:
onClick={() => { e.preventDefault(); e.stopPropagation(); handleModelClick(model.name); }}

// With:
onClick={(e) => {
  e.preventDefault();
  e.stopPropagation();
  setExpandedModel(expandedModel === model.name ? null : model.name);
}}
```

This inline toggle is identical in behaviour to the removed `handleModelClick` helper.

**`handleActionClick` call-site change** — the current call at line 249 passes `model.name`
(a string). After the refactor the hook expects a full `NavigationItem`. Replace:

```tsx
// Replace (line 249):
onClick={() => handleActionClick(action, model.name)}

// With:
onClick={() => handleActionClick(action, model)}
```

No other JSX in `NavigationSidebar` references `expandedModel`, `getVisibleActions`,
`handleActionClick`, or `setExpandedModel` beyond what is already accounted for above.

---

### Exact Replacement Block in `NavigationSidebar.tsx`

For clarity, here is the complete before/after for the state and handler section at the top
of the component function (lines 13–84):

**Before** (lines 13–84, relevant portions):

```typescript
const [navigationData, setNavigationData] = useState<NavigationData | null>(null);
const [expandedModel, setExpandedModel] = useState<string | null>(null);          // LINE 17 — REMOVE
const [expandedCustomPage, setExpandedCustomPage] = useState<string | null>(null);
const [isLoading, setIsLoading] = useState(true);
const [error, setError] = useState<string | null>(null);
// ... useEffect, loadNavigation ...
const handleModelClick = (modelKey: string) => {                                   // LINES 42-44 — REMOVE
  setExpandedModel(expandedModel === modelKey ? null : modelKey);
};
// ... handleCustomPageToggle, handleEventsSmartClick ...
const getVisibleActions = (model: NavigationItem): NavigationAction[] => {         // LINES 56-62 — REMOVE
  if (!model.actions) return [];
  return model.actions.filter((action) => {
    if (action.action === 'create') return model.permissions?.create !== false;
    return true;
  });
};
const handleActionClick = (action: NavigationAction, modelName: string) => {       // LINES 64-84 — REMOVE
  // ... 20 lines ...
};
```

**After** (same region, showing only structural changes):

```typescript
const [navigationData, setNavigationData] = useState<NavigationData | null>(null);
// expandedModel useState REMOVED — provided by hook below
const [expandedCustomPage, setExpandedCustomPage] = useState<string | null>(null);
const [isLoading, setIsLoading] = useState(true);
const [error, setError] = useState<string | null>(null);

// NEW: hook provides expandedModel state and action handlers
const { expandedModel, setExpandedModel, getVisibleActions, handleActionClick } =
  useModelActions();

// ... useEffect, loadNavigation, handleCustomPageToggle, handleEventsSmartClick ...
// handleModelClick REMOVED — toggle inlined at call site in JSX
// getVisibleActions REMOVED — provided by hook
// handleActionClick REMOVED — provided by hook
```

---

### Imports Needed in the New Hook File

```typescript
import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { NavigationItem, NavigationAction } from '../types/navigation';
```

`react-router-dom` is already a project dependency (used in `NavigationSidebar.tsx` and
`App.tsx`). No new package installation is required.

---

## Error Handling

- `getVisibleActions`: returns `[]` early when `item.actions` is falsy — no errors possible.
- `handleActionClick`: guards `action.url` before calling `navigate(action.url)`. If neither
  `action.action === 'create'` nor `action.url` is present, the function does nothing and
  closes the sub-menu. This matches existing behaviour in the sidebar.

---

## Unit Test Specifications

**File**: `gravitycar-frontend/src/hooks/useModelActions.test.ts`

Use `@testing-library/react-hooks` (or `renderHook` from `@testing-library/react` v13+) to
test the hook in isolation. Mock `react-router-dom` with `jest.mock`.

### `expandedModel` state

| Case | Action | Expected | Why |
|------|--------|----------|-----|
| Initial state | render hook | `expandedModel === null` | Starts closed |
| Open a model | `setExpandedModel('Books')` | `expandedModel === 'Books'` | Tracks open model |
| Close explicitly | `setExpandedModel(null)` | `expandedModel === null` | Can be closed externally |

### `getVisibleActions(item)`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| No actions | `item.actions = undefined` | `[]` | Guard clause |
| Create allowed | `permissions.create = true`, action `'create'` | action included | Permitted |
| Create denied | `permissions.create = false`, action `'create'` | action excluded | Filtered out |
| Create undefined | `permissions.create = undefined`, action `'create'` | action included | `undefined !== false` |
| Non-create action | action with `action: 'list'` | always included | Only create is gated |
| URL-only action | action with no `action` field | always included | No gate applied |

### `handleActionClick(action, item)` — create action, on model page

**Setup**: mock `useLocation` to return `{ pathname: '/books' }`, item has `name: 'Books'`
**Action**: call `handleActionClick({ action: 'create', key: 'create', title: 'New' }, item)`
**Expected**:
- `window.dispatchEvent` called with `CustomEvent('navigation-create', { detail: { modelName: 'Books' } })`
- `navigate` NOT called
- `expandedModel` set to `null` after the call

### `handleActionClick(action, item)` — create action, on different page

**Setup**: mock `useLocation` to return `{ pathname: '/events' }`, item has `name: 'Books'`
**Action**: call `handleActionClick({ action: 'create', key: 'create', title: 'New' }, item)`
**Expected**:
- `navigate` called with `'/books?action=create'`
- `window.dispatchEvent` NOT called
- `expandedModel` set to `null` after the call

### `handleActionClick(action, item)` — URL action

**Setup**: action has `url: '/some/url'`, no `action` field
**Action**: call `handleActionClick({ key: 'view', title: 'View', url: '/some/url' }, item)`
**Expected**:
- `navigate` called with `'/some/url'`
- `expandedModel` set to `null` after the call

### `handleActionClick(action, item)` — no-op action

**Setup**: action has neither `action` field nor `url`
**Action**: call `handleActionClick({ key: 'noop', title: 'Nothing' }, item)`
**Expected**:
- `navigate` NOT called
- `window.dispatchEvent` NOT called
- `expandedModel` set to `null` (sub-menu still closed — safe default)

---

## Notes

### Why `item: NavigationItem` instead of `modelName: string` in `handleActionClick`

The spec defines the hook interface as:
```
handleActionClick: (action: NavigationAction, item: NavigationItem) => void
```
The extra fields on `NavigationItem` (e.g., `url`) are available for future use without
changing the hook's signature. The behaviour is identical to the current string-based version
since only `item.name` is used today.

### Isolated state per hook instance

Each component that calls `useModelActions()` gets its own independent `expandedModel` state.
`NavigationSidebar` and `NavGroupSection` do not share action-expansion state, which is
correct: opening a create sub-menu inside "Event Organizer" should not affect the expanded
state of any ungrouped item.

### `navigate()` vs `window.location.href`

The original sidebar code used `window.location.href` for cross-page navigation. The hook
uses React Router's `navigate()` instead, which is the established pattern in this codebase
(see `NavigationSidebar.tsx` line 54, `handleEventsSmartClick`). This avoids a full page
reload and keeps navigation within the SPA.

### `setExpandedModel(null)` after every action

The hook closes the sub-menu (`setExpandedModel(null)`) at the end of every
`handleActionClick` branch. This is intentional: after the user takes an action, the
sub-menu should close. The original sidebar code did not close the sub-menu on action — this
is a deliberate UX improvement introduced by the hook.
