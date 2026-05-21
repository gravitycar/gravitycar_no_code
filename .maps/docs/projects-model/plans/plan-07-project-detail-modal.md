# Implementation Plan: ProjectDetailModal

## Spec Context

This plan implements the Project Detail Modal overlay described in Spec Section 12. When a user clicks a project tile in `ProjectsListView`, this modal opens over the page and shows the full project details: title, tag line, screenshot (optionally linked), description, and a "Check it out" button. The component is a standalone custom modal (not reusing `Modal.tsx`) because its layout is image-dominant and does not match the generic form-based modal structure.

Catalog item: `ProjectDetailModal`
Specification section: Section 12 — ProjectDetailModal Component
Acceptance criteria addressed: 7, 8, 9, 10, 11, 12, 13, 25

---

## Dependencies

- **Blocked by**: None — the component is self-contained UI. `ProjectsListView` will import it, but the modal itself has no imports blocked by other plans.
- **Uses**: React (hooks: `useState`, `useEffect`, `useRef`), Tailwind CSS only (no external UI libraries), project TypeScript setup

---

## File Changes

### New Files

- `gravitycar-frontend/src/components/projects/types.ts` — shared Project type definition
- `gravitycar-frontend/src/components/projects/ProjectDetailModal.tsx` — the detail modal component

### Modified Files

- None in this plan. `ProjectsListView.tsx` (a separate plan) imports and uses this component.

---

## Implementation Details

### ProjectDetailModal Component

**File**: `gravitycar-frontend/src/components/projects/ProjectDetailModal.tsx`

**Exports**:
- `export default function ProjectDetailModal(props: ProjectDetailModalProps): React.ReactElement | null`

---

### `types.ts` Content

**File**: `gravitycar-frontend/src/components/projects/types.ts`

```typescript
export interface Project {
  id: string;
  title: string;
  tag_line: string;
  description: string;
  screenshot: string;
  link?: string;
}
```

### Type Definitions (import from types.ts)

Import `Project` from the shared types file. Define `ProjectDetailModalProps` inline in this file:

```typescript
import { Project } from './types';

interface ProjectDetailModalProps {
  project: Project | null;  // null = modal is closed
  onClose: () => void;
}
```

---

### State and Refs

```typescript
const [imgError, setImgError] = useState<boolean>(false);
const closeButtonRef = useRef<HTMLButtonElement>(null);
const titleId = 'project-detail-title';  // constant string, not state
```

- `imgError` — tracks whether the `<img>` failed to load; switches rendering to the grey placeholder fallback
- `closeButtonRef` — ref to the close button so focus can be moved to it on modal open

---

### Hook Logic

**Effect 1: Escape key listener**

```typescript
useEffect(() => {
  if (!project) return;

  const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      onClose();
    }
  };

  document.addEventListener('keydown', handleKeyDown);
  return () => document.removeEventListener('keydown', handleKeyDown);
}, [project, onClose]);
```

- Only registers when `project` is non-null (modal is open)
- Cleans up on unmount or when `project` becomes null

**Effect 2: Body scroll lock**

```typescript
useEffect(() => {
  if (!project) return;

  const previousOverflow = document.body.style.overflow;
  document.body.style.overflow = 'hidden';

  return () => {
    document.body.style.overflow = previousOverflow;
  };
}, [project]);
```

- Locks scroll while modal is open; restores the previous value (not hardcoded `''`) on cleanup
- `previousOverflow` captures any pre-existing overflow value

**Effect 3: Focus management on open**

```typescript
useEffect(() => {
  if (!project) return;
  closeButtonRef.current?.focus();
}, [project]);
```

- When `project` becomes non-null (modal opens), focus moves to the close button
- Focus return to the opening tile is the responsibility of `ProjectsListView` (it owns the `ref` to the clicked tile element and calls `focus()` after `onClose`). This modal only manages inward focus.

**State reset on project change**

```typescript
useEffect(() => {
  setImgError(false);
}, [project]);
```

- Resets the image error state whenever a new project is opened, so the stale error from a previous project doesn't persist.

---

### Initials Helper (inline function)

```typescript
function getInitials(title: string): string {
  return title
    .split(' ')
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase())
    .join('');
}
```

- Takes up to the first two words of the title, extracts the first letter of each, returns uppercase initials
- Used in the fallback placeholder when image fails to load

---

### JSX Structure (full layout)

```tsx
// Early return for closed state
if (!project) return null;

const hasLink = Boolean(project.link && project.link.trim() !== '');

return (
  {/* ── Backdrop ── */}
  <div
    className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
    onClick={onClose}
    aria-hidden="true"
  >
    {/* ── Modal card ── */}
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby={titleId}
      className="relative bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6"
      onClick={(e) => e.stopPropagation()}
    >
      {/* ── Close button ── */}
      <button
        ref={closeButtonRef}
        onClick={onClose}
        aria-label="Close"
        className="absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 text-lg leading-none"
      >
        &times;
      </button>

      {/* ── Title ── */}
      <h2
        id={titleId}
        className="text-2xl font-bold text-gray-900 text-center pr-8 mb-1"
      >
        {project.title}
      </h2>

      {/* ── Tag line ── */}
      <p className="text-center text-gray-500 text-sm mb-4">
        {project.tag_line}
      </p>

      {/* ── Screenshot (conditional link wrapper) ── */}
      {hasLink ? (
        <a
          href={project.link}
          target="_blank"
          rel="noopener noreferrer"
          className="block mb-4 cursor-pointer"
        >
          {renderScreenshot(project, imgError, setImgError)}
        </a>
      ) : (
        <div className="mb-4">
          {renderScreenshot(project, imgError, setImgError)}
        </div>
      )}

      {/* ── Description ── */}
      <p className="text-gray-700 text-sm leading-relaxed text-right mb-4 whitespace-pre-wrap">
        {project.description}
      </p>

      {/* ── "Check it out" button (only when link is set) ── */}
      {hasLink && (
        <div className="flex justify-center">
          <a
            href={project.link}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors"
          >
            Check it out
          </a>
        </div>
      )}
    </div>
  </div>
);
```

**`renderScreenshot` helper** (defined as a local function inside the component or extracted above return):

```tsx
function renderScreenshot(
  project: Project,
  imgError: boolean,
  setImgError: (v: boolean) => void
): React.ReactElement {
  if (imgError) {
    return (
      <div className="w-full max-h-[50vh] flex items-center justify-center bg-gray-200 rounded-lg"
           style={{ minHeight: '200px' }}>
        <span className="text-4xl font-bold text-gray-500">
          {getInitials(project.title)}
        </span>
      </div>
    );
  }

  return (
    <img
      src={project.screenshot}
      alt={project.title}
      className="w-full max-h-[50vh] object-contain rounded-lg"
      onError={() => setImgError(true)}
    />
  );
}
```

- When `imgError` is false: renders `<img>` with `w-full max-h-[50vh] object-contain rounded-lg`
- When `imgError` is true: renders grey placeholder `<div>` with `bg-gray-200 rounded-lg` centered on initials
- The `minHeight: '200px'` inline style prevents the placeholder collapsing to zero height

---

### Tailwind Class Reference (all classes used)

| Element | Tailwind Classes |
|---------|-----------------|
| Backdrop | `fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4` |
| Modal card | `relative bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6` |
| Close button | `absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 text-lg leading-none` |
| Title `<h2>` | `text-2xl font-bold text-gray-900 text-center pr-8 mb-1` |
| Tag line `<p>` | `text-center text-gray-500 text-sm mb-4` |
| Screenshot link wrapper | `block mb-4 cursor-pointer` |
| Screenshot plain wrapper | `mb-4` |
| `<img>` | `w-full max-h-[50vh] object-contain rounded-lg` |
| Img fallback div | `w-full max-h-[50vh] flex items-center justify-center bg-gray-200 rounded-lg` |
| Initials text | `text-4xl font-bold text-gray-500` |
| Description | `text-gray-700 text-sm leading-relaxed text-right mb-4 whitespace-pre-wrap` |
| Button wrapper | `flex justify-center` |
| "Check it out" `<a>` | `inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors` |

Note: `pr-8` on the title prevents the text from overlapping the close button (which is positioned `top-3 right-3` and is `w-8`).

---

### Accessibility Notes

- `role="dialog"` + `aria-modal="true"` on the card element
- `aria-labelledby={titleId}` on the card pointing to `id="project-detail-title"` on the `<h2>`
- `aria-label="Close"` on the close button
- `aria-hidden="true"` on the backdrop `<div>` so screen readers focus on the card
- Close button receives focus on modal open via `closeButtonRef.current?.focus()`
- "Check it out" rendered as an `<a>` (not a `<button>`) since it navigates to an external URL — semantically correct

---

### Backdrop Click Behaviour

The backdrop `onClick={onClose}` fires when the user clicks outside the card. The card itself calls `e.stopPropagation()` to prevent backdrop clicks from triggering when clicking inside the card.

This matches the pattern used by the existing `Modal.tsx` (which uses `if (e.target === e.currentTarget)` — both approaches work; `stopPropagation` is slightly simpler).

---

### Full File Skeleton (imports + structure)

```typescript
import React, { useState, useEffect, useRef } from 'react';
import { Project } from './types';

interface ProjectDetailModalProps {
  project: Project | null;
  onClose: () => void;
}

function getInitials(title: string): string { ... }

export default function ProjectDetailModal({ project, onClose }: ProjectDetailModalProps): React.ReactElement | null {
  const [imgError, setImgError] = useState<boolean>(false);
  const closeButtonRef = useRef<HTMLButtonElement>(null);

  // Effect: reset image error on project change
  useEffect(() => { ... }, [project]);

  // Effect: Escape key listener
  useEffect(() => { ... }, [project, onClose]);

  // Effect: body scroll lock
  useEffect(() => { ... }, [project]);

  // Effect: focus close button on open
  useEffect(() => { ... }, [project]);

  if (!project) return null;

  const hasLink = Boolean(project.link && project.link.trim() !== '');

  return (
    <div className="fixed inset-0 ..." onClick={onClose} aria-hidden="true">
      <div role="dialog" aria-modal="true" aria-labelledby="project-detail-title"
           className="relative bg-white ..." onClick={(e) => e.stopPropagation()}>
        <button ref={closeButtonRef} onClick={onClose} aria-label="Close" className="absolute top-3 right-3 ...">
          &times;
        </button>
        <h2 id="project-detail-title" className="text-2xl font-bold ... text-center ...">
          {project.title}
        </h2>
        <p className="text-center text-gray-500 ...">
          {project.tag_line}
        </p>
        {/* conditional link wrapper around screenshot */}
        {/* description */}
        {/* check it out button */}
      </div>
    </div>
  );
}
```

---

## Error Handling

| Condition | Handling |
|-----------|----------|
| `project` is null | Returns `null` immediately — no modal rendered |
| Screenshot image URL is broken | `onError` sets `imgError = true`; renders grey placeholder with initials |
| `project.link` is empty string | `hasLink = false`; no `<a>` wrapper on screenshot, no "Check it out" button |
| `project.link` is whitespace-only | `trim() !== ''` check in `hasLink` evaluation catches this |

---

## Unit Test Specifications

### `ProjectDetailModal` rendering

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Closed state | `project={null}` | Returns null — nothing rendered | Closed state guard |
| Basic render | Valid project, no link | Modal renders title, tag line, description; no "Check it out" button; screenshot not wrapped in `<a>` | No-link variant |
| With link | Valid project with link | "Check it out" button rendered; screenshot wrapped in `<a>` with `target="_blank"` | Link variant |
| Img error fallback | `onError` triggered | Grey placeholder with initials shown instead of `<img>` | Broken URL handling |
| Empty link string | `link=""` | Treated same as no link — no button, no `<a>` wrapper | Edge case |
| Whitespace link | `link="  "` | Treated same as no link | Edge case |
| Title initials — single word | `title="Gravitycar"` | Initials = `"G"` | Single word |
| Title initials — two words | `title="Gravity Car"` | Initials = `"GC"` | Two words |
| Title initials — three words | `title="My Cool Project"` | Initials = `"MC"` (first two words only) | Word limit |

### Key Scenario: Image Error Fallback

**Setup**: Render `<ProjectDetailModal project={validProject} onClose={jest.fn()} />`
**Action**: Simulate `onError` event on the `<img>` element
**Expected**: `<img>` is replaced by a `<div>` with `bg-gray-200` containing the project initials text; `<img>` is no longer in the DOM

### Key Scenario: Escape Key Closes Modal

**Setup**: Render open modal with `onClose` mock
**Action**: Dispatch `keydown` event with `key = 'Escape'` on `document`
**Expected**: `onClose` is called once

### Key Scenario: Backdrop Click Closes Modal

**Setup**: Render open modal with `onClose` mock
**Action**: Click the backdrop `<div>` (the outermost fixed div)
**Expected**: `onClose` is called

### Key Scenario: Card Click Does Not Close Modal

**Setup**: Render open modal with `onClose` mock
**Action**: Click inside the white card `<div>`
**Expected**: `onClose` is NOT called (`stopPropagation` blocks the click)

### Key Scenario: Body Scroll Locked While Open

**Setup**: `document.body.style.overflow` is `''` initially
**Action**: Render open modal
**Expected**: `document.body.style.overflow === 'hidden'`
**Cleanup**: After unmount or `project` becomes null, `overflow` is restored to `''`

### Key Scenario: Focus Moves to Close Button on Open

**Setup**: Render `<ProjectDetailModal project={null} />`; update to valid project
**Expected**: The close button element receives focus (check `document.activeElement`)

---

## Notes

1. **`renderScreenshot` placement**: `renderScreenshot` is defined as a helper function with explicit arguments `(project, imgError, setImgError)` for testability. Define it as a local function inside the component body (above the `return`).

2. **`whitespace-pre-wrap` on description**: The description field is `BigText` (textarea). Users may enter newlines. `whitespace-pre-wrap` preserves those newlines in the rendered output without injecting `<br>` tags.

3. **`text-right` for description**: The spec requires right-justified description text. This is `text-right` in Tailwind.

4. **`pr-8` on title**: Adds right padding equal to the close button width to prevent title text overlapping the absolutely-positioned close button in the top-right corner.

5. **`object-contain` for screenshot**: The spec says `max-h-[50vh] object-contain` so the full image is visible without cropping. This differs from the tile which uses `object-cover`. The modal prioritises seeing the complete image.

6. **"Check it out" as `<a>` not `<button>`**: Since it opens an external URL, using `<a href>` is semantically correct and does not require `onClick` handler — native browser behaviour handles new tab.

7. **Focus return to tile**: The spec (acceptance criterion 13) requires focus returns to the tile that opened the modal. This is the `ProjectsListView` component's responsibility — it tracks a `ref` to the clicked tile button and calls `tileRef.current?.focus()` after `onClose` is invoked. This modal only manages focus inward (to the close button on open).

8. **`aria-hidden="true"` on backdrop**: The backdrop itself is a presentational overlay; adding `aria-hidden` prevents screen readers from announcing it as a separate interactive region. The `role="dialog"` on the inner card is what screen readers announce.

9. **Re-exported from barrel**: The `index.ts` barrel file is created as part of Plan 06 (after both components exist). It handles all exports including `ProjectDetailModal`. No changes to `index.ts` are required in this plan.
