# Implementation Plan: Event_Invitations ManyToMany Relationship

## Spec Context

The Event_Invitations relationship is a ManyToMany relationship between Events and Users that tracks which users are invited to which events. It includes two additional fields on the join table: `invited_at` (when the invitation was created) and `invited_by` (which user sent the invitation). This relationship governs who can see and interact with an event -- it is used by the Chart of Goodness endpoint, commitments logic, and reminder emails to determine the set of invited users.

- **Catalog item**: 5 - Event_Invitations Relationship (ManyToMany)
- **Specification section**: Models section -- Event_Invitations, Relationships section
- **Acceptance criteria addressed**: AC-3 (admin can invite users to an event, and those users see the event in their event list)

## Dependencies

- **Blocked by**: Item 3 (Events Model) -- the Events model and its metadata must exist first since this relationship references it as modelA.
- **Uses**: `src/Relationships/ManyToManyRelationship.php` (relationship class -- no custom subclass needed), `src/Relationships/users_roles/users_roles_metadata.php` (pattern reference for ManyToMany with additionalFields), existing Users model.

## File Changes

### New Files

- `src/Relationships/events_users_invitations/events_users_invitations_metadata.php` -- Metadata definition for the ManyToMany relationship between Events and Users with invitation-specific additional fields.
- `tests/Relationships/EventsUsersInvitationsTest.php` -- Unit tests for the relationship metadata and behavior.

### Modified Files

None. The Events metadata already lists `events_users_invitations` in its `relationships` array (defined in plan-03). The Users model does not need modification -- the framework discovers relationships via MetadataEngine scanning of the Relationships directory.

## Implementation Details

### 1. Relationship Metadata

**File**: `src/Relationships/events_users_invitations/events_users_invitations_metadata.php`

This file returns a PHP array defining the ManyToMany relationship. Follow the exact structure of `src/Relationships/users_roles/users_roles_metadata.php`.

```php
<?php
// Event_Invitations: ManyToMany relationship between Events and Users
// Tracks which users are invited to which events, with invitation metadata.
return [
    'name' => 'events_users_invitations',
    'type' => 'ManyToMany',
    'modelA' => 'Events',
    'modelB' => 'Users',
    'constraints' => [],
    'additionalFields' => [
        'invited_at' => [
            'name' => 'invited_at',
            'type' => 'DateTime',
            'label' => 'Invited At',
            'required' => true,
            'validationRules' => ['DateTime'],
        ],
        'invited_by' => [
            'name' => 'invited_by',
            'type' => 'ID',
            'label' => 'Invited By User ID',
            'required' => true,
            'validationRules' => [],
        ],
    ],
];
```

**Key decisions:**

- **`modelA` = Events, `modelB` = Users**: This follows the naming convention where the first model in the relationship name (`events_users_invitations`) is modelA. The `events_` prefix and `_invitations` suffix distinguish this from a generic events-users join.
- **`invited_at` uses type `DateTime`**: Stores the UTC timestamp of when the invitation was created. Marked `required: true` per the spec. Includes the `DateTime` validation rule for format enforcement.
- **`invited_by` uses type `ID`**: Stores the UUID of the user who sent the invitation. Uses the `ID` field type (same pattern as `assigned_by` in `users_roles`). Marked `required: true` per the spec. This is a UUID reference to the Users table but is NOT a `RelatedRecord` field -- using `ID` is the established pattern for "by" fields on join tables (see `users_roles.assigned_by`, `users_permissions.granted_by`).
- **No `constraints` needed**: The framework's ManyToMany base class already handles the composite uniqueness of (modelA_id, modelB_id) on the join table. No additional constraints are required.
- **No `rolesAndActions` in metadata**: Relationship metadata files do not include `rolesAndActions` -- access control is handled at the model level. Per the spec, invitation management is admin-only (Events model's rolesAndActions restrict create/update/delete to admin), and users can see their own invitations through the Events list/read actions.

### 2. Database Table

The `SchemaGenerator` will automatically create the join table `events_users_invitations` with:
- `id` (UUID, PK) -- from core fields
- `events_id` (UUID, FK to events.id) -- auto-generated from modelA
- `users_id` (UUID, FK to users.id) -- auto-generated from modelB
- `invited_at` (DATETIME) -- from additionalFields
- `invited_by` (CHAR(36)) -- from additionalFields, ID type
- `created_at`, `updated_at`, `deleted_at`, `created_by`, `updated_by`, `deleted_by` -- from core fields

No manual SQL or schema changes needed.

### 3. No Custom PHP Class Needed

Unlike models which require a custom PHP class extending ModelBase, ManyToMany relationships use the framework's built-in `ManyToManyRelationship` class directly. The `RelationshipFactory` instantiates `ManyToManyRelationship` using the metadata file. No custom subclass is needed because:
- The relationship has no computed properties or custom methods.
- All behavior (add, remove, getRelatedWithData, bulk operations) is handled by `ManyToManyRelationship`.
- The `additionalFields` are handled generically by the framework.

## Error Handling

- If an invitation is created without `invited_at` or `invited_by`, the framework's validation layer will reject it (both fields are `required: true`).
- If an invalid UUID is passed for `invited_by`, the `ID` field type validation will catch it.
- Duplicate invitations (same event + same user) are prevented by the ManyToMany base class's composite key handling.
- Attempting to invite a non-existent user or reference a non-existent event will fail at the database FK constraint level, and the framework will surface a GCException.

## Unit Test Specifications

**File**: `tests/Relationships/EventsUsersInvitationsTest.php`

**Namespace**: `Gravitycar\Tests\Relationships`

Tests will validate the metadata structure and relationship behavior using mocked dependencies.

### Metadata Structure Tests

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Name is correct | Load metadata | `name` = `events_users_invitations` | Matches naming convention |
| Type is ManyToMany | Load metadata | `type` = `ManyToMany` | Spec says ManyToMany |
| modelA is Events | Load metadata | `modelA` = `Events` | Events is the "owning" side |
| modelB is Users | Load metadata | `modelB` = `Users` | Users is the "invited" side |
| invited_at field exists | Load metadata | `additionalFields` has `invited_at` key | Required by spec |
| invited_at is DateTime | Load metadata | `invited_at.type` = `DateTime` | Timestamp field |
| invited_at is required | Load metadata | `invited_at.required` = `true` | Spec says required |
| invited_by field exists | Load metadata | `additionalFields` has `invited_by` key | Required by spec |
| invited_by is ID type | Load metadata | `invited_by.type` = `ID` | UUID reference to Users |
| invited_by is required | Load metadata | `invited_by.required` = `true` | Spec says required |

### Relationship Behavior Tests

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Add invitation | Create Event + User, add relation with invited_at and invited_by | Relation record created with all fields | Happy path |
| Get invited users for event | Event with 2 invitations | Returns 2 records with additional fields | Verify getRelatedWithData returns invited_at and invited_by |
| Get events for user | User invited to 2 events | Returns 2 records | Verify bidirectional query |
| Duplicate invitation rejected | Same event + same user added twice | Second add fails or is idempotent | Composite uniqueness |
| Soft delete invitation | Remove an invitation | Record has deleted_at set, not hard deleted | Framework soft-delete pattern |

### Key Scenario: Add Invitation with Additional Fields

**Setup**: 
- Mock DatabaseConnector to capture insert calls.
- Create a ManyToManyRelationship instance loaded with the `events_users_invitations` metadata.
- Create mock Event model (with ID `event-uuid-1`) and mock User model (with ID `user-uuid-1`).

**Action**: Call `add($eventModel, $userModel, ['invited_at' => '2026-04-01 12:00:00', 'invited_by' => 'admin-uuid-1'])`.

**Expected**: 
- DatabaseConnector's insert method is called once.
- The inserted record includes `events_id` = `event-uuid-1`, `users_id` = `user-uuid-1`, `invited_at` = `2026-04-01 12:00:00`, `invited_by` = `admin-uuid-1`.

### Key Scenario: Query Invited Users with Data

**Setup**:
- Mock DatabaseConnector to return 2 records with invited_at and invited_by populated.
- Load relationship with metadata.

**Action**: Call `getRelatedWithData($eventModel)`.

**Expected**: Returns array of 2 records, each containing `users_id`, `invited_at`, and `invited_by` fields.

### Test Helper Setup

```php
private function loadMetadata(): array
{
    return require __DIR__ . '/../../src/Relationships/events_users_invitations/events_users_invitations_metadata.php';
}

private function createRelationshipInstance(): ManyToManyRelationship
{
    $logger = new Logger('test');
    $metadataEngine = $this->createMock(MetadataEngineInterface::class);
    $coreFieldsMetadata = $this->createMock(CoreFieldsMetadata::class);
    $modelFactory = $this->createMock(ModelFactory::class);
    $databaseConnector = $this->createMock(DatabaseConnectorInterface::class);

    $metadataEngine->method('getRelationshipMetadata')
        ->with('events_users_invitations')
        ->willReturn($this->loadMetadata());

    return new ManyToManyRelationship(
        'events_users_invitations',
        $logger,
        $metadataEngine,
        $coreFieldsMetadata,
        $modelFactory,
        $databaseConnector
    );
}
```

## Notes

- The `events_users_invitations` relationship name is already referenced in the Events metadata `relationships` array (from plan-03). The framework resolves relationship references lazily, so the Events model can be built before this relationship metadata file exists.
- The `invited_by` field stores a raw UUID (ID type) rather than using RelatedRecord. This matches the established pattern in `users_roles` (`assigned_by`) and `users_permissions` (`granted_by`). Using RelatedRecord would add unnecessary overhead since the join table does not need full foreign-key-aware field behavior -- it just needs to store who performed the action.
- The framework's `SchemaGenerator` will create the database table automatically from this metadata. No migration scripts are needed.
- This relationship is used by catalog items 6 (Event_Commitments), 9 (Chart API), 10 (Commitments API), 14 (Email Reminders), and 17 (Event Admin Pages) to determine the set of invited users for an event.
