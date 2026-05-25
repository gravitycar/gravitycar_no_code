# Implementation Plan: Post-Login Redirect in Login Component

## Spec Context

After a successful login, the `Login` component currently has no post-login navigation logic of
its own — the comment in `handleSubmit` says "the auth context will handle the redirect", but
`useAuth.login()` only updates auth state and returns the response; it does not navigate anywhere.
Navigation after login is currently missing entirely from the component. This plan adds explicit
post-login navigation to both the credential login path (`handleSubmit`) and the Google login
path (`GoogleSignInButton`), reading a `?redirect=` query parameter to send the user back to the
page they were trying to reach.

Catalog item: Post-Login Redirect in Login Component (Item 6)
Specification section: §4.10
Acceptance criteria addressed: AC-14

## Dependencies

- **Blocked by**: nothing — operates independently; Item 2 (403 Interceptor in api.ts) writes the
  `?redirect=` param into the 401 redirect URL, but `Login.tsx` can be updated independently.
- **Uses**: `react-router-dom` (already a project dependency) — `useNavigate`, `useSearchParams`.
- **Uses**: `useAuth` hook from `gravitycar-frontend/src/hooks/useAuth.tsx` (already imported).

---

## File Changes

### New Files

- `gravitycar-frontend/src/utils/redirectPath.ts` — shared utility exporting `getRedirectPath(searchParams)`. Used by `Login.tsx`, `GoogleSignInButton.tsx`, and `PublicRoute` in `App.tsx` (3 use cases — meets the project threshold for extraction per CLAUDE.md).

### Modified Files

- `gravitycar-frontend/src/components/auth/Login.tsx` — add `useNavigate` + `useSearchParams`, import `getRedirectPath` from `utils/redirectPath`, call `navigate(getRedirectPath(searchParams))` on successful login.
- `gravitycar-frontend/src/components/auth/GoogleSignInButton.tsx` — add `useNavigate` + `useSearchParams`, import `getRedirectPath` from `utils/redirectPath`, call `navigate(getRedirectPath(searchParams))` on successful Google login.

---

## Implementation Details

### New File: `gravitycar-frontend/src/utils/redirectPath.ts`

This pure utility encapsulates the redirect validation logic shared across `Login.tsx`, `GoogleSignInButton.tsx`, and `PublicRoute` in `App.tsx`.

```typescript
/**
 * Returns the post-login navigation target from a ?redirect= query param.
 * Accepts only relative paths (starting with /) to prevent open redirect attacks.
 * Rejects protocol-relative URLs (starting with //) that could redirect off-site.
 */
export function getRedirectPath(searchParams: URLSearchParams): string {
  const redirect = searchParams.get('redirect');
  if (redirect && redirect.startsWith('/') && !redirect.startsWith('//')) {
    return redirect;
  }
  return '/';
}
```

**Validation rules** (per §4.10 and AC-14):
- Value MUST start with `/`.
- Value MUST NOT start with `//` (protocol-relative URL — would redirect to `//evil.com`).
- If absent or invalid: fall back to `/`.

### Modified Component: `Login.tsx`

**New imports to add**:
```typescript
import { useNavigate, useSearchParams } from 'react-router-dom';
import { getRedirectPath } from '../../utils/redirectPath';
```

**New hooks inside component body** (alongside existing `const { login } = useAuth()`):
```typescript
const navigate = useNavigate();
const [searchParams] = useSearchParams();
```

**Updated `handleSubmit`** — replace the comment `// If successful, the auth context will handle
the redirect` with explicit navigation:

```typescript
const handleSubmit = async (e: React.FormEvent) => {
  e.preventDefault();
  setError('');
  setIsLoading(true);

  try {
    const response = await login(credentials);
    if (!response.success) {
      setError(response.message || 'Login failed');
      return;
    }
    navigate(getRedirectPath(searchParams));
  } catch {
    setError('An unexpected error occurred');
  } finally {
    setIsLoading(false);
  }
};
```

Note: the early `return` on failure avoids the need for an else branch and keeps complexity low.

### Modified Component: `GoogleSignInButton.tsx`

`GoogleSignInButton` is a child component rendered inside `Login`. It must read `?redirect=`
independently — it cannot receive `searchParams` as a prop from `Login` without architectural
changes that are out of scope. Since it is rendered inside the React Router tree, it can call
`useSearchParams()` directly.

**New imports to add**:
```typescript
import { useNavigate, useSearchParams } from 'react-router-dom';
import { getRedirectPath } from '../../utils/redirectPath';
```

**New hooks inside `GoogleSignInButton` body** (alongside existing `const { loginWithGoogle } = useAuth()`):
```typescript
const navigate = useNavigate();
const [searchParams] = useSearchParams();
```

**Updated `handleGoogleSuccess`** — replace the comment-free fall-through after `result.success`
check with explicit navigation:

```typescript
const handleGoogleSuccess = useCallback(async (credentialResponse: CredentialResponse) => {
  setIsLoading(true);
  setError('');

  try {
    if (!credentialResponse.credential) {
      throw new Error('No credential received from Google');
    }
    const result = await loginWithGoogle(credentialResponse.credential);
    if (!result.success) {
      setError(result.message || 'Google login failed');
      return;
    }
    navigate(getRedirectPath(searchParams));
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : 'An error occurred during Google login';
    setError(errorMessage);
  } finally {
    setIsLoading(false);
  }
}, [loginWithGoogle, navigate, searchParams]);
```

The `navigate` and `searchParams` references are added to the `useCallback` dependency array.

---

## Error Handling

- Login API failure (`response.success === false`) → set error message, early return, no navigation.
- Exception thrown by `login()` → set generic error message, no navigation.
- `redirect` param present but invalid (no leading `/`, or starts with `//`) → silently fall back to `/`.
- `redirect` param absent → fall back to `/`.

---

## Unit Test Specifications

### `getRedirectPath(searchParams)` helper

This logic is the core of the feature and should be tested directly. Since it is currently
defined inline, tests will exercise it via the component or it can be extracted to
`gravitycar-frontend/src/utils/redirectPath.ts` if the test author prefers a pure-function test.

| Case | `?redirect=` value | Expected result | Why |
|------|--------------------|-----------------|-----|
| Valid relative path | `/events/42` | `/events/42` | Happy path |
| Root path | `/` | `/` | Edge: minimum valid value |
| Absent param | (none) | `/` | Default fallback |
| Empty string | `` | `/` | Does not start with `/` |
| Protocol-relative | `//evil.com/steal` | `/` | Open redirect prevention |
| Absolute URL | `https://evil.com` | `/` | Does not start with `/` |
| Relative no slash | `events/42` | `/` | Does not start with `/` |
| Path with query | `/events/42?tab=details` | `/events/42?tab=details` | Preserves query string |

### `Login` component — `handleSubmit`

| Case | Setup | Expected |
|------|-------|----------|
| Successful login, redirect present | Mock `login()` returns `{ success: true }`, URL has `?redirect=/events/42` | `navigate('/events/42')` called |
| Successful login, no redirect | Mock `login()` returns `{ success: true }`, no query param | `navigate('/')` called |
| Successful login, invalid redirect | Mock `login()` returns `{ success: true }`, URL has `?redirect=//evil.com` | `navigate('/')` called |
| Failed login | Mock `login()` returns `{ success: false, message: 'Bad credentials' }` | Error shown, `navigate` NOT called |
| Exception thrown | Mock `login()` throws | Generic error shown, `navigate` NOT called |

### `GoogleSignInButton` — `handleGoogleSuccess`

| Case | Setup | Expected |
|------|-------|----------|
| Successful Google login, redirect present | Mock `loginWithGoogle()` returns `{ success: true }`, URL has `?redirect=/events` | `navigate('/events')` called |
| Successful Google login, no redirect | Mock `loginWithGoogle()` returns `{ success: true }`, no query param | `navigate('/')` called |
| Failed Google login | Mock `loginWithGoogle()` returns `{ success: false }` | Error shown, `navigate` NOT called |

### Key Scenario: Valid Redirect After 401 Intercept

**Setup**: User is on `/events/42`. API returns 401. Interceptor redirects to
`/login?redirect=%2Fevents%2F42`. User enters credentials.

**Action**: `handleSubmit` fires with `searchParams.get('redirect')` returning `/events/42`.
`login()` resolves with `{ success: true }`.

**Expected**: `navigate('/events/42')` is called. User lands on `/events/42`.

### Key Scenario: Open Redirect Rejected

**Setup**: URL is `/login?redirect=https%3A%2F%2Fevil.com%2Fsteal`.

**Action**: `handleSubmit` fires after successful login. `searchParams.get('redirect')`
returns `https://evil.com/steal`.

**Expected**: `getRedirectPath` returns `/` because the value does not start with `/`.
`navigate('/')` is called. User lands at `/`.

---

## Notes

- `useSearchParams` from `react-router-dom` is already available in the project (same version
  used by `BatchProposeDates.tsx` which already uses `useNavigate` / `useParams`).
- `GoogleSignInButton` is a child rendered inside `Login`; it is inside the React Router tree
  (wrapped in `<BrowserRouter>` at the app root), so `useSearchParams()` works there without
  any prop drilling.
- The `getRedirectPath` helper is extracted to `utils/redirectPath.ts` and shared across `Login.tsx`, `GoogleSignInButton.tsx`, and `PublicRoute` in `App.tsx` — exactly 3 use cases, meeting the project threshold for extraction (CLAUDE.md: no abstractions with fewer than 3 use cases).
- No changes to `useAuth.tsx` or `apiService` are required by this plan.
- The `console.log` debug lines in `GoogleSignInButton.tsx` (e.g., `console.log('✅ Google
  sign-in successful:', ...)`) are pre-existing. They are not removed by this plan — a
  separate cleanup task can address them.
