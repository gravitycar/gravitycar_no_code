# Implementation Plan: NavigationBuilder Refactor (Item 2)

## Spec Context

Implements spec §5 — `NavigationBuilder::buildModelNavigation()` currently builds a flat array of
model items. After this change it reads the `navigation_bar` property from each model's metadata,
skips hidden models (`false`), buckets visible models into named groups or the ungrouped list, sorts
each bucket alphabetically, and returns a pre-grouped array where every entry carries a `type`
discriminator (`'group'` or `'item'`). Two logging statements are also updated to reflect the new
semantics of the values being counted.

Catalog item: **Item 2 — Refactor `NavigationBuilder::buildModelNavigation()`**
Specification sections: §5.1, §5.2, §5.3, §5.4
Acceptance criteria addressed: AC-1, AC-2, AC-3, AC-4, AC-5, AC-12

---

## Dependencies

- **Blocked by**: Plan 1a (hidden-model metadata) and Plan 1b (Event Organizer metadata) — both
  must exist before the builder can produce correct output, though the builder code itself can be
  written in parallel
- **Blocks**: Plan 3 (frontend types and `api.ts`) — the concrete cache structure produced here
  defines what the TypeScript discriminated union must represent
- **Uses**:
  - `$this->metadataEngine` (`MetadataEngineInterface`) — already injected; `getModelMetadata()`
    will be called for the first time inside `buildModelNavigation()`
  - `$this->authorizationService` — unchanged
  - `$this->modelFactory` — unchanged

---

## File Changes

### Modified Files

- `src/Services/NavigationBuilder.php` — three changes:
  1. New private method `buildModelItem()` (extraction of existing inline code)
  2. Full replacement of `buildModelNavigation()` body with grouping algorithm
  3. Two logging-statement updates (one in `buildNavigationForRole()`, one in
     `buildAllRoleNavigationCaches()`)

---

## Implementation Details

### Change 1 — Extract `buildModelItem()`

**Location**: add as a new `protected` method after `buildModelNavigation()` (i.e., after the
current line 151, before `getRoleByName()` at line 153).

**Signature**:
```php
protected function buildModelItem(string $modelName, object $roleModel): array
```

**Body** — move the existing inline construction verbatim, then add `'type' => 'item'` as the
first key. The complete method:

```php
/**
 * Build a single navigation item array for a model.
 * Always includes 'type' => 'item' as the first key.
 */
protected function buildModelItem(string $modelName, object $roleModel): array
{
    $modelItem = [
        'type'        => 'item',
        'name'        => $modelName,
        'title'       => $this->generateModelTitle($modelName),
        'url'         => '/' . $modelName,
        'icon'        => $this->getModelIcon($modelName),
        'actions'     => [],
        'permissions' => [
            'list'   => true,
            'create' => false,
            'update' => false,
            'delete' => false,
        ],
    ];

    $hasCreatePermission = $this->authorizationService->roleHasPermission($roleModel, 'create', $modelName);
    if ($hasCreatePermission) {
        $modelItem['actions'][] = [
            'key'    => 'create',
            'title'  => 'Create New',
            'action' => 'create',
            'icon'   => '➕',
        ];
        $modelItem['permissions']['create'] = true;
    }

    $hasUpdatePermission = $this->authorizationService->roleHasPermission($roleModel, 'update', $modelName);
    if ($hasUpdatePermission) {
        $modelItem['permissions']['update'] = true;
    }

    $hasDeletePermission = $this->authorizationService->roleHasPermission($roleModel, 'delete', $modelName);
    if ($hasDeletePermission) {
        $modelItem['permissions']['delete'] = true;
    }

    return $modelItem;
}
```

**Note on access modifier**: use `protected` (not `private`) so that a test subclass can override
it for isolation, consistent with the project's other helper methods in this class.

---

### Change 2 — Replace `buildModelNavigation()` body

**Location**: method `buildModelNavigation()`, currently lines 75–151.

Keep the existing method signature unchanged:

```php
protected function buildModelNavigation(array $modelNames, string $role): array
```

Replace the entire method body with the grouping algorithm below. The old body (lines 77–150) is
deleted in full; the new body is substituted.

```php
protected function buildModelNavigation(array $modelNames, string $role): array
{
    $roleModel = $this->getRoleByName($role);
    if (!$roleModel) {
        $this->logger->warning('Role not found for navigation building', ['role' => $role]);
        return [];
    }

    $groups    = [];   // string $label => array[] $modelItems
    $ungrouped = [];   // array[] $modelItems

    foreach ($modelNames as $modelName) {
        try {
            $hasListPermission = $this->authorizationService->roleHasPermission($roleModel, 'list', $modelName);
            if (!$hasListPermission) {
                continue;
            }

            $metadata      = $this->metadataEngine->getModelMetadata($modelName);
            $navigationBar = $metadata['navigation_bar'] ?? '';

            if ($navigationBar === false) {
                continue;  // Hidden model — skip entirely
            }

            $modelItem = $this->buildModelItem($modelName, $roleModel);

            if (is_string($navigationBar) && $navigationBar !== '') {
                $groups[$navigationBar][] = $modelItem;
            } else {
                $ungrouped[] = $modelItem;
            }

        } catch (\Exception $e) {
            $this->logger->warning('Failed to build navigation for model', [
                'model' => $modelName,
                'role'  => $role,
                'error' => $e->getMessage(),
            ]);
        }
    }

    return $this->assembleNavigationResult($groups, $ungrouped);
}
```

**Edge cases handled inside the loop**:

| Condition | Handling |
|---|---|
| `navigation_bar` key absent from metadata | `?? ''` coalesces to empty string → ungrouped |
| `navigation_bar === null` | `?? ''` coalesces to empty string → ungrouped |
| `navigation_bar === ''` | explicit `$navigationBar !== ''` check → ungrouped |
| `navigation_bar === false` | `=== false` strict check → `continue` (skip) |
| `navigation_bar` is a non-empty string | bucketed under that group label |
| `getModelMetadata()` throws `GCException` | caught by `\Exception`; logged as warning; model skipped |
| Role has no `list` permission | `continue` before metadata fetch (avoids unnecessary call) |

---

### Change 2b — New private helper `assembleNavigationResult()`

Extract the sort + merge step into its own method to keep `buildModelNavigation()` under 30 lines.
Add this immediately after `buildModelItem()`.

```php
/**
 * Sort groups and ungrouped items, then merge into the final ordered result array.
 * Groups appear first (sorted alphabetically by label), followed by ungrouped items
 * (sorted alphabetically by title).
 */
private function assembleNavigationResult(array $groups, array $ungrouped): array
{
    // Sort items within each group alphabetically by title
    foreach ($groups as &$groupItems) {
        usort($groupItems, fn(array $a, array $b) => strcmp($a['title'], $b['title']));
    }
    unset($groupItems);

    // Sort group labels alphabetically
    ksort($groups);

    // Sort ungrouped items alphabetically by title
    usort($ungrouped, fn(array $a, array $b) => strcmp($a['title'], $b['title']));

    // Build result: groups first, then ungrouped items
    $result = [];
    foreach ($groups as $label => $items) {
        $result[] = [
            'type'  => 'group',
            'label' => $label,
            'items' => $items,
        ];
    }
    foreach ($ungrouped as $item) {
        $result[] = $item;
    }

    return $result;
}
```

---

### Change 3 — Update log key in `buildNavigationForRole()`

**Location**: `buildNavigationForRole()`, currently lines 63–67:

```php
$this->logger->info('Navigation built successfully', [
    'role' => $role,
    'custom_pages_count' => count($navigation['custom_pages']),
    'models_count' => count($navigation['models'])
]);
```

Change `'models_count'` to `'top_level_nav_entries_count'`. The value (`count($navigation['models'])`)
is unchanged — it now correctly describes the count of top-level entries (groups + ungrouped items):

```php
$this->logger->info('Navigation built successfully', [
    'role'                        => $role,
    'custom_pages_count'          => count($navigation['custom_pages']),
    'top_level_nav_entries_count' => count($navigation['models']),
]);
```

---

### Change 4 — Update log key and count in `buildAllRoleNavigationCaches()`

**Location**: `buildAllRoleNavigationCaches()`, currently lines 223–227:

```php
$cacheResults[$role] = [
    'success'     => true,
    'cache_file'  => $cacheFile,
    'items_count' => count($navigation['models']) + count($navigation['custom_pages'])
];
```

After the refactor, `count($navigation['models'])` returns top-level entry count (groups count as 1
each), which under-counts total model links. The new code must sum the actual model item count:
- For each entry in `$navigation['models']`: if `entry['type'] === 'group'` add
  `count(entry['items'])`; if `entry['type'] === 'item'` add 1.

Replace that block with:

```php
$totalModelItems = $this->countTotalModelItems($navigation['models']);

$cacheResults[$role] = [
    'success'                => true,
    'cache_file'             => $cacheFile,
    'total_model_items_count' => $totalModelItems + count($navigation['custom_pages']),
];
```

And add the private helper method (after `assembleNavigationResult()`):

```php
/**
 * Count total individual model items across all groups and ungrouped entries.
 */
private function countTotalModelItems(array $modelEntries): int
{
    $count = 0;
    foreach ($modelEntries as $entry) {
        if ($entry['type'] === 'group') {
            $count += count($entry['items']);
        } else {
            $count += 1;
        }
    }
    return $count;
}
```

---

## Output Format (Cache Structure)

After the change, `$navigation['models']` will have this shape (example for admin role):

```php
[
    // Group entry (type === 'group')
    [
        'type'  => 'group',
        'label' => 'Event Organizer',
        'items' => [
            ['type' => 'item', 'name' => 'EventCommitments',  'title' => 'Event Commitments',   'url' => '/EventCommitments',  'icon' => '📋', 'actions' => [...], 'permissions' => [...]],
            ['type' => 'item', 'name' => 'EventProposedDates','title' => 'Event Proposed Dates', 'url' => '/EventProposedDates','icon' => '📋', 'actions' => [...], 'permissions' => [...]],
            ['type' => 'item', 'name' => 'EventReminders',    'title' => 'Event Reminders',      'url' => '/EventReminders',    'icon' => '📋', 'actions' => [...], 'permissions' => [...]],
            ['type' => 'item', 'name' => 'Events',            'title' => 'Events',               'url' => '/Events',            'icon' => '📅', 'actions' => [...], 'permissions' => [...]],
        ],
    ],
    // Ungrouped items (type === 'item'), alphabetical by title
    ['type' => 'item', 'name' => 'Books',      'title' => 'Books',      ...],
    ['type' => 'item', 'name' => 'EmailQueue', 'title' => 'Email Queue', ...],
    // ... etc.
]
```

`GoogleOauthTokens` and `JwtRefreshTokens` do NOT appear anywhere.

---

## Error Handling

- `getModelMetadata()` throws `GCException` (a subclass of `\Exception`) when a model name is not
  found in the metadata cache. The existing `catch (\Exception $e)` block in the loop catches this
  and logs a warning; the model is skipped. No new exception types are needed.
- If `$roleModel` is null (role not found), return `[]` immediately — same as existing behavior.
- `assembleNavigationResult()` is pure PHP array manipulation; it cannot throw.
- `countTotalModelItems()` is pure PHP array iteration; it cannot throw.

---

## Unit Test Specifications

**Test file**: `tests/Unit/Services/NavigationBuilderTest.php` (extend existing test class)

### `buildModelItem()`

| Case | Setup | Expected |
|---|---|---|
| All permissions granted | `roleHasPermission` returns `true` for all actions | Returns array with `type === 'item'`, `create` action in `actions[]`, all permissions `true` |
| Only list permission | `roleHasPermission` returns `true` only for `list` | Returns `type === 'item'`, empty `actions[]`, `create/update/delete` all `false` |
| Returns `type` key first | Any valid input | `array_key_first($result) === 'type'` (or assert key exists) |

### `buildModelNavigation()` — skip hidden models

| Case | Setup | Expected |
|---|---|---|
| `navigation_bar === false` | Metadata returns `['navigation_bar' => false]` | Model absent from result |
| `navigation_bar` absent | Metadata returns array without `navigation_bar` key | Model present as ungrouped `type === 'item'` |
| `navigation_bar === ''` | Metadata returns `['navigation_bar' => '']` | Model present as ungrouped `type === 'item'` |
| `navigation_bar === null` | Metadata returns `['navigation_bar' => null]` | Model present as ungrouped `type === 'item'` |
| `navigation_bar === 'MyGroup'` | Metadata returns `['navigation_bar' => 'MyGroup']` | Model inside `type === 'group'` entry with `label === 'MyGroup'` |

### `buildModelNavigation()` — output structure

| Case | Setup | Expected |
|---|---|---|
| Groups before ungrouped | 1 grouped model + 1 ungrouped | First entry is `type === 'group'`, second is `type === 'item'` |
| Groups alphabetical | Groups 'Zebra' and 'Alpha' | 'Alpha' group appears before 'Zebra' group |
| Items within group alphabetical | Group has models with titles 'Zoo', 'Ant', 'Bear' | Order: Ant, Bear, Zoo |
| Ungrouped alphabetical | Ungrouped models with titles 'Zoo', 'Ant' | Order: Ant, Zoo |
| No list permission | `roleHasPermission('list')` returns `false` | Model absent from result |
| Role not found | `getRoleByName()` returns `null` | Returns `[]` |
| `getModelMetadata()` throws | Mock throws `GCException` | Model skipped; warning logged; other models unaffected |

### `assembleNavigationResult()` (test via `buildModelNavigation()`)

| Case | Expected |
|---|---|
| Empty groups and empty ungrouped | Returns `[]` |
| Only groups | All entries have `type === 'group'` |
| Only ungrouped | All entries have `type === 'item'` |

### `countTotalModelItems()` (test via `buildAllRoleNavigationCaches()` or directly)

| Case | Input | Expected count |
|---|---|---|
| All ungrouped | 3 `type === 'item'` entries | 3 |
| All grouped | 1 group with 4 items | 4 |
| Mixed | 1 group (3 items) + 2 ungrouped | 5 |
| Empty | `[]` | 0 |

### Log key verification

| Case | Expected log context key |
|---|---|
| `buildNavigationForRole()` completes | Log contains `top_level_nav_entries_count` (not `models_count`) |
| `buildAllRoleNavigationCaches()` completes | `cacheResults[$role]['total_model_items_count']` exists (not `items_count`) |

**Key test scenario — mixed grouped and ungrouped with hidden model:**

```
Setup:
  Models: ['Events', 'Books', 'GoogleOauthTokens']
  Metadata:
    Events            => ['navigation_bar' => 'Event Organizer']
    Books             => []  (no navigation_bar key)
    GoogleOauthTokens => ['navigation_bar' => false]
  All roles have 'list' permission for all three models

Action: buildModelNavigation(['Events', 'Books', 'GoogleOauthTokens'], 'admin')

Expected result:
  [
    ['type' => 'group', 'label' => 'Event Organizer', 'items' => [
        ['type' => 'item', 'name' => 'Events', ...]
    ]],
    ['type' => 'item', 'name' => 'Books', ...]
  ]
  // GoogleOauthTokens absent entirely
```

---

## Method Ordering in Final File

After all changes, the method order in `NavigationBuilder.php` shall be:

1. `__construct()` (unchanged, lines 24–36)
2. `buildNavigationForRole()` (modified log key, lines ~41–70)
3. `buildModelNavigation()` (new body, ~25 lines)
4. `assembleNavigationResult()` (new private method)
5. `countTotalModelItems()` (new private method)
6. `buildModelItem()` (new protected method, extracted from old `buildModelNavigation()`)
7. `getRoleByName()` (unchanged)
8. `generateModelTitle()` (unchanged)
9. `getModelIcon()` (unchanged)
10. `buildAllRoleNavigationCaches()` (modified count/key)
11. `writeNavigationCache()` (unchanged)

---

## Notes

- The `?? ''` null-coalesce is intentional: PHP's `null ?? ''` yields `''`, and absent-key access
  also yields `null` which then coalesces. Both cases correctly fall through to ungrouped.
- The strict `=== false` check for the hidden case is required because `'' == false` is `true` in
  PHP's loose comparison — always use strict equality here.
- `ksort($groups)` sorts string keys alphabetically (locale-independent, binary sort). This is
  correct for group labels like "Event Organizer" which are ASCII strings.
- `is_string($navigationBar) && $navigationBar !== ''` guards against any unexpected non-string,
  non-false value that might appear in a malformed metadata file.
- The RBAC permission check for `list` is performed BEFORE the metadata fetch to short-circuit early;
  this avoids unnecessary metadata lookups for models the role cannot see regardless.
- The existing `catch (\Exception $e)` in the loop already covers `GCException` (since
  `GCException` extends `\Exception`). No change to exception handling is required.
- `var_export()` in `writeNavigationCache()` handles arbitrarily nested arrays correctly — no
  changes needed there.
- The file remains well under 300 lines after adding the three new methods (~50 new lines total).
