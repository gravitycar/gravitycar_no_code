# Implementation Plan: CAT-09 — AdminAPIController SSE Streaming Endpoint

## Spec Context

`AdminAPIController` exposes the cache rebuild operation as an authenticated, admin-only
POST endpoint that streams progress as Server-Sent Events (SSE). It bridges the HTTP layer
to `AdminService::performCacheRebuild()`, which does all real work. The controller validates
the request body, sets SSE headers, then drives the stream by passing an `$onStep` callback
into `AdminService` that emits one SSE event per step. Auth and RBAC enforcement happen
before the controller method is called; the controller never checks auth itself.

Catalog item: CAT-09  
Specification section: Component 5 (AdminAPIController); API Endpoint Specification; AC-21 through AC-26a  
Acceptance criteria addressed:
- AC-21: Registered in `ContainerConfig` and auto-discovered by `APIControllerFactory`.
- AC-22: `$rolesAndActions = ['admin' => ['*']]` — admin-only.
- AC-23: `POST /api/admin/cache/rebuild` — accepts JSON body, responds `text/event-stream`. 400/401/403 before stream.
- AC-24: Each SSE event is JSON with `stepName`, `component`, `status`, `errorMessage`. Final event has `done: true`, `success`, `message`.
- AC-25: Non-admin authenticated request returns 403.
- AC-26: Unauthenticated request returns 401.
- AC-26a: 7-param constructor — 6 standard + `AdminService`.

---

## Dependencies

- **Blocked by**: CAT-07 (`AdminService` — provides `performCacheRebuild()`)
- **Blocked by**: CAT-08 (`ContainerConfig` — registers `AdminAPIController`)
- **Uses**:
  - `Gravitycar\Services\Admin\AdminService` (CAT-07)
  - `Gravitycar\Services\Admin\CacheComponent` (CAT-01)
  - `Gravitycar\Services\Admin\CacheRebuildOptions` (CAT-02)
  - `Gravitycar\Services\Admin\CacheRebuildResult` (CAT-03)
  - `Gravitycar\Services\Admin\CacheStepResult` (CAT-02)
  - `Gravitycar\Api\ApiControllerBase` — base class
  - `Gravitycar\Factories\ModelFactory`
  - `Gravitycar\Contracts\DatabaseConnectorInterface`
  - `Gravitycar\Contracts\MetadataEngineInterface`
  - `Gravitycar\Contracts\CurrentUserProviderInterface`
  - `Gravitycar\Core\Config`
  - `Monolog\Logger`

---

## File Changes

### New Files
- `src/Api/AdminAPIController.php` — admin-only SSE streaming controller

### Modified Files
- none (CAT-08 handles `ContainerConfig` registration)

---

## Implementation Details

### AdminAPIController

**File**: `src/Api/AdminAPIController.php`

**Namespace**: `Gravitycar\Api`

**Extends**: `ApiControllerBase`

**Class property** (overrides base class default — admin-only):
```php
protected array $rolesAndActions = ['admin' => ['*']];
```

**Additional property**:
```php
private AdminService $adminService;
```

---

### Constructor

Follows the exact pattern of `TMDBController` (7-param) and `NavigationAPIController`.
All 7 parameters use null defaults and match the Aura DI `$di->params` wiring in `ContainerConfig`.

```php
public function __construct(
    Logger $logger = null,
    ModelFactory $modelFactory = null,
    DatabaseConnectorInterface $databaseConnector = null,
    MetadataEngineInterface $metadataEngine = null,
    Config $config = null,
    CurrentUserProviderInterface $currentUserProvider = null,
    AdminService $adminService = null
) {
    parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config, $currentUserProvider);
    $container = \Gravitycar\Core\ContainerConfig::getContainer();
    $this->adminService = $adminService ?? $container->get('admin_service');
}
```

**Note**: The null-default pattern for all 7 params matches the `ApiControllerBase` signature and
is required for Aura DI to wire params by name via `$di->params[AdminAPIController::class]`. The
`AdminService` fallback to container mirrors how the base class resolves its own optional params.

---

### `registerRoutes(): array`

Returns one route. `parameterNames` is empty because `/api/admin/cache/rebuild` has no
dynamic `{param}` segments.

```php
public function registerRoutes(): array
{
    return [
        [
            'method'         => 'POST',
            'path'           => '/admin/cache/rebuild',
            'apiClass'       => self::class,
            'apiMethod'      => 'handleCacheRebuild',
            'parameterNames' => [],
            'rbacAction'     => 'rebuild',
        ],
    ];
}
```

**Note on path**: The framework's Router prepends `/api` when dispatching, so the full external
path is `POST /api/admin/cache/rebuild`. Look at how `NavigationAPIController` registers
`/navigation` (not `/api/navigation`) to confirm — the `/api` prefix is added by the router
dispatch layer, not in `registerRoutes()`.

---

### `handleCacheRebuild(): void`

This method outputs SSE directly rather than returning an array. The return type is `void`.

**Execution order**:

1. **Parse request body** — decode JSON; return 400 BEFORE setting SSE headers if malformed.
2. **Validate components** — validate each component with `CacheComponent::isValid()`; return 400 if any unknown or if array is empty.
3. **Set SSE headers** — `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`; call `ob_implicit_flush(true)`. Headers are set BEFORE calling the service, so all subsequent errors (including archive failure) are communicated as `done:false` SSE events, not as Router 500s.
4. **Build options** — `CacheRebuildOptions::fromArray($data)`.
5. **Define `$onStep` callback** — closure that calls `$this->emitEvent($step->toArray())`.
6. **Call service** — `$this->adminService->performCacheRebuild($options, $onStep)`.
7. **Emit final done event** — using `$result->toArray()` merged with `['done' => true]`.
8. **Exit** — call `exit(0)` after emitting the final SSE event. This prevents the Router from appending `null` to the output and overwriting the `Content-Type` header.

```php
public function handleCacheRebuild(): void
{
    $data = $this->parseAndValidateRequestBody();
    if ($data === null) {
        return; // 400 already sent
    }

    $this->setSseHeaders();

    $options = CacheRebuildOptions::fromArray($data);

    $onStep = function (CacheStepResult $step): void {
        $this->emitEvent($step->toArray());
    };

    try {
        $result = $this->adminService->performCacheRebuild($options, $onStep);
        $finalEvent = array_merge($result->toArray(), ['done' => true]);
        $this->emitEvent($finalEvent);
    } catch (\Throwable $e) {
        // Archive failure: AdminService already emitted failed('archive','all') via $onStep.
        // Emit the final done:false event and terminate cleanly.
        $this->emitEvent([
            'done'    => true,
            'success' => false,
            'message' => 'Cache rebuild failed: ' . $e->getMessage(),
            'steps'   => [],
        ]);
    }

    exit(0); // Prevent Router from appending null and overwriting Content-Type
}
```

---

### `parseAndValidateRequestBody(): ?array` (private)

Returns the decoded data array on success. On failure, sends a 400 JSON response and returns `null`.

```php
private function parseAndValidateRequestBody(): ?array
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);

    if (!is_array($data)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid JSON request body']);
        return null;
    }

    $components = $data['components'] ?? [];

    if (empty($components)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'components array must not be empty']);
        return null;
    }

    foreach ($components as $component) {
        if (!CacheComponent::isValid($component)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => "Unknown cache component: $component"]);
            return null;
        }
    }

    return $data;
}
```

---

### `setSseHeaders(): void` (private)

```php
private function setSseHeaders(): void
{
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    ob_implicit_flush(true);
}
```

---

### `emitEvent(array $data): void` (private)

Writes one SSE frame. Each call flushes immediately so the browser receives the event
without waiting for subsequent events.

```php
private function emitEvent(array $data): void
{
    echo 'data: ' . json_encode($data) . "\n\n";
    flush();
}
```

---

### Full class structure summary

```
AdminAPIController
  Properties:
    protected array $rolesAndActions = ['admin' => ['*']]
    private AdminService $adminService

  Constructor (7 params, all null-default):
    parent::__construct(6 standard params)
    $this->adminService = $adminService ?? container->get('admin_service')

  Public:
    registerRoutes(): array
    handleCacheRebuild(): void

  Private:
    parseAndValidateRequestBody(): ?array
    setSseHeaders(): void
    emitEvent(array $data): void
```

Estimated file length: ~130 lines — well under the 300-line limit.

---

### Authentication and Authorization

Auth (401) and RBAC (403) are enforced by the framework's `Router` and `AuthorizationService`
BEFORE `handleCacheRebuild()` is called. The controller does not check auth itself.

- `$rolesAndActions = ['admin' => ['*']]` declares the RBAC constraint.
- `PermissionsBuilder::buildAllControllerPermissions()` auto-discovers all `ApiControllerBase`
  subclasses (including `AdminAPIController`) and populates the `roles_permissions` table.
- The Router calls `AuthorizationService::hasPermissionForRoute()` before dispatching. If the
  check fails, the Router returns 401 or 403 before the controller method is invoked.
- Result: `handleCacheRebuild()` always runs in an authenticated admin context.

---

### SSE Event Format

**Progress event** (emitted by `$onStep` callback):
```
data: {"stepName":"archive","component":"all","status":"in_progress","errorMessage":null}\n\n
```

The `CacheStepResult::toArray()` method (defined in CAT-02) produces:
```php
[
    'stepName'     => 'archive',
    'component'    => 'all',
    'status'       => 'in_progress',   // or 'success', 'failed', 'skipped'
    'errorMessage' => null,
]
```

**Final done event** (emitted after `performCacheRebuild()` returns):
```
data: {"stepName":..., "steps":[...], "success":true, "message":"...", "done":true}\n\n
```

The `CacheRebuildResult::toArray()` (defined in CAT-03) produces the serialized result.
The controller merges `['done' => true]` into that array for the final event.

---

### Error Handling

| Condition | Action |
|-----------|--------|
| Malformed JSON body | `parseAndValidateRequestBody()` → 400 JSON, return null, no SSE |
| Empty `components` array | `parseAndValidateRequestBody()` → 400 JSON, return null, no SSE |
| Unknown component name | `parseAndValidateRequestBody()` → 400 JSON, return null, no SSE |
| `CacheRebuildOptions::fromArray()` throws `InvalidArgumentException` | Uncaught — bubbles to Router's exception handler → 500. In practice, guarded by the 400 checks above. |
| `AdminService::performCacheRebuild()` archive phase throws | Caught by the `try/catch` in `handleCacheRebuild()`. Since SSE headers are already set, the final `done:false` event is emitted as an SSE event (not a Router 500). `exit(0)` is called after emitting. |
| `AdminService` step failure (clear/rebuild/validate) | Handled inside `AdminService` — restore is called, failure result returned. The controller emits the failure steps as they arrive via `$onStep`, then emits the final `done: false` event. No exception reaches the controller. |
| `AdminService::performCacheRebuild()` returns with `success=false` | `$result->toArray()` carries `success=false` and failure details. Controller emits `done: true, success: false` final event. |

---

## Unit Test Specifications

**Test file**: `tests/Unit/Api/AdminAPIControllerTest.php`

**Setup**: Mock `AdminService`, `Logger`, `Config`. Inject all via constructor. Use output
buffering to capture SSE output.

```php
$logger       = $this->createMock(Logger::class);
$adminService = $this->createMock(AdminService::class);

$controller = new AdminAPIController(
    $logger, null, null, null, null, null, $adminService
);
```

### `registerRoutes()` — route registration

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Returns single route | Call `registerRoutes()` | Array with 1 element | Only one endpoint defined |
| Route method is POST | Inspect route | `'method' => 'POST'` | AC-23 |
| Route path | Inspect route | `'path' => '/admin/cache/rebuild'` | AC-23 |
| Route handler | Inspect route | `'apiMethod' => 'handleCacheRebuild'` | Wiring |
| parameterNames is empty | Inspect route | `'parameterNames' => []` | No dynamic params |
| rbacAction | Inspect route | `'rbacAction' => 'rebuild'` | AC-22 RBAC |

### `$rolesAndActions` — admin-only restriction

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Only admin role | Read property | `['admin' => ['*']]` | AC-22 |
| Not a superset | Read property | No other role keys | Admin-only |

### `handleCacheRebuild()` — validation (400 paths, pre-SSE)

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Malformed JSON | `php://input` = `"not-json"` | 400 response, JSON error, no SSE output | AC-23 |
| Empty components | `{"components":[]}` | 400 response, JSON error message | AC-15a |
| Unknown component | `{"components":["invalid"]}` | 400 response with unknown component message | AC-23 |
| Valid input | `{"components":["metadata"]}` | SSE headers set, service called | Happy path |

**Testing 400 paths**: Use `runkit` or override `file_get_contents` via a testable wrapper.
Alternatively, extract the request body reading into a protected method that can be overridden
in a test subclass — `protected function getRawRequestBody(): string`.

### `handleCacheRebuild()` — happy path SSE output

**Setup**: Mock `AdminService::performCacheRebuild()` to:
1. Call `$onStep` with a `CacheStepResult::inProgress('archive', 'all')`.
2. Call `$onStep` with a `CacheStepResult::success('archive', 'all')`.
3. Return a `CacheRebuildResult` with `isSuccess()=true`.

**Action**: Call `handleCacheRebuild()` with output buffering; capture stdout.

**Expected**:
- Output contains `data: {"stepName":"archive","component":"all","status":"in_progress"...}\n\n`
- Output contains a second archive success event.
- Output ends with `data: {...,"done":true,...}\n\n`
- `adminService->performCacheRebuild()` called once.

**Why**: Validates SSE framing format (AC-24) and that `$onStep` emits correctly.

### `handleCacheRebuild()` — failure path SSE output

**Setup**: Mock `AdminService::performCacheRebuild()` to:
1. Call `$onStep` with a `CacheStepResult::failed('clear', 'metadata', 'Disk error')`.
2. Return a `CacheRebuildResult` with `isSuccess()=false`.

**Expected**:
- Output contains a failed step event with `"status":"failed"` and `"errorMessage":"Disk error"`.
- Final event contains `"done":true,"success":false`.

**Why**: Validates that failure paths are correctly streamed (AC-24, AC-32a).

### Key Scenario: Validate 400 before SSE headers

**Setup**: Mock request body as `{"components":[]}`.

**Action**: Call `handleCacheRebuild()`.

**Expected**:
- HTTP response code is 400.
- `Content-Type: application/json` (not `text/event-stream`).
- `AdminService::performCacheRebuild()` is NOT called.

**Why**: AC-23 — "Auth and input validation errors are returned as normal HTTP status codes
before the stream begins." SSE headers must NOT be set if validation fails.

---

## Notes

- The controller does NOT call `header('HTTP/1.1 200 OK')` explicitly — the 200 is implicit
  when SSE headers are sent without a prior `http_response_code(4xx)`.
- The route path in `registerRoutes()` is `/admin/cache/rebuild` (without `/api` prefix).
  The framework's Router prepends `/api` during dispatch. Verify this by checking how other
  controllers register their routes (e.g., `NavigationAPIController` uses `/navigation`, not
  `/api/navigation`).
- `ob_implicit_flush(true)` is set in `setSseHeaders()`. This disables output buffering so each
  `echo` in `emitEvent()` is sent immediately without needing `ob_flush()`.
- `flush()` is called after each `echo` in `emitEvent()` as defense-in-depth (nginx proxy, etc.).
- The `handleCacheRebuild()` method calls `exit(0)` after emitting the final SSE event. This
  is the resolved solution to the Router void-return problem: `exit(0)` terminates the PHP
  process cleanly before the Router can append `null` or attempt to set a JSON `Content-Type`
  header. No verification of Router behavior is needed — `exit(0)` is deterministic.
- SSE headers are set **before** `performCacheRebuild()` is called (step 3 above). Archive
  failures are therefore communicated as `done:false` SSE events, not as Router 500 responses.
- `APIControllerFactory` auto-discovers `AdminAPIController` by scanning `src/Api/` for files
  that extend `ApiControllerBase`. No manual registration in the factory is needed — only the
  `ContainerConfig` registration (handled by CAT-08).
- The `getRawRequestBody()` protected method pattern is recommended to make unit testing of the
  400 validation paths possible without mocking global functions.
