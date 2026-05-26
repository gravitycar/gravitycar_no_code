# Implementation Plan: Navigation Singleton Module

## Spec Context

This plan implements the module-level navigation singleton described in §6 of the specification ("Navigation Singleton Pattern"). It creates `gravitycar-frontend/src/utils/navigate.ts`, which enables imperative React Router navigation from outside the React component tree — specifically from the axios response interceptor in `api.ts`.

Catalog item: 1 — Navigation Singleton Module  
Specification sections: §4.5 (403 interceptor), §6 (Navigation Singleton Pattern)  
Acceptance criteria addressed: AC-5 (prerequisite), AC-13 (prerequisite)

---

## Dependencies

- **Blocked by**: nothing — no other catalog items are required first
- **Blocks**: Item 2 (403 Interceptor — imports `imperativeNavigate`), Item 7 (App.tsx Route Overhaul — renders `NavigatorSetter`)
- **Uses**: `react-router-dom` (already installed) — `NavigateFunction` and `NavigateOptions` types, `useNavigate` hook

---

## File Changes

### New Files

- `gravitycar-frontend/src/utils/navigate.ts` — module-level navigation singleton plus `NavigatorSetter` component

### Modified Files

- None — this plan creates only the new utility module. `App.tsx` integration is handled by Item 7.

---

## Implementation Details

### `navigate.ts` — Navigation Singleton + NavigatorSetter

**File**: `gravitycar-frontend/src/utils/navigate.ts`

**Design decision**: The `NavigatorSetter` component is defined in this file (not in `App.tsx`). This keeps `App.tsx` a pure route table and avoids scattering the singleton's collaborating code across files. `App.tsx` will simply import and render `<NavigatorSetter />`.

**Exports**:
- `setNavigator(fn: NavigateFunction): void` — stores the navigator reference
- `imperativeNavigate(path: string, options?: NavigateOptions): void` — calls stored navigator or falls back to `window.location.href`
- `NavigatorSetter` (default or named export, React component) — calls `useNavigate()` and registers the result on mount

**Module-level state**:

```typescript
import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import type { NavigateFunction, NavigateOptions } from 'react-router-dom';

// Module-level singleton — intentionally mutable module state
let navigator: NavigateFunction | null = null;
```

**`setNavigator` implementation**:

```typescript
export function setNavigator(fn: NavigateFunction): void {
  navigator = fn;
}
```

**`imperativeNavigate` implementation**:

```typescript
export function imperativeNavigate(path: string, options?: NavigateOptions): void {
  if (navigator) {
    navigator(path, options);
    return;
  }
  // Fallback for startup edge case: navigator not yet registered
  window.location.href = path;
}
```

Note: The fallback does not honour `options` (such as `replace: true`) because `window.location.href` assignment always adds a history entry. This is acceptable — the fallback is only hit if `imperativeNavigate` is called before the React tree has mounted (an extremely narrow startup window). The spec explicitly acknowledges this limitation in §6.

**`NavigatorSetter` component**:

```typescript
export function NavigatorSetter(): null {
  const navigate = useNavigate();

  useEffect(() => {
    setNavigator(navigate);
  }, [navigate]);

  return null;
}
```

Key points:
- Returns `null` — renders nothing, exists only for its side effect.
- `useEffect` dependency on `navigate` is correct: `navigate` is stable across renders in React Router v6 (the reference does not change on re-render), so the effect runs exactly once after mount.
- The component MUST be rendered inside a `<Router>` context (i.e., inside `<BrowserRouter>`) because it calls `useNavigate()`. In `App.tsx` it will be placed inside `<Router>` but outside `<Routes>`.

**Complete file**:

```typescript
import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import type { NavigateFunction, NavigateOptions } from 'react-router-dom';

let navigator: NavigateFunction | null = null;

export function setNavigator(fn: NavigateFunction): void {
  navigator = fn;
}

export function imperativeNavigate(path: string, options?: NavigateOptions): void {
  if (navigator) {
    navigator(path, options);
    return;
  }
  window.location.href = path;
}

export function NavigatorSetter(): null {
  const navigate = useNavigate();

  useEffect(() => {
    setNavigator(navigate);
  }, [navigate]);

  return null;
}
```

This file is intentionally minimal — under 30 lines with no imports other than react and react-router-dom.

---

## Error Handling

- No runtime errors are expected. The `navigator` variable may be `null` at startup; `imperativeNavigate` handles that with the `window.location.href` fallback.
- There is no cleanup needed: the singleton lives for the application lifetime. If `NavigatorSetter` unmounts and remounts (e.g., during hot reload), `setNavigator` is called again with the fresh reference — this is safe.

---

## Unit Test Specifications

Because `navigate.ts` calls `useNavigate()` (a React hook), tests for `NavigatorSetter` require a React testing context. The utility functions `setNavigator` and `imperativeNavigate` are plain functions and can be tested without React.

### `setNavigator` and `imperativeNavigate`

| Case | Setup | Action | Expected | Why |
|------|-------|--------|----------|-----|
| Navigator set, navigate called | Call `setNavigator(mockFn)` | `imperativeNavigate('/foo')` | `mockFn('/foo', undefined)` called | Happy path — registered navigator is used |
| Navigator set with options | Call `setNavigator(mockFn)` | `imperativeNavigate('/bar', { replace: true })` | `mockFn('/bar', { replace: true })` called | Options are forwarded to navigator |
| Navigator not set (fallback) | Do not call `setNavigator` | `imperativeNavigate('/baz')` | `window.location.href` is set to `/baz` | Fallback for startup edge case |
| Overwrite navigator | `setNavigator(fn1)` then `setNavigator(fn2)` | `imperativeNavigate('/x')` | `fn2('/x', undefined)` called, `fn1` not called | Latest registration wins |

**Key Scenario: Fallback to window.location.href**  
**Setup**: Module is freshly imported; `setNavigator` has not been called (or test resets `navigator` to null by not calling `setNavigator`).  
**Action**: `imperativeNavigate('/baz')`  
**Expected**: `window.location.href === '/baz'` (use `Object.defineProperty` or `jsdom` mock to spy on assignment)  

### `NavigatorSetter` component

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Mounts inside Router | Render `<MemoryRouter><NavigatorSetter /></MemoryRouter>` | `navigator` module variable is non-null after mount | `setNavigator` was called with `useNavigate()` result |
| Renders nothing | Render `<MemoryRouter><NavigatorSetter /></MemoryRouter>` | Component output is `null` (renders no DOM nodes) | Component is side-effect-only |

**Test setup note**: Tests that call `imperativeNavigate` must reset the module-level `navigator` to `null` between tests. Because Jest caches modules, the simplest approach is to import the module normally and call `setNavigator(null as any)` in `beforeEach`, or use `jest.resetModules()` for isolation.

---

## Notes

- **Where to place `NavigatorSetter` in `App.tsx`**: It must be a sibling of `<AppRoutes />` (or rendered anywhere inside the `<Router>` but outside `<Routes>`). The exact placement will be specified in the Item 7 plan. The `NavigatorSetter` component exported from this file is the authoritative location.
- **React Router v6 stability of `navigate`**: `useNavigate()` returns a stable function reference in React Router v6 — it does not change between renders. The `useEffect` dependency array including `[navigate]` is correct (required by hooks linting rules) and will not cause the effect to re-run unnecessarily.
- **No TypeScript strict-null issues**: `navigator` is typed as `NavigateFunction | null`. `imperativeNavigate` checks for null before calling. No `!` non-null assertions are needed.
- **ESLint**: The file should not need any `eslint-disable` comments. The mutable module variable is intentional and does not violate any project rule — it is the documented singleton pattern.
- **File length**: The complete file is under 30 lines — well within the 300-line project limit.
