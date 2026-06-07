# Specification: Navigation Bar Cleanup

**Epic ID**: 158
**Status**: Draft
**Last Updated**: 2026-06-06

---

## 1. Problem Statement

The navigation sidebar is visually cluttered. It displays every model as a flat list, including backend-only auth models that users should never interact with directly. Related models (Events, EventCommitments, EventReminders, EventProposedDates) appear as four separate top-level items rather than a logical group. The goal is to reduce visual noise and improve navigation structure by: (a) hiding internal models from the nav, and (b) grouping related models under collapsible parent headings.

---

## 2. Scope

This feature touches three layers:

1. **Model metadata files** — add `navigation_bar` property to models that need non-default behavior
2. **PHP `NavigationBuilder`** — read `navigation_bar` from metadata, produce a pre-grouped cache structure
3. **React frontend** — update TypeScript types and `NavigationSidebar.tsx` to render the new grouped structure

Out of scope for this feature:
- Creating new models
- Changing RBAC/permissions logic
- Any model other than the ones explicitly listed in this spec
- Multi-level (deeper than one level) navigation nesting
- User-configurable navigation grouping
- Drag-and-drop reordering

---

## 3. Explicit Constraints (DO NOT)

- Do NOT create a separate grouping config file outside of model metadata; grouping is declared in each model's own metadata file
- Do NOT use any external React component library (no Radix, Headless UI, Shadcn, etc.) — Tailwind CSS only
- Do NOT implement tree/treeitem ARIA roles; use the Disclosure pattern (`button` + `aria-expanded`) as specified by the W3C WAI-ARIA APG
- Do NOT use `max-h-screen` for animated collapse; use `max-h-64` (256px) as the fixed pixel cap
- Do NOT change the PHP cache serialization mechanism (`var_export`) — only the structure of what is serialized changes
- Do NOT change the `custom_pages` section of the cache or its frontend rendering
- Do NOT change the existing `groupCustomPages()` utility function in `navigationUtils.ts`
- Do NOT nest navigation groups more than one level deep

---

## 4. Metadata Schema Changes

### 4.1 New Property: `navigation_bar`

Each model metadata file (located at `src/Models/{model}/{model}_metadata.php`) MAY include a top-level `navigation_bar` key. The property is optional; its absence is treated identically to an empty string.

**Allowed values:**

| Value | Type | Meaning |
|---|---|---|
| `false` | `bool` | Exclude this model from navigation entirely |
| `'Some Group'` | `string` | Place this model under the named group in the sidebar |
| `''` (empty string) | `string` | Show as ungrouped top-level item (default when property is absent) |
| *(absent)* | — | Same as empty string; ungrouped top-level item |

**Design rationale:** A plain string is used rather than an array (`['Group Name']`) because only one level of grouping is planned. Using an array would introduce syntactic complexity without delivering value. If multi-level nesting is needed in a future epic, the schema can be extended at that time.

### 4.2 Models Requiring `navigation_bar` Changes

The following models SHALL have their metadata files updated:

| Model | Metadata File | New `navigation_bar` Value | Reason |
|---|---|---|---|
| `GoogleOauthTokens` | `src/Models/googleoauthtokens/googleoauthtokens_metadata.php` | `false` | Backend-only auth storage; no user-facing CRUD |
| `JwtRefreshTokens` | `src/Models/jwtrefreshtokens/jwt_refresh_tokens_metadata.php` | `false` | Backend-only auth token; no user-facing CRUD |
| `Events` | `src/Models/events/events_metadata.php` | `'Event Organizer'` | Group under shared parent |
| `EventCommitments` | `src/Models/eventcommitments/event_commitments_metadata.php` | `'Event Organizer'` | Group under shared parent |
| `EventReminders` | `src/Models/eventreminders/event_reminders_metadata.php` | `'Event Organizer'` | Group under shared parent |
| `EventProposedDates` | `src/Models/eventproposeddates/event_proposed_dates_metadata.php` | `'Event Organizer'` | Group under shared parent |

All other existing models SHALL retain default behavior (ungrouped, visible). No change to their metadata files is required unless explicitly listed above.

---

## 5. NavigationBuilder Changes

### 5.1 Overview

`src/Services/NavigationBuilder.php` — method `buildModelNavigation(array $modelNames, string $role): array`

The method currently builds a flat `$modelNavigation[]` array. It SHALL be changed to:
1. Read the `navigation_bar` property from each model's metadata
2. Skip models where `navigation_bar === false`
3. Bucket models into named groups or the ungrouped list
4. Return a pre-grouped, ordered array using the new cache structure defined in section 5.3

### 5.2 Algorithm (Pseudocode)

```
function buildModelNavigation(modelNames, role):
    roleModel = getRoleByName(role)
    if roleModel is null: return []

    groups = {}         # map of groupLabel -> [modelItem, ...]
    ungrouped = []      # [modelItem, ...]

    for each modelName in modelNames:
        if role does not have 'list' permission for modelName: skip

        metadata = metadataEngine.getModelMetadata(modelName)
        navigationBar = metadata['navigation_bar'] ?? ''

        if navigationBar === false: skip   # Hidden model

        modelItem = buildModelItem(modelName, roleModel)

        if navigationBar is non-empty string:
            groups[navigationBar][] = modelItem
        else:
            ungrouped[] = modelItem

    # Sort ungrouped items alphabetically by title
    sort ungrouped by title

    # Sort items within each group alphabetically by title
    for each group in groups:
        sort group.items by title

    # Build output array: groups first (sorted by label), then ungrouped items
    result = []
    for each groupLabel in sorted(groups.keys()):
        result[] = {
            'type'  => 'group',
            'label' => groupLabel,
            'items' => groups[groupLabel]
        }
    for each item in ungrouped:
        result[] = item   # item already contains 'type' => 'item' from buildModelItem()

    return result
```

**Notes:**
- The call to `metadataEngine->getModelMetadata($modelName)` is already available via `$this->metadataEngine` — the `NavigationBuilder` constructor already receives a `MetadataEngineInterface` instance
- The `navigation_bar` key may be absent from older metadata files; use `?? ''` (null-coalescing to empty string) for backward compatibility
- A `try/catch` wraps the metadata fetch per model; failures are logged and the model is skipped, matching existing error-handling behavior
- The `buildModelItem()` helper (current inline code inside the loop) SHALL be extracted to a private method to keep `buildModelNavigation()` under 30 lines
- `buildModelItem()` SHALL always include `'type' => 'item'` in its returned array; callers do NOT add the `type` field separately. The method is self-describing — its return value is always a valid flat item entry ready to be placed into the result array or into a group's `items` array

### 5.3 New Cache Output Format

The `models` key in the cache array SHALL change from a flat array of items to an ordered array of entries, where each entry has a `type` discriminator field.

**Flat item entry** (`type: 'item'`):
```
[
  'type'        => 'item',
  'name'        => 'Books',
  'title'       => 'Books',
  'url'         => '/Books',
  'icon'        => '📚',
  'actions'     => [ ... ],
  'permissions' => [ 'list' => true, 'create' => true, ... ]
]
```

**Group entry** (`type: 'group'`):
```
[
  'type'  => 'group',
  'label' => 'Event Organizer',
  'items' => [
    [ 'type' => 'item', 'name' => 'EventCommitments', 'title' => 'Event Commitments', ... ],
    [ 'type' => 'item', 'name' => 'EventProposedDates', 'title' => 'Event Proposed Dates', ... ],
    [ 'type' => 'item', 'name' => 'EventReminders', 'title' => 'Event Reminders', ... ],
    [ 'type' => 'item', 'name' => 'Events', 'title' => 'Events', ... ],
  ]
]
```

The top-level `models` array SHALL contain groups first (sorted alphabetically by `label`), followed by ungrouped items (sorted alphabetically by `title`).

### 5.4 Log Count Adjustments

**`buildNavigationForRole()`**: currently logs `models_count` as `count($navigation['models'])`. After the change, this count reflects the number of top-level entries (groups + items), not the total number of model links. The log message SHALL be updated to `top_level_nav_entries_count` to accurately describe the value being logged.

**`buildAllRoleNavigationCaches()`**: contains a second count at the point where it logs aggregate results (the `items_count` value computed as `count($navigation['models']) + ...`). After the change this count similarly reflects top-level entries, not total model links. The `items_count` SHALL be updated to count total model items across all groups plus ungrouped items: for each top-level entry, if `entry['type'] === 'group'` add `count(entry['items'])`; if `entry['type'] === 'item'` add 1. The log key SHALL be renamed to `total_model_items_count` to distinguish it from the top-level entry count in `buildNavigationForRole()`.

---

## 6. Frontend Type Changes

### 6.1 Updated Types in `navigation.ts`

File: `gravitycar-frontend/src/types/navigation.ts`

The following types SHALL be added:

**`NavModelItem`** — a single navigable model link (replaces direct use of `NavigationItem` in the models list):
```
{
  type: 'item'
  name: string
  title: string
  url: string
  icon?: string
  actions?: NavigationAction[]
  permissions?: { list: boolean; create: boolean; update: boolean; delete: boolean }
}
```

**`NavModelGroup`** — a collapsible group header containing model items:
```
{
  type: 'group'
  label: string
  items: NavModelItem[]
}
```

**`NavModelEntry`** — discriminated union:
```
type NavModelEntry = NavModelItem | NavModelGroup
```

The existing `NavigationItem` interface SHALL be retained (it is used by `getVisibleActions()` and potentially elsewhere). `NavigationData.models` SHALL be changed from `NavigationItem[]` to `NavModelEntry[]`.

**Files that consume `NavigationData.models` and must be updated to handle the `NavModelEntry` discriminated union:**

| File | Location | Current usage |
|---|---|---|
| `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx` | Model rendering loop | Iterates `navigationData.models` treating each entry as a `NavigationItem` |
| `gravitycar-frontend/src/services/api.ts` | Line 693 | Maps over `navData.data.models` treating each entry as a `NavigationItem` |

Both files must be updated to handle `type === 'group'` and `type === 'item'` entries correctly.

### 6.2 Rationale for Discriminated Union

The `type` discriminator field mirrors the PHP cache structure exactly, making the JSON-to-TypeScript mapping trivial. React's `.map()` with `entry.type === 'group'` cleanly separates group rendering from item rendering, matching the pattern already established by `groupCustomPages()` for custom pages.

---

## 7. Frontend Component Changes

### 7.0 New Hook: `useModelActions`

**File**: `gravitycar-frontend/src/hooks/useModelActions.ts`

To avoid duplicating action-expansion logic in both `NavigationSidebar` (ungrouped items) and `NavGroupSection` (grouped sub-items), the `expandedModel` state and related handlers SHALL be extracted into a shared custom hook.

**Hook interface:**
```
useModelActions() → {
  expandedModel: string | null
  getVisibleActions: (item: NavigationItem) => NavigationAction[]
  handleActionClick: (action: NavigationAction, item: NavigationItem) => void
  setExpandedModel: (name: string | null) => void
}
```

**Behavior:**
- `expandedModel` is a `string | null` tracking which model's action sub-menu is currently open (by model `name`)
- `getVisibleActions(item)` returns the filtered list of actions shown in the action sub-menu for a given item; it uses the same logic currently inlined in `NavigationSidebar`
- `handleActionClick(action, item)` executes the action (e.g., navigate to create URL) and closes the sub-menu
- Both `NavigationSidebar` (for ungrouped top-level items) and `NavGroupSection` (for grouped sub-items) SHALL call `useModelActions()` independently; each instance maintains its own isolated state

**Rationale:** Extracting to a shared hook eliminates code duplication and ensures grouped and ungrouped items behave consistently, without requiring the two components to share state.

### 7.1 New Component: `NavGroupSection`

**File**: `gravitycar-frontend/src/components/navigation/NavGroupSection.tsx`

This is a new, standalone component responsible for rendering one collapsible navigation group.

**Props:**
```
interface NavGroupSectionProps {
  label: string
  items: NavModelItem[]
  defaultOpen?: boolean   // true when the active route is inside this group
}
```

**Behavior:**
- Internal `useState<boolean>` tracks expanded/collapsed state
- Initializes to `defaultOpen` prop value (used for active-route auto-expand on first render)
- A `useEffect` watches `location.pathname` (received from parent or via `useLocation`). If any item in the group has `item.url === location.pathname`, the effect calls `setIsOpen(true)`. The effect SHALL NOT call `setIsOpen(false)` when no item matches — the user can manually collapse a group even while viewing a model inside it
- Calls `useModelActions()` to manage action sub-menu state for its sub-items
- Renders a `<button>` as the group header with:
  - `aria-expanded={isOpen}` (boolean, not string)
  - `aria-controls={contentId}` where `contentId` is computed by the following normative formula:
    ```
    contentId = 'nav-group-'
      + groupName.toLowerCase().replace(/[^a-z0-9]+/g, '-')
      + '-'
      + label.toLowerCase().replace(/[^a-z0-9]+/g, '-')
    ```
    where `groupName` is the group's display label (e.g., `"Event Organizer"`) and `label` is also the group's display label. Both are slugified identically using the same regex. The compound form (groupName + label) ensures uniqueness even if two groups have similar names. Example: `nav-group-event-organizer-event-organizer`
  - A right-pointing chevron SVG that rotates 90° (clockwise) when expanded, using `transition-transform duration-200`
- Renders a `<ul>` with matching `id={contentId}` containing the sub-items
- The `<ul>` uses `max-h-0` / `max-h-64` toggling with `overflow-hidden transition-all duration-300` for smooth animation; the fixed pixel cap SHALL be `max-h-64` (256px)
- Each sub-item inside the group renders identically to how a current top-level model item renders in `NavigationSidebar.tsx` (icon + title as a link; chevron expander for actions if present), using `expandedModel`, `getVisibleActions`, and `handleActionClick` from `useModelActions()`
- `aria-current="page"` SHALL be applied to the active link (using `useLocation()` to compare `location.pathname` with `item.url`)

**Keyboard behavior:**
- Pressing Escape while focus is inside an open `NavGroupSection` SHALL simultaneously:
  1. Set `isOpen = false` (close the group)
  2. Call `setExpandedModel(null)` from `useModelActions` (close any open action sub-menu)
  3. Return focus to the group's toggle button
  This applies regardless of which disclosure level currently has focus (group level or action sub-menu level)

**Accessibility notes:**
- Do NOT use `aria-haspopup` on the group button (it implies a popup widget; this is a disclosure)
- The group button is a `<button>` element, not an `<a>` tag, because it does not navigate
- Group items are `<a>` tags that navigate to their respective model list pages

### 7.2 Changes to `NavigationSidebar.tsx`

File: `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx`

**Type import update**: Import `NavModelEntry`, `NavModelItem`, `NavModelGroup` from `navigation.ts` in addition to existing imports.

**Hook usage**: Replace the inline `expandedModel` state and action handlers with a call to `useModelActions()`. The returned `expandedModel`, `getVisibleActions`, `handleActionClick`, and `setExpandedModel` are used for ungrouped top-level items exactly as the current inline code does.

**Model rendering loop** — replace the current flat `.map(model => ...)` over `navigationData.models` with a discriminated union map:
- When `entry.type === 'group'`: render `<NavGroupSection>` with `label`, `items`, and `defaultOpen` set to `true` if any `item.url === location.pathname` within that group's items (exact equality match — do NOT use `startsWith`)
- When `entry.type === 'item'`: render using the existing inline model item rendering (extracted to a local helper or private component for clarity), consuming state from `useModelActions()`

**Debug panel update**: The dev-mode debug section at the bottom of the sidebar currently iterates `navigationData.models` and accesses `model.permissions`. This code SHALL be updated to handle the discriminated union (iterate items within groups, and ungrouped items, separately).

### 7.3 No New Utility Function Required

Because the PHP cache is pre-grouped, no client-side grouping transform (analogous to `groupCustomPages()`) is needed. The frontend renders the pre-grouped structure directly.

---

## 8. Cache Rebuild Requirement

After updating metadata files and deploying `NavigationBuilder` changes, all four role caches must be rebuilt. The existing `POST /navigation/cache/rebuild` endpoint handles this. The process is:

1. Deploy PHP changes
2. Call `POST /navigation/cache/rebuild` (requires admin role)
3. The endpoint calls `buildAllRoleNavigationCaches()` for all four roles: `admin`, `manager`, `user`, `guest`
4. The frontend `navigationService.clearCache()` is called automatically after rebuild (existing behavior)

No migration script is needed; cache files are fully regenerated on rebuild.

---

## 9. Ordering Rules

The following ordering rules apply to the `models` array in the cache:

1. **Groups appear before ungrouped items** at the top level
2. **Groups are ordered alphabetically** by their `label`
3. **Items within a group are ordered alphabetically** by their `title`
4. **Ungrouped items are ordered alphabetically** by their `title`

This ordering is deterministic and requires no explicit order field in the metadata.

---

## 10. Acceptance Criteria

### AC-1: Hidden Models Excluded from Cache

Given that `GoogleOauthTokens` and `JwtRefreshTokens` metadata files have `'navigation_bar' => false`,
when the navigation cache is rebuilt for any role,
then neither `GoogleOauthTokens` nor `JwtRefreshTokens` SHALL appear anywhere in the `models` array of the cache file.

### AC-2: Event Organizer Group in Cache

Given that Events, EventCommitments, EventReminders, and EventProposedDates metadata files have `'navigation_bar' => 'Event Organizer'`,
when the navigation cache is rebuilt for a role that has `list` permission on all four models,
then the `models` array SHALL contain exactly one entry with `type === 'group'` and `label === 'Event Organizer'`, and that entry's `items` array SHALL contain all four models.

### AC-3: Ungrouped Models Remain Ungrouped

Given that Books, Movies, Projects, Roles, Permissions, Users, EmailQueue, Movie_Quotes, Movie_Quote_Trivia_Games, Movie_Quote_Trivia_Questions, and Installer do not have a `navigation_bar` group string in their metadata,
when the navigation cache is rebuilt,
then those models SHALL appear as top-level entries with `type === 'item'` in the `models` array (subject to RBAC — only if the role has `list` permission).

### AC-4: Groups Appear Before Ungrouped Items

When the navigation cache is rebuilt,
then all `type === 'group'` entries SHALL appear before all `type === 'item'` entries in the top-level `models` array.

### AC-5: Items Within Group Are Sorted

When the navigation cache is built and the "Event Organizer" group is present,
then items within that group SHALL be ordered alphabetically by `title`: Event Commitments, Event Proposed Dates, Event Reminders, Events.

### AC-6: Sidebar Renders Group as Collapsible Section

Given a user is authenticated and navigation loads with a `type === 'group'` entry,
when the navigation sidebar renders,
then the group SHALL appear as a button with the group label, a chevron icon, and no navigation link on the button itself.

### AC-7: Group Expands and Collapses

Given the navigation sidebar is rendered with the "Event Organizer" group,
when the user clicks the group button,
then the group SHALL expand to show its child model links;
when the user clicks the button again,
then the group SHALL collapse and hide the child links.

### AC-8: Group Auto-Expands When Active Route Matches (One-Way Only)

Given the user navigates to a URL that belongs to a model inside a group (e.g., `/EventCommitments`),
when the navigation sidebar loads or the route changes,
then the group containing that model SHALL be expanded by default without requiring a user click.
The match SHALL use exact equality (`item.url === location.pathname`); `startsWith` SHALL NOT be used.
The auto-open useEffect SHALL trigger when `location.pathname` matches a group item URL; it SHALL NOT auto-close when no item in the group matches the current path. A user who manually collapses a group while viewing a model inside it SHALL NOT have the group forcibly re-opened by the effect.

### AC-9: Active Link Marked with `aria-current="page"`

Given the current URL matches a model's URL in the navigation,
when the sidebar renders,
then that model's link SHALL have `aria-current="page"` and no other model link SHALL have that attribute.

### AC-10: Accessible Button Attributes

Given the navigation sidebar is rendered with a group,
when the group button is inspected,
then it SHALL have `aria-expanded="false"` when collapsed and `aria-expanded="true"` when expanded, and `aria-controls` pointing to the `id` of the submenu `<ul>`.
The `id` of the submenu `<ul>` SHALL be computed using the normative formula:
`'nav-group-' + groupName.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-' + label.toLowerCase().replace(/[^a-z0-9]+/g, '-')`
where both `groupName` and `label` are the group's display label.

### AC-11: Escape Key Closes Group and Action Sub-Menu Simultaneously

Given the navigation sidebar is rendered and a group is open, and focus is anywhere inside the open `NavGroupSection` (at either the group level or an action sub-menu level),
when the user presses the Escape key,
then simultaneously: (1) the group SHALL close (`isOpen = false`), (2) any open action sub-menu SHALL close (`setExpandedModel(null)`), and (3) focus SHALL return to the group's toggle button.
This behavior applies regardless of which disclosure level currently has focus.

### AC-12: Absent `navigation_bar` Property Treated as Ungrouped

Given a model metadata file does not include the `navigation_bar` key,
when the navigation cache is rebuilt,
then that model SHALL be treated identically to `navigation_bar => ''` — shown as a top-level ungrouped item (if RBAC permits).

### AC-13: No TypeScript Compilation Errors

When the TypeScript compiler runs on the frontend source,
then there SHALL be zero type errors related to the `NavigationData.models` type change or the new `NavModelEntry` discriminated union.

---

## 11. Technical Context

### Existing Patterns to Follow

- **RBAC permission check**: Use the existing `$this->authorizationService->roleHasPermission($roleModel, $action, $modelName)` pattern; do not change how permission filtering works
- **Metadata access**: Use `$this->metadataEngine->getModelMetadata($modelName)` with the existing null-coalesce pattern for optional properties
- **Cache writing**: `writeNavigationCache()` is unchanged; it uses `var_export()` and handles nested arrays correctly
- **Frontend action expansion**: The existing per-model actions expander (`expandedModel` state + chevron) SHALL be extracted into the `useModelActions` hook and consumed by both `NavigationSidebar` (ungrouped items) and `NavGroupSection` (grouped sub-items); each component instance gets its own isolated hook state
- **React Router location**: Use `useLocation()` (already imported in `NavigationSidebar.tsx`) for active-route detection

### Integration Points

- **`NavigationBuilder::buildModelNavigation()`** → produces the new nested cache structure
- **`cache/navigation_cache_{role}.php`** → consumed by the navigation API endpoint (unchanged backend API contract — the API returns the cache contents as-is; the shape of `models` changes but the API transport layer is untouched)
- **`gravitycar-frontend/src/types/navigation.ts`** → type definitions consumed by `NavigationSidebar.tsx`, `navigationService.ts`, and any other component accessing `NavigationData`
- **`gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx`** → primary render component; imports and uses `NavGroupSection` and `useModelActions`
- **`gravitycar-frontend/src/hooks/useModelActions.ts`** → new shared hook; consumed by both `NavigationSidebar` and `NavGroupSection`
- **`gravitycar-frontend/src/services/api.ts`** (line 693) → maps over `navData.data.models`; must be updated to handle the `NavModelEntry` discriminated union

---

## 12. Open Questions

None. All design decisions have been resolved:

| Decision | Resolution | Rationale |
|---|---|---|
| `navigation_bar` value type: string vs array | Plain string (e.g., `'Event Organizer'`) | Only single-level grouping is planned; array adds complexity without benefit |
| Cache structure: flat with group field vs pre-grouped | Pre-grouped nested structure with `type` discriminator | Simpler frontend rendering; groups ordering is explicit; mirrors Filament PHP pattern |
| Frontend collapse mechanism: `useState` + CSS vs `<details>` | `useState` + Tailwind `max-h` transition | Consistent with existing sidebar pattern; finer control over animation and initial open state |
| ARIA pattern: tree/treeitem vs Disclosure | Disclosure (`button` + `aria-expanded`) | W3C WAI-ARIA APG recommends Disclosure for sidebar nav groups; tree requires complex keyboard management |
| Group ordering relative to ungrouped items | Groups first, then ungrouped | Provides consistent predictable layout; prominent groups anchor the nav structure |
