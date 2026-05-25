# Critical Review #1: Specification
**Epic**: Public Home Page / Projects List View (ID: 48)
**Task**: Critical Review #1 (ID: 52)
**Date**: 2026-05-25
**Reviewer**: Critic Agent

---

## Overall Assessment

The specification is well-structured and covers the core scenario clearly. The problem statement, goal list, technical context, and acceptance criteria are strong. The spec is appropriately scoped and makes good use of existing patterns.

However, **7 open questions** were identified that need resolution before implementation can proceed cleanly. The most significant issues are:

1. The scope of ProtectedRoute removal is broader than the spec explicitly acknowledges — all previously-protected routes become public, not just the routes mentioned.
2. Two of the spec's own Open Questions (#1 and #3) are unresolved and need answers before implementation.
3. The 403 redirect pattern has two UX edge cases (redirect loop, back-trap) that the spec does not address.

---

## Completeness Checklist

| Criterion | Status | Notes |
|-----------|--------|-------|
| Clear problem statement | PASS | §1 clearly states the current redirect-to-dashboard problem |
| User story / stakeholder context | PARTIAL | Goal list covers intent; no formal user story format |
| Measurable acceptance criteria | PASS | AC-1 through AC-10 are concrete and testable |
| Functional requirements by capability | PASS | §4.1–4.9 organized by area |
| Non-functional requirements | PARTIAL | §5 is present but thin (3 bullet points) |
| Explicit constraints (DO NOTs) | PASS | §9 has 10 explicit DO NOTs |
| Technical context | PASS | §6 references existing patterns directly |
| Out of scope section | PASS | §3 explicit |
| Dependencies identified | PARTIAL | Backend anon access dependency noted in §7.5 but not in a formal dependencies section |

---

## Clarity Checklist

| Criterion | Status | Notes |
|-----------|--------|-------|
| Another developer could implement from this | MOSTLY | 7 unresolved questions create implementation ambiguity |
| Active, specific language (SHALL/MUST) | PASS | Consistent use of SHALL throughout |
| No unquantified ambiguous terms | PASS | No vague "fast" or "reliable" terms |
| Edge cases covered | PARTIAL | §7 is thorough; 403 loop and back-trap missing |
| Given/When/Then for workflows | PARTIAL | ACs cover outcomes but not full workflow steps |

---

## Specification Guidelines Compliance

| Criterion | Status | Notes |
|-----------|--------|-------|
| Specifies WHAT not HOW | MOSTLY | §6 does specify `window.location.href` (a HOW) — see Q#55 |
| Includes "why" for non-obvious requirements | PASS | §5 explains the ProtectedRoute removal rationale |
| References existing code patterns | PASS | §6 references all relevant existing patterns |
| Under 10K tokens | PASS | Well within limit |

---

## Open Questions Created

### Q1 — Task #53: Which routes should retain auth guards after ProtectedRoute removal?
**Severity**: HIGH
**Section**: §4.3 / AC-3

The spec says to remove ALL ProtectedRoute wrappers. This affects 7 currently-guarded routes beyond the `/dashboard` removal, including developer/admin-only pages (`/metadata-test`, `/dnd-chat`, etc.) and the `/:modelName` catch-all. The spec does not explicitly enumerate what happens to each of these routes for unauthenticated guests. This is an implicit scope expansion that could cause developer surprise.

---

### Q2 — Task #54: What does the user see when navigating to /dashboard after removal?
**Severity**: MEDIUM
**Section**: AC-2

AC-2 accepts either "a 404" or "catch-all route rendering" as valid outcomes for `/dashboard`. These are two distinct UX experiences. The `:modelName` catch-all would attempt to load a "dashboard" model from the API — which would fail. What error state does `DynamicModelRoute` show for a non-existent model? Does a dedicated `*` 404 route exist? The spec should commit to one outcome.

---

### Q3 — Task #55: Should /unauthorized redirect use window.location.href or React Router navigation?
**Severity**: LOW-MEDIUM
**Section**: §4.5 / §6 Technical Context

The spec prescribes `window.location.href` for the 403 redirect (consistent with the existing 401 handler). Unlike 401, a 403 does not require clearing auth state — a full page reload is heavier than necessary and re-triggers the `/auth/me` check. The spec should explicitly justify this choice or consider React Router programmatic navigation via a stored navigator ref pattern.

---

### Q4 — Task #56: Can /unauthorized cause a redirect loop?
**Severity**: HIGH
**Section**: §4.5 / §4.6 / §7.4

If the `/unauthorized` page is wrapped in `<Layout>`, and `Layout` renders `NavigationSidebar` for authenticated users, and `NavigationSidebar` calls `GET /navigation`, and that endpoint returns 403 — the interceptor fires again, redirecting to `/unauthorized`, creating an infinite reload loop. The spec should either add a loop-prevention guard in the interceptor (`if current URL is /unauthorized, do not redirect`) or justify why the `/navigation` endpoint cannot return 403.

---

### Q5 — Task #57: What is the UX for the Back button from /unauthorized?
**Severity**: LOW-MEDIUM
**Section**: §4.5 / §7.4

Using `window.location.href` for the 403 redirect pushes a history entry. If the user presses Back, they return to the page that caused the 403, which immediately fires API calls again and re-redirects to `/unauthorized`. This is a "back-trap." The spec should address whether `window.location.replace()` (replaces history entry) should be used instead of `window.location.href` (pushes history entry).

---

### Q6 — Task #58: Should the /unauthorized page content question be resolved before implementation?
**Severity**: MEDIUM
**Section**: §10 Open Question #1

The spec's own Open Question #1 ("generic message vs. context-specific message for `/unauthorized`") is unresolved. AC-6 implies generic-only, but Open Question #1 leaves it open. This creates a contradiction: the acceptance criterion and the open question give different signals. The developer needs a definitive answer. The likely correct resolution is: generic message only for this epic, context-specific as a future enhancement.

---

### Q7 — Task #59: Should XDEBUG_TRIGGER be scoped to development mode only?
**Severity**: LOW
**Section**: §4.5 / adjacent to api.ts changes

The `api.ts` request interceptor appends `XDEBUG_TRIGGER=mike` to all requests. This dev-only debugging parameter will now appear in requests from public/unauthenticated users. Since `api.ts` is already being modified as part of this epic, the spec should address whether this parameter should be conditioned on `import.meta.env.DEV` or removed. This is a minor cleanup adjacent to the required work.

---

## Summary

- **Questions created**: 7
- **High severity**: 2 (route guard scope, 403 redirect loop)
- **Medium severity**: 3 (dashboard 404 UX, unresolved open question #1, back-trap)
- **Low severity**: 2 (navigation method choice, XDEBUG_TRIGGER cleanup)
- **Spec quality**: Good overall. Core requirements are clear and well-grounded in existing patterns. The main gaps are around edge cases in the 403 handling and the unresolved scope of ProtectedRoute removal across all routes.

**Recommended action**: Resolve the 7 open questions, then proceed to spec revision before implementation planning.
