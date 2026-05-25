# Critical Review #2: Specification (Post-Question Resolution)
**Epic**: Public Home Page / Projects List View (ID: 48)
**Task**: Critical Review #2 (ID: 61)
**Date**: 2026-05-25
**Reviewer**: Critic Agent

---

## Overall Assessment

The spec was NOT updated to reflect the resolved decisions from Critical Review #1. Three of the seven resolutions directly contradict current spec text, and two more resolutions are entirely absent from the spec. The spec must be revised before implementation can proceed.

**Verdict: NEEDS REVISION**

No new user decisions are required — all gaps are editorial spec updates based on already-resolved questions.

---

## Decision-by-Decision Gap Check

### Decision 1 (Q53 — Route scope)
**Decision**: Remove /metadata-test, /test-related-record, and /movies-quotes-demo routes entirely. All other routes become public.

**Gap found**: The spec (§3 In Scope, §4.3, AC-3) covers removing ProtectedRoute wrappers generically, but nowhere states that three specific routes — `/metadata-test`, `/test-related-record`, and `/movies-quotes-demo` — are to be deleted entirely from the route table. This is meaningfully different from "remove the ProtectedRoute wrapper." These routes disappear; they are not just made public.

**Required spec update**: Add to §4.2 (or a new §4.2a) that the routes `/metadata-test`, `/test-related-record`, and `/movies-quotes-demo` SHALL be removed entirely from the route table in `App.tsx`. Add corresponding acceptance criterion (or amend AC-2 or AC-3 to cover these deletions).

---

### Decision 2 (Q56 — 403 redirect loop guard)
**Decision**: Add guard in 403 interceptor — do NOT redirect if current URL is already /unauthorized.

**Gap found**: The spec §4.5 and §7.4 say nothing about a loop-prevention guard. The interceptor behavior as written would cause an infinite reload if any request made from `/unauthorized` (e.g., Layout's nav bar fetch) returns 403.

**Required spec update**: Add a bullet to §4.5: "The interceptor SHALL NOT redirect to `/unauthorized` if the current URL is already `/unauthorized`." Also add or update §7.4 to describe this guard explicitly.

---

### Decision 3 (Q55 — Navigation method for 403 redirect)
**Decision**: Use React Router `navigate('/unauthorized', { replace: true })` — NOT `window.location.href`. Implement via a `NavigatorSetter` component in `App.tsx` that stores `navigate()` in a module-level singleton (`imperativeNavigate`). Keep `window.location.href` for 401.

**Gap found**: This is the most significant contradiction. The spec currently says — in two places — the opposite of what was decided:

- §4.5: "On a 403 response, the interceptor SHALL redirect the browser to `/unauthorized` using `window.location.href`."
- §6 Technical Context: "SHALL add `window.location.href = '/unauthorized'` before the `Promise.reject(error)` in that branch."

Both occurrences prescribe `window.location.href`. The user decided to use React Router `navigate()` with `replace: true` instead.

Additionally, the `NavigatorSetter` component and `imperativeNavigate` singleton pattern — which are the implementation mechanism for this decision — appear nowhere in the spec's scope (§3) or technical context (§6).

**Required spec updates**:
- Replace both occurrences of `window.location.href` for 403 with the React Router approach in §4.5 and §6.
- Add to §3 In Scope: `NavigatorSetter` component (new, in `App.tsx`) and the `imperativeNavigate` module-level singleton in `api.ts`.
- Add to §6 a technical context entry explaining the `NavigatorSetter`/`imperativeNavigate` pattern and why it is needed (axios interceptors run outside React component scope and cannot call `useNavigate()` directly).
- The `replace: true` option must be mentioned explicitly (it is the resolution to the back-trap / Q57).

---

### Decision 4 (Q54 — /dashboard catch-all behavior)
**Decision**: Acceptable to let /:modelName catch-all handle /dashboard. No dedicated 404 page needed.

**Status**: REFLECTED. AC-2 already accepts either "a 404 or the catch-all route rendering" as valid outcomes. No spec update required for this item.

---

### Decision 5 (Q57 — Back-trap)
**Decision**: Resolved by `replace: true` in the `navigate()` call (same as Decision 3).

**Status**: NOT REFLECTED — because Decision 3 is not reflected. The `replace: true` flag in `navigate()` is what prevents the back-trap. Once Decision 3 is incorporated into the spec, this is automatically resolved. No separate spec bullet is needed beyond capturing `replace: true` in §4.5.

---

### Decision 6 (Q58 — /unauthorized page content)
**Decision**: Generic message only — "you don't have permission" with a link back to /. No path-specific context.

**Gap found**: The requirement in §4.6 and AC-6 is consistent with this decision. However, §10 Open Questions still lists this as item #1 — unresolved — which contradicts the decision and creates confusion for the developer.

**Required spec update**: Remove or strike through Open Question #1 in §10 and note it is resolved as "generic message only for this epic." (Or remove §10 Open Questions #1 entirely and keep the answer reflected in §4.6 / AC-6.)

---

### Decision 7 (Q59 — XDEBUG_TRIGGER)
**Decision**: Wrap in `if (import.meta.env.DEV)` in `api.ts` request interceptor. In scope for this epic.

**Gap found**: This requirement is completely absent from the spec. There is no mention of `XDEBUG_TRIGGER`, the request interceptor, or the DEV guard anywhere. Since this is explicitly declared in scope, it needs a home in the spec.

**Required spec update**: Add a requirement in §4 (a new §4.10 or added to §4.4/§4.5 as it concerns `api.ts`) stating that the `XDEBUG_TRIGGER` parameter in the `api.ts` request interceptor SHALL be wrapped in `if (import.meta.env.DEV)` so that it is only appended in development builds. Add a corresponding item to §3 In Scope (already listed as `api.ts`). Add an acceptance criterion (e.g., AC-11) verifying that `XDEBUG_TRIGGER` is not appended in non-DEV builds.

---

## Summary of Gaps

| # | Question | Gap Type | Severity | Status in Spec |
|---|----------|----------|----------|----------------|
| Q53 | Route deletions | Missing requirement | HIGH | Absent |
| Q56 | 403 loop guard | Missing requirement | HIGH | Absent |
| Q55 | Navigation method | Contradiction | HIGH | Directly contradicts spec text (2 locations) |
| Q54 | /dashboard catch-all | None | — | Reflected |
| Q57 | Back-trap | Dependent on Q55 | HIGH | Will be resolved when Q55 is incorporated |
| Q58 | /unauthorized content | Stale open question | MEDIUM | AC-6 is correct, but §10 still shows it open |
| Q59 | XDEBUG_TRIGGER | Missing requirement | LOW | Absent |

---

## New Question Tasks

None. All gaps above are editorial updates based on already-resolved user decisions. No new user decisions are required.

---

## Required Spec Changes for Reviser

The Reviser should make the following targeted changes:

1. **§4.2 or §4.3**: Add explicit requirement to delete `/metadata-test`, `/test-related-record`, and `/movies-quotes-demo` routes entirely from `App.tsx`.

2. **§4.5**: 
   - Replace `window.location.href` with React Router `navigate('/unauthorized', { replace: true })` via the `imperativeNavigate` singleton.
   - Add loop guard: interceptor SHALL NOT redirect if current URL is already `/unauthorized`.

3. **§6 Technical Context**: 
   - Replace both occurrences of `window.location.href` for 403 with the React Router approach.
   - Add new subsection explaining the `NavigatorSetter` component pattern: a component rendered in `App.tsx` that calls `useNavigate()` and stores the result in a module-level `imperativeNavigate` variable exported from `api.ts`, allowing the interceptor to call it outside React's component scope.

4. **§3 In Scope**: Add `NavigatorSetter` component (new file or addition to `App.tsx`) and note the `imperativeNavigate` singleton lives in `api.ts`.

5. **§10 Open Questions**: Remove or close items #1 (generic message — resolved) and #3 (catch-all behavior — resolved). Item #2 (return-to-original-URL) may remain as a deferred item.

6. **New §4.10 (or §4.4 extension)**: Add XDEBUG_TRIGGER guard requirement (wrap in `if (import.meta.env.DEV)` in `api.ts` request interceptor).

7. **Acceptance Criteria**: 
   - Add AC for route deletions (metadata-test, test-related-record, movies-quotes-demo gone).
   - Add AC for loop guard (navigating to a 403-returning resource while on `/unauthorized` does NOT trigger another redirect).
   - Add AC for XDEBUG_TRIGGER not present in production builds.

---

## Verdict

**NEEDS REVISION**

The spec requires targeted editorial updates only — no new user decisions. Once the Reviser incorporates the 7 changes above, the spec should be ready for sign-off.
