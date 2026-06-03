# Implementation Plan: CAT-14 — ConfirmRebuildModal Component

## Spec Context

`ConfirmRebuildModal` is the three-state modal that handles confirmation, real-time SSE
streaming progress, and final result display for a cache rebuild operation. It owns the
entire lifecycle: user confirms → POST fires → SSE events stream in → `done` event signals
completion. It is the only component in this epic that makes a network call — using raw
`fetch` with `response.body.getReader()` because `EventSource` does not support POST.

Catalog item: CAT-14  
Specification section: Component 6 (React Admin Panel); AC-30 through AC-32a, AC-34; AC-31a  
Acceptance criteria addressed:
- AC-30: Confirmation modal lists selected components and secondary operations before rebuild.
- AC-31: After confirm, sends POST to `/api/admin/cache/rebuild` with `fetch` + `response.body.getReader()`.
- AC-31a: Checks `response.status` before reading stream; calls `handleAuthError(status)` for 401/403.
- AC-32: Each SSE event is parsed and appended to step list; status icons update in real time.
- AC-32a: Final `done` event transitions to result view with summary message and Close button.
- AC-34: Tailwind CSS only — no external UI libraries.

---

## Dependencies

- **Blocked by**: CAT-12 (`handleAuthError` from `gravitycar-frontend/src/utils/authError.ts`)
- **Blocks**: CAT-15 (`CacheManagementPanel` renders this modal and passes props)
- **Uses**:
  - `gravitycar-frontend/src/utils/authError.ts` — `handleAuthError(status)`
  - `gravitycar-frontend/src/services/api.ts` — JWT token is read from `localStorage.getItem('auth_token')` (same key the axios request interceptor uses at line 34 of `api.ts`)
  - `gravitycar-frontend/src/components/ui/Modal.tsx` — existing modal shell (backdrop + card)

---

## File Changes

### New Files
- `gravitycar-frontend/src/components/admin/ConfirmRebuildModal.tsx` — the three-view modal component
- `gravitycar-frontend/src/components/admin/` — directory must be created (mkdir)

### Modified Files
- none (CAT-15 will import this component)

---

## Implementation Details

### Types

Define a local TypeScript interface for the SSE step data received from the backend:

```typescript
interface CacheStepResultData {
  stepName: string;
  component: string;
  status: 'in_progress' | 'success' | 'failed' | 'skipped';
  errorMessage: string | null;
}

interface DoneEvent {
  done: true;
  success: boolean;
  message: string;
  steps?: CacheStepResultData[];
}
```

These interfaces are local to the file — not exported. They match the `CacheStepResult::toArray()`
shape defined in CAT-02 and the final `CacheRebuildResult::toArray()` shape defined in CAT-03.

### Props Interface

```typescript
interface RebuildOptions {
  components: string[];
  updateSchema: boolean;
  updatePermissions: boolean;
}

interface ConfirmRebuildModalProps {
  isOpen: boolean;
  onClose: () => void;
  options: RebuildOptions;
}
```

`options` is passed down from `CacheManagementPanel` and reflects the current checkbox selections.

### State

```typescript
type ModalView = 'confirm' | 'streaming' | 'result';

const [view, setView]                   = useState<ModalView>('confirm');
const [steps, setSteps]                 = useState<CacheStepResultData[]>([]);
const [resultMessage, setResultMessage] = useState<string>('');
const [resultSuccess, setResultSuccess] = useState<boolean>(false);
```

**Reset on open**: Use a `useEffect` that watches `isOpen`. When `isOpen` becomes `true`, reset
all state back to defaults (`view = 'confirm'`, `steps = []`, etc.) so re-opening after a
previous run starts fresh.

### View 1: Confirm View

Rendered when `view === 'confirm'`.

**Content**:
- Modal title: `"Confirm Cache Rebuild"`
- Section "The following will be rebuilt:" — a `<ul>` list of component display names for each
  value in `options.components`. Use a constant map for display names:
  ```typescript
  const COMPONENT_LABELS: Record<string, string> = {
    metadata:   'Metadata Cache',
    routes:     'API Routes Cache',
    docs:       'Documentation Cache',
    navigation: 'Navigation Cache',
  };
  ```
- Section "Additional operations:" (only rendered if `options.updateSchema || options.updatePermissions`):
  - "Database schema will be updated" (if `updateSchema`)
  - "Permissions will be rebuilt" (if `updatePermissions`)
- Informational note: `"A backup archive will be created before clearing any files."`
- Button row (right-aligned):
  - "Cancel" button — `onClick={onClose}`, grey styling
  - "Rebuild Cache" button — danger-styled (`bg-red-600 hover:bg-red-700 text-white`), `onClick={handleConfirm}`

### View 2: Streaming View

Rendered when `view === 'streaming'`.

**Content**:
- Modal title: `"Rebuilding Cache..."`
- Spinner at the top (animated SVG or Tailwind `animate-spin` div)
- Step list: `steps.map(step => <StepRow key={...} step={step} />)`
  - If `steps` is empty, show placeholder text `"Starting rebuild..."`
- No buttons (no cancel possible during streaming)

**`StepRow` sub-component** (inline function component inside the file):

```typescript
function StepRow({ step }: { step: CacheStepResultData }): JSX.Element
```

Renders one row per step showing:
- Status icon (left side):
  - `in_progress` → animated spinner (Tailwind `animate-spin`, grey or blue)
  - `success` → green checkmark SVG (`text-green-600`)
  - `failed` → red X SVG (`text-red-600`)
  - `skipped` → grey dash (`text-gray-400`, `—`)
- Step label: use a label map for human-readable names, e.g.:
  ```typescript
  const STEP_LABELS: Record<string, string> = {
    archive:             'Creating archive',
    clear:               'Clearing cache',
    rebuild:             'Rebuilding cache',
    validate:            'Validating cache',
    schema_update:       'Updating schema',
    permissions_update:  'Updating permissions',
    restore:             'Restoring archive',   // valid: emitted on archive restore after failure
  };
  const label = STEP_LABELS[step.stepName] ?? step.stepName;
  ```
  Displayed as `"{label} ({step.component})"` — e.g., `"Creating archive (all)"`, `"Restoring archive (all)"`
- Error message (only if `step.errorMessage`): shown in red below the label, `text-sm text-red-500`

### View 3: Result View

Rendered when `view === 'result'`.

**Content**:
- Modal title: `"Rebuild Complete"` or `"Rebuild Failed"` depending on `resultSuccess`
- Summary message: `resultMessage` (the `message` field from the `done` event)
- Full step list (same `StepRow` rendering as streaming view, now all steps are final)
- Success icon (large green checkmark) if `resultSuccess`, error icon (large red X) if not
- Single "Close" button (`onClick={onClose}`)

### SSE Streaming Logic — `handleConfirm()`

This is the core of the component. Called when the user clicks "Rebuild Cache" in the confirm view.

```typescript
async function handleConfirm(): Promise<void> {
  try {
    setView('streaming');
    setSteps([]);

    const token = localStorage.getItem('auth_token');
    const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8081';

    const response = await fetch(`${baseUrl}/api/admin/cache/rebuild`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify(options),
    });

    // Check status BEFORE reading stream body
    if (response.status === 401 || response.status === 403) {
      handleAuthError(response.status);
      return;
    }

    if (!response.ok || !response.body) {
      setResultMessage(`Unexpected error: HTTP ${response.status}`);
      setResultSuccess(false);
      setView('result');
      return;
    }

    // Read SSE stream
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });
      const events = buffer.split('\n\n');
      buffer = events.pop() ?? '';   // last partial chunk stays in buffer

      for (const event of events) {
        processEvent(event.trim());
      }
    }
  } catch (err) {
    // Network error, failed fetch, or mid-stream reader failure
    setResultMessage('Network error — could not reach server.');
    setResultSuccess(false);
    setView('result');
  }
}
```

**`processEvent(rawEvent: string): void`** — extracts the JSON payload from one SSE event:

```typescript
function processEvent(rawEvent: string): void {
  if (!rawEvent.startsWith('data:')) return;

  const jsonStr = rawEvent.replace(/^data:\s*/, '');
  let parsed: CacheStepResultData | DoneEvent;
  try {
    parsed = JSON.parse(jsonStr);
  } catch {
    return;  // Malformed event — skip silently
  }

  if ((parsed as DoneEvent).done === true) {
    const doneEvent = parsed as DoneEvent;
    setResultMessage(doneEvent.message);
    setResultSuccess(doneEvent.success);
    setView('result');
    return;
  }

  // Regular step event
  const stepData = parsed as CacheStepResultData;
  setSteps(prev => {
    // Find existing step with same stepName+component and replace it (in_progress → final status)
    const existingIdx = prev.findIndex(
      s => s.stepName === stepData.stepName && s.component === stepData.component
    );
    if (existingIdx !== -1) {
      const updated = [...prev];
      updated[existingIdx] = stepData;
      return updated;
    }
    return [...prev, stepData];
  });
}
```

**Why upsert instead of append**: The backend emits two events per step — one `in_progress`
event when a step starts and one final (`success`/`failed`/`skipped`) event when it completes.
The frontend upserts by `(stepName, component)` key so the step row transitions in place
rather than appearing twice in the list.

**Error handling for `fetch`**:
- Network errors (no response): wrap `handleConfirm` in a `try/catch`. On `catch`, set
  `resultMessage = 'Network error — could not reach server.'`, `resultSuccess = false`,
  `setView('result')`.
- Non-200 non-401/403 response: handled by the `!response.ok` guard above.

### JWT Token Attachment

The token is read directly from `localStorage.getItem('auth_token')` — the same key used by
the axios request interceptor in `api.ts` (line 34: `const token = localStorage.getItem('auth_token')`).
This avoids any coupling to the axios instance while replicating its exact behavior.

`baseUrl` is read from `import.meta.env.VITE_API_BASE_URL` with the same fallback as `api.ts`.

### Modal Shell

The component uses the existing `Modal` component from `components/ui/Modal.tsx` as the outer
shell. This provides the backdrop, card, and scroll behaviour. The `title` prop changes per view.
Backdrop-click (`onClose`) is suppressed during streaming — pass a no-op for `onClose` while
`view === 'streaming'`, then restore `onClose` for confirm and result views.

```typescript
const handleClose = view === 'streaming' ? () => {} : onClose;
```

### Component Structure

```
ConfirmRebuildModal (FC)
  ├── useEffect (reset on isOpen change)
  ├── handleConfirm (async, SSE logic)
  ├── processEvent (pure helper)
  ├── StepRow (inline FC)
  └── JSX
       ├── <Modal isOpen={isOpen} onClose={handleClose} title={...}>
       │    ├── [view === 'confirm']   → ConfirmView
       │    ├── [view === 'streaming'] → StreamingView
       │    └── [view === 'result']   → ResultView
       └── </Modal>
```

### Full Component Signature

```typescript
import React, { useState, useEffect } from 'react';
import Modal from '../ui/Modal';
import { handleAuthError } from '../../utils/authError';

interface CacheStepResultData { ... }
interface DoneEvent { ... }
interface RebuildOptions { ... }
interface ConfirmRebuildModalProps { ... }

const COMPONENT_LABELS: Record<string, string> = { ... };

function StepRow({ step }: { step: CacheStepResultData }): JSX.Element { ... }

const ConfirmRebuildModal: React.FC<ConfirmRebuildModalProps> = ({
  isOpen,
  onClose,
  options,
}) => {
  // state declarations
  // useEffect for reset
  // handleConfirm
  // processEvent
  // handleClose = view === 'streaming' ? () => {} : onClose
  // return <Modal ...>...</Modal>
};

export default ConfirmRebuildModal;
```

**File length estimate**: ~200 lines. Well within the 300-line limit.

---

## Error Handling

| Condition | Handling |
|-----------|----------|
| `response.status === 401` | Call `handleAuthError(401)` → clears localStorage, redirects to `/login`. Return early without transitioning to result view. |
| `response.status === 403` | Call `handleAuthError(403)` → navigates to `/unauthorized`. Return early. |
| `response.ok === false` (other status) | Set `resultMessage = 'Unexpected error: HTTP {status}'`, `resultSuccess = false`, transition to result view. |
| `response.body === null` | Same as non-ok response above. |
| `JSON.parse` fails on event | Skip the event silently (`return` inside try/catch). |
| Network error (fetch throws) | Caught by the outer `try/catch` wrapping all `handleConfirm` logic. Sets `resultMessage = 'Network error — could not reach server.'`, `resultSuccess = false`, transitions to result view. |
| `reader.read()` throws mid-stream | Same outer `try/catch` — same error result and view transition. |

The complete `handleConfirm` function wraps ALL fetch and stream logic in a single `try/catch` — see the code example above. The catch block always sets `resultMessage = 'Network error — could not reach server.'`, `resultSuccess = false`, and calls `setView('result')`.

---

## Unit Test Specifications

**Test file**: `gravitycar-frontend/src/components/admin/ConfirmRebuildModal.test.tsx`

Use Vitest + React Testing Library. Mock `fetch`, `handleAuthError`, and `Modal`.

### Setup

```typescript
import { vi, describe, it, expect, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ConfirmRebuildModal from './ConfirmRebuildModal';

vi.mock('../../utils/authError', () => ({
  handleAuthError: vi.fn(),
}));

vi.mock('../ui/Modal', () => ({
  default: ({ isOpen, children, title }: any) =>
    isOpen ? <div data-testid="modal"><h2>{title}</h2>{children}</div> : null,
}));

const defaultOptions = {
  components: ['metadata', 'routes'],
  updateSchema: true,
  updatePermissions: false,
};

const defaultProps = {
  isOpen: true,
  onClose: vi.fn(),
  options: defaultOptions,
};
```

### Confirm View rendering

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Renders when open | `isOpen=true` | Modal visible | Basic open/close |
| Hidden when closed | `isOpen=false` | Modal not in DOM | `Modal` short-circuits |
| Lists components | `components: ['metadata', 'routes']` | "Metadata Cache" and "API Routes Cache" text visible | Display name mapping |
| Shows updateSchema | `updateSchema: true` | "Database schema will be updated" visible | Conditional section |
| Hides updateSchema | `updateSchema: false, updatePermissions: false` | "Additional operations" section absent | Conditional |
| Shows updatePermissions | `updatePermissions: true` | "Permissions will be rebuilt" visible | Conditional |
| Cancel button calls onClose | Click "Cancel" | `onClose` called | Dismiss |
| Archive note visible | (default) | "backup archive" text present | User info |

### Confirm View → Streaming View transition

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Clicking Rebuild sets view | Mock `fetch` returning a readable stream; click "Rebuild Cache" | Title changes to "Rebuilding Cache..." | View transition |
| Streaming view has no Close button | During stream | No "Close" button | Cannot cancel mid-stream |

### SSE event processing

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Step appended on event | Emit `{stepName:'archive', component:'all', status:'in_progress', errorMessage:null}` | Row with "archive (all)" appears | Step rendering |
| in_progress shows spinner | `status:'in_progress'` | Spinner element present | Status icon |
| success shows checkmark | `status:'success'` | Checkmark indicator present | Status icon |
| failed shows X | `status:'failed', errorMessage:'err'}` | X indicator + "err" text visible | Status icon + error |
| skipped shows dash | `status:'skipped'` | Dash indicator present | Status icon |
| in_progress replaced by success | Emit in_progress then success for same step | Only one row for that step | Upsert logic |
| Error message shown on failed | `{errorMessage:'MetadataEngine error'}` | "MetadataEngine error" text in row | Error display |

### done event → Result View transition

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| done:true transitions to result | Emit `{done:true, success:true, message:'Rebuild complete.'}` | Title changes to "Rebuild Complete" | View transition |
| Success message shown | `message:'Rebuild complete.'` | That text visible | Message display |
| Failure message shown | `{done:true, success:false, message:'Rebuild failed.'}` | "Rebuild Failed" title + "Rebuild failed." text | Failure path |
| Close button appears in result | After done event | "Close" button visible | Result view CTA |
| Close button calls onClose | Click "Close" | `onClose` called | Dismiss |

### Auth error handling

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| 401 calls handleAuthError | `fetch` resolves with `status:401` | `handleAuthError(401)` called | AC-31a |
| 403 calls handleAuthError | `fetch` resolves with `status:403` | `handleAuthError(403)` called | AC-31a |
| 401 does NOT show result view | `status:401` | No transition to result view | handleAuthError handles redirect |
| Non-ok shows error result | `status:500` | Result view with error message | Error path |

### Network error handling

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| fetch throws → error result | `fetch` rejects with `new Error('fail')` | Result view with network error message | Graceful error |

### Key Scenario: Full SSE stream round-trip

**Setup**: Mock `fetch` to return a `Response` with `status: 200` and a `body` `ReadableStream`
that emits these chunks in sequence:

```
data: {"stepName":"archive","component":"all","status":"in_progress","errorMessage":null}\n\n
data: {"stepName":"archive","component":"all","status":"success","errorMessage":null}\n\n
data: {"done":true,"success":true,"message":"Rebuild complete."}\n\n
```

**Action**:
1. Render modal, click "Rebuild Cache"
2. Await all stream events to be processed

**Expected**:
- View transitions: confirm → streaming → result
- Step list has exactly 1 row ("archive (all)") with `success` icon (upsert replaced in_progress)
- Result view shows "Rebuild complete." message

**Why**: Validates the complete SSE lifecycle including the upsert behaviour.

---

## Notes

- The `COMPONENT_LABELS` constant must be defined at module level (not inside the component)
  so it is not re-created on every render.
- The `StepRow` inline function component should be defined outside `ConfirmRebuildModal`'s
  render function to avoid React reconciliation issues (new function reference on every render).
- The `buffer` split + `pop()` pattern correctly handles SSE events split across TCP packets:
  the last partial chunk (no trailing `\n\n`) stays in `buffer` and is prepended to the next
  `read()` result.
- `TextDecoder` is constructed with `{ stream: true }` option so multi-byte UTF-8 characters
  split across chunks are decoded correctly.
- The `useEffect` reset guard (`if (!isOpen) return`) ensures state is only reset when the
  modal opens, not on every isOpen=false render.
- `VITE_API_BASE_URL` must match the value used by `api.ts` — both use the same environment
  variable and the same `'http://localhost:8081'` fallback.
- TypeScript: avoid `any` except where unavoidable in the `processEvent` JSON parse path.
  Use the `CacheStepResultData | DoneEvent` union type and discriminate on the `done` property.
- The `done` event may or may not include a `steps` array (the spec shows the final event
  carrying `done/success/message` but not necessarily the full steps array). The frontend's
  step list is already populated from the incremental events, so the `steps` key on the `done`
  event is optional and can be ignored by the frontend.
- Backdrop click is disabled during streaming (no-op `onClose`) to prevent accidental dismissal
  mid-operation. The `Modal` component's `onClose` prop is used for backdrop click — swapping
  it to a no-op achieves this without any Modal modification.
