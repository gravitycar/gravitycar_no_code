# Codebase Summary: Public Home Page / Projects List View

## Tech Stack

- **Frontend**: React 18 + TypeScript, Vite, React Router v6, Tailwind CSS (no Shadcn/Radix)
- **HTTP client**: Axios (class-based singleton `ApiService` exported as `apiService`)
- **Auth**: JWT stored in `localStorage` (`auth_token` key), user object in `localStorage` (`user` key)
- **Backend API base URL**: `VITE_API_BASE_URL` env var, defaults to `http://localhost:8081`

## Architecture Overview

- `App.tsx` is the root component wrapping `ErrorBoundary > NotificationProvider > AuthProvider > Router > AppRoutes`
- Route guards are inline components (`ProtectedRoute`, `PublicRoute`) defined in `App.tsx`
- Layout wraps page content and shows a header + sidebar + footer
- Navigation sidebar is data-driven: fetches from `/navigation` API, receives a role-filtered list of models and custom_pages from the backend
- Pages live in `gravitycar-frontend/src/pages/`; reusable UI components live in `gravitycar-frontend/src/components/`

---

## Relevant Existing Code

### 1. App.tsx — Route Definitions and Route Guards

**File**: `gravitycar-frontend/src/App.tsx`

**ProtectedRoute**:
- Reads `{ isAuthenticated, isLoading }` from `useAuth()`
- Shows a loading spinner while `isLoading === true`
- Redirects to `/login` if not authenticated; otherwise renders children

**PublicRoute**:
- Reads `{ isAuthenticated, isLoading }` from `useAuth()`
- Redirects to `/dashboard` if already authenticated; otherwise renders children

**Current Routes**:
| Path | Guard | Component |
|------|-------|-----------|
| `/login` | PublicRoute | Login |
| `/dashboard` | ProtectedRoute + Layout | Dashboard |
| `/metadata-test` | ProtectedRoute + Layout | MetadataTestPage |
| `/test-related-record` | ProtectedRoute + Layout | TestRelatedRecord |
| `/movies-quotes-demo` | ProtectedRoute + Layout | MoviesQuotesRelationshipDemo |
| `/trivia` | ProtectedRoute + Layout | TriviaPage |
| `/projects_showcase` | **No guard** + Layout | ProjectsPage |
| `/dnd-chat` | ProtectedRoute + Layout | DnDChatPage |
| `/events` | No guard + Layout | GenericCrudPage (Events) |
| `/events/:eventId/chart` | No guard + Layout | ChartOfGoodness |
| `/events/:eventId/propose-dates` | ProtectedRoute + Layout | BatchProposeDates |
| `/` | — | Navigate to /dashboard |
| `/:modelName` | ProtectedRoute + Layout | DynamicModelRoute (catch-all) |

**Key observation**: `/projects_showcase` is already public (no `ProtectedRoute`). The root `/` redirects authenticated users to `/dashboard` — there is no public home page yet.

### 2. useAuth Hook and AuthProvider

**File**: `gravitycar-frontend/src/hooks/useAuth.tsx`

**AuthContext shape**:
```typescript
{
  user: User | null;
  isAuthenticated: boolean;   // computed as !!user
  isLoading: boolean;
  login(credentials): Promise<AuthResponse>;
  loginWithGoogle(googleToken): Promise<AuthResponse>;
  logout(): Promise<void>;
  checkAuth(): Promise<void>;
}
```

**Initialization flow**:
1. `useState` initializes `user` from `localStorage['user']` immediately (avoids flash)
2. `useEffect` on mount calls `checkAuth()` which hits `GET /auth/me` to validate the stored token
3. If `/auth/me` fails, clears `auth_token` and `user` from localStorage and sets `user = null`
4. `isLoading` starts as `true`, set to `false` after `checkAuth()` resolves

**Auth token**: stored as `localStorage['auth_token']`; the API service reads this on every request via request interceptor.

### 3. API Service

**File**: `gravitycar-frontend/src/services/api.ts`

**Class**: `ApiService` — singleton exported as `apiService` (default and named export).

**Request interceptor**:
- Attaches `Authorization: Bearer <token>` if `localStorage['auth_token']` exists
- Appends `XDEBUG_TRIGGER=mike` to all request params (dev debugging aid)

**Response interceptor (error handling)**:
- **Network error** (no response): rejects with generic "Network error" message
- **401 with backend error format**: clears localStorage, calls `window.location.href = '/login'`
  - Special case: shows `alert()` if session expired due to inactivity
- **403**: rejected with "Access denied" message — does NOT redirect
- **Other HTTP errors**: rejects with status-specific messages

**No 403 redirect**: 403 errors are bubbled to the caller as rejected promises with an error message, not redirected to `/login` or an error page. The caller is responsible for handling them.

**Generic CRUD methods**:
- `getList<T>(model, page, limit, filters?, search?)` — GET `/{model}?page=...`
- `getRecord<T>(model, id)` — GET `/{model}/{id}`
- `create<T>(model, data)` — POST `/{model}`
- `update<T>(model, id, data)` — PUT `/{model}/{id}`
- `delete(model, id)` — DELETE `/{model}/{id}`

### 4. Layout and Navigation

**File**: `gravitycar-frontend/src/components/layout/Layout.tsx`

- Renders: top `<header>` + flex row with `<NavigationSidebar>` (left) + `<main>` (right) + `<footer>`
- `NavigationSidebar` is only rendered when `isAuthenticated === true`
- When unauthenticated, the layout renders header + main + footer **without** the sidebar
- The header "Welcome, {name}" and Logout button are also hidden when unauthenticated
- This means `/projects_showcase` renders in Layout but shows NO sidebar and NO user header items

**File**: `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx`

- Fetches from `GET /navigation` via `navigationService.getCurrentUserNavigation()`
- The backend returns `NavigationData` which includes: `role`, `models[]`, `custom_pages[]`, `sections[]`
- Navigation data is role-filtered **server-side** — the sidebar just renders whatever the backend returns
- `custom_pages` items have a `roles: string[]` field (the backend uses this to filter)
- Models section shows per-model permissions (list/create/update/delete)
- Navigation is re-fetched when `user` changes (via `useEffect([user])`)
- 5-minute client-side cache in `NavigationService`
- Dev mode shows role and permission debug info at the bottom of the sidebar

### 5. Dashboard Page

**File**: `gravitycar-frontend/src/pages/Dashboard.tsx`

- **Requires auth** (wrapped in `ProtectedRoute`)
- On mount, fetches in parallel: Users (page 1, limit 5), Movies (page 1, limit 5), MovieQuotes (page 1, limit 5), Movie_Quote_Trivia_Games (page 1, limit 20)
- Displays:
  - Welcome message with user display name
  - Stats cards: Total Users, Total Movies, Total Quotes
  - Latest Movie Quote Trivia Scores (completed games with score >= 100, top 5)
  - Recent Movies list
  - Quick Actions links (users, movies, quotes, trivia)
- Uses `user` from `useAuth()` for display name

### 6. ProjectsPage and ProjectsListView

**File**: `gravitycar-frontend/src/pages/ProjectsPage.tsx`

- Thin wrapper: renders `<div className="min-h-screen bg-gray-50"><ProjectsListView /></div>`
- Routed at `/projects_showcase` — **public, no auth required**
- Currently wrapped in `<Layout>` but NOT in `<ProtectedRoute>`

**File**: `gravitycar-frontend/src/components/projects/ProjectsListView.tsx`

- Fetches `GET /Projects?page=1&limit=1000` via `apiService.getList<Project>('Projects', 1, 1000)`
- Sorts results by `display_order` ascending (nulls/undefined go last via `Infinity`)
- Renders a 2-column responsive grid of `ProjectTile` components
- Each tile: background image (`project.screenshot`), title, status badge, tagline
- Clicking a tile opens `ProjectDetailModal`
- Falls back to initials avatar if image fails to load
- Status badge colors: Planned (gray), In Progress (amber), Complete (green)

**File**: `gravitycar-frontend/src/components/projects/types.ts`

```typescript
interface Project {
  id: string;
  title: string;
  tag_line: string;
  description: string;
  screenshot: string;
  link?: string;
  status?: string;
  display_order?: number;
}
```

**Note**: The `/Projects` API is called without auth token requirements at the route level, but the API service always attaches `Authorization: Bearer <token>` if a token exists. If no token exists (unauthenticated user), the request is sent without Authorization header — the backend must permit anonymous access to `GET /Projects` for the public showcase to work.

---

## Conventions to Follow

- **No auth guard for public pages**: just omit `<ProtectedRoute>` wrapper in `App.tsx`
- **Layout usage**: always wrap page content in `<Layout>` — it conditionally shows/hides nav based on auth state
- **API calls**: use `apiService.getList()` / `apiService.getRecord()` etc. — singleton pattern
- **Types**: define component-specific types in a `types.ts` file alongside the component
- **Tailwind only**: no Shadcn, Radix, or other UI component libraries
- **Routing**: add new routes to `AppRoutes` in `App.tsx` BEFORE the `/:modelName` catch-all route
- **Navigation sidebar**: backend-driven; to add a nav link for a new page, configure the backend's navigation endpoint to include it in `custom_pages`, not the frontend sidebar component
- **Role filtering**: done server-side at the `/navigation` endpoint; the `CustomPage.roles[]` field controls who sees which nav links

## Reusable Components

- `apiService.getList<T>(modelName, page, limit)` — generic paginated list fetch for any backend model
- `ProtectedRoute` / `PublicRoute` — inline in `App.tsx`, reuse the same pattern for new routes
- `Layout` — wraps any page with header/nav/footer; sidebar auto-hides when unauthenticated
- `ProjectsListView` — already built; fetches and displays projects in a tile grid
- `ProjectDetailModal` — modal for project details, used inside `ProjectsListView`
- `NavigationSidebar` — role-aware sidebar; no changes needed for new public pages

## Gaps and Notes for Implementation

1. **Root `/` redirect**: Currently redirects to `/dashboard` (which requires auth). For a public home page, the `/` route should either be changed to render the public home page OR a new `/home` route added and `/` redirected there.
2. **Public API access**: The `/Projects` endpoint must support unauthenticated access for the public showcase. This may already be configured or may need a backend change.
3. **No 403 handling in nav**: If an unauthenticated user hits the `/navigation` endpoint, it may return an error that causes the sidebar to show an error state — but since `isAuthenticated === false`, the sidebar is not rendered at all on public pages (Layout hides it).
4. **ProjectDetailModal**: Not yet read in detail — may need to be checked for any auth-gated features.
