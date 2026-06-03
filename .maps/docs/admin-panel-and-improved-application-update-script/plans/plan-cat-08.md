# Implementation Plan: CAT-08 — ContainerConfig Update — Register AdminService

## Spec Context

`ContainerConfig` is the central Aura DI wiring class for the Gravitycar framework. This plan
adds two new registrations to `configureApplicationServices()`: one for `admin_service`
(the `AdminService` orchestrator) and one for `admin_api_controller` (the `AdminAPIController`
SSE endpoint). Both registrations follow the established patterns for other services and controllers
already present in the file.

Catalog item: CAT-08  
Specification section: AC-11, AC-21, AC-26a; Component 4a (AdminService constructor), Component 5 (AdminAPIController)  
Acceptance criteria addressed:
- AC-11: `AdminService` uses `ContainerConfig::getContainer()`. No `ServiceLocator`, no `ReflectionClass`.
- AC-21: `AdminAPIController` is registered in `ContainerConfig` and auto-discovered by `APIControllerFactory`.
- AC-26a: `AdminAPIController` accepts 7 constructor parameters (6 standard + `AdminService` as 7th), consistent with `OpenAPIController`, `NavigationAPIController`, and `TMDBController`.

---

## Dependencies

- **Blocked by**: CAT-07 (`AdminService` must exist before it can be registered)
- **Blocked by**: CAT-09 (`AdminAPIController` is registered here — its class must exist by build time)
- **Blocks**: CAT-09 (though CAT-09 is planned before this is built; the class must exist when built)
- **Blocks**: CAT-10 (`scripts/application-update.php` gets `AdminService` from the container)
- **Uses**:
  - `Gravitycar\Services\Admin\AdminService` (CAT-07)
  - `Gravitycar\Api\AdminAPIController` (CAT-09)
  - `src/Core/ContainerConfig.php` — existing file, modified in place

---

## File Changes

### New Files
- none

### Modified Files
- `src/Core/ContainerConfig.php` — add four registration blocks in `configureApplicationServices()`:
  1. `cache_archiver` → `CacheArchiver` with `logger` and `config`
  2. `cache_rebuilder` → `CacheRebuilder` with `logger`, `config`, and all six engine services from the container
  3. `admin_service` → `AdminService` with `logger`, `config`, `cacheArchiver`, and `cacheRebuilder` (all from container)
  4. `admin_api_controller` → `AdminAPIController` with all 7 params (6 standard + `adminService`)

---

## Implementation Details

### Locating the insertion point

Read `src/Core/ContainerConfig.php`. The `configureApplicationServices()` method runs from
approximately line 263 to line 582. New registrations should be added at the **end of
`configureApplicationServices()`**, immediately before the closing `$di->set('open_api_generator', ...)` /
`$di->set('open_api_generator', ...)` alias block (currently the last block in the method).

Specifically, insert the two new blocks **after** the `open_api_generator` alias line (line 581):

```php
// Alias for OpenAPIGenerator service (APIControllerFactory expects underscore naming)
$di->set('open_api_generator', $di->lazyGet('openapi_generator'));

// ↓ INSERT HERE ↓

// CacheArchiver — handles all tar archive operations for cache rebuild safety
$di->set('cache_archiver', $di->lazyNew(\Gravitycar\Services\Admin\CacheArchiver::class));
$di->params[\Gravitycar\Services\Admin\CacheArchiver::class] = [
    'logger' => $di->lazyGet('logger'),
    'config' => $di->lazyGet('config'),
];

// CacheRebuilder — handles recursive clear, engine rebuild, and php -l validation
$di->set('cache_rebuilder', $di->lazyNew(\Gravitycar\Services\Admin\CacheRebuilder::class));
$di->params[\Gravitycar\Services\Admin\CacheRebuilder::class] = [
    'logger'             => $di->lazyGet('logger'),
    'config'             => $di->lazyGet('config'),
    'metadataEngine'     => $di->lazyGet('metadata_engine'),
    'apiRouteRegistry'   => $di->lazyGet('api_route_registry'),
    'openAPIGenerator'   => $di->lazyGet('openapi_generator'),
    'navigationBuilder'  => $di->lazyGet('navigation_builder'),
    'schemaGenerator'    => $di->lazyGet('schema_generator'),
    'permissionsBuilder' => $di->lazyGet('permissions_builder'),
];

// AdminService — orchestrates cache rebuild lifecycle (singleton: stale scan runs once per request)
$di->set('admin_service', $di->lazyNew(\Gravitycar\Services\Admin\AdminService::class));
$di->params[\Gravitycar\Services\Admin\AdminService::class] = [
    'logger'    => $di->lazyGet('logger'),
    'config'    => $di->lazyGet('config'),
    'archiver'  => $di->lazyGet('cache_archiver'),
    'rebuilder' => $di->lazyGet('cache_rebuilder'),
];

// AdminAPIController — admin-only cache rebuild SSE endpoint
$di->set('admin_api_controller', $di->lazyNew(\Gravitycar\Api\AdminAPIController::class));
$di->params[\Gravitycar\Api\AdminAPIController::class] = [
    'logger'              => $di->lazyGet('logger'),
    'modelFactory'        => $di->lazyGet('model_factory'),
    'databaseConnector'   => $di->lazyGet('database_connector'),
    'metadataEngine'      => $di->lazyGet('metadata_engine'),
    'config'              => $di->lazyGet('config'),
    'currentUserProvider' => $di->lazyGet('current_user_provider'),
    'adminService'        => $di->lazyGet('admin_service'),
];
```

### Why `CacheArchiver` and `CacheRebuilder` are now registered in `ContainerConfig`

`AdminService` now requires `CacheArchiver` and `CacheRebuilder` as non-nullable constructor
parameters. Both collaborators must be pre-built before `AdminService` is constructed.
`ContainerConfig` uses `lazyNew()` (lazy singleton) for all three, so:
1. The stale archive scan in `AdminService`'s constructor runs exactly once per container lifetime (once per request).
2. `CacheRebuilder` gets all six engine services from the container with no internal `ContainerConfig::getContainer()` calls.
3. Test mocks are injected directly into the constructor — no container priming needed.

### Pattern reference: TMDBController

The `tmdb_controller` registration (lines 487–496 of `ContainerConfig.php`) is the closest
existing example — 6 standard params plus one extra service-specific param (`tmdbService`):

```php
$di->set('tmdb_controller', $di->lazyNew(\Gravitycar\Api\TMDBController::class));
$di->params[\Gravitycar\Api\TMDBController::class] = [
    'logger'              => $di->lazyGet('logger'),
    'modelFactory'        => $di->lazyGet('model_factory'),
    'databaseConnector'   => $di->lazyGet('database_connector'),
    'metadataEngine'      => $di->lazyGet('metadata_engine'),
    'config'              => $di->lazyGet('config'),
    'currentUserProvider' => $di->lazyGet('current_user_provider'),
    'tmdbService'         => $di->lazyGet('movie_tmdb_integration_service'),
];
```

`admin_api_controller` follows exactly this shape, substituting `adminService` for `tmdbService`.

### Pattern reference: NavigationBuilder (service-only registration)

The `navigation_builder` registration (lines 341–348) is an example of a service with just
`logger` + domain services:

```php
$di->set('navigation_builder', $di->lazyNew(\Gravitycar\Services\NavigationBuilder::class));
$di->params[\Gravitycar\Services\NavigationBuilder::class] = [
    'logger'               => $di->lazyGet('logger'),
    'metadataEngine'       => $di->lazyGet('metadata_engine'),
    ...
];
```

`admin_service` follows the same pattern, using `lazyNew()` + explicit `$di->params[]` block.

### Use statements to add

Add the following `use` statements at the top of `ContainerConfig.php`, in alphabetical order
within the `use` block. The existing `use` block ends around line 22. Add:

```php
use Gravitycar\Services\Admin\AdminService;
use Gravitycar\Api\AdminAPIController;
```

Insert them after the existing `use Gravitycar\Services\AuthorizationService;` line, maintaining
alphabetical sort order:

```php
use Gravitycar\Api\AdminAPIController;                // ← new
use Gravitycar\Services\Admin\AdminService;           // ← new
use Gravitycar\Services\Admin\CacheArchiver;          // ← new
use Gravitycar\Services\Admin\CacheRebuilder;         // ← new
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Services\AuthorizationService;
```

Note: since the two new registrations use fully-qualified class names in `$di->lazyNew()` and
`$di->params[]`, the `use` statements are technically optional (FQCN references work without them).
However, adding them keeps the file consistent with the project's PSR-12 style and makes the
class references shorter in the IDE.

---

## Error Handling

| Condition | Action |
|-----------|--------|
| `AdminService` class does not exist at container build time | Aura DI throws at the point where `$di->get('admin_service')` is first called, not at registration time (lazy). The `buildContainer()` try/catch logs and falls back to `buildFallbackContainer()`. |
| `AdminAPIController` class does not exist | Same lazy resolution — only throws when the controller factory requests it. |
| `CacheArchiver` or `CacheRebuilder` class not found | Aura DI throws at the point where `lazyNew()` is resolved. Caught by `buildContainer()` try/catch, falls back to `buildFallbackContainer()`. |

---

## Unit Test Specifications

There are no new test-worthy behaviors introduced by this plan alone — it is pure wiring code.
The integration test for this plan is verified indirectly by:

1. `AdminServiceTest` (CAT-07 plan) — verifies that `AdminService` can be constructed with mocked dependencies.
2. `AdminAPIControllerTest` (CAT-09 plan) — verifies that the controller can be constructed with mocked DI params.

However, a minimal smoke test for the container registration is appropriate:

**Test file**: `tests/Unit/Core/ContainerConfigAdminServiceTest.php`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| `admin_service` key resolves | Call `ContainerConfig::getContainer()->get('admin_service')` | Returns an `AdminService` instance | Verifies lazy registration |
| `admin_api_controller` key resolves | Call `ContainerConfig::getContainer()->get('admin_api_controller')` | Returns an `AdminAPIController` instance | Verifies 7-param registration |
| `admin_service` params are correct type | Retrieve and check `$adminService` | Is instance of `AdminService`, has `$logger` set | Confirms DI params wired correctly |

**Setup for test**: Use `ContainerConfig::configureForTesting()` with mock overrides for `logger`
and `config`. Provide a mock `AdminService` for the `admin_api_controller` test to avoid
instantiating `CacheArchiver`/`CacheRebuilder` which require filesystem access.

```php
// Example test outline
public function testAdminServiceKeyResolvesToAdminServiceInstance(): void
{
    $container = ContainerConfig::configureForTesting([
        'logger' => $this->createMock(\Monolog\Logger::class),
        'config' => $this->createMock(\Gravitycar\Core\Config::class),
    ]);

    $adminService = $container->get('admin_service');
    $this->assertInstanceOf(AdminService::class, $adminService);
}

public function testAdminApiControllerKeyResolvesToAdminApiControllerInstance(): void
{
    $mockAdminService = $this->createMock(AdminService::class);
    $container = ContainerConfig::configureForTesting([
        'logger'        => $this->createMock(\Monolog\Logger::class),
        'config'        => $this->createMock(\Gravitycar\Core\Config::class),
        'admin_service' => $mockAdminService,
    ]);

    $controller = $container->get('admin_api_controller');
    $this->assertInstanceOf(\Gravitycar\Api\AdminAPIController::class, $controller);
}
```

---

## Notes

- The two new registrations are appended at the end of `configureApplicationServices()` — after
  the `open_api_generator` alias. This is the natural "last service" position and requires no
  reordering of existing code.
- `APIControllerFactory` auto-discovers `AdminAPIController` by scanning `src/Api/` for
  `ApiControllerBase` subclasses. The explicit `admin_api_controller` registration in
  `ContainerConfig` is needed so the controller can be retrieved by key when the factory
  constructs it (rather than instantiating a new instance each time).
- `CacheArchiver` and `CacheRebuilder` ARE registered in `ContainerConfig` under keys
  `cache_archiver` and `cache_rebuilder`. They are injected into `AdminService` as concrete
  instances, not null defaults.
- `AdminService` uses `lazyNew()` (singleton per container lifetime) so the stale archive scan
  in its constructor runs exactly once per HTTP request or CLI invocation, never more.
- The `cache_rebuilder` registration passes all six engine services from the container, so
  `CacheRebuilder`'s constructor never calls `ContainerConfig::getContainer()` internally.
- File length: `ContainerConfig.php` is currently 1,139 lines — well over the 300-line target.
  This plan adds only ~20 lines. Adding a comment block above each new registration (as done
  for every other service in the file) is consistent with style but does not add meaningful bulk.
  The 300-line rule applies to new files; `ContainerConfig.php` is an existing, intentionally
  large wiring file. Adding 20 lines does not make this worse.
