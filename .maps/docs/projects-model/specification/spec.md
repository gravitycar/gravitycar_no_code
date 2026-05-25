# Specification: Projects Model

**Epic ID**: 5  
**Status**: Revised (post Critic Review #1)  
**Author**: Architect Agent  
**Date**: 2026-05-21

---

## Overview

Add a "Projects" showcase feature to the Gravitycar Framework that allows an admin to manage a portfolio of projects and allows all users (including unauthenticated visitors) to browse and view project details. The feature introduces a new `Projects` model, a new `Link` field type, a public-facing custom list view with image-tile grid layout, and a custom detail modal view.

---

## Goals

- Allow admin users to create, read, update, and delete Projects records via the existing GenericCRUD admin interface.
- Allow all users (authenticated or not) to browse a visually engaging 2-tile-wide image grid of projects.
- Allow all users to open a detailed view of any project in a modal overlay.
- Introduce a reusable `Link` field type (URL input + display) to the framework that any future model can use.
- Expose a Projects navigation link in the top navigation section for all roles including guests.

## Non-Goals

- File upload support for screenshots. Images are referenced by URL only; admins upload files to the web server manually and enter the absolute URL.
- A dedicated frontend page route for individual project detail views — the detail is a modal overlay on `/projects_showcase`, not a separate page. Note: the backend API route `/Projects/{id}` is provided automatically by the framework and is used by the detail modal to fetch individual project data.
- Pagination or filtering on the public Projects list view (out of scope for initial release).
- Any social or collaborative features (comments, likes, sharing).

---

## Explicit Constraints (DO NOT)

- Do NOT implement server-side file upload for the Screenshot field. The `Image` field type stores a URL string only.
- Do NOT require authentication for the custom Projects list or detail views.
- Do NOT add a new custom API controller for Projects — use the existing generic API endpoints.
- Do NOT use any external UI component library (Shadcn, Radix, etc.) — Tailwind CSS only.
- Do NOT wrap the public Projects route in `<ProtectedRoute>`.
- Do NOT use `<a href="">` (empty href) when the Link field is not set — conditionally omit the `<a>` wrapper entirely.
- Do NOT render `javascript:` or `data:` URI schemes in the Link field — validate scheme as `http` or `https` only.

---

## Technical Architecture

### Backend

The backend follows the established two-file model pattern:

1. **Metadata file** — `src/Models/projects/projects_metadata.php`: drives fields, RBAC, validation, UI hints, and API route registration.
2. **PHP model class** — `src/Models/projects/Projects.php`: extends `ModelBase`, namespace `Gravitycar\Models\projects`, implements any domain logic beyond CRUD.

A new field type class, `src/Fields/LinkField.php`, extends `FieldBase` and is auto-discovered by `MetadataEngine` by scanning `src/Fields/`.

The generic REST API (`/api/{modelName}`) handles all Projects CRUD operations. No custom controller is needed.

### Frontend

The frontend adds:

1. **`src/Fields/LinkField.php`** — new PHP field class (backend).
2. **`gravitycar-frontend/src/components/fields/LinkInput.tsx`** — React component for the Link field type.
3. **`gravitycar-frontend/src/pages/ProjectsPage.tsx`** — thin page wrapper, public route.
4. **`gravitycar-frontend/src/components/projects/ProjectsListView.tsx`** — main list view with 2-tile image grid.
5. **`gravitycar-frontend/src/components/projects/ProjectDetailModal.tsx`** — detail overlay modal.
6. **`gravitycar-frontend/src/components/projects/index.ts`** — barrel export.

Modifications to existing files:

- `gravitycar-frontend/src/App.tsx` — add public `/projects_showcase` route.
- `gravitycar-frontend/src/components/fields/FieldComponent.tsx` — register `LinkInput` in `componentMap`.
- `gravitycar-frontend/src/components/crud/GenericCrudPage.tsx` — add `Link` case to `renderFieldValue()`.
- `src/Navigation/navigation_config.php` — add Projects entry to `custom_pages`.

### Database

A new `projects` table is added to the MySQL database, following the framework's standard schema conventions.

---

## Feature Specifications

### 1. Database Schema

The `projects` table SHALL have the following columns:

| Column       | Type             | Nullable | Notes                            |
|--------------|------------------|----------|----------------------------------|
| `id`         | INT UNSIGNED AUTO_INCREMENT | No | Primary key               |
| `title`      | VARCHAR(256)     | No       |                                  |
| `tag_line`   | VARCHAR(1024)    | No       |                                  |
| `description`| TEXT (up to 16000 chars) | No  | Stored as BigText field type     |
| `screenshot` | VARCHAR(500)     | No       | URL string to image              |
| `link`       | VARCHAR(256)     | Yes      | URL string; optional             |
| `created_at` | DATETIME         | No       |                                  |
| `updated_at` | DATETIME         | Yes      |                                  |
| `deleted_at` | DATETIME         | Yes      | Soft delete                      |
| `created_by` | INT UNSIGNED     | No       | FK to users                      |
| `updated_by` | INT UNSIGNED     | Yes      | FK to users                      |
| `deleted_by` | INT UNSIGNED     | Yes      | FK to users                      |

The `projects` table SHALL be created automatically at runtime by `SchemaGenerator`, which reads the model metadata and uses Doctrine DBAL to create the table. No manual SQL migration scripts or separate installer steps are required.

### 2. Projects Metadata File

The metadata file at `src/Models/projects/projects_metadata.php` SHALL define:

**Model identity:**
- `name`: `'Projects'`
- `table`: `'projects'`
- `displayColumns`: `['title']`

**Fields** (in addition to the framework's core/audit fields):

| Field key     | Type    | Label        | Required | Max Length | Notes                                |
|---------------|---------|--------------|----------|------------|--------------------------------------|
| `title`       | Text    | Title        | Yes      | 256        |                                      |
| `tag_line`    | Text    | Tag Line     | Yes      | 1024       |                                      |
| `description` | BigText | Description  | Yes      | 16000      | Renders `TextArea` React component   |
| `screenshot`  | Image   | Screenshot   | Yes      | 500        | `allowRemote: true`, `allowLocal: false`, `altText: 'Project screenshot'` |
| `link`        | Link    | Link         | No       | 256        | Nullable; must be valid http/https URL if provided |

**RBAC** (`rolesAndActions`):
- `admin`: all actions (`*`)
- `user`: `list`, `read`
- `guest`: `list`, `read`

**UI configuration** (`ui` key):
- `listFields`: `['title', 'tag_line', 'screenshot', 'link']`
- `createFields`: `['title', 'tag_line', 'description', 'screenshot', 'link']`
- `editFields`: `['title', 'tag_line', 'description', 'screenshot', 'link']`

**Validation rules:**
- `title`: required, max length 256
- `tag_line`: required, max length 1024
- `description`: required, max length 16000 (BigText type)
- `screenshot`: required, valid URL (Image type handles this)
- `link`: optional; if provided, must be a valid URL with http or https scheme

### 3. Projects PHP Model Class

The class at `src/Models/projects/Projects.php` SHALL:

- Use namespace `Gravitycar\Models\projects`
- Extend `ModelBase`
- Inject all standard constructor dependencies: `Logger`, `MetadataEngineInterface`, `FieldFactory`, `DatabaseConnectorInterface`, `RelationshipFactory`, `ModelFactory`, `CurrentUserProviderInterface`
- Not require any domain-specific methods beyond what `ModelBase` provides (Projects is a simple CRUD model)
- Follow all CLAUDE.md coding standards: PSR-12, strict types, PHPDoc, Monolog logger, Config instance, no hardcoded config values, cyclomatic complexity under 4/10

### 4. New LinkField (Backend)

The class at `src/Fields/LinkField.php` SHALL:

- Extend `FieldBase`
- Declare `$type = 'Link'`
- Declare `$reactComponent = 'LinkInput'`
- Set default `$maxLength = 256`
- Set default `$required = false` (the field is optional by default)
- Set `$placeholder = 'https://...'`
- Set `$nullable = true`
- Set default `$target = '_blank'` — controls the `target` attribute of the rendered `<a>` tag; overridable per-model in metadata
- Include validation logic: if a value is provided (non-empty), it MUST pass `filter_var($value, FILTER_VALIDATE_URL)` AND its scheme (extracted via `parse_url`) MUST be either `http` or `https`. Empty values SHALL be accepted when the field is not required.
- Be auto-discovered by `MetadataEngine`; no manual registration is needed.
- Follow all CLAUDE.md coding standards.

### 5. New LinkInput (Frontend Component)

The component at `gravitycar-frontend/src/components/fields/LinkInput.tsx` SHALL:

- Accept the same props interface as other field components (e.g. `TextInput.tsx`): `field`, `value`, `onChange`, `readOnly`, `error`
- In **edit mode** (not `readOnly`): render `<input type="url">` with the field label, current value, and error display
- In **read-only mode** (`readOnly === true`): if value is non-empty, render `<a href={value} target={field.target ?? '_blank'} rel="noopener noreferrer">` displaying the URL text, reading the `target` value from the field metadata prop rather than hardcoding it; if value is empty, render nothing (or a dash placeholder)
- Display validation errors from the `error` prop
- Use Tailwind CSS only for styling; follow the visual style of `TextInput.tsx`

### 6. FieldComponent Registration

The file `gravitycar-frontend/src/components/fields/FieldComponent.tsx` SHALL be updated to:

- Import `LinkInput` from `./LinkInput`
- Add `'LinkInput': LinkInput` to the `componentMap` object

### 7. GenericCrudPage Link Rendering

The file `gravitycar-frontend/src/components/crud/GenericCrudPage.tsx` SHALL be updated to add a `case 'Link':` branch to the existing `renderFieldValue()` switch statement. This branch SHALL:

- Render nothing (or a dash) when the value is empty
- Render a clickable `<a>` anchor with `target={field.target ?? '_blank'}` and `rel="noopener noreferrer"` when the value is a non-empty string, reading `target` from field metadata
- Apply `break-all` word-breaking so long URLs wrap within table cells
- Apply accessible link styling (blue color, underline on hover)

### 8. Navigation Bar Integration

The file `src/Navigation/navigation_config.php` SHALL have a new entry added to the `custom_pages` array:

- `key`: `'projects'`
- `title`: `'Projects'`
- `url`: `'/projects_showcase'`
- `icon`: a suitable icon character or emoji (resolvable during implementation)
- `roles`: `['*']` (visible to all users including guests)

This entry SHALL appear in the "Navigation" (top) section of the sidebar, not the "Data Management" section.

### 9. App.tsx Route

The file `gravitycar-frontend/src/App.tsx` SHALL have a route added for `/projects_showcase`:

- Path: `/projects_showcase`
- Element: `<Layout><ProjectsPage /></Layout>`
- No `<ProtectedRoute>` wrapper — the page is publicly accessible

The existing dynamic `/:modelName` route for GenericCrudPage handles admin CRUD at `/Projects` (uppercase, matching the model name convention). This is a separate route from the public showcase. No additional route is needed for admin CRUD.

### 10. ProjectsPage (Thin Wrapper)

The file `gravitycar-frontend/src/pages/ProjectsPage.tsx` SHALL:

- Be a thin wrapper component that renders `<ProjectsListView />` inside a container `<div>` with `min-h-screen` and a neutral background
- Follow the pattern established by `TriviaPage.tsx`
- Hold no state of its own; all state lives in `ProjectsListView`

### 11. ProjectsListView Component

The file `gravitycar-frontend/src/components/projects/ProjectsListView.tsx` SHALL:

**Data fetching:**
- Fetch the full list of Projects from the backend using `apiService.getList('Projects', ...)` (or equivalent)
- While data is loading, display a centered spinner or "Loading..." text
- If the fetch returns an empty list, display a centered "No projects yet" message in place of the grid
- Handle error states using existing patterns (`DataWrapper`, or local state with conditional rendering)

**Layout:**
- Display a page heading ("Projects") above the grid
- Render project tiles in a CSS Grid using Tailwind classes `grid-cols-1 md:grid-cols-2` — single column below the `md` breakpoint (768px), two columns at 768px and above
- Each tile SHALL be 300px tall with a fixed height; tiles remain 300px tall on mobile
- Tiles SHALL have rounded corners, a box shadow, and a zoom hover effect (scale transform on image on hover, smooth transition)

**Tile content (always visible, not hover-only):**
- The Screenshot image SHALL fill the entire tile using `object-cover`
- If the Screenshot image fails to load (broken URL), an `onError` handler SHALL swap the `<img>` to a grey placeholder `<div>` displaying the project title's first letter(s) as initials, centered in the tile
- A persistent gradient overlay SHALL cover the tile (dark at top and bottom, transparent in the middle) to ensure text legibility over any image
- The project Title SHALL appear at the top of the tile in large, bold white text with a text shadow; truncated if too long (max 2 lines)
- The project Tag Line SHALL appear at the bottom of the tile in smaller white text; truncated if too long (max 2 lines)
- The gradient overlay SHALL NOT be hover-only; it SHALL always be visible

**Interaction:**
- Clicking anywhere on a tile (including the Screenshot image) SHALL open the `ProjectDetailModal` for that project. The tile is a single clickable unit — the image does NOT navigate to the Link URL.
- The component SHALL track which project is selected (or `null`) in local state
- Closing the modal (Escape key, backdrop click, or X button) SHALL set selected project back to `null`

**Accessibility:**
- Each tile SHALL have a keyboard-accessible role (e.g. `role="button"` or use a `<button>` wrapper)
- Alt text on the Screenshot image SHALL use the project title

### 12. ProjectDetailModal Component

The file `gravitycar-frontend/src/components/projects/ProjectDetailModal.tsx` SHALL:

**Structure:**
- Accept props: `project` (Project data object or null), `onClose` (callback)
- Render nothing when `project` is null
- Render a full-viewport fixed backdrop with `z-50` when a project is provided
- Render a centered white card with `max-w-2xl` width and `max-h-[90vh]` with internal scroll

**Content (top to bottom):**
1. A close button (small "×" or "X") in the top-right corner of the card
2. Project `Title` — centered, prominent heading
3. Project `Tag Line` — centered, beneath the title, muted styling
4. Project `Screenshot` image — fills available card width, capped at `max-h-[50vh]` with `object-contain` so the full image is visible without cropping and content below remains accessible; rounded corners
   - If the Screenshot image fails to load (broken URL), an `onError` handler SHALL swap the `<img>` to a grey placeholder `<div>` displaying the project title's first letter(s) as initials, centered in the placeholder area
   - If the `Link` field is non-empty, the image SHALL be wrapped in an `<a href={link} target="_blank" rel="noopener noreferrer">` making it a clickable link to the project URL; the image SHALL show `cursor-pointer` on hover as a visual affordance
   - If the `Link` field is empty, the image SHALL render without any `<a>` wrapper
5. Project `Description` — rendered below the image, right-justified text alignment
6. A "Check it out" button — centered, shown ONLY if the `Link` field is non-empty; clicking it opens the link in a new tab with `rel="noopener noreferrer"`. This intentionally duplicates the clickable screenshot link — both the image and the button navigate to the same URL.

**Behavior:**
- Pressing the Escape key SHALL close the modal (via `useEffect` keydown listener)
- Clicking the backdrop (outside the card) SHALL close the modal
- While the modal is open, `document.body.style.overflow` SHALL be set to `'hidden'` to prevent background scroll; it SHALL be restored when the modal closes
- On open, focus SHALL move into the modal (to the close button or modal container)
- On close, focus SHALL return to the tile element that opened the modal (tracked via `useRef`)

**Accessibility:**
- Modal container SHALL have `role="dialog"`, `aria-modal="true"`, and `aria-labelledby` referencing the title element
- The close button SHALL have `aria-label="Close"`

---

## RBAC Rules

| Role  | Actions Permitted on Projects |
|-------|-------------------------------|
| admin | create, read, update, delete, list, restore |
| user  | list, read |
| guest | list, read |

The custom Projects List View and Detail Modal routes SHALL be accessible without any authentication check on the frontend (no `<ProtectedRoute>`) and without any authentication check on the backend API for `list` and `read` actions.

Guest API access is supported out of the box: when no JWT token is present in a request, `CurrentUserProvider` falls back to a guest user via `GuestUserManager`. The `'guest' => ['list', 'read']` RBAC entry in the Projects metadata is sufficient — no additional backend work is needed. This pattern follows the existing Movies model. No special guest-access middleware or open-access flag is required.

---

## Acceptance Criteria

1. An admin user SHALL be able to create a new Projects record via the GenericCRUD admin interface at `/Projects` (uppercase, via the Data Management sidebar link), providing all required fields.
2. An admin user SHALL be able to edit and delete any Projects record via the GenericCRUD admin interface.
3. A non-authenticated (guest) user SHALL be able to navigate to `/projects_showcase` and see the custom Projects List View without being redirected to a login page.
4. The Projects List View SHALL display all Projects records (non-deleted) in a grid layout.
5. Each tile SHALL display the Screenshot image filling the tile, with the Title visible at the top of the tile and the Tag Line visible at the bottom of the tile, both always visible (not requiring hover).
6. Hovering over a tile SHALL produce a visible zoom effect on the screenshot image.
7. Clicking a tile SHALL open the Project Detail modal over the list view without navigating away from the page.
8. The Project Detail modal SHALL display the Title (centered), Tag Line (centered), Screenshot (full width), Description (right-justified), and a "Check it out" button (centered, only when Link is set).
9. When the Link field is populated, the Screenshot in the detail modal SHALL be clickable and open the link URL in a new browser tab.
10. When the Link field is not populated, the Screenshot in the detail modal SHALL NOT be wrapped in an anchor tag, and the "Check it out" button SHALL NOT appear.
11. The Project Detail modal SHALL close when the user clicks the "×" button, presses the Escape key, or clicks outside the modal card.
12. While the Project Detail modal is open, the page background SHALL NOT scroll.
13. After closing the Project Detail modal, focus SHALL return to the tile that opened it.
14. The Link field in the admin GenericCRUD interface SHALL render as a clickable hyperlink in the list view (not plain text).
15. The Link field in the admin form (create/edit) SHALL render as a URL input that validates `http`/`https` scheme and rejects empty-scheme or `javascript:` values.
16. A `Projects` navigation link pointing to `/projects_showcase` SHALL appear in the top "Navigation" section of the sidebar for all users including guests.
17. The `LinkField` PHP class SHALL be auto-discovered by `MetadataEngine` without manual registration.
18. Submitting a Projects record with an invalid URL (e.g. `javascript:alert(1)`) in the Link field SHALL produce a validation error and not save the record.
19. Submitting a Projects record with an empty Link field SHALL succeed (Link is optional).
20. The Projects PHP model class and metadata file SHALL follow all CLAUDE.md coding standards (PSR-12, strict types, Monolog logger, Config instance, no hardcoded config values).
21. The `projects` database table SHALL include all columns specified in the Database Schema section, including soft-delete (`deleted_at`, `deleted_by`) and audit (`created_at`, `created_by`, `updated_at`, `updated_by`) columns.
22. The GenericCRUD table view for Projects SHALL show the `screenshot` column as a thumbnail image and the `link` column as a clickable anchor link.
23. When the Projects List View has no records to display, it SHALL show a centered "No projects yet" message.
24. While the Projects List View is loading data, it SHALL show a centered spinner or "Loading..." text.
25. When a Screenshot image URL is broken (fails to load), both the tile and the detail modal SHALL display a grey placeholder showing the project title's first letter(s) as initials.
26. Clicking anywhere on a tile (including its Screenshot image) SHALL open the Project Detail modal — the tile image does NOT navigate to the Link URL. When the `Link` field is set, the Screenshot image in the detail modal SHALL be clickable and navigate to the link URL in a new tab (`target="_blank" rel="noopener noreferrer"`), the same destination as the "Check it out" button. This is the intentional dual affordance: tile image opens the modal, modal image opens the link.
27. The Projects grid SHALL display as a single column on screens narrower than 768px and as two columns on screens 768px or wider.

---

## Implementation Notes

The following items were previously listed as open questions. They are resolved and are recorded here for implementation reference only — they do not block development.

1. **Icon for navigation entry**: No specific icon is required by the specification. Choose any suitable icon (e.g. a folder, rocket, or briefcase character) during implementation.

2. **Screenshot URL validation**: The `ImageField` uses `allowRemote: true, allowLocal: false`. The existing `ImageField` validation is assumed sufficient for URL format. Verify during implementation; if additional validation is needed, add it as a validation rule on the `screenshot` field without modifying the core `ImageField` class.

3. **Tailwind `line-clamp` support**: The tile text truncation uses `line-clamp-2`. Confirm the project's Tailwind CSS version (v3.3+ supports this natively). If using an older version, add the `@tailwindcss/line-clamp` plugin.
