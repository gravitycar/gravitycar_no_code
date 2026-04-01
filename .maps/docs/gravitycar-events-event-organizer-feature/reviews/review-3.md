# Critical Review #3 - Implementation Plans

**Reviewer**: Critic Agent
**Date**: 2026-04-01
**Scope**: All 18 implementation plans reviewed against specification v1.2

---

## Summary

The 18 implementation plans are thorough, well-structured, and cover the vast majority of spec requirements. Each plan includes file paths, function signatures, data shapes, error handling, and unit test specifications. The plans follow the framework's existing patterns correctly (7-param DI constructors, metadata files, ApiControllerBase, etc.). However, I identified several cross-plan inconsistencies and gaps that need resolution before building.

**Verdict**: Plans are buildable with the issues below resolved. No fundamental design problems found.

---

## Acceptance Criteria Coverage

| AC | Description | Covered By Plan(s) | Status |
|----|-------------|---------------------|--------|
| AC-1 | Admin create event | 03, 17 | Covered |
| AC-2 | Add/remove proposed dates | 04, 17 | Covered |
| AC-3 | Invite users | 05, 17 | Covered |
| AC-4 | Chart grid display | 09, 16 | Covered |
| AC-5 | Toggle availability | 06, 10, 16 | Covered |
| AC-6 | Accept All | 10, 16 | Covered |
| AC-7 | Most popular date (with ties) | 03, 12, 16 | Covered |
| AC-8 | Set accepted date | 11, 16 | Covered |
| AC-9 | ICS export | 13, 16 | Covered |
| AC-10 | Create reminders | 07, 17 | Covered |
| AC-11 | Send reminders exactly once | 14 | Covered |
| AC-12 | Link event to model record | 18 | Covered |
| AC-13 | Navigation + smart routing | 15 | Covered |
| AC-14 | Guest read-only | 09, 12, 16 | Covered |
| AC-15 | Own-row authorization | 10 | Covered |
| AC-16 | Active events sorted first | 03 | **Partially covered** (see Q1) |
| AC-18 | Display names via displayColumns | 09, 16 | Covered |
| AC-19 | Reminder recalculation | 07, 11 | Covered |
| AC-20 | DateTime UTC + timezone | 01, 02 | Covered |
| AC-21 | Email_Queue model | 08 | Covered |

---

## Issues Found

### Issue 1: AC-16 -- Active events sorted first in lists (QUESTION)

**Spec says**: "Active events (those with future proposed dates and no accepted_date) SHALL be displayed first in any event list."

**Gap**: Plan-03 defines the `isActive()` method on the Events model, but NO plan addresses sorting active events first in list API responses. Plan-17 (Admin Pages) mentions this should be handled by "standard CRUD with custom ordering" but does not define any custom ordering. The standard CRUD list endpoint would need a custom query or sort override to put active events first. This is not trivial -- it requires a subquery or join to check for future proposed dates.

**Severity**: Medium. The list will render but without the required sort order.

### Issue 2: Chart date display not using user timezone (QUESTION)

**Spec says (AC-20)**: "All DateTime values SHALL be displayed to users in their configured timezone."

**Gap in Plan-16**: The `formatProposedDate` helper in ChartOfGoodness.tsx uses `new Date(dateStr).toLocaleDateString(...)` without specifying a timezone. This uses the browser's local timezone, NOT the user's configured `user_timezone`. Plan-16 itself notes this in its Notes section ("For consistency with the framework's timezone approach, the `user_timezone` from `useAuth` could be passed to a timezone-aware formatter -- but that is an enhancement beyond the core chart functionality"). However, the spec does not treat this as optional -- it says "SHALL". The chart page should use the user's timezone from `useAuth()` when formatting proposed dates and accepted dates.

**Severity**: Medium. Dates will display in browser timezone rather than user's configured timezone.

### Issue 3: `events_event_reminders` relationship metadata file missing from plans (QUESTION)

**Spec's Relationships table** lists `events_event_reminders` as a OneToMany relationship. Plan-03 (Events metadata) references it in the `relationships` array. Plan-07 (Event_Reminders metadata) also references it. However, NO plan creates the actual relationship metadata file at `src/Relationships/events_event_reminders/events_event_reminders_metadata.php`.

Plans 04 and 06 each create their corresponding relationship metadata files (`events_event_proposed_dates` and `events_event_commitments`), but plan-07 does not create one for `events_event_reminders`.

**Severity**: High. Missing file will cause the framework to fail when resolving the `events_event_reminders` relationship.

### Issue 4: Duplicated access control helpers across 4+ controllers

**Observation**: Plans 09, 10, 11, 12, and 13 each duplicate `isUserInvited()`, `getUserRoles()`, and `isUserAdmin()` helper methods. Plan-13 notes that "With 4 controllers sharing the pattern, extracting into a shared EventAccessControlTrait is justified during build."

**Recommendation**: This is not a blocker, but builders should extract `EventAccessControlTrait` at `src/Models/events/api/Api/EventAccessControlTrait.php` during the build phase to avoid 5 copies of the same SQL queries. This should be part of plan-09 (first controller built) and consumed by plans 10-13.

### Issue 5: SmartRouteController SQL uses wrong join table column names (QUESTION)

**In Plan-15**: The SmartRouteController's SQL query uses `eui.event_id` and `eui.user_id`:
```sql
INNER JOIN events_users_invitations eui ON eui.event_id = e.id
...
WHERE eui.user_id = :userId
```

But per Plan-05 and the framework's ManyToMany convention, the join table columns are `events_id` and `users_id` (model name + `_id`). Plans 09, 10, 12, 13, and 14 all correctly use `events_id` and `users_id`. Plan-15 is inconsistent.

**Severity**: High. SQL will fail at runtime if not corrected.

### Issue 6: Email reminder body does not include ICS attachment info in queue (QUESTION)

**Spec says**: "Reminder emails SHALL include: event name, accepted date/time, location, and an ICS attachment."

**Gap in Plan-14**: The `queueEmailsForReminder()` method stores the email body in the Email_Queue `body` field but does NOT store the ICS content anywhere in the queue record. The ICS attachment is generated later in `sendEmail()` via `getIcsForEmail()`. This means:
1. The ICS is regenerated per email during send (wasteful for N invitees on the same event).
2. If the event's accepted_date changes between queuing and sending, the ICS will reflect the new date, not the date at queue time.

This is a design choice, not necessarily a bug, but it means the email queue record alone is not self-contained. The plan should document this trade-off explicitly.

**Severity**: Low. Acceptable design but worth documenting.

### Issue 7: Plan-07 Event_Reminders metadata includes unexpected `manager` role

**In Plan-07** (Event_Reminders metadata), the `rolesAndActions` includes:
```php
'manager' => ['list', 'read'],
```

The spec does not mention a `manager` role for Event_Reminders. The spec says: admin: all actions, user: read/list, guest: none. No other roles are listed.

**Severity**: Low. Extra role that does not exist in the spec. Should be removed.

### Issue 8: Plan-15 route URL inconsistency with Plan-16

**Plan-15** registers the chart route as `/events/chart/:eventId` and the smart route redirects to `/events/chart/{event_id}`.

**Plan-16** registers the chart route as `/events/:eventId/chart`.

These are different URL patterns. They must match.

**Severity**: High. Route mismatch will cause 404s.

### Issue 9: Plan-14 queueEmailsForReminder reuses same model instance for multiple creates

**In Plan-14**: The `queueEmailsForReminder` method calls `$emailQueueModel->set(...)` and `$emailQueueModel->create()` in a loop for each invitee. However, after the first `create()`, the model instance has an ID assigned. Calling `set()` and `create()` again on the same instance may cause issues (attempting to create a record that already has an ID, or overwriting the previous record). The correct pattern would be to create a new model instance per iteration, or reset the model's state between iterations.

**Severity**: Medium. May silently overwrite the first email record or throw a duplicate ID error.

---

## Cross-Plan Consistency Checks

### Join Table Column Names
- Plans 09, 10, 12, 13, 14: Use `events_id` and `users_id` -- **Consistent**
- Plan 15: Uses `event_id` and `user_id` -- **INCONSISTENT** (Issue 5)

### Access Control Pattern
- Plans 09, 10, 12, 13: Same `isUserInvited`, `getUserRoles` pattern -- **Consistent**
- Plan 11: Same `isUserAdmin` pattern -- **Consistent**
- Plan 15: Different pattern (backend endpoint for smart routing) -- **Appropriate**

### Response Format
- All API controllers use `{ success: true, status: 200, data: {...}, timestamp: date('c') }` -- **Consistent**
- Plan 13 (ICS): Uses `{ raw_response: true, body: ..., content_type: ... }` -- **Appropriate deviation** for non-JSON response

### Chart Route URL
- Plan 15: `/events/chart/:eventId` -- **INCONSISTENT with Plan 16**
- Plan 16: `/events/:eventId/chart` -- Different pattern
- **Must be resolved** (Issue 8)

### Model Metadata `ui` Sections
- Plans 03, 04, 06, 07, 08 all define `ui` sections in their metadata -- **Consistent**
- Plan 17 proposes modifications to these same `ui` sections with slightly different values (e.g., adding `created_by_display_name` to Events listFields, adding `relatedItemsSections`) -- **This is fine**: Plan 17 is meant to augment the initial metadata

### Events Model Metadata `relationships` Array
- Plan 03 lists: `events_event_proposed_dates`, `events_users_invitations`, `events_event_commitments`, `events_event_reminders`
- Plan 04 creates `events_event_proposed_dates` relationship metadata -- **Covered**
- Plan 05 creates `events_users_invitations` relationship metadata -- **Covered**
- Plan 06 creates `events_event_commitments` relationship metadata -- **Covered**
- Plan 07 does NOT create `events_event_reminders` relationship metadata -- **MISSING** (Issue 3)

---

## Plans Assessment: Buildability

| Plan | Buildable? | Notes |
|------|-----------|-------|
| 01 - DateTime Backend | Yes | Minimal change, well-specified |
| 02 - DateTime Frontend | Yes | Clear conversion logic with code examples |
| 03 - Events Model | Yes | Complete metadata + model class |
| 04 - Event Proposed Dates | Yes | Complete metadata + relationship + model class |
| 05 - Event Invitations | Yes | Complete relationship metadata |
| 06 - Event Commitments | Yes | Complete with SchemaGenerator enhancement |
| 07 - Event Reminders | Yes, with fix | Missing relationship metadata file (Issue 3), extra role (Issue 7) |
| 08 - Email Queue | Yes | Complete model + helpers |
| 09 - Chart API | Yes | Complete controller with access control |
| 10 - Commitments API | Yes | Complete upsert + accept-all logic |
| 11 - Accepted Date API | Yes | Complete with reminder recalculation integration |
| 12 - Most Popular Date API | Yes | Thin wrapper over Events model method |
| 13 - ICS Export | Yes | Complete with IcsGeneratorService |
| 14 - Email Cron | Yes, with fix | Model instance reuse issue (Issue 9) |
| 15 - Navigation | Yes, with fixes | Wrong column names (Issue 5), route mismatch (Issue 8) |
| 16 - Chart UI | Yes, with fix | Route mismatch (Issue 8), timezone gap (Issue 2) |
| 17 - Admin Pages | Yes | Metadata-driven, no code needed |
| 18 - Model Linking UI | Yes | Complete component + chart header integration |

---

## Open Questions Summary

| # | Issue | Severity | Needs Resolution Before Build? |
|---|-------|----------|-------------------------------|
| Q1 | AC-16: No plan implements active-events-first sorting in list API | Medium | Yes |
| Q2 | Chart dates not using user timezone (AC-20 violation) | Medium | Yes |
| Q3 | Missing `events_event_reminders` relationship metadata file | High | Yes |
| Q4 | Duplicated access control helpers (recommendation, not blocker) | Low | No |
| Q5 | Plan-15 SmartRouteController uses wrong join table column names | High | Yes |
| Q6 | ICS not stored in email queue (design trade-off documentation) | Low | No |
| Q7 | Unexpected `manager` role in Event_Reminders metadata | Low | Yes (quick fix) |
| Q8 | Route URL mismatch between Plan-15 and Plan-16 | High | Yes |
| Q9 | Plan-14 reuses model instance in create loop | Medium | Yes |
