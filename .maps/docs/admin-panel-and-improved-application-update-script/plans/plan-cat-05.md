# Implementation Plan: CAT-05 — CacheArchiver Class

## Spec Context

`CacheArchiver` encapsulates every tar file operation in the cache rebuild lifecycle: creating
the timestamped backup archive before any files are cleared, verifying the archive is valid,
restoring it on failure, deleting it when no longer needed, and scanning for stale archives on
startup. It is the safety net that enables `AdminService` to roll back cleanly if any rebuild
step fails. `CacheArchiver` does no business logic — it is purely file I/O and shell execution.

Catalog item: CAT-05  
Specification section: Component 4b (CacheArchiver); AC-2, AC-3, AC-4, AC-10, AC-12, AC-13,
AC-14a, AC-14b, AC-14c  
Acceptance criteria addressed:
- AC-2: Always archives before clearing; archive name is `cache_YYYY_MM_DD_HH_MM_SS.tar` in app root.
- AC-3: After archive creation, verifies file exists and has non-zero size; throws `AdminServiceException` on failure.
- AC-4: `restore()` restores the ENTIRE archive regardless of selected components.
- AC-12: Has `$logger` property (Monolog Logger); logs start, completion, and errors per step.
- AC-13: Has `$config` property (Config instance).
- AC-14a: `scanForStaleArchives()` scans app root for `cache_*.tar` files; logs warning per file found.
- AC-14b: `delete()` is called by `AdminService` after validation success and after restore.
- AC-14c: `chmod 600` is run immediately after archive tar file is created.

---

## Dependencies

- **Blocked by**: CAT-04 (`AdminServiceException` — thrown on archive/restore/verify failure)
- **Blocks**: CAT-07 (`AdminService` uses `CacheArchiver` for all tar operations)
- **Uses**:
  - `Gravitycar\Exceptions\AdminServiceException` (CAT-04)
  - `Gravitycar\Core\Config`
  - `Monolog\Logger`
  - PHP built-ins: `exec()`, `chmod()`, `file_exists()`, `filesize()`, `unlink()`, `glob()`

---

## File Changes

### New Files
- `src/Services/Admin/CacheArchiver.php` — all tar archive operations for cache rebuild safety

### Modified Files
- none

---

## Implementation Details

### CacheArchiver

**File**: `src/Services/Admin/CacheArchiver.php`

**Namespace**: `Gravitycar\Services\Admin`

**Properties**:
- `private Logger $logger` — Monolog logger instance
- `private Config $config` — Config instance
- `private string $appRootDirPath` — resolved in constructor; all archive files placed here

**App root resolution**:

The config does not have a dedicated `paths.app_root` key. The app root is derived from the
`cache.directory` config value (`'cache'` by default): the parent directory of the cache
directory is the app root. The constructor resolves this as:

```php
$cacheDirPath = $this->config->get('cache.directory', 'cache');
$this->appRootDirPath = dirname(realpath($cacheDirPath) ?: $cacheDirPath);
```

If `realpath()` fails (e.g., in tests), it falls back to `dirname($cacheDirPath)`. In practice,
the app root is the project root directory (one level up from `cache/`).

**Constructor**:

```php
public function __construct(Logger $logger, Config $config)
{
    $this->logger    = $logger;
    $this->config    = $config;
    $cacheDirPath    = $this->config->get('cache.directory', 'cache');
    $resolvedPath    = realpath($cacheDirPath);
    $this->appRootDirPath = $resolvedPath !== false
        ? dirname($resolvedPath)
        : dirname($cacheDirPath);
}
```

**Public interface**:

```php
public function archive(): string
public function restore(string $archiveFilePath): void
public function deleteArchive(string $archiveFilePath): void
public function verify(string $archiveFilePath): bool
public function findStaleArchives(): array
```

> **Method name summary**: `archive()` (was `create()`), `deleteArchive()` (was `delete()`), `findStaleArchives()` (was `scanForStaleArchives()`). All references in `AdminService` and the test file use the new names above.

---

### Method: `archive(): string`

Creates a timestamped tar archive of the entire `cache/` directory in the app root.

**Steps**:
1. Build archive filename: `cache_YYYY_MM_DD_HH_MM_SS.tar` using `date('Y_m_d_H_i_s')`.
2. Build full archive file path: `$this->appRootDirPath . DIRECTORY_SEPARATOR . $archiveFilename`.
3. Get the cache directory path from config: `$this->config->get('cache.directory', 'cache')`.
4. Log: `'Creating cache archive'` with archive path and cache dir.
5. Execute: `exec('tar -cf ' . escapeshellarg($archiveFilePath) . ' ' . escapeshellarg($cacheDirPath), $output, $exitCode)`.
6. If `$exitCode !== 0`: throw `AdminServiceException` with message and context `['archiveFilePath' => ..., 'exitCode' => ..., 'output' => implode($output)]`.
7. Run `chmod($archiveFilePath, 0600)` — if it returns false, throw `AdminServiceException`.
8. Verify: `if (!$this->verify($archiveFilePath))` — throw `AdminServiceException`.
9. Log: `'Cache archive created successfully'` with archive path and size.
10. Return `$archiveFilePath`.

**Code Example**:

```php
public function archive(): string
{
    $archiveFilename  = 'cache_' . date('Y_m_d_H_i_s') . '.tar';
    $archiveFilePath  = $this->appRootDirPath . DIRECTORY_SEPARATOR . $archiveFilename;
    $cacheDirPath     = $this->config->get('cache.directory', 'cache');

    $this->logger->info('Creating cache archive', [
        'archiveFilePath' => $archiveFilePath,
        'cacheDirPath'    => $cacheDirPath,
    ]);

    exec(
        'tar -cf ' . escapeshellarg($archiveFilePath) . ' ' . escapeshellarg($cacheDirPath),
        $output,
        $exitCode
    );

    if ($exitCode !== 0) {
        throw new AdminServiceException(
            'Archive creation failed: tar command returned non-zero exit code',
            ['archiveFilePath' => $archiveFilePath, 'exitCode' => $exitCode, 'output' => implode("\n", $output)]
        );
    }

    if (!chmod($archiveFilePath, 0600)) {
        throw new AdminServiceException(
            'Archive creation failed: could not set permissions on archive file',
            ['archiveFilePath' => $archiveFilePath]
        );
    }

    if (!$this->verify($archiveFilePath)) {
        throw new AdminServiceException(
            'Archive creation failed: archive file verification failed after creation',
            ['archiveFilePath' => $archiveFilePath]
        );
    }

    $this->logger->info('Cache archive created successfully', [
        'archiveFilePath' => $archiveFilePath,
        'fileSize'        => filesize($archiveFilePath),
    ]);

    return $archiveFilePath;
}
```

---

### Method: `restore(string $archiveFilePath): void`

Restores the ENTIRE archive to recover all cache files to their pre-rebuild state.

**Steps**:
1. Log: `'Restoring cache from archive'` with archive path.
2. If `!file_exists($archiveFilePath)`: throw `AdminServiceException` with context.
3. Execute: `exec('tar -xf ' . escapeshellarg($archiveFilePath) . ' -C ' . escapeshellarg($this->appRootDirPath), $output, $exitCode)`.
4. If `$exitCode !== 0`: throw `AdminServiceException` with exit code and output.
5. Log: `'Cache archive restored successfully'` with archive path.

**Note on extraction target**: `tar -xf <archive> -C <appRoot>` extracts relative to the app root, which recreates the `cache/` directory structure as it was when archived (since `tar -cf` was called with a relative path like `cache/`).

**Code Example**:

```php
public function restore(string $archiveFilePath): void
{
    $this->logger->info('Restoring cache from archive', ['archiveFilePath' => $archiveFilePath]);

    if (!file_exists($archiveFilePath)) {
        throw new AdminServiceException(
            'Restore failed: archive file not found',
            ['archiveFilePath' => $archiveFilePath]
        );
    }

    exec(
        'tar -xf ' . escapeshellarg($archiveFilePath) . ' -C ' . escapeshellarg($this->appRootDirPath),
        $output,
        $exitCode
    );

    if ($exitCode !== 0) {
        throw new AdminServiceException(
            'Restore failed: tar extract command returned non-zero exit code',
            ['archiveFilePath' => $archiveFilePath, 'exitCode' => $exitCode, 'output' => implode("\n", $output)]
        );
    }

    $this->logger->info('Cache archive restored successfully', ['archiveFilePath' => $archiveFilePath]);
}
```

---

### Method: `deleteArchive(string $archiveFilePath): void`

Deletes the archive file after validation succeeds or after restore completes.

**Steps**:
1. Log: `'Deleting cache archive'` with archive path.
2. If `!file_exists($archiveFilePath)`: log warning `'Archive file not found for deletion — skipping'` and return (not an error).
3. `unlink($archiveFilePath)`.
4. Log: `'Cache archive deleted'` with archive path.

**Code Example**:

```php
public function deleteArchive(string $archiveFilePath): void
{
    $this->logger->info('Deleting cache archive', ['archiveFilePath' => $archiveFilePath]);

    if (!file_exists($archiveFilePath)) {
        $this->logger->warning('Archive file not found for deletion — skipping', [
            'archiveFilePath' => $archiveFilePath,
        ]);
        return;
    }

    unlink($archiveFilePath);

    $this->logger->info('Cache archive deleted', ['archiveFilePath' => $archiveFilePath]);
}
```

---

### Method: `verify(string $archiveFilePath): bool`

Returns `true` if the archive file exists and has a non-zero size.

```php
public function verify(string $archiveFilePath): bool
{
    return file_exists($archiveFilePath) && filesize($archiveFilePath) > 0;
}
```

---

### Method: `findStaleArchives(): array`

Scans the app root for any leftover `cache_*.tar` files from previous interrupted runs.
Called by `AdminService` on construction; each result is logged as a warning by `AdminService`.

**Steps**:
1. Log: `'Scanning for stale cache archives'` with app root path.
2. Use `glob($this->appRootDirPath . DIRECTORY_SEPARATOR . 'cache_*.tar')` to find matches.
3. If `glob()` returns `false`, treat as empty array (no stale files).
4. Return the array of file paths (may be empty).

**Note**: `CacheArchiver::findStaleArchives()` only FINDS and RETURNS stale files. It does NOT
log warnings for them — that is `AdminService`'s responsibility (per AC-14a: "AdminService logs
a warning for each stale file found"). This keeps `CacheArchiver` focused on file I/O and
avoids double-logging.

```php
public function findStaleArchives(): array
{
    $this->logger->info('Scanning for stale cache archives', [
        'appRootDirPath' => $this->appRootDirPath,
    ]);

    $pattern = $this->appRootDirPath . DIRECTORY_SEPARATOR . 'cache_*.tar';
    $found   = glob($pattern);

    return $found !== false ? $found : [];
}
```

---

## Error Handling

| Condition | Action |
|-----------|--------|
| `tar -cf` exits non-zero | Throw `AdminServiceException` with exit code + output |
| `chmod()` returns false | Throw `AdminServiceException` |
| `verify()` returns false after create | Throw `AdminServiceException` |
| Archive file not found in `restore()` | Throw `AdminServiceException` |
| `tar -xf` exits non-zero | Throw `AdminServiceException` with exit code + output |
| Archive not found in `deleteArchive()` | Log warning, return (non-fatal) |

`AdminServiceException` is auto-logged on construction (via `GCException::logException()`).
`CacheArchiver` additionally logs its own info/warning messages for observability.

---

## Unit Test Specifications

**Test file**: `tests/Unit/Services/Admin/CacheArchiverTest.php`

**Setup**: Use `vfsStream` (or a real temp directory) to simulate the file system. Mock `exec()`
calls with a test double or inject a callable. Mock `Logger` and `Config`.

### `CacheArchiver::archive()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Success | `tar` exits 0, file exists with size > 0 | Returns archive path string; path matches `cache_YYYY_MM_DD_HH_MM_SS.tar` pattern | AC-2 |
| tar fails | `tar` exits 1 | Throws `AdminServiceException` | AC-3 |
| chmod fails | tar ok, chmod returns false | Throws `AdminServiceException` | AC-14c |
| verify fails | tar ok, chmod ok, file size == 0 | Throws `AdminServiceException` | AC-3 |
| Archive filename format | Success case | Filename matches regex `/^cache_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.tar$/` | AC-2 |

### `CacheArchiver::restore()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Success | Archive exists, `tar -xf` exits 0 | No exception | Happy path |
| Archive not found | File does not exist | Throws `AdminServiceException` | AC-4 |
| tar extract fails | Archive exists, `tar -xf` exits 1 | Throws `AdminServiceException` | AC-4 |

### `CacheArchiver::deleteArchive()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| File exists | File present | `unlink()` called; no exception | AC-14b |
| File not found | File absent | Logs warning, returns without exception | Non-fatal per spec |

### `CacheArchiver::verify()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| File exists, size > 0 | Real or mocked file | Returns `true` | AC-3 |
| File does not exist | No file | Returns `false` | AC-3 |
| File exists, size == 0 | Empty file | Returns `false` | AC-3 |

### `CacheArchiver::findStaleArchives()`

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| No stale archives | Empty app root | Returns `[]` | AC-14a |
| One stale archive | One `cache_*.tar` present | Returns array with one path | AC-14a |
| Multiple stale archives | Several `cache_*.tar` files | Returns all paths | AC-14a |
| `glob()` returns false | (edge case in mocking) | Returns `[]` | Defensive |

### Key Scenario: Full archive lifecycle

**Setup**: Configure temp directory as app root; `Config::get('cache.directory')` returns
a temp `cache/` subdir with some PHP files.  
**Action**: Call `archive()`, then `verify()`, then `deleteArchive()` with the returned path.  
**Expected**:
- `archive()` returns a path ending in `.tar`
- `verify()` on the returned path returns `true`
- `deleteArchive()` removes the file; `file_exists($path)` returns `false` afterward  
**Why**: Validates the full create-verify-delete cycle that `AdminService` follows on success.

### Key Scenario: Stale archive detection

**Setup**: Place two `cache_2026_01_01_00_00_00.tar` and `cache_2026_01_02_00_00_00.tar` files
in the mocked app root.  
**Action**: `$archiver->findStaleArchives()`  
**Expected**: Returns an array with both file paths.  
**Why**: `AdminService` depends on this to log warnings during initialization (AC-14a).

---

## Notes

- The `tar` command uses a relative path for the cache directory (`cache/`) so that `tar -xf`
  restores the files relative to the app root directory (`-C $appRootDirPath`). This ensures
  restore recreates the correct directory structure.
- `escapeshellarg()` is used on all shell arguments to prevent injection.
- `filesize()` may return cached values — no cache-busting needed here because the file is
  freshly written by `tar` in the same request.
- The class is under 150 lines (well within the 300-line limit). Extract `buildArchiveFilePath()`
  as a private helper if needed to keep `archive()` readable.
- **Archive failure propagation**: `CacheArchiver::archive()` still throws `AdminServiceException` on failure. It is `AdminService`'s responsibility (not `CacheArchiver`'s) to catch that exception, emit a `done:false` SSE event (via the `$onStep` / stream mechanism), and exit. `CacheArchiver` does not suppress or catch its own exceptions — it always throws on failure and lets the caller handle the error boundary.
- **AC-14b method names**: `AdminService` calls `$this->archiver->deleteArchive($archivePath)` (not `delete()`). References to `scanForStaleArchives()` in `AdminService` and tests are now `findStaleArchives()`.
- `glob()` is acceptable here (unlike in `CacheRebuilder::clear()`) because it is used only for
  file discovery (not for clearing), and `cache_*.tar` files are in the flat app root directory
  (not a subdirectory tree).
- In production, the app root should be above Apache `DocumentRoot` (security requirement from
  spec). The class does not enforce this — it is a deployment concern.
- Do NOT call `SchemaGenerator::createDatabaseIfNotExists()` — that belongs to initial setup,
  not cache rebuild (explicit constraint from spec).
