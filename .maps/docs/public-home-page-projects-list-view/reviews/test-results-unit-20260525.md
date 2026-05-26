# Test Results: Unit — 2026-05-25

## Summary
- Total tests: 39
- Passed: 39
- Failed: 0
- Skipped: 0
- Test files written: 5
- Test runner: vitest v4.1.7 (newly configured)

## Test Files Written

### 1. `src/utils/__tests__/redirectPath.test.ts`
Tests `getRedirectPath()` — the shared post-login redirect validation utility.

**Tests (8):**
- returns a valid relative path starting with /
- returns the root path when redirect is exactly /
- preserves query strings in the redirect path
- returns / when redirect param is absent
- returns / when redirect is an empty string
- rejects protocol-relative URLs starting with //
- rejects absolute URLs starting with https://
- rejects relative paths without a leading slash

**Acceptance Criteria covered:** AC-14

---

### 2. `src/utils/__tests__/navigate.test.ts`
Tests `setNavigator()` and `imperativeNavigate()` — the module-level navigation singleton.

**Tests (4):**
- calls the stored navigator function with the path
- forwards NavigateOptions to the stored navigator
- uses the most recently registered navigator when overwritten
- falls back to window.location.href when navigator is null

**Acceptance Criteria covered:** AC-5 (prerequisite), AC-13 (prerequisite)

---

### 3. `src/services/__tests__/api.interceptors.test.ts`
Tests the axios request and response interceptors in `api.ts`.

**Tests (15):**

Request interceptor — XDEBUG_TRIGGER:
- adds XDEBUG_TRIGGER when DEV is true and params is undefined
- merges XDEBUG_TRIGGER with existing params when DEV is true
- does NOT add XDEBUG_TRIGGER when DEV is false
- leaves existing params untouched when DEV is false

Response interceptor — 401 handling:
- redirects to /login with encoded path on 401 backend error
- appends path + search to redirect URL on 401 fallback
- rejects the promise after 401 backend redirect

Response interceptor — 403 handling:
- calls imperativeNavigate to /unauthorized on 403 backend error
- calls imperativeNavigate to /unauthorized on 403 fallback HTTP error
- does NOT call imperativeNavigate when already on /unauthorized (loop guard — backend error branch)
- does NOT call imperativeNavigate when already on /unauthorized (loop guard — fallback branch)
- does NOT clear localStorage on 403 — user remains authenticated
- rejects the promise after 403 backend error
- does NOT redirect to /login on 403 — only /unauthorized

Response interceptor — network error:
- rejects with a network error message when there is no response

**Acceptance Criteria covered:** AC-4, AC-5, AC-11, AC-13

---

### 4. `src/components/auth/__tests__/Login.test.tsx`
Tests `Login` component `handleSubmit()` — post-login navigation with redirect support.

**Tests (7):**
- navigates to redirect path when ?redirect= is a valid relative path
- navigates to / when no ?redirect= param is present
- navigates to / when ?redirect= value is an invalid protocol-relative URL
- navigates to / when ?redirect= value is an absolute URL
- shows error message and does NOT navigate when login returns success:false
- shows a fallback error message when login returns success:false with no message
- shows a generic error message and does NOT navigate when login throws

**Acceptance Criteria covered:** AC-14

---

### 5. `src/components/auth/__tests__/GoogleSignInButton.test.tsx`
Tests `GoogleSignInButton` `handleGoogleSuccess()` — post-login navigation with redirect support.

**Tests (5):**
- navigates to redirect path when ?redirect= is valid and login succeeds
- navigates to / when no ?redirect= param is present
- does NOT navigate when loginWithGoogle returns success:false
- does NOT navigate when the credential is missing
- does NOT navigate when loginWithGoogle throws

**Acceptance Criteria covered:** AC-14

---

## Pre-existing Test File Issue

`src/components/navigation/__tests__/NavigationSidebar.test.tsx` — this file exists in the repo
but uses Jest API (`jest.mock`, `jest.fn`) instead of vitest. It failed because it was written
before vitest was configured. This file is NOT in scope for this epic's changes; a separate
task should migrate it from Jest to vitest syntax.

## Notes

- Vitest v4.1.7 was installed and `vite.config.ts` was updated to include `test` configuration.
- A `src/test/setup.ts` setup file was created to import `@testing-library/jest-dom`.
- `package.json` was updated to add `"test": "vitest run"` and `"test:watch": "vitest"` scripts.
- `act(...)` warnings appear in test output for Login/GoogleSignInButton tests but are non-critical;
  all assertions pass because `waitFor` correctly handles async React state updates.
