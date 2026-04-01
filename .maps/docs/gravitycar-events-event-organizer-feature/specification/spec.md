# Specification: Gravitycar Events (Chart of Goodness) Feature

## Revision History

| Rev | Date | Description |
|-----|------|-------------|
| 1.0 | 2026-04-01 | Initial specification |
| 1.1 | 2026-04-01 | Incorporate resolutions from Critical Review #1 (Q1-Q8) |
| 1.2 | 2026-04-01 | Incorporate resolutions from Critical Review #2 (Q9-Q11): sent reminders skip recalculation, email retry strategy, broader smart routing |

## Problem Statement

Groups of people need a way to coordinate meeting times. The system SHALL allow an administrator to create named events, propose candidate date/times, and invite users. Invited users SHALL indicate which proposed dates work for them via a visual grid (the "Chart of Goodness"). The system SHALL calculate and display the most popular date(s). This feature previously existed in the old Gravitycar codebase and must be re-implemented within the new Gravitycar Framework, leveraging its metadata-driven model system.

---

## Models

### 1. Events

The primary model representing a scheduled gathering.

**Fields:**

| Field Name | Type | Required | Default | Description |
|---|---|---|---|---|
| name | Text | Yes | | Display name of the event |
| description | Text | No | | Detailed description |
| location | Text | No | | Free-text venue or address |
| duration_hours | Number (Integer) | No | 3 | Duration of the event in hours. Used for ICS export DTEND calculation (DTEND = accepted_date + duration_hours). |
| accepted_date | DateTime | No | | The finalized date chosen by the admin |
| linked_model_name | Text | No | | Name of a related Gravitycar model (e.g., "Books") |
| linked_record_id | ID | No | | ID of the specific record in the linked model |
| created_by | (core field) | Auto | | The user who created the event (treated as event owner) |

**Computed/Derived Properties (not stored as fields):**
- `is_active`: An event is "active" when it has at least one proposed date in the future AND `accepted_date` is NULL. Otherwise it is "inactive".
- `most_popular_dates`: The proposed date(s) with the highest commitment count where `is_available = true`. When multiple dates are tied for the highest count, ALL tied dates SHALL be returned. The API and UI SHALL display all tied dates rather than picking a single winner.

**Roles and Actions:**
- `admin`: all actions (create, read, update, delete, list)
- `user`: list, read
- `guest`: list, read

### 2. Event_Proposed_Dates

Candidate date/time options for an event.

**Fields:**

| Field Name | Type | Required | Description |
|---|---|---|---|
| event_id | RelatedRecord (Events) | Yes | The parent event |
| proposed_date | DateTime | Yes | The candidate date and time |

**Roles and Actions:**
- `admin`: all actions
- `user`: list, read
- `guest`: list, read

### 3. Event_Invitations

Tracks which users are invited to which events. Implemented as a ManyToMany relationship between Events and Users with additional fields.

**Additional Fields on the Relationship:**

| Field Name | Type | Required | Description |
|---|---|---|---|
| invited_at | DateTime | Yes | Timestamp of when the invitation was created |
| invited_by | RelatedRecord (Users) | Yes | The user who sent the invitation |

**Roles and Actions:**
- `admin`: all actions
- `user`: read, list (users can see their own invitations)
- `guest`: none

### 4. Event_Commitments

Records a specific user's availability for a specific proposed date on a specific event. This is the core data behind the Chart of Goodness.

**Fields:**

| Field Name | Type | Required | Description |
|---|---|---|---|
| event_id | RelatedRecord (Events) | Yes | The event |
| user_id | RelatedRecord (Users) | Yes | The invited user |
| proposed_date_id | RelatedRecord (Event_Proposed_Dates) | Yes | The proposed date |
| is_available | Boolean | Yes | Whether the user can attend on this date (default: false) |

**Unique Constraint:** The combination of (event_id, user_id, proposed_date_id) SHALL be unique.

**Roles and Actions:**
- `admin`: all actions
- `user`: create, read, update, list (restricted to own records -- see Authorization)
- `guest`: none (note: this only governs direct CRUD on commitment records; guests access chart data via the dedicated chart endpoint which assembles data directly -- see Authorization section)

### 5. Event_Reminders

Scheduled email reminders for an event. Admin creates reminder entries; a background process sends them.

**Fields:**

| Field Name | Type | Required | Description |
|---|---|---|---|
| event_id | RelatedRecord (Events) | Yes | The parent event |
| remind_at | DateTime | No | When the reminder should be sent. Auto-calculated for preset types (see Reminder Lifecycle below). NULL when no accepted_date is set. |
| reminder_type | Enum | Yes | One of: "2_weeks", "1_week", "1_day", "custom" |
| sent_at | DateTime | No | Timestamp when the reminder was actually sent (NULL = not yet sent) |
| status | Enum | Yes | One of: "pending", "sent", "failed" (default: "pending") |

**Reminder Lifecycle:**
- For preset reminder types ("2_weeks", "1_week", "1_day"), the `remind_at` value SHALL be auto-calculated relative to the event's `accepted_date`:
  - "2_weeks" = accepted_date minus 14 days
  - "1_week" = accepted_date minus 7 days
  - "1_day" = accepted_date minus 1 day
- If the event has no `accepted_date` set, preset reminders SHALL remain in "pending" status with `remind_at` set to NULL. The background process SHALL skip reminders with NULL `remind_at`.
- When an event's `accepted_date` changes, ALL preset reminders (those with reminder_type != "custom") for that event SHALL have their `remind_at` recalculated based on the new accepted_date.
- Custom reminders (reminder_type = "custom") are NEVER recalculated. The admin sets the `remind_at` explicitly and it does not change when accepted_date changes.
- Reminders that have already been sent (status = "sent") SHALL NOT be recalculated regardless of type.

**Roles and Actions:**
- `admin`: all actions
- `user`: read, list
- `guest`: none

### 7. Email_Queue

A full Gravitycar framework model for reliable email delivery. All outbound emails generated by the events feature (reminders, etc.) SHALL be queued here.

**Fields:**

| Field Name | Type | Required | Default | Description |
|---|---|---|---|---|
| recipient_email | Text | Yes | | Email address of the recipient |
| recipient_user_id | RelatedRecord (Users) | No | | The recipient user (if the recipient is a registered user) |
| subject | Text | Yes | | Email subject line |
| body | BigText | Yes | | Email body content (HTML) |
| status | Enum | Yes | "pending" | One of: "pending", "sent", "failed", "cancelled" |
| send_at | DateTime | Yes | | Scheduled send time |
| sent_at | DateTime | No | | Timestamp when the email was actually sent (NULL = not yet sent) |
| retry_count | Number (Integer) | No | 0 | Number of send attempts made so far |
| error_message | Text | No | | Error details if status is "failed" (NULL when no error) |
| related_event_id | RelatedRecord (Events) | No | | The event this email relates to (if applicable) |
| related_reminder_id | RelatedRecord (Event_Reminders) | No | | The reminder that triggered this email (if applicable) |

**Roles and Actions:**
- `admin`: all actions (create, read, update, delete, list)
- `user`: none
- `guest`: none

This model SHALL have a metadata file, a PHP model class extending ModelBase, and a database table generated by SchemaGenerator, just like all other framework models.

---

## Relationships

| Relationship Name | Type | Model One | Model Many/Two | Notes |
|---|---|---|---|---|
| events_event_proposed_dates | OneToMany | Events | Event_Proposed_Dates | |
| events_users_invitations | ManyToMany | Events | Users | Additional fields: invited_at, invited_by |

| events_event_commitments | OneToMany | Events | Event_Commitments | |
| events_event_reminders | OneToMany | Events | Event_Reminders | |

---

## API Endpoints

Standard CRUD endpoints SHALL be auto-generated by the framework for all models above. The following custom API endpoints are additionally required:

### Custom Endpoints

**GET /api/events/{event_id}/chart**
- Returns the full Chart of Goodness data for an event: all proposed dates, all invited users, and all commitments in a structure optimized for grid rendering.
- This endpoint assembles chart data directly by querying proposed dates, invitations, and commitments. It does NOT go through the Event_Commitments model's rolesAndActions for access control. The `guest:none` setting on Event_Commitments only prevents direct CRUD operations on individual commitment records; it does not restrict the chart endpoint from reading commitment data for display.
- Accessible by: admin, invited users, guests (read-only).

**PUT /api/events/{event_id}/commitments**
- Accepts an array of { proposed_date_id, is_available } objects for the current authenticated user.
- SHALL use per-cell upsert logic (INSERT or UPDATE on the unique constraint), NOT delete-all-then-reinsert.
- Accessible by: authenticated invited users only.

**POST /api/events/{event_id}/accept-all**
- Sets `is_available = true` for ALL proposed dates for the current authenticated user on this event.
- Accessible by: authenticated invited users only.

**PUT /api/events/{event_id}/accepted-date**
- Sets the `accepted_date` field on the event to a specified proposed_date_id's datetime value.
- When accepted_date is set or changed, this endpoint SHALL trigger recalculation of `remind_at` on all preset reminders for this event, except those with status='sent', which SHALL NOT be recalculated (see Reminder Lifecycle under Event_Reminders).
- Accessible by: admin only.

**GET /api/events/{event_id}/ics**
- Generates and returns an ICS (iCalendar) file for the event's accepted date. SHALL include: event name, description, location, accepted date/time as DTSTART, DTEND calculated as DTSTART + `duration_hours`, and organizer.
- Returns HTTP 404 if no accepted_date is set.
- Content-Type: text/calendar.
- Accessible by: admin, invited users.

**GET /api/events/{event_id}/most-popular-date**
- Returns the proposed date(s) with the highest count of commitments where `is_available = true`.
- When multiple dates are tied for the highest count, ALL tied dates SHALL be returned as an array.
- Accessible by: admin, invited users, guests.

---

## Authorization

Beyond the standard role-based `rolesAndActions`, the following row-level authorization rules SHALL apply:

1. **Invitation-gated access:** Only users who are invited to an event (or admins) SHALL be able to view the Chart of Goodness and create commitments for that event.
2. **Own-row editing:** Users SHALL only be able to create or update Event_Commitments where `user_id` matches their own user ID.

4. **Public read access:** Unauthenticated visitors (guests) SHALL be able to view the events list and read event details (name, description, proposed dates, chart data) but SHALL NOT be able to modify any data. Guest access to chart data is provided by the chart API endpoint, which assembles data directly and is not governed by the Event_Commitments model's `guest:none` rolesAndActions setting.
5. **Admin full access:** Admins SHALL have full CRUD on all event-related models regardless of invitation status.

---

## Navigation

The system SHALL add an "Events" section to the navigation sidebar with the following behavior:

**Menu Items:**
- "Events" (top-level section, main link uses smart routing -- see below)
  - "Create Event" (admin only)
  - "List Events"

Proposing dates and inviting people are actions within the event detail/management UI, NOT separate navigation items.

**Smart Routing:**
- When a user clicks "Events" (the main link):
  - If the user has exactly ONE upcoming event invitation, redirect directly to that event's Chart of Goodness.
  - If the user has ZERO or MORE THAN ONE upcoming event invitation, display a list of events.
- An "upcoming event" for smart routing purposes is any event where the user is invited AND (has at least one proposed date in the future OR has an accepted_date in the future). This is broader than the `is_active` computed property, which excludes events with an accepted_date.
- Active events (those with future proposed dates and no accepted_date) SHALL be displayed first in any event list.

---

## UI Components

### Chart of Goodness (Custom React Page)

The primary feature UI. This SHALL be a custom React page component, NOT rendered by GenericCrudPage.

**Layout:**
- **Header area:** Event name, description, location, and link to the linked model record (if any). If the linked model record has an Image field (e.g., a book cover or movie poster), display that image in the header as visual eye-candy. The image SHALL be fetched from the linked record's first Image-type field as defined in its metadata.
- **Most Popular Date banner:** Prominently displayed above the grid, showing the proposed date(s) with the highest availability count. When multiple dates are tied, ALL tied dates SHALL be displayed (e.g., "Most popular: Sat Mar 14 @ 7pm, Sun Mar 15 @ 7pm (tied, 5 votes each)").
- **Accepted Date banner:** If an accepted_date is set, display it prominently with an "Export to Calendar (.ics)" button.
- **Grid/Table:**
  - Columns: one per proposed date, formatted to show day-of-week, month/day, and time.
  - Rows: one per invited guest. Guest display names SHALL be determined by the `displayColumns` metadata property from the Users model. Do NOT hardcode a name format; rely on the framework's existing displayColumns mechanism to govern how user names are rendered.
  - Cells: for the current user's row, render a tap-to-toggle checkbox. For other users' rows, render a read-only green (available) or red/gray (unavailable) indicator.
  - An "Accept All" button for the current user that checks all dates.

- **Admin controls:** Visible only to admins:
  - "Set Accepted Date" action on any proposed date column.
  - Links to manage proposed dates, invitations, and reminders.

**Responsiveness:**
- On narrow viewports, the grid SHALL scroll horizontally or switch to a card-based layout where each proposed date is a card the user can tap to toggle.

### Event Admin Pages

Standard CRUD pages (via GenericCrudPage) for:
- Events: create, edit, list, delete
- Event_Proposed_Dates: create, delete (accessed from event detail/edit)
- Event_Invitations: manage invited users (user selector UI)
- Event_Reminders: create, list, delete

### Model Linking UI

When creating or editing an event, the admin SHALL be able to optionally link the event to a record from any other model in the system (e.g., link a "Book Club Meeting" event to a specific Books record). This SHALL use the `linked_model_name` and `linked_record_id` fields. The UI SHALL provide:
- A dropdown to select the model name.
- A search/select field to pick a specific record from that model.

---

## ICS Export

The system SHALL generate RFC 5545-compliant iCalendar files using a PHP library (installed via Composer). The generated ICS file SHALL include:
- VEVENT with DTSTART (the accepted_date), DTEND (DTSTART + the event's `duration_hours` field, defaulting to 3 hours), SUMMARY (event name), DESCRIPTION, LOCATION, UID (based on event UUID), and DTSTAMP.
- All datetime values in the ICS file SHALL be in UTC.
- Proper timezone handling using DateTimeImmutable with explicit timezone objects.

The ICS file SHALL be downloadable from the Chart of Goodness page (when an accepted date is set) and optionally attached to reminder emails.

---

## Email Reminders

The system SHALL support scheduled email reminders for events.

**Behavior:**
- Admins can create reminders at predefined intervals: 2 weeks before, 1 week before, 1 day before the accepted date, or a custom date/time.
- For preset reminder types, `remind_at` is auto-calculated from the accepted_date (see Reminder Lifecycle under Event_Reminders model).
- Reminders with NULL `remind_at` (i.e., no accepted_date set yet) SHALL NOT be processed by the background job.
- A background process (cron job) SHALL check for pending reminders whose `remind_at` has passed and send emails to all invited users.
- Each reminder record SHALL track its sent status to prevent duplicate sends.
- Reminder emails SHALL include: event name, accepted date/time, location, and an ICS attachment.

**Email Queue:**
- The system SHALL use the Email_Queue model (see Models section) for reliable email delivery.
- Failed sends SHALL be automatically retried by the cron job up to 3 attempts with exponential backoff: 1st retry after 5 minutes, 2nd retry after 30 minutes, 3rd retry after 2 hours. The `retry_count` field on Email_Queue tracks the number of attempts. After 3 failed attempts, the status SHALL change to "failed" permanently and the email requires admin review. During retries, the status remains "pending" and `send_at` is updated to the next retry time.

---

## Framework Enhancement: DateTime Timezone Support

This feature requires timezone-aware date handling. The following framework-level changes SHALL be made as part of this epic.

**Principle:** All DateTime values SHALL be stored in UTC in the database. All DateTime values SHALL be displayed to users in their configured timezone.

**Required Changes:**

1. **Backend -- AuthenticationService.formatUserData():**
   - File: `src/Services/AuthenticationService.php` (approximately line 718)
   - The `formatUserData()` method SHALL include the `user_timezone` field from the Users model metadata in its output. The `user_timezone` field is already defined in the Users model metadata and already present in the TypeScript User interface; it is simply not being populated from the backend currently.

2. **Frontend -- DateTimePicker.tsx:**
   - The DateTimePicker component SHALL call `useAuth()` to obtain the current user's timezone from the auth context.
   - On display: convert stored UTC values to the user's timezone for rendering.
   - On save: convert the user's local timezone input back to UTC before sending to the API.

**Context:** The `user_timezone` field already exists in the Users model metadata, is already defined in the TypeScript `User` interface, and already flows through the `useAuth()` context. The only gap is that `AuthenticationService.formatUserData()` does not currently include it in the response payload. No event-level timezone field is needed.

---

## Explicit Constraints (DO NOT)

- Do NOT modify existing models (Users, Movies, Books, etc.) except to add relationship references where needed.
- Do NOT implement real-time/WebSocket updates for the Chart of Goodness (use standard HTTP request/response).
- Do NOT implement a drag-select availability grid (use discrete checkbox toggles on proposed dates).
- Do NOT use Google Maps API for location. Use a plain text field.
- Do NOT implement recurring events (each event is a one-time occurrence).
- Do NOT use the delete-all-then-reinsert pattern for commitments. Use per-cell upsert.
- Do NOT store email credentials in code. Use configuration files.
- Do NOT implement push notifications (email only).
- Do NOT hardcode user display name formats. Use the framework's displayColumns mechanism.
- Do NOT add an event-level timezone field. Use the user's timezone from the Users model.

---

## Acceptance Criteria

1. **AC-1:** An admin can create an event with name, description, optional location, and optional duration, and it appears in the events list.
2. **AC-2:** An admin can add and remove proposed date/times for an event.
3. **AC-3:** An admin can invite users to an event, and those users see the event in their event list.
4. **AC-4:** The Chart of Goodness displays a grid of proposed dates (columns) vs. invited users (rows) with correct availability indicators.
5. **AC-5:** An authenticated invited user can toggle their own availability checkboxes, and changes persist across page reloads.
6. **AC-6:** An authenticated invited user can click "Accept All" to mark all proposed dates as available in a single action.
7. **AC-7:** The "Most Popular Date" is calculated correctly and displayed prominently above the chart. When dates are tied, ALL tied dates are shown.
8. **AC-8:** An admin can set an accepted date for an event, and it is displayed on the Chart of Goodness.
9. **AC-9:** When an accepted date is set, users can download an ICS file that is valid per RFC 5545 and imports correctly into common calendar applications. The ICS DTEND is calculated from the event's duration_hours field.
10. **AC-10:** An admin can create email reminders at 2-week, 1-week, and 1-day intervals before the accepted date, and their remind_at values are auto-calculated.
11. **AC-11:** Pending reminders are sent to all invited users by the background process, and each reminder is sent exactly once. Reminders with no accepted_date (NULL remind_at) are skipped.
12. **AC-12:** An admin can link an event to a record from any other model, and the linked record is displayed on the event detail/chart page.
13. **AC-13:** Navigation includes an "Events" section with "Create Event" (admin only) and "List Events" sub-items. Smart routing: single active event redirects to chart, multiple shows list.
14. **AC-14:** Unauthenticated guests can view the events list and Chart of Goodness in read-only mode (no checkboxes, no edit controls). The chart endpoint provides this data directly.
15. **AC-15:** Users cannot modify another user's commitments; the system rejects such attempts.
16. **AC-16:** Events with future proposed dates and no accepted_date are displayed as "active" and sorted first in lists.

18. **AC-18:** User display names in the Chart of Goodness rows are rendered using the Users model's displayColumns metadata property.
19. **AC-19:** When an event's accepted_date is changed, all preset reminders for that event have their remind_at values recalculated. Custom reminders are not affected.
20. **AC-20:** All DateTime values are stored in UTC. The DateTimePicker component displays dates in the current user's timezone and converts to UTC on save.
21. **AC-21:** The Email_Queue model exists as a full framework model with metadata, model class, and auto-generated database table. Only admins can access it.

---

## Technical Context

### Existing Patterns to Follow

- **Model structure:** Each model needs a PHP class at `src/Models/{modelname}/{ModelName}.php` extending `ModelBase` and a metadata file at `src/Models/{modelname}/{modelname}_metadata.php`. Follow the same constructor DI pattern (7 params) as existing models.
- **Relationship structure:** Metadata at `src/Relationships/{rel_name}/{rel_name}_metadata.php`. ManyToMany relationships support `additionalFields`.
- **Custom API controllers:** Extend `ApiControllerBase`, placed in `src/Models/{modelname}/api/Api/`. Registered via `APIRouteRegistry` auto-discovery.
- **Navigation:** Add entries to `src/Navigation/navigation_config.php` under `custom_pages` with role restrictions.
- **Frontend custom pages:** Add dedicated routes in `App.tsx`. Page components go in `gravitycar-frontend/src/pages/`. Use `useModelMetadata`, `useAuth` hooks and `apiService` for data fetching.
- **Schema generation:** `SchemaGenerator` creates database tables from metadata. New models will get tables automatically.
- **Authorization:** Use `rolesAndActions` in metadata for role-based access. Row-level authorization (invitation-gated, own-row) will require custom logic in the API controllers. The chart endpoint SHALL implement its own access control independently of the Event_Commitments rolesAndActions.
- **Display columns:** The framework's `displayColumns` metadata property on any model controls how records from that model are displayed when referenced. The Chart of Goodness SHALL use this mechanism for rendering user names in rows.

### Integration Points

- **Users model:** Event_Invitations creates a ManyToMany between Events and Users. Event_Commitments references Users via a RelatedRecord field. The `user_timezone` field from Users SHALL be included in `AuthenticationService.formatUserData()`.
- **NavigationConfig:** Must be updated to include the Events section with "Create Event" and "List Events" sub-items.
- **App.tsx:** Must add a route for the Chart of Goodness custom page.
- **Composer:** Must add `spatie/icalendar-generator` and `phpmailer/phpmailer` dependencies.
- **AuthenticationService:** Must be updated to include `user_timezone` in the formatted user data.
- **DateTimePicker.tsx:** Must be updated to convert UTC to/from the user's timezone.

### New Infrastructure

- **Email_Queue model:** A full framework model with metadata, PHP class, and auto-generated database table for queuing outbound emails with status tracking.
- **Cron job script:** A PHP script to process the email queue, intended to run via system crontab every minute.
