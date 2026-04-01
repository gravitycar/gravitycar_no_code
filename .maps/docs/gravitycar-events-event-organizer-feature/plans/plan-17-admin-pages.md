# Implementation Plan: Item 17 - Event Admin Pages (CRUD via GenericCrudPage)

## Spec Context

The specification requires standard CRUD pages for Events, Event_Proposed_Dates, Event_Invitations, and Event_Reminders via GenericCrudPage (Spec: UI Components - Event Admin Pages). GenericCrudPage is fully metadata-driven: it reads `ui.listFields`, `ui.createFields`, `ui.editFields`, and `ui.relatedItemsSections` from model metadata to render list views, create/edit forms, and related item management. This plan adds or verifies those UI sections in each model's metadata file.

Catalog item: 17 - Event Admin Pages
Specification section: UI Components - Event Admin Pages
Acceptance criteria addressed: AC-1 (admin create event), AC-2 (add/remove proposed dates), AC-3 (invite users), AC-10 (create reminders)

## Dependencies

- **Blocked by**: Items 3 (Events Model), 4 (Event_Proposed_Dates), 5 (Event_Invitations), 7 (Event_Reminders) -- metadata files must exist
- **Uses**: `gravitycar-frontend/src/components/crud/GenericCrudPage.tsx` (no modifications needed), `gravitycar-frontend/src/components/forms/ModelForm.tsx`, `gravitycar-frontend/src/components/routing/DynamicModelRoute.tsx`

## File Changes

### Modified Files

- `src/Models/events/events_metadata.php` -- Add `ui` section with listFields, createFields, editFields, relatedItemsSections
- `src/Models/event_proposed_dates/event_proposed_dates_metadata.php` -- Add `ui` section with listFields, createFields
- `src/Models/event_reminders/event_reminders_metadata.php` -- Add `ui` section with listFields, createFields, editFields

### No New Files

GenericCrudPage and DynamicModelRoute already handle rendering for any model with a `ui` section. No new React components or routes are needed -- the existing catch-all `/:modelName` route in App.tsx renders DynamicModelRoute, which loads GenericCrudPage with the model's metadata.

## Implementation Details

### 1. Events Metadata UI Section

**File**: `src/Models/events/events_metadata.php`

Add the following `ui` key to the metadata array:

```php
'ui' => [
    'listFields' => ['name', 'location', 'accepted_date', 'created_by_display_name'],
    'createFields' => ['name', 'description', 'location', 'duration_hours', 'linked_model_name', 'linked_record_id'],
    'editFields' => ['name', 'description', 'location', 'duration_hours', 'accepted_date', 'linked_model_name', 'linked_record_id'],
    'relatedItemsSections' => [
        'proposed_dates' => [
            'title' => 'Proposed Dates',
            'relationship' => 'events_event_proposed_dates',
            'mode' => 'children_management',
            'relatedModel' => 'Event_Proposed_Dates',
            'displayColumns' => ['proposed_date'],
            'actions' => ['create', 'delete'],
            'allowInlineCreate' => true,
            'allowInlineEdit' => false,
            'createFields' => ['proposed_date'],
            'editFields' => [],
        ],
        'invitations' => [
            'title' => 'Invited Users',
            'relationship' => 'events_users_invitations',
            'mode' => 'children_management',
            'relatedModel' => 'Users',
            'displayColumns' => ['first_name', 'last_name', 'email'],
            'actions' => ['create', 'delete'],
            'allowInlineCreate' => true,
            'allowInlineEdit' => false,
            'createFields' => ['user_id'],
            'editFields' => [],
        ],
        'reminders' => [
            'title' => 'Reminders',
            'relationship' => 'events_event_reminders',
            'mode' => 'children_management',
            'relatedModel' => 'Event_Reminders',
            'displayColumns' => ['reminder_type', 'remind_at', 'status'],
            'actions' => ['create', 'delete'],
            'allowInlineCreate' => true,
            'allowInlineEdit' => false,
            'createFields' => ['reminder_type', 'remind_at'],
            'editFields' => [],
        ],
    ],
],
```

**Rationale for field choices:**

- **listFields**: `name` is the primary identifier; `location` gives context; `accepted_date` shows event status (null = no date chosen yet); `created_by_display_name` shows who created it. These are the most useful columns for an admin scanning the events list.
- **createFields**: All admin-editable fields except `accepted_date` (set via the dedicated accept-date API endpoint, not direct edit). Includes `linked_model_name` and `linked_record_id` for model linking.
- **editFields**: Same as create plus `accepted_date` (visible for reference, though typically set via the Chart of Goodness UI).
- **relatedItemsSections**: Three child sections accessible from the edit view. Proposed dates are create/delete only (no editing a date -- delete and re-add). Invitations allow adding/removing users. Reminders allow creating preset or custom reminders.

### 2. Event_Proposed_Dates Metadata UI Section

**File**: `src/Models/event_proposed_dates/event_proposed_dates_metadata.php`

Add the following `ui` key:

```php
'ui' => [
    'listFields' => ['event_id', 'proposed_date'],
    'createFields' => ['event_id', 'proposed_date'],
],
```

**Rationale**: This model is primarily managed as a child of Events (via the `relatedItemsSections` above), but standalone CRUD access is available for admins. The list shows which event and the proposed date. Create requires selecting the parent event and a date/time.

### 3. Event_Reminders Metadata UI Section

**File**: `src/Models/event_reminders/event_reminders_metadata.php`

Add the following `ui` key:

```php
'ui' => [
    'listFields' => ['event_id', 'reminder_type', 'remind_at', 'status', 'sent_at'],
    'createFields' => ['event_id', 'reminder_type', 'remind_at'],
    'editFields' => ['event_id', 'reminder_type', 'remind_at', 'status'],
],
```

**Rationale**:
- **listFields**: Shows parent event, type, scheduled time, current status, and when it was sent. This gives admins a complete overview.
- **createFields**: Admin picks event, reminder type, and optionally a custom remind_at (for "custom" type; for presets, remind_at is auto-calculated by the backend).
- **editFields**: Adds `status` so admins can manually change a reminder's status (e.g., cancel a pending reminder).

### 4. Event_Invitations (ManyToMany Relationship)

**File**: `src/Relationships/events_users_invitations/events_users_invitations_metadata.php`

The Event_Invitations relationship is a ManyToMany between Events and Users. It does NOT need its own standalone `ui` section because:
1. It is accessed as a `relatedItemsSections` entry on the Events model (see section 1 above).
2. ManyToMany relationships do not have their own GenericCrudPage route -- they are managed through the parent model's edit view.

No changes needed to this file for admin pages. The `relatedItemsSections` config on Events metadata handles the invitation management UI.

## How GenericCrudPage Uses These Sections

Based on the existing code in `GenericCrudPage.tsx`:

1. **List view**: Reads `metadata.ui.listFields` to determine which columns to render in the table. Each field name maps to `metadata.fields[fieldName]` for label and type-specific rendering.
2. **Create modal**: Opens `ModelForm` which reads `metadata.ui.createFields` to determine which form fields to show.
3. **Edit modal**: Opens `ModelForm` which reads `metadata.ui.editFields` (falling back to `createFields` if not set) to determine which form fields to show.
4. **Related items**: The `relatedItemsSections` are rendered on the edit view, showing child records with inline create/delete capabilities.

No changes to GenericCrudPage or ModelForm are required. The metadata-driven architecture handles everything.

## Error Handling

- If a metadata file is missing the `ui` section, GenericCrudPage will show a loading error. This is existing behavior and acceptable -- it signals to the developer that metadata is incomplete.
- RelatedRecord fields (like `event_id`) in list views will display the related record's display name using the `displayColumns` mechanism from the related model's metadata.

## Unit Test Specifications

No dedicated unit tests are needed for this item because:

1. The metadata `ui` sections are declarative configuration, not executable code.
2. GenericCrudPage is already tested for its metadata-driven rendering behavior.
3. The correctness of these configurations will be validated by integration/E2E tests that verify the admin can perform CRUD operations (covered by AC-1, AC-2, AC-3, AC-10).

However, the following manual/integration test scenarios should be verified:

| Scenario | Steps | Expected Result |
|---|---|---|
| Events list page loads | Navigate to /Events | Table shows name, location, accepted_date, created_by columns |
| Create event | Click "Add New Event", fill form, submit | Event created with name, description, location, duration_hours |
| Edit event with related items | Click Edit on an event | Edit form shows fields + Proposed Dates, Invited Users, Reminders sections |
| Add proposed date inline | In event edit, click "Add" in Proposed Dates section | DateTime picker appears, date is added to event |
| Delete proposed date | In event edit, click delete on a proposed date | Date removed from event |
| Add invitation inline | In event edit, click "Add" in Invited Users section | User selector appears, user added as invitee |
| Add reminder inline | In event edit, click "Add" in Reminders section | Reminder type + date form appears, reminder created |
| Reminders list page | Navigate to /Event_Reminders | Table shows event, type, remind_at, status, sent_at columns |

## Notes

- The `linked_model_name` and `linked_record_id` fields appear in create/edit forms as standard fields here. The enhanced Model Linking UI (catalog item 18) will provide a specialized dropdown/search component for these fields, but the basic text-based input works as a fallback for admin use.
- The `accepted_date` field is included in `editFields` for reference/visibility but is typically set via the dedicated API endpoint from the Chart of Goodness UI, not directly edited in the form.
- The `duration_hours` field has a default value of 3 (defined in the Events model metadata), so it is pre-populated in the create form but can be overridden by the admin.
- Event_Proposed_Dates and Event_Reminders are accessible both as standalone pages (via their model routes) and as inline child sections on the Events edit page. The inline approach is the primary admin workflow.
