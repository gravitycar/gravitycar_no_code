# Critical Review #1 -- Specification Review

**Reviewer:** Critic Agent  
**Date:** 2026-04-01  
**Spec reviewed:** `.maps/docs/gravitycar-events-event-organizer-feature/specification/spec.md`

---

## Overall Assessment

The specification is well-structured and comprehensive for a feature of this scope. It follows the framework's metadata-driven model approach, defines clear models with fields and relationships, includes custom API endpoints, and provides detailed UI component descriptions. The acceptance criteria are measurable and cover the major user workflows.

However, 8 open questions were identified that need resolution before implementation can begin. Most relate to ambiguities in edge-case behavior, a contradiction in authorization rules, and under-specified infrastructure components.

---

## Checklist Results

### Completeness

- [x] Clear problem statement (what are we building and why)
- [x] User story / stakeholder context
- [x] Measurable acceptance criteria (17 ACs defined)
- [x] Functional requirements organized by capability
- [x] Non-functional requirements -- partially (no performance targets specified, but likely acceptable for this feature)
- [x] Explicit constraints (DO NOTs) -- 8 constraints defined
- [x] Technical context (existing systems, integration points)
- [x] Out of scope -- covered implicitly by DO NOT constraints
- [x] Dependencies identified (Composer packages, Users model, navigation config, App.tsx)

### Clarity

- [x] Active, specific language (SHALL, MUST used consistently)
- [x] No ambiguous unquantified terms
- [ ] Edge cases and error scenarios -- PARTIAL. Several edge cases not addressed (see questions)
- [ ] Given/When/Then format for user workflows -- NOT USED, but the acceptance criteria are clear enough

### Specification Guidelines Compliance

- [x] Specifies WHAT, not HOW (no code examples in the spec itself)
- [x] References existing code patterns (Technical Context section)
- [x] Reasonable length (under 10K tokens)
- [ ] Includes the "why" for non-obvious requirements -- PARTIAL. The linked_model feature and the email queue lack rationale.

---

## Contradiction Found

**Guest access vs. commitment model permissions (Task #8):**  
Authorization rule 4 and the chart endpoint both grant guests read access to chart data. However, Event_Commitments rolesAndActions set guest permissions to "none". Since the chart is built from commitment data, this is a direct contradiction that will cause implementation confusion.

---

## Open Questions Raised (8 total)

| # | Task ID | Question | Severity |
|---|---------|----------|----------|
| 1 | 6 | Most Popular Date tie-breaking rule when counts are equal | Medium |
| 2 | 7 | Event end time -- no field defined but ICS export needs DTEND | High |
| 3 | 8 | Guest access to Chart of Goodness contradicts Event_Commitments "guest: none" | High |
| 4 | 9 | Email queue -- framework model with metadata or raw database table? | High |
| 5 | 10 | Nav items "Propose Dates" and "Invite People" have no clear target pages | Medium |
| 6 | 11 | User display name format in Chart of Goodness rows | Low |
| 7 | 12 | Preset reminder types -- auto-calculate remind_at from accepted_date? Lifecycle unclear | High |
| 8 | 13 | Timezone handling for proposed dates storage and display | High |

### Severity Legend
- **High:** Blocks implementation or could lead to incorrect behavior if guessed wrong
- **Medium:** Implementation can proceed with a reasonable default but should be confirmed
- **Low:** Minor detail that can be decided during implementation

---

## Positive Observations

1. The model definitions are thorough and follow the existing metadata pattern well.
2. The unique constraint on Event_Commitments (event_id, user_id, proposed_date_id) is explicitly called out.
3. The "DO NOT use delete-all-then-reinsert" constraint prevents a common data integrity issue.
4. The custom API endpoints are well-defined with clear access control annotations.
5. The responsive design consideration for the Chart of Goodness (horizontal scroll or card layout) is a good inclusion.
6. The row-level authorization rules (invitation-gated, own-row editing) are clearly specified.

---

## Recommendations

1. Resolve all 8 open questions before proceeding to implementation planning.
2. Consider adding an explicit "Out of Scope" section (currently implied by DO NOT constraints but not formalized).
3. Consider adding error response specifications for the custom API endpoints (e.g., what HTTP status codes for unauthorized access, missing events, etc.).
4. The email infrastructure (queue table, cron job, email templates) deserves its own sub-specification given its complexity relative to the core events feature.
