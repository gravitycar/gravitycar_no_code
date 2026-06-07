# Implementation Plan: Frontend Types + api.ts Consumer Update

## Spec Context

This plan implements spec §6.1 — the TypeScript discriminated union types that mirror the new PHP cache structure, and the corresponding update to `api.ts` that consumes `NavigationData.models`. The PHP `NavigationBuilder` (Item 2) now emits a mixed array of `{ type: 'group', ... }` and `{ type: 'item', ... }` entries; both files updated here must understand that union.

Catalog item: **Item 3: Update frontend TypeScript types and `api.ts` consumer**
Specification section: §6 (Frontend Type Changes), §11 (Integration Points)
Acceptance criteria addressed: **AC-13** (zero TypeScript type errors from the `NavigationData.models` change)

---

## Dependencies

- **Blocked by**: Item 2 (NavigationBuilder) — the cache shape confirmed there determines these types
- **Blocks**: Item 4 (`useModelActions` hook uses `NavigationItem` / `NavigationAction` from `navigation.ts` — types must be stable) and Item 5 (`NavigationSidebar` and `NavGroupSection` both import from `navigation.ts`)
- **Uses**: existing `NavigationItem`, `NavigationAction` interfaces already in `navigation.ts`

---

## File Changes

### New Files

None.

### Modified Files

- `gravitycar-frontend/src/types/navigation.ts` — Add three new exported types; update one field in an existing interface
- `gravitycar-frontend/src/services/api.ts` — Update import line 13; update `.map()` body on line 693

---

## Implementation Details

### 1. `gravitycar-frontend/src/types/navigation.ts`

**Current file (51 lines total):**

```
Line  1: export interface NavigationItem { ... }        (lines 1–13)
Line 15: export interface NavigationAction { ... }      (lines 15–21)
Line 23: export interface CustomPage { ... }            (lines 23–29)
Line 31: export interface NavigationSection { ... }     (lines 31–34)
Line 36: export interface NavigationData { ... }        (lines 36–43)
Line 45: export interface NavigationResponse { ... }    (lines 45–51)
```

**Change 1 — Insert three new exported types after `NavigationAction` (after line 21, before line 23).**

Insert a blank line after the closing `}` of `NavigationAction` (line 21), then add:

```typescript
/**
 * A single navigable model link as emitted by NavigationBuilder.
 * Mirrors the PHP cache entry with type === 'item'.
 */
export interface NavModelItem {
  type: 'item';
  name: string;
  title: string;
  url: string;
  icon?: string;
  actions?: NavigationAction[];
  permissions?: {
    list: boolean;
    create: boolean;
    update: boolean;
    delete: boolean;
  };
}

/**
 * A collapsible group header containing model items.
 * Mirrors the PHP cache entry with type === 'group'.
 */
export interface NavModelGroup {
  type: 'group';
  label: string;
  items: NavModelItem[];
}

/**
 * Discriminated union of all top-level entries in NavigationData.models.
 * Use entry.type to narrow: 'item' → NavModelItem, 'group' → NavModelGroup.
 */
export type NavModelEntry = NavModelItem | NavModelGroup;
```

After inserting, the next line is the existing `export interface CustomPage { ... }` block.

**Change 2 — Update `NavigationData.models` field type (currently line 40, will shift after insert).**

Current line (~line 40 in the current file; will be around line 73 after the insert):
```typescript
  models: NavigationItem[];
```

Replace with:
```typescript
  models: NavModelEntry[];
```

**Exact edit target** (use surrounding context for uniqueness):
```typescript
export interface NavigationData {
  role: string;
  sections: NavigationSection[];
  custom_pages: CustomPage[];
  models: NavigationItem[];
  generated_at: string;
}
```

Replace the `models` line only:
```typescript
  models: NavModelEntry[];
```

**No other changes to `navigation.ts`.** The existing `NavigationItem` interface is retained as-is — it continues to be used by `getVisibleActions()` in `NavigationSidebar.tsx`, and by the `useModelActions` hook (Item 4).

---

### 2. `gravitycar-frontend/src/services/api.ts`

**Change 1 — Update import on line 13.**

Current line 13:
```typescript
import type { NavigationItem } from '../types/navigation';
```

Replace with:
```typescript
import type { NavModelEntry } from '../types/navigation';
```

`NavigationItem` is no longer referenced anywhere in `api.ts` after the map update below; `NavModelEntry` is needed to type the `.map()` parameter.

**Change 2 — Update the `.map()` body in `getAvailableModels()` (lines 692–696).**

Current code (lines 692–696):
```typescript
      if (navData.success && navData.data?.models) {
        return navData.data.models.map((m: NavigationItem) => ({
          name: m.name,
          title: m.title,
        }));
      }
```

Replace with:
```typescript
      if (navData.success && navData.data?.models) {
        return navData.data.models.flatMap((entry: NavModelEntry) => {
          if (entry.type === 'group') {
            return entry.items.map((item) => ({ name: item.name, title: item.title }));
          }
          return [{ name: entry.name, title: entry.title }];
        });
      }
```

**Why `flatMap`:** The original `.map()` returned one result per entry. With groups, a single entry can yield multiple model name/title pairs. `flatMap` flattens the nested arrays from group entries and the single-element arrays from item entries into the flat `Array<{ name: string; title: string }>` that callers expect. No change to the return type of `getAvailableModels()` is needed.

**Why the `type === 'group'` guard first:** TypeScript narrows `entry` to `NavModelGroup` inside the if-block, making `entry.items` valid. In the else path, `entry` is narrowed to `NavModelItem`, making `entry.name` and `entry.title` valid. No cast is needed.

---

## Complete File State After Changes

### `navigation.ts` (final, annotated by change)

```typescript
// ── UNCHANGED ──────────────────────────────────────────────────────────────
export interface NavigationItem {
  name: string;
  title: string;
  url: string;
  icon?: string;
  actions?: NavigationAction[];
  permissions?: {
    list: boolean;
    create: boolean;
    update: boolean;
    delete: boolean;
  };
}

export interface NavigationAction {
  key: string;
  title: string;
  url?: string;
  action?: string;
  icon?: string;
}

// ── NEW (inserted after NavigationAction) ──────────────────────────────────
export interface NavModelItem {
  type: 'item';
  name: string;
  title: string;
  url: string;
  icon?: string;
  actions?: NavigationAction[];
  permissions?: {
    list: boolean;
    create: boolean;
    update: boolean;
    delete: boolean;
  };
}

export interface NavModelGroup {
  type: 'group';
  label: string;
  items: NavModelItem[];
}

export type NavModelEntry = NavModelItem | NavModelGroup;

// ── UNCHANGED ──────────────────────────────────────────────────────────────
export interface CustomPage {
  key: string;
  title: string;
  url: string;
  icon?: string;
  roles: string[];
}

export interface NavigationSection {
  key: string;
  title: string;
}

export interface NavigationData {
  role: string;
  sections: NavigationSection[];
  custom_pages: CustomPage[];
  models: NavModelEntry[];   // ← CHANGED (was NavigationItem[])
  generated_at: string;
}

export interface NavigationResponse {
  success: boolean;
  status: number;
  data: NavigationData;
  cache_hit?: boolean;
  timestamp: string;
  count?: number;
}
```

---

## Error Handling

- No new error paths are introduced. The `getAvailableModels()` method already catches all errors and returns `[]`; the change only alters what happens inside the success branch.
- If the backend sends an entry with an unrecognized `type` value (not `'item'` or `'group'`), the `type === 'group'` check fails and the else branch runs. `entry.name` and `entry.title` will be `undefined` in that case — same behavior as before (the previous code also had no guard). This edge case is acceptable; it cannot occur with a correctly built PHP cache.

---

## Unit Test Specifications

### `getAvailableModels()` — updated behavior

| Case | navData shape | Expected return | Why |
|---|---|---|---|
| All flat items | `models: [{ type: 'item', name: 'Books', title: 'Books', url: '/Books' }]` | `[{ name: 'Books', title: 'Books' }]` | Backward compatibility with ungrouped-only caches |
| One group only | `models: [{ type: 'group', label: 'Event Organizer', items: [{ type: 'item', name: 'Events', title: 'Events', url: '/Events' }, { type: 'item', name: 'EventCommitments', title: 'Event Commitments', url: '/EventCommitments' }] }]` | `[{ name: 'Events', title: 'Events' }, { name: 'EventCommitments', title: 'EventCommitments' }]` (both items extracted from group) | Groups must be flattened |
| Mixed (group + items) | `models: [group with 2 items, { type: 'item', name: 'Books', ... }]` | Array containing all 3 model name/title pairs | flatMap flattens group items and includes top-level item |
| Empty models array | `models: []` | `[]` | Edge case |
| navData.success === false | `{ success: false }` | `[]` | Existing guard returns early |
| Network error | axios throws | `[]` | Existing catch returns `[]` |

### Key Scenario: Mixed Group + Item

**Setup**: Mock `this.api.get('/navigation')` to resolve with:
```json
{
  "success": true,
  "data": {
    "models": [
      {
        "type": "group",
        "label": "Event Organizer",
        "items": [
          { "type": "item", "name": "Events", "title": "Events", "url": "/Events" },
          { "type": "item", "name": "EventCommitments", "title": "Event Commitments", "url": "/EventCommitments" }
        ]
      },
      { "type": "item", "name": "Books", "title": "Books", "url": "/Books" }
    ]
  }
}
```

**Action**: Call `apiService.getAvailableModels()`

**Expected**: Returns `[{ name: 'Events', title: 'Events' }, { name: 'EventCommitments', title: 'Event Commitments' }, { name: 'Books', title: 'Books' }]` — 3 entries total, group items extracted and flattened.

---

## Notes

- **`NavigationItem` is NOT removed.** It is retained for downstream consumers: `useModelActions` hook (Item 4) and `NavigationSidebar.tsx` (Item 5) both use `NavigationItem` as the type for individual items passed around within component logic. `NavModelItem` is the wire-format type; `NavigationItem` is the component-internal type. They happen to have the same fields except `NavModelItem` adds the `type: 'item'` literal — this is intentional and matches the spec.
- **`flatMap` is safe** in all browsers and Node versions this project targets; no polyfill needed.
- **No changes to `NavigationResponse`** — the shape of the response wrapper is unchanged; only the nested `NavigationData.models` field type changes.
- **Import in `api.ts`**: After the change, `NavigationItem` is no longer imported in `api.ts`. The old import line (`import type { NavigationItem } from '../types/navigation'`) must be fully replaced with `import type { NavModelEntry } from '../types/navigation'` — not amended. If both are imported, the unused `NavigationItem` import will cause a lint warning.
- **Other files that import `NavigationItem`**: The spec notes that `NavigationSidebar.tsx` and `useModelActions.ts` use `NavigationItem`. Those files are handled in Items 4 and 5, not this plan. This plan does NOT touch those files.
