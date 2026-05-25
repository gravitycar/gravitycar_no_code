# Implementation Plan: ProjectsListView

## Spec Context

This plan implements the Projects showcase grid view described in Spec Section 11. `ProjectsListView` fetches all published Projects from the backend and renders them as a 2-column image-tile grid. Clicking any tile opens `ProjectDetailModal` over the page. The component is publicly accessible — no authentication is required. It is the primary visual surface of the Projects feature.

Catalog item: `ProjectsListView`
Specification section: Section 11 — ProjectsListView Component
Acceptance criteria addressed: 3, 4, 5, 6, 7, 23, 24, 25, 26, 27

---

## Dependencies

- **Blocked by**: `ProjectDetailModal` plan (plan-07) — this component imports and renders `ProjectDetailModal`.
- **Uses**:
  - `apiService.getList('Projects', ...)` from `gravitycar-frontend/src/services/api.ts`
  - `ProjectDetailModal` from `./ProjectDetailModal`
  - React hooks: `useState`, `useEffect`, `useRef`, `useCallback`
  - Tailwind CSS only (no external UI libraries)

---

## File Changes

### New Files

- `gravitycar-frontend/src/components/projects/ProjectsListView.tsx` — the main list-view component with data fetching, grid layout, and modal integration
- `gravitycar-frontend/src/components/projects/index.ts` — barrel file exporting both components and the shared Project type (created after both components exist)

**`index.ts` content:**

```typescript
export { ProjectDetailModal } from './ProjectDetailModal';
export { ProjectsListView } from './ProjectsListView';
export type { Project } from './types';
```

### Modified Files

- None in this plan. `ProjectsPage.tsx` (a separate plan) imports and renders this component.

---

## Implementation Details

### Type Definitions

Import `Project` from the shared `types.ts` file (created in Plan 07):

```typescript
import { Project } from './types';
```

`ProjectTileProps` is defined inline in this file since it is only used here:

```typescript
interface ProjectTileProps {
  project: Project;
  imgError: boolean;
  onImgError: (id: string) => void;
  onClick: (project: Project) => void;
  tileRef: (el: HTMLDivElement | null) => void;
}
```

---

### State

```typescript
const [projects, setProjects] = useState<Project[]>([]);
const [loading, setLoading] = useState<boolean>(true);
const [error, setError] = useState<string | null>(null);
const [selectedProject, setSelectedProject] = useState<Project | null>(null);
const [imgErrors, setImgErrors] = useState<Record<string, boolean>>({});
// Map from project id to a ref so focus can return to the clicked tile after modal close
const tileRefs = useRef<Record<string, HTMLDivElement | null>>({});
```

- `projects` — the loaded list of Project records
- `loading` — true while the initial fetch is in flight
- `error` — non-null when the fetch fails; holds a user-readable message
- `selectedProject` — the project whose detail modal is open; null means modal is closed
- `imgErrors` — keyed by project `id`; tracks which tiles have a broken screenshot URL independently
- `tileRefs` — maps project `id` to the DOM node for the tile so focus can be restored on modal close

---

### Data Fetching

Use `useEffect` on mount. Call `apiService.getList('Projects', 1, 1000)` with a large limit to fetch all projects in one request (no pagination required by spec).

```typescript
const fetchProjects = useCallback(async () => {
  setLoading(true);
  setError(null);
  try {
    const response = await apiService.getList<Project>('Projects', 1, 1000);
    if (response.success) {
      setProjects(response.data ?? []);
    } else {
      setError(response.message ?? 'Failed to load projects.');
    }
  } catch {
    setError('Failed to load projects. Please try again later.');
  } finally {
    setLoading(false);
  }
}, []);

useEffect(() => {
  fetchProjects();
}, [fetchProjects]);
```

Key notes:
- `apiService` is imported as the named export `apiService` from `../../services/api`
- `apiService.getList` returns a `PaginatedResponse<T>`; the data array is in `.data`
- No auth token management needed — the request interceptor in `ApiService` already attaches the token from `localStorage` if present; for guests, no token is present and the backend falls back to the guest role automatically
- Wrap in `try/catch` to handle both network errors (which `getList` catches internally and returns `success: false`) and any unexpected throws

---

### Per-tile Image Error Handler

Each tile has its own image-error state tracked by project id in the `imgErrors` object. Use a stable callback to set the error for the specific project:

```typescript
const handleImgError = useCallback((projectId: string) => {
  setImgErrors((prev) => ({ ...prev, [projectId]: true }));
}, []);
```

---

### Initials Helper

```typescript
function getInitials(title: string): string {
  return title
    .split(' ')
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase())
    .join('');
}
```

Used in both the tile fallback and is consistent with the same helper in `ProjectDetailModal`.

---

### Tile Open / Close Handler

```typescript
const handleTileClick = useCallback((project: Project) => {
  setSelectedProject(project);
}, []);

const handleModalClose = useCallback(() => {
  const closingProjectId = selectedProject?.id;
  setSelectedProject(null);
  // Restore focus to the tile that opened the modal
  if (closingProjectId) {
    // Use setTimeout(0) to allow React to re-render before shifting focus
    setTimeout(() => {
      tileRefs.current[closingProjectId]?.focus();
    }, 0);
  }
}, [selectedProject]);
```

---

### Render States

The component returns one of four possible render outputs:

#### 1. Loading State

```tsx
if (loading) {
  return (
    <div className="flex items-center justify-center min-h-[400px]">
      <p className="text-gray-500 text-lg">Loading...</p>
    </div>
  );
}
```

#### 2. Error State

```tsx
if (error) {
  return (
    <div className="flex items-center justify-center min-h-[400px]">
      <p className="text-red-500 text-lg">{error}</p>
    </div>
  );
}
```

#### 3. Empty State

```tsx
if (projects.length === 0) {
  return (
    <div className="flex items-center justify-center min-h-[400px]">
      <p className="text-gray-500 text-lg">No projects yet.</p>
    </div>
  );
}
```

#### 4. Grid State

The main render: a heading, the grid of tiles, and the modal portal.

---

### Full JSX Structure (Grid State)

```tsx
return (
  <div className="w-full px-4 py-6">
    {/* Page heading */}
    <h1 className="text-3xl font-bold text-gray-900 mb-6">Projects</h1>

    {/* Project grid */}
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
      {projects.map((project) => (
        <ProjectTile
          key={project.id}
          project={project}
          imgError={imgErrors[project.id] ?? false}
          onImgError={handleImgError}
          onClick={handleTileClick}
          tileRef={(el) => { tileRefs.current[project.id] = el; }}
        />
      ))}
    </div>

    {/* Detail modal — renders null when selectedProject is null */}
    <ProjectDetailModal
      project={selectedProject}
      onClose={handleModalClose}
    />
  </div>
);
```

---

### ProjectTile Sub-Component

Extract the tile into a local sub-component (defined in the same file, below the main component) to keep the main component focused.

**Props interface:**

```typescript
interface ProjectTileProps {
  project: Project;
  imgError: boolean;
  onImgError: (id: string) => void;
  onClick: (project: Project) => void;
  tileRef: (el: HTMLDivElement | null) => void;
}
```

**Full tile JSX:**

```tsx
function ProjectTile({ project, imgError, onImgError, onClick, tileRef }: ProjectTileProps) {
  const handleKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onClick(project);
    }
  };

  return (
    <div
      ref={tileRef}
      role="button"
      tabIndex={0}
      aria-label={project.title}
      className="relative h-[300px] overflow-hidden rounded-xl cursor-pointer group shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
      onClick={() => onClick(project)}
      onKeyDown={handleKeyDown}
    >
      {/* Screenshot image or initials fallback */}
      {imgError ? (
        <div className="absolute inset-0 bg-gray-300 flex items-center justify-center">
          <span className="text-4xl font-bold text-gray-600">
            {getInitials(project.title)}
          </span>
        </div>
      ) : (
        <img
          src={project.screenshot}
          alt={project.title}
          className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          onError={() => onImgError(project.id)}
        />
      )}

      {/* Gradient overlay — always visible, not hover-only */}
      <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-transparent to-black/70 pointer-events-none" />

      {/* Title at the top */}
      <p className="absolute top-0 left-0 right-0 p-3 text-white text-xl font-bold drop-shadow-md line-clamp-2 pointer-events-none">
        {project.title}
      </p>

      {/* Tag line at the bottom */}
      <p className="absolute bottom-0 left-0 right-0 p-3 text-white text-sm drop-shadow-md line-clamp-2 pointer-events-none">
        {project.tag_line}
      </p>
    </div>
  );
}
```

**Design notes:**

- `h-[300px]` fixes the tile height; `overflow-hidden` clips the image and hover-zoom effect within the rounded corners
- `group` on the container enables `group-hover:scale-105` on the `<img>` child
- `transition-transform duration-300` provides a smooth 300ms ease zoom animation
- `pointer-events-none` on the overlay and text prevents them from blocking click events on the tile container
- `focus:ring-2 focus:ring-blue-500` provides a visible keyboard focus indicator
- `tabIndex={0}` makes the `<div>` keyboard-focusable
- `onKeyDown` handles Enter and Space to open the modal (Space calls `e.preventDefault()` to prevent page scroll)
- The `tileRef` callback ref is used to register each tile's DOM node with the `tileRefs` map in the parent

---

### Tailwind Class Reference

| Element | Tailwind Classes |
|---------|-----------------|
| Outer container | `w-full px-4 py-6` |
| Page heading | `text-3xl font-bold text-gray-900 mb-6` |
| Grid | `grid grid-cols-1 md:grid-cols-2 gap-6` |
| Tile container | `relative h-[300px] overflow-hidden rounded-xl cursor-pointer group shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2` |
| Screenshot `<img>` | `absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300` |
| Initials fallback `<div>` | `absolute inset-0 bg-gray-300 flex items-center justify-center` |
| Initials text | `text-4xl font-bold text-gray-600` |
| Gradient overlay | `absolute inset-0 bg-gradient-to-b from-black/60 via-transparent to-black/70 pointer-events-none` |
| Title `<p>` | `absolute top-0 left-0 right-0 p-3 text-white text-xl font-bold drop-shadow-md line-clamp-2 pointer-events-none` |
| Tag line `<p>` | `absolute bottom-0 left-0 right-0 p-3 text-white text-sm drop-shadow-md line-clamp-2 pointer-events-none` |
| Loading/Error/Empty container | `flex items-center justify-center min-h-[400px]` |
| Loading text | `text-gray-500 text-lg` |
| Error text | `text-red-500 text-lg` |
| Empty text | `text-gray-500 text-lg` |

---

### Full File Skeleton (imports + structure)

```typescript
import React, { useState, useEffect, useRef, useCallback } from 'react';
import apiService from '../../services/api';
import ProjectDetailModal from './ProjectDetailModal';
import { Project } from './types';

interface ProjectTileProps {
  project: Project;
  imgError: boolean;
  onImgError: (id: string) => void;
  onClick: (project: Project) => void;
  tileRef: (el: HTMLDivElement | null) => void;
}

function getInitials(title: string): string { ... }

function ProjectTile({ project, imgError, onImgError, onClick, tileRef }: ProjectTileProps): React.ReactElement {
  // handleKeyDown
  return (
    <div ref={tileRef} role="button" tabIndex={0} ...>
      {/* image or initials fallback */}
      {/* gradient overlay */}
      {/* title */}
      {/* tag line */}
    </div>
  );
}

export default function ProjectsListView(): React.ReactElement {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedProject, setSelectedProject] = useState<Project | null>(null);
  const [imgErrors, setImgErrors] = useState<Record<string, boolean>>({});
  const tileRefs = useRef<Record<string, HTMLDivElement | null>>({});

  // fetchProjects useCallback
  // useEffect calling fetchProjects
  // handleImgError useCallback
  // handleTileClick useCallback
  // handleModalClose useCallback

  if (loading) { return <loading state />; }
  if (error) { return <error state />; }
  if (projects.length === 0) { return <empty state />; }

  return (
    <div className="w-full px-4 py-6">
      <h1 ...>Projects</h1>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {projects.map((project) => (
          <ProjectTile key={project.id} ... />
        ))}
      </div>
      <ProjectDetailModal project={selectedProject} onClose={handleModalClose} />
    </div>
  );
}
```

---

## Error Handling

| Condition | Handling |
|-----------|----------|
| Network error during fetch | `getList` catches internally and returns `success: false`; `error` state is set to the message from the response or a fallback string |
| Unexpected throw during fetch | Outer `try/catch` sets `error` state to `'Failed to load projects. Please try again later.'` |
| Empty projects list | `projects.length === 0` renders centered "No projects yet." message |
| Broken screenshot URL | `onError` on `<img>` calls `handleImgError(project.id)`; sets `imgErrors[project.id] = true`; renders initials fallback `<div>` in place of `<img>` |
| `selectedProject` is null | `ProjectDetailModal` receives `project={null}` and returns `null` — no modal rendered |

---

## Unit Test Specifications

### `ProjectsListView` rendering

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Loading state | `getList` is pending | "Loading..." text rendered | Initial fetch in progress |
| Error state | `getList` returns `success: false` | Error message rendered | API error handling |
| Empty state | `getList` returns empty `data` array | "No projects yet." message rendered | No records |
| Grid renders | `getList` returns 3 projects | 3 tiles rendered in the DOM | Happy path |
| Tile has title | Project with title "My App" | Title text appears in tile | Content rendering |
| Tile has tag line | Project with tag_line "A great app" | Tag line text appears in tile | Content rendering |
| Single column class | Window width < 768px | Grid has `grid-cols-1` | Responsive layout |
| Two column class | Window width >= 768px | Grid has `md:grid-cols-2` | Responsive layout |

### Key Scenario: Image Error Shows Initials

**Setup**: Render with one project; project has `id="p1"`, `title="Gravity Car"`, `screenshot="broken-url.jpg"`
**Action**: Simulate `onError` event on the tile's `<img>` element
**Expected**: `<img>` is replaced by a `<div>` with class `bg-gray-300` containing the text "GC"; `<img>` is no longer in the DOM

### Key Scenario: Clicking a Tile Opens Modal

**Setup**: Render with one project
**Action**: Click the tile `<div>` (the `role="button"` element)
**Expected**: `ProjectDetailModal` receives `project` equal to the clicked project (not null); modal becomes visible

### Key Scenario: Keyboard Enter Opens Modal

**Setup**: Render with one project; tile has focus
**Action**: Press Enter key while tile has focus
**Expected**: Modal opens for that project (same as click behaviour)

### Key Scenario: Keyboard Space Opens Modal

**Setup**: Render with one project; tile has focus
**Action**: Press Space key while tile has focus
**Expected**: Modal opens for that project; `e.preventDefault()` was called (page does not scroll)

### Key Scenario: Modal Close Restores Focus

**Setup**: Render with one project; click tile to open modal
**Action**: Call `onClose` callback (simulating Escape or close button)
**Expected**: After `onClose`, focus is returned to the tile DOM element (check `document.activeElement` after `setTimeout` flush)

### Key Scenario: Per-Tile Error State Independence

**Setup**: Render with two projects — project A has a broken screenshot URL, project B has a valid one
**Action**: Simulate `onError` on project A's `<img>`
**Expected**: Project A renders initials fallback; Project B still renders its `<img>` normally (error state is isolated to project A)

### Key Scenario: `getList` Called With Correct Model Name

**Setup**: Mock `apiService.getList` as a jest spy
**Action**: Render `ProjectsListView`
**Expected**: `apiService.getList` was called with `'Projects'` as the first argument

---

## Notes

1. **`fetchProjects` as `useCallback`**: The `fetchProjects` function is wrapped in `useCallback` so it can be safely listed as a dependency of the `useEffect`. This avoids the ESLint `react-hooks/exhaustive-deps` warning while preventing infinite re-renders.

2. **`line-clamp-2` availability**: This requires Tailwind CSS v3.3+ (built-in) or the `@tailwindcss/line-clamp` plugin for earlier versions. Verify the project's Tailwind version before building. If the plugin is needed, add it to `tailwind.config.js`.

3. **`pointer-events-none` on overlay and text**: Without this, the absolutely-positioned gradient, title, and tag line `<div>`/`<p>` elements sit on top of the tile and intercept click events before they reach the container. `pointer-events-none` passes all pointer events through to the container `<div>`, ensuring the `onClick` always fires.

4. **`tileRef` as callback ref**: The `tileRef` prop is a callback ref `(el: HTMLDivElement | null) => void` passed to the tile's root `<div>`. This registers the DOM node in the parent's `tileRefs.current` map keyed by project id. This is preferred over creating `n` individual `useRef` instances for an unknown number of tiles.

5. **`setTimeout(0)` in `handleModalClose`**: After calling `setSelectedProject(null)`, React will re-render and `ProjectDetailModal` will unmount. The `setTimeout(0)` defers the `focus()` call to after the re-render, ensuring the tile element is in the DOM and reachable before focus is moved to it.

6. **`apiService` import**: Import the default export `apiService` from `../../services/api`: `import apiService from '../../services/api';`. This is the default export form — the import syntax is correct as written.

7. **`ProjectTile` placement**: Define `ProjectTile` below the main `ProjectsListView` export in the same file, not in a separate file. It is only used by `ProjectsListView` and extracting it to a separate file would add unnecessary file overhead. The combined file will remain under 300 lines.

8. **Focus ring on tile**: The `focus:ring-2 focus:ring-blue-500 focus:ring-offset-2` classes provide a visible focus indicator for keyboard navigation, satisfying the accessibility requirement. `focus:outline-none` removes the browser default outline to prevent double-outline rendering.

9. **Barrel file (`index.ts`)**: This plan creates `index.ts` as a new file after both `ProjectDetailModal.tsx` and `ProjectsListView.tsx` exist. The full content is specified in the File Changes section above.
