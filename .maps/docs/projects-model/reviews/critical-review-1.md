# Critical Review #1: Projects Model Specification

**Review Task ID**: 9  
**Epic ID**: 5  
**Date**: 2026-05-20  
**Reviewer**: Critic Agent  
**Spec reviewed**: `.maps/docs/projects-model/specification/spec.md`

---

## Summary

The specification is well-structured and covers the major feature areas thoroughly. It correctly identifies framework patterns, reuses existing conventions, and includes a comprehensive acceptance criteria list. However, 10 issues were found requiring resolution before implementation can proceed safely. Two of these are genuine blockers (routing conflict, DB migration mechanism). The spec's own Open Questions #4 and #5 are confirmed as blockers and were surfaced as question tasks.

---

## Issues Found

### BLOCKER — Technical Risk

**Issue 1: Routing conflict at `/projects`** (Task #10)  
The spec adds a public `/projects` route in App.tsx AND states the dynamic `/:modelName` route already handles admin CRUD for Projects. Both patterns match `/projects`. React Router will resolve only one. The admin CRUD path becomes unreachable unless explicitly disambiguated. This must be resolved before App.tsx is modified.

**Issue 4: Guest API access mechanism unconfirmed** (Task #13)  
The backend uses JWT+Google OAuth. The spec grants `guest: ['list', 'read']` on Projects and requires the public view to work unauthenticated. Whether `AuthorizationService` already handles missing-JWT requests as `guest` role is unconfirmed. If it doesn't, backend changes beyond the spec scope are needed. Must confirm against Events model (which has the same guest pattern).

**Issue 6: Database table creation mechanism unknown** (Task #15)  
The codebase summary has no information about the installer or migration system. The spec's own Open Question #4 confirms this is unresolved. No developer can implement the DB schema without knowing what file(s) to modify.

---

### AMBIGUITY — Spec Corrections Needed

**Issue 3: AC #4 uses undefined term "published"** (Task #14)  
Acceptance Criterion #4 says "all published Projects records" but the schema has no `published` field. Either a drafting error (should say "all non-deleted records") or a missing requirement (needs a `published` boolean field). The AC cannot be tested as written.

**Issue 5: `description` field typed as `Text` but 4096 chars implies Textarea** (Task #12)  
`TextField` renders `<input type="text">` (single line). A 4096-char description field is unusable as a single-line input. The spec should reference a `Textarea` or `LongText` field type if one exists, or call for creating one.

**Issue 9: Dual link affordance — image AND button both navigate to same URL** (Task #17)  
The spec requires both an image-as-link and a "Check it out" button when `Link` is set. The problem statement implies this is intentional, but the spec does not address whether there should be any visual affordance on the clickable image (hover state, cursor, overlay), and whether the redundancy is deliberate or an oversight.

---

### MISSING — Edge Cases Not Covered

**Issue 2: Empty state not specified** (Task #11)  
No description of what the Projects List View shows when zero records exist, or what the loading state looks like during `apiService.getList`. Both are visible to public/guest users.

**Issue 7: Broken image URL fallback not specified** (Task #18)  
Screenshot URLs are manually entered strings. If a URL becomes broken (404), tile backgrounds disappear and text may be unreadable. No `onError` fallback or CSS background fallback is described.

**Issue 8: Screenshot image height in detail modal unresolved** (Task #16)  
The spec's own Open Question #5 — whether to cap image height in the modal — is confirmed as a genuine implementation blocker. A tall portrait screenshot (e.g., 9:16 mobile app) could fill the entire modal, hiding description and button without specifying a scroll or crop strategy.

---

### AMBIGUITY — Implementation Details

**Issue 10: Responsive breakpoint for grid not specified** (Task #19)  
The spec says "single column below a breakpoint" without naming the breakpoint. At 400px-wide tiles, a 2-column layout needs ~850px. The Tailwind breakpoint (`sm:`, `md:`, `lg:`) and single-column tile height need to be specified.

---

## Open Questions from Spec — Evaluation

| # | Question | Status |
|---|----------|--------|
| 1 | Icon for navigation entry | Non-blocker; implementation detail. Developer can choose. |
| 2 | Screenshot URL validation sufficiency | Non-blocker; codebase summary confirms ImageField validates URLs with `allowRemote: true`. |
| 3 | Tailwind `line-clamp` support | Non-blocker; minor implementation detail, verify during dev. |
| 4 | Installer/migration mechanism | **BLOCKER** — surfaced as Task #15. |
| 5 | Screenshot image sizing in detail modal | **BLOCKER** — surfaced as Task #16. |

---

## Acceptance Criteria Assessment

- AC #4: Contains undefined term "published" — must be corrected (Task #14).
- AC #6: Testable (hover zoom effect).
- AC #11: Testable (Escape, X, backdrop).
- AC #13: Testable (focus return).
- AC #15: Testable (URL input validation).
- AC #18: Testable (server-side validation rejection).
- All other ACs: Testable as written.

**Missing ACs:**
- No AC for empty state behavior.
- No AC for broken image fallback.
- No AC for responsive/mobile layout.

---

## Question Tasks Created

| Task ID | Summary |
|---------|---------|
| #10 | Routing conflict at `/projects` |
| #11 | Empty state and loading state |
| #12 | Description field type (Textarea vs Text) |
| #13 | Guest API access mechanism |
| #14 | AC #4 "published" field discrepancy |
| #15 | DB migration mechanism (Open Q #4) |
| #16 | Screenshot max-height in modal (Open Q #5) |
| #17 | Dual link affordance (image + button) |
| #18 | Broken image fallback |
| #19 | Responsive breakpoint |

**Total: 10 question tasks created**
