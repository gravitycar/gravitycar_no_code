# Implementation Plan: CAT-02 — CacheRebuildOptions + CacheStepResult Value Objects

## Spec Context

These two value objects form the data contracts between every layer of the cache rebuild system.
`CacheRebuildOptions` carries the caller's intent into `AdminService::performCacheRebuild()`.
`CacheStepResult` carries the outcome of a single rebuild step through `AdminService` to the
`AdminAPIController` SSE stream and the CLI script. Neither class contains business logic.

Catalog item: CAT-02  
Specification section: Component 2 (CacheRebuildOptions); Component 3 (CacheRebuildResult — note
`CacheStepResult` is the per-step record used by both `CacheRebuildResult` and the SSE stream);
AC-15, AC-15a, AC-16, AC-17, AC-20, AC-24  
Acceptance criteria addressed:
- AC-15: `CacheRebuildOptions` has `dryRun`, `updateSchema`, `updatePermissions` booleans and a `components` array. Archiving is not configurable.
- AC-15a: If `components` is empty, construction throws `InvalidArgumentException` immediately.
- AC-16: Component values are validated against `CacheComponent::isValid()`.
- AC-17: `CacheRebuildOptions::all()` returns all four components, `updateSchema=true`, `updatePermissions=true`, `dryRun=false`.
- AC-20: Each step record has `status`, optional `errorMessage`, and `component`.
- AC-24: Each SSE event has `stepName`, `component`, `status`, `errorMessage`; final event has `done`, `success`, `message`.

---

## Dependencies

- **Blocked by**: CAT-01 (`CacheComponent` constants class — needed for `isValid()` validation and the `all()` constructor)
- **Blocks**: CAT-03 (`CacheRebuildResult` holds an array of `CacheStepResult`), CAT-05 (`CacheArchiver` emits `CacheStepResult`), CAT-06 (`CacheRebuilder` emits `CacheStepResult`), CAT-07 (`AdminService` consumes `CacheRebuildOptions` and produces `CacheStepResult`)
- **Uses**: `Gravitycar\Services\Admin\CacheComponent` (CAT-01)

---

## File Changes

### New Files
- `src/Services/Admin/CacheRebuildOptions.php` — immutable input value object for a cache rebuild operation
- `src/Services/Admin/CacheStepResult.php` — immutable per-step outcome record with SSE serialization

### Modified Files
- none

---

## Implementation Details

### CacheRebuildOptions

**File**: `src/Services/Admin/CacheRebuildOptions.php`

**Namespace**: `Gravitycar\Services\Admin`

**Properties** (all private, readonly):
- `array $components` — list of `CacheComponent` constant strings; must be non-empty
- `bool $updateSchema` — default `false`
- `bool $updatePermissions` — default `false`
- `bool $dryRun` — default `false`

**Design notes**:
- Final class; no logger or config — pure value object.
- Constructor validates `$components` immediately: throws `InvalidArgumentException` if empty.
- Each element of `$components` is validated via `CacheComponent::isValid()`; throws `InvalidArgumentException` for unknown values (message includes the bad value).
- Properties are `private readonly` to enforce immutability.
- The array of valid-component-check logic stays in the constructor; the constant values live in `CacheComponent`.

**Code Example**:

```php
<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

use InvalidArgumentException;

/**
 * CacheRebuildOptions
 *
 * Immutable value object that specifies which cache components to rebuild
 * and which secondary operations to perform. Passed to
 * AdminService::performCacheRebuild().
 *
 * Archiving is always performed before any files are cleared; it is not
 * a configurable option and is therefore not a property of this class.
 *
 * Usage:
 *   // Rebuild everything:
 *   $options = CacheRebuildOptions::all();
 *
 *   // Rebuild from API request body:
 *   $options = CacheRebuildOptions::fromArray($requestBody);
 *
 *   // Rebuild specific components only:
 *   $options = new CacheRebuildOptions(
 *       components: [CacheComponent::METADATA, CacheComponent::ROUTES],
 *       updateSchema: true
 *   );
 */
final class CacheRebuildOptions
{
    private readonly array $components;
    private readonly bool $updateSchema;
    private readonly bool $updatePermissions;
    private readonly bool $dryRun;

    public function __construct(
        array $components,
        bool $updateSchema = false,
        bool $updatePermissions = false,
        bool $dryRun = false
    ) {
        $this->validateComponents($components);
        $this->components        = $components;
        $this->updateSchema      = $updateSchema;
        $this->updatePermissions = $updatePermissions;
        $this->dryRun            = $dryRun;
    }

    /**
     * Returns options with all four components, updateSchema=true,
     * updatePermissions=true, dryRun=false.
     */
    public static function all(): self
    {
        return new self(
            components:        CacheComponent::all(),
            updateSchema:      true,
            updatePermissions: true,
            dryRun:            false
        );
    }

    /**
     * Constructs from an API request body or CLI parsed-options array.
     *
     * Expected keys:
     *   'components'        => string[] (required)
     *   'updateSchema'      => bool     (optional, default false)
     *   'updatePermissions' => bool     (optional, default false)
     *   'dryRun'            => bool     (optional, default false)
     *
     * @throws InvalidArgumentException if components is missing, empty, or
     *                                  contains an unknown value
     */
    public static function fromArray(array $data): self
    {
        return new self(
            components:        $data['components']        ?? [],
            updateSchema:      (bool)($data['updateSchema']      ?? false),
            updatePermissions: (bool)($data['updatePermissions'] ?? false),
            dryRun:            (bool)($data['dryRun']            ?? false)
        );
    }

    public function getComponents(): array       { return $this->components; }
    public function isUpdateSchema(): bool       { return $this->updateSchema; }
    public function isUpdatePermissions(): bool  { return $this->updatePermissions; }
    public function isDryRun(): bool             { return $this->dryRun; }

    /**
     * Validates that $components is non-empty and all values are known identifiers.
     *
     * @throws InvalidArgumentException
     */
    private function validateComponents(array $components): void
    {
        if (empty($components)) {
            throw new InvalidArgumentException(
                'CacheRebuildOptions: components array must not be empty.'
            );
        }
        foreach ($components as $component) {
            if (!CacheComponent::isValid($component)) {
                throw new InvalidArgumentException(
                    "CacheRebuildOptions: unknown component identifier '{$component}'."
                );
            }
        }
    }
}
```

**Key implementation notes**:
- `validateComponents()` is extracted to its own private method to keep the constructor simple and comply with single-responsibility.
- `fromArray()` passes raw `$data['components'] ?? []` to the constructor so the same validation fires in all construction paths.
- Named arguments in `new self(...)` calls make the intent readable.
- No logger, no config — this is a pure value object.

---

### CacheStepResult

**File**: `src/Services/Admin/CacheStepResult.php`

**Namespace**: `Gravitycar\Services\Admin`

**Properties** (all private, readonly):
- `string $stepName` — one of: `'archive'`, `'clear'`, `'rebuild'`, `'validate'`, `'schema_update'`, `'permissions_update'`, `'restore'`
- `string $component` — a `CacheComponent` constant, or `'all'` for archive-level steps
- `string $status` — one of: `'in_progress'`, `'success'`, `'failed'`, `'skipped'`
- `?string $errorMessage` — null unless status is `'failed'`

**Design notes**:
- Final class; no logger or config — pure value object.
- Construction is via named constructors only (the regular constructor is private to prevent
  invalid status strings from being set by callers).
- `toArray()` produces the JSON-serializable shape used by the SSE stream and `CacheRebuildResult`.
- Status strings are defined as private class constants to avoid typo drift between usages.
- The class does NOT validate that `$stepName` or `$component` are from a controlled list — the
  named constructors ensure they are passed in correctly by the callers (AdminService, CacheArchiver,
  CacheRebuilder).

**Code Example**:

```php
<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

/**
 * CacheStepResult
 *
 * Immutable record of a single cache rebuild step's outcome. Used by:
 *   - CacheArchiver (archive, restore steps)
 *   - CacheRebuilder (clear, rebuild, validate, schema_update, permissions_update steps)
 *   - AdminService (aggregates into CacheRebuildResult)
 *   - AdminAPIController (serialized as SSE events via toArray())
 *   - application-update.php (printed to STDOUT)
 *
 * All construction is via named constructors to enforce valid status values.
 */
final class CacheStepResult
{
    private const string STATUS_IN_PROGRESS = 'in_progress';
    private const string STATUS_SUCCESS     = 'success';
    private const string STATUS_FAILED      = 'failed';
    private const string STATUS_SKIPPED     = 'skipped';

    private readonly string $stepName;
    private readonly string $component;
    private readonly string $status;
    private readonly ?string $errorMessage;

    private function __construct(
        string $stepName,
        string $component,
        string $status,
        ?string $errorMessage
    ) {
        $this->stepName     = $stepName;
        $this->component    = $component;
        $this->status       = $status;
        $this->errorMessage = $errorMessage;
    }

    public static function inProgress(string $stepName, string $component): self
    {
        return new self($stepName, $component, self::STATUS_IN_PROGRESS, null);
    }

    public static function success(string $stepName, string $component): self
    {
        return new self($stepName, $component, self::STATUS_SUCCESS, null);
    }

    public static function failed(string $stepName, string $component, string $error): self
    {
        return new self($stepName, $component, self::STATUS_FAILED, $error);
    }

    public static function skipped(string $stepName, string $component): self
    {
        return new self($stepName, $component, self::STATUS_SKIPPED, null);
    }

    public function getStepName(): string      { return $this->stepName; }
    public function getComponent(): string     { return $this->component; }
    public function getStatus(): string        { return $this->status; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function isSuccess(): bool          { return $this->status === self::STATUS_SUCCESS; }
    public function isFailed(): bool           { return $this->status === self::STATUS_FAILED; }

    /**
     * Returns the array shape used for SSE JSON serialization and
     * CacheRebuildResult::toArray().
     *
     * Shape:
     *   {
     *     "stepName":     "archive",
     *     "component":    "all",
     *     "status":       "success",
     *     "errorMessage": null
     *   }
     */
    public function toArray(): array
    {
        return [
            'stepName'     => $this->stepName,
            'component'    => $this->component,
            'status'       => $this->status,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
```

**Key implementation notes**:
- The constructor is `private` — callers must use `inProgress()`, `success()`, `failed()`, or `skipped()`. This prevents creation of a result with an arbitrary status string.
- `isSuccess()` and `isFailed()` are helper predicates for `AdminService` and `CacheRebuildResult`.
- Status constants are `private const string` (PHP 8.2 typed constants), matching the project pattern.
- `toArray()` keys (`stepName`, `component`, `status`, `errorMessage`) match the SSE event format specified in AC-24 and the spec's API endpoint section.

---

## Error Handling

- `CacheRebuildOptions` constructor and `fromArray()` throw `InvalidArgumentException` (PHP built-in) for invalid input. Callers (`AdminAPIController::handleCacheRebuild()` and `AdminService::performCacheRebuild()`) catch this and return HTTP 400 or record the failure respectively.
- `CacheStepResult` has no error conditions — it IS the error representation.
- Neither class throws `AdminServiceException` — that is reserved for service-layer failures, not value-object validation.

---

## Unit Test Specifications

**Test file**: `tests/Unit/Services/Admin/CacheRebuildOptionsTest.php`

### `CacheRebuildOptions` — Constructor validation

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Empty components array | `new CacheRebuildOptions([])` | `InvalidArgumentException` thrown | AC-15a |
| Unknown component | `new CacheRebuildOptions(['unknown'])` | `InvalidArgumentException` with value in message | AC-16 |
| Mixed valid/invalid | `new CacheRebuildOptions(['metadata', 'bad'])` | `InvalidArgumentException` | AC-16 |
| All valid | `new CacheRebuildOptions(CacheComponent::all())` | No exception; `getComponents()` returns array | Happy path |
| Single valid | `new CacheRebuildOptions(['metadata'])` | No exception | Edge case |

### `CacheRebuildOptions` — Property defaults

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Default updateSchema | `new CacheRebuildOptions(['metadata'])` | `isUpdateSchema() === false` | Default |
| Default updatePermissions | `new CacheRebuildOptions(['metadata'])` | `isUpdatePermissions() === false` | Default |
| Default dryRun | `new CacheRebuildOptions(['metadata'])` | `isDryRun() === false` | Default |
| Override all flags | `new CacheRebuildOptions(['metadata'], true, true, true)` | All three getters return `true` | Constructor args |

### `CacheRebuildOptions::all()`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Components | (none) | Returns all four `CacheComponent` values | AC-17 |
| updateSchema | (none) | `isUpdateSchema() === true` | AC-17 |
| updatePermissions | (none) | `isUpdatePermissions() === true` | AC-17 |
| dryRun | (none) | `isDryRun() === false` | AC-17 |

### `CacheRebuildOptions::fromArray()`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Missing components key | `[]` | `InvalidArgumentException` (empty components) | AC-15a via constructor |
| Valid full body | `['components'=>['metadata'],'updateSchema'=>true,'updatePermissions'=>true,'dryRun'=>true]` | All getters correct | Happy path |
| Omitted boolean keys | `['components'=>['routes']]` | booleans default to false | Default handling |
| Unknown component in body | `['components'=>['invalid']]` | `InvalidArgumentException` | AC-16 |

---

**Test file**: `tests/Unit/Services/Admin/CacheStepResultTest.php`

### `CacheStepResult` — Named constructors

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| `inProgress()` status | `CacheStepResult::inProgress('archive', 'all')` | `getStatus() === 'in_progress'` | Named constructor |
| `success()` status | `CacheStepResult::success('clear', 'metadata')` | `getStatus() === 'success'` | Named constructor |
| `failed()` status | `CacheStepResult::failed('rebuild', 'routes', 'err')` | `getStatus() === 'failed'` | Named constructor |
| `skipped()` status | `CacheStepResult::skipped('validate', 'docs')` | `getStatus() === 'skipped'` | Named constructor |
| `failed()` errorMessage | `CacheStepResult::failed('rebuild', 'routes', 'boom')` | `getErrorMessage() === 'boom'` | Error carries message |
| `success()` errorMessage | `CacheStepResult::success('clear', 'metadata')` | `getErrorMessage() === null` | No error on success |
| `inProgress()` errorMessage | `CacheStepResult::inProgress('archive', 'all')` | `getErrorMessage() === null` | No error while in progress |

### `CacheStepResult` — `isSuccess()` and `isFailed()` predicates

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| `isSuccess()` on success | `CacheStepResult::success(...)` | `true` | Predicate |
| `isSuccess()` on failed | `CacheStepResult::failed(...)` | `false` | Predicate |
| `isFailed()` on failed | `CacheStepResult::failed(...)` | `true` | Predicate |
| `isFailed()` on success | `CacheStepResult::success(...)` | `false` | Predicate |
| `isFailed()` on skipped | `CacheStepResult::skipped(...)` | `false` | Predicate |

### `CacheStepResult::toArray()`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Keys present | `CacheStepResult::success('archive', 'all')->toArray()` | Has `stepName`, `component`, `status`, `errorMessage` keys | SSE format |
| Values match getters | Any result | `toArray()['status'] === getStatus()` etc. | Consistency |
| Error message in array | `CacheStepResult::failed('rebuild', 'routes', 'boom')` | `toArray()['errorMessage'] === 'boom'` | SSE error reporting |
| Null error message in array | `CacheStepResult::success('clear', 'metadata')` | `toArray()['errorMessage'] === null` | JSON null in SSE |

### Key Scenario: `fromArray()` → `CacheRebuildOptions` roundtrip

**Setup**: `$data = ['components' => ['metadata', 'routes'], 'updateSchema' => true, 'updatePermissions' => false, 'dryRun' => true]`  
**Action**: `$opts = CacheRebuildOptions::fromArray($data)`  
**Expected**:
- `$opts->getComponents() === ['metadata', 'routes']`
- `$opts->isUpdateSchema() === true`
- `$opts->isUpdatePermissions() === false`
- `$opts->isDryRun() === true`  
**Why**: Confirms `fromArray()` faithfully parses all four fields without loss.

### Key Scenario: Invalid component in `fromArray()`

**Setup**: `$data = ['components' => ['metadata', 'permissions']]` (where `'permissions'` is not a `CacheComponent`)  
**Action**: `CacheRebuildOptions::fromArray($data)`  
**Expected**: Throws `InvalidArgumentException` with `'permissions'` in the message  
**Why**: The API controller must reject unknown component names with HTTP 400 (AC-16).

---

## Notes

- `CacheStepResult` is used both in `CacheRebuildResult` (the final outcome) and in the SSE stream via the `$onStep` callback in `CacheRebuilder::rebuild()`. The `toArray()` method produces the exact shape emitted in each `data:` SSE line.
- The `'all'` component string (used for the archive and restore steps) is a literal — not a `CacheComponent` constant. This is intentional: the archive and restore steps span all components and have no single-component identity.
- The `'restore'` step name is used by `AdminService` in the catch block when restoring the archive after a clear/rebuild/validate failure. It is a valid step name in `CacheStepResult` and is emitted as a visible SSE event. `CacheRebuildResult::isSuccess()` returns `false` when the named constructor was called as `failure()` (explicit `$success=false`) AND/OR when any step's `isFailed()` is `true` — specifically, `failure([], 'msg')` produces `isSuccess()===false` because the explicit flag is checked alongside `hasFailures()`.
- `CacheRebuildOptions` uses PHP 8.2 `private readonly` properties for true immutability.
- Both classes are `final` — they are concrete value objects, not extension points.
- Both classes are under 100 lines and well within the 300-line file limit.
- The `fromArray()` method casts boolean-ish values with `(bool)` to handle JSON `true`/`false` decoded by PHP's `json_decode()` which produces actual `bool` — no special handling needed, but the cast is defensive for string inputs from the CLI.
