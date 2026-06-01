# Implementation Catalog: Admin Panel and Improved Application Update Script

## Catalog Items

---

### CAT-01: CacheComponent constants class

- **Purpose**: Define the four named cache component identifiers used throughout the system, plus validation and enumeration helpers.
- **Scope**:
  - `src/Services/Admin/CacheComponent.php` (create)
- **Description**: A constants-only class with `METADATA`, `ROUTES`, `DOCS`, and `NAVIGATION` string constants, a static `all(): array` method returning all four, and a static `isValid(string $component): bool` method for validation. No business logic.
- **Blocked by**: _(none)_

---

### CAT-02: Value objects — CacheRebuildOptions and CacheStepResult

- **Purpose**: Provide the immutable input/output value objects that all cache rebuild logic reads and writes.
- **Scope**:
  - `src/Services/Admin/CacheRebuildOptions.php` (create)
  - `src/Services/Admin/CacheStepResult.php` (create)
- **Description**: `CacheRebuildOptions` is an immutable value object with boolean properties (`dryRun`, `updateSchema`, `updatePermissions`) and an array property `components`. Throws `InvalidArgumentException` if `components` is empty. Provides static factory methods `all()` and `fromArray(array $data)`. `CacheStepResult` is a value object recording `stepName`, `component`, `status` (success/failed/skipped), and optional `errorMessage`. No business logic in either class.
- **Blocked by**: CAT-01

---

### CAT-03: CacheRebuildResult value object

- **Purpose**: Provide the output container that `AdminService::performCacheRebuild()` returns to all callers.
- **Scope**:
  - `src/Services/Admin/CacheRebuildResult.php` (create)
- **Description**: An immutable value object holding an array of `CacheStepResult` objects. Exposes `isSuccess(): bool` (true only when no step has `failed` status), `getSteps(): array`, and `toArray(): array` (serializable for JSON responses). Constructed by `AdminService`; read by `AdminAPIController` and `application-update.php`.
- **Blocked by**: CAT-02

---

### CAT-04: AdminServiceException

- **Purpose**: Provide the specific exception class used by admin services to signal recoverable and non-recoverable errors with context and logging.
- **Scope**:
  - `src/Services/Admin/AdminServiceException.php` (create)
- **Description**: Extends the framework's base exception class (following the pattern of other domain-specific exceptions in the codebase). Carries a message and optional context array. Used by `CacheArchiver` and `CacheRebuilder` when operations fail. Caught and recorded by `AdminService` into `CacheRebuildResult`.
- **Blocked by**: _(none)_

---

### CAT-05: CacheArchiver class

- **Purpose**: Encapsulate all tar archive operations: create, chmod, verify, restore, delete, and scan for stale archives.
- **Scope**:
  - `src/Services/Admin/CacheArchiver.php` (create)
- **Description**: Has `$logger` (Monolog Logger) and `$config` (Config) properties injected via constructor. Public interface: `create(array $components): string` (creates timestamped `cache_YYYY_MM_DD_HH_MM_SS.tar` in the app root, runs `chmod 600`, verifies the file exists with non-zero size, returns the archive path; throws `AdminServiceException` on failure), `restore(string $archiveFilePath): void`, `delete(string $archiveFilePath): void`, `verify(string $archiveFilePath): bool`, `scanForStaleArchives(): array`. Logs the start, completion, and any error of each operation. No business logic — only file I/O and tar commands.
- **Blocked by**: CAT-04

---

### CAT-06: CacheRebuilder class

- **Purpose**: Encapsulate all cache file operations: clear cache files recursively, call engine services to rebuild each component, and run php -l syntax validation.
- **Scope**:
  - `src/Services/Admin/CacheRebuilder.php` (create)
- **Description**: Has `$logger` and `$config` properties. Constructor accepts engine service references retrieved from `ContainerConfig::getContainer()` (MetadataEngine, APIRouteRegistry, OpenAPIGenerator, NavigationBuilder, SchemaGenerator, PermissionsBuilder). Public interface: `clear(array $components): void` (uses `RecursiveDirectoryIterator`, removes files only, not directories), `rebuild(array $components, bool $updateSchema, bool $updatePermissions): void` (calls the appropriate service per component; also calls `SchemaGenerator::generateSchema()` and `PermissionsBuilder::buildAllPermissions()` when applicable), `validate(array $components): void` (runs `php -l` on every PHP file in selected component cache paths; throws `AdminServiceException` if any file fails). Logs start, completion, and errors per operation.
- **Blocked by**: CAT-04

---

### CAT-07: AdminService orchestration class

- **Purpose**: Coordinate the full archive → clear → rebuild → validate → delete archive lifecycle, delegating each phase to CacheArchiver or CacheRebuilder.
- **Scope**:
  - `src/Services/Admin/AdminService.php` (create)
- **Description**: Has `$logger` and `$config` properties. Constructor signature: `__construct(Logger $logger, Config $config, ?CacheArchiver $archiver = null, ?CacheRebuilder $rebuilder = null)` — instantiates `CacheArchiver` and `CacheRebuilder` internally when null. On construction, calls `CacheArchiver::scanForStaleArchives()` and logs a warning per stale file. Public method `performCacheRebuild(CacheRebuildOptions $options): CacheRebuildResult` throws `InvalidArgumentException` if `components` is empty, then executes the ordered phase sequence. On any failure in clear/rebuild/validate, stops immediately, calls `CacheArchiver::restore()`, calls `CacheArchiver::delete()`, records failure in the result, and returns (does not re-throw). On success, calls `CacheArchiver::delete()` after validation passes. In dry-run mode, all steps are skipped and recorded as `skipped`. Uses `ContainerConfig::getContainer()` exclusively; no `ServiceLocator` or `ReflectionClass`.
- **Blocked by**: CAT-03, CAT-05, CAT-06

---

### CAT-08: ContainerConfig update — register AdminService

- **Purpose**: Wire AdminService into the Aura DI container so it is injectable by AdminAPIController and retrievable by the CLI script.
- **Scope**:
  - `src/Core/ContainerConfig.php` (modify)
- **Description**: Add a `$di->set('admin_service', ...)` block registering `AdminService` with `logger` and `config` as lazy dependencies. `CacheArchiver` and `CacheRebuilder` are NOT registered — they are instantiated internally by `AdminService`. `AdminAPIController` registration block is also added here (see CAT-09). Follow the existing pattern for services like `NavigationBuilder` and `PermissionsBuilder`.
- **Blocked by**: CAT-07

---

### CAT-09: AdminAPIController — SSE streaming endpoint

- **Purpose**: Expose the cache rebuild operation as an authenticated, admin-only POST endpoint that streams progress as Server-Sent Events.
- **Scope**:
  - `src/Api/AdminAPIController.php` (create)
- **Description**: Extends `ApiControllerBase`. Sets `$rolesAndActions = ['admin' => ['*']]`. Constructor accepts up to 7 optional DI parameters: the 6 standard params (logger, modelFactory, databaseConnector, metadataEngine, config, currentUserProvider) plus `AdminService` as the 7th (null default, resolved from container when null). `registerRoutes()` returns one route: `POST /api/admin/cache/rebuild` → `handleCacheRebuild`, rbacAction `rebuild`. `handleCacheRebuild()`: validates request body (400 on unknown component or empty array), builds `CacheRebuildOptions::fromArray()`, sets SSE response headers (`Content-Type: text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`, `ob_implicit_flush(true)`), then calls `AdminService::performCacheRebuild()`. Emits one SSE event per step as steps begin and complete; emits a final `{"done":true,...}` event. Auth/RBAC errors (401/403) are returned as normal HTTP status codes before the stream begins. Placed in `src/Api/` so `APIControllerFactory` auto-discovers it.
- **Blocked by**: CAT-07, CAT-08

---

### CAT-10: CLI script — scripts/application-update.php

- **Purpose**: Provide a safe, CLI-only entry point for coding agents and deployment pipelines to trigger cache rebuilds without web access.
- **Scope**:
  - `scripts/application-update.php` (create)
- **Description**: First executable statement checks `PHP_SAPI !== 'cli'` and exits with code 2 (writing to STDERR) if not CLI. Then: `require_once` Composer autoloader, bootstrap via `(new Gravitycar())->bootstrap()` (never calls `run()`), obtain `AdminService` via `ContainerConfig::getContainer()->get('admin_service')`. Parse flags with `getopt()`: `--metadata`, `--routes`, `--docs`, `--navigation`, `--all`, `--schema`, `--permissions`, `--dry-run`, `-v`, `-q`. Default (no flags) = `--all --schema --permissions`. Build `CacheRebuildOptions`, call `AdminService::performCacheRebuild()`, print per-step results to STDOUT (suppressed with `-q`), write errors to STDERR. Exit codes: 0 success, 1 failure, 2 invalid args. Define named constants `EXIT_SUCCESS`, `EXIT_FAILURE`, `EXIT_INVALID_ARGS` at the top of the file. No `ReflectionClass` hacks.
- **Blocked by**: CAT-07, CAT-08

---

### CAT-11: Migrate build/deploy shell scripts and deprecate setup.php

- **Purpose**: Update all callers of setup.php to use the new application-update.php, and add a deprecation notice to setup.php.
- **Scope**:
  - `scripts/build/build-backend.sh` (modify — lines 319, 320, 326)
  - `scripts/deploy/transfer.sh` (modify — lines 232, 246)
  - `setup.php` (modify — add deprecation comment at top)
- **Description**: In `build-backend.sh`, update the three references to `setup.php` inside `rebuild_cache()` to call `scripts/application-update.php` instead. In `transfer.sh`, fix the path bug on line 232 (change `chmod` target from `scripts/setup.php` to `scripts/application-update.php`) and update line 246 to call `scripts/application-update.php`. Add a PHP deprecation comment block at the top of `setup.php` stating it is deprecated in favour of `scripts/application-update.php`; do NOT delete the file.
- **Blocked by**: CAT-10

---

### CAT-12: handleAuthError shared utility

- **Purpose**: Extract the 401/403 redirect logic from the axios interceptor into a shared utility so the SSE fetch path can reuse it without duplicating logic.
- **Scope**:
  - `gravitycar-frontend/src/utils/authError.ts` (create)
  - `gravitycar-frontend/src/services/api.ts` (modify — import and call the shared utility in existing interceptor)
- **Description**: Create a `handleAuthError(status: number): void` function that: on 401, clears localStorage and uses `imperativeNavigate` to redirect to `/login`; on 403, navigates to `/unauthorized`. Update the existing axios error interceptor in `api.ts` to delegate to this shared function. The `ConfirmRebuildModal` component will call this function when checking SSE stream response status codes before reading the body.
- **Blocked by**: _(none)_

---

### CAT-13: ProtectedRoute component

- **Purpose**: Provide a reusable route-guard component that handles the unauthenticated and insufficient-role redirect cases in one place.
- **Scope**:
  - `gravitycar-frontend/src/components/ProtectedRoute.tsx` (create)
- **Description**: Accepts two props: `children` (ReactNode, required) and `requiredRole` (string, optional). While `isLoading` is true from `useAuth()`, renders a loading spinner (or null). Once loaded: if not authenticated, redirects to `/login`; if `requiredRole` is provided and `user.user_type` does not match, redirects to `/unauthorized`; otherwise renders children. Uses React Router `<Navigate>` for redirects. Tailwind CSS only, no external libraries.
- **Blocked by**: _(none)_

---

### CAT-14: ConfirmRebuildModal component

- **Purpose**: Implement the three-state confirmation/streaming/result modal that shows real-time SSE progress and final outcome to the admin user.
- **Scope**:
  - `gravitycar-frontend/src/components/admin/ConfirmRebuildModal.tsx` (create)
- **Description**: Three internal views: (1) Confirmation view — lists selected components and secondary operations, "Rebuild Cache" (danger-styled) and "Cancel" buttons; (2) Streaming view — "Rebuild Cache" button replaced with spinner, step list populates in real time (spinner while in_progress → checkmark/X/dash on completion), no cancel possible; (3) Result view — spinner replaced with summary message ("Rebuild complete" or "Rebuild failed — archive restored"), "Close" button dismisses. Uses `fetch` with `response.body.getReader()` (NOT EventSource) for SSE streaming. Before reading the stream, checks `response.status` and calls `handleAuthError(status)` from `utils/authError.ts` on 401 or 403. Parses each `data: {...}` line as JSON and appends to step list state. On receipt of `{"done":true}`, transitions to result view. Tailwind CSS only.
- **Blocked by**: CAT-12

---

### CAT-15: CacheManagementPanel component

- **Purpose**: Implement the cache component selection UI with conditional schema/permissions options and the button that opens the confirmation modal.
- **Scope**:
  - `gravitycar-frontend/src/components/admin/CacheManagementPanel.tsx` (create)
- **Description**: Manages state for: which of the four cache components are selected (all checked by default), whether updateSchema and updatePermissions are checked (checkboxes visible and checked by default when METADATA is selected, hidden otherwise), whether the confirmation modal is open, and loading state. Renders: "Cache Management" heading, brief description, one labeled checkbox per component, conditional "Update Database Schema" and "Update Permissions" checkboxes, and a "Rebuild Cache" button that sets `modalOpen = true`. Passes selection state down to `ConfirmRebuildModal` as props. Tailwind CSS only, no external UI libraries.
- **Blocked by**: CAT-14

---

### CAT-16: AdminPage and route registration

- **Purpose**: Wire the admin panel into the React SPA with proper auth protection and layout wrapping.
- **Scope**:
  - `gravitycar-frontend/src/pages/AdminPage.tsx` (create)
  - `gravitycar-frontend/src/App.tsx` (modify — add `/admin` route)
- **Description**: `AdminPage.tsx` is a section-based layout page that renders a heading "Admin Panel" and renders `CacheManagementPanel` inside a content section. The layout is designed to accommodate future admin feature panels (e.g., User Management, System Status) as additional sections. In `App.tsx`, add the route: `/admin` → `<Layout><ProtectedRoute requiredRole="admin"><AdminPage /></ProtectedRoute></Layout>`. Import all required components. Tailwind CSS only.
- **Blocked by**: CAT-13, CAT-15

---

## Dependency Graph

```
CAT-01 (CacheComponent)
  └─► CAT-02 (CacheRebuildOptions + CacheStepResult)
        └─► CAT-03 (CacheRebuildResult)
              └─► CAT-07 (AdminService) ◄── CAT-05 (CacheArchiver) ◄── CAT-04 (AdminServiceException)
                    │                   ◄── CAT-06 (CacheRebuilder) ◄── CAT-04
                    └─► CAT-08 (ContainerConfig update)
                          ├─► CAT-09 (AdminAPIController)
                          └─► CAT-10 (application-update.php)
                                └─► CAT-11 (Migrate shell scripts + deprecate setup.php)

CAT-04 (AdminServiceException) — standalone, blocks CAT-05, CAT-06

CAT-12 (handleAuthError utility) — standalone, blocks CAT-14
CAT-13 (ProtectedRoute) — standalone, blocks CAT-16
CAT-14 (ConfirmRebuildModal) ◄── CAT-12, blocks CAT-15
CAT-15 (CacheManagementPanel) ◄── CAT-14, blocks CAT-16
CAT-16 (AdminPage + route) ◄── CAT-13, CAT-15
```

## Build Order (suggested)

**Backend (Phase 1 — value objects and helpers):**
1. CAT-01, CAT-04 (parallel — no dependencies)
2. CAT-02 (depends on CAT-01)
3. CAT-03 (depends on CAT-02)
4. CAT-05, CAT-06 (parallel — both depend on CAT-04)
5. CAT-07 (depends on CAT-03, CAT-05, CAT-06)
6. CAT-08 (depends on CAT-07)
7. CAT-09, CAT-10 (parallel — both depend on CAT-07, CAT-08)
8. CAT-11 (depends on CAT-10)

**Frontend (Phase 2 — can start in parallel with backend Phase 1):**
1. CAT-12, CAT-13 (parallel — no dependencies)
2. CAT-14 (depends on CAT-12)
3. CAT-15 (depends on CAT-14)
4. CAT-16 (depends on CAT-13, CAT-15)
