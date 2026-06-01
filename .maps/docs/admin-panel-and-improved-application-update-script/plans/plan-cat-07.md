# Implementation Plan: CAT-07 — AdminService Orchestration Class

## Spec Context

`AdminService` is the top-level orchestrator for the cache rebuild lifecycle. It coordinates the
four phases — archive → clear → rebuild → validate — by delegating each phase to `CacheArchiver`
or `CacheRebuilder`. It wires per-step progress callbacks into a `CacheRebuildResult` that both
the SSE controller and the CLI script consume. `AdminService` performs no direct file I/O; it
only sequences the phases, handles failures, and emits steps.

Catalog item: CAT-07  
Specification section: Component 4a (AdminService); AC-1 through AC-4, AC-10 through AC-14b  
Acceptance criteria addressed:
- AC-1: `performCacheRebuild()` accepts `CacheRebuildOptions` and returns `CacheRebuildResult`.
- AC-2: Always delegates to `CacheArchiver::archive()` before any files are cleared.
- AC-3: Archiving throws `AdminServiceException` if the archive is invalid (handled in `CacheArchiver`; propagated here and recorded as failure).
- AC-4: On ANY failure in clear/rebuild/validate — stops immediately, restores archive, records failure.
- AC-10: Delegates restore and delete to `CacheArchiver`; records failure in result; does NOT re-throw.
- AC-11: Uses `ContainerConfig::getContainer()` exclusively. No `ServiceLocator`, no `ReflectionClass`.
- AC-12: Has `$logger` property; logs start, completion, and errors per step.
- AC-13: Has `$config` property.
- AC-14: File does not exceed 300 lines.
- AC-14a: On construction, calls `CacheArchiver::findStaleArchives()` and logs a warning per stale file.
- AC-14b: `CacheArchiver::deleteArchive()` is called after validation success AND after restore.

---

## Dependencies

- **Blocked by**: CAT-03 (`CacheRebuildResult` — return type of `performCacheRebuild()`)
- **Blocked by**: CAT-05 (`CacheArchiver` — archive/restore/delete/findStaleArchives)
- **Blocked by**: CAT-06 (`CacheRebuilder` — clear/rebuild/validate, including the `$onStep` callback)
- **Blocks**: CAT-08 (`ContainerConfig` registration of `AdminService`)
- **Uses**:
  - `Gravitycar\Services\Admin\CacheArchiver` (CAT-05)
  - `Gravitycar\Services\Admin\CacheRebuilder` (CAT-06)
  - `Gravitycar\Services\Admin\CacheRebuildOptions` (CAT-02)
  - `Gravitycar\Services\Admin\CacheRebuildResult` (CAT-03)
  - `Gravitycar\Services\Admin\CacheStepResult` (CAT-02)
  - `Gravitycar\Exceptions\AdminServiceException` (CAT-04)
  - `Gravitycar\Core\Config`
  - `Monolog\Logger`

---

## File Changes

### New Files
- `src/Services/Admin/AdminService.php` — orchestration class; coordinates archive → clear → rebuild → validate → delete lifecycle

### Modified Files
- none (CAT-08 handles `ContainerConfig` registration)

---

## Implementation Details

### AdminService

**File**: `src/Services/Admin/AdminService.php`

**Namespace**: `Gravitycar\Services\Admin`

**Properties**:
- `private Logger $logger` — Monolog logger instance
- `private Config $config` — Config instance
- `private CacheArchiver $archiver` — handles all tar operations
- `private CacheRebuilder $rebuilder` — handles all cache file operations

**Constructor**:

```php
public function __construct(
    Logger $logger,
    Config $config,
    CacheArchiver $archiver,
    CacheRebuilder $rebuilder
) {
    $this->logger    = $logger;
    $this->config    = $config;
    $this->archiver  = $archiver;
    $this->rebuilder = $rebuilder;
    $this->scanAndLogStaleArchives();
}
```

All four constructor parameters are **required** — no null defaults. `ContainerConfig` always
provides pre-built instances of `CacheArchiver` and `CacheRebuilder` (see CAT-08). The null-coalescing
pattern from the spec has been superseded: both collaborators are now registered in `ContainerConfig`
and injected as concrete instances, which eliminates the "internally instantiated with what args?"
ambiguity and makes all dependencies explicit and testable.

**Initialization — `scanAndLogStaleArchives()` (private)**:

Called at the end of the constructor. Calls `$this->archiver->findStaleArchives()` and logs
a `warning` for each path returned. Does not auto-delete them.

```php
private function scanAndLogStaleArchives(): void
{
    $staleArchives = $this->archiver->findStaleArchives();
    foreach ($staleArchives as $archiveFilePath) {
        $this->logger->warning('Stale cache archive found — manual cleanup may be required', [
            'archiveFilePath' => $archiveFilePath,
        ]);
    }
}
```

---

### Method: `performCacheRebuild(CacheRebuildOptions $options, callable $onStep): CacheRebuildResult`

The primary public method. Sequences all phases and delegates to `CacheArchiver` and
`CacheRebuilder`. The `$onStep` callback signature is `callable(CacheStepResult): void` and
is used to emit progress in real time to the SSE stream or CLI output.

**Dry-run path**:

When `$options->isDryRun()` is `true`, ALL steps are skipped — no files are created, modified,
or deleted. Log what would happen for each step. Return a `CacheRebuildResult` with all steps
recorded as `skipped`.

**Normal execution path**:

1. Throw `InvalidArgumentException` immediately if `$options->getComponents()` is empty.
2. Create `$result = CacheRebuildResult::success([])` to accumulate steps.
3. **Archive phase**: emit `in_progress('archive','all')`, attempt `$this->archiver->archive()`. On exception: emit `failed('archive','all', $e->getMessage())`, emit `done:false` result, call `exit(0)` (SSE headers already set). On success: emit `success('archive','all')`. Store `$archivePath`.
4. **Clear/rebuild/validate phases**: wrapped in a single `try/catch`. On ANY exception:
   - Emit a `failed` step for whichever step threw.
   - Emit `in_progress('restore','all')` via `$onStep`.
   - Call `$this->archiver->restore($archivePath)`.
   - Emit `success('restore','all')` or `failed('restore','all', $msg)` depending on whether restore succeeds.
   - Call `$this->archiver->deleteArchive($archivePath)`.
   - Add a failure step to `$result`.
   - Return `$result` (do NOT re-throw).
5. **Clear phase**: call `$this->rebuilder->clear($options->getComponents())` — emit `in_progress`/`success` steps per component.
6. **Rebuild phase**: call `$this->rebuilder->rebuild($options->getComponents(), $options->isUpdateSchema(), $options->isUpdatePermissions(), $onStep)` — `CacheRebuilder` fires `$onStep` internally for each sub-step.
7. **Validate phase**: call `$this->rebuilder->validate($options->getComponents())` — emit `in_progress`/`success` steps per component.
8. **Delete archive**: call `$this->archiver->deleteArchive($archivePath)`.
9. Return `$result`.

**Code Example**:

```php
public function performCacheRebuild(CacheRebuildOptions $options, callable $onStep): CacheRebuildResult
{
    if (empty($options->getComponents())) {
        throw new \InvalidArgumentException('No components specified for cache rebuild.');
    }

    if ($options->isDryRun()) {
        return $this->performDryRun($options, $onStep);
    }

    $result = CacheRebuildResult::success([]);
    $archivePath = $this->runArchivePhase($result, $onStep);

    try {
        $this->runClearPhase($options, $result, $onStep);
        $this->rebuilder->rebuild(
            $options->getComponents(),
            $options->isUpdateSchema(),
            $options->isUpdatePermissions(),
            function (CacheStepResult $step) use ($result, $onStep): void {
                $result->addStep($step);
                $onStep($step);
            }
        );
        $this->runValidatePhase($options, $result, $onStep);
    } catch (\Throwable $e) {
        $this->logger->error('Cache rebuild failed — restoring archive', [
            'error'       => $e->getMessage(),
            'archivePath' => $archivePath,
        ]);
        $this->runRestorePhase($archivePath, $result, $onStep);
        $this->archiver->deleteArchive($archivePath);
        return $result;
    }

    $this->archiver->deleteArchive($archivePath);
    return $result;
}
```

**Private phase helpers**:

```php
private function runArchivePhase(CacheRebuildResult $result, callable $onStep): string
{
    $step = CacheStepResult::inProgress('archive', 'all');
    $result->addStep($step);
    $onStep($step);

    try {
        $archivePath = $this->archiver->archive();
    } catch (\Throwable $e) {
        $this->logger->error('Archive phase failed', ['error' => $e->getMessage()]);
        $failStep = CacheStepResult::failed('archive', 'all', $e->getMessage());
        $result->addStep($failStep);
        $onStep($failStep);

        // SSE headers already set; emit done:false and terminate
        $failureResult = CacheRebuildResult::failure($result->getSteps(), 'Cache rebuild failed: archive creation error.');
        $onStep(CacheStepResult::failed('archive', 'all', $e->getMessage())); // emit done event via caller
        // Signal caller to emit final done:false event and exit
        throw new \RuntimeException('archive_failed:' . $e->getMessage(), 0, $e);
    }

    $step = CacheStepResult::success('archive', 'all');
    $result->addStep($step);
    $onStep($step);

    return $archivePath;
}
```

**Note on archive failure flow**: `runArchivePhase()` re-throws so that `performCacheRebuild()`'s
outer caller (`AdminAPIController::handleCacheRebuild()`) can catch it, emit the final `done:false`
SSE event (using `$result->toArray()`), and call `exit(0)`. The SSE headers are set before
`performCacheRebuild()` is called. No restore is attempted because the archive never completed.

```php

private function runClearPhase(CacheRebuildOptions $options, CacheRebuildResult $result, callable $onStep): void
{
    foreach ($options->getComponents() as $component) {
        $step = CacheStepResult::inProgress('clear', $component);
        $result->addStep($step);
        $onStep($step);
    }

    $this->rebuilder->clear($options->getComponents());

    foreach ($options->getComponents() as $component) {
        $step = CacheStepResult::success('clear', $component);
        $result->addStep($step);
        $onStep($step);
    }
}

private function runValidatePhase(CacheRebuildOptions $options, CacheRebuildResult $result, callable $onStep): void
{
    foreach ($options->getComponents() as $component) {
        $step = CacheStepResult::inProgress('validate', $component);
        $result->addStep($step);
        $onStep($step);
    }

    $this->rebuilder->validate($options->getComponents());

    foreach ($options->getComponents() as $component) {
        $step = CacheStepResult::success('validate', $component);
        $result->addStep($step);
        $onStep($step);
    }
}

private function runRestorePhase(string $archivePath, CacheRebuildResult $result, callable $onStep): void
{
    $step = CacheStepResult::inProgress('restore', 'all');
    $result->addStep($step);
    $onStep($step);

    try {
        $this->archiver->restore($archivePath);
        $step = CacheStepResult::success('restore', 'all');
    } catch (\Throwable $restoreEx) {
        $this->logger->error('Restore failed', ['error' => $restoreEx->getMessage()]);
        $step = CacheStepResult::failed('restore', 'all', $restoreEx->getMessage());
    }

    $result->addStep($step);
    $onStep($step);
}
```

**Note on clear phase step events**: The clear phase is now per-component interleaved:
for each component, emit `in_progress('clear', $component)`, call the clear operation, then
emit `success('clear', $component)`. This matches the spec's SSE event sequence example exactly
(each component in_progress immediately followed by its success). `runClearPhase()` calls
`$this->rebuilder->clear()` one component at a time (not all at once) so that progress events
interleave correctly. If `clear()` throws for a component, the exception propagates to the
outer `try/catch` in `performCacheRebuild()`, which triggers restore.

Updated `runClearPhase()`:

```php
private function runClearPhase(CacheRebuildOptions $options, CacheRebuildResult $result, callable $onStep): void
{
    foreach ($options->getComponents() as $component) {
        $step = CacheStepResult::inProgress('clear', $component);
        $result->addStep($step);
        $onStep($step);

        $this->rebuilder->clear([$component]);

        $step = CacheStepResult::success('clear', $component);
        $result->addStep($step);
        $onStep($step);
    }
}
```

**Dry-run path — `performDryRun()`**:

```php
private function performDryRun(CacheRebuildOptions $options, callable $onStep): CacheRebuildResult
{
    $this->logger->info('Dry run — no files will be modified', [
        'components' => $options->getComponents(),
    ]);

    $result = CacheRebuildResult::success([]);
    $steps  = $this->buildDryRunSteps($options);

    foreach ($steps as $step) {
        $result->addStep($step);
        $onStep($step);
        $this->logger->info('Dry run step (skipped)', $step->toArray());
    }

    return $result;
}

private function buildDryRunSteps(CacheRebuildOptions $options): array
{
    $steps      = [];
    $components = $options->getComponents();

    $steps[] = CacheStepResult::skipped('archive', 'all');

    foreach ($components as $component) {
        $steps[] = CacheStepResult::skipped('clear', $component);
    }
    foreach ($components as $component) {
        $steps[] = CacheStepResult::skipped('rebuild', $component);
    }
    if (in_array('metadata', $components, strict: true)) {
        if ($options->isUpdateSchema()) {
            $steps[] = CacheStepResult::skipped('schema_update', 'metadata');
        }
        if ($options->isUpdatePermissions()) {
            $steps[] = CacheStepResult::skipped('permissions_update', 'metadata');
        }
    }
    foreach ($components as $component) {
        $steps[] = CacheStepResult::skipped('validate', $component);
    }

    return $steps;
}
```

Note: `buildDryRunSteps()` returns an array so the constant is defined outside method calls;
this satisfies the CLAUDE.md rule that arrays used only in a method should be constants — however
since this array is built dynamically from `$options`, it cannot be a compile-time constant. The
private method extraction is the appropriate equivalent pattern here.

---

## Error Handling

| Condition | Action |
|-----------|--------|
| `$options->getComponents()` is empty | Throw `\InvalidArgumentException` immediately (before any file I/O) |
| `CacheArchiver::archive()` throws | Caught inside `runArchivePhase()`. Emits `failed('archive','all', $e->getMessage())` via `$onStep`, then emits a `done:false` result, then calls `exit(0)`. Does NOT propagate. The archive never existed so no restore is needed. `AdminAPIController` has already set SSE headers before calling `performCacheRebuild()`, so emitting a `done:false` SSE event is the correct error surface. |
| Any exception from `clear()`, `rebuild()`, or `validate()` | Caught by the single `try/catch` in `performCacheRebuild()`; triggers restore + delete + returns failure result |
| `CacheArchiver::restore()` throws inside the catch block | Log the error; the `deleteArchive()` call is still attempted. The result still records the original failure. |
| `CacheArchiver::deleteArchive()` fails | `deleteArchive()` is non-fatal (per CAT-05 plan); it logs a warning internally. |

**Important**: The archive phase (`runArchivePhase()`) is called OUTSIDE the try/catch block.
If archiving itself fails (e.g., disk full), the exception propagates to the caller.
There is no archive to restore at that point. The caller (`AdminAPIController` or CLI script)
handles this case by receiving the exception and emitting an appropriate error.

---

## Unit Test Specifications

**Test file**: `tests/Unit/Services/Admin/AdminServiceTest.php`

**Setup**: Mock `CacheArchiver`, `CacheRebuilder`, `Logger`, and `Config`. Inject the mocks
via constructor parameters — all four are required (no null defaults). No container priming
needed.

```php
$logger    = $this->createMock(Logger::class);
$config    = $this->createMock(Config::class);
$archiver  = $this->createMock(CacheArchiver::class);
$rebuilder = $this->createMock(CacheRebuilder::class);

// findStaleArchives() is called on construction — stub it to return []
$archiver->method('findStaleArchives')->willReturn([]);

$service = new AdminService($logger, $config, $archiver, $rebuilder);
```

### Constructor — stale archive scan

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| No stale archives | `findStaleArchives()` returns `[]` | No warning logged | AC-14a — no stale files |
| One stale archive | Returns `['/app/cache_old.tar']` | Logger `warning()` called once with path | AC-14a |
| Multiple stale archives | Returns 2 paths | Logger `warning()` called twice | AC-14a |

### `performCacheRebuild()` — empty components guard

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Empty components | `$options->getComponents()` returns `[]` | Throws `InvalidArgumentException` | AC-15a |
| Non-empty components | `['metadata']` | No exception from guard | Happy path |

### `performCacheRebuild()` — dry run

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Dry run returns skipped result | `isDryRun()=true`, all four components | Returns result with all steps `skipped` | AC-39 |
| Archive skipped in dry run | `isDryRun()=true` | `$archiver->archive()` NOT called | Dry run = no file I/O |
| clear/rebuild/validate not called | `isDryRun()=true` | `$rebuilder->clear/rebuild/validate()` NOT called | Dry run = no file I/O |
| `$onStep` receives skipped steps | `isDryRun()=true` | Callback receives `CacheStepResult` with `skipped` status | Streaming dry-run output |

### `performCacheRebuild()` — happy path

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Archive called first | All mocks succeed | `archive()` called before `clear()` | AC-2 |
| Clear called with components | components=['metadata','routes'] | `clear(['metadata','routes'])` called | Correct delegation |
| Rebuild called with all flags | `updateSchema=true`, `updatePermissions=true` | `rebuild(components, true, true, $onStep)` called | AC-7, AC-8 |
| Validate called with components | All succeed | `validate(['metadata','routes'])` called | AC-9 |
| deleteArchive called on success | All succeed | `deleteArchive($archivePath)` called once | AC-14b |
| Returns success result | All succeed | `$result->isSuccess() === true` | AC-1 |
| $onStep receives archive steps | All succeed | Callback receives in_progress then success for archive | Streaming support |
| $onStep receives clear steps | All succeed | Callback receives in_progress/success per component | Streaming support |

### `performCacheRebuild()` — failure and restore

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| clear() throws → restore triggered | `clear()` throws `AdminServiceException` | `restore($archivePath)` called | AC-4 |
| rebuild() throws → restore triggered | `rebuild()` throws generic `\Exception` | `restore($archivePath)` called | AC-4 |
| validate() throws → restore triggered | `validate()` throws `AdminServiceException` | `restore($archivePath)` called | AC-4 |
| deleteArchive called after restore | `clear()` throws | `deleteArchive($archivePath)` called after restore | AC-14b |
| Result returned (not re-thrown) | `clear()` throws | `performCacheRebuild()` returns result, does NOT re-throw | AC-10 |
| Result has failure step | `rebuild()` throws | `$result->hasFailures() === true` | AC-10 |
| Archive phase exception propagates | `archive()` throws | Exception propagates; restore NOT called | No archive to restore |

### Key Scenario: Full successful rebuild

**Setup**: Mock `archiver->archive()` returns `'/app/cache_2026_06_01.tar'`. `rebuilder->clear()`,
`rebuild()`, `validate()` all succeed. `archiver->deleteArchive()` is a no-op.

**Action**: Call `performCacheRebuild(CacheRebuildOptions::all(), $captureSteps)` where
`$captureSteps` is a closure that appends to a `$received` array.

**Expected**:
- `archive()` called once before `clear()`
- `clear()` called with all four components
- `rebuild()` called with all four components, `updateSchema=true`, `updatePermissions=true`
- `validate()` called with all four components
- `deleteArchive('/app/cache_2026_06_01.tar')` called once
- Returned result `isSuccess() === true`
- `$received` contains at least: `archive/in_progress`, `archive/success`, then clear/rebuild/validate steps

**Why**: Validates the happy-path orchestration order specified in the spec (archive → clear →
rebuild → validate → delete).

### Key Scenario: Rebuild failure triggers restore

**Setup**: `archiver->archive()` returns `'/app/cache_2026_06_01.tar'`. `rebuilder->clear()`
succeeds. `rebuilder->rebuild()` throws `new \RuntimeException('Engine error')`.

**Action**: Call `performCacheRebuild($options, $onStep)`.

**Expected**:
- `archiver->restore('/app/cache_2026_06_01.tar')` called once
- `archiver->deleteArchive('/app/cache_2026_06_01.tar')` called once
- Returned `$result->isSuccess() === false`
- `$result->hasFailures() === true`
- Method returns (does NOT re-throw the exception)

**Why**: Validates AC-4 and AC-10 — the fail-fast / restore / return pattern.

---

## Notes

- The `$onStep` callback for the rebuild phase is wrapped inside `performCacheRebuild()` so that
  each step emitted by `CacheRebuilder::rebuild()` is both added to `$result` AND forwarded to
  the caller's `$onStep`. This keeps `CacheRebuilder` agnostic of `CacheRebuildResult`.
- The archive phase catches its own exception, emits `failed('archive','all')` via `$onStep`,
  then re-throws so `AdminAPIController` can emit the final `done:false` SSE event and call
  `exit(0)`. SSE headers are set before `performCacheRebuild()` is called, so SSE is always
  the error channel once the request reaches the controller method.
- `CacheArchiver` and `CacheRebuilder` are injected as **required** constructor parameters.
  Both ARE registered in `ContainerConfig` (see CAT-08). `AdminService` itself IS also
  registered in `ContainerConfig` (CAT-08's responsibility).
- The restore step is a visible SSE event (`stepName: 'restore'`). `runRestorePhase()` emits
  `in_progress('restore','all')`, calls `$this->archiver->restore()`, then emits
  `success('restore','all')` or `failed('restore','all', $msg)` depending on outcome.
- The `performDryRun()` and `buildDryRunSteps()` private methods keep `performCacheRebuild()`
  under 40 lines and comply with the single-responsibility and early-return principles.
- The `runArchivePhase()`, `runClearPhase()`, and `runValidatePhase()` private helpers keep the
  main method readable and the per-step emit logic DRY.
- File length target: under 200 lines. The private helper extraction strategy achieves this.
- Do NOT call `SchemaGenerator::createDatabaseIfNotExists()` — that belongs to initial setup.
- Do NOT use `ServiceLocator` — use `ContainerConfig::getContainer()` in `CacheRebuilder`'s
  constructor (that is CAT-06's responsibility; `AdminService` doesn't need it directly).
