# Implementation Plan: Item 1a — Hidden Model Metadata (`navigation_bar: false`)

## Spec Context

Implements spec §4.1 and §4.2 for the two backend-only auth models that must be completely excluded from the navigation sidebar. The `navigation_bar` property is a new optional top-level key in any model metadata file. Setting it to `false` signals the `NavigationBuilder` to skip this model when building navigation caches.

Catalog item: Item 1a — Add `navigation_bar` to hidden-model metadata files
Specification section: §4.1 (schema), §4.2 (models table)
Acceptance criteria addressed: AC-1 (hidden models excluded from cache), AC-12 (absent property treated as ungrouped)

---

## Dependencies

- **Blocked by**: nothing — can start immediately
- **Blocks**: Item 2 (NavigationBuilder reads `navigation_bar` from metadata; must be present before the builder is refactored)
- **Uses**: Existing metadata file structure; no library or class dependencies

---

## File Changes

### New Files
None.

### Modified Files

- `src/Models/googleoauthtokens/googleoauthtokens_metadata.php` — Add `'navigation_bar' => false` as a top-level key
- `src/Models/jwtrefreshtokens/jwt_refresh_tokens_metadata.php` — Add `'navigation_bar' => false` as a top-level key

---

## Implementation Details

### Existing Metadata Structure

Both files return a flat PHP associative array. The top-level keys in the current files, in order, are:

**`googleoauthtokens_metadata.php`** (64 lines):
```
'name'            => 'GoogleOauthTokens'
'table'           => 'google_oauth_tokens'
'fields'          => [ ... ]
'validationRules' => []
'relationships'   => ['users_google_oauth_tokens']
'ui'              => [ 'listFields' => [...], 'createFields' => [...] ]
'rolesAndActions' => [ 'admin' => ['*'], 'manager' => ['read'], 'user' => ['read'], 'guest' => [] ]
```

**`jwt_refresh_tokens_metadata.php`** (65 lines):
```
'name'            => 'JwtRefreshTokens'
'table'           => 'jwt_refresh_tokens'
'fields'          => [ ... ]
'validationRules' => []
'relationships'   => ['users_jwt_refresh_tokens']
'ui'              => [ 'listFields' => [...], 'createFields' => [...] ]
'rolesAndActions' => [ 'admin' => ['*'], 'manager' => ['read'], 'user' => ['read'], 'guest' => [] ]
```

### Placement Rule

The `navigation_bar` property is a top-level navigation concern, not a field definition or RBAC concern. Place it **after `'name'`** (the model identity key) and **before `'table'`** (the persistence key). This groups the metadata logically: identity → display/navigation → persistence → structure.

The spec (§4.1) states the property is optional, but imposes no ordering requirement. The chosen placement is a convention decision — it follows the principle of declaring display/navigation behavior near the model identity declaration.

### Exact Change — `googleoauthtokens_metadata.php`

**File**: `src/Models/googleoauthtokens/googleoauthtokens_metadata.php`

Insert after line 4 (`'name' => 'GoogleOauthTokens',`), before line 5 (`'table' => 'google_oauth_tokens',`):

```php
    'navigation_bar' => false,
```

**Result — top of file after change:**
```php
<?php

return [
    'name' => 'GoogleOauthTokens',
    'navigation_bar' => false,
    'table' => 'google_oauth_tokens',
    'fields' => [
```

### Exact Change — `jwt_refresh_tokens_metadata.php`

**File**: `src/Models/jwtrefreshtokens/jwt_refresh_tokens_metadata.php`

Insert after line 4 (`'name' => 'JwtRefreshTokens',`), before line 5 (`'table' => 'jwt_refresh_tokens',`):

```php
    'navigation_bar' => false,
```

**Result — top of file after change:**
```php
<?php

return [
    'name' => 'JwtRefreshTokens',
    'navigation_bar' => false,
    'table' => 'jwt_refresh_tokens',
    'fields' => [
```

### PHP Syntax Notes

- The value is the PHP boolean literal `false` (lowercase), NOT the string `'false'`.
- Use 4-space indentation to match the existing file style.
- A trailing comma after `false` is required (both surrounding lines already have trailing commas; this is consistent PHP array style).

---

## Edge Cases

### Property Already Exists

Neither file currently contains a `navigation_bar` key (confirmed by reading both files). The property does not exist anywhere in the codebase (the codebase summary confirms "No `navigation_bar` property currently exists in any metadata file"). No defensive check is needed during this change.

If a future developer adds `navigation_bar` again to these files, there would be a PHP array key collision (second declaration silently overwrites first). To guard against this at review time, the reviewer should verify there is exactly one `navigation_bar` key in the file.

### Wrong Value Type

The `NavigationBuilder` will use `=== false` (strict equality) to detect hidden models (per spec §5.2 pseudocode: `if navigationBar === false: skip`). Using the string `'false'` instead of the boolean `false` would cause the model to appear in navigation as an ungrouped item (treated as a non-empty string). Use the PHP boolean literal `false` only.

### Metadata Cache Invalidation

These metadata files are consumed by `MetadataEngine`, which caches its parsed output at `cache/`. After deploying this change:
1. The metadata cache may need to be cleared/rebuilt before `NavigationBuilder` sees the new `navigation_bar` key.
2. After the metadata cache is fresh, the navigation cache for all roles must be rebuilt via `POST /navigation/cache/rebuild`.

This is an operational concern, not a code change. It is documented in the spec §8 and the catalog's Post-Build Step section.

---

## Unit Test Specifications

These are metadata file changes only — there is no PHP class logic to unit test. The acceptance criteria are verified by the `NavigationBuilder` tests (Item 2) and potentially a smoke test that reads the metadata files.

### Smoke test: metadata files are valid PHP and contain `navigation_bar`

| Case | Action | Expected |
|------|--------|----------|
| GoogleOauthTokens metadata loads | `require` the file, inspect return value | `$meta['navigation_bar'] === false` (strict bool false) |
| JwtRefreshTokens metadata loads | `require` the file, inspect return value | `$meta['navigation_bar'] === false` (strict bool false) |
| No other top-level keys changed | Inspect return value | All existing keys (`name`, `table`, `fields`, `validationRules`, `relationships`, `ui`, `rolesAndActions`) are present and unchanged |

These cases may be covered by existing `MetadataEngine` integration tests if they load all metadata files and inspect the returned structure. If not, Item 2's test plan should include a test that calls `MetadataEngine::getModelMetadata('GoogleOauthTokens')` and asserts `navigation_bar === false`.

---

## Notes

- These are the smallest possible changes in the entire epic — one line added to each of two files.
- No class files change. No database changes. No frontend changes.
- The change is safe to deploy independently; it is inert until `NavigationBuilder` is updated in Item 2 to read and act on the property.
- The boolean `false` value matches the PHP `??` null-coalescing behavior used in `NavigationBuilder`: `$metadata['navigation_bar'] ?? ''` will yield `false` (not `''`) when the key is present with value `false`, triggering the skip branch correctly.
