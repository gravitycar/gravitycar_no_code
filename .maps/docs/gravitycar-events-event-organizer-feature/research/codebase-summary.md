# Codebase Summary: Event Organizer (Chart of Goodness) Feature

## Tech Stack

**New Gravitycar Framework:**
- Frontend: React (Vite + TypeScript), Shadcn UI, React Router
- Backend: PHP 8.2+, Composer, Doctrine DBAL
- Database: MySQL 8.0+ with UUID primary keys
- Testing: PHPUnit
- Logging: Monolog
- Auth: JWT + Google OAuth, role-based (admin/manager/user/guest)

**Old Gravitycar Codebase:**
- PHP (procedural/class-based, no framework)
- MySQL with auto-increment integer IDs
- Server-side HTML rendering
- Simple password-based auth with permission weights

---

## Part 1: New Gravitycar Framework Architecture

### Directory Structure
```
src/
  Api/              - REST API controllers, router, request handling
  ComponentGenerator/ - React component generators per field type
  Contracts/        - Interfaces (DatabaseConnectorInterface, MetadataEngineInterface, etc.)
  Core/             - Config, ServiceLocator, Gravitycar bootstrap
  Database/         - DatabaseConnector (Doctrine DBAL)
  Exceptions/       - GCException base + specific exception types
  Factories/        - ModelFactory, FieldFactory, RelationshipFactory, etc.
  Fields/           - 17 field types (Text, Integer, Boolean, Date, Email, Enum, etc.)
  Metadata/         - MetadataEngine, CoreFieldsMetadata, templates
  Models/           - Model classes + metadata files
  Navigation/       - NavigationConfig + navigation_config.php
  Relationships/    - RelationshipBase + specific relationship types + metadata
  Schema/           - SchemaGenerator
  Services/         - AuthenticationService, AuthorizationService, etc.
  Validation/       - ValidationRuleBase + specific rules
gravitycar-frontend/
  src/components/   - crud/, navigation/, fields/, forms/, auth/, etc.
  src/pages/        - Dashboard, model-specific pages
  src/services/     - api.ts, navigationService.ts
  src/types/        - TypeScript interfaces
  src/hooks/        - useAuth, useModelMetadata
```

### Model/Field Metadata System

**Model metadata** lives at `src/Models/{modelname}/{modelname}_metadata.php` and returns a PHP array:
```php
return [
    'name' => 'Movies',
    'table' => 'movies',
    'displayColumns' => ['name', 'release_year'],
    'fields' => [
        'name' => ['name'=>'name', 'type'=>'Text', 'label'=>'Title', 'required'=>true, ...],
        // ...
    ],
    'rolesAndActions' => [
        'admin' => ['*'],
        'user' => ['list', 'read', 'create', 'update', 'delete'],
        'guest' => ['list', 'read'],
    ],
    'relationships' => ['movies_movie_quotes'],
    'ui' => [
        'listFields' => [...],
        'createFields' => [...],
        'editFields' => [...],
        'relatedItemsSections' => [...],
    ],
];
```

**Core fields** are automatically added to every model via `CoreFieldsMetadata`:
- `id` (UUID), `created_at`, `updated_at`, `deleted_at`, `created_by`, `updated_by`, `deleted_by` (+ display name variants)

**Model classes** extend `ModelBase` and live at `src/Models/{modelname}/{ModelName}.php`:
- Namespace: `Gravitycar\Models\{modelname}`
- Constructor receives 7 DI params: Logger, MetadataEngine, FieldFactory, DatabaseConnector, RelationshipFactory, ModelFactory, CurrentUserProvider
- ModelBase handles: metadata loading, field initialization, validation, CRUD ops, soft delete

### Available Field Types (17 total)
BigTextField, BooleanField, DateField, DateTimeField, EmailField, EnumField, FieldBase (abstract), FloatField, IDField, ImageField, IntegerField, MultiEnumField, PasswordField, RadioButtonSetField, RelatedRecordField, TextField, VideoField

Each field has a `$reactComponent` property telling the frontend which React component to render.

### Relationship System

**Relationship types:** OneToOne, OneToMany, ManyToMany (all extend RelationshipBase which extends ModelBase)

**Relationship metadata** at `src/Relationships/{rel_name}/{rel_name}_metadata.php`:
```php
return [
    'name' => 'movies_movie_quotes',
    'type' => 'OneToMany',
    'modelOne' => 'Movies',
    'modelMany' => 'Movie_Quotes',
    'constraints' => [],
    'additionalFields' => [],
];
```

ManyToMany relationships can have `additionalFields` (e.g., users_roles has `assigned_at`, `assigned_by`).

### API Endpoint Patterns

- `RestApiHandler` bootstraps the app and delegates to `Router`
- `Router` uses `APIRouteRegistry` (auto-discovers routes from model directories) and `APIPathScorer` for matching
- Generic CRUD endpoints are auto-generated per model (e.g., GET/POST/PUT/DELETE `/api/{modelname}`)
- Custom API controllers extend `ApiControllerBase` and live in `src/Models/{modelname}/api/Api/`
- Auth: JWT tokens, role-based access via `rolesAndActions` on models and controllers

### Navigation System

- Backend: `NavigationConfig` loads `src/Navigation/navigation_config.php`
- Config has `custom_pages` (with role restrictions) and `navigation_sections`
- Frontend: `NavigationSidebar` fetches `/api/navigation`, renders model CRUD links + custom pages
- Custom pages need: key, title, url, icon, roles array

### Frontend Routing Pattern

- `App.tsx` defines specific routes (dashboard, trivia, dnd-chat) and a catch-all `/:modelName` route
- `DynamicModelRoute` resolves model names and renders `GenericCrudPage`
- `GenericCrudPage` uses `useModelMetadata` hook to fetch metadata, then renders list/create/edit views
- Custom pages (like trivia, dnd-chat) get their own dedicated route and page component

### Existing Models
- Users, Movies, Movie_Quotes, Movie_Quote_Trivia_Games, Movie_Quote_Trivia_Questions, Books, Roles, Permissions, JwtRefreshTokens, GoogleOauthTokens, Installer

### Existing Relationships
- movies_movie_quotes (OneToMany)
- movie_quote_trivia_games_movie_quote_trivia_questions (likely ManyToMany)
- users_roles, users_permissions, users_jwt_refresh_tokens, users_google_oauth_tokens, roles_permissions

---

## Part 2: Old Gravitycar - COG (Chart of Goodness) Event Organizer

### Database Tables (inferred from code - no CREATE TABLE statements found)

**gcCOGSocialEvents** - The main events table
- `id` (int, auto-increment, PK)
- `eventName` (varchar/text)
- `eventDescription` (text)
- `creator_id` (int, FK to gcUsers.id)

**gcCOGInvitations** - Which users are invited to which events
- `event_id` (int, FK to gcCOGSocialEvents.id)
- `guest_id` (int, FK to gcUsers.id)
- (composite key: event_id + guest_id)

**gcCOGProposedDates** - Proposed date/time options for an event
- `id` (int, auto-increment, PK)
- `event_id` (int, FK to gcCOGSocialEvents.id)
- `proposedDate` (datetime)

**gcCOGCommitments** - Which dates each guest can attend
- `event_id` (int, FK to gcCOGSocialEvents.id)
- `guest_id` (int, FK to gcUsers.id)
- `proposedDate_id` (int, FK to gcCOGProposedDates.id)

**gcCOGSocialEventNotes** - Comments/notes guests leave on events
- `id` (int, auto-increment, PK)
- `event_id` (int, FK to gcCOGSocialEvents.id)
- `guest_id` (int, FK to gcUsers.id)
- `note` (text)
- `dateEntered` (datetime)

### Old Feature Capabilities

1. **Event Creation** (admin only): Create/edit/delete social events with name + description
2. **Event Login**: Combined login + event selection page; users log in and pick which event to view
3. **Invitation Management** (admin only): Select users from full user list to invite to an event; replace-all pattern (delete old invitations, insert new)
4. **Propose Dates** (admin only): Add/delete proposed date+time options for an event using calendar + hour picker; dates stored as MySQL datetime
5. **The Chart of Goodness** (main feature): A grid/table showing:
   - Header row: proposed dates formatted as "Day\nMonth Day\nTime"
   - One row per invited guest
   - Each cell: checkbox if current user's row, empty otherwise
   - Green ("canMakeIt") or red ("cannotMakeIt") CSS classes based on commitment status
   - "Most Popular Meeting Time" calculation displayed above the chart
   - Notes section for "witty remarks"
   - Save button to update commitments (delete-all + re-insert pattern)
6. **Notes/Comments**: Guests can leave notes on events; displayed with speaker name + date
7. **Navigation between dates**: Prev/Next scrolling when many dates proposed

### Permission Model (old)
- `GC_AUTH_USER`: Can view chart, leave notes, toggle their own checkboxes
- `GC_AUTH_ADMIN`: Can create events, manage invitations, propose dates

### Key Behavioral Notes
- Only invited users can view an event's chart
- Users can only toggle their OWN checkboxes (other users' rows show status but no controls)
- Commitments use delete-all-then-reinsert pattern per user per event
- Invitations also use delete-all-then-reinsert pattern
- "Most popular date" algorithm counts commitments per date, returns the date with most
- Notes have full CRUD but are tied to event_id + guest_id

---

## Part 3: Mapping Old Feature to New Framework

### Models Needed (new framework)
1. **Events** - name, description, creator (RelatedRecord to Users)
2. **EventProposedDates** - event_id (RelatedRecord), proposed_date (DateTime)
3. **EventInvitations** - event_id + guest_id (ManyToMany relationship between Events and Users, or could be a model)
4. **EventCommitments** - event_id + guest_id + proposed_date_id (ternary relationship)
5. **EventNotes** - event_id, guest_id, note, date_entered

### Relationships Needed
- Events -> EventProposedDates (OneToMany)
- Events <-> Users via EventInvitations (ManyToMany with additional fields possible)
- Events -> EventNotes (OneToMany)
- EventCommitments links Events, Users, and EventProposedDates (ternary - may need special handling)

### Custom Components Needed
- **Chart of Goodness view**: A custom React page (not GenericCrudPage) showing the grid of dates vs guests with checkboxes
- **Event Dashboard/Landing**: Event selection + chart view
- **Date Proposal UI**: Calendar/datetime picker for proposing dates

### Navigation Integration
- Add "Event Organizer" to `navigation_config.php` custom_pages
- Add route in `App.tsx` for the custom event page
- Standard CRUD for Events model will be handled by GenericCrudPage

### Authorization Considerations
- Event creators (or admins) can manage invitations and proposed dates
- Only invited users can view an event's chart
- Users can only modify their own commitment checkboxes
- This may require custom authorization logic beyond simple role-based `rolesAndActions`

---

## Conventions to Follow

1. **Model file structure**: `src/Models/{modelname}/{ModelName}.php` + `{modelname}_metadata.php`
2. **Relationship structure**: `src/Relationships/{rel_name}/{rel_name}_metadata.php`
3. **Namespace**: `Gravitycar\Models\{modelname}` for models
4. **Constructor DI**: All 7 standard params for ModelBase, call parent::__construct()
5. **Metadata format**: PHP array with name, table, displayColumns, fields, rolesAndActions, relationships, ui
6. **Frontend custom pages**: Dedicated route in App.tsx + page component + navigation_config.php entry
7. **API controllers**: Extend ApiControllerBase, placed in model's api/Api/ directory

## Reusable Components
- `GenericCrudPage.tsx` - For standard CRUD views of Events, EventNotes models
- `ModelForm.tsx` - For create/edit forms
- `useModelMetadata` hook - For fetching model metadata in custom components
- `apiService` - For all API calls
- `useAuth` hook - For current user context
- `NavigationSidebar` - Already supports custom pages
- `RelationshipBase` / `ManyToManyRelationship` - For event-user invitation relationship
- `SchemaGenerator` - For creating database tables from metadata
