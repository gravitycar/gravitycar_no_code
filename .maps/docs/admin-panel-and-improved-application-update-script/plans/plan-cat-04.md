# Implementation Plan: CAT-04 — AdminServiceException

## Spec Context

`AdminServiceException` is the specific exception type thrown by the three admin service classes (`CacheArchiver`, `CacheRebuilder`, and `AdminService`) whenever an operation fails. It follows the project's established exception pattern: extends `GCException`, carries an optional context array, and auto-logs on construction via `GCException::logException()`. Callers (primarily `AdminService::performCacheRebuild()`) catch it, record the failure in `CacheRebuildResult`, and return the result rather than re-throwing.

Catalog item: CAT-04  
Specification section: Component description for `AdminService`, `CacheArchiver`, `CacheRebuilder`; AC-3, AC-4, AC-10  
Acceptance criteria addressed:
- AC-3: `CacheArchiver::create()` throws `AdminServiceException` on failure
- AC-4: On any failure, `AdminService` stops processing and delegates to restore; the mechanism is catching `AdminServiceException`
- AC-10: On failure, `AdminService` records the error from the caught exception and returns (does not re-throw)

---

## Dependencies

- **Blocked by**: none
- **Blocks**: CAT-05 (CacheArchiver uses this exception), CAT-06 (CacheRebuilder uses this exception)
- **Uses**: `Gravitycar\Exceptions\GCException` — base class that provides `$context` property, `getContext()`, and `logException()` via `ServiceLocator::getLogger()`

---

## File Changes

### New Files
- `src/Exceptions/AdminServiceException.php` — domain exception for all admin cache rebuild operations

### Modified Files
- none

---

## Implementation Details

### AdminServiceException

**File**: `src/Exceptions/AdminServiceException.php`

**Namespace**: `Gravitycar\Exceptions`

**Extends**: `Gravitycar\Exceptions\GCException`

**Design notes**:

The project exception pattern (see `NavigationBuilderException`, `PermissionsBuilderException`) is: extend `GCException` with no additional methods. `GCException` already provides:
- `$context` (protected array)
- Constructor: `__construct(string $message, array $context = [], int $code = 0, ?Exception $previous = null)`
- `logException()` — called automatically in the constructor, uses `ServiceLocator::getLogger()` to log at `error` level with `exception`, `code`, `context`, and `trace` fields
- `getContext(): array`

`AdminServiceException` inherits all of the above. No additional methods are needed — the exception type itself conveys which subsystem failed (admin cache rebuild), and the `$context` array can carry step-specific data (e.g., archive path, component name, command output).

**Code Example**:

```php
<?php

declare(strict_types=1);

namespace Gravitycar\Exceptions;

/**
 * AdminServiceException
 *
 * Thrown by CacheArchiver, CacheRebuilder, and AdminService when a cache
 * rebuild operation fails. Carries an optional context array with diagnostic
 * information (e.g., archive file path, component name, command output).
 *
 * AdminService catches this exception and records the failure in
 * CacheRebuildResult rather than re-throwing it. This keeps the caller's
 * interface clean — performCacheRebuild() always returns a result.
 *
 * Usage examples:
 *
 *   // In CacheArchiver::create():
 *   throw new AdminServiceException(
 *       'Archive creation failed: tar command returned non-zero exit code',
 *       ['archiveFilePath' => $archiveFilePath, 'exitCode' => $exitCode]
 *   );
 *
 *   // In CacheRebuilder::validate():
 *   throw new AdminServiceException(
 *       'Syntax validation failed for cache file',
 *       ['filePath' => $filePath, 'component' => $component, 'output' => $output]
 *   );
 */
class AdminServiceException extends GCException
{
    // Inherits all functionality from GCException.
    // No additional methods needed — the exception type identifies the subsystem,
    // and $context carries step-specific diagnostic data.
}
```

**Constructor call signature** (inherited from GCException, shown for clarity):

```php
new AdminServiceException(
    string $message,
    array  $context  = [],   // optional: step-specific diagnostic data
    int    $code     = 0,    // optional: HTTP status code or custom code
    ?Exception $previous = null  // optional: wrapped underlying exception
);
```

**Typical context keys** (convention, not enforced):
- `'archiveFilePath'` — path of the tar archive being operated on
- `'component'` — which `CacheComponent` constant was being processed
- `'exitCode'` — shell command exit code
- `'output'` — shell command output
- `'filePath'` — specific cache file that failed validation
- `'step'` — name of the step that failed (`'archive'`, `'clear'`, `'rebuild'`, `'validate'`)

---

## Error Handling

- This class IS the error handling mechanism. It is thrown, not caught, within this file.
- `GCException::logException()` is called automatically in the constructor, so every `AdminServiceException` is automatically logged at `error` level — callers do not need to log separately.
- When `AdminService` catches `AdminServiceException`, it calls `$exception->getMessage()` and `$exception->getContext()` to populate the `CacheRebuildResult` step record.

---

## Unit Test Specifications

**Test file**: `tests/Unit/Exceptions/AdminServiceExceptionTest.php`

### Constructor and inheritance

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Extends GCException | `new AdminServiceException('msg')` | `instanceof GCException` | Inheritance chain must be correct |
| Extends Exception | `new AdminServiceException('msg')` | `instanceof \Exception` | PHP base class chain |
| Message stored | `new AdminServiceException('test message')` | `getMessage() === 'test message'` | Standard Exception behavior |
| Context stored | `new AdminServiceException('msg', ['key' => 'val'])` | `getContext() === ['key' => 'val']` | Via GCException::getContext() |
| Empty context default | `new AdminServiceException('msg')` | `getContext() === []` | Default parameter |
| Previous exception | `new AdminServiceException('msg', [], 0, $prev)` | `getPrevious() === $prev` | Chained exceptions |
| Code parameter | `new AdminServiceException('msg', [], 42)` | `getCode() === 42` | Standard Exception behavior |

### Key Scenario: Context carries diagnostic data

**Setup**: `$e = new AdminServiceException('Archive failed', ['archiveFilePath' => '/app/cache_2026_05_31.tar', 'exitCode' => 1]);`  
**Action**: Call `$e->getContext()`  
**Expected**: Returns `['archiveFilePath' => '/app/cache_2026_05_31.tar', 'exitCode' => 1]`  
**Why**: `CacheArchiver` and callers need to extract context for result recording.

### Key Scenario: Auto-logging on construction

**Note per CLAUDE.md**: Do NOT write tests that depend on log output. Instead, test the behavior that causes logging.

The auto-logging behavior is inherited from `GCException` and is already covered by GCException's own tests. `AdminServiceException` tests should NOT duplicate log-verification tests. The relevant behavior test is: constructing `AdminServiceException` does not throw a secondary exception (i.e., the logger fallback in `GCException::logException()` handles a missing logger gracefully).

---

## Notes

- File location is `src/Exceptions/AdminServiceException.php` — this follows the project convention that all exception classes live in `src/Exceptions/` (see `NavigationBuilderException.php`, `PermissionsBuilderException.php`).
- The exception is named `AdminServiceException` (not `CacheException` or `AdminException`) to scope it specifically to the admin service subsystem while remaining descriptive.
- `GCException` uses `ServiceLocator::getLogger()` internally. This is acceptable in the exception class itself even though new code avoids ServiceLocator, because exception classes cannot easily receive injected loggers without complicating every throw site.
- Follow exactly the pattern of `NavigationBuilderException` and `PermissionsBuilderException`: extend `GCException`, no additional methods, a docblock comment explaining the usage domain.
