# Web Research: Public Home Page / Projects List View (API-Driven Auth Patterns in React SPAs)

## Search Terms Used
- axios interceptor 401 redirect React SPA authentication 2025
- React SPA protected routes backend enforced auth public routes pattern 2025
- React flash of content unauthorized page load auth check loading state
- React auth loading state isLoading pattern prevent flash protected content
- axios interceptor 403 forbidden handling React best practices redirect or show error
- role-based navigation filtering React frontend RBAC nav sidebar best practices
- React SPA public homepage with auth check mixed public private routes pattern portfolio app

---

## Key Findings

### 1. Axios Interceptors for 401/403 Handling

**Summary:**
Response interceptors are the standard mechanism for globally catching API authentication/authorization failures without repeating error-handling logic in every component. The interceptor runs before the calling code gets the response.

**401 (Unauthenticated) Pattern:**
- Catch 401 in the response interceptor, clear local storage (token, user data), and navigate to `/login` using React Router's programmatic navigation.
- After clearing state and redirecting, **always return `Promise.reject(error)`** to halt the promise chain. Without this, calling code may continue executing as if the request succeeded.
- If the axios instance is used against multiple domains, add an origin check so that third-party 401 responses do not erroneously trigger your own logout flow.

**403 (Forbidden) Pattern:**
- A 403 means the user _is_ authenticated but lacks permission for the requested resource — this is semantically distinct from 401.
- Two valid approaches depending on UX goals:
  1. **Redirect to a dedicated `/unauthorized` or `/403` page** — common for role-restricted pages.
  2. **Reject silently (let the component show an inline error)** — better when the resource is one item on a page with mixed permission levels.
- For a portfolio/showcase app where the admin is the only privileged user, redirecting to `/unauthorized` on 403 is the cleaner UX choice.

**Current state in this codebase:** 401 already handled with redirect + localStorage clear. 403 currently rejected without redirect — this is a gap to address.

**Sources:**
- [Handle 401 errors in a cleaner way with Axios interceptors – DEV Community](https://dev.to/idboussadel/handle-401-errors-in-a-cleaner-way-with-axios-interceptors-5hkk)
- [How to stop code execution after a 401 response in Axios – DEV Community](https://dev.to/bhaidar/how-to-stop-code-execution-after-a-401-response-in-axios-1ecc)
- [Unauthorised access page on response 403 in React – Medium](https://medium.com/@_deadlock/unauthorised-access-page-on-response-403-in-react-4eeb8153010c)
- [How to Handle 401 Authentication Error in React with Axios – xjavascript.com](https://www.xjavascript.com/blog/how-to-handle-401-authentication-error-in-axios-and-react/)

**Recommendation:**
Keep the existing 401 interceptor as-is. Add a 403 branch that redirects to an `/unauthorized` page (or back to `/` with a toast/banner). Always return `Promise.reject(error)` in both branches.

---

### 2. Public vs. Protected Route Strategies Where the Backend Enforces Auth

**Summary:**
React Router does not enforce authentication — it only handles navigation. The backend is the true security boundary. The frontend route guard is a UX layer that prevents loading protected UI unnecessarily, not a security layer.

**Standard Approach — `ProtectedRoute` Wrapper Component:**
```
<Route path="/admin/projects" element={
  <ProtectedRoute>
    <ProjectsAdminPage />
  </ProtectedRoute>
} />
```
`ProtectedRoute` checks auth state from context/store:
- If `isLoading` → render a spinner (see section 3)
- If not authenticated → redirect to `/login`
- Otherwise → render `children`

**Mixed Public + Private Routes (Portfolio Pattern):**
- Public routes: `/`, `/projects` (homepage, project showcase) — accessible to anyone
- Private routes: `/admin/*` — require authentication
- The homepage (`/`) is always rendered regardless of auth state; the nav sidebar conditionally shows admin links when authenticated.
- No redirect on visiting `/` — that's the public landing page.

**Backend as Enforcer:**
- All protected API endpoints return 401/403 to unauthenticated/unauthorized callers regardless of what the frontend renders.
- Frontend route guards are purely UX: they prevent rendering a page that would immediately fail its API calls, which avoids a flash-then-redirect experience.
- Never rely solely on frontend guards for security — always enforce on the server.

**Sources:**
- [Protected Routes in React Router – react.wiki](https://react.wiki/router/protected-routes/)
- [React Router 7: Private Routes – Robin Wieruch](https://www.robinwieruch.de/react-router-private-routes/)
- [How to Secure React Routes with Guarded Routes – Edana (2025)](https://edana.ch/en/2025/11/04/protect-react-routes-with-guarded-routes/)
- [Private and Public Routes in React Router v6 – DEV Community](https://dev.to/nightfury/private-protected-and-public-routes-in-react-router-v6-with-real-time-mern-stack-example-3fmk)

**Recommendation:**
Wrap only admin/protected pages in a `ProtectedRoute` component. The public projects list view does not need wrapping — it can fetch from a public API endpoint and render for all visitors. The API call on that page will either return data (public endpoint) or be filtered by the backend if role-based visibility is added later.

---

### 3. Flash-of-Content (FOUC) When a Guest Navigates to a Protected URL

**Summary:**
The "flash" problem occurs because auth state is checked asynchronously (via an API call on mount), but route rendering is synchronous. Without a guard, a user visiting `/admin/projects` directly will briefly see the page (or an empty/skeleton version) before the auth check completes and the redirect fires.

**The `isLoading` Pattern (standard solution for SPAs):**
```tsx
function ProtectedRoute({ children }) {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) return <LoadingSpinner />;         // wait for auth check
  if (!isAuthenticated) return <Navigate to="/login" />;
  return children;
}
```

Key details:
- `isLoading` must be `true` **until** the auth API call resolves (not just until the component mounts).
- A full-page spinner or blank screen is preferable to showing partial protected content.
- Once auth resolves, subsequent client-side navigations (within the same session) are instant — no re-check needed because the token is already in memory.

**Initializing Auth State on App Load:**
- On app startup, call a `/api/me` or `/api/auth/status` endpoint.
- While that request is in-flight, set `isLoading = true` globally (in AuthContext).
- When it resolves: set `isLoading = false` and `isAuthenticated = true/false`.
- This single startup check eliminates flashing on direct URL visits.

**The "null auth" sub-pattern:**
- Some apps initialize auth state as `null` (not `false`), where `null` means "not yet checked", `false` means "checked, not logged in", and `true` means "checked, logged in."
- `ProtectedRoute` treats `null` the same as `isLoading: true` — render a spinner.
- This avoids a separate boolean and works well for simple apps.

**Sources:**
- [Next.js Redirect Without Flashing Content – theodorusclarence.com](https://theodorusclarence.com/blog/nextjs-redirect-no-flashing)
- [Should You Show the Real URL When Unauthorized? – Medium](https://medium.com/@kamblepratik1137/should-you-show-the-real-url-when-a-user-is-unauthorized-and-how-to-handle-it-right-in-react-b757f6b86b97)
- [Content Flash in Protected Route with React HOC – Auth0 Community](https://community.auth0.com/t/content-flash-in-protected-route-with-react-hoc/122741)
- [The Complete Guide to React User Authentication – Auth0](https://auth0.com/blog/complete-guide-to-react-user-authentication/)

**Recommendation:**
Add an `isLoading` state to `AuthContext`. On app mount, call a lightweight `/api/auth/status` (or `/api/me`) endpoint. Set `isLoading = true` until response arrives. `ProtectedRoute` renders a spinner while `isLoading` is true. This prevents any flash for direct URL navigation to protected routes.

---

### 4. Role-Based Navigation Filtering on the Frontend

**Summary:**
Frontend RBAC (Role-Based Access Control) is a **UX layer only** — it hides menu items the user cannot access in order to avoid misleading them, not to enforce security. Real enforcement is always backend-side.

**Key Principles:**
- Never trust the frontend to enforce access control. Backend APIs must validate roles on every request.
- Hiding nav items for unauthorized users improves UX but provides zero security.
- Keep role-checking logic centralized — avoid scattering `if (user.role === 'admin')` checks throughout components.

**Recommended Pattern — Centralized Permission Check:**
```tsx
// utils/permissions.ts
export function canAccess(user, resource) {
  const roleMap = {
    admin: ['projects:manage', 'users:manage', 'settings:manage'],
    guest: ['projects:view'],
  };
  return roleMap[user?.role]?.includes(resource) ?? false;
}
```

**Nav Filtering:**
```tsx
const navItems = [
  { label: 'Projects', href: '/projects', permission: null },       // public
  { label: 'Admin', href: '/admin', permission: 'projects:manage' }, // admin only
];

// In nav component:
navItems.filter(item => !item.permission || canAccess(user, item.permission))
```

**For a Portfolio App:**
- The nav structure is simple: public items visible always, admin items visible only when `isAuthenticated && user.role === 'admin'`.
- A single `isAuthenticated` check for the sidebar is likely sufficient rather than full RBAC — the only "role" is admin vs. unauthenticated guest.
- The existing pattern (show sidebar only when `isAuthenticated`) is aligned with best practices for this use case.

**Sources:**
- [Implementing RBAC in React 18 with React Router v6 – DEV Community](https://dev.to/m_yousaf/implementing-role-based-access-control-in-react-18-with-react-router-v6-a-step-by-step-guide-1p8b)
- [Choosing the Best Access Control Model for Your Frontend – LogRocket](https://blog.logrocket.com/choosing-best-access-control-model-frontend/)
- [Best Practice for Role-Based Authorization in a React SPA – Auth0 Community](https://community.auth0.com/t/best-practice-for-role-based-or-permission-based-authorization-in-a-react-spa/194364)
- [How to Use ReactJS for Secure RBAC – Cerbos](https://www.cerbos.dev/blog/how-to-use-react-js-for-secure-role-based-access-control)

**Recommendation:**
For this portfolio app, keep nav filtering simple: show admin nav items only when `isAuthenticated`. No full RBAC library needed. If multi-role support is added later, introduce a centralized `canAccess(user, permission)` utility.

---

## Recommended Approaches (for this project)

1. **Keep existing 401 interceptor** — it already handles unauthenticated redirect correctly.
2. **Add 403 handling** to the axios interceptor: redirect to a `/unauthorized` page or to `/` with an error banner. Return `Promise.reject(error)` after.
3. **Add `isLoading` to AuthContext** — initialize as `true`, set to `false` after a startup `/api/me` call resolves. `ProtectedRoute` renders a spinner while loading.
4. **Public homepage/projects list** — render without a `ProtectedRoute` wrapper; the page calls a public API endpoint. Auth state can still be read from context to conditionally show edit controls.
5. **Nav filtering** — current pattern (sidebar only when authenticated) is correct for a portfolio app. No changes needed unless roles are added.

---

## Potential Pitfalls

- **Missing `Promise.reject()` after interceptor redirect** — calling code continues executing, potentially causing double-navigation or component errors.
- **Skipping `isLoading` guard** — ProtectedRoute redirects to `/login` on every direct URL visit before auth resolves, creating a bad UX loop.
- **Using auth state as `false` before first check** — treat uninitialized auth as neither true nor false; use `null` or a separate `isLoading` flag.
- **Trusting frontend-only auth** — always enforce on the server; frontend guards are UX, not security.
- **403 ≠ 401** — do not log the user out on a 403; they are authenticated but simply lack permission. Redirect to `/unauthorized`, not `/login`.
- **Storing tokens in memory only** — direct URL visits after browser refresh will fail if tokens are not persisted (localStorage/sessionStorage).

---

## Libraries/Services to Consider

- **React Router v6+ `<Navigate>`** — built-in declarative redirect inside ProtectedRoute; no external library needed.
- **React Context API** — sufficient for auth state in a portfolio-scale app; no need for Redux or Zustand.
- **axios** — already in use; interceptors cover all auth error handling globally.
- **react-hot-toast / sonner** — lightweight toast libraries for displaying "You need to log in" messages after redirect; good UX complement to silent redirects.

No additional authentication libraries (Auth0, Clerk, NextAuth) are needed — this app uses a custom PHP backend for auth.
