# Codebase Summary: Admin Panel and Improved Application Update Script

## Tech Stack

- **Backend**: PHP 8.2+, PSR-4 autoloading, Composer, Aura DI container
- **Frontend**: React (Vite + TypeScript), Tailwind CSS only (no Shadcn/Radix), axios-based API service
- **Database**: MySQL 8.0+, Doctrine DBAL
- **Web Server**: Apache 2.4+
- **Logging**: Monolog (RotatingFileHandler, daily rotation)
- **Testing**: PHPUnit (backend), Vitest (frontend)

---

## Architecture Overview

The framework is a full-stack metadata-driven CRUD framework. The backend is a PHP REST API, and the frontend is a React SPA. Services are wired through **Aura DI** via `ContainerConfig`. The framework uses a metadata cache, an API routes cache, per-role navigation caches, and a documentation (OpenAPI) cache — all stored as PHP files in the `cache/` directory.

### Directory layout (relevant areas)

```
/
├── setup.php                          # EXISTING catch-all bootstrap/setup script (root level — PUBLIC)
├── cache/
│   ├── metadata_cache.php
│   ├── api_routes.php
│   ├── navigation_cache_admin.php
│   ├── navigation_cache_manager.php
│   ├── navigation_cache_user.php
│   ├── navigation_cache_guest.php
│   └── documentation/
│       └── openapi_spec.php (+ model-specific files)
├── src/
│   ├── Api/
│   │   ├── ApiControllerBase.php       # Abstract base for all API controllers
│   │   ├── APIRouteRegistry.php        # Singleton; discovers, caches, serves routes
│   │   ├── Router.php                  # Dispatches requests, enforces auth/RBAC
│   │   ├── AuthController.php
│   │   ├── HealthAPIController.php
│   │   ├── MetadataAPIController.php
│   │   └── OpenAPIController.php
│   ├── Core/
│   │   ├── ContainerConfig.php         # Central Aura DI wiring
│   │   ├── ServiceLocator.php          # Legacy accessor (wraps ContainerConfig)
│   │   └── Gravitycar.php              # Framework bootstrap entry point
│   ├── Metadata/
│   │   └── MetadataEngine.php          # Loads/caches model/field/relationship metadata
│   ├── Schema/
│   │   └── SchemaGenerator.php         # Generates/migrates DB schema from metadata
│   ├── Services/
│   │   ├── DocumentationCache.php      # File-based cache for OpenAPI specs + model docs
│   │   ├── NavigationBuilder.php       # Builds per-role navigation caches
│   │   ├── PermissionsBuilder.php      # Rebuilds permissions table from metadata
│   │   ├── AuthorizationService.php
│   │   └── ...
│   ├── Models/
│   │   ├── ModelBase.php
│   │   ├── api/Api/ModelBaseAPIController.php   # Generic CRUD controller for any model
│   │   └── {model}/                    # Model-specific folders
│   │       ├── {Model}.php             # PHP model class
│   │       ├── {model}_metadata.php    # Metadata file
│   │       └── api/                    # Model-specific custom controllers
│   └── Factories/
│       ├── ModelFactory.php
│       └── APIControllerFactory.php
├── scripts/
│   ├── build/build-backend.sh          # CI build script
│   └── deploy/transfer.sh              # Deployment script
└── gravitycar-frontend/src/
    ├── App.tsx                          # Route definitions
    ├── pages/ProjectsPage.tsx           # Example custom page wrapper
    ├── components/projects/             # Example custom component (ProjectsListView)
    └── services/api.ts                  # Axios-based API singleton
```

---

## Relevant Existing Code

### 1. `setup.php` (root directory — to be replaced/moved)

**File**: `setup.php`

**What it does (7 steps)**:
1. Bootstraps the Gravitycar framework via `Gravitycar(['environment' => 'development'])`
2. Uses `ReflectionClass` to skip the routing bootstrap step (a known hack)
3. Clears all cache files via glob on `cache/*` (flat; misses `cache/documentation/`)
4. Calls `MetadataEngine::clearAllCaches()` + `DocumentationCache::clearCache()`
5. Calls `MetadataEngine::loadAllMetadata()` to rebuild metadata cache
6. Accesses the route registry via Reflection on the router object to rebuild API routes (another hack)
7. Calls `SchemaGenerator::createDatabaseIfNotExists()` then `SchemaGenerator::generateSchema($allMetadata)`
8. Calls `PermissionsBuilder::buildAllPermissions()` via ContainerConfig container
9. Calls `NavigationBuilder::buildAllRoleNavigationCaches()` via ContainerConfig container
10. Creates sample users and roles in the database (idempotent but messy)

**Known issues in setup.php**:
- Uses `ReflectionClass` twice (to skip routing step; to access route registry from router object) — an antipattern
- Uses `ServiceLocator` for some services, `ContainerConfig::getContainer()` for others — inconsistent
- Always tries to create the database and create default users/roles
- Lives in the root web-accessible directory
- Clears cache with `glob('cache/*')` which misses subdirectories like `cache/documentation/`

**Called by**:
- `scripts/build/build-backend.sh` line 320: `php setup.php` (inside `rebuild_cache()` function)
- `scripts/deploy/transfer.sh` line 232: `chmod +x .../scripts/setup.php` (incorrect path — script is in root, not scripts/)
- `scripts/deploy/transfer.sh` line 246: `php setup.php` (run after deployment completes)

---

### 2. Cache System Classes

#### `MetadataEngine` — `src/Metadata/MetadataEngine.php`
- **Key methods**:
  - `loadAllMetadata(): array` — scans metadata directories, merges core fields, validates, returns full metadata array AND writes `cache/metadata_cache.php`
  - `clearAllCaches(): void` — clears internal caches (line 191)
- **Cache file**: `cache/metadata_cache.php`
- Retrieved from container: `$container->get('metadata_engine')` (or legacy `ServiceLocator::getMetadataEngine()`)

#### `APIRouteRegistry` — `src/Api/APIRouteRegistry.php`
- **Pattern**: Singleton (`getInstance()`)
- **Key methods**:
  - `rebuildCache(): void` — clears routes array, calls `discoverAndRegisterRoutes()` which auto-discovers controllers and writes `cache/api_routes.php`
  - `getInstance(): APIRouteRegistry`
- **Cache file**: `cache/api_routes.php`
- Retrieved from container: `$container->get('api_route_registry')` (registered as a closure calling `APIRouteRegistry::getInstance()`)
- **Note**: `APIRouteRegistry` uses `ServiceLocator` internally in its constructor — not yet fully DI-migrated

#### `DocumentationCache` — `src/Services/DocumentationCache.php`
- **Key methods**:
  - `cacheOpenAPISpec(array $spec): void` — writes `cache/documentation/openapi_spec.php`
  - `clearCache(): void` — clears entire `cache/documentation/` directory
  - `getCachedOpenAPISpec(): ?array` — loads from cache with TTL validation
- **How to rebuild**: Call `OpenAPIGenerator::generateSpecification()`, which internally calls `$this->cache->cacheOpenAPISpec($spec)` (line 111 of `OpenAPIGenerator.php`)
- **Cache directory**: `cache/documentation/` (configurable via `documentation.cache_directory` config key)
- Retrieved from container: `$container->get('documentation_cache')`

#### `NavigationBuilder` — `src/Services/NavigationBuilder.php`
- **Key methods**:
  - `buildAllRoleNavigationCaches(): array` — iterates over a fixed set of roles (admin, manager, user, guest), builds navigation for each, writes `cache/navigation_cache_{role}.php`, returns array of results with `success`, `items_count`, `error` keys
- **Cache files**: `cache/navigation_cache_admin.php`, `cache/navigation_cache_manager.php`, etc.
- Retrieved from container: `$container->get('navigation_builder')`

---

### 3. Schema and Permissions

#### `SchemaGenerator` — `src/Schema/SchemaGenerator.php`
- **Key methods**:
  - `generateSchema(array $metadata): void` — compares current DB schema to target schema from metadata, generates migration SQL, executes via Doctrine DBAL
  - `createDatabaseIfNotExists(): bool`
- Retrieved from container: `$container->get('schema_generator')`
- **Note**: `generateSchema` requires the full metadata array as produced by `MetadataEngine::loadAllMetadata()`

#### `PermissionsBuilder` — `src/Services/PermissionsBuilder.php`
- **Key methods**:
  - `buildAllPermissions(): void` — truncates permissions table, rebuilds for all models and controllers
- **Signature** (line 42): `public function buildAllPermissions(): void`
- Retrieved from container: `$container->get('permissions_builder')`

---

### 4. ContainerConfig — `src/Core/ContainerConfig.php`

**Pattern**: Static singleton (`ContainerConfig::getContainer()`) returning Aura DI `Container`.

**Registered service keys** (relevant to this epic):
| Key | Class |
|-----|-------|
| `metadata_engine` | `MetadataEngine` |
| `api_route_registry` | `APIRouteRegistry::getInstance()` (singleton) |
| `documentation_cache` | `DocumentationCache` |
| `navigation_builder` | `NavigationBuilder` |
| `schema_generator` | `SchemaGenerator` |
| `permissions_builder` | `PermissionsBuilder` |
| `logger` | `Monolog\Logger` |
| `config` | `Config` |
| `database_connector` | `DatabaseConnector` |
| `model_factory` | `ModelFactory` |
| `api_controller_factory` | `APIControllerFactory` |
| `openapi_generator` | `OpenAPIGenerator` |
| `cli_current_user_provider` | `CLICurrentUserProvider` |

**How to get services in new code**:
```php
$container = ContainerConfig::getContainer();
$metadataEngine = $container->get('metadata_engine');
$schemaGenerator = $container->get('schema_generator');
// etc.
```

**Note**: `ServiceLocator` is a legacy wrapper — new code should use `ContainerConfig::getContainer()` directly.

---

### 5. API Controller Pattern

**Base class**: `src/Api/ApiControllerBase.php`

```php
abstract class ApiControllerBase {
    protected ?Logger $logger;
    protected ?ModelFactory $modelFactory;
    protected ?DatabaseConnectorInterface $databaseConnector;
    protected ?MetadataEngineInterface $metadataEngine;
    protected ?Config $config;
    protected ?CurrentUserProviderInterface $currentUserProvider;

    protected array $rolesAndActions = [
        'admin' => ['*'],
        'manager' => ['*'],
        'user' => ['*'],
        'guest' => ['*']
    ];

    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null,
        CurrentUserProviderInterface $currentUserProvider = null
    ) { ... }

    abstract public function registerRoutes(): array;
}
```

**`registerRoutes()` return format** (example from `ChartAPIController`):
```php
return [
    [
        'method' => 'GET',
        'path' => '/Events/{event_id}/chart',
        'apiClass' => self::class,
        'apiMethod' => 'getChart',
        'parameterNames' => ['event_id'],   // ONLY dynamic param names
        'rbacAction' => 'read',
    ],
];
```

**RBAC**: Controllers declare `$rolesAndActions` as class property. The `AuthorizationService` checks the DB-backed `roles_permissions` table (populated by `PermissionsBuilder`). Route-level RBAC checks use `hasPermissionForRoute()`.

**Custom controller example**: `src/Models/events/api/ChartAPIController.php` — restricts to `admin`/`user`/`guest` read, implements invitation-gated access in the controller method itself.

**Registering a new controller in ContainerConfig** — add a block like:
```php
$di->set('my_controller', $di->lazyNew(MyController::class));
$di->params[MyController::class] = [
    'logger' => $di->lazyGet('logger'),
    'modelFactory' => $di->lazyGet('model_factory'),
    'databaseConnector' => $di->lazyGet('database_connector'),
    'metadataEngine' => $di->lazyGet('metadata_engine'),
    'config' => $di->lazyGet('config'),
    'currentUserProvider' => $di->lazyGet('current_user_provider'),
];
```
The `APIControllerFactory` discovers controllers by scanning `src/Api/` and `src/Models/` for `ApiControllerBase` subclasses.

---

### 6. Existing Custom React View Pattern (ProjectsListView)

**Pattern for a custom page** (no auth requirement):

1. **Component file**: `gravitycar-frontend/src/components/projects/ProjectsListView.tsx`
   - Exports a named React component
   - Uses `apiService.getList<T>('ModelName', page, pageSize)` from `services/api.ts` for data
   - Tailwind CSS only (no external UI lib)
   - Handles loading/error/empty states

2. **Page wrapper**: `gravitycar-frontend/src/pages/ProjectsPage.tsx`
   - Thin wrapper that renders the component inside layout div
   - No `<Layout>` wrapper here — that's applied in App.tsx

3. **Route registration**: `gravitycar-frontend/src/App.tsx`
   - Import the page component
   - Add `<Route>` inside `<Routes>` wrapping in `<Layout>` if needed
   - Public routes do NOT need `<ProtectedRoute>`
   - Protected routes should use `<Layout>` + wrap in auth check

**For an Admin Panel page** (requires auth):
- Wrap in `<Layout>` in App.tsx
- Use `useAuth()` hook to get current user and check role
- Add `<ProtectedRoute>` or redirect if user is not admin

**API service pattern**:
```typescript
// Singleton class-based API service
import apiService from '../../services/api';

const response = await apiService.getList<T>('ModelName', page, limit);
// response.success, response.data, response.message

// For custom endpoints:
await apiService.post('/admin/rebuild-cache', { options });
```

---

## Cache Directory Structure

```
cache/
├── metadata_cache.php          # All model/field/relationship metadata
├── api_routes.php              # Discovered API routes (keyed by method + path length)
├── navigation_cache_admin.php  # Navigation for admin role
├── navigation_cache_manager.php
├── navigation_cache_user.php
├── navigation_cache_guest.php
└── documentation/
    └── openapi_spec.php        # OpenAPI specification (+ per-model files)
```

Cache validation: PHP files are valid if they parse successfully (`php -l <file>`).

---

## Build/Deploy Script Touchpoints

### `scripts/build/build-backend.sh` (lines 315-328)
The `rebuild_cache()` function simply calls `php setup.php` if the file exists. This is the only cache-rebuild step in the CI build.

### `scripts/deploy/transfer.sh` (lines 232, 246)
- Line 232: `chmod +x .../scripts/setup.php` — **incorrect path**, setup.php is in root, not `scripts/`
- Line 246: `php setup.php` — runs setup.php after deploying backend files to production server

Both scripts need to be updated to call the new `application-update.php` CLI script instead.

---

## Conventions to Follow

1. **DI container**: All new services registered in `ContainerConfig::configureApplicationServices()`. Always use `$container->get('service_key')` rather than `ServiceLocator`.
2. **No ReflectionClass hacks**: Avoid using Reflection to access private properties/methods. Design classes so needed methods are public.
3. **ApiControllerBase subclasses**: 6-param constructor (all optional null defaults), `registerRoutes()` returning route array, `$rolesAndActions` property.
4. **Route parameterNames**: Only list dynamic `{param}` segment names, not static path segments.
5. **Error handling**: Use specific exception classes extending a base GCException. Always log errors.
6. **Logging**: Every class has a `$logger` property (Monolog Logger instance).
7. **CLI scripts**: Must check `php_sapi_name() === 'cli'` and exit if not. Use `getopt()` for options. Cannot live in web root.
8. **React**: No Shadcn/Radix — Tailwind CSS only, native HTML inputs. API calls through `services/api.ts` singleton. Routes defined in `App.tsx` wrapped in `<Layout>`.
9. **File length**: Max 300 lines per file. Split concerns across multiple files.
10. **Config access**: Via `Config` class (`$config->get('key', 'default')`). Never hardcode config values.

---

## Reusable Components for the New Epic

| Component | Location | What it provides |
|-----------|----------|-----------------|
| `MetadataEngine` | `src/Metadata/MetadataEngine.php` | `loadAllMetadata()`, `clearAllCaches()` |
| `APIRouteRegistry` | `src/Api/APIRouteRegistry.php` | `rebuildCache()`, `getInstance()` |
| `DocumentationCache` | `src/Services/DocumentationCache.php` | `cacheOpenAPISpec()`, `clearCache()` |
| `NavigationBuilder` | `src/Services/NavigationBuilder.php` | `buildAllRoleNavigationCaches()` |
| `SchemaGenerator` | `src/Schema/SchemaGenerator.php` | `generateSchema(array $metadata)` |
| `PermissionsBuilder` | `src/Services/PermissionsBuilder.php` | `buildAllPermissions()` |
| `OpenAPIGenerator` | `src/Services/OpenAPIGenerator.php` | `generateSpecification()` — calls `cacheOpenAPISpec()` internally |
| `ContainerConfig` | `src/Core/ContainerConfig.php` | `getContainer()` — all service keys listed above |
| `ApiControllerBase` | `src/Api/ApiControllerBase.php` | Base class for new Admin API controller |
| `apiService` | `gravitycar-frontend/src/services/api.ts` | Axios singleton for API calls |
| `ProjectsListView` | `gravitycar-frontend/src/components/projects/ProjectsListView.tsx` | Pattern for custom React view |
| `App.tsx` | `gravitycar-frontend/src/App.tsx` | Where to add new routes |

---

## Key Design Gaps to Address

1. **setup.php must be removed from root** and replaced with a CLI-only `application-update.php` in a non-web-accessible location (e.g., `scripts/`).
2. **A new `ApplicationUpdateService`** (or similar name) should consolidate all cache rebuild logic so both the CLI script and the Admin API controller call the same methods.
3. **The Admin API controller** (`AdminAPIController`) needs to be admin-only: `$rolesAndActions = ['admin' => ['*']]`.
4. **Cache clearing** in setup.php currently misses `cache/documentation/` subdirectory — the new implementation must handle recursive clearing.
5. **ReflectionClass usage** in setup.php to call bootstrap steps and get route registry must be eliminated; the new service should call methods directly.
6. **User/role seeding** is a separate concern from cache rebuilding — the new update script should NOT create users or roles unless explicitly requested.
7. **`OpenAPIGenerator::generateSpecification()`** is the correct way to rebuild the documentation cache (it calls `cacheOpenAPISpec()` internally) — not calling `cacheOpenAPISpec()` directly.
