# Implementation Catalog: Projects Model

## Item 1: LinkField Backend
**Files**:
- `src/Fields/LinkField.php` (new)

**Description**: Creates the new `Link` field type as a PHP class extending `FieldBase`. Declares `$type = 'Link'`, `$reactComponent = 'LinkInput'`, `$maxLength = 256`, `$nullable = true`, `$required = false`, `$placeholder = 'https://...'`, and `$target = '_blank'`. Implements URL validation logic: if a value is non-empty, it must pass `filter_var` URL validation AND have an `http` or `https` scheme (rejecting `javascript:`, `data:`, etc.). `MetadataEngine` auto-discovers this class by scanning `src/Fields/` — no manual registration needed.

**Depends on**: none

---

## Item 2: LinkInput Frontend + FieldComponent Registration + GenericCrudPage Case
**Files**:
- `gravitycar-frontend/src/components/fields/LinkInput.tsx` (new)
- `gravitycar-frontend/src/components/fields/FieldComponent.tsx` (modify)
- `gravitycar-frontend/src/components/crud/GenericCrudPage.tsx` (modify)

**Description**: Creates the React component `LinkInput.tsx` for the Link field type. In edit mode renders `<input type="url">` with label, current value, and error display. In read-only mode renders a clickable `<a>` anchor (reading `target` from field metadata) when value is non-empty, or nothing when empty. Follows the visual style of `TextInput.tsx`. Registers `LinkInput` in the `componentMap` in `FieldComponent.tsx`. Adds a `case 'Link':` branch to `renderFieldValue()` in `GenericCrudPage.tsx` that renders a styled, accessible anchor link with `break-all` word-wrapping and `target` read from field metadata, or a dash when the value is empty.

**Depends on**: Item 1 (LinkField backend defines the field type and `$target` property that the frontend reads from metadata)

---

## Item 3: Projects Model Backend
**Files**:
- `src/Models/projects/projects_metadata.php` (new)
- `src/Models/projects/Projects.php` (new)

**Description**: Creates the Projects model backend. The metadata file defines the model identity (`name: 'Projects'`, `table: 'projects'`, `displayColumns: ['title']`), all five application fields (`title`, `tag_line`, `description`, `screenshot`, `link`), RBAC granting admin full access and user/guest list+read, UI field lists (`listFields`, `createFields`, `editFields`), and validation rules including the required/maxLength constraints and the optional http/https URL validation for `link`. The `SchemaGenerator` reads this metadata at runtime to create the `projects` table automatically — no SQL migration is needed. The `Projects.php` class extends `ModelBase` in the `Gravitycar\Models\projects` namespace, injects all standard constructor dependencies, and requires no domain methods beyond what `ModelBase` provides.

**Depends on**: Item 1 (LinkField backend must exist before MetadataEngine can resolve the `Link` field type in the metadata)

---

## Item 4: Navigation Bar Entry
**Files**:
- `src/Navigation/navigation_config.php` (modify)

**Description**: Adds a new entry to the `custom_pages` array in the navigation config. The entry uses key `'projects'`, title `'Projects'`, URL `'/projects_showcase'`, a suitable icon character, and `roles: ['*']` so it appears for all users including unauthenticated guests. The entry appears in the "Navigation" (top) section of the sidebar, not the "Data Management" section. The simple key `'projects'` (no underscores) renders as a standalone link, not part of a grouped nav cluster.

**Depends on**: none (config-only change; can be applied independently)

---

## Item 5: ProjectsPage + App.tsx Route
**Files**:
- `gravitycar-frontend/src/pages/ProjectsPage.tsx` (new)
- `gravitycar-frontend/src/App.tsx` (modify)

**Description**: Creates the thin page wrapper `ProjectsPage.tsx` following the `TriviaPage.tsx` pattern — a `<div className="min-h-screen bg-gray-50">` container that renders `<ProjectsListView />`. Holds no state of its own. Adds the `/projects_showcase` route to `App.tsx` as `<Layout><ProjectsPage /></Layout>` without a `<ProtectedRoute>` wrapper, making the page publicly accessible to unauthenticated visitors. The existing dynamic `/:modelName` route already handles the admin GenericCRUD view at `/Projects` — no additional route is needed for that.

**Depends on**: Item 6 (ProjectsListView must exist before ProjectsPage can render it)

---

## Item 6: ProjectsListView Component
**Files**:
- `gravitycar-frontend/src/components/projects/ProjectsListView.tsx` (new)
- `gravitycar-frontend/src/components/projects/index.ts` (new)

**Description**: Creates the main public-facing list view for the Projects showcase. Fetches all Projects from the backend via `apiService.getList('Projects', ...)`. Shows a loading spinner while fetching, a "No projects yet" message for empty results, and an error state on failure. Renders project tiles in a CSS Grid with `grid-cols-1 md:grid-cols-2` — single column on mobile, two columns at 768px and above. Each tile is 300px tall with rounded corners, box shadow, and a zoom hover effect (scale transform on image, smooth transition). The Screenshot image fills the tile with `object-cover`; if the image fails to load an `onError` handler swaps it for a grey initials placeholder. A persistent gradient overlay (always visible, not hover-only) ensures text legibility. The project Title appears at the top in large bold white text (max 2 lines, truncated); the Tag Line appears at the bottom in smaller white text (max 2 lines, truncated). Clicking anywhere on a tile opens `ProjectDetailModal` — the tile image does NOT navigate to the Link URL. Tracks selected project in local state (`null` when no modal open). Includes keyboard accessibility (tiles have `role="button"` or use `<button>` wrappers). Creates `index.ts` as a barrel export for the `projects` component directory.

**Depends on**: Item 3 (Projects model backend must exist so the API can serve data), Item 7 (ProjectDetailModal must exist before ProjectsListView can render it)

---

## Item 7: ProjectDetailModal Component
**Files**:
- `gravitycar-frontend/src/components/projects/ProjectDetailModal.tsx` (new)

**Description**: Creates the detail overlay modal for a selected project. Accepts `project` (data object or null) and `onClose` props; renders nothing when `project` is null. Renders a fixed full-viewport backdrop (`z-50`) with a centered white card (`max-w-2xl`, `max-h-[90vh]` with internal scroll). Card content from top to bottom: close button ("×", `aria-label="Close"`), centered Title heading, centered Tag Line in muted style, Screenshot image filling card width (`max-h-[50vh]`, `object-contain`) optionally wrapped in an `<a>` anchor to the Link URL when Link is non-empty (image shows `cursor-pointer`; `target="_blank" rel="noopener noreferrer"`), right-justified Description text, and a centered "Check it out" button shown only when Link is non-empty. Both the clickable screenshot image and the button link to the same Link URL — the intentional dual affordance. If the Screenshot image fails to load, an `onError` handler swaps it for a grey initials placeholder. Escape key closes the modal via `useEffect` keydown listener; clicking the backdrop closes it; `document.body.style.overflow` is set to `'hidden'` while open and restored on close. Focus moves into the modal on open and returns to the originating tile on close (tracked via `useRef`). ARIA: `role="dialog"`, `aria-modal="true"`, `aria-labelledby` referencing the title element.

**Depends on**: Item 3 (Projects model backend provides the data shape / TypeScript interface)
