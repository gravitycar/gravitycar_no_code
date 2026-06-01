# Implementation Plan: CAT-15 — CacheManagementPanel Component

## Spec Context

`CacheManagementPanel` is the self-contained panel on the Admin page that exposes the cache
rebuild controls. It manages all checkbox state (which components to rebuild, whether to update
schema/permissions) and opens the `ConfirmRebuildModal` with the current selections. The panel
does not make any API calls itself — all network activity happens inside `ConfirmRebuildModal`.

Catalog item: CAT-15  
Specification section: Component 6 (React Admin Panel); AC-28, AC-29, AC-30, AC-33, AC-34  
Acceptance criteria addressed:
- AC-28: Displays "Cache Management" section with checkboxes for all four cache components, all checked by default.
- AC-29: When METADATA is checked, additional "Update Schema" and "Update Permissions" checkboxes appear (both checked by default); hidden when METADATA is unchecked.
- AC-30: Clicking "Rebuild Cache" opens the confirmation modal.
- AC-33: Layout is section-based and can accommodate future admin panels.
- AC-34: Tailwind CSS only — no external UI libraries.

---

## Dependencies

- **Blocked by**: CAT-14 (`ConfirmRebuildModal` — rendered inside this panel; must exist to import)
- **Blocks**: CAT-16 (`AdminPage` renders `CacheManagementPanel`)
- **Uses**:
  - `gravitycar-frontend/src/components/admin/ConfirmRebuildModal.tsx` (CAT-14)

---

## File Changes

### New Files
- `gravitycar-frontend/src/components/admin/CacheManagementPanel.tsx` — cache rebuild section panel

### Modified Files
- none (CAT-16 handles `AdminPage` and `App.tsx`)

---

## Implementation Details

### Module-Level Constants

Per CLAUDE.md: arrays whose contents are not changed programmatically must be defined as
constants at module level (not inside the component function).

```typescript
const CACHE_COMPONENTS: Array<{ id: string; label: string }> = [
  { id: 'metadata',   label: 'Metadata Cache' },
  { id: 'routes',     label: 'API Routes Cache' },
  { id: 'docs',       label: 'Documentation Cache' },
  { id: 'navigation', label: 'Navigation Cache' },
];

const ALL_COMPONENT_IDS: string[] = CACHE_COMPONENTS.map(c => c.id);
```

`CACHE_COMPONENTS` is defined at module level so it is not re-created on every render.
`ALL_COMPONENT_IDS` is derived from it once and used to initialize the `selectedComponents`
state and for the "select all" reset if needed in future.

### Props Interface

`CacheManagementPanel` takes no props — it is entirely self-contained. All state lives inside
the component. It communicates with `ConfirmRebuildModal` by passing `isModalOpen`, `onClose`,
and an `options` object as props to the modal.

```typescript
// No props interface needed — component is self-contained.
const CacheManagementPanel: React.FC = () => { ... };
```

### State

```typescript
const [selectedComponents, setSelectedComponents] = useState<string[]>(ALL_COMPONENT_IDS);
const [updateSchema, setUpdateSchema]             = useState<boolean>(true);
const [updatePermissions, setUpdatePermissions]   = useState<boolean>(true);
const [isModalOpen, setIsModalOpen]               = useState<boolean>(false);
```

- `selectedComponents`: starts with all four IDs checked.
- `updateSchema` and `updatePermissions`: start as `true` (shown only when `metadata` is selected).
- `isModalOpen`: starts `false`; set to `true` on "Rebuild Cache" click; set to `false` on modal `onClose`.

### Derived Values

```typescript
const isMetadataSelected: boolean = selectedComponents.includes('metadata');
const isRebuildDisabled:  boolean = selectedComponents.length === 0;

const options = {
  components:        selectedComponents,
  updateSchema:      isMetadataSelected && updateSchema,
  updatePermissions: isMetadataSelected && updatePermissions,
};
```

`options` is computed inline (not stored in state) — it is always derived from the checkbox state.
The `updateSchema` and `updatePermissions` flags in `options` are forced to `false` when
`metadata` is not selected, even if the checkboxes are still checked, because those operations
only apply when the metadata component is rebuilt (spec AC-29).

### Event Handlers

```typescript
function handleComponentToggle(componentId: string): void {
  setSelectedComponents(prev =>
    prev.includes(componentId)
      ? prev.filter(id => id !== componentId)
      : [...prev, componentId]
  );
}

function handleOpenModal(): void {
  setIsModalOpen(true);
}

function handleCloseModal(): void {
  setIsModalOpen(false);
}
```

`handleComponentToggle` toggles a single component ID in/out of `selectedComponents`.

### UI Structure

```
<section>                           ← panel wrapper (card-style)
  <h2>Cache Management</h2>
  <p>Description text</p>

  <fieldset>                        ← component checkboxes
    <legend>Select components to rebuild:</legend>
    [x] Metadata Cache
    [x] API Routes Cache
    [x] Documentation Cache
    [x] Navigation Cache
  </fieldset>

  {isMetadataSelected && (
    <fieldset>                      ← conditional secondary options
      <legend>Additional options:</legend>
      [x] Update Database Schema
      [x] Update Permissions
    </fieldset>
  )}

  <button disabled={isRebuildDisabled}>Rebuild Cache</button>

  <ConfirmRebuildModal
    isOpen={isModalOpen}
    onClose={handleCloseModal}
    options={options}
  />
</section>
```

### Tailwind Class Conventions

Follow the conventions observed in `ProjectsListView.tsx` and `Modal.tsx`:
- Panel card: `bg-white rounded-lg shadow p-6`
- Section heading: `text-xl font-semibold text-gray-900 mb-2`
- Description: `text-sm text-gray-600 mb-4`
- Fieldset: `border-0 p-0 m-0` (reset browser fieldset styles)
- Legend: `text-sm font-medium text-gray-700 mb-2`
- Checkbox row: `flex items-center gap-2 mb-2`
- Checkbox input: `h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500`
- Label: `text-sm text-gray-700`
- Conditional section wrapper: `mt-4 pl-4 border-l-2 border-gray-200` (visually indented under metadata)
- Primary button: `mt-6 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed`

### Full Component Code Example

```typescript
import React, { useState } from 'react';
import ConfirmRebuildModal from './ConfirmRebuildModal';

const CACHE_COMPONENTS: Array<{ id: string; label: string }> = [
  { id: 'metadata',   label: 'Metadata Cache' },
  { id: 'routes',     label: 'API Routes Cache' },
  { id: 'docs',       label: 'Documentation Cache' },
  { id: 'navigation', label: 'Navigation Cache' },
];

const ALL_COMPONENT_IDS: string[] = CACHE_COMPONENTS.map(c => c.id);

const CacheManagementPanel: React.FC = () => {
  const [selectedComponents, setSelectedComponents] = useState<string[]>(ALL_COMPONENT_IDS);
  const [updateSchema, setUpdateSchema]             = useState<boolean>(true);
  const [updatePermissions, setUpdatePermissions]   = useState<boolean>(true);
  const [isModalOpen, setIsModalOpen]               = useState<boolean>(false);

  const isMetadataSelected = selectedComponents.includes('metadata');
  const isRebuildDisabled  = selectedComponents.length === 0;

  const options = {
    components:        selectedComponents,
    updateSchema:      isMetadataSelected && updateSchema,
    updatePermissions: isMetadataSelected && updatePermissions,
  };

  function handleComponentToggle(componentId: string): void {
    setSelectedComponents(prev =>
      prev.includes(componentId)
        ? prev.filter(id => id !== componentId)
        : [...prev, componentId]
    );
  }

  return (
    <section className="bg-white rounded-lg shadow p-6">
      <h2 className="text-xl font-semibold text-gray-900 mb-2">Cache Management</h2>
      <p className="text-sm text-gray-600 mb-4">
        Rebuild one or more cache components. A backup archive is created before any files are cleared.
      </p>

      <fieldset className="border-0 p-0 m-0">
        <legend className="text-sm font-medium text-gray-700 mb-2">
          Select components to rebuild:
        </legend>
        {CACHE_COMPONENTS.map(({ id, label }) => (
          <div key={id} className="flex items-center gap-2 mb-2">
            <input
              id={`component-${id}`}
              type="checkbox"
              checked={selectedComponents.includes(id)}
              onChange={() => handleComponentToggle(id)}
              className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <label htmlFor={`component-${id}`} className="text-sm text-gray-700">
              {label}
            </label>
          </div>
        ))}
      </fieldset>

      {isMetadataSelected && (
        <div className="mt-4 pl-4 border-l-2 border-gray-200">
          <fieldset className="border-0 p-0 m-0">
            <legend className="text-sm font-medium text-gray-700 mb-2">
              Additional options:
            </legend>
            <div className="flex items-center gap-2 mb-2">
              <input
                id="update-schema"
                type="checkbox"
                checked={updateSchema}
                onChange={e => setUpdateSchema(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <label htmlFor="update-schema" className="text-sm text-gray-700">
                Update Database Schema
              </label>
            </div>
            <div className="flex items-center gap-2 mb-2">
              <input
                id="update-permissions"
                type="checkbox"
                checked={updatePermissions}
                onChange={e => setUpdatePermissions(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <label htmlFor="update-permissions" className="text-sm text-gray-700">
                Update Permissions
              </label>
            </div>
          </fieldset>
        </div>
      )}

      <button
        onClick={() => setIsModalOpen(true)}
        disabled={isRebuildDisabled}
        className="mt-6 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        Rebuild Cache
      </button>

      <ConfirmRebuildModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        options={options}
      />
    </section>
  );
};

export default CacheManagementPanel;
```

**File length estimate**: ~90 lines. Well within the 300-line limit.

---

## Error Handling

`CacheManagementPanel` itself has no error conditions. All error handling lives in
`ConfirmRebuildModal`. The panel's "Rebuild Cache" button is disabled when no components are
selected, preventing a call to `ConfirmRebuildModal` with an empty `components` array.

---

## Unit Test Specifications

**Test file**: `gravitycar-frontend/src/components/admin/CacheManagementPanel.test.tsx`

Use Vitest + React Testing Library. Mock `ConfirmRebuildModal` to avoid testing it here.

```typescript
import { vi, describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import CacheManagementPanel from './CacheManagementPanel';

vi.mock('./ConfirmRebuildModal', () => ({
  default: ({ isOpen, options }: any) =>
    isOpen ? (
      <div data-testid="modal">
        {options.components.join(',')}
        {options.updateSchema ? 'schema' : ''}
        {options.updatePermissions ? 'permissions' : ''}
      </div>
    ) : null,
}));
```

### Default state rendering

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| All four checkboxes rendered | Default render | 4 checkbox inputs visible | AC-28 |
| All checkboxes checked by default | Default render | All 4 checkboxes have `checked` attribute | AC-28 |
| Schema checkbox visible by default | Default render (metadata checked) | "Update Database Schema" checkbox present | AC-29 |
| Permissions checkbox visible by default | Default render | "Update Permissions" checkbox present | AC-29 |
| Schema checkbox checked by default | Default render | Schema checkbox checked | AC-29 |
| Permissions checkbox checked by default | Default render | Permissions checkbox checked | AC-29 |
| Modal not shown by default | Default render | No modal in DOM | Initial state |

### Component checkbox toggling

| Case | Action | Expected | Why |
|------|--------|----------|-----|
| Uncheck Metadata | Click Metadata checkbox | Metadata unchecked | Toggle |
| Uncheck then re-check | Click Metadata twice | Metadata checked again | Toggle idempotency |
| Uncheck Metadata hides schema checkbox | Click Metadata checkbox | "Update Database Schema" not in DOM | AC-29 |
| Uncheck Metadata hides permissions checkbox | Click Metadata checkbox | "Update Permissions" not in DOM | AC-29 |
| Uncheck all four | Uncheck all | "Rebuild Cache" button disabled | Button guard |
| Uncheck one | Uncheck Routes | Other three still checked | Independent toggles |

### Metadata conditional options

| Case | Action | Expected | Why |
|------|--------|----------|-----|
| Schema shown when metadata checked | (default) | "Update Database Schema" visible | AC-29 |
| Schema hidden when metadata unchecked | Uncheck metadata | "Update Database Schema" not in DOM | AC-29 |
| Schema re-appears when metadata re-checked | Uncheck then re-check metadata | Schema visible again | AC-29 |
| updateSchema toggles | Click schema checkbox | Schema unchecked | State change |
| updatePermissions toggles | Click permissions checkbox | Permissions unchecked | State change |

### Rebuild Cache button

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Button enabled by default | Default render | Button not disabled | Components selected |
| Button disabled with no selections | Uncheck all four | Button has `disabled` attribute | AC-28 / UX guard |
| Button click opens modal | Click "Rebuild Cache" | Modal test-id appears | AC-30 |

### Modal receives correct options

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| All components passed | Default, click Rebuild | Modal receives all 4 component IDs | Options propagation |
| Unchecked component excluded | Uncheck Routes, click Rebuild | Modal options exclude 'routes' | Selective rebuild |
| updateSchema true by default | Default, click Rebuild | Modal options has schema text | AC-29 |
| updateSchema false when unchecked | Uncheck schema, click Rebuild | Modal options has no schema text | AC-29 |
| updateSchema false when metadata unchecked | Uncheck metadata, check schema, click Rebuild | Modal options has no schema text | Derived value |

### Modal open/close lifecycle

| Case | Action | Expected | Why |
|------|--------|----------|-----|
| Modal opens on button click | Click "Rebuild Cache" | Modal visible | AC-30 |
| Modal closes via onClose | Modal's onClose fires | Modal not in DOM | Dismiss path |

### Key Scenario: Metadata unchecked removes secondary options from options object

**Setup**: Default render. Uncheck the Metadata checkbox. Click "Rebuild Cache".

**Expected**:
- Modal is open.
- `options.components` does NOT include `'metadata'`.
- `options.updateSchema` is `false` (even if the schema checkbox was previously checked).
- `options.updatePermissions` is `false`.

**Why**: Validates the derived `options` computation: `updateSchema: isMetadataSelected && updateSchema`.
The backend's `CacheRebuilder` only runs schema/permissions when METADATA is included — the
frontend must enforce this to avoid sending a misleading payload.

### Key Scenario: All unchecked disables button

**Setup**: Default render. Uncheck all four component checkboxes.

**Expected**:
- "Rebuild Cache" button has the `disabled` attribute.
- Clicking it does NOT open the modal.

**Why**: Prevents sending an empty `components` array to the backend (which would return HTTP 400).

---

## Notes

- `CACHE_COMPONENTS` is defined at module level (not inside the component) per CLAUDE.md, so
  it is created once and never re-created on render.
- `ALL_COMPONENT_IDS` is derived from `CACHE_COMPONENTS` once at module level, used to initialize
  the `selectedComponents` state default value.
- The `options` object is computed inline as a derived value — it is NOT stored in a separate
  `useState` call. This ensures it is always in sync with the checkbox state without any
  `useEffect` synchronization.
- `updateSchema` and `updatePermissions` are forced to `false` in the `options` object when
  metadata is not selected, even if the checkboxes themselves remain checked. This prevents the
  backend from being asked to run schema migration without a fresh metadata load.
- The component renders `<ConfirmRebuildModal>` unconditionally (always in the JSX tree); the
  modal itself short-circuits to `null` when `isOpen` is `false`. This is the pattern established
  by `Modal.tsx` (`if (!isOpen) return null`).
- Tailwind class conventions follow `ProjectsListView.tsx` (gray background panels, blue primary
  actions) and `Modal.tsx` (rounded-lg, shadow, border-gray patterns).
- No `useEffect` is needed — all state transitions are driven by user interaction. The component
  has no async operations.
- `<fieldset>` and `<legend>` are used for the checkbox groups to meet accessibility best
  practices. Browser default fieldset styles (border, padding) are reset with `border-0 p-0 m-0`.
- File length target: ~90 lines. No risk of approaching the 300-line limit.
