# Implementation Catalog: Navigation Bar Cleanup

**Epic ID**: 158
**Spec**: `.maps/docs/navigation_bar_cleanup/specification/spec.md`
**Created**: 2026-06-06

---

## Overview

The work is decomposed into 6 discrete implementation items across three layers:

1. Metadata layer (PHP) — 2 items (can run in parallel)
2. Backend service layer (PHP) — 1 item (depends on Item 1)
3. Frontend types + api.ts (TypeScript) — 1 item (depends on Item 2)
4. Frontend hook (TypeScript/React) — 1 item (no backend dependency; can start after Item 3)
5. Frontend components (React) — 1 item (depends on Items 3 and 4)

---

## Catalog Items

---

### Item 1a: Add `navigation_bar` to hidden-model metadata files

- **Purpose**: Implement spec §4.2 for the two auth-only models that must be excluded from the navigation bar entirely. Sets `'navigation_bar' => false` in each file.
- **Scope** (2 files):
  - `src/Models/googleoauthtokens/googleoauthtokens_metadata.php`
  - `src/Models/jwtrefreshtokens/jwt_refresh_tokens_metadata.php`
- **Blocks**: Item 2 (NavigationBuilder reads this property)
- **Blocked by**: nothing — can start immediately
- **Acceptance Criteria**: AC-1, AC-12

---

### Item 1b: Add `navigation_bar` to Event Organizer model metadata files

- **Purpose**: Implement spec §4.2 for the four event-related models that must be grouped under the "Event Organizer" group. Sets `'navigation_bar' => 'Event Organizer'` in each file.
- **Scope** (4 files — just over the target, but all are trivial one-property additions to separate files; grouping them is more efficient than 4 separate items):
  - `src/Models/events/events_metadata.php`
  - `src/Models/eventcommitments/event_commitments_metadata.php`
  - `src/Models/eventreminders/event_reminders_metadata.php`
  - `src/Models/eventproposeddates/event_proposed_dates_metadata.php`
- **Blocks**: Item 2 (NavigationBuilder reads this property)
- **Blocked by**: nothing — can start immediately, in parallel with Item 1a
- **Acceptance Criteria**: AC-2, AC-5, AC-12

---

### Item 2: Refactor `NavigationBuilder::buildModelNavigation()`

- **Purpose**: Implement spec §5 — read `navigation_bar` from model metadata, skip hidden models, bucket models into groups vs ungrouped list, sort each bucket, and emit the new pre-grouped `type`-discriminated cache structure. Update the two logging statements (`models_count` → `top_level_nav_entries_count` and `items_count` → `total_model_items_count`). Extract the model-item construction into a private `buildModelItem()` helper method.
- **Scope** (1 file):
  - `src/Services/NavigationBuilder.php`
- **Blocks**: Item 3 (cache format changes propagate to frontend consumers)
- **Blocked by**: Items 1a and 1b (both metadata sets must exist before the builder can produce correct output)
- **Acceptance Criteria**: AC-1, AC-2, AC-3, AC-4, AC-5, AC-12

---

### Item 3: Update frontend TypeScript types and `api.ts` consumer

- **Purpose**: Implement spec §6.1 — add `NavModelItem`, `NavModelGroup`, and `NavModelEntry` discriminated union types to `navigation.ts`, change `NavigationData.models` from `NavigationItem[]` to `NavModelEntry[]`. Update the `getAvailableModels()` method in `api.ts` (line 693) to flatten grouped entries when extracting model names and titles (iterate `NavModelEntry[]`, handle `type === 'group'` by iterating `entry.items`, handle `type === 'item'` directly).
- **Scope** (2 files):
  - `gravitycar-frontend/src/types/navigation.ts`
  - `gravitycar-frontend/src/services/api.ts`
- **Blocks**: Items 4 and 5 (both depend on the correct TypeScript types being in place)
- **Blocked by**: Item 2 (the new cache format defines what the types must represent; the developer should confirm the shape by reading the updated NavigationBuilder before writing types)
- **Acceptance Criteria**: AC-13

---

### Item 4: Create `useModelActions` hook

- **Purpose**: Implement spec §7.0 — extract the `expandedModel` state, `getVisibleActions()`, `handleActionClick()`, and `setExpandedModel` into a new shared custom hook. This eliminates the need to duplicate action-expander logic between `NavigationSidebar` (ungrouped items) and `NavGroupSection` (grouped sub-items).
- **Scope** (1 file — new file):
  - `gravitycar-frontend/src/hooks/useModelActions.ts`
- **Blocks**: Item 5 (`NavGroupSection` and the updated `NavigationSidebar` both consume this hook)
- **Blocked by**: Item 3 (hook signature uses `NavigationItem` and `NavigationAction` from `navigation.ts`; types must be stable before the hook is written)
- **Acceptance Criteria**: AC-7, AC-11 (the hook provides the state primitives that make these work)

---

### Item 5: Build `NavGroupSection` component and update `NavigationSidebar`

- **Purpose**: Implement spec §§7.1 and 7.2 — create the new `NavGroupSection` collapsible group component (Disclosure pattern, `aria-expanded`, `aria-controls`, chevron rotation, `max-h-64` CSS animation, Escape key handler, `aria-current="page"`, auto-expand on active route via one-way `useEffect`). Update `NavigationSidebar` to: import new types and `NavGroupSection`; replace inline `expandedModel` state with `useModelActions()`; replace the flat `.map()` over `navigationData.models` with a discriminated union map that renders `NavGroupSection` for groups and the existing item template for ungrouped items; update the dev-mode debug panel to handle the discriminated union.
- **Scope** (2 files — 1 new, 1 modified):
  - `gravitycar-frontend/src/components/navigation/NavGroupSection.tsx` (new)
  - `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx` (modified)
- **Blocks**: nothing (leaf node in the dependency graph)
- **Blocked by**: Items 3 and 4 (requires stable types and the `useModelActions` hook)
- **Acceptance Criteria**: AC-6, AC-7, AC-8, AC-9, AC-10, AC-11

---

## Dependency Graph

```
Item 1a ─┐
          ├─→ Item 2 ─→ Item 3 ─→ Item 4 ─┐
Item 1b ─┘                                 ├─→ Item 5
                                Item 3 ────┘
```

Items 1a and 1b are fully parallel (touch different files, no shared dependencies).

Item 2 is blocked by both 1a and 1b — wait until both metadata batches are done.

Item 3 is blocked by Item 2 — the new cache structure must be confirmed before writing the discriminated union types.

Items 3 and 4 are parallel from Item 2's output:
- Item 4 can start as soon as Item 3 is done (hook depends on types)
- Item 5 is blocked by both 3 and 4

---

## Parallel Execution Opportunities

| Phase | Items that can run in parallel |
|---|---|
| Phase 1 | Item 1a and Item 1b |
| Phase 2 | Item 2 (after both Phase 1 items complete) |
| Phase 3 | Item 3 alone (gates Item 4) |
| Phase 4 | Item 4 (after Item 3) |
| Phase 5 | Item 5 (after Items 3 and 4) |

---

## File Touch Summary

| File | Item | New / Modified |
|---|---|---|
| `src/Models/googleoauthtokens/googleoauthtokens_metadata.php` | 1a | Modified |
| `src/Models/jwtrefreshtokens/jwt_refresh_tokens_metadata.php` | 1a | Modified |
| `src/Models/events/events_metadata.php` | 1b | Modified |
| `src/Models/eventcommitments/event_commitments_metadata.php` | 1b | Modified |
| `src/Models/eventreminders/event_reminders_metadata.php` | 1b | Modified |
| `src/Models/eventproposeddates/event_proposed_dates_metadata.php` | 1b | Modified |
| `src/Services/NavigationBuilder.php` | 2 | Modified |
| `gravitycar-frontend/src/types/navigation.ts` | 3 | Modified |
| `gravitycar-frontend/src/services/api.ts` | 3 | Modified |
| `gravitycar-frontend/src/hooks/useModelActions.ts` | 4 | New |
| `gravitycar-frontend/src/components/navigation/NavGroupSection.tsx` | 5 | New |
| `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx` | 5 | Modified |

**Total**: 12 files across 6 items (1 item slightly exceeds the 3-file target due to 4 trivially identical metadata edits being grouped for efficiency)

---

## Post-Build Step (not a code item)

After all items are merged and deployed, the navigation caches for all four roles must be rebuilt:

- Call `POST /navigation/cache/rebuild` (requires admin credentials)
- This triggers `buildAllRoleNavigationCaches()` for `admin`, `manager`, `user`, `guest`
- The frontend `navigationService.clearCache()` is called automatically

This is an operational step, not a code change. No migration script is required.
