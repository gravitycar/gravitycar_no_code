# Critical Review #2: Projects Model Specification (Revised)

**Review Task ID**: 21  
**Epic ID**: 5  
**Date**: 2026-05-21  
**Reviewer**: Critic Agent  
**Spec reviewed**: `.maps/docs/projects-model/specification/spec.md` (revised, post Critic Review #1)

---

## Summary

All 10 issues raised in Critical Review #1 were correctly resolved in the revised specification. The spec is now substantially more complete and unambiguous. One new issue was found during the fresh review: a tile click vs. image click event propagation conflict that a developer would need to resolve, but the spec does not address. This is a minor blocker that requires a one-sentence clarification.

---

## Part 1 — Resolution Verification

### Issue 1 (Task #10): Routing conflict at `/projects`
**Resolution**: Public route changed to `/projects_showcase` throughout (Section 9, AC #1, #3, #16, nav config). Admin CRUD at `/Projects` via existing dynamic route. Explicitly disambiguated.
**Status**: RESOLVED CORRECTLY

### Issue 4 (Task #13): Guest API access mechanism unconfirmed
**Resolution**: RBAC Rules section now has an explicit paragraph confirming `CurrentUserProvider` falls back to guest via `GuestUserManager` when no JWT is present. Cites Movies/Events pattern. No additional backend work needed.
**Status**: RESOLVED CORRECTLY

### Issue 6 (Task #15): Database table creation mechanism unknown
**Resolution**: Section 1 now states the `projects` table SHALL be created automatically at runtime by `SchemaGenerator` using Doctrine DBAL. No manual SQL or installer steps required.
**Status**: RESOLVED CORRECTLY

### Issue 3 (Task #14): AC #4 used undefined term "published"
**Resolution**: AC #4 now reads "all Projects records (non-deleted) in a grid layout." No reference to "published."
**Status**: RESOLVED CORRECTLY

### Issue 5 (Task #12): Description field typed as `Text` — should be BigText/Textarea
**Resolution**: Fields table now shows `description` as type `BigText` with note "Renders `TextArea` React component." DB schema column is `TEXT (up to 16000 chars)`. Section 4 consistently uses BigText.
**Status**: RESOLVED CORRECTLY

### Issue 2 (Task #11): Empty state and loading state not specified
**Resolution**: Section 11 (ProjectsListView) now explicitly specifies: centered spinner/"Loading..." text while fetching; centered "No projects yet" message when list is empty. AC #23 and AC #24 test both states.
**Status**: RESOLVED CORRECTLY

### Issue 7 (Task #18): Broken image URL fallback not specified
**Resolution**: Section 11 specifies `onError` handler swapping `<img>` to a grey placeholder `<div>` displaying project title initials for tile. Section 12 specifies the same for the modal. AC #25 tests both.
**Status**: RESOLVED CORRECTLY

### Issue 9 (Task #17): Dual link affordance — image AND button intentionality unclear
**Resolution**: Section 11 has a dedicated "Link affordance on tile image (intentional)" subsection with explicit dual-link description and `cursor-pointer` on hover. Section 12 item 6 states "This intentionally duplicates the clickable screenshot link." AC #26 tests tile dual-link behavior.
**Status**: RESOLVED CORRECTLY

### Issue 8 (Task #16): Screenshot image height in detail modal unresolved
**Resolution**: Section 12 item 4 now specifies `max-h-[50vh]` with `object-contain` for the modal image.
**Status**: RESOLVED CORRECTLY

### Issue 10 (Task #19): Responsive breakpoint for grid not specified
**Resolution**: Section 11 now specifies `grid-cols-1 md:grid-cols-2` (768px breakpoint), 300px tall tiles fixed height on all screen sizes. AC #27 tests single-column below 768px.
**Status**: RESOLVED CORRECTLY

**All 10 resolutions verified as correctly applied.**

---

## Part 2 — Fresh Review

### Checks Passing

- **LinkField validation**: `javascript:` and `data:` URI schemes explicitly blocked in Constraints, Section 4 (backend), Section 5 (frontend input type), AC #15 and AC #18. Complete.
- **"Check it out" button visibility**: Section 12 item 6 says "shown ONLY if the `Link` field is non-empty." AC #8, #10 confirm. Complete.
- **Navigation config completeness**: Section 8 specifies key, title, url, icon (deferred, documented as non-blocker in Implementation Notes), roles. All four elements present.
- **All ACs testable**: Reviewed all 27 ACs — all are specific and testable. AC #20 (coding standards compliance) is a code-review criterion rather than a runtime test, which is appropriate.
- **Cross-section consistency**: Route `/projects_showcase` used consistently in Section 8, Section 9, App.tsx, RBAC section, and all ACs referencing it. Field types, max lengths, and RBAC permissions are consistent across metadata spec, DB schema, and RBAC table.

---

### New Issue Found

**Issue A: Tile click vs. image click — event propagation conflict**

Section 11 states two behaviors on the same tile:
1. "Clicking any tile SHALL open the `ProjectDetailModal` for that project." (tile click)
2. "When the `Link` field is set, the Screenshot image in the tile SHALL also navigate to the Link URL in a new tab... when clicked." (image click within the tile)

The image is a child element inside the tile (which is a clickable `role="button"`/`<button>`). When a user clicks the image, the click event will bubble up from the image to the tile container, triggering both behaviors simultaneously: the link navigation AND the modal opening. The spec does not specify whether the image click should call `event.stopPropagation()` to prevent the modal from opening, or whether both behaviors are intended to happen together (which would be a poor UX — a new tab opens AND a modal opens on the same click).

A developer implementing this without guidance could ship either behavior and both would be a reasonable reading of the spec as written. This needs one sentence of clarification.

---

## Question Tasks Created

| Task ID | Summary |
|---------|---------|
| TBD | Tile image click — stop propagation or allow both modal + link to trigger? |

**Total: 1 question task created**

---

## Verdict

The revised specification is sound. All 10 prior issues are cleanly resolved. One new issue requires a one-sentence clarification before implementation to prevent a potentially confusing UX interaction. This is a minor blocker on the tile interaction spec only — all other sections are implementation-ready.
