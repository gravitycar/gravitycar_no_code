# Codebase Summary: Projects Model

## Tech Stack

- **Backend**: PHP 8.2+, Gravitycar Framework, Doctrine DBAL, Monolog
- **Frontend**: React (Vite), TypeScript, Tailwind CSS (no Shadcn/Radix), React Router DOM
- **Database**: MySQL 8.0+
- **API**: REST, route-driven, PHP backend serving JSON
- **Auth**: JWT + Google OAuth; `useAuth()` hook on frontend

---

## Architecture Overview

Gravitycar is a metadata-driven full-stack framework. The backend defines models as:
1. A **PHP metadata file** (`src/Models/{modelname}/{model}_metadata.php`) — drives everything: fields, validation, RBAC, UI hints, relationships.
2. A **PHP model class** (`src/Models/{modelname}/{ModelName}.php`) extending `ModelBase` — adds domain logic.

The frontend is a React SPA with:
- `GenericCrudPage` handling all standard list/create/edit/delete via metadata
- Custom page components (e.g. TriviaGamePage) for specialized UIs
- Navigation served dynamically from the backend based on the user's role

---

## Directory Structure (Key Areas)

```
src/
  Fields/           # FieldBase subclasses — one per field type (Image, Text, Video, etc.)
  Factories/        # ModelFactory, FieldFactory, RelationshipFactory, ComponentGeneratorFactory
  Metadata/         # MetadataEngine — scans Fields/, Models/, Validation/ and caches definitions
  Models/           # One subdirectory per model
    books/          # Example: Books.php + books_metadata.php
    movies/         # Example: Movies.php + movies_metadata.php
    events/         # Example: Events.php + events_metadata.php + api/
    ModelBase.php   # Abstract base all model classes extend
  Navigation/       # navigation_config.php + NavigationConfig.php
  Services/         # AuthorizationService, NavigationBuilder, ReactComponentMapper, etc.
  Validation/       # ValidationRuleBase subclasses

gravitycar-frontend/src/
  App.tsx                          # All route definitions
  components/
    crud/GenericCrudPage.tsx       # The standard CRUD page for all models
    fields/FieldComponent.tsx      # Dynamic field renderer; maps react_component → React component
    fields/ImageUpload.tsx         # React component for Image fields (URL input + preview)
    fields/TextInput.tsx           # Template for new field components
    forms/ModelForm.tsx            # Form that renders all fields from metadata
    layout/Layout.tsx              # App shell with NavigationSidebar
    navigation/NavigationSidebar.tsx  # Sidebar nav; "Navigation" section (top) + "Data Management" section
    trivia/TriviaGamePage.tsx      # Example custom view: Movie Quote Trivia Game
    ui/Modal.tsx                   # Modal dialog used by GenericCrudPage
  pages/
    TriviaPage.tsx                 # Thin wrapper page for TriviaGamePage (custom view pattern)
  services/
    api.ts                         # Singleton ApiService with axios
    navigationService.ts           # Fetches nav data from backend
  types/index.ts                   # TypeScript interfaces (ModelMetadata, FieldMetadata, etc.)
```

---

## Relevant Existing Code

### 1. Model Definitions

**Pattern**: Every model needs exactly two files.

**Metadata file**: `src/Models/{modelname}/{model}_metadata.php`
- Keys: `name`, `table`, `displayColumns`, `fields`, `rolesAndActions`, `validationRules`, `relationships`, `apiRoutes`, `ui`
- The `fields` key is a map of field name → field config array including `type`, `label`, `required`, `maxLength`, `nullable`, `validationRules`, and type-specific properties
- The `ui` key controls: `listFields`, `createFields`, `editFields`, `viewUrl`, `relatedItemsSections`, custom `createButtons`/`editButtons`

**PHP class**: `src/Models/{modelname}/{ModelName}.php`
- Namespace: `Gravitycar\Models\{modelname}` (lowercase directory name)
- Extends `ModelBase`
- Constructor injects: `Logger, MetadataEngineInterface, FieldFactory, DatabaseConnectorInterface, RelationshipFactory, ModelFactory, CurrentUserProviderInterface`
- Domain methods call `$this->get('field')`, `$this->find($conditions)`, etc.
- For simple models with no special behavior, the class can be minimal (just the constructor and a few domain helpers)

**Example** (Books model — most similar structure to Projects):
- File: `src/Models/books/Books.php` + `src/Models/books/books_metadata.php`
- Has an `Image` type field (`cover_image_url`) with `allowRemote: true`, `allowLocal: false`
- RBAC not shown in books metadata (defaults to open)

**Example** (Movies model — RBAC + Image):
```php
'rolesAndActions' => [
    'admin' => ['*'],
    'user' => ['*'],
    'guest' => ['list', 'read'],
],
```

**Example** (Events model — guest read-only, full admin):
```php
'rolesAndActions' => [
    'admin' => ['*'],
    'user' => ['list', 'read'],
    'guest' => ['list', 'read'],
],
```

For **Projects** the spec says "All users (authenticated or not)" can read/list:
```php
'rolesAndActions' => [
    'admin' => ['*'],
    'user' => ['list', 'read'],
    'guest' => ['list', 'read'],
],
```
(Same as Events pattern — guests can list/read without authentication.)

### 2. Field Types

Field types are PHP classes in `src/Fields/` extending `FieldBase`. MetadataEngine auto-discovers them by scanning `src/Fields/*.php`. The field type name is derived from the class name by stripping `Field` (e.g. `ImageField` → type `Image`).

**Key properties on FieldBase**:
- `protected string $type` — the type string used in metadata
- `protected string $reactComponent` — the React component name used in FieldComponent.tsx
- `protected array $operators` — supported filter operators
- `protected bool $required`, `protected int $maxLength`, `protected string $placeholder`

**ImageField** (`src/Fields/ImageField.php`):
- Type: `Image`, reactComponent: `ImageUpload`
- Stores a URL/path string (max 500 chars)
- Properties: `allowLocal`, `allowRemote`, `width`, `height`, `altText`, `thumbnailWidth`, `thumbnailHeight`, `showThumbnail`, `thumbnailSize`
- Has `getThumbnailUrl()` that handles TMDB URL size replacement
- No upload functionality — the frontend `ImageUpload.tsx` accepts a URL string and displays a preview
- Current usage in projects: `cover_image_url` in Books, `poster_url` in Movies; both use `allowRemote: true, allowLocal: false` to accept URL-only input

**Adding a new LinkField** (for the `link` field type):
1. Create `src/Fields/LinkField.php` extending `FieldBase` with `$type = 'Link'` and `$reactComponent = 'LinkInput'`
2. Create `gravitycar-frontend/src/components/fields/LinkInput.tsx` (renders `<input type="url">` with preview; in readOnly shows as `<a href>` link)
3. Register in `FieldComponent.tsx` componentMap: `'LinkInput': LinkInput`
4. MetadataEngine auto-discovers it; GenericCrudPage needs a `case 'Link':` in `renderFieldValue()` to render as an anchor link

**TextField** (for reference — simplest field):
- `src/Fields/TextField.php`: type `Text`, reactComponent `TextInput`, maxLength 255, text operators
- `src/components/fields/TextInput.tsx`: `<input type="text">` with label, error, readOnly states

### 3. GenericCRUD UI

**File**: `gravitycar-frontend/src/components/crud/GenericCrudPage.tsx`

**How it works**:
1. Accepts `modelName`, `title`, `description` props
2. Fetches model metadata via `useModelMetadata(modelName)` hook
3. Reads `metadata.rolesAndActions` to show/hide Create/Edit/Delete buttons based on user role
4. Renders a table using `metadata.ui.listFields` array for columns
5. `renderFieldValue()` switches on `fieldMeta.type` to render Image, Boolean, DateTime, Email, Video, Text etc. — **this switch needs a new case for `Link` type**
6. Create/Edit open a `<Modal>` containing `<ModelForm>` — ModelForm renders all `createFields`/`editFields` from metadata using `FieldComponent`
7. `FieldComponent.tsx` maps `field.react_component` → React component via `componentMap`
8. To use GenericCrudPage for Projects (admin CRUD), add route in `App.tsx`:
   ```tsx
   <Route path="/:modelName" element={<ProtectedRoute><Layout><DynamicModelRoute /></Layout></ProtectedRoute>} />
   ```
   The dynamic model route already handles any model including Projects. No special route needed.

**Key metadata-to-UI mapping**:
- `metadata.ui.listFields` → table columns
- `metadata.ui.createFields` → fields shown in Create modal
- `metadata.ui.editFields` → fields shown in Edit modal
- `metadata.ui.viewUrl` → "View" button link (optional; replaces `{id}` in template)
- `metadata.rolesAndActions` → which actions are available per role

### 4. Custom Views (Movie Quote Trivia Game Pattern)

The Trivia Game is the reference custom view. Structure:

**Page file** (`src/pages/TriviaPage.tsx`): Thin wrapper that renders the main component inside `<div className="min-h-screen bg-gray-50">`. Used by App.tsx routing.

**Component directory** (`src/components/trivia/`): Contains all sub-components:
- `TriviaGamePage.tsx` — main stateful component managing game phases
- Sub-components: `TriviaGameBoard`, `TriviaGameComplete`, `TriviaHighScores`, `TriviaQuestion`, `TriviaAnswerOption`, `TriviaScoreDisplay`
- `index.ts` — barrel export
- `types.ts` — TypeScript interfaces

**Route in App.tsx**:
```tsx
<Route
  path="/trivia"
  element={
    <ProtectedRoute>
      <Layout>
        <TriviaPage />
      </Layout>
    </ProtectedRoute>
  }
/>
```

**For Projects custom view**: The spec requires a public (no auth) custom list view. Pattern:
```tsx
<Route
  path="/projects"
  element={
    <Layout>
      <ProjectsPage />
    </Layout>
  }
/>
```
(No `<ProtectedRoute>` wrapper, matching the Events pattern.)

The detail view can be managed with state inside `ProjectsPage` (open/close modal) rather than a separate route, since it "opens over" the list view.

### 5. Navigation Bar

**Backend config**: `src/Navigation/navigation_config.php`

Custom pages appear in the "Navigation" top section (rendered as `custom_pages`). The sidebar has two sections:
1. **"Navigation"** — custom_pages from the config (shown in source order, filtered by role)
2. **"Data Management"** — model-driven links (auto-generated from metadata, alphabetically sorted)

**Adding a nav link for Projects custom view**:
```php
// In src/Navigation/navigation_config.php, add to custom_pages array:
[
    'key' => 'projects',
    'title' => 'Projects',
    'url' => '/projects',
    'icon' => '🚀',
    'roles' => ['*']  // All roles including guest
],
```

The `roles => ['*']` means all users (including unauthenticated guests) see this link.

**Frontend**: `NavigationSidebar.tsx` fetches nav data from backend and renders it. The `groupCustomPages()` utility handles parent/child grouping for items with underscores (e.g. `events`, `events_create`, `events_list` form a group). The Projects entry uses a simple key `'projects'` so it renders as a standalone link.

### 6. RBAC Configuration

**How it works**:
- `AuthorizationService` checks `(action, component/modelName)` against `roles_permissions` DB table
- `metadata.rolesAndActions` is the configuration hint — determines which permissions are seeded per role
- Frontend `GenericCrudPage` also reads `metadata.rolesAndActions` directly to show/hide UI elements
- Actions: `create`, `read`, `update`, `delete`, `list`, `restore`, or `*` (wildcard = all)

**For Projects**:
```php
'rolesAndActions' => [
    'admin' => ['*'],              // Full CRUD
    'user' => ['list', 'read'],    // Read-only
    'guest' => ['list', 'read'],   // Read-only (unauthenticated)
],
```

The custom Projects List View and Detail View should be accessible without authentication at all, so the React route should not use `<ProtectedRoute>`.

### 7. ImageField + Screenshot field

The `Screenshot` field for Projects should use `type: Image` (same as `poster_url` in Movies and `cover_image_url` in Books).

Current behavior: `ImageUpload.tsx` component accepts:
- A URL string entered manually by the user
- Optionally a local file (when `allowLocal: true`), but this only stores the filename — no server upload

**Recommended approach** (as stated in the spec): Set `allowRemote: true, allowLocal: false`. The image URL is set manually by an admin who uploads images to the web server and then enters the absolute URL. This is confirmed by the existing ImageField pattern in Books and Movies.

Metadata for the Screenshot field:
```php
'screenshot' => [
    'type' => 'Image',
    'name' => 'screenshot',
    'label' => 'Screenshot',
    'required' => true,
    'allowRemote' => true,
    'allowLocal' => false,
    'maxLength' => 500,
    'altText' => 'Project screenshot',
],
```

### 8. New LinkField

The `link` field (URL to the project) requires a new field type. The framework has `VideoField` as a comparable URL-type field. A `LinkField` would be simpler:

**Backend** (`src/Fields/LinkField.php`):
```php
class LinkField extends FieldBase {
    protected string $type = 'Link';
    protected string $reactComponent = 'LinkInput';
    protected int $maxLength = 256;
    protected string $placeholder = 'Enter URL (https://...)';
    protected array $operators = ['equals', 'notEquals', 'isNull', 'isNotNull'];
}
```

**Frontend** (`src/components/fields/LinkInput.tsx`):
- Edit mode: `<input type="url">` with label and error
- ReadOnly mode: renders as `<a href={value} target="_blank">{value}</a>` (clickable link)

**Register in FieldComponent.tsx**:
```ts
import LinkInput from './LinkInput';
// In componentMap:
'LinkInput': LinkInput,
```

**Register in GenericCrudPage.tsx** `renderFieldValue()`:
```ts
case 'Link':
  return (
    <a href={stringValue} target="_blank" rel="noopener noreferrer"
       className="text-blue-600 hover:text-blue-800 break-all">
      {stringValue}
    </a>
  );
```

MetadataEngine auto-discovers `LinkField.php` by scanning `src/Fields/` — no manual registration needed.

---

## Conventions to Follow

1. **Model directory name**: lowercase, no spaces (e.g. `projects`)
2. **PHP class name**: PascalCase matching directory (e.g. `Projects`)
3. **Namespace**: `Gravitycar\Models\projects`
4. **Metadata file name**: `{modelname}_metadata.php` (e.g. `projects_metadata.php`)
5. **Field names in metadata**: snake_case (e.g. `tag_line`, `screenshot`)
6. **React page**: `src/pages/ProjectsPage.tsx` (thin wrapper)
7. **React component directory**: `src/components/projects/` with `ProjectsListView.tsx`, `ProjectDetailModal.tsx`, `index.ts`
8. **Tailwind CSS only** — no Shadcn/Radix UI libraries
9. **No ProtectedRoute** for public pages (Projects custom view is public per spec)
10. **No external file upload** — ImageField stores URL strings only

---

## Reusable Components

- `Modal.tsx` (`src/components/ui/Modal.tsx`) — for the Project Detail View overlay
- `useAuth()` hook — to check role for showing admin edit buttons
- `apiService.getList('Projects', ...)` — to fetch projects from backend
- `Layout.tsx` + `NavigationSidebar.tsx` — wrap all pages
- `ErrorBoundary`, `DataWrapper` — for loading/error states
- `useModelMetadata(modelName)` — fetch metadata for a model (useful if ProjectsListView needs field metadata)
