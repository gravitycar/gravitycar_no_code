# Critical Review #1: Admin Panel and Improved Application Update Script

**Task ID**: 88
**Epic ID**: 84
**Reviewer**: Critic agent
**Date**: 2026-05-31

## Summary

The specification is substantially complete and well-structured. It covers all four primary components from `admin_panel.md` (AdminService, Admin API Controller, React UI, CLI script), includes a detailed API contract with request/response schemas and HTTP status codes, a migration plan with phase separation, security considerations, and a UI wireframe. The acceptance criteria are generally specific and testable.

However, **12 open questions** were found that require resolution before implementation can safely begin. Three of them (ProtectedRoute, bootstrap-without-routing, archive restore semantics) are significant enough that they could derail implementation if not decided first.

---

## Completeness Check

| Area | Status | Notes |
|------|--------|-------|
| All 4 primary components covered | PASS | AdminService, AdminAPIController, React UI, CLI script all specified |
| Acceptance criteria specific and testable | MOSTLY PASS | AC-14 (file size split) is conditional, not prescriptive |
| API request/response schema | PASS | Fully specified with examples |
| HTTP status codes | PASS | 200/400/401/403 defined |
| Security | MOSTLY PASS | Archive path security left unresolved |
| Bootstrap problem addressed | PARTIAL | Acknowledged as Open Question #1 but not resolved |
| Migration plan | PASS | Two-phase approach with rollback defined |
| Cache file paths/globs | PASS | CacheComponent constants map to exact paths |
| Error recovery (archive restore) | PARTIAL | Restore behavior on partial failure is ambiguous |
| UI wireframe | MOSTLY PASS | Inline vs modal result display is contradictory |
| File size limits | PARTIAL | Conditional split language is not actionable |

---

## Open Questions Created

| Task ID | Title | Severity |
|---------|-------|----------|
| 89 | ProtectedRoute with requiredRole prop does not exist | HIGH — blocks frontend implementation |
| 90 | Bootstrap-without-routing: no public API exists on Gravitycar class | HIGH — blocks CLI script implementation |
| 91 | Archive restore behavior underspecified for partial component runs | HIGH — ambiguous core behavior |
| 92 | Archive storage path security gap — app root may be web-accessible | MEDIUM — security concern left unresolved |
| 93 | RBAC for AdminAPIController — $rolesAndActions vs DB permissions table | MEDIUM — deployment step missing |
| 94 | Dry-run behavior for archiveCache step is ambiguous | MEDIUM — edge case in core feature |
| 95 | build-backend.sh has only one call to setup.php, but spec says three lines | MEDIUM — AC-43 may be inaccurate |
| 96 | AdminAPIController constructor pattern conflicts with spec and base class | MEDIUM — testability concern |
| 97 | CacheRebuildOptions defaults conflict between property table and AC-17 | LOW — clarification needed for fromArray() behavior |
| 98 | No acceptance criteria for AdminService file-size split threshold | LOW — "if it exceeds" language is not actionable |
| 99 | No spec for what happens when components array is empty | LOW — unspecified edge case |
| 100 | UI result panel — inline vs modal, and when archive path is shown | LOW — contradictory wireframe vs component description |

---

## Key Findings

### Critical: ProtectedRoute does not exist (Q-89)
The spec states `<ProtectedRoute>` "already exists" but a codebase search finds no such component. `App.tsx` only has `PublicRoute` (redirects authenticated users away from public pages). Creating a role-gating route wrapper is a prerequisite for the admin UI.

### Critical: No public bootstrap-without-routing API (Q-90)
`Gravitycar.php` has a private `$bootstrapSteps` array and a single `bootstrap()` method. There is no public way to run the DI/config/database steps without also running routing. The CLI script needs this resolved. `setup.php` used `ReflectionClass` as a hack; the spec says to fix this but doesn't specify how.

### Critical: Archive restore semantics on partial failure (Q-91)
AC-4 (restore on failure) and AC-6 (process components independently) conflict when some components succeed and others fail. The spec does not define whether restore is all-or-nothing or component-scoped, and whether the archive captures all cache files or only selected component files.

### Security: Archive path (Q-92)
The spec raises but does not resolve whether archives stored in the app root are web-accessible. This is a real information disclosure risk in some server configurations and the spec should make a definitive decision.

### Accuracy: build-backend.sh line count (Q-95)
The codebase summary describes a single `php setup.php` call in `build-backend.sh`, but AC-43 lists three lines (319, 320, 326). The spec may have inherited inaccurate line numbers from the initial `admin_panel.md`. The actual file should be read before writing AC-43.
