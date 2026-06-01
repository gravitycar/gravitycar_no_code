# Implementation Plan: CAT-01 — CacheComponent Constants Class

## Spec Context

This class defines the canonical string identifiers for the four cache components used throughout the Admin Panel epic. Every other class that references a cache component (CacheRebuildOptions, CacheArchiver, CacheRebuilder, AdminAPIController, the CLI script) imports and uses these constants. This is a pure value class — no business logic, no I/O.

Catalog item: CAT-01  
Specification section: Component 1 — CacheComponent; AC-16  
Acceptance criteria addressed:
- AC-16: Valid component identifiers are `CacheComponent::METADATA`, `CacheComponent::ROUTES`, `CacheComponent::DOCS`, `CacheComponent::NAVIGATION`
- AC-15: `CacheRebuildOptions` (and callers) use these constants for the `components` array
- AC-17: `CacheRebuildOptions::all()` uses `CacheComponent::all()` to enumerate all four

---

## Dependencies

- **Blocked by**: none
- **Blocks**: CAT-02 (CacheRebuildOptions uses these constants)
- **Uses**: PHP 8.2+ built-in language features only — no external libraries

---

## File Changes

### New Files
- `src/Services/Admin/CacheComponent.php` — pure constants class with enumeration and validation helpers

### Modified Files
- none

---

## Implementation Details

### CacheComponent

**File**: `src/Services/Admin/CacheComponent.php`

**Namespace**: `Gravitycar\Services\Admin`

**Exports**:
- `METADATA` (string constant) — `'metadata'`
- `ROUTES` (string constant) — `'routes'`
- `DOCS` (string constant) — `'docs'`
- `NAVIGATION` (string constant) — `'navigation'`
- `all(): array` — static method, returns all four constants
- `isValid(string $component): bool` — static method, validates a component identifier

**Design notes**:
- This is a final class with no constructor — it cannot be instantiated. Use `final class`.
- All four constants are `public const string`.
- `all()` calls `isValid()` indirectly; the canonical list of valid identifiers lives in `all()`, and `isValid()` delegates to `all()` for the check.
- No logger, no config — per spec, this is a pure constants class.
- Per CLAUDE.md coding conventions: the constants array used in `all()` is defined as a class-level constant (since it is a fixed set never changed programmatically).

**Code Example**:

```php
<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

/**
 * CacheComponent
 *
 * Defines the four named cache component identifiers used throughout the
 * cache rebuild system. Each constant value matches the cache file identifier
 * used in CacheArchiver, CacheRebuilder, and CacheRebuildOptions.
 *
 * Component-to-cache-file mapping:
 *   METADATA   → cache/metadata_cache.php
 *   ROUTES     → cache/api_routes.php
 *   DOCS       → cache/documentation/ (directory)
 *   NAVIGATION → cache/navigation_cache_*.php (multiple files)
 */
final class CacheComponent
{
    public const string METADATA   = 'metadata';
    public const string ROUTES     = 'routes';
    public const string DOCS       = 'docs';
    public const string NAVIGATION = 'navigation';

    /** @var string[] All valid component identifiers */
    private const array ALL_COMPONENTS = [
        self::METADATA,
        self::ROUTES,
        self::DOCS,
        self::NAVIGATION,
    ];

    /**
     * Returns all four component identifiers.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return self::ALL_COMPONENTS;
    }

    /**
     * Returns true if $component is one of the four valid identifiers.
     */
    public static function isValid(string $component): bool
    {
        return in_array($component, self::ALL_COMPONENTS, strict: true);
    }
}
```

**Implementation notes**:
- `in_array(..., strict: true)` prevents type coercions (e.g., `0 == 'metadata'` would pass a loose check).
- The `ALL_COMPONENTS` private constant holds the canonical list so it is defined in exactly one place.
- PHP 8.2 typed class constants (`public const string`, `private const array`) are used.
- No instantiation path exists (`final class`, no public constructor).

---

## Error Handling

- No exceptions are thrown by this class.
- `isValid()` returns `false` for any unrecognized string; callers (CacheRebuildOptions, AdminAPIController) decide how to react.

---

## Unit Test Specifications

**Test file**: `tests/Unit/Services/Admin/CacheComponentTest.php`

### `CacheComponent::all()`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Returns all four identifiers | (none) | `['metadata', 'routes', 'docs', 'navigation']` | All four must be present |
| Returns a plain array | (none) | `is_array($result) === true` | Must be usable in foreach/count |
| Returns exactly four items | (none) | `count($result) === 4` | No extras or missing items |

### `CacheComponent::isValid()`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Valid: metadata | `'metadata'` | `true` | Exact constant value |
| Valid: routes | `'routes'` | `true` | Exact constant value |
| Valid: docs | `'docs'` | `true` | Exact constant value |
| Valid: navigation | `'navigation'` | `true` | Exact constant value |
| Invalid: uppercase | `'METADATA'` | `false` | Case-sensitive strict check |
| Invalid: unknown | `'users'` | `false` | Not a defined component |
| Invalid: empty string | `''` | `false` | Edge case |
| Invalid: integer-like | `'0'` | `false` | Strict type check |

### `CacheComponent` constants

| Case | Check | Expected | Why |
|------|-------|----------|-----|
| METADATA constant value | `CacheComponent::METADATA` | `'metadata'` | Cache file uses this string |
| ROUTES constant value | `CacheComponent::ROUTES` | `'routes'` | Cache file uses this string |
| DOCS constant value | `CacheComponent::DOCS` | `'docs'` | Cache dir uses this string |
| NAVIGATION constant value | `CacheComponent::NAVIGATION` | `'navigation'` | Cache files use this string |

### Key Scenario: `all()` and `isValid()` stay in sync

**Setup**: Get the array from `CacheComponent::all()`.  
**Action**: For each element in the result, call `CacheComponent::isValid($element)`.  
**Expected**: Every element returns `true` — no element in `all()` is rejected by `isValid()`.  
**Why**: The two methods share the same `ALL_COMPONENTS` constant; this test catches any future drift.

---

## Notes

- PHP 8.2 typed constants require PHP 8.2+; the project spec confirms PHP 8.2+.
- The constant values (`'metadata'`, `'routes'`, etc.) must match exactly what `CacheArchiver` and `CacheRebuilder` use when constructing file paths. The archive and clear logic in those classes will switch on these string values.
- This class should be the ONLY place in the codebase where these string literals are defined. All other classes import the constants.
- `final class` prevents subclassing which would allow constant overriding — appropriate for a pure value class.
