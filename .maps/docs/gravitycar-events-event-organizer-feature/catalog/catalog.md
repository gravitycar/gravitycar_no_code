# Implementation Catalog: Gravitycar Events (Chart of Goodness)

## Dependency Graph Overview

```
Item 1 (DateTime Timezone - Backend)
Item 2 (DateTime Timezone - Frontend)  -- blocked by 1
Item 3 (Events Model)
Item 4 (Event_Proposed_Dates Model)    -- blocked by 3
Item 5 (Event_Invitations Relationship) -- blocked by 3
Item 6 (Event_Commitments Model)       -- blocked by 3, 4, 5
Item 7 (Event_Reminders Model)         -- blocked by 3
Item 8 (Email_Queue Model)             -- blocked by none
Item 9 (Chart API Endpoint)            -- blocked by 3, 4, 5, 6
Item 10 (Commitments API Endpoints)    -- blocked by 6
Item 11 (Accepted Date API Endpoint)   -- blocked by 3, 7
Item 12 (Most Popular Date Endpoint)   -- blocked by 6
Item 13 (ICS Export Endpoint)          -- blocked by 3
Item 14 (Email Reminder Cron Job)      -- blocked by 7, 8, 13
Item 15 (Navigation Config)            -- blocked by 3
Item 16 (Chart of Goodness UI)         -- blocked by 9, 10, 12, 15
Item 17 (Event Admin Pages)            -- blocked by 3, 4, 5, 7
Item 18 (Model Linking UI)             -- blocked by 3, 16
```

---

## Catalog Items

### 1. DateTime Timezone Support - Backend

- **Purpose**: Fix the gap where `user_timezone` is not included in `AuthenticationService.formatUserData()` output. This is a framework-level prerequisite for correct timezone handling across the events feature. (Spec: Framework Enhancement section)
- **Scope**:
  - `src/Services/AuthenticationService.php` (modify `formatUserData()`)
  - `tests/Services/AuthenticationServiceTest.php` (add test for timezone field inclusion)
- **Blocks**: 2
- **Blocked by**: None
- **Acceptance Criteria**: AC-20 (partial - backend half)

### 2. DateTime Timezone Support - Frontend

- **Purpose**: Update the DateTimePicker component to convert between UTC (stored) and the user's configured timezone (displayed). (Spec: Framework Enhancement section)
- **Scope**:
  - `gravitycar-frontend/src/components/fields/DateTimePicker.tsx` (add timezone conversion)
  - `gravitycar-frontend/src/components/fields/__tests__/DateTimePicker.test.tsx` (test timezone conversion)
- **Blocks**: None (but all DateTime-using UI benefits from this)
- **Blocked by**: 1
- **Acceptance Criteria**: AC-20 (frontend half)

### 3. Events Model

- **Purpose**: Create the primary Events model with all specified fields (name, description, location, duration_hours, accepted_date, linked_model_name, linked_record_id). This is the core model that all other items depend on. (Spec: Models section - Events)
- **Scope**:
  - `src/Models/events/events_metadata.php` (metadata definition)
  - `src/Models/events/Events.php` (model class with is_active and most_popular_dates computed logic)
  - `tests/Models/EventsTest.php` (unit tests)
- **Blocks**: 4, 5, 6, 7, 9, 11, 13, 15, 17, 18
- **Blocked by**: None
- **Acceptance Criteria**: AC-1, AC-16

### 4. Event_Proposed_Dates Model

- **Purpose**: Create the model for candidate date/time options that users vote on. (Spec: Models section - Event_Proposed_Dates)
- **Scope**:
  - `src/Models/event_proposed_dates/event_proposed_dates_metadata.php`
  - `src/Models/event_proposed_dates/EventProposedDates.php`
  - `tests/Models/EventProposedDatesTest.php`
- **Blocks**: 6, 9
- **Blocked by**: 3
- **Acceptance Criteria**: AC-2

### 5. Event_Invitations Relationship (ManyToMany)

- **Purpose**: Create the ManyToMany relationship between Events and Users with additional fields (invited_at, invited_by). This governs who can see and interact with an event. (Spec: Models section - Event_Invitations, Relationships section)
- **Scope**:
  - `src/Relationships/events_users_invitations/events_users_invitations_metadata.php`
  - `tests/Relationships/EventsUsersInvitationsTest.php`
- **Blocks**: 6, 9
- **Blocked by**: 3
- **Acceptance Criteria**: AC-3

### 6. Event_Commitments Model

- **Purpose**: Create the model that stores per-user, per-proposed-date availability (the data behind the Chart of Goodness). Includes unique constraint on (event_id, user_id, proposed_date_id). (Spec: Models section - Event_Commitments)
- **Scope**:
  - `src/Models/event_commitments/event_commitments_metadata.php`
  - `src/Models/event_commitments/EventCommitments.php`
  - `tests/Models/EventCommitmentsTest.php`
- **Blocks**: 9, 10, 12
- **Blocked by**: 3, 4, 5
- **Acceptance Criteria**: AC-5, AC-15

### 7. Event_Reminders Model

- **Purpose**: Create the model for scheduled email reminders with auto-calculation of remind_at for preset types and the full reminder lifecycle logic. (Spec: Models section - Event_Reminders, Email Reminders section)
- **Scope**:
  - `src/Models/event_reminders/event_reminders_metadata.php`
  - `src/Models/event_reminders/EventReminders.php`
  - `tests/Models/EventRemindersTest.php`
- **Blocks**: 11, 14
- **Blocked by**: 3
- **Acceptance Criteria**: AC-10, AC-19

### 8. Email_Queue Model

- **Purpose**: Create the framework model for reliable email delivery with status tracking, retry support, and related event/reminder references. (Spec: Models section - Email_Queue)
- **Scope**:
  - `src/Models/email_queue/email_queue_metadata.php`
  - `src/Models/email_queue/EmailQueue.php`
  - `tests/Models/EmailQueueTest.php`
- **Blocks**: 14
- **Blocked by**: None
- **Acceptance Criteria**: AC-21

### 9. Chart of Goodness API Endpoint

- **Purpose**: Build the custom GET endpoint that assembles the full chart data (proposed dates, invited users, commitments) optimized for grid rendering. Implements its own access control (admin, invited users, guests). (Spec: API Endpoints - GET /api/events/{event_id}/chart)
- **Scope**:
  - `src/Models/events/api/Api/ChartController.php`
  - `tests/Api/ChartControllerTest.php`
- **Blocks**: 16
- **Blocked by**: 3, 4, 5, 6
- **Acceptance Criteria**: AC-4, AC-14, AC-18

### 10. Commitments API Endpoints (Upsert + Accept All)

- **Purpose**: Build the PUT endpoint for per-cell upsert of commitments and the POST accept-all endpoint. Both enforce invitation-gated and own-row authorization. (Spec: API Endpoints - PUT /api/events/{event_id}/commitments, POST /api/events/{event_id}/accept-all)
- **Scope**:
  - `src/Models/events/api/Api/CommitmentsController.php`
  - `tests/Api/CommitmentsControllerTest.php`
- **Blocks**: 16
- **Blocked by**: 6
- **Acceptance Criteria**: AC-5, AC-6, AC-15

### 11. Accepted Date API Endpoint

- **Purpose**: Build the PUT endpoint that sets an event's accepted_date and triggers recalculation of remind_at on all preset (non-sent) reminders. (Spec: API Endpoints - PUT /api/events/{event_id}/accepted-date)
- **Scope**:
  - `src/Models/events/api/Api/AcceptedDateController.php`
  - `tests/Api/AcceptedDateControllerTest.php`
- **Blocks**: None directly (but 16 uses it)
- **Blocked by**: 3, 7
- **Acceptance Criteria**: AC-8, AC-19

### 12. Most Popular Date API Endpoint

- **Purpose**: Build the GET endpoint that returns the proposed date(s) with the highest availability count, including tied dates. (Spec: API Endpoints - GET /api/events/{event_id}/most-popular-date)
- **Scope**:
  - `src/Models/events/api/Api/MostPopularDateController.php`
  - `tests/Api/MostPopularDateControllerTest.php`
- **Blocks**: 16
- **Blocked by**: 6
- **Acceptance Criteria**: AC-7

### 13. ICS Export Endpoint + Composer Dependency

- **Purpose**: Add the `spatie/icalendar-generator` Composer dependency and build the GET endpoint that generates RFC 5545-compliant ICS files for events with an accepted date. (Spec: ICS Export section, API Endpoints - GET /api/events/{event_id}/ics)
- **Scope**:
  - `composer.json` (add spatie/icalendar-generator dependency)
  - `src/Models/events/api/Api/IcsExportController.php`
  - `tests/Api/IcsExportControllerTest.php`
- **Blocks**: 14
- **Blocked by**: 3
- **Acceptance Criteria**: AC-9

### 14. Email Reminder Cron Job + PHPMailer

- **Purpose**: Add the `phpmailer/phpmailer` Composer dependency and build the cron job script that processes the email queue (sending pending emails, handling retries with exponential backoff) and processes pending reminders (creating email queue entries for invited users). (Spec: Email Reminders section - Background process, Email Queue section)
- **Scope**:
  - `composer.json` (add phpmailer/phpmailer dependency)
  - `src/Services/EmailReminderService.php` (reminder processing + email queue processing logic)
  - `tests/Services/EmailReminderServiceTest.php`
- **Blocks**: None
- **Blocked by**: 7, 8, 13
- **Acceptance Criteria**: AC-11

### 15. Navigation Configuration

- **Purpose**: Add the "Events" section to the navigation sidebar with "Create Event" (admin only) and "List Events" sub-items. Implement smart routing logic (single upcoming event redirects to chart). (Spec: Navigation section)
- **Scope**:
  - `src/Navigation/navigation_config.php` (add Events entries)
  - `gravitycar-frontend/src/services/navigationService.ts` (smart routing logic)
  - `gravitycar-frontend/src/services/__tests__/navigationService.test.ts`
- **Blocks**: 16
- **Blocked by**: 3
- **Acceptance Criteria**: AC-13

### 16. Chart of Goodness UI (React Page)

- **Purpose**: Build the custom React page component for the Chart of Goodness: the grid of proposed dates vs invited users with toggle checkboxes, most popular date banner, accepted date banner with ICS download, admin controls, and responsive layout. (Spec: UI Components - Chart of Goodness)
- **Scope**:
  - `gravitycar-frontend/src/pages/ChartOfGoodness.tsx` (main page component)
  - `gravitycar-frontend/src/App.tsx` (add route for chart page)
  - `gravitycar-frontend/src/pages/__tests__/ChartOfGoodness.test.tsx`
- **Blocks**: 18
- **Blocked by**: 9, 10, 12, 15
- **Acceptance Criteria**: AC-4, AC-5, AC-6, AC-7, AC-8, AC-9, AC-14, AC-18

### 17. Event Admin Pages (CRUD via GenericCrudPage)

- **Purpose**: Ensure standard CRUD pages work for Events, Event_Proposed_Dates, Event_Invitations, and Event_Reminders via GenericCrudPage. This involves verifying metadata UI sections (listFields, createFields, editFields, relatedItemsSections) are correctly configured. (Spec: UI Components - Event Admin Pages)
- **Scope**:
  - `src/Models/events/events_metadata.php` (verify/add UI section config)
  - `src/Models/event_proposed_dates/event_proposed_dates_metadata.php` (verify/add UI section config)
  - `src/Models/event_reminders/event_reminders_metadata.php` (verify/add UI section config)
- **Blocks**: None
- **Blocked by**: 3, 4, 5, 7
- **Acceptance Criteria**: AC-1, AC-2, AC-3, AC-10

### 18. Model Linking UI

- **Purpose**: Build the UI for linking an event to a record from any other model in the system (model selector dropdown + record search/select). Uses linked_model_name and linked_record_id fields. Also display linked record info (including image if available) on the Chart of Goodness header. (Spec: UI Components - Model Linking UI)
- **Scope**:
  - `gravitycar-frontend/src/components/fields/ModelLinker.tsx` (model selector + record picker component)
  - `gravitycar-frontend/src/pages/ChartOfGoodness.tsx` (integrate linked record display in header)
  - `gravitycar-frontend/src/components/fields/__tests__/ModelLinker.test.tsx`
- **Blocks**: None
- **Blocked by**: 3, 16
- **Acceptance Criteria**: AC-12

---

## Build Order Summary

**Phase 1 - Framework Fix + Independent Models (no dependencies):**
- Item 1: DateTime Timezone - Backend
- Item 3: Events Model
- Item 8: Email_Queue Model

**Phase 2 - Dependent Models + Frontend Timezone:**
- Item 2: DateTime Timezone - Frontend (after 1)
- Item 4: Event_Proposed_Dates Model (after 3)
- Item 5: Event_Invitations Relationship (after 3)
- Item 7: Event_Reminders Model (after 3)

**Phase 3 - Core Linking Model:**
- Item 6: Event_Commitments Model (after 3, 4, 5)

**Phase 4 - API Endpoints:**
- Item 9: Chart API Endpoint (after 3, 4, 5, 6)
- Item 10: Commitments API Endpoints (after 6)
- Item 11: Accepted Date API Endpoint (after 3, 7)
- Item 12: Most Popular Date Endpoint (after 6)
- Item 13: ICS Export Endpoint (after 3)

**Phase 5 - Infrastructure + Navigation:**
- Item 14: Email Reminder Cron Job (after 7, 8, 13)
- Item 15: Navigation Configuration (after 3)

**Phase 6 - UI:**
- Item 16: Chart of Goodness UI (after 9, 10, 12, 15)
- Item 17: Event Admin Pages (after 3, 4, 5, 7)

**Phase 7 - Final UI:**
- Item 18: Model Linking UI (after 3, 16)
