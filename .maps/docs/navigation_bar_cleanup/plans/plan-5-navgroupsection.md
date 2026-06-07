# Implementation Plan: NavGroupSection Component + NavigationSidebar Update

## Spec Context

This plan implements spec §§7.1 and 7.2 — the new `NavGroupSection` collapsible group
component and the corresponding updates to `NavigationSidebar.tsx` to render the
discriminated union `NavModelEntry[]`. Together these two files complete the frontend
rendering layer of the navigation bar cleanup.

Catalog item: **Item 5 — Build `NavGroupSection` component and update `NavigationSidebar`**
Specification sections: §7.1 (new component), §7.2 (sidebar update), §11 (integration points)
Acceptance criteria addressed: **AC-6, AC-7, AC-8, AC-9, AC-10, AC-11**

---

## Dependencies

- **Blocked by**: Item 3 (plan-3-frontend-types) — requires `NavModelItem`, `NavModelGroup`,
  `NavModelEntry` types from `navigation.ts`
- **Blocked by**: Item 4 (plan-4-useModelActions) — `NavGroupSection` and updated
  `NavigationSidebar` both import `useModelActions` from `hooks/useModelActions.ts`
- **Uses**:
  - `react` — `useState`, `useEffect`, `useRef`, `React.FC`
  - `react-router-dom` — `useLocation` (already imported in `NavigationSidebar`)
  - `gravitycar-frontend/src/types/navigation.ts` — `NavModelItem`, `NavModelGroup`,
    `NavModelEntry`, `NavigationItem`
  - `gravitycar-frontend/src/hooks/useModelActions.ts` — `useModelActions`
- **Blocks**: nothing (leaf node in dependency graph)

---

## File Changes

### New Files

- `gravitycar-frontend/src/components/navigation/NavGroupSection.tsx` — standalone
  collapsible group component (Disclosure ARIA pattern, Tailwind animation, Escape handler)

### Modified Files

- `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx` — import new types
  and `NavGroupSection`; call `useModelActions()` hook; replace flat model map with
  discriminated union map; update debug panel

---

## Implementation Details

### Part A — `NavGroupSection.tsx` (new file)

**File**: `gravitycar-frontend/src/components/navigation/NavGroupSection.tsx`

**Exports**:
- `NavGroupSection` (default export) — `React.FC<NavGroupSectionProps>`

**`contentId` formula** (normative, from spec §7.1 and AC-10):
```
contentId = 'nav-group-'
  + group.label.toLowerCase().replace(/[^a-z0-9]+/g, '-')
  + '-'
  + group.label.toLowerCase().replace(/[^a-z0-9]+/g, '-')
```
Both segments use `group.label` — in this implementation `groupName` and `label` are the
same string (the group's display label). This compound form ensures unique IDs even if the
formula is generalised later. Example for "Event Organizer":
`nav-group-event-organizer-event-organizer`

**Props interface**:
```typescript
interface NavGroupSectionProps {
  group: NavModelGroup;
  location: ReturnType<typeof useLocation>;
  defaultOpen?: boolean;
}
```

The `location` object is passed from the parent (`NavigationSidebar`) rather than calling
`useLocation()` inside the component, because the parent already holds it. This avoids a
redundant hook call and keeps the component easy to test.

`defaultOpen` is computed by the parent and passed in to set the initial open/closed state at
mount time. The `useEffect` then handles subsequent navigation changes after mount (one-way
auto-open only).

**State**:
- `isOpen: boolean` — initialised from `defaultOpen ?? false`; `defaultOpen` is computed by
  the parent as `group.items.some(item => item.url === location.pathname)` at render time
- `toggleButtonRef: React.RefObject<HTMLButtonElement>` — used to return focus on Escape

**Hook**:
```typescript
const { expandedModel, setExpandedModel, getVisibleActions, handleActionClick } =
  useModelActions();
```
Each `NavGroupSection` instance calls `useModelActions()` independently, giving it isolated
action-expansion state (spec §7.0, §11).

**`useEffect` for auto-open** (one-way only — AC-8):
```typescript
useEffect(() => {
  const isActive = group.items.some((item) => item.url === location.pathname);
  if (isActive) {
    setIsOpen(true);
  }
  // Intentionally no else branch — never force-close on route change
}, [location.pathname, group.items]);
```

**Escape key handler**:
```typescript
const handleKeyDown = (e: React.KeyboardEvent) => {
  if (e.key === 'Escape') {
    setIsOpen(false);
    setExpandedModel(null);
    toggleButtonRef.current?.focus();
  }
};
```
Attached to the wrapping `<div>` via `onKeyDown`. Fires regardless of which element inside
the group has focus (group button or action sub-menu button), satisfying AC-11.

**Sub-item cast helper** — `NavModelItem` from the group has `type: 'item'`; it is structurally
identical to `NavigationItem` but carries the extra `type` literal. `useModelActions` accepts
`NavigationItem`. Cast each sub-item before passing to hook methods:
```typescript
const item = subItem as NavigationItem;
```
This is safe because `NavModelItem` has a strict superset of `NavigationItem`'s fields.

**Full component code**:

```tsx
import React, { useState, useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';
import { NavModelGroup, NavModelItem, NavigationItem, NavigationAction } from '../../types/navigation';
import { useModelActions } from '../../hooks/useModelActions';

interface NavGroupSectionProps {
  group: NavModelGroup;
  location: ReturnType<typeof useLocation>;
  defaultOpen?: boolean;
}

const NavGroupSection: React.FC<NavGroupSectionProps> = ({ group, location, defaultOpen }) => {
  const [isOpen, setIsOpen] = useState<boolean>(defaultOpen ?? false);
  const toggleButtonRef = useRef<HTMLButtonElement>(null);

  const { expandedModel, setExpandedModel, getVisibleActions, handleActionClick } =
    useModelActions();

  const slug = group.label.toLowerCase().replace(/[^a-z0-9]+/g, '-');
  const contentId = `nav-group-${slug}-${slug}`;

  useEffect(() => {
    const isActive = group.items.some((item) => item.url === location.pathname);
    if (isActive) {
      setIsOpen(true);
    }
  }, [location.pathname, group.items]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      setIsOpen(false);
      setExpandedModel(null);
      toggleButtonRef.current?.focus();
    }
  };

  const renderSubItem = (subItem: NavModelItem) => {
    const item = subItem as unknown as NavigationItem;
    const visibleActions = getVisibleActions(item);
    const isActive = subItem.url === location.pathname;

    return (
      <li key={subItem.name}>
        <div>
          <div className="flex items-center justify-between px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors">
            <a
              href={subItem.url}
              aria-current={isActive ? 'page' : undefined}
              className="flex items-center flex-1"
            >
              <span className="mr-2">{subItem.icon}</span>
              {subItem.title}
            </a>
            {visibleActions.length > 0 && (
              <button
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  setExpandedModel(expandedModel === subItem.name ? null : subItem.name);
                }}
                className="ml-2 p-1 hover:bg-gray-200 rounded"
                aria-label={`Toggle actions for ${subItem.title}`}
              >
                <svg
                  className={`w-4 h-4 transition-transform ${
                    expandedModel === subItem.name ? 'rotate-180' : ''
                  }`}
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path
                    fillRule="evenodd"
                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                    clipRule="evenodd"
                  />
                </svg>
              </button>
            )}
          </div>

          {expandedModel === subItem.name && visibleActions.length > 0 && (
            <ul className="mt-1 ml-6 space-y-1">
              {visibleActions.map((action: NavigationAction) => (
                <li key={action.key}>
                  {action.action ? (
                    <button
                      onClick={() => handleActionClick(action, item)}
                      className="flex items-center px-3 py-1 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors w-full text-left"
                    >
                      <span className="mr-2">{action.icon}</span>
                      {action.title}
                    </button>
                  ) : (
                    <a
                      href={action.url}
                      className="flex items-center px-3 py-1 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                    >
                      <span className="mr-2">{action.icon}</span>
                      {action.title}
                    </a>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      </li>
    );
  };

  return (
    <li onKeyDown={handleKeyDown}>
      <button
        ref={toggleButtonRef}
        aria-expanded={isOpen}
        aria-controls={contentId}
        onClick={() => setIsOpen((prev) => !prev)}
        className="flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
      >
        <span>{group.label}</span>
        <svg
          className={`w-4 h-4 transition-transform duration-200 ${isOpen ? 'rotate-90' : ''}`}
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fillRule="evenodd"
            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
            clipRule="evenodd"
          />
        </svg>
      </button>

      <ul
        id={contentId}
        role="list"
        className={`overflow-hidden transition-all duration-300 ${
          isOpen ? 'max-h-64' : 'max-h-0'
        }`}
      >
        {group.items.map(renderSubItem)}
      </ul>
    </li>
  );
};

export default NavGroupSection;
```

**ARIA notes** (per spec §7.1 and AC-10):
- Group `<button>` has `aria-expanded={isOpen}` (boolean, not string) and
  `aria-controls={contentId}` — Disclosure pattern per W3C WAI-ARIA APG
- No `aria-haspopup` on group button (would imply a popup widget, not a disclosure)
- Sub-item links are `<a>` tags that navigate; group button is a `<button>` that does not
- `aria-current="page"` applied to the active sub-item link (AC-9)
- The `<ul>` carries `role="list"` to restore list semantics removed by `list-style: none`
  in some browsers when `list-style` is CSS-reset

**Chevron direction**: the group toggle button uses a right-pointing path
(`d="M7.293 14.707..."`) that rotates 90° clockwise (`rotate-90`) when open — per spec §7.1.
Sub-item action chevrons continue to use the downward-pointing path
(`d="M5.293 7.293..."`) and `rotate-180` when expanded — matching the existing sidebar style.

---

### Part B — `NavigationSidebar.tsx` changes

**File**: `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx`

The current file is 304 lines. After changes it will be approximately 290 lines (net removal
of the four extracted state/handler blocks, net addition of the discriminated union render
and new imports).

#### Change 1 — Import block update (lines 1–6)

**Current** (lines 1–6):
```typescript
import React, { useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { navigationService } from '../../services/navigationService';
import { NavigationData, NavigationAction, NavigationItem } from '../../types/navigation';
import { groupCustomPages } from '../../utils/navigationUtils';
import { useAuth } from '../../hooks/useAuth';
```

**Replace with**:
```typescript
import React, { useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { navigationService } from '../../services/navigationService';
import { NavigationData, NavModelEntry, NavModelItem, NavModelGroup, NavigationItem } from '../../types/navigation';
import { groupCustomPages } from '../../utils/navigationUtils';
import { useAuth } from '../../hooks/useAuth';
import { useModelActions } from '../../hooks/useModelActions';
import NavGroupSection from './NavGroupSection';
```

`NavigationItem` is retained because `useModelActions` hook methods (`getVisibleActions`,
`handleActionClick`) still accept `NavigationItem`. `NavModelEntry` is needed for the
discriminated union map. `NavModelItem` and `NavModelGroup` are imported for type narrowing
(not strictly required by TypeScript since the `type` guard narrows to them automatically,
but explicit import makes intent clear).

#### Change 2 — Remove `expandedModel` state declaration and extracted handlers (lines 17, 42–84)

Per plan-4-useModelActions, the following lines are REMOVED from the component:

| Lines | Content |
|-------|---------|
| 17 | `const [expandedModel, setExpandedModel] = useState<string | null>(null);` |
| 42–44 | `const handleModelClick = (modelKey: string) => { ... };` |
| 56–62 | `const getVisibleActions = ...` |
| 64–84 | `const handleActionClick = ...` |

`useState` import stays (still used for `expandedCustomPage`, `isLoading`, `error`).
`useNavigate` import stays (still used by `handleEventsSmartClick` at line 51).

#### Change 3 — Add `useModelActions()` hook call (after remaining `useState` declarations)

After the remaining `useState` declarations (line 20 in the current file; will be line 19
after removing line 17), insert:

```typescript
const { expandedModel, setExpandedModel, getVisibleActions, handleActionClick } =
  useModelActions();
```

This provides the same four names that the removed inline code provided, so all downstream
JSX that already uses these names continues to work without further changes.

#### Change 4 — Replace the flat model map with discriminated union render (lines 206–273)

**Current** (lines 200–274):
```tsx
{navigationData.models.length > 0 && (
  <div>
    <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
      Data Management
    </h3>
    <ul className="space-y-1">
      {navigationData.models.map((model) => {
        const visibleActions = getVisibleActions(model);
        return (
          <li key={model.name}>
            ...
          </li>
        );
      })}
    </ul>
  </div>
)}
```

**Replace the entire block** (from the outer `{navigationData.models.length > 0 &&` to its
closing `)}`) with:

```tsx
{navigationData.models.length > 0 && (
  <div>
    <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
      Data Management
    </h3>
    <ul className="space-y-1">
      {navigationData.models.map((entry: NavModelEntry) => {
        if (entry.type === 'group') {
          return (
            <NavGroupSection
              key={entry.label}
              group={entry}
              location={location}
              defaultOpen={entry.items.some(item => item.url === location.pathname)}
            />
          );
        }

        // entry.type === 'item' — render identical to current top-level model item
        const model = entry as NavigationItem;
        const visibleActions = getVisibleActions(model);
        return (
          <li key={model.name}>
            <div>
              <div className="flex items-center justify-between px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors">
                <a
                  href={model.url}
                  aria-current={location.pathname === model.url ? 'page' : undefined}
                  className="flex items-center flex-1"
                >
                  <span className="mr-2">{model.icon}</span>
                  {model.title}
                </a>
                {visibleActions.length > 0 && (
                  <button
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      setExpandedModel(expandedModel === model.name ? null : model.name);
                    }}
                    className="ml-2 p-1 hover:bg-gray-200 rounded"
                  >
                    <svg
                      className={`w-4 h-4 transition-transform ${
                        expandedModel === model.name ? 'rotate-180' : ''
                      }`}
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                    </svg>
                  </button>
                )}
              </div>

              {expandedModel === model.name && visibleActions.length > 0 && (
                <ul className="mt-1 ml-6 space-y-1">
                  {visibleActions.map((action) => (
                    <li key={action.key}>
                      {action.action ? (
                        <button
                          onClick={() => handleActionClick(action, model)}
                          className="flex items-center px-3 py-1 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors w-full text-left"
                        >
                          <span className="mr-2">{action.icon}</span>
                          {action.title}
                        </button>
                      ) : (
                        <a
                          href={action.url}
                          className="flex items-center px-3 py-1 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                        >
                          <span className="mr-2">{action.icon}</span>
                          {action.title}
                        </a>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </li>
        );
      })}
    </ul>
  </div>
)}
```

**Key decisions in this block**:
- `NavGroupSection` receives both the `location` object and a computed `defaultOpen` prop from
  the parent. `defaultOpen` is evaluated inline as `entry.items.some(item => item.url ===
  location.pathname)` — this sets the correct initial open state at mount time. The
  `useEffect` inside `NavGroupSection` then handles subsequent route changes (one-way
  auto-open only). `location` is passed as a prop to avoid a redundant `useLocation()` call
  inside the child and to keep the component testable without a Router wrapper.
- `key={entry.label}` for groups — `label` is unique per group (spec guarantees one group
  entry per label in the pre-grouped cache).
- The cast `const model = entry as NavigationItem` — `NavModelItem` has all `NavigationItem`
  fields plus `type: 'item'`. TypeScript narrows `entry` to `NavModelItem` via the
  `type === 'group'` guard's else branch; the additional cast to `NavigationItem` satisfies
  `getVisibleActions` and `handleActionClick` which type their parameter as `NavigationItem`.
- `aria-current` added to ungrouped top-level items here too (spec §7.2, AC-9).
- `handleActionClick(action, model)` — passes full `NavigationItem` as per plan-4's refactor.
  This replaces the current `handleActionClick(action, model.name)` call.

#### Change 5 — Update debug panel (lines 283–295)

**Current** (lines 283–295):
```tsx
{navigationData.models.map((model) => (
  <div key={model.name} className="mb-1">
    <strong>{model.name}:</strong> 
    {model.permissions && Object.entries(model.permissions)
      .filter(([, hasPermission]) => hasPermission)
      .map(([permission]) => permission)
      .join(', ')}
  </div>
))}
```

**Replace with**:
```tsx
{navigationData.models.flatMap((entry: NavModelEntry) => {
  if (entry.type === 'group') {
    return entry.items.map((item) => (
      <div key={item.name} className="mb-1">
        <strong>{item.name}</strong> <em className="text-gray-300">({entry.label})</em>:{' '}
        {item.permissions && Object.entries(item.permissions)
          .filter(([, hasPermission]) => hasPermission)
          .map(([permission]) => permission)
          .join(', ')}
      </div>
    ));
  }
  return [
    <div key={entry.name} className="mb-1">
      <strong>{entry.name}:</strong>{' '}
      {entry.permissions && Object.entries(entry.permissions)
        .filter(([, hasPermission]) => hasPermission)
        .map(([permission]) => permission)
        .join(', ')}
    </div>,
  ];
})}
```

`flatMap` is used because group entries expand to multiple `<div>` elements. Each group item
also shows its group label in parentheses (e.g., `Events (Event Organizer): list, create`)
to aid debugging without requiring the developer to cross-reference the group header.

---

## Complete `NavigationSidebar.tsx` State After All Changes

The component function body will have this structure after the five changes above:

```
imports (8 lines — was 6)
interface NavigationSidebarProps
const NavigationSidebar = () => {
  const { user } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [navigationData, ...] = useState(null);
  // expandedModel useState REMOVED
  const [expandedCustomPage, ...] = useState(null);
  const [isLoading, ...] = useState(true);
  const [error, ...] = useState(null);

  const { expandedModel, setExpandedModel, getVisibleActions, handleActionClick } =
    useModelActions();  // NEW

  useEffect(() => { loadNavigation(); }, [user]);
  const loadNavigation = async () => { ... };
  // handleModelClick REMOVED
  const handleCustomPageToggle = ...;   // unchanged
  const handleEventsSmartClick = ...;   // unchanged
  // getVisibleActions REMOVED
  // handleActionClick REMOVED

  // loading / error / empty guards (unchanged)

  return (
    <nav>
      <div>
        {/* Custom Pages Section — unchanged */}
        {/* Models Section — updated discriminated union map */}
        {/* Debug Panel — updated flatMap */}
      </div>
    </nav>
  );
};
export default NavigationSidebar;
```

---

## Error Handling

- **Group with no items**: `group.items.some(...)` returns `false`; `group.items.map(...)` renders
  nothing. The `<ul>` is empty but structurally valid. No error thrown.
- **Unknown `entry.type`**: TypeScript discriminated union makes this impossible at compile
  time. At runtime (e.g., malformed cache), the `type === 'group'` guard fails and the else
  branch runs — it will attempt to render with `entry.name` / `entry.url` undefined, but no
  exception is thrown. This matches existing behavior.
- **`useModelActions` in `NavGroupSection`**: hook cannot fail (it only uses `useState` and
  `useLocation`/`useNavigate` which throw only if called outside a Router — guaranteed not to
  happen here since `NavGroupSection` is always a child of `NavigationSidebar` which is inside
  the Router).

---

## Unit Test Specifications

**Test file**: `gravitycar-frontend/src/components/navigation/NavGroupSection.test.tsx`

Use `@testing-library/react` with a `MemoryRouter` wrapper (for `useLocation` and
`useNavigate` in `useModelActions`). Mock `useModelActions` in tests that focus on
group-open/close behavior to avoid the Router dependency in those cases.

### `NavGroupSection` — initial state

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| No active route | `defaultOpen={false}` (parent computed: no item URL matches) | Group rendered collapsed (`isOpen = false`) | Default closed |
| Active route matches item | `defaultOpen={true}` (parent computed: item URL matches `location.pathname`) | Group rendered expanded (`isOpen = true`) | AC-8 auto-open at mount |
| Active route matches no item | `defaultOpen={false}`, `location.pathname === '/Books'`, no item has that URL | Group rendered collapsed | One-way only |

### `NavGroupSection` — toggle behavior

| Case | Action | Expected | Why |
|------|--------|----------|-----|
| Collapsed group | Click toggle button | `aria-expanded` changes to `true`, `<ul>` has `max-h-64` class | AC-7 |
| Expanded group | Click toggle button again | `aria-expanded` changes to `false`, `<ul>` has `max-h-0` class | AC-7 |

### `NavGroupSection` — `aria-controls` / `id` linkage (AC-10)

**Setup**: group with `label: 'Event Organizer'`
**Expected**:
- Toggle button has `aria-controls="nav-group-event-organizer-event-organizer"`
- `<ul>` has `id="nav-group-event-organizer-event-organizer"`

### `NavGroupSection` — `aria-current` on active link (AC-9)

**Setup**: `location.pathname === '/Events'`, group has item with `url: '/Events'`
**Expected**: The `<a>` for "Events" has `aria-current="page"`; no other `<a>` has it

### `NavGroupSection` — Escape key handler (AC-11)

**Setup**: Group is open; focus is on any element inside the group
**Action**: Fire `keyDown` event with `key: 'Escape'` on the wrapping `<li>`
**Expected**:
1. `isOpen` changes to `false`
2. `setExpandedModel` called with `null`
3. Focus returns to toggle button (`document.activeElement === toggleButtonRef.current`)

### `NavGroupSection` — auto-open on route change (AC-8)

**Setup**: Render with `location.pathname = '/Books'` (no match). Then re-render with
`location.pathname = '/Events'` where item `url === '/Events'` exists.
**Expected**: `isOpen` becomes `true` on re-render (effect fires, condition met).

### `NavGroupSection` — no force-close on route away (AC-8)

**Setup**: Render with active route (`isOpen = true`). Re-render with non-matching
`location.pathname`.
**Expected**: `isOpen` remains `true` (effect never calls `setIsOpen(false)`)

### Key Scenario: Escape returns focus

**Setup**:
```tsx
const { getByRole } = render(
  <MemoryRouter initialEntries={['/Books']}>
    <NavGroupSection group={group} location={...} defaultOpen={false} />
  </MemoryRouter>
);
const toggleButton = getByRole('button', { name: /event organizer/i });
// open the group
fireEvent.click(toggleButton);
// focus a sub-item link
const link = getByRole('link', { name: /events/i });
link.focus();
```
**Action**: `fireEvent.keyDown(link.closest('li')!, { key: 'Escape' })`
**Expected**: `document.activeElement === toggleButton`

---

### `NavigationSidebar` — updated rendering tests

**Test file**: `gravitycar-frontend/src/components/navigation/NavigationSidebar.test.tsx`

(Add new test cases to the existing test file if one exists; create if not.)

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Renders `NavGroupSection` for group entries | `models: [{ type: 'group', label: 'Event Organizer', items: [...] }]` | `<NavGroupSection>` rendered with correct `group` prop | AC-6 |
| Renders flat `<li>` for item entries | `models: [{ type: 'item', name: 'Books', ... }]` | Regular `<li>` with `<a href="/Books">Books</a>` | AC-3 |
| Mixed — group + item | One group and one flat item | Both rendered; group as `NavGroupSection`, item as `<li>` | AC-4 |
| Debug panel — group items shown with label | DEV mode, group with 2 items | Debug div shows item name and group label in parentheses | Regression |

---

## Notes

### Why `location` is passed as a prop to `NavGroupSection`

The parent `NavigationSidebar` already calls `useLocation()` at line 15 and uses the result
throughout its render. Passing `location` as a prop to `NavGroupSection`:
1. Avoids a second `useLocation()` hook call for the same value
2. Makes `NavGroupSection` testable without a Router wrapper (can pass a plain object)
3. Is consistent with how `handleEventsSmartClick` uses the parent's `navigate` ref

If future requirements call for `NavGroupSection` to be used outside `NavigationSidebar`,
the prop can be replaced with an internal `useLocation()` call at that time.

### Why the cast `entry as NavigationItem` in the ungrouped item branch

`NavModelItem` has all fields of `NavigationItem` plus the `type: 'item'` literal. After the
`type === 'group'` guard, TypeScript narrows `entry` to `NavModelItem`. The additional cast
to `NavigationItem` is needed only because `getVisibleActions` and `handleActionClick` are
typed to accept `NavigationItem` (from the hook defined in plan-4). An alternative is to
widen the hook's parameter type to accept `NavModelItem`, but that would require modifying
the hook — out of scope for this plan. The cast is safe and accurate.

### `aria-current` addition to ungrouped items

The current `NavigationSidebar.tsx` does not apply `aria-current="page"` to ungrouped items.
This plan adds it as part of Change 4 (AC-9). It is a small addition to the existing item
render block and does not require a separate plan item.

### Tailwind `max-h-0` / `max-h-64` animation

The `<ul>` in `NavGroupSection` uses `overflow-hidden transition-all duration-300` with
`max-h-0` (collapsed) and `max-h-64` (expanded, 256px). This is consistent with the spec
constraint in §3 ("DO NOT use `max-h-screen`; use `max-h-64`"). The 256px cap accommodates
up to ~8 sub-items at the current 32px-per-item row height before internal scrolling would
be needed — sufficient for the four "Event Organizer" items.

### `role="list"` on the `<ul>`

Some browsers (notably Safari) remove list semantics from `<ul>` elements that have
`list-style: none` applied (which Tailwind's CSS reset does). Adding `role="list"` restores
the semantic role for screen readers without changing visual rendering.
