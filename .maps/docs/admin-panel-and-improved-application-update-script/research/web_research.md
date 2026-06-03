# Web Research: Admin Panel and Improved Application Update Script

## Search Terms Used

- PHP CLI-only script enforcement php_sapi_name prevent web execution best practices
- PHP script outside web root security scripts directory CLI only deployment pattern
- PHP CLI script design getopt argument parsing exit codes dry-run verbose mode
- PHP CLI script exit codes conventions 0 success non-zero error best practices linux
- PHP application update script dry-run mode verbose quiet flags pattern example
- Safe application update workflow archive before delete rollback on failure PHP deployment
- Idempotent update operations selective cache rebuild rollback strategy PHP application
- Admin panel UI patterns cache management UX progress feedback confirmation dialogs
- React admin panel async long-running operation toast notification success error state management
- React Tailwind CSS loading spinner async operation useEffect useState admin UI pattern
- React admin panel operation status display individual bulk operations tailwind no library pattern

---

## Key Findings

### Topic 1: PHP CLI-Only Script Enforcement

**Summary:**
The canonical pattern is to check `PHP_SAPI === 'cli'` (or `php_sapi_name() === 'cli'`) at the very top of any script that must only run from the command line. The `PHP_SAPI` constant is preferred because it is resolved at compile time (faster, no function call overhead) and "directly reports the SAPI, leaving little room for ambiguity."

**Recommended early-exit pattern:**
```php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}
```

**Key gotchas:**
- `PHP_SAPI === 'cli-server'` is PHP's built-in dev server — distinct from `'cli'`. Scripts exposed via the dev server would pass a `php_sapi_name()` check against `'cli-server'`, so always use `=== 'cli'` exactly.
- Environment variables (`REQUEST_METHOD`, `$_SERVER` superglobals) can be spoofed on the CLI, making them unreliable for this check.
- `register_argc_argv` was removed in PHP 8.0 — do not rely on it.
- CLI scripts may load a different `php.ini` than the web server; verify with `php --ini` if config values are critical.
- `max_execution_time` is zero (no timeout) on CLI — long-running operations will not be killed automatically.

**File placement (defense in depth):**
Placing scripts outside the web root (`scripts/` or any directory not under `DocumentRoot`) is the strongest protection. Even if the SAPI check is bypassed somehow, the web server cannot serve files it cannot reach. This is a standard Symfony/Drupal/Laravel pattern.

**Sources:**
- [How to restrict the execution of a PHP script to CLI - Our Code World](https://ourcodeworld.com/articles/read/951/how-to-restrict-the-execution-of-a-php-script-to-the-command-line-detect-if-running-php-from-the-cli)
- [PHP: Best Determine if CLI or Web Server - codegenes.net](https://www.codegenes.net/blog/php-how-to-best-determine-if-the-current-invocation-is-from-cli-or-web-server/)
- [PHP: php_sapi_name - Manual](https://www.php.net/manual/en/function.php-sapi_name.php)
- [PHP: Case 3: setting doc_root or user_dir - Manual](https://www.php.net/manual/en/security.cgi-bin.doc-root.php)

**Recommendation:**
In `application-update.php`, place `PHP_SAPI !== 'cli'` check as the very first executable line. Move the file to `scripts/` (outside web root) for defense in depth. Both protections together are more robust than either alone.

---

### Topic 2: PHP CLI Argument Parsing — getopt, Exit Codes, Verbose/Dry-Run Modes

**Summary:**
PHP's built-in `getopt()` covers the basics but has important limitations. For a well-designed update script the recommended patterns are straightforward.

**`getopt()` syntax:**
```php
$shortOptions = "vq";          // -v (verbose), -q (quiet) — no value
$longOptions = [
    'metadata',                // --metadata    (flag, no value)
    'routes',                  // --routes
    'docs',                    // --docs
    'navigation',              // --navigation
    'all',                     // --all
    'schema',                  // --schema
    'permissions',             // --permissions
    'dry-run',                 // --dry-run
];
$options = getopt($shortOptions, $longOptions, $restIndex);
```

**Critical `getopt()` gotcha:**
Parsing stops at the first non-option argument (bare string without a `-` or `--` prefix). Anything after that is silently discarded. This means `--dry-run` placed after a non-option token will be ignored. Always put flags before any positional arguments, or use `$restIndex` to detect where option parsing stopped.

**Exit code conventions (Linux standard):**
| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | General / catchall error |
| 2 | Misuse of shell built-ins / invalid arguments |
| 126 | Command cannot execute |
| 127 | Command not found |

Use named constants to avoid magic numbers:
```php
const EXIT_SUCCESS = 0;
const EXIT_FAILURE = 1;
const EXIT_INVALID_ARGS = 2;
```

Always write errors to `STDERR`: `fwrite(STDERR, "Error: ...\n");`

**Verbosity levels (Symfony pattern, adaptable to raw PHP):**
| Flag | Level | Behavior |
|------|-------|----------|
| `--silent` | Silent | Suppress all output |
| `-q` / `--quiet` | Quiet | Suppress output, show errors only |
| (none) | Normal | Show key operation messages |
| `-v` | Verbose | Show each individual step |
| `-vv` | Very verbose | Debug detail |

A simple verbosity integer is easy to implement without a framework:
```php
$verbosity = 1; // normal
if (isset($options['q'])) $verbosity = 0;
if (isset($options['v'])) $verbosity = 2;

function output(string $msg, int $level = 1, int $verbosity): void {
    if ($verbosity >= $level) {
        echo $msg . "\n";
    }
}
```

**Dry-run pattern:**
```php
$dryRun = isset($options['dry-run']);

function executeStep(callable $action, string $description, bool $dryRun): void {
    if ($dryRun) {
        echo "[DRY-RUN] Would: $description\n";
        return;
    }
    $action();
}
```

The Composer and PHP-CS-Fixer tools both use `--dry-run` in exactly this way. The Symfony Console framework formalizes this into the six verbosity levels pattern. A raw PHP script should implement a simplified version of the same concept.

**Sources:**
- [PHP: getopt - Manual](https://www.php.net/manual/en/function.getopt.php)
- [Get Command-Line Arguments With PHP $argv or getopt - Envato Tuts+](https://code.tutsplus.com/get-command-line-arguments-with-php-argv-or-getopt--cms-39201t)
- [Symfony Console: Verbosity Levels](https://symfony.com/doc/current/console/verbosity.html)
- [CLI Applications Tips & Tricks - dry-run - DEV Community](https://dev.to/gatbakan/writing-command-line-applications-tips-tricks-dry-run-15op)
- [A clean way to safely call destructive methods in PHP - DEV Community](https://dev.to/mattkenefick/a-clean-way-to-safely-call-your-potentially-destructive-methods-in-php-1pi7)
- [Mastering PHP exit() - TheLinuxCode](https://thelinuxcode.com/php-exit-function/)
- [Exit best practices - codestudy.net](https://www.codestudy.net/blog/exit-exit-exit-0-die-die-0-how-to-exit-script/)

**Recommendation:**
Implement three modes: `--dry-run` (show what would happen, do nothing), `-v` (verbose, show each step), `-q` (quiet, errors only). Use named exit code constants. Validate required conditions at startup and exit early with descriptive error messages before doing any work.

---

### Topic 3: Safe Application Update Workflow — Archive, Rollback, Selective Updates

**Summary:**
The established patterns from tools like PHP Deployer and AWS CodeDeploy map well to the cache rebuild workflow.

**Archive-before-delete pattern:**
The core principle: never delete or overwrite existing artifacts before you have a known-good backup. Create a timestamped archive (`tar -czf`) of the target directory before clearing it. This creates a rollback artifact even if the rebuild fails completely.

```bash
# Analogous to what the PHP service should do:
tar -czf cache_YYYY_MM_DD_HH_MM_SS.tar.gz cache/
```

**Two-phase operation:**
1. **Prepare phase**: Archive existing files, verify archive was created successfully.
2. **Activate phase**: Clear old files, rebuild, validate. If validation fails, restore from archive.

**Rollback-on-failure pattern:**
From Amazon's Builders' Library: "divide such a change into two parts and perform a two-phase deployment." Before activating any destructive change (clearing cache), verify the archive exists and is complete. If the rebuild step fails, automatically restore from the archive.

```php
// Pseudo-code for the service:
$archivePath = $this->archiveCache($targetFiles);  // create tar
$this->clearCache($targetFiles);                    // delete
try {
    $this->rebuildCache($targetFiles);              // regenerate
    $this->validateCache($targetFiles);             // php -l check
} catch (Throwable $e) {
    $this->restoreFromArchive($archivePath);        // rollback
    throw new CacheRebuildException("Rebuild failed, restored from archive", 0, $e);
}
```

**Selective / partial update:**
The spec requires being able to update any subset of cache files (metadata only, routes only, all, etc.). The pattern is a bitmask or flag set passed to the service that controls which components are included in each phase:

```php
$options = new CacheRebuildOptions(
    rebuildMetadata: true,
    rebuildRoutes: false,
    rebuildNavigation: true,
    rebuildDocs: false,
    updateSchema: true,
    updatePermissions: true,
);
```

**Idempotency:**
Each rebuild operation should be safe to re-run. If a rebuild is requested for a cache that is already current, the operation should succeed without causing errors. Avoid operations like "truncate and reinsert" inside the archive/clear cycle unless the operation is wrapped in a transaction.

**Sources:**
- [Ensuring rollback safety during deployments - AWS Builders' Library](https://aws.amazon.com/builders-library/ensuring-rollback-safety-during-deployments/)
- [How to Roll Back a Failed Deployment in 30 Seconds - DEV Community](https://dev.to/deploynix/how-to-roll-back-a-failed-deployment-in-30-seconds-25ok)
- [Automating PHP Application Deployment with PHP Deployer - Alibaba Cloud](https://alibaba-cloud.medium.com/automating-your-php-application-deployment-with-php-deployer-b00c1dc64d4a)
- [Handling Rollback Strategies for Failed Product Deployments - Agile Seekers](https://agileseekers.com/blog/handling-rollback-strategies-for-failed-product-deployments)

**Recommendation:**
Implement archive-then-clear-then-rebuild-then-validate in the `ApplicationUpdateService`. Catch any exception from rebuild/validate and automatically restore from the archive before re-throwing. Make each step individually logged so the admin UI and CLI both get clear step-by-step feedback.

---

### Topic 4: Admin Panel UI Patterns for Cache Management

**Summary:**
UX research strongly supports a consistent set of patterns for admin operations that are potentially destructive, long-running, or irreversible.

**Confirmation before destructive actions:**
- Use modal dialogs for irreversible or high-impact operations. Explicitly state the consequence in active voice: "This will delete all cache files and rebuild them from source."
- For doubly-dangerous operations (e.g., clearing production cache), a "double confirmation" pattern is acceptable — require the user to type a confirmation phrase or click through two modals.
- Always provide a clear cancel option (cancel button + X close button).
- Use red/danger button styling for the confirm action; neutral for cancel.

**Progress feedback for long-running operations:**
- Show a loading state immediately when the operation begins — do not wait for the operation to complete before giving feedback.
- For multi-step operations (archive → clear → rebuild → validate), show step-by-step progress: a step list where each step transitions from "pending" → "in progress" → "done" or "failed".
- Use a progress bar or step indicator within the confirmation/progress modal.

**Success and error state display:**
- After completion, show a summary: how many operations succeeded, how many failed, and what specifically failed (not just "an error occurred").
- For partial success (some components rebuilt, others failed), show a mixed-state summary with per-component status.
- Toast notifications for quick, non-blocking feedback; modals or inline status panels for detailed multi-step results.

**Individual vs. bulk operations:**
- Support both: individual buttons per cache component AND a "Rebuild All" bulk action.
- Bulk action dropdown or button group pattern works well (e.g., checkboxes + "Selected: Rebuild" action, or pre-defined button per component with a "Rebuild All" button at the top).
- After a bulk operation, show per-component results so the admin knows exactly which components were rebuilt successfully.

**Cache status display:**
- Show the last-modified timestamp or "last rebuilt" time for each cache component.
- Use color-coded status indicators (green = fresh, yellow = stale/unknown, red = missing/failed).

**Sources:**
- [Bulk action UX: 8 design guidelines - Eleken](https://www.eleken.co/blog-posts/bulk-actions-ux)
- [Admin Dashboard UI/UX Best Practices 2025 - Medium](https://medium.com/@CarlosSmith24/admin-dashboard-ui-ux-best-practices-for-2025-8bdc6090c57d)
- [Modal UX design: Patterns, examples, best practices - LogRocket](https://blog.logrocket.com/ux-design/modal-ux-design-patterns-examples-best-practices/)
- [Top 10 UI Components to Boost Your Admin Dashboard Design - Bootstrapdash](https://www.bootstrapdash.com/blog/admin-dashboard-ui-components)

**Recommendation:**
For the Gravitycar admin panel:
1. Default view shows a card per cache component with last-rebuilt timestamp and a "Rebuild" button.
2. A "Rebuild All" button triggers a confirmation modal listing which components will be rebuilt and what secondary operations (schema update, permissions update) will run.
3. The confirmation modal transitions to a progress modal on confirm, showing each step (Archive → Clear → Rebuild → Validate) with live status indicators.
4. On completion, the modal shows a per-component result summary with success/failure states.

---

### Topic 5: React Admin Panel Patterns — Loading States, Async Feedback, Tailwind

**Summary:**
The established React + Tailwind pattern for async operations with visible feedback uses `useState` to track three states (`loading`, `result`, `error`) and conditionally renders different UI for each.

**Core async operation hook pattern:**
```typescript
const [loading, setLoading] = useState(false);
const [result, setResult] = useState<RebuildResult | null>(null);
const [error, setError] = useState<string | null>(null);

const handleRebuild = async (options: RebuildOptions) => {
    setLoading(true);
    setError(null);
    setResult(null);
    try {
        const res = await apiService.post('/admin/rebuild-cache', options);
        setResult(res.data);
    } catch (err) {
        setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
        setLoading(false);
    }
};
```

**Tailwind-native loading spinner (no library needed):**
```tsx
{loading && (
    <div className="flex items-center gap-2">
        <div className="animate-spin h-5 w-5 border-2 border-blue-500 border-t-transparent rounded-full" />
        <span>Rebuilding cache...</span>
    </div>
)}
```

**Multi-step progress display:**
For operations with discrete steps (Archive, Clear, Rebuild, Validate), the API response should return step-level status. The component renders a step list:
```tsx
{result?.steps.map(step => (
    <div key={step.name} className={`flex items-center gap-2 ${
        step.status === 'done' ? 'text-green-600' :
        step.status === 'failed' ? 'text-red-600' : 'text-gray-500'
    }`}>
        <StepIcon status={step.status} />
        <span>{step.name}</span>
        {step.error && <span className="text-sm text-red-500">{step.error}</span>}
    </div>
))}
```

**Confirmation dialog (no external library):**
Tailwind modal with `fixed inset-0 bg-black bg-opacity-50` overlay + centered dialog box. State-driven: `showConfirm` boolean gates rendering.

**Status display for individual components:**
Each cache component card uses color classes to show status:
- `text-green-600` / `bg-green-50` — last rebuilt timestamp, healthy
- `text-yellow-600` / `bg-yellow-50` — stale or age unknown
- `text-red-600` / `bg-red-50` — missing or last rebuild failed

**Toast notification (no external library — inline implementation):**
```typescript
const [toast, setToast] = useState<{msg: string; type: 'success'|'error'} | null>(null);
// Render: fixed bottom-right div, auto-dismiss via setTimeout
```

**Protected route pattern (for admin-only page):**
```tsx
// In App.tsx:
<Route path="/admin" element={
    <Layout>
        <ProtectedRoute requiredRole="admin">
            <AdminPage />
        </ProtectedRoute>
    </Layout>
} />
```

**Sources:**
- [React Design Patterns and Best Practices 2025 - Telerik](https://www.telerik.com/blogs/react-design-patterns-best-practices)
- [The Essential Guide to Tailwind CSS Best Practices for React Developers 2025 - DEV Community](https://dev.to/sorenvahlreact/the-essential-guide-to-tailwind-css-best-practices-for-react-developers-2025-2hjh)
- [How to handle loading spinners in React - CoreUI](https://coreui.io/answers/how-to-handle-loading-spinners-in-react/)
- [React-Toastify 2025 update - LogRocket](https://blog.logrocket.com/react-toastify-guide/)
- [Handling API Errors Gracefully in React - OneUptime](https://oneuptime.com/blog/post/2026-01-15-handle-api-errors-gracefully-react/view)
- [Creating a Loading Page with React and Tailwind CSS - JavaScript in Plain English](https://javascript.plainenglish.io/creating-a-loading-page-with-react-and-tailwind-css-9ec618fce0a0)

**Recommendation:**
Build the admin panel as a single React page (`AdminPage.tsx`) with:
1. A grid of cache-component cards (one per cache type), each showing last-rebuilt timestamp and a "Rebuild" button.
2. A shared confirmation modal component that receives operation details as props and displays step-by-step progress after confirmation.
3. All state managed locally with `useState`/`useCallback` — no Redux or external state needed for this scope.
4. All styling with Tailwind utility classes — no component library. Use `animate-spin` for loading spinner.
5. API calls through the existing `apiService` singleton in `services/api.ts`.

---

## Recommended Approaches

### For `application-update.php` (CLI script)

1. **CLI guard first**: `PHP_SAPI !== 'cli'` check at the top, before any includes or autoloading.
2. **Move file to `scripts/`**: Outside the Apache `DocumentRoot` for defense in depth.
3. **Argument design**:
   - Flag options (no value required): `--metadata`, `--routes`, `--docs`, `--navigation`, `--all`, `--schema`, `--permissions`, `--dry-run`, `-v`, `-q`
   - Use `getopt()` with no positional args; all options are flags.
4. **Exit codes**: 0 = success, 1 = operation failed, 2 = invalid arguments/missing prerequisites.
5. **Verbosity**: 0 (quiet) / 1 (normal) / 2 (verbose) controlled by `-q` / `-v`.
6. **Dry-run**: Pass boolean through to `ApplicationUpdateService`; service logs what it would do instead of acting.

### For `ApplicationUpdateService` (shared service)

1. **Archive first**: Always create a timestamped tar archive before clearing.
2. **Selective component support**: Accept a `CacheRebuildOptions` value object that specifies which components to include.
3. **Rollback on failure**: Catch exceptions from rebuild/validate steps and restore from archive before re-throwing.
4. **Step result reporting**: Return a structured result object (array of step name → status/error) so both CLI and API caller can report progress.
5. **Idempotent**: Each operation is safe to re-run; no operation assumes a clean-slate state.

### For `AdminAPIController` (backend)

1. **Admin-only RBAC**: `$rolesAndActions = ['admin' => ['*']]`.
2. **Single rebuild endpoint**: `POST /admin/rebuild-cache` with JSON body containing options.
3. **Return step-level results**: Response body includes per-step status so the UI can show granular progress.

### For `AdminPage.tsx` (React frontend)

1. **Component cards**: One card per cache type, showing status and individual rebuild button.
2. **Confirmation modal**: Describe what will happen, list secondary operations (schema/permissions) when metadata is selected.
3. **Progress display**: Step list that updates after operation completes (not real-time streaming — single API call, response shows all steps).
4. **Protected route**: Admin-only via `useAuth()` role check or `<ProtectedRoute>`.

---

## Potential Pitfalls

1. **`getopt()` stops at first non-option**: Put all flags before any positional arguments, or rely on `$restIndex` to detect dropped arguments.
2. **`die("message")` exits with code 0**: Always use `exit(1)` for error exits, not `die("error message")`.
3. **`php_sapi_name()` vs `PHP_SAPI`**: Prefer `PHP_SAPI` constant — it cannot be overridden at runtime.
4. **`cli-server` SAPI**: PHP's built-in dev server returns `'cli-server'`, not `'cli'`. Always compare with `=== 'cli'` exactly.
5. **Archive before clearing**: Never clear cache files before confirming the archive was created and is complete. Check the archive file exists and has non-zero size.
6. **Recursive directory clearing**: PHP's `glob('cache/*')` misses subdirectories. Use `RecursiveDirectoryIterator` or `glob('cache/documentation/*')` explicitly. (This is a known bug in the existing `setup.php`.)
7. **Long-running operations in React**: Don't disable the button without showing a loading indicator — users will think the click was ignored.
8. **Destructive confirmation modals**: Use explicit, consequence-describing text. "Delete all cache files?" is better than "Are you sure?".
9. **Silent failure on partial rebuild**: If one cache component fails to rebuild, log and report the error but continue with other components unless they depend on the failed one (e.g., schema update depends on metadata cache being valid).

---

## Libraries / Tools to Consider

- **`getopt-php/getopt-php`** (GitHub): Drop-in `getopt()` replacement with better long option support, aliasing, and validation. Useful if native `getopt()` proves too limiting. [GitHub](https://github.com/getopt-php/getopt-php)
- **`garden-cli`** (Vanilla): Full-featured PHP CLI parser. Overkill for a single update script; useful if the CLI script grows significantly. [GitHub](https://github.com/vanilla/garden-cli)
- **Tailwind `animate-spin`**: Native Tailwind utility — no library needed for a loading spinner on the React side.
- **React `useState` + `useCallback`**: Sufficient for admin panel state management without Redux or React Query for this scope.
