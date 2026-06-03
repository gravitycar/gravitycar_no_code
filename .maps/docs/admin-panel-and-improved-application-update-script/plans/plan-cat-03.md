# Implementation Plan: CAT-03 — CacheRebuildResult Value Object

## Spec Context

`CacheRebuildResult` is the return type of `AdminService::performCacheRebuild()`. It carries
the aggregate outcome of the entire cache rebuild operation — success or failure — together
with an ordered list of `CacheStepResult` records for every phase that was attempted. It is
consumed by `AdminAPIController` (to emit the final `done` SSE event) and by
`scripts/application-update.php` (to print per-step output and determine the exit code).

Catalog item: CAT-03  
Specification section: Component 3 (CacheRebuildResult); AC-18, AC-19, AC-20, AC-24, AC-32a  
Acceptance criteria addressed:
- AC-18: `isSuccess()` returns `false` if any step has `failed` status.
- AC-19: Exposes per-step status records for archive, clear, rebuild, validate, schema update, and permissions update.
- AC-20: Each step record has `status`, optional `errorMessage`, and `component` (fulfilled by `CacheStepResult` from CAT-02).
- AC-24: Final SSE event carries `done: true`, `success: bool`, `message: string`, `steps: [...]`.
- AC-32a: On receipt of the `done` event, the modal transitions to result view; the final event shape is `{done: true, success: bool, message: string}`.

---

## Dependencies

- **Blocked by**: CAT-02 (`CacheStepResult` — `CacheRebuildResult` holds an array of these)
- **Blocks**: CAT-07 (`AdminService` returns `CacheRebuildResult` from `performCacheRebuild()`)
- **Uses**: `Gravitycar\Services\Admin\CacheStepResult` (CAT-02)

---

## File Changes

### New Files
- `src/Services/Admin/CacheRebuildResult.php` — immutable aggregate outcome value object

### Modified Files
- none

---

## Implementation Details

### CacheRebuildResult

**File**: `src/Services/Admin/CacheRebuildResult.php`

**Namespace**: `Gravitycar\Services\Admin`

**Design notes**:
- Final class; no logger or config — pure value object.
- The class has two construction paths: `success(array $steps)` and `failure(array $steps, string $message)` named constructors. The regular constructor is private.
- `addStep(CacheStepResult $step): void` is a mutating method to support incremental step accumulation during streaming. While the object is technically mutable in this one dimension, it is never shared across threads and is used in a strict append-only pattern by `AdminService`.
- `hasFailures(): bool` iterates `$steps` and returns `true` if any step's `isFailed()` is `true`.
- `isSuccess(): bool` returns `$this->success && !$this->hasFailures()` — BOTH the explicit constructor-set flag AND the absence of failed steps must be true. This means `failure([], 'msg')` correctly returns `isSuccess()===false` even with no steps (because `$success` was set to `false` by `failure()`).
- `toArray(): array` produces the final `done` SSE event payload shape.

**Properties** (all private):
- `bool $success` — set at construction time; `true` for the success named constructor, `false` for failure
- `string $message` — human-readable summary; default `''` for the success path, required for the failure path
- `CacheStepResult[] $steps` — ordered list of step results

**Named constructors**:

```php
public static function success(array $steps): self
public static function failure(array $steps, string $message): self
```

**Public interface**:

```php
public function addStep(CacheStepResult $step): void
public function isSuccess(): bool
public function hasFailures(): bool
public function getSteps(): array
public function getMessage(): string
public function toArray(): array
```

**`toArray()` output shape** (used for the final `done` SSE event and JSON API body):

```json
{
  "done": true,
  "success": true,
  "message": "Cache rebuild completed successfully.",
  "steps": [
    { "stepName": "archive", "component": "all", "status": "success", "errorMessage": null },
    { "stepName": "clear",   "component": "metadata", "status": "success", "errorMessage": null }
  ]
}
```

The `done: true` key is always present in `toArray()` — it is the signal the frontend SSE reader
uses to detect the final event and transition to the result view.

**Code Example**:

```php
<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

/**
 * CacheRebuildResult
 *
 * Aggregate outcome of AdminService::performCacheRebuild(). Holds the
 * overall success flag, a human-readable summary message, and an ordered
 * list of CacheStepResult objects for every phase that was attempted.
 *
 * Construction:
 *   // All steps succeeded:
 *   $result = CacheRebuildResult::success($steps);
 *
 *   // A failure occurred (archive was restored):
 *   $result = CacheRebuildResult::failure($steps, 'Cache rebuild failed. Archive restored.');
 *
 *   // Build incrementally during AdminService processing:
 *   $result = CacheRebuildResult::success([]);
 *   $result->addStep(CacheStepResult::success('archive', 'all'));
 *   ...
 *   if ($result->hasFailures()) { ... }
 *
 * Consumed by:
 *   - AdminAPIController::handleCacheRebuild() — emits toArray() as the final SSE event
 *   - scripts/application-update.php — iterates getSteps() for STDOUT output; checks isSuccess() for exit code
 */
final class CacheRebuildResult
{
    /** @var CacheStepResult[] */
    private array $steps;
    private bool $success;
    private string $message;

    private function __construct(bool $success, string $message, array $steps)
    {
        $this->success = $success;
        $this->message = $message;
        $this->steps   = $steps;
    }

    /**
     * Creates a result for a fully successful rebuild.
     *
     * @param CacheStepResult[] $steps
     */
    public static function success(array $steps): self
    {
        return new self(true, 'Cache rebuild completed successfully.', $steps);
    }

    /**
     * Creates a result for a failed rebuild (archive was restored).
     *
     * @param CacheStepResult[] $steps
     */
    public static function failure(array $steps, string $message): self
    {
        return new self(false, $message, $steps);
    }

    /**
     * Appends a step result. Called by AdminService as each phase
     * completes during the rebuild sequence.
     */
    public function addStep(CacheStepResult $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Returns true only if the named constructor set success=true AND no step
     * has a 'failed' status. Both conditions must hold.
     *
     * This means failure([], 'msg') returns false even with no failed steps,
     * because the explicit $success flag is checked first.
     */
    public function isSuccess(): bool
    {
        return $this->success && !$this->hasFailures();
    }

    /**
     * Returns true if any step has a 'failed' status.
     */
    public function hasFailures(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->isFailed()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the ordered list of step results.
     *
     * @return CacheStepResult[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Returns the human-readable summary message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the serializable array shape for the final SSE 'done' event
     * and the JSON API response body.
     *
     * Shape:
     *   {
     *     "done":    true,
     *     "success": bool,
     *     "message": string,
     *     "steps":   [ { stepName, component, status, errorMessage }, ... ]
     *   }
     */
    public function toArray(): array
    {
        return [
            'done'    => true,
            'success' => $this->isSuccess(),
            'message' => $this->message,
            'steps'   => array_map(
                fn(CacheStepResult $s) => $s->toArray(),
                $this->steps
            ),
        ];
    }
}
```

**Key implementation notes**:

- `isSuccess()` returns `$this->success && !$this->hasFailures()`. This dual check provides two independent guards: (1) the constructor-time flag (`success()` sets `true`, `failure()` sets `false`) catches the case where `failure([], 'msg')` has no failed steps but should still report failure; (2) `hasFailures()` catches the case where `success([])` has a failed step added via `addStep()`.
- The `$success` property is set by the named constructors and is never changed after construction. `addStep()` does not modify it — so `isSuccess()` correctly detects a deteriorating result (success constructed → failed step added → `isSuccess()` now false) via the `hasFailures()` scan.
- `addStep()` is the only mutating operation. It is used by `AdminService` to build the result incrementally so `AdminAPIController` can emit SSE events for each step as they complete.
- `toArray()` always emits `done: true` — this is the frontend's trigger to close the stream and transition to the result view.
- `getMessage()` returns `$this->message`, which is set at construction. For the success path, the default message is `'Cache rebuild completed successfully.'`. For the failure path, the message is passed explicitly (e.g., `'Cache rebuild failed. Archive restored.'`).
- The `array_map` in `toArray()` preserves the original insertion order of `$steps`.
- Class is under 100 lines; well within the 300-line file limit.

---

## Error Handling

- `CacheRebuildResult` has no error conditions of its own — it IS the error representation container.
- It does not throw `AdminServiceException` or any other exception.
- Invalid construction is prevented by the private constructor and two named constructors — there is no way to create an instance without `success()` or `failure()`.

---

## Unit Test Specifications

**Test file**: `tests/Unit/Services/Admin/CacheRebuildResultTest.php`

### Named constructors

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| `success()` with no steps | `CacheRebuildResult::success([])` | `isSuccess() === true`, `getSteps() === []` | Empty success |
| `success()` with steps | Steps all have `success` status | `isSuccess() === true` | Happy path |
| `failure()` sets message | `CacheRebuildResult::failure([], 'Archive restored.')` | `getMessage() === 'Archive restored.'` | Failure message preserved |
| `failure()` with no steps | `CacheRebuildResult::failure([], 'msg')` | `isSuccess() === false` (explicit $success flag is false), `hasFailures() === false` | Named constructor sets $success=false; isSuccess() checks both flag AND steps |
| `success()` default message | `CacheRebuildResult::success([])` | `getMessage() === 'Cache rebuild completed successfully.'` | Default message |

### `addStep()` — incremental construction

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Add one step | `$r->addStep(CacheStepResult::success('archive', 'all'))` | `count($r->getSteps()) === 1` | Step appended |
| Add multiple steps | Add 3 steps | `count($r->getSteps()) === 3` | Ordered accumulation |
| Add failed step to success result | `$r = CacheRebuildResult::success([]); $r->addStep(CacheStepResult::failed('clear', 'metadata', 'err'))` | `$r->isSuccess() === false` | Dynamic failure detection |
| Step order preserved | Add steps A, B, C | `getSteps()[0]` is A, `[1]` is B, `[2]` is C | FIFO order |

### `isSuccess()` and `hasFailures()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| All success steps | 3 success steps | `isSuccess() === true`, `hasFailures() === false` | All passed |
| One failed step | 2 success + 1 failed | `isSuccess() === false`, `hasFailures() === true` | Any failure = overall failure |
| All skipped | 3 skipped steps | `isSuccess() === true`, `hasFailures() === false` | Skipped != failed |
| Mix of skipped and success | 2 success + 1 skipped | `isSuccess() === true` | No failures |
| Empty steps | `CacheRebuildResult::success([])` | `isSuccess() === true`, `hasFailures() === false` | Vacuously true |
| in_progress step | 1 in_progress step | `isSuccess() === true`, `hasFailures() === false` | in_progress != failed |

### `toArray()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| `done` key always present | Any result | `toArray()['done'] === true` | Frontend stream detection |
| `success` key reflects `isSuccess()` | Failed step added | `toArray()['success'] === false` | Must match |
| `message` key present | `failure($steps, 'msg')` | `toArray()['message'] === 'msg'` | Failure message passed through |
| `steps` key is array | 2 steps added | `count(toArray()['steps']) === 2` | All steps serialized |
| Each step shape | `success` step added | `toArray()['steps'][0]` has `stepName`, `component`, `status`, `errorMessage` | Delegates to `CacheStepResult::toArray()` |
| Empty steps array | No steps | `toArray()['steps'] === []` | Empty array valid |

### Key Scenario: AdminService build pattern

**Setup**: Start with `CacheRebuildResult::success([])`, then add steps incrementally via `addStep()`:

```php
$result = CacheRebuildResult::success([]);
$result->addStep(CacheStepResult::success('archive', 'all'));
$result->addStep(CacheStepResult::success('clear', 'metadata'));
$result->addStep(CacheStepResult::failed('rebuild', 'metadata', 'MetadataEngine error'));
```

**Expected**:
- `$result->hasFailures() === true`
- `$result->isSuccess() === false`
- `count($result->getSteps()) === 3`
- `$result->toArray()['success'] === false`
- `$result->toArray()['steps'][2]['status'] === 'failed'`
- `$result->toArray()['steps'][2]['errorMessage'] === 'MetadataEngine error'`

**Why**: Confirms the incremental build pattern used by `AdminService` works correctly, and that a single failed step flips the overall success flag in `toArray()`.

### Key Scenario: failure() message propagation

**Setup**: `$result = CacheRebuildResult::failure([], 'Cache rebuild failed. Archive restored.')`  
**Action**: `$array = $result->toArray()`  
**Expected**: `$array['message'] === 'Cache rebuild failed. Archive restored.'`  
**Why**: The `AdminAPIController` uses `toArray()` to emit the final SSE event. The human-readable message must survive the serialization path unchanged.

---

## Notes

- `toArray()` always emits `done: true` as the first key. This makes `JSON.parse(data)['done']` the reliable frontend detection signal without needing to check for the absence of a `stepName` key.
- `isSuccess()` checks BOTH the constructor-set `$success` flag AND `!$this->hasFailures()`. This dual check means: (1) `failure([], 'msg')` returns `isSuccess()===false` even with no failed steps (because `$success=false`); (2) `success([])` with a later `addStep(CacheStepResult::failed(...))` still returns `false` (because `hasFailures()` is true). The `$success` flag is the constructor's explicit intent; the step-scan is the runtime safety net.
- The `addStep()` mutability is acceptable here because `CacheRebuildResult` is a single-threaded, short-lived object produced and consumed within one HTTP request or one CLI invocation. There is no concurrency risk.
- `final class` — not an extension point.
- Do not add a logger or config to this class. It is a pure value object.
