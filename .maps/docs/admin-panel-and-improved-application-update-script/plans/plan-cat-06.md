# Implementation Plan: CAT-06 — CacheRebuilder Class

## Spec Context

`CacheRebuilder` encapsulates all cache file operations: recursively clearing cache files,
calling the framework's engine services to regenerate each cache component, and running
`php -l` syntax validation on the generated PHP files. It receives no HTTP context and no
business logic — it executes file I/O and delegates regeneration to the appropriate service.
`AdminService` calls `CacheRebuilder` methods in sequence after `CacheArchiver` creates the
archive.

Catalog item: CAT-06  
Specification section: Component 4c (CacheRebuilder); AC-5, AC-7, AC-8, AC-9, AC-11, AC-12, AC-13, AC-14  
Acceptance criteria addressed:
- AC-5: `clear()` uses `RecursiveDirectoryIterator` (not `glob`) so `cache/documentation/` subdirectory is always included.
- AC-7: When METADATA is included and `updateSchema` is true, `SchemaGenerator::generateSchema($metadata)` is called with the freshly loaded metadata.
- AC-8: When METADATA is included and `updatePermissions` is true, `PermissionsBuilder::buildAllPermissions()` is called.
- AC-9: `validate()` runs `php -l <file>` for every PHP file in selected component cache paths.
- AC-11: All dependencies obtained from `ContainerConfig::getContainer()`. No `ServiceLocator`, no `ReflectionClass`.
- AC-12: Has `$logger` property; logs start, completion, and errors per step.
- AC-13: Has `$config` property.
- AC-14: No file exceeds 300 lines.

---

## Dependencies

- **Blocked by**: CAT-04 (`AdminServiceException`), CAT-01 (`CacheComponent` — component constants and mapping)
- **Blocked by**: CAT-02 (`CacheStepResult` — used in the `$onStep` callback)
- **Blocks**: CAT-07 (`AdminService` calls `clear()`, `rebuild()`, `validate()`)
- **Uses**:
  - `Gravitycar\Exceptions\AdminServiceException` (CAT-04)
  - `Gravitycar\Services\Admin\CacheComponent` (CAT-01)
  - `Gravitycar\Services\Admin\CacheStepResult` (CAT-02)
  - `Gravitycar\Core\ContainerConfig` (to get engine services in constructor)
  - `Gravitycar\Core\Config`
  - `Monolog\Logger`
  - `Gravitycar\Contracts\MetadataEngineInterface` (container key: `metadata_engine`)
  - `Gravitycar\Api\APIRouteRegistry` (container key: `api_route_registry`)
  - `Gravitycar\Services\OpenAPIGenerator` (container key: `openapi_generator`)
  - `Gravitycar\Services\NavigationBuilder` (container key: `navigation_builder`)
  - `Gravitycar\Schema\SchemaGenerator` (container key: `schema_generator`)
  - `Gravitycar\Services\PermissionsBuilder` (container key: `permissions_builder`)

---

## File Changes

### New Files
- `src/Services/Admin/CacheRebuilder.php` — recursive clear, engine-delegated rebuild, and php -l validation

### Modified Files
- none

---

## Implementation Details

### CacheRebuilder

**File**: `src/Services/Admin/CacheRebuilder.php`

**Namespace**: `Gravitycar\Services\Admin`

**Properties**:
- `private Logger $logger`
- `private Config $config`
- `private MetadataEngineInterface $metadataEngine`
- `private APIRouteRegistry $apiRouteRegistry`
- `private OpenAPIGenerator $openAPIGenerator`
- `private NavigationBuilder $navigationBuilder`
- `private SchemaGenerator $schemaGenerator`
- `private PermissionsBuilder $permissionsBuilder`

**Class-level constant** — component to cache path mapping:

Per CLAUDE.md: when an array is only used in a method and its contents are never changed
programmatically, define it as a class constant.

```php
private const array COMPONENT_CACHE_PATHS = [
    CacheComponent::METADATA   => 'cache/metadata_cache.php',
    CacheComponent::ROUTES     => 'cache/api_routes.php',
    CacheComponent::DOCS       => 'cache/documentation/',
    CacheComponent::NAVIGATION => null, // Resolved dynamically via glob pattern
];

private const string NAVIGATION_CACHE_GLOB = 'cache/navigation_cache_*.php';
```

**Constructor**:

Engine services are injected as constructor parameters. No `ContainerConfig::getContainer()`
calls inside the constructor, no `ServiceLocator`, no `ReflectionClass`. `ContainerConfig` is
responsible for wiring these dependencies when it registers `CacheRebuilder` (see CAT-08).

```php
public function __construct(
    Logger $logger,
    Config $config,
    MetadataEngineInterface $metadataEngine,
    APIRouteRegistry $apiRouteRegistry,
    OpenAPIGenerator $openAPIGenerator,
    NavigationBuilder $navigationBuilder,
    SchemaGenerator $schemaGenerator,
    PermissionsBuilder $permissionsBuilder
) {
    $this->logger             = $logger;
    $this->config             = $config;
    $this->metadataEngine     = $metadataEngine;
    $this->apiRouteRegistry   = $apiRouteRegistry;
    $this->openAPIGenerator   = $openAPIGenerator;
    $this->navigationBuilder  = $navigationBuilder;
    $this->schemaGenerator    = $schemaGenerator;
    $this->permissionsBuilder = $permissionsBuilder;
}
```

**Note on testability**: Because all services are injected as constructor parameters, unit
tests can pass mock instances directly into the constructor without priming `ContainerConfig`
or any container setup. Each mock is passed as a named argument:

```php
$rebuilder = new CacheRebuilder(
    $this->createMock(Logger::class),
    $this->createMock(Config::class),
    $this->createMock(MetadataEngineInterface::class),
    $this->createMock(APIRouteRegistry::class),
    $this->createMock(OpenAPIGenerator::class),
    $this->createMock(NavigationBuilder::class),
    $this->createMock(SchemaGenerator::class),
    $this->createMock(PermissionsBuilder::class)
);
```

No container priming needed in tests.

---

### Method: `clear(array $components): void`

Removes cache files for each selected component using `RecursiveDirectoryIterator`.
Does NOT remove directories — only their contents.

**Component path mapping**:
- `METADATA` → delete the single file `cache/metadata_cache.php`
- `ROUTES` → delete the single file `cache/api_routes.php`
- `DOCS` → delete all files inside `cache/documentation/` recursively (keep the directory)
- `NAVIGATION` → delete all files matching `cache/navigation_cache_*.php` (glob is acceptable here — flat directory, pattern-matched single files)

**Steps**:
1. Log: `'Clearing cache files'` with component list.
2. For each component in `$components`:
   a. Log: `'Clearing component cache'` with component name.
   b. Call the appropriate private helper: `clearFile()` for METADATA/ROUTES, `clearDirectory()` for DOCS, `clearGlobPattern()` for NAVIGATION.
   c. Log: `'Component cache cleared'`.
3. Log: `'Cache clear complete'`.

**Private helper: `clearFile(string $filePath): void`**:
```php
private function clearFile(string $filePath): void
{
    if (!file_exists($filePath)) {
        return;
    }
    if (!unlink($filePath)) {
        throw new AdminServiceException(
            "Failed to delete cache file",
            ['filePath' => $filePath]
        );
    }
}
```

**Private helper: `clearDirectory(string $dirPath): void`**:

Uses `RecursiveDirectoryIterator` + `RecursiveIteratorIterator` to walk the directory tree and
delete all files (not directories). This fixes the `glob('cache/*')` bug in `setup.php` which
missed `cache/documentation/` subdirectory contents.

```php
private function clearDirectory(string $dirPath): void
{
    if (!is_dir($dirPath)) {
        return;
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && !unlink($file->getRealPath())) {
            throw new AdminServiceException(
                'Failed to delete file during cache clear',
                ['filePath' => $file->getRealPath(), 'component' => CacheComponent::DOCS]
            );
        }
    }
}
```

**Private helper: `clearGlobPattern(string $pattern): void`**:
```php
private function clearGlobPattern(string $pattern): void
{
    $files = glob($pattern);
    if ($files === false) {
        return;
    }
    foreach ($files as $filePath) {
        if (!unlink($filePath)) {
            throw new AdminServiceException(
                'Failed to delete navigation cache file',
                ['filePath' => $filePath, 'component' => CacheComponent::NAVIGATION]
            );
        }
    }
}
```

**Public `clear()` method sketch**:

```php
public function clear(array $components): void
{
    $this->logger->info('Clearing cache files', ['components' => $components]);

    foreach ($components as $component) {
        $this->logger->info('Clearing component cache', ['component' => $component]);
        $this->clearComponent($component);
        $this->logger->info('Component cache cleared', ['component' => $component]);
    }

    $this->logger->info('Cache clear complete', ['components' => $components]);
}

private function clearComponent(string $component): void
{
    match ($component) {
        CacheComponent::METADATA   => $this->clearFile(self::COMPONENT_CACHE_PATHS[CacheComponent::METADATA]),
        CacheComponent::ROUTES     => $this->clearFile(self::COMPONENT_CACHE_PATHS[CacheComponent::ROUTES]),
        CacheComponent::DOCS       => $this->clearDirectory(self::COMPONENT_CACHE_PATHS[CacheComponent::DOCS]),
        CacheComponent::NAVIGATION => $this->clearGlobPattern(self::NAVIGATION_CACHE_GLOB),
    };
}
```

---

### Method: `rebuild(array $components, bool $updateSchema, bool $updatePermissions, callable $onStep): void`

Calls the appropriate engine service for each component, in the fixed order:
METADATA → ROUTES → DOCS → NAVIGATION. After METADATA (if selected), optionally calls
`SchemaGenerator::generateSchema()` and/or `PermissionsBuilder::buildAllPermissions()`.

The `$onStep` callable is called before and after each step with a `CacheStepResult`. This
allows `AdminService` (and ultimately the SSE stream) to emit progress events in real time.

**Rebuild order** (always rebuild METADATA before ROUTES, ROUTES before DOCS, NAVIGATION last):

The method iterates over `CacheComponent::all()` as the canonical order and skips any component
not in `$components`. This ensures consistent ordering regardless of the order the caller
passes components.

**Steps**:
1. Log: `'Rebuilding cache components'` with component list.
2. Track `$metadata = null` to carry the freshly loaded metadata to SchemaGenerator.
3. Iterate over `CacheComponent::all()` (canonical order):
   - If component not in `$components`, skip.
   - Emit `$onStep(CacheStepResult::inProgress($stepName, $component))`.
   - Call the engine service method.
   - If component is METADATA: store the returned metadata array in `$metadata`.
   - Emit `$onStep(CacheStepResult::success($stepName, $component))`.
4. After METADATA step (if METADATA was rebuilt):
   - If `$updateSchema && $metadata !== null`:
     - Emit `$onStep(CacheStepResult::inProgress('schema_update', CacheComponent::METADATA))`.
     - Call `$this->schemaGenerator->generateSchema($metadata)`.
     - Emit `$onStep(CacheStepResult::success('schema_update', CacheComponent::METADATA))`.
   - If `$updatePermissions`:
     - Emit `$onStep(CacheStepResult::inProgress('permissions_update', CacheComponent::METADATA))`.
     - Call `$this->permissionsBuilder->buildAllPermissions()`.
     - Emit `$onStep(CacheStepResult::success('permissions_update', CacheComponent::METADATA))`.
5. Log: `'Cache rebuild complete'`.

**Component → service method mapping**:

| Component | Step name | Service call |
|-----------|-----------|--------------|
| `METADATA` | `'rebuild'` | `$this->metadataEngine->loadAllMetadata()` → returns `array $metadata` |
| `ROUTES` | `'rebuild'` | `$this->apiRouteRegistry->rebuildCache()` → returns `void` |
| `DOCS` | `'rebuild'` | `$this->openAPIGenerator->generateSpecification()` → returns `array` |
| `NAVIGATION` | `'rebuild'` | `$this->navigationBuilder->buildAllRoleNavigationCaches()` → returns `array` |

**Code Example**:

```php
public function rebuild(
    array $components,
    bool $updateSchema,
    bool $updatePermissions,
    callable $onStep
): void {
    $this->logger->info('Rebuilding cache components', ['components' => $components]);
    $metadata = null;
    $metadataRebuilt = false;

    foreach (CacheComponent::all() as $component) {
        if (!in_array($component, $components, strict: true)) {
            continue;
        }
        $onStep(CacheStepResult::inProgress('rebuild', $component));
        $metadata = $this->rebuildComponent($component, $metadata);
        $onStep(CacheStepResult::success('rebuild', $component));

        if ($component === CacheComponent::METADATA) {
            $metadataRebuilt = true;
            $this->runSchemaAndPermissions(
                $metadata,
                $updateSchema,
                $updatePermissions,
                $onStep
            );
        }
    }

    $this->logger->info('Cache rebuild complete', ['components' => $components]);
}

private function rebuildComponent(string $component, ?array $currentMetadata): ?array
{
    return match ($component) {
        CacheComponent::METADATA   => $this->metadataEngine->loadAllMetadata(),
        CacheComponent::ROUTES     => $this->rebuildRoutes(),
        CacheComponent::DOCS       => $this->rebuildDocs(),
        CacheComponent::NAVIGATION => $this->rebuildNavigation(),
    };
}

private function rebuildRoutes(): null
{
    $this->apiRouteRegistry->rebuildCache();
    return null;
}

private function rebuildDocs(): null
{
    $this->openAPIGenerator->generateSpecification();
    return null;
}

private function rebuildNavigation(): null
{
    $this->navigationBuilder->buildAllRoleNavigationCaches();
    return null;
}

private function runSchemaAndPermissions(
    ?array $metadata,
    bool $updateSchema,
    bool $updatePermissions,
    callable $onStep
): void {
    if ($updateSchema && $metadata !== null) {
        $onStep(CacheStepResult::inProgress('schema_update', CacheComponent::METADATA));
        $this->schemaGenerator->generateSchema($metadata);
        $onStep(CacheStepResult::success('schema_update', CacheComponent::METADATA));
    }

    if ($updatePermissions) {
        $onStep(CacheStepResult::inProgress('permissions_update', CacheComponent::METADATA));
        $this->permissionsBuilder->buildAllPermissions();
        $onStep(CacheStepResult::success('permissions_update', CacheComponent::METADATA));
    }
}
```

**Exception handling**: Exceptions thrown by engine services propagate up to `AdminService`,
which catches them, records the failure in `CacheRebuildResult`, and triggers restore.
`CacheRebuilder::rebuild()` does NOT catch exceptions itself — it lets them propagate.

---

### Method: `validate(array $components): void`

Runs `php -l <file>` for every PHP file in each selected component's cache path.
Throws `AdminServiceException` if any file fails syntax validation.

**Component → files to validate**:
- `METADATA` → `cache/metadata_cache.php` (if it exists)
- `ROUTES` → `cache/api_routes.php` (if it exists)
- `DOCS` → all `*.php` files in `cache/documentation/` (recursive)
- `NAVIGATION` → all files matching `cache/navigation_cache_*.php`

**Steps**:
1. Log: `'Validating rebuilt cache files'` with components.
2. For each component, collect the list of PHP files to validate.
3. For each file:
   a. Log: `'Validating file syntax'` with file path.
   b. Execute: `exec('php -l ' . escapeshellarg($filePath), $output, $exitCode)`.
   c. If `$exitCode !== 0`: throw `AdminServiceException` with file path, component, and output.
   d. Log: `'File syntax valid'`.
4. Log: `'Cache validation complete'`.

**Private helper: `collectFilesForComponent(string $component): array`**:

Returns a flat list of file paths for the given component.

```php
private function collectFilesForComponent(string $component): array
{
    return match ($component) {
        CacheComponent::METADATA   => $this->filterExisting([self::COMPONENT_CACHE_PATHS[CacheComponent::METADATA]]),
        CacheComponent::ROUTES     => $this->filterExisting([self::COMPONENT_CACHE_PATHS[CacheComponent::ROUTES]]),
        CacheComponent::DOCS       => $this->collectPhpFilesInDir(self::COMPONENT_CACHE_PATHS[CacheComponent::DOCS]),
        CacheComponent::NAVIGATION => $this->filterExisting(glob(self::NAVIGATION_CACHE_GLOB) ?: []),
    };
}

private function filterExisting(array $paths): array
{
    return array_filter($paths, 'file_exists');
}

private function collectPhpFilesInDir(string $dirPath): array
{
    if (!is_dir($dirPath)) {
        return [];
    }
    $files    = [];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getRealPath();
        }
    }
    return $files;
}
```

**Public `validate()` sketch**:

```php
public function validate(array $components): void
{
    $this->logger->info('Validating rebuilt cache files', ['components' => $components]);

    foreach ($components as $component) {
        $files = $this->collectFilesForComponent($component);
        foreach ($files as $filePath) {
            $this->validateFile($filePath, $component);
        }
    }

    $this->logger->info('Cache validation complete', ['components' => $components]);
}

private function validateFile(string $filePath, string $component): void
{
    $this->logger->info('Validating file syntax', ['filePath' => $filePath]);
    exec('php -l ' . escapeshellarg($filePath), $output, $exitCode);

    if ($exitCode !== 0) {
        throw new AdminServiceException(
            'Cache file failed syntax validation',
            ['filePath' => $filePath, 'component' => $component, 'output' => implode("\n", $output)]
        );
    }

    $this->logger->info('File syntax valid', ['filePath' => $filePath]);
}
```

---

## Error Handling

| Condition | Action |
|-----------|--------|
| `unlink()` fails in `clearFile()` | Throw `AdminServiceException` with file path |
| `unlink()` fails in `clearDirectory()` | Throw `AdminServiceException` with file path + component |
| `unlink()` fails in `clearGlobPattern()` | Throw `AdminServiceException` with file path + component |
| Engine service throws any exception | Let it propagate to `AdminService` |
| `php -l` exits non-zero | Throw `AdminServiceException` with file path, component, output |

All `AdminServiceException` instances are auto-logged on construction via `GCException::logException()`.
`CacheRebuilder` additionally logs info-level start/completion messages for observability.

---

## Unit Test Specifications

**Test file**: `tests/Unit/Services/Admin/CacheRebuilderTest.php`

**Setup**: Use a real temp directory for file system tests. Mock all six engine services.
Inject mocks directly into the constructor — no container priming needed.

### `CacheRebuilder::clear()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| METADATA component | `cache/metadata_cache.php` exists | File is deleted after `clear(['metadata'])` | AC-5 |
| ROUTES component | `cache/api_routes.php` exists | File is deleted | AC-5 |
| DOCS component | `cache/documentation/openapi_spec.php` + subdirectory file exist | Both files deleted, directory retained | AC-5 |
| NAVIGATION component | Two `cache/navigation_cache_*.php` files exist | Both deleted | AC-5 |
| Missing file | Component file does not exist | No exception thrown | Idempotent |
| Subset of components | Only METADATA and ROUTES | Only those files deleted | Selective clear |
| All components | All cache files present | All files deleted | Full clear |

### `CacheRebuilder::rebuild()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| METADATA only | Mock `loadAllMetadata()` returns `['models'=>[]]` | `loadAllMetadata()` called; `$onStep` receives inProgress then success | AC-7 |
| METADATA + updateSchema | Metadata rebuilt, `updateSchema=true` | `generateSchema(['models'=>[]])` called with fresh metadata | AC-7 |
| METADATA + updatePermissions | Metadata rebuilt, `updatePermissions=true` | `buildAllPermissions()` called | AC-8 |
| updateSchema without METADATA | `updateSchema=true`, METADATA not in components | `generateSchema()` NOT called | AC-7 (only when METADATA included) |
| ROUTES only | Mock `rebuildCache()` | `rebuildCache()` called once | Component mapping |
| DOCS only | Mock `generateSpecification()` | `generateSpecification()` called once | Component mapping |
| NAVIGATION only | Mock `buildAllRoleNavigationCaches()` | Called once | Component mapping |
| Rebuild order | All components | METADATA called before ROUTES called before DOCS called before NAVIGATION | Canonical order |
| $onStep callback | Any component | Called with `inProgress` before service call, `success` after | Streaming support |
| Engine throws | `loadAllMetadata()` throws `\Exception` | Exception propagates uncaught from `rebuild()` | AdminService handles |

### `CacheRebuilder::validate()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Valid PHP file | Write valid `<?php return [];` to temp path | No exception | Happy path |
| Invalid PHP file | Write `<?php )) invalid` to temp path | Throws `AdminServiceException` | AC-9 |
| Missing file | Component cache path does not exist | No exception (file skipped) | Validate only what exists |
| DOCS recursive | Valid PHP in `cache/documentation/subdir/` | Validates the nested file | Recursive collection |
| Multiple files, one fails | Two files, second invalid | Throws on second file | AC-9 |
| Exception carries component | Invalid ROUTES file | `AdminServiceException` context has `'component' => 'routes'` | Diagnostic context |

### Key Scenario: `clear()` uses RecursiveDirectoryIterator for DOCS

**Setup**: Create temp `cache/documentation/` with `openapi_spec.php` AND a subdirectory
`models/` containing `user.php`.  
**Action**: `$rebuilder->clear([CacheComponent::DOCS])`  
**Expected**: Both `openapi_spec.php` and `models/user.php` are deleted; the `documentation/`
and `documentation/models/` directories still exist.  
**Why**: This is the fix for AC-5 — `glob('cache/*')` would have missed the `models/` subdirectory.

### Key Scenario: Schema and permissions order

**Setup**: Mock `loadAllMetadata()` returns `$freshMetadata = ['models' => ['User' => [...]]]`.
Both `updateSchema=true` and `updatePermissions=true`.  
**Action**: `$rebuilder->rebuild([CacheComponent::METADATA], true, true, $onStep)`  
**Expected**:
1. `loadAllMetadata()` called first.
2. `generateSchema($freshMetadata)` called next with the RETURNED metadata (not a stale copy).
3. `buildAllPermissions()` called last.
4. `$onStep` called: inProgress(rebuild,metadata), success(rebuild,metadata), inProgress(schema_update,metadata), success(schema_update,metadata), inProgress(permissions_update,metadata), success(permissions_update,metadata).  
**Why**: Validates AC-7 and AC-8 together, and confirms `$onStep` fires correctly.

---

## Notes

- `CacheRebuilder` does NOT commit its own exceptions — it lets engine-service exceptions
  propagate up to `AdminService`, which catches them and restores the archive. Only file I/O
  failures (failed `unlink()`, `php -l` failure) are wrapped in `AdminServiceException`.
- The `$onStep` callback signature is `callable(CacheStepResult): void`. The `AdminAPIController`
  SSE handler passes a closure that emits `data: {...}\n\n` to the output buffer. The
  `application-update.php` CLI script passes a closure that prints to STDOUT. `AdminService`
  aggregates the results into `CacheRebuildResult`.
- `NAVIGATION` uses `glob()` in `clear()` (via `clearGlobPattern()`) because navigation cache
  files are named `cache/navigation_cache_{role}.php` — a flat, pattern-matched set of single
  files in one directory, not a recursive tree. `RecursiveDirectoryIterator` is reserved for
  `DOCS` where there is an actual subdirectory structure.
- The `COMPONENT_CACHE_PATHS` constant maps `CacheComponent::DOCS` to a directory path ending
  in `/`. The `clearDirectory()` and `collectPhpFilesInDir()` helpers check `is_dir()` before
  iterating.
- The canonical iteration order in `rebuild()` is driven by `CacheComponent::all()` (METADATA,
  ROUTES, DOCS, NAVIGATION), ensuring METADATA is always rebuilt before ROUTES so that any
  route controller added via metadata is available when the routes cache is written.
- Do NOT call `MetadataEngine::clearAllCaches()` in `clear()` — that clears in-memory PHP
  state. The `clear()` method clears only the on-disk cache files; the engine's in-memory
  cache is irrelevant since `loadAllMetadata()` forces a full reload when `rebuild()` calls it.
- File is expected to be under 200 lines with the private helper extraction strategy shown above.
  If it approaches 250 lines, extract `CacheRebuilderValidator` as a separate private helper class.
