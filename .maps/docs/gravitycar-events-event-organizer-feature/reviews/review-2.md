# Critical Review #2 -- Revised Specification Review

**Reviewer:** Critic Agent  
**Date:** 2026-04-01  
**Spec reviewed:** `.maps/docs/gravitycar-events-event-organizer-feature/specification/spec.md` (Rev 1.1)

---

## Overall Assessment

The revised specification (v1.1) has successfully incorporated all 8 resolutions from Critical Review #1. The additions are well-integrated into the existing structure, the new acceptance criteria (AC-18 through AC-21) are measurable and specific, and the new DO NOT constraints are appropriate. The spec is substantially ready for implementation planning.

Three new questions were identified -- two are Low severity (can be resolved quickly) and one is Medium severity. None are blockers for beginning implementation planning, but they should be resolved before implementation begins.

---

## Verification of Q1-Q8 Resolutions

### Q1: Most Popular Date tie-breaking (Task 6) -- RESOLVED

- **Resolution:** Show all tied dates.
- **Spec updates:** `most_popular_dates` computed property (line 37) explicitly states "ALL tied dates SHALL be returned." The API endpoint (line 215), UI banner (line 259), and AC-7 (line 363) all consistently reflect this.
- **Verdict:** Fully addressed. No ambiguity remains.

### Q2: Event end time / DTEND (Task 7) -- RESOLVED

- **Resolution:** Add `duration_hours` field to Events model.
- **Spec updates:** `duration_hours` field added (line 29) with default of 3 and clear description linking to ICS DTEND calculation. ICS Export section (line 293) and AC-9 (line 365) reference the field.
- **Verdict:** Fully addressed.

### Q3: Guest access contradiction (Task 8) -- RESOLVED

- **Resolution:** Chart endpoint bypasses commitment model role checks.
- **Spec updates:** Chart endpoint description (line 190) explicitly states it "does NOT go through the Event_Commitments model's rolesAndActions." Event_Commitments roles section (line 94) adds a clarifying note. Authorization rule 4 (line 227) and AC-14 (line 370) are consistent.
- **Verdict:** Fully addressed. The contradiction is eliminated with clear, repeated explanation.

### Q4: Email_Queue as framework model (Task 9) -- RESOLVED

- **Resolution:** Full Gravitycar metadata-driven model.
- **Spec updates:** Full model definition added (lines 142-167) with 10 fields, roles (admin-only), and explicit statement about metadata/model class/database table (line 166). AC-21 (line 377) covers this.
- **Verdict:** Fully addressed.

### Q5: Navigation items (Task 10) -- RESOLVED

- **Resolution:** Simplify to Events/Create Event/List Events.
- **Spec updates:** Navigation section (lines 238-242) lists only "Create Event" and "List Events" as sub-items. Line 242 explicitly states proposing dates and inviting people are actions within the event management UI.
- **Verdict:** Fully addressed.

### Q6: User display name (Task 11) -- RESOLVED

- **Resolution:** Use displayColumns metadata property.
- **Spec updates:** Chart UI section (line 263) references displayColumns. AC-18 (line 374) and DO NOT constraint (line 350) reinforce this.
- **Verdict:** Fully addressed.

### Q7: Reminder lifecycle (Task 12) -- RESOLVED

- **Resolution:** Auto-calculate, recalculate on change, null when no accepted_date, skip custom.
- **Spec updates:** Full "Reminder Lifecycle" subsection (lines 128-135) covers all scenarios: auto-calculation for presets, null remind_at when no accepted_date, recalculation on accepted_date change, custom exemption, and sent-reminder protection. AC-10, AC-11, and AC-19 cover the key behaviors.
- **Verdict:** Fully addressed. See new question Q9 (Task 17) for a minor clarity issue in the API endpoint description.

### Q8: Timezone handling (Task 13) -- RESOLVED

- **Resolution:** Store UTC, display in user timezone, framework-level fix.
- **Spec updates:** New "Framework Enhancement: DateTime Timezone Support" section (lines 319-337) with specific file references for AuthenticationService and DateTimePicker. AC-20 (line 376) and DO NOT constraint (line 351) cover this.
- **Verdict:** Fully addressed.

---

## New Issues Found (3)

### Q9 (Task 17): Reminder recalculation -- endpoint vs. lifecycle description mismatch -- LOW

The PUT /api/events/{event_id}/accepted-date endpoint (line 203) says it "SHALL trigger recalculation of remind_at on all preset reminders" but does not mention the exception for already-sent reminders. The Reminder Lifecycle section (line 135) does cover this: "Reminders that have already been sent (status = 'sent') SHALL NOT be recalculated." An implementer reading only the API section could miss this. The endpoint description should add a cross-reference or restate the exception.

### Q10 (Task 18): Email_Queue retry strategy undefined -- MEDIUM

The spec says failed sends "remain in the queue with status 'failed' and an error_message for retry or admin review" (line 315). It does not specify whether retries are automatic (cron job retries on next run) or manual (admin must intervene). If automatic, a retry_count or max_retries field may be needed on Email_Queue to prevent infinite retry loops. This affects both the model definition and cron job behavior.

### Q11 (Task 19): Smart routing "active event invitation" definition -- LOW

The Navigation section uses the phrase "active event invitation" (line 245) for smart routing. The Events model defines is_active as "future proposed dates AND no accepted_date" (line 36). It is unclear whether smart routing should use this same definition or a broader "upcoming events" definition that includes confirmed events with a future accepted_date. This affects the user experience for events that have progressed past the voting stage.

---

## Completeness Check -- Is the Spec Ready for Implementation Planning?

| Criterion | Status |
|-----------|--------|
| All Q1-Q8 resolutions incorporated | Yes |
| Models fully defined with fields, types, defaults | Yes |
| Relationships defined | Yes |
| API endpoints specified with access control | Yes |
| UI components described | Yes |
| Authorization rules clear | Yes |
| Acceptance criteria measurable | Yes (21 ACs) |
| Constraints explicit | Yes (10 DO NOTs) |
| Technical context sufficient for planning | Yes |
| New questions blocking? | No -- all 3 are Low/Medium severity |

**Conclusion:** The specification is ready for implementation planning. The 3 new questions (Tasks 17-19) should be resolved but are not blockers for beginning the planning phase. They can be resolved in parallel with early planning work.

---

## Positive Observations on Revisions

1. The Reminder Lifecycle subsection is thorough and covers the full state machine (pending -> sent, null remind_at handling, recalculation rules, custom exemption).
2. The Email_Queue model is well-specified with appropriate fields and admin-only access.
3. The DateTime Timezone Support section is precise about which files need changes and why, with good context about what already exists.
4. The revision history table is a good practice for spec traceability.
5. AC-18 through AC-21 are specific and testable additions.
