# Specification: Admin Panel and Improved Application Update Script

## Overview

The Gravitycar Framework currently relies on a monolithic `setup.php` script in the web root to perform all administrative and setup tasks. This script is publicly accessible, inconsistently implemented, and cannot be invoked safely by an authenticated browser user without SSH access to the server. This epic replaces `setup.php` with a well-designed set of components that deliver the same operations through two safe surfaces: an authenticated Admin UI and a CLI script for coding agents and deployment pipelines.

### Goals

1. Centralize all cache-rebuild logic in one service so the CLI and the API always produce identical results.
2. Provide an authenticated browser UI for admin users to rebuild cache components without server access.
3. Provide a CLI script that coding agents and deployment scripts can call safely.
4. Fix the known bugs and anti-patterns in `setup.php` (glob miss, ReflectionClass hacks, ServiceLocator inconsistency).
5. Hide all update tooling from the public internet.
6. Deprecate `setup.php` in place; update all callers to use the new script.

---

## Acceptance Criteria

### Three-Class Backend Split (AdminService / CacheArchiver / CacheRebuilder)

The backend implementation is split into exactly three classes with distinct responsibilities:

- **`AdminService`** — orchestration only: coordinates the phases (archive → clear → rebuild → validate → delete archive), performs stale-file scan on initialization, and wires phase results into a `CacheRebuildResult`. Does no direct file I/O.
- **`CacheArchiver`** — all tar operations: create archive, `chmod 600`, verify archive integrity, restore archive, delete archive, and scan for stale `cache_*.tar` files in the app root.
- **`CacheRebuilder`** — all cache file operations: clear cache files via `RecursiveDirectoryIterator`, call engine services to rebuild, and run `php -l` syntax validation.

### AdminService (PHP)

- AC-1: `AdminService::performCacheRebuild()` accepts a `CacheRebuildOptions` value object and returns a `CacheRebuildResult` value object.
- AC-2: `AdminService` always delegates to `CacheArchiver` to create an archive before any files are cleared. The archive is named `cache_YYYY_MM_DD_HH_MM_SS.tar` and placed in the application root. Archiving is not optional.
- AC-3: After `CacheArchiver` creates the archive, it verifies the archive file exists and has a non-zero file size before returning the archive path. Throws `AdminServiceException` on failure.
- AC-4: If ANY failure occurs during clear, rebuild, or validation — `AdminService` stops all processing immediately, delegates to `CacheArchiver::restore()` to restore the ENTIRE archive, then records the failure in the result. There is no independent per-component processing after a failure. Cache files must be kept in sync — partial-component archives still restore to a consistent cache state.
- AC-5: `CacheRebuilder::clear()` uses `RecursiveDirectoryIterator` (not `glob`) so that the `cache/documentation/` subdirectory is always included.
- AC-7: When METADATA is included and `updateSchema` is true, `SchemaGenerator::generateSchema()` is called with the freshly loaded metadata.
- AC-8: When METADATA is included and `updatePermissions` is true, `PermissionsBuilder::buildAllPermissions()` is called. This clears existing permissions, rebuilds model permissions from metadata, and rebuilds controller permissions from all `ApiControllerBase` subclasses.
- AC-9: `CacheRebuilder::validate()` runs `php -l <file>` for every PHP file in the selected components' cache paths. The result is recorded per file.
- AC-10: On any failure during clear/rebuild/validate, `AdminService` delegates to `CacheArchiver::restore()` to restore the full archive, then records the failure in the `CacheRebuildResult` with an error message. The result is returned (not re-thrown).
- AC-11: `AdminService` retrieves all dependencies from `ContainerConfig::getContainer()`. No `ServiceLocator` usage, no `ReflectionClass` usage.
- AC-12: `AdminService`, `CacheArchiver`, and `CacheRebuilder` each have a `$logger` property (Monolog Logger) and log the start, completion, and any error of each step.
- AC-13: `AdminService`, `CacheArchiver`, and `CacheRebuilder` each have a `$config` property (Config instance).
- AC-14: No file in the implementation exceeds 300 lines.
- AC-14a: On `AdminService` initialization, `CacheArchiver::scanForStaleArchives()` is called. Any stale `cache_*.tar` files found in the app root are logged as warnings (one warning per file). No auto-deletion occurs.
- AC-14b: After validation succeeds, `CacheArchiver::delete()` is called to remove the archive. After a restore completes, `CacheArchiver::delete()` is also called to remove the archive. The archive is a short-lived safety net only — it MUST NOT persist after the operation concludes.
- AC-14c: Immediately after the archive `tar` file is created, `CacheArchiver` runs `chmod 600` on it before returning.

### CacheRebuildOptions

- AC-15: `CacheRebuildOptions` is a value object (no business logic) with the following boolean properties: `dryRun` (defaults `false`), `updateSchema` (defaults `false`), `updatePermissions` (defaults `false`), and an array property `components` listing which cache components to include. Archiving is always performed and is not a configurable option.
- AC-15a: If `components` is empty, `CacheRebuildOptions` construction (or `AdminService::performCacheRebuild()` reception) throws `InvalidArgumentException` immediately.
- AC-16: The valid component identifiers are the constants `CacheComponent::METADATA`, `CacheComponent::ROUTES`, `CacheComponent::DOCS`, `CacheComponent::NAVIGATION`.
- AC-17: A static factory method `CacheRebuildOptions::all()` returns an instance with all four components selected, `updateSchema=true`, `updatePermissions=true`, `dryRun=false`.

### CacheRebuildResult

- AC-18: `CacheRebuildResult` exposes a boolean `isSuccess()` method that returns `false` if any step failed.
- AC-19: `CacheRebuildResult` exposes per-step status records for: archive, clear, rebuild, validate, and (conditionally) schema update and permissions update.
- AC-20: Each step record includes a `status` string (`success`, `skipped`, `failed`), an optional `errorMessage`, and the `component` it applies to.

### Admin API Controller

- AC-21: The `AdminAPIController` is registered in `ContainerConfig` and auto-discovered by `APIControllerFactory`.
- AC-22: `$rolesAndActions` is `['admin' => ['*']]`, restricting all actions to the `admin` role exclusively. No manual seeding is needed; `PermissionsBuilder::buildAllControllerPermissions()` auto-discovers all `ApiControllerBase` subclasses.
- AC-23: `POST /api/admin/cache/rebuild` accepts a JSON body with `components` (array), `updateSchema` (bool), `updatePermissions` (bool), and responds with `Content-Type: text/event-stream`. Auth and input validation errors (401, 403, 400) are returned as normal HTTP status codes before the stream begins.
- AC-24: Each SSE event is a JSON object with `stepName`, `component`, `status` (`in_progress`/`success`/`failed`/`skipped`), and `errorMessage`. The final event additionally carries `done: true`, `success: bool`, and a `message` string.
- AC-25: A non-admin authenticated request to any admin route receives a 403 response.
- AC-26: An unauthenticated request to any admin route receives a 401 response.
- AC-26a: `AdminAPIController` accepts up to 7 constructor parameters: the 6 standard optional DI params (logger, modelFactory, databaseConnector, metadataEngine, config, currentUserProvider) plus `AdminService` as the 7th. This pattern is consistent with `OpenAPIController`, `NavigationAPIController`, and `TMDBController`.

### ProtectedRoute Component (new)

- AC-27a: A new `ProtectedRoute` component is created at `gravitycar-frontend/src/components/ProtectedRoute.tsx` as part of this epic.
- AC-27b: `ProtectedRoute` accepts two props: `children` (ReactNode, required) and `requiredRole` (string, optional).
- AC-27c: While `isLoading` is `true` from `useAuth()`, `ProtectedRoute` renders a loading spinner (or null). Auth and role checks only execute once `isLoading` is `false`.
- AC-27d: If the user is not authenticated (and `isLoading` is `false`), `ProtectedRoute` redirects to `/login`.
- AC-27e: If the user is authenticated but `requiredRole` is provided and `user.user_type` does not match `requiredRole`, `ProtectedRoute` redirects to `/unauthorized`. The `UnauthorizedPage` component already exists.
- AC-27f: If the user is authenticated and either no `requiredRole` is specified or the role matches, `ProtectedRoute` renders `children`.

### React Admin Panel UI

- AC-27: The route `/admin` is protected by `<ProtectedRoute requiredRole="admin">` and is wrapped in `<Layout>` in `App.tsx`.
- AC-28: The admin panel page displays a "Cache Management" section with checkboxes for each of the four cache components (all checked by default).
- AC-29: When the METADATA checkbox is checked, additional checkboxes for "Update Schema" and "Update Permissions" appear (both checked by default).
- AC-30: Clicking "Rebuild Cache" opens a confirmation modal that describes the consequences in plain language, listing selected components and secondary operations.
- AC-31: After the user confirms, the UI sends the request to `POST /api/admin/cache/rebuild` using `fetch` with `response.body.getReader()` (SSE streaming). The modal shows a loading spinner and an empty step list that populates in real time as events arrive.
- AC-31a: Before reading the stream, the UI checks `response.status`. A 401 clears localStorage and redirects to `/login`; a 403 navigates to `/unauthorized` using `imperativeNavigate`. Both reuse a shared `handleAuthError(status)` utility extracted from the existing axios interceptor logic in `api.ts`.
- AC-32: As each SSE event arrives, it is parsed and appended to the step list in the modal. Each step shows its name, a status icon (spinner while in-progress, checkmark for success, X for failure, dash for skipped), and an error message when applicable.
- AC-32a: The final SSE event carries `{"done": true, "success": true|false}`. On receipt, the modal transitions to the result view: the spinner is replaced with a summary message and a "Close" button appears. A "Close" button dismisses the modal.
- AC-33: The admin panel layout is designed as a section-based page that can accommodate future admin features beyond cache management.
- AC-34: No external UI libraries are used; all styling uses Tailwind CSS utility classes.

### CLI Script (`scripts/application-update.php`)

- AC-35: The script checks `PHP_SAPI !== 'cli'` as the very first executable statement and writes an error to STDERR then exits with code 2 if not running in CLI mode.
- AC-36: The script accepts the following flags via `getopt()`: `--metadata`, `--routes`, `--docs`, `--navigation`, `--all`, `--schema`, `--permissions`, `--dry-run`, `-v` (verbose), `-q` (quiet).
- AC-37: When invoked with no options, the script behaves identically to `--all --schema --permissions` (rebuild everything).
- AC-38: The script delegates all operations to `AdminService::performCacheRebuild()`.
- AC-39: Dry-run mode skips ALL steps — no files are created, modified, or deleted. The service receives `dryRun=true` in the options and logs what would happen for each step, recording `skipped` status for each.
- AC-40: Progress output goes to STDOUT; error output goes to STDERR.
- AC-41: Exit codes: 0 = all operations succeeded, 1 = one or more operations failed, 2 = invalid arguments or CLI-guard failure.
- AC-42: The script is located at `scripts/application-update.php`, outside the Apache `DocumentRoot`.
- AC-42a: The bootstrap sequence calls `new Gravitycar()` then `->bootstrap()`. All bootstrap steps are safe in CLI context because the routing step only warms the router in the DI container; HTTP dispatch only occurs in `run()`, which the CLI script never calls. Services are then obtained via `ContainerConfig::getContainer()`. The `ReflectionClass` hack from `setup.php` MUST NOT be replicated.

### Migration of `setup.php` Callers

- AC-43: `scripts/build/build-backend.sh` lines 319, 320, and 326 are updated to call `scripts/application-update.php` instead of `setup.php`. (Verified: all three lines reference `setup.php`.)
- AC-44: `scripts/deploy/transfer.sh` line 232 is updated to fix the path bug: the `chmod` target changes from `scripts/setup.php` (wrong) to `scripts/application-update.php` (correct). This fixes the existing path bug where the script tried to chmod a file at the wrong location.
- AC-45: `scripts/deploy/transfer.sh` line 246 is updated to call `scripts/application-update.php` instead of `setup.php`. (Verified: this line references `setup.php`.)
- AC-46: `setup.php` in the root directory is marked with a deprecation comment at the top but is NOT deleted.

---

## Explicit Constraints (DO NOT)

- Do NOT delete `setup.php`; add a deprecation notice only. Deletion is a separate task.
- Do NOT create users or roles from `AdminService` or `application-update.php`; user/role seeding is a separate concern.
- Do NOT use `ServiceLocator` in any new code; use `ContainerConfig::getContainer()`.
- Do NOT use `ReflectionClass` to access private properties or bypass bootstrapping.
- Do NOT use `glob('cache/*')` for clearing; it misses subdirectories.
- Do NOT use external UI component libraries (Shadcn, Radix, etc.); Tailwind only.
- Do NOT use `EventSource` for the streaming endpoint; use `fetch` with `response.body.getReader()` (SSE via POST requires `fetch`, as `EventSource` only supports GET).
- Do NOT bypass the 401/403 redirect logic when using `fetch`; extract it into a shared utility and call it explicitly before reading the stream.
- Do NOT call `SchemaGenerator::createDatabaseIfNotExists()` from the new service; that belongs to initial setup, not cache rebuild.
- Do NOT create any file exceeding 300 lines.

---

## Component Descriptions

### 1. `CacheComponent` (constants class)

**Location**: `src/Services/Admin/CacheComponent.php`

Defines the four named cache component identifiers used throughout the system:
- `METADATA` — corresponds to `cache/metadata_cache.php`
- `ROUTES` — corresponds to `cache/api_routes.php`
- `DOCS` — corresponds to `cache/documentation/` directory
- `NAVIGATION` — corresponds to `cache/navigation_cache_*.php` files

Provides a static method `all()` returning an array of all four component constants, and a static method `isValid(string $component)` for validation.

---

### 2. `CacheRebuildOptions` (value object)

**Location**: `src/Services/Admin/CacheRebuildOptions.php`

An immutable value object with no business logic. Archiving is always performed before clearing and is not a configurable option. Properties:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `components` | `array` | `[]` | Which cache components to include (use `CacheComponent` constants) |
| `updateSchema` | `bool` | `false` | Run schema migration after metadata rebuild |
| `updatePermissions` | `bool` | `false` | Rebuild permissions after metadata rebuild |
| `dryRun` | `bool` | `false` | Log operations without executing them |

Static factory methods:
- `CacheRebuildOptions::all()` — all components, `updateSchema=true`, `updatePermissions=true`, `dryRun=false`
- `CacheRebuildOptions::fromArray(array $data)` — construct from API request body or CLI parsed options

---

### 3. `CacheRebuildResult` (value object)

**Location**: `src/Services/Admin/CacheRebuildResult.php`

Holds the outcome of a `performCacheRebuild()` call. Structure:

- `isSuccess(): bool` — `true` only if all executed steps succeeded
- `getSteps(): array` — array of `CacheRebuildStepResult` objects
- `toArray(): array` — serializable form for JSON API response

Each `CacheRebuildStepResult` records:
- `stepName`: string — one of `archive`, `clear`, `rebuild`, `validate`, `schema_update`, `permissions_update`
- `component`: string — which `CacheComponent` constant this step applies to (or `all` for archive)
- `status`: string — `success`, `failed`, or `skipped`
- `errorMessage`: `?string`

---

### 4. `AdminService`, `CacheArchiver`, `CacheRebuilder`

The backend implementation is split into three classes upfront (not conditionally):

---

#### 4a. `AdminService`

**Location**: `src/Services/Admin/AdminService.php`

Orchestration only. Has `$logger` (Monolog Logger) and `$config` (Config) properties. Holds references to `CacheArchiver` and `CacheRebuilder`, which are injected as optional constructor parameters (null defaults) and instantiated in the constructor when null. All other cache-system service dependencies are retrieved from `ContainerConfig::getContainer()` internally. `CacheArchiver` and `CacheRebuilder` are NOT registered in `ContainerConfig`.

**Constructor signature:**

```php
public function __construct(
    Logger $logger,
    Config $config,
    ?CacheArchiver $archiver = null,
    ?CacheRebuilder $rebuilder = null
) {
    $this->archiver = $archiver ?? new CacheArchiver($logger, $config);
    $this->rebuilder = $rebuilder ?? new CacheRebuilder($logger, $config, /* engine services */);
}
```

**Initialization behavior:**

On construction, calls `CacheArchiver::scanForStaleArchives()`. Logs a warning for each stale `cache_*.tar` file found in the app root. Does not auto-delete them.

**Public interface:**

`performCacheRebuild(CacheRebuildOptions $options): CacheRebuildResult`

High-level orchestration. Throws `InvalidArgumentException` immediately if `$options->components` is empty. Executes the following sequence:

1. `CacheArchiver::create($components)` → returns archive path (always performed)
2. `CacheRebuilder::clear($components)`
3. `CacheRebuilder::rebuild($components, $updateSchema, $updatePermissions)`
4. `CacheRebuilder::validate($components)`
5. On success: `CacheArchiver::delete($archivePath)`
6. On ANY failure in steps 2–4: stop all processing immediately, `CacheArchiver::restore($archivePath)`, then `CacheArchiver::delete($archivePath)`, then record failure in result

The method does not re-throw — it returns the `CacheRebuildResult` so the caller can inspect and report the error.

**Dry-run behavior:**

When `CacheRebuildOptions::$dryRun` is `true`, ALL steps are skipped — no files are created, modified, or deleted. Each step is logged with what it would have done, and the result records `skipped` for every step.

---

#### 4b. `CacheArchiver`

**Location**: `src/Services/Admin/CacheArchiver.php`

Responsible for all tar operations. Has `$logger` (Monolog Logger) and `$config` (Config) properties.

**Public interface:**

`create(array $components): string`

Creates a timestamped `cache_YYYY_MM_DD_HH_MM_SS.tar` archive in the application root containing the cache files/directories for the given components. Immediately runs `chmod 600` on the archive file after creation. Verifies the file exists and has non-zero size. Returns the archive file path. Throws `AdminServiceException` on failure.

`restore(string $archiveFilePath): void`

Restores the ENTIRE archive regardless of which components were originally selected. After restore, all cache files are in a consistent pre-rebuild state. Throws `AdminServiceException` on failure.

`delete(string $archiveFilePath): void`

Deletes the archive file. Called after validation succeeds OR after restore completes. Logs a warning if the file does not exist (non-fatal).

`verify(string $archiveFilePath): bool`

Returns `true` if the archive file exists and has a non-zero size.

`scanForStaleArchives(): array`

Scans the application root for files matching `cache_*.tar`. Returns an array of file paths. Logs a warning for each one found (called by `AdminService` on init).

---

#### 4c. `CacheRebuilder`

**Location**: `src/Services/Admin/CacheRebuilder.php`

Responsible for cache file operations. Has `$logger` (Monolog Logger) and `$config` (Config) properties. Holds references to the engine services retrieved from the DI container.

**Public interface:**

`clear(array $components): void`

Removes cache files for the specified components using `RecursiveDirectoryIterator`. Does not remove directories themselves, only their contents.

`rebuild(array $components, bool $updateSchema, bool $updatePermissions): void`

Calls the appropriate framework service for each component:
- METADATA: `MetadataEngine::loadAllMetadata()`
- ROUTES: `APIRouteRegistry::rebuildCache()` — confirmed to write `cache/api_routes.php` directly
- DOCS: `OpenAPIGenerator::generateSpecification()`
- NAVIGATION: `NavigationBuilder::buildAllRoleNavigationCaches()`

When METADATA is included and `updateSchema` is true, calls `SchemaGenerator::generateSchema($metadata)` with the freshly returned metadata array.

When METADATA is included and `updatePermissions` is true, calls `PermissionsBuilder::buildAllPermissions()`.

`validate(array $components): void`

For every PHP file in the selected components' cache paths, executes `php -l <file>` via `exec()` or `shell_exec()`. Throws `AdminServiceException` if any file fails syntax validation.

---

### 5. `AdminAPIController`

**Location**: `src/Api/AdminAPIController.php`

Extends `ApiControllerBase`. Restricts all actions to admin role.

**Constructor**: Accepts up to 7 optional DI parameters: the 6 standard params (logger, modelFactory, databaseConnector, metadataEngine, config, currentUserProvider) plus `AdminService` as the 7th. This matches the pattern of `OpenAPIController`, `NavigationAPIController`, and `TMDBController`. All parameters are optional with null defaults; the constructor resolves them from the container when null.

**Routes:**

`registerRoutes()` returns:

| Method | Path | Handler | RBAC Action |
|--------|------|---------|-------------|
| POST | `/api/admin/cache/rebuild` | `handleCacheRebuild` | `rebuild` |

**Method `handleCacheRebuild()`:**

1. Decodes the JSON request body.
2. Validates the `components` array values against `CacheComponent::isValid()`.
3. Constructs a `CacheRebuildOptions` via `CacheRebuildOptions::fromArray()`.
4. Calls `AdminService::performCacheRebuild($options)`.
5. Returns JSON with `success`, `message`, and `steps` fields from the `CacheRebuildResult`.

**Registration**: Added to `ContainerConfig` and auto-discovered by `APIControllerFactory`.

---

### 6. React Admin Panel

**Files:**
- `gravitycar-frontend/src/components/ProtectedRoute.tsx` — new auth/role gate component (see AC-27a through AC-27e)
- `gravitycar-frontend/src/pages/AdminPage.tsx` — page wrapper
- `gravitycar-frontend/src/components/admin/CacheManagementPanel.tsx` — cache rebuild feature
- `gravitycar-frontend/src/components/admin/ConfirmRebuildModal.tsx` — confirmation + result modal
- Route added to `gravitycar-frontend/src/App.tsx`

**Page structure (`AdminPage.tsx`):**

A section-based layout with a heading and distinct panels for each admin feature. Currently contains one panel: Cache Management. The layout must visually accommodate future panels (e.g., User Management, System Status).

**`CacheManagementPanel` component:**

Manages state for:
- Which components are selected (four checkboxes, all checked by default)
- Whether updateSchema and updatePermissions are selected (checkboxes, visible and checked by default when METADATA is selected)
- Whether the confirmation modal is open
- The API result (null until a rebuild completes)
- Loading state

Displays:
- A heading "Cache Management"
- A brief description of what cache rebuild does
- One checkbox per cache component (Metadata, Routes, Docs, Navigation)
- Conditional checkboxes for "Update Database Schema" and "Update Permissions" (shown only when Metadata is checked)
- A "Rebuild Cache" button that opens the confirmation modal

**`ConfirmRebuildModal` component:**

Three internal views controlled by state:
1. **Confirmation view**: Lists selected components and secondary operations. "Rebuild Cache" (danger-styled) and "Cancel" buttons.
2. **Streaming view**: Shown while the SSE stream is in progress. The "Rebuild Cache" button is replaced with a spinner. A step list appears and populates in real time as events arrive — each step shows a spinner while `in_progress`, then transitions to a checkmark (success), X (failed), or dash (skipped). No cancel is possible during the stream.
3. **Result view**: Shown when the final `done` event is received. The spinner is replaced with a summary message ("Rebuild complete" or "Rebuild failed — archive restored"). A "Close" button dismisses the modal. Results are displayed inside the modal, NOT inline on the page.

**Route registration in `App.tsx`:**

```
/admin → <Layout><ProtectedRoute requiredRole="admin"><AdminPage /></ProtectedRoute></Layout>
```

`ProtectedRoute` is a new component created as part of this epic (see AC-27a through AC-27f). It has three states: (1) while auth is loading, renders a spinner (or null); (2) once loaded, redirects unauthenticated users to `/login`; (3) redirects authenticated users without the required role to `/unauthorized`.

---

### 7. `scripts/application-update.php` (CLI Script)

**Location**: `scripts/application-update.php` (outside Apache DocumentRoot)

A standalone PHP script with no web-accessible path.

**Startup sequence:**

1. `PHP_SAPI !== 'cli'` guard — exits with code 2 if not CLI.
2. `require_once` the Composer autoloader.
3. Bootstrap Gravitycar framework: `(new Gravitycar())->bootstrap()`. All bootstrap steps are safe in CLI context. HTTP dispatch only happens in `run()`, which this script never calls.
4. Obtain services via `ContainerConfig::getContainer()`. Do NOT replicate the `ReflectionClass` hack from `setup.php`.
5. Parse arguments with `getopt()`.
6. Validate argument combination; exit 2 with STDERR message on invalid args.
7. Build `CacheRebuildOptions` from parsed flags.
8. Call `AdminService::performCacheRebuild($options)`.
9. Print per-step results to STDOUT (suppressed in quiet mode).
10. Exit 0 on success, 1 on failure.

**Accepted flags:**

| Flag | Meaning |
|------|---------|
| `--metadata` | Include metadata cache component |
| `--routes` | Include routes cache component |
| `--docs` | Include documentation cache component |
| `--navigation` | Include navigation cache components |
| `--all` | Include all four components (overrides individual flags) |
| `--schema` | Run schema migration (only effective when metadata is included) |
| `--permissions` | Rebuild permissions (only effective when metadata is included) |
| `--dry-run` | Show what would happen without executing |
| `-v` | Verbose output (log each individual step) |
| `-q` | Quiet output (errors only) |

**Default behavior (no flags):** Equivalent to `--all --schema --permissions`.

**Output verbosity:**
- Quiet (`-q`): Only errors on STDERR.
- Normal (default): Summary line per component and final success/failure.
- Verbose (`-v`): Each step name and result, including archive path.

**Named exit code constants** at top of file:
- `EXIT_SUCCESS = 0`
- `EXIT_FAILURE = 1`
- `EXIT_INVALID_ARGS = 2`

---

## API Endpoint Specification

### `POST /api/admin/cache/rebuild`

**Authentication**: Required (JWT bearer token)

**Authorization**: `admin` role only

**Response type**: `text/event-stream` (Server-Sent Events over HTTP)

The PHP controller sets the following headers before any output:
```php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
ob_implicit_flush(true);
```

**Request body (JSON):**

```
{
  "components": ["metadata", "routes", "docs", "navigation"],
  "updateSchema": true,
  "updatePermissions": true
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `components` | `string[]` | Yes | Subset of: `metadata`, `routes`, `docs`, `navigation` |
| `updateSchema` | `bool` | No (default false) | Run schema migration after metadata rebuild |
| `updatePermissions` | `bool` | No (default false) | Rebuild permissions after metadata rebuild |

**SSE event format:**

Each event is a JSON object written as:
```
data: {"stepName":"archive","component":"all","status":"in_progress","errorMessage":null}\n\n
```

Progress events are emitted as each step begins and completes. The final event signals completion:
```
data: {"done":true,"success":true,"message":"Cache rebuild completed successfully."}\n\n
```

Or on failure (after archive restore):
```
data: {"done":true,"success":false,"message":"Cache rebuild failed. Archive restored."}\n\n
```

**Step status values**: `in_progress`, `success`, `failed`, `skipped`

**Example event sequence (metadata + routes, updateSchema=true):**
```
data: {"stepName":"archive","component":"all","status":"in_progress","errorMessage":null}

data: {"stepName":"archive","component":"all","status":"success","errorMessage":null}

data: {"stepName":"clear","component":"metadata","status":"in_progress","errorMessage":null}

data: {"stepName":"clear","component":"metadata","status":"success","errorMessage":null}

data: {"stepName":"rebuild","component":"metadata","status":"in_progress","errorMessage":null}

data: {"stepName":"rebuild","component":"metadata","status":"success","errorMessage":null}

data: {"stepName":"schema_update","component":"metadata","status":"in_progress","errorMessage":null}

data: {"stepName":"schema_update","component":"metadata","status":"success","errorMessage":null}

data: {"stepName":"validate","component":"metadata","status":"in_progress","errorMessage":null}

data: {"stepName":"validate","component":"metadata","status":"success","errorMessage":null}

data: {"stepName":"clear","component":"routes","status":"in_progress","errorMessage":null}

...

data: {"done":true,"success":true,"message":"Cache rebuild completed successfully."}

```

**HTTP status codes** (returned before the stream begins):
- 200: Stream started (auth passed, request valid) — success/failure communicated via the final `done` event
- 400: Invalid request body (unknown component name, malformed JSON, empty components array)
- 401: Unauthenticated
- 403: Authenticated but not admin role

---

## UI Wireframe Description

```
/admin

┌──────────────────────────────────────────────────────────┐
│  Admin Panel                                             │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  Cache Management                                        │
│  ─────────────────────────────────────────────────────  │
│  Rebuild one or more cache components. A backup archive  │
│  is created before any files are cleared.               │
│                                                          │
│  Select components to rebuild:                          │
│  [x] Metadata Cache                                     │
│  [x] API Routes Cache                                   │
│  [x] Documentation Cache                               │
│  [x] Navigation Cache                                   │
│                                                          │
│  Additional options (visible when Metadata is checked): │
│  [x] Update Database Schema                             │
│  [x] Update Permissions                                  │
│                                                          │
│  [  Rebuild Cache  ]                                     │
│                                                          │
│  [Result panel appears here after rebuild]               │
│                                                          │
│  ─────────────────────────────────────────────────────  │
│  [Future admin feature section]                         │
│                                                          │
└──────────────────────────────────────────────────────────┘

Confirmation Modal:
┌─────────────────────────────────────┐
│  Confirm Cache Rebuild              │
│                                     │
│  The following will be rebuilt:     │
│  • Metadata Cache                   │
│  • API Routes Cache                 │
│  • Documentation Cache              │
│  • Navigation Cache                 │
│                                     │
│  Additional operations:             │
│  • Database schema will be updated  │
│  • Permissions will be rebuilt      │
│                                     │
│  A backup archive will be created   │
│  before clearing any files.         │
│                                     │
│  [Cancel]   [Rebuild Cache ⚠]       │
└─────────────────────────────────────┘

Result View (shown inside modal after API response):
  ✓ Archive created
  ✓ Metadata: cleared
  ✓ Metadata: rebuilt
  ✓ Metadata: validated
  ✓ Schema updated
  ✓ Permissions rebuilt
  ✓ Routes: cleared
  ✗ Routes: rebuild failed — <error message>
  — Routes: validate (skipped — rebuild failed)
  ...
  [  Close  ]
```

---

## Migration Plan

### Phase 1: New components (no callers changed yet)

1. Create `CacheComponent`, `CacheRebuildOptions`, `CacheRebuildResult` value objects.
2. Create `AdminService`, `CacheArchiver`, and `CacheRebuilder` as three separate classes.
3. Register `AdminService` in `ContainerConfig`.
4. Create `AdminAPIController` and register in `ContainerConfig`.
5. Create React admin panel components and register route in `App.tsx`.
6. Create `scripts/application-update.php`.

### Phase 2: Migrate callers

7. Update `scripts/build/build-backend.sh` (lines 319, 320, 326) to call `scripts/application-update.php`.
8. Update `scripts/deploy/transfer.sh` (line 232: fix path to `scripts/application-update.php`; line 246: call `scripts/application-update.php`).
9. Add deprecation notice at top of `setup.php`.

### Rollback

If the new script fails in CI/CD, the build scripts can temporarily revert to calling `setup.php` by reverting the two shell script changes. The new components do not modify `setup.php`'s behavior.

---

## Security Considerations

1. **CLI guard**: `PHP_SAPI !== 'cli'` at line 1 of `application-update.php`, combined with placement outside the web root (`scripts/`), provides defense in depth against web execution.
2. **Admin-only RBAC**: `AdminAPIController::$rolesAndActions` must be `['admin' => ['*']]`. The Router's `AuthorizationService` enforces this before the controller method is called.
3. **No user-data operations**: The service never creates or modifies user accounts or roles, limiting blast radius.
4. **Archive storage and lifecycle**: Archives are written to the application root (named `cache_YYYY_MM_DD_HH_MM_SS.tar`), `chmod 600` immediately after creation. Archives are deleted after validation succeeds OR after restore completes — they are short-lived safety nets only, never persisted. On `AdminService` initialization, stale `cache_*.tar` files are detected and logged as warnings (not auto-deleted). Verify the app root is above the Apache `DocumentRoot` to prevent web accessibility.
5. **Input validation**: `components` array values in the API request are validated against `CacheComponent::isValid()` before use. Unknown values return a 400 response.
6. **Logging**: Every step is logged via Monolog. Log files must not be web-accessible (verify Apache config excludes log directory).

---

## Technical Context

### Existing patterns to follow

- All new PHP services follow the pattern established in `src/Services/NavigationBuilder.php` and `src/Services/PermissionsBuilder.php`: constructor DI with `$logger` and `$config` properties, methods that throw specific exception classes, results returned as structured data.
- The API controller pattern is established in `src/Api/ApiControllerBase.php`. The 6-param constructor with null defaults and `registerRoutes()` array format must be followed exactly.
- React pages follow the thin-wrapper pattern of `gravitycar-frontend/src/pages/ProjectsPage.tsx` with logic in a component file (`src/components/projects/ProjectsListView.tsx`).
- Route registration follows the `App.tsx` pattern: import page, add `<Route>` element inside `<Routes>`.
- The `apiService` singleton (`services/api.ts`) is the only permitted mechanism for frontend API calls.

### Key integration points

- `CacheRebuilder` calls: `MetadataEngine` (container key `metadata_engine`), `APIRouteRegistry` (container key `api_route_registry`), `OpenAPIGenerator` (container key `openapi_generator`), `NavigationBuilder` (container key `navigation_builder`), `SchemaGenerator` (container key `schema_generator`), `PermissionsBuilder` (container key `permissions_builder`). `APIRouteRegistry::rebuildCache()` writes `cache/api_routes.php` directly.
- `AdminService` is registered in `ContainerConfig` under key `admin_service`. `CacheArchiver` and `CacheRebuilder` are NOT registered in `ContainerConfig` — they are injected as optional constructor parameters on `AdminService` (with null defaults), and instantiated inside the constructor when null.
- `AdminAPIController` is placed in `src/Api/` so `APIControllerFactory` auto-discovers it.
- `application-update.php` bootstraps via `new Gravitycar()` + `->bootstrap()`. All steps are safe in CLI context (routing step only warms the router in DI; HTTP dispatch only happens in `run()` which CLI never calls). Services are obtained via `ContainerConfig::getContainer()`. The `ReflectionClass` hack in `setup.php` is obsolete and MUST NOT be replicated.

### Known bugs this epic fixes

- `setup.php` line 3: `glob('cache/*')` misses `cache/documentation/` — fixed by `RecursiveDirectoryIterator`.
- `scripts/deploy/transfer.sh` line 232: `chmod +x .../scripts/setup.php` — wrong path, file is in root not scripts/ — fixed by updating to `scripts/application-update.php`.
- `setup.php` use of `ReflectionClass` to access route registry — eliminated by calling `APIRouteRegistry::rebuildCache()` directly.
- `setup.php` inconsistent use of `ServiceLocator` vs `ContainerConfig` — all new code uses `ContainerConfig::getContainer()`.

---

## Open Questions

1. **Async vs synchronous rebuild**: The spec assumes a synchronous API call. For very large metadata sets, the rebuild (especially schema migration) may take more than 30 seconds. If this proves to be a timeout issue in practice, a background job / polling pattern may be needed. For now, synchronous is specified. The developer should flag this if preliminary testing shows timeouts.

*All other open questions from Review #1 (Q-89 through Q-100) have been resolved and incorporated into the spec above.*
