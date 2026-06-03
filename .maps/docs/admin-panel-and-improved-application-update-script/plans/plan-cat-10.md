# Implementation Plan: CAT-10 — CLI Script: scripts/application-update.php

## Spec Context

`scripts/application-update.php` is a standalone CLI-only entry point for coding agents and
deployment pipelines to trigger cache rebuilds without web access. It replaces the existing
`setup.php` (which lives in the web root, uses `ReflectionClass` hacks, and runs user seeding).
The new script bootstraps the framework cleanly via `new Gravitycar()->bootstrap()`, obtains
`AdminService` from the DI container, parses CLI flags, builds `CacheRebuildOptions`, delegates
all work to `AdminService::performCacheRebuild()`, and exits with a meaningful exit code.

Catalog item: CAT-10  
Specification section: Component 7 (scripts/application-update.php); AC-35 through AC-42a  
Acceptance criteria addressed:
- AC-35: First executable statement checks `PHP_SAPI !== 'cli'`; exits 2 with STDERR message.
- AC-36: Accepts flags via `getopt()`: `--metadata`, `--routes`, `--docs`, `--navigation`, `--all`, `--schema`, `--permissions`, `--dry-run`, `-v`, `-q`.
- AC-37: No flags = `--all --schema --permissions`.
- AC-38: Delegates all operations to `AdminService::performCacheRebuild()`.
- AC-39: Dry-run mode: service receives `dryRun=true`; all steps recorded as `skipped`.
- AC-40: Progress to STDOUT; errors to STDERR.
- AC-41: Exit 0 = success, 1 = failure, 2 = invalid args / CLI guard.
- AC-42: Located at `scripts/application-update.php`, outside Apache DocumentRoot.
- AC-42a: Bootstrap via `new Gravitycar()->bootstrap()`. No `ReflectionClass` hacks. Services via `ContainerConfig::getContainer()`.

---

## Dependencies

- **Blocked by**: CAT-07 (`AdminService` — the service this script delegates to)
- **Blocked by**: CAT-08 (`ContainerConfig` — registers `admin_service` key)
- **Blocks**: CAT-11 (shell scripts updated to call this file)
- **Uses**:
  - `Gravitycar\Core\Gravitycar` — framework bootstrap entry point
  - `Gravitycar\Core\ContainerConfig` — DI container access
  - `Gravitycar\Services\Admin\AdminService` (CAT-07)
  - `Gravitycar\Services\Admin\CacheComponent` (CAT-01)
  - `Gravitycar\Services\Admin\CacheRebuildOptions` (CAT-02)
  - `Gravitycar\Services\Admin\CacheRebuildResult` (CAT-03)
  - `Gravitycar\Services\Admin\CacheStepResult` (CAT-02)
  - `vendor/autoload.php` — Composer autoloader (required manually, not via autoloading)

---

## File Changes

### New Files
- `scripts/application-update.php` — standalone CLI script

### Modified Files
- none (CAT-11 handles shell script updates and `setup.php` deprecation)

---

## Implementation Details

### Bootstrap Context: How `setup.php` Does It vs. How the New Script Must Do It

`setup.php` uses `ReflectionClass` to access private properties on the `Gravitycar` object in
order to skip the routing bootstrap step. This is an explicit anti-pattern that must NOT be
replicated.

The new script calls `(new Gravitycar())->bootstrap()` directly. Per AC-42a and the spec's
technical context: the routing bootstrap step only warms the router in the DI container — it
does NOT dispatch an HTTP request. HTTP dispatch only happens in `Gravitycar::run()`, which
this script never calls. Therefore `bootstrap()` is safe in CLI context without any skip logic.

**Correct bootstrap sequence** (after the CLI guard, constants, and require_once at the top):
```php
// These lines appear AFTER the CLI guard, define() constants, and require_once:
use Gravitycar\Core\Gravitycar;
use Gravitycar\Core\ContainerConfig;

// ... inside the try block:
$gc = new Gravitycar();
$gc->bootstrap();

$container = ContainerConfig::getContainer();
$adminService = $container->get('admin_service');
```

---

### Named Exit Code Constants

Defined as PHP `define()` constants at the top of the file, immediately after the CLI guard:

```php
define('EXIT_SUCCESS', 0);
define('EXIT_FAILURE', 1);
define('EXIT_INVALID_ARGS', 2);
```

---

### CLI Guard (first executable statement)

```php
<?php

declare(strict_types=1);

// CLI guard MUST be first — PHP_SAPI is a built-in constant, no autoloader needed.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Error: This script must be run from the command line.\n");
    exit(2);
}

define('EXIT_SUCCESS', 0);
define('EXIT_FAILURE', 1);
define('EXIT_INVALID_ARGS', 2);

require_once __DIR__ . '/../vendor/autoload.php';

use Gravitycar\Core\Gravitycar;
use Gravitycar\Core\ContainerConfig;
// ... other use statements
```

The CLI guard is the FIRST executable PHP statement — before `require_once`, before `define()`,
before `use` statements. This order is mandatory: `PHP_SAPI` needs no autoloader, but
`require_once` and `use` statements would cause parse/autoload errors if reached in a web context.

---

### Flag Parsing with `getopt()`

```php
$shortOptions = 'vq';
$longOptions  = [
    'metadata',
    'routes',
    'docs',
    'navigation',
    'all',
    'schema',
    'permissions',
    'dry-run',
];
$opts = getopt($shortOptions, $longOptions);
```

`getopt()` returns an associative array where each present flag maps to `false` (flag is present
with no value). Absence of a key means the flag was not supplied.

**Presence check helper** (use inline `isset()` — no helper function needed):
```php
$hasAll        = isset($opts['all']);
$hasMetadata   = isset($opts['metadata']);
$hasRoutes     = isset($opts['routes']);
$hasDocs       = isset($opts['docs']);
$hasNavigation = isset($opts['navigation']);
$hasSchema     = isset($opts['schema']);
$hasPermissions= isset($opts['permissions']);
$hasDryRun     = isset($opts['dry-run']);
$isVerbose     = isset($opts['v']);
$isQuiet       = isset($opts['q']);
```

**Mutual exclusion** — `-v` and `-q` are mutually exclusive. Validate and exit 2 if both present:
```php
if ($isVerbose && $isQuiet) {
    fwrite(STDERR, "Error: -v (verbose) and -q (quiet) cannot be used together.\n");
    exit(EXIT_INVALID_ARGS);
}
```

---

### Building `CacheRebuildOptions`

**Default behavior** (no component flags provided):

When none of `--metadata`, `--routes`, `--docs`, `--navigation`, `--all` are present,
behave as if `--all --schema --permissions` were passed.

```php
$noComponentFlags = !$hasAll && !$hasMetadata && !$hasRoutes && !$hasDocs && !$hasNavigation;

if ($noComponentFlags) {
    $options = CacheRebuildOptions::all();
    if ($hasDryRun) {
        $options = new CacheRebuildOptions(
            CacheComponent::all(),
            updateSchema: true,
            updatePermissions: true,
            dryRun: true
        );
    }
}
```

**`--all` flag** (or no flags — same result, plus `--schema`/`--permissions` handled by `all()`):

When `--all` is present explicitly (but component flags may also be present — `--all` wins):
```php
elseif ($hasAll) {
    $options = CacheRebuildOptions::fromArray([
        'components'        => CacheComponent::all(),
        'updateSchema'      => $hasSchema,
        'updatePermissions' => $hasPermissions,
        'dryRun'            => $hasDryRun,
    ]);
}
```

**Specific component flags** (no `--all`, no default):
```php
else {
    $components = [];
    if ($hasMetadata)   { $components[] = CacheComponent::METADATA; }
    if ($hasRoutes)     { $components[] = CacheComponent::ROUTES; }
    if ($hasDocs)       { $components[] = CacheComponent::DOCS; }
    if ($hasNavigation) { $components[] = CacheComponent::NAVIGATION; }

    $options = CacheRebuildOptions::fromArray([
        'components'        => $components,
        'updateSchema'      => $hasSchema,
        'updatePermissions' => $hasPermissions,
        'dryRun'            => $hasDryRun,
    ]);
}
```

`CacheRebuildOptions::fromArray()` throws `InvalidArgumentException` if components is empty.
This cannot happen here because the `$noComponentFlags` branch uses `CacheComponent::all()`,
and the `else` branch only runs when at least one component flag is set. No extra guard needed.

---

### Progress Output

**`$onStep` callback** passed to `AdminService::performCacheRebuild()`:

```php
$onStep = function (CacheStepResult $step) use ($isQuiet, $isVerbose): void {
    if ($isQuiet) {
        return; // Quiet mode: suppress all progress output
    }

    $line = buildStepLine($step);
    fwrite(STDOUT, $line . "\n");

    if ($isVerbose) {
        $detail = json_encode($step->toArray(), JSON_PRETTY_PRINT);
        fwrite(STDOUT, $detail . "\n");
    }
};
```

**`buildStepLine(CacheStepResult $step): string`** (standalone function in script scope):

Formats the step as `[STATUS] stepName (component)`:
```php
function buildStepLine(CacheStepResult $step): string
{
    $statusMap = [
        'in_progress' => 'RUNNING',
        'success'     => 'OK',
        'failed'      => 'FAILED',
        'skipped'     => 'SKIPPED',
    ];

    $status    = $statusMap[$step->getStatus()] ?? strtoupper($step->getStatus());
    $stepName  = $step->getStepName();
    $component = $step->getComponent();

    return "[$status] $stepName ($component)";
}
```

**Error output** — when a step has `failed` status, additionally write to STDERR regardless
of quiet mode (quiet suppresses STDOUT progress, not STDERR errors):
```php
$onStep = function (CacheStepResult $step) use ($isQuiet, $isVerbose): void {
    if ($step->getStatus() === 'failed') {
        $errorMessage = $step->getErrorMessage() ?? 'Unknown error';
        fwrite(STDERR, '[ERROR] ' . $step->getStepName() . ' (' . $step->getComponent() . '): ' . $errorMessage . "\n");
    }

    if ($isQuiet) {
        return;
    }

    fwrite(STDOUT, buildStepLine($step) . "\n");

    if ($isVerbose) {
        fwrite(STDOUT, json_encode($step->toArray(), JSON_PRETTY_PRINT) . "\n");
    }
};
```

---

### Result Handling and Exit Codes

After `$result = $adminService->performCacheRebuild($options, $onStep)`:

```php
if ($result->isSuccess()) {
    if (!$isQuiet) {
        fwrite(STDOUT, "\nCache rebuild completed successfully.\n");
    }
    exit(EXIT_SUCCESS);
} else {
    fwrite(STDERR, "\nCache rebuild failed. Archive restored to pre-rebuild state.\n");
    exit(EXIT_FAILURE);
}
```

---

### Exception Handling

Wrap the bootstrap and service call in a top-level `try/catch`:

```php
try {
    // bootstrap + getopt + build options + performCacheRebuild
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(EXIT_INVALID_ARGS);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Fatal error: ' . $e->getMessage() . "\n");
    exit(EXIT_FAILURE);
}
```

`InvalidArgumentException` maps to exit code 2 (invalid args) because it can only be thrown
by `CacheRebuildOptions` when components is empty — which indicates a programming error or
a bug in the option-building logic, not a runtime failure.

---

### Complete Script Structure

The CLI guard uses only `PHP_SAPI`, which is a PHP built-in constant available before the
autoloader is loaded. It must appear first — before `require_once`, before constants, before
any framework code — because those lines would fail if executed in a web context.

```
scripts/application-update.php
  1. <?php declare(strict_types=1);
  2. CLI guard — if (PHP_SAPI !== 'cli') { fwrite(STDERR, ...); exit(2); }
     (FIRST executable statement — uses only PHP_SAPI, needs no autoloader)
  3. define() exit code constants
  4. require_once __DIR__ . '/../vendor/autoload.php'
  5. use statements (Gravitycar, ContainerConfig, CacheComponent, CacheRebuildOptions, CacheStepResult)
  6. buildStepLine() function definition
  7. try {
       a. Bootstrap: new Gravitycar()->bootstrap()
       b. Get services: ContainerConfig::getContainer()->get('admin_service')
       c. getopt() flag parsing
       d. Validate -v/-q mutual exclusion
       e. Build $options (CacheRebuildOptions)
       f. Define $onStep callback
       g. $result = $adminService->performCacheRebuild($options, $onStep)
       h. Check $result->isSuccess(), fwrite, exit(0|1)
     }
     catch (InvalidArgumentException $e) { fwrite(STDERR, ...); exit(2); }
     catch (\Throwable $e) { fwrite(STDERR, ...); exit(1); }
```

Estimated file length: ~160 lines — well under the 300-line limit.

---

### Example Invocations

```bash
# Default — rebuild everything (same as --all --schema --permissions)
php scripts/application-update.php

# Rebuild only metadata and routes
php scripts/application-update.php --metadata --routes

# Rebuild all with schema and permissions, verbose output
php scripts/application-update.php --all --schema --permissions -v

# Dry run — show what would happen, no changes
php scripts/application-update.php --dry-run

# Quiet — only errors to STDERR, exit code tells the story
php scripts/application-update.php --all --schema --permissions -q

# Rebuild routes cache only
php scripts/application-update.php --routes
```

---

## Error Handling

| Condition | Action |
|-----------|--------|
| Not running in CLI | Write to STDERR, exit 2 (before anything else) |
| `-v` and `-q` both present | Write to STDERR, exit 2 |
| Bootstrap throws | Caught by `\Throwable`; write to STDERR, exit 1 |
| `ContainerConfig::getContainer()->get('admin_service')` fails | Caught by `\Throwable`; write to STDERR, exit 1 |
| `CacheRebuildOptions::fromArray()` throws `InvalidArgumentException` | Caught specifically; exit 2 (invalid args) |
| `AdminService::performCacheRebuild()` archive phase throws | Caught by `\Throwable`; write to STDERR, exit 1 |
| `AdminService::performCacheRebuild()` returns failure result | `$result->isSuccess() === false`; write to STDERR, exit 1 |
| All steps succeed | `$result->isSuccess() === true`; exit 0 |

---

## Unit Test Specifications

**Test file**: `tests/Unit/Scripts/ApplicationUpdateScriptTest.php`

**Testing approach**: Because this is a procedural script (not a class), testing requires
either:
(a) Extracting the script logic into a testable class `ApplicationUpdateRunner` and calling
    that from the script, OR
(b) Running the script as a subprocess via `proc_open()` / `shell_exec()` with different
    CLI args and checking exit code + output.

Approach (b) is recommended — it tests the actual entry point without refactoring. These
are integration-style unit tests.

**Setup**: Mock `AdminService` cannot be injected from outside the script. Tests use
approach (b) — subprocess execution against a test environment with a real (or stubbed)
container. Alternatively, approach (a) is cleaner for unit testing.

### Recommended approach (a): `ApplicationUpdateRunner` class

Extract all logic from the script into a class:
```php
class ApplicationUpdateRunner {
    public function __construct(private AdminService $adminService) {}
    public function run(array $opts): int { ... }
}
```

The script becomes:
```php
// CLI guard, require, bootstrap, get service...
$runner = new ApplicationUpdateRunner($adminService);
exit($runner->run($opts));
```

This makes all option-parsing and exit-code logic unit-testable with a mock `AdminService`.

**If approach (a) is chosen**, tests for `ApplicationUpdateRunner::run()`:

### `run()` — default behavior (no flags)

| Case | `$opts` | Expected | Why |
|------|---------|----------|-----|
| No flags → all + schema + permissions | `[]` | `AdminService` called with `CacheRebuildOptions::all()` (all 4 components, schema=true, permissions=true, dryRun=false) | AC-37 |
| No flags → success → exit 0 | `[]`, mock returns success | Returns `EXIT_SUCCESS` (0) | AC-41 |

### `run()` — component flags

| Case | `$opts` | Expected | Why |
|------|---------|----------|-----|
| `--metadata` only | `['metadata' => false]` | Components = `['metadata']`, schema=false, permissions=false | Specific flag |
| `--routes` only | `['routes' => false]` | Components = `['routes']` | Specific flag |
| `--all` flag | `['all' => false]` | Components = all 4 | AC-36 |
| `--all --schema` | `['all'=>false,'schema'=>false]` | All components, schema=true | AC-36 |
| `--all --permissions` | `['all'=>false,'permissions'=>false]` | All components, permissions=true | AC-36 |
| `--metadata --routes` | both flags | Components = `['metadata','routes']` | Multiple flags |

### `run()` — dry-run

| Case | `$opts` | Expected | Why |
|------|---------|----------|-----|
| `--dry-run` no other flags | `['dry-run'=>false]` | options.dryRun=true, all components (default) | AC-39 |
| `--dry-run --metadata` | both | options.dryRun=true, components=['metadata'] | AC-39 |
| AdminService called with dryRun=true | `['dry-run'=>false]` | `CacheRebuildOptions.dryRun === true` passed to service | AC-38 |

### `run()` — quiet and verbose

| Case | `$opts` | Expected | Why |
|------|---------|----------|-----|
| `-q` quiet mode | `['q'=>false]` | No STDOUT output, service still called | AC-40 |
| `-v` verbose mode | `['v'=>false]` | STDOUT includes JSON detail per step | AC-40 |
| `-v` and `-q` together | `['v'=>false,'q'=>false]` | Returns `EXIT_INVALID_ARGS` (2), service NOT called | Mutual exclusion |

### `run()` — exit codes

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Service returns success | Mock `isSuccess()=true` | Returns 0 | AC-41 |
| Service returns failure | Mock `isSuccess()=false` | Returns 1 | AC-41 |
| `-v` and `-q` together | | Returns 2 | AC-41 |

### Key Scenario: Default invocation runs all components

**Setup**: Mock `AdminService::performCacheRebuild()` to capture the `CacheRebuildOptions`
it receives, call `$onStep` with one success step, and return a success result.

**Action**: `$runner->run([])` (empty opts = no flags).

**Expected**:
- `performCacheRebuild()` called once.
- `$capturedOptions->getComponents()` === all 4 `CacheComponent` values.
- `$capturedOptions->isUpdateSchema()` === `true`.
- `$capturedOptions->isUpdatePermissions()` === `true`.
- `$capturedOptions->isDryRun()` === `false`.
- Return value: `EXIT_SUCCESS` (0).

**Why**: AC-37 — "no options behaves identically to --all --schema --permissions".

### Key Scenario: Step output format

**Setup**: Mock `$onStep` receives a `CacheStepResult::success('clear', 'metadata')`.

**Action**: Capture STDOUT during `$runner->run(['metadata' => false])`.

**Expected**: STDOUT contains `[OK] clear (metadata)`.

**Why**: AC-40 — progress output format `[STATUS] stepName (component)`.

### Key Scenario: Error only to STDERR in quiet mode

**Setup**: Mock service calls `$onStep` with a `CacheStepResult::failed('rebuild', 'routes', 'Engine error')`.

**Action**: `$runner->run(['q' => false, 'routes' => false])`.

**Expected**:
- STDOUT is empty (quiet mode).
- STDERR contains `[ERROR] rebuild (routes): Engine error`.
- Return value: `EXIT_FAILURE` (1) (because result.isSuccess() = false).

**Why**: AC-40 — errors go to STDERR even in quiet mode.

---

## Notes

- The script is NOT autoloaded by Composer — it requires `require_once __DIR__ . '/../vendor/autoload.php'`
  at the top (after the CLI guard and constants). The `scripts/` directory is one level below
  the project root, where `vendor/` lives. `__DIR__` resolves to the `scripts/` directory, so
  `'/../vendor/autoload.php'` is the correct relative path.
- `new Gravitycar()` (no arguments) uses the default environment. To pass the environment from
  the shell, use: `$env = getenv('APP_ENV') ?: 'production'; $gc = new Gravitycar(['environment' => $env]);`
  Match the same pattern seen in `setup.php` line 99: `new Gravitycar(['environment' => 'development'])`.
  For the production script, default to `'production'` not `'development'`.
- `bootstrap()` is safe in CLI context because the routing step only warms the DI container's
  router service — it does NOT call `Router::dispatch()`. HTTP dispatch only happens in
  `Gravitycar::run()`, which this script never calls. This is confirmed by the spec (AC-42a).
- Do NOT call `SchemaGenerator::createDatabaseIfNotExists()` from this script. That belongs to
  initial setup, not cache rebuild. The `--schema` flag triggers `SchemaGenerator::generateSchema()`
  (via `AdminService` → `CacheRebuilder`), which is safe to call repeatedly.
- Do NOT create users or roles from this script. User/role seeding is a separate concern (and
  a deliberate design choice to separate `setup.php` concerns into focused tools).
- The `ReflectionClass` hack from `setup.php` (lines 104–127) MUST NOT be replicated. The spec
  (AC-42a) explicitly states this.
- File must be placed at `scripts/application-update.php`, which is outside the Apache
  `DocumentRoot` (the web root serves from the project root or a `public/` subdirectory).
  The `scripts/` directory is never web-accessible. This provides defense-in-depth alongside
  the CLI guard.
- The `buildStepLine()` function is defined at script scope (not inside a class or closure)
  to keep the script readable. This is acceptable for a procedural CLI script that is not
  autoloaded.
- If the `ApplicationUpdateRunner` class extraction approach is chosen for testability, the
  class should live in `scripts/ApplicationUpdateRunner.php` (not under `src/`) since it is
  a script-scope concern, not a framework concern. Alternatively it can be defined inline in
  the script file since it is only used there.
