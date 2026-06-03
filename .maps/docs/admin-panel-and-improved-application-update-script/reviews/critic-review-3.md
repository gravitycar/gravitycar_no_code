# Critic Review #3 — Implementation Plans
## Epic: Admin Panel and Improved Application Update Script
## Review Date: 2026-06-01
## Reviewer: Critic Agent (Task 126)

---

## Summary

All 16 implementation plans (CAT-01 through CAT-16) were reviewed against the specification and against each other for gaps, contradictions, and missing detail. The plans are generally well-structured and internally consistent. No plan exceeds the 300-line file limit. Test specifications are thorough.

**12 questions were raised** covering: method name divergence from spec, SSE event ordering, unresolved Router compatibility, PHP syntax issue in the CLI script, potential circular DI, misleading named constructor semantics, archive phase exception timing, failure path step naming, and behavioral change in auth redirect.

---

## Plan-by-Plan Assessment

### CAT-01 — CacheComponent Constants Class
**Status: APPROVED**
Complete, correct, well-specified. PHP 8.2 typed constants used correctly. `isValid()` and `all()` share the same `ALL_COMPONENTS` constant — no drift risk. Tests are comprehensive.

### CAT-02 — CacheRebuildOptions + CacheStepResult
**Status: APPROVED with minor note**
Both value objects are well-designed. `CacheStepResult::toArray()` correctly produces the SSE event shape that CAT-09 and CAT-14 consume. The `fromArray()` method handles CLI and API paths cleanly.

Minor: The `'all'` component string for archive steps is a literal not covered by `CacheComponent::isValid()` — this is intentional and documented, but developers creating `CacheStepResult` for the archive step need to know to use `'all'` literally.

### CAT-03 — CacheRebuildResult
**Status: APPROVED with question**
The `toArray()` method correctly always emits `done: true`, enabling CAT-14's frontend detection. The `addStep()` mutability pattern is acceptable for single-threaded use.

**Question Q-134**: `failure([], 'message')` produces `isSuccess() === true` (vacuously true, no failed steps). This is documented but the `failure()` named constructor is misleading and unused by CAT-07.

### CAT-04 — AdminServiceException
**Status: APPROVED**
Follows established exception pattern exactly. The note that `GCException` uses `ServiceLocator::getLogger()` internally is acceptable since this is existing code, not new code. Test spec correctly excludes log-verification tests per CLAUDE.md.

### CAT-05 — CacheArchiver
**Status: APPROVED with question**
The plan is internally consistent and the implementation is correct. However, the public method names (`archive()`, `deleteArchive()`, `findStaleArchives()`) differ from the spec's stated interface (`create()`, `delete()`, `scanForStaleArchives()`).

**Question Q-127**: Method names in CAT-05 diverge from spec. Plans are mutually consistent (CAT-07 uses CAT-05 names), but spec says `create(array $components)` — are the plans the final design, or should they be renamed to match the spec?

Note: `archive()` takes no `$components` parameter (archives the full `cache/` dir), which correctly implements "ENTIRE archive" semantics from AC-4, but differs from the spec's `create(array $components)` signature.

### CAT-06 — CacheRebuilder
**Status: APPROVED with question**
`rebuild()` correctly accepts `callable $onStep` and fires it for each step. The `$onStep` callback design propagates events up through AdminService to the SSE stream. The use of `CacheComponent::all()` as the canonical iteration order for rebuild ensures METADATA always precedes ROUTES — correct.

**Question Q-133**: `CacheRebuilder.__construct()` calls `ContainerConfig::getContainer()` to retrieve six engine services. This runs during `AdminService`'s construction which itself runs during container resolution. Potential circular DI risk.

### CAT-07 — AdminService
**Status: APPROVED with questions**
The orchestration logic is sound. The `$onStep` parameter is correctly added to `performCacheRebuild()` (spec AC-1 says it accepts `CacheRebuildOptions` and returns `CacheRebuildResult` — both still true; the additional parameter is valid). The archive phase correctly sits outside the try/catch.

**Question Q-128**: `runClearPhase()` and `runValidatePhase()` emit all in_progress events before calling `clear()`/`validate()`, then all success events after. The spec's SSE example shows per-component interleaving (in_progress A → success A → in_progress B → success B). These differ.

**Question Q-131**: Archive phase throws propagate to `AdminAPIController` AFTER SSE headers have been set. The Router cannot return a clean HTTP 500 at that point.

**Question Q-137**: The catch block adds a `CacheStepResult::failed('restore', 'all', ...)` step, but `'restore'` is not a valid step name per the spec. The step also lacks a preceding `in_progress` event, so the SSE stream gives no warning while the restore operation runs.

### CAT-08 — ContainerConfig Registration
**Status: APPROVED with question**
The registration pattern exactly follows the `TMDBController` pattern. Passing `null` for `archiver` and `rebuilder` is correctly handled by `AdminService`'s null-coalescing.

**Question Q-136**: Is `$di->set('admin_service', $di->lazyNew(...))` a true singleton in Aura DI? If not, the stale archive scan runs on every `AdminService` instantiation. Should verify against the Aura DI pattern used by other services.

### CAT-09 — AdminAPIController
**Status: APPROVED with questions**
Auth/RBAC enforcement by the Router before method dispatch is correctly relied upon. The `$rolesAndActions = ['admin' => ['*']]` pattern is correct. Validation before SSE headers is correctly placed.

**Question Q-129**: `array_merge($result->toArray(), ['done' => true])` — `toArray()` already includes `done: true`. The merge is harmless but redundant. More importantly: `toArray()` includes a `steps` array in the final event; the spec only shows `done/success/message`. Should the final event include `steps`?

**Question Q-130**: `handleCacheRebuild()` is `void` — does the Router tolerate this? The plan flags this as "verify Router behavior" but gives no resolution. This is a potential build-breaking issue if the Router expects an array.

### CAT-10 — CLI Script
**Status: APPROVED with question**
Bootstrap via `new Gravitycar()->bootstrap()` is correct; no `ReflectionClass` usage. Exit code constants, flag parsing, `ApplicationUpdateRunner` extraction for testability are all well-designed.

**Question Q-132**: PHP `use` statements cannot appear after executable statements (`if`, `define()`). The "Complete Script Structure" lists `use` statements after `define()` and `require_once`, which is invalid PHP. The correct approach is to place `use` at the top of the file before any executable code.

### CAT-11 — Migrate Build/Deploy Scripts + Deprecate setup.php
**Status: APPROVED**
All three required file changes are specified precisely (exact line numbers, exact old/new content). The `docker-entrypoint.sh` exclusion is correctly identified and justified. Manual validation steps are appropriate since this is shell script changes.

### CAT-12 — handleAuthError Utility
**Status: APPROVED with question**
The extraction pattern is correct. The session-expired `alert()` logic stays in `api.ts` (not moved to the utility) — good separation.

**Question Q-138**: The existing axios interceptor 401 branch uses `window.location.href` (hard reload); `handleAuthError(401)` uses `imperativeNavigate` (soft SPA navigation). This is a behavioral change that may leave stale auth context state in memory. Is this intentional?

### CAT-13 — ProtectedRoute Component
**Status: APPROVED**
All three states (loading/unauth/wrong-role) are correctly handled in order. The `isLoading` guard prevents flash-redirects. `replace` on all `<Navigate>` calls prevents history pollution. Return type `React.ReactElement` (not `null`) means all paths are covered.

The `requiredRole !== undefined` check (rather than `!requiredRole`) correctly handles hypothetical empty string cases. Tests are comprehensive.

### CAT-14 — ConfirmRebuildModal
**Status: APPROVED with question**
SSE stream parsing using `\n\n` splitting is correct for the SSE format the backend produces. The upsert logic (`findIndex` by `stepName+component`) correctly handles `in_progress` → final status transitions. `DoneEvent` detection via `parsed.done === true` is the right discriminant.

**Question Q-135**: The main `handleConfirm()` code example does not show the try/catch wrapper that the Error Handling section specifies. Inconsistency in the plan — developer needs clarity on which representation to implement.

### CAT-15 — CacheManagementPanel
**Status: APPROVED**
`CACHE_COMPONENTS` at module level (not inside component) correctly follows CLAUDE.md. Derived `options` object (not stored in state) is the right pattern. `updateSchema: isMetadataSelected && updateSchema` correctly prevents the backend from being asked to run schema migration without a metadata rebuild.

### CAT-16 — AdminPage + App.tsx Route Registration
**Status: APPROVED**
The route nesting `<Layout><ProtectedRoute requiredRole="admin"><AdminPage /></ProtectedRoute></Layout>` matches the spec exactly. `/admin` placement before `/:modelName` catch-all is correct. No double-wrapping of Layout.

---

## Cross-Plan Consistency Checks

### SSE Contract (CAT-09 ↔ CAT-14)
- Progress event shape: CAT-09 uses `$step->toArray()` → `{stepName, component, status, errorMessage}`. CAT-14 `CacheStepResultData` interface matches exactly. **CONSISTENT.**
- Final `done` event: CAT-03 `toArray()` produces `{done, success, message, steps[]}`. CAT-14 `DoneEvent` interface expects `{done, success, message, steps?}`. **CONSISTENT** (steps optional).
- Detection signal: CAT-03 always emits `done: true`; CAT-14 checks `parsed.done === true`. **CONSISTENT.**

### $onStep Callback (CAT-06 ↔ CAT-07)
- CAT-06 `rebuild()` accepts `callable $onStep` (4th param). CAT-07 passes a closure wrapping `$result->addStep()` and the outer `$onStep`. **CONSISTENT.**
- The inner closure in CAT-07 correctly propagates both accumulation (into `$result`) and streaming (via outer `$onStep`). **CONSISTENT.**

### CacheStepResult::toArray() (CAT-02 ↔ CAT-09 ↔ CAT-14)
- CAT-02 defines `toArray()` keys: `stepName`, `component`, `status`, `errorMessage`.
- CAT-09 calls `$step->toArray()` and passes directly to `emitEvent()`.
- CAT-14 parses `{stepName, component, status, errorMessage}` from SSE.
- **CONSISTENT** — all three agree on the shape.

### Archive lifecycle (CAT-05 ↔ CAT-07)
- CAT-07 calls archive BEFORE clear (correct — AC-2).
- CAT-07 calls delete after success AND after restore (correct — AC-14b).
- CAT-05 `deleteArchive()` is non-fatal on missing file (correct — AC-14b logs warning, doesn't throw).
- **CONSISTENT** except for method name divergence (Q-127).

### Auth error utility (CAT-12 ↔ CAT-14)
- CAT-12 exports `handleAuthError(status: number): void`.
- CAT-14 imports from `../../utils/authError` and calls `handleAuthError(response.status)` for 401/403.
- **CONSISTENT** on import path and call signature.

### ProtectedRoute (CAT-13 ↔ CAT-16)
- CAT-13 defines `ProtectedRoute` at `src/components/ProtectedRoute.tsx`, default export.
- CAT-16 imports `from './components/ProtectedRoute'` and wraps with `<ProtectedRoute requiredRole="admin">`.
- No double-wrapping of Layout.
- **CONSISTENT.**

### ContainerConfig (CAT-08 ↔ CAT-07 ↔ CAT-09)
- CAT-08 passes `null` for `archiver` and `rebuilder` in `$di->params` for `AdminService`.
- CAT-07 constructor handles `null` via `?? new CacheArchiver(...)`.
- CAT-08 wires `AdminService` as 7th param to `AdminAPIController`.
- CAT-09 constructor accepts `AdminService $adminService = null` and falls back to container.
- **CONSISTENT.**

### CLI Bootstrap (CAT-10)
- Uses `new Gravitycar()->bootstrap()` — correct per AC-42a.
- Does NOT use `ReflectionClass` — correct.
- Services obtained via `ContainerConfig::getContainer()` — correct.
- **CONSISTENT** with spec.

### docker-entrypoint.sh (CAT-11)
- Correctly excluded from the migration scope — calls `setup.php` for user seeding which `application-update.php` does not replace (spec DO NOT: "Do NOT create users or roles from AdminService or application-update.php").
- **CONSISTENT** with spec.

---

## Questions Raised

| ID | Plan | Issue |
|----|------|-------|
| Q-127 | CAT-05 | CacheArchiver method names differ from spec (`archive` vs `create`, `deleteArchive` vs `delete`, `findStaleArchives` vs `scanForStaleArchives`). Is spec out of date, or should plans be updated? |
| Q-128 | CAT-07 | `runClearPhase()` batches all in_progress before calling `clear()`, then batches all success. Spec shows per-component interleaving. Which is correct? |
| Q-129 | CAT-09 | Final SSE event from `toArray()` includes `steps[]` array. Spec shows only `done/success/message`. Should `steps` be included? `done` key in `array_merge` is also redundant. |
| Q-130 | CAT-09 | Router compatibility with `void` controller method return is flagged as "verify" with no resolution. If Router requires return value, SSE stream breaks. |
| Q-131 | CAT-07/09 | Archive phase exception propagates after SSE headers are already set. Router cannot emit clean HTTP 500. Should archive failures be caught and emitted as `done:false` SSE events? |
| Q-132 | CAT-10 | `use` statements listed after `define()` and `require_once` in structure — invalid PHP. `use` must appear before any executable code. |
| Q-133 | CAT-06 | `CacheRebuilder.__construct()` calls `ContainerConfig::getContainer()` during `AdminService` construction, which happens during container resolution. Potential circular DI. |
| Q-134 | CAT-03 | `CacheRebuildResult::failure([], 'msg')` produces `isSuccess()===true` (no steps = no failures). Misleading named constructor — should it set a force-failed flag? |
| Q-135 | CAT-14 | `handleConfirm()` code example omits try/catch. Error handling section shows it should be wrapped. Inconsistency; developer needs clarity. |
| Q-136 | CAT-08 | Is `$di->set('admin_service', $di->lazyNew(...))` a singleton in Aura DI? If not, stale archive scan runs on every instantiation. |
| Q-137 | CAT-07 | Failure path adds `failed('restore', 'all', ...)` step — `'restore'` is not a valid step name per spec. Also lacks a preceding `in_progress` for restore, leaving the SSE stream dark during restore operation. |
| Q-138 | CAT-12 | Existing axios interceptor uses `window.location.href` (hard reload) on 401; new utility uses `imperativeNavigate` (soft SPA nav). Behavioral change may leave stale auth context state. |

---

## Verdict

Plans are buildable as-is with the following caveats:
- **Blocking**: Q-130 (Router void return) and Q-132 (PHP `use` statement order) could cause the code to fail at runtime if not resolved before implementation.
- **High priority**: Q-131 (archive exception after SSE headers) and Q-128 (SSE event ordering) affect observable behavior and the frontend experience.
- **Design decisions needed**: Q-127 (method naming), Q-134 (failure() semantics), Q-137 (restore step visibility).
- **Informational**: Q-129, Q-133, Q-135, Q-136, Q-138 — important to confirm but less likely to cause immediate failures.
