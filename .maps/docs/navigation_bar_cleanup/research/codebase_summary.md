# Codebase Summary: Navigation Bar Cleanup

## Tech Stack
- **Backend**: PHP 8.2+, Gravitycar Framework (custom metadata-driven MVC)
- **Frontend**: React (TypeScript), Tailwind CSS, React Router
- **No external UI library** — Tailwind-only per project conventions
- **Navigation cache**: PHP files in `cache/navigation_cache_{role}.php`

---

## Architecture Overview

The navigation bar is driven by a **metadata-to-cache pipeline**:

1. Model metadata files define each model's configuration (`src/Models/{model}/{model}_metadata.php`)
2. `MetadataEngine` loads all metadata and caches it at `cache/`
3. `NavigationBuilder` reads available models from `MetadataEngine`, filters by RBAC permissions, and writes per-role navigation cache files to `cache/navigation_cache_{role}.php`
4. The React frontend's `NavigationService` fetches navigation from a backend API endpoint (`/navigation`) which reads from the cache
5. `NavigationSidebar.tsx` renders the navigation data

---

## Relevant Existing Code

### 1. NavigationBuilder
- **File**: `src/Services/NavigationBuilder.php`
- **Key method**: `buildModelNavigation(array $modelNames, string $role): array`
- **Current behavior**:
  - Gets all available model names from `MetadataEngine::getAvailableModels()`
  - For each model, checks RBAC permission for `list` action; skips if not allowed
  - Builds a `$modelItem` array: `{name, title, url, icon, actions, permissions}`
  - Appends each to a flat array: `$modelNavigation[] = $modelItem`
  - Sorts alphabetically by title
  - Returns the flat array
- **Does NOT currently read model metadata** — no call to `getModelMetadata()` inside `buildModelNavigation()`
- **Cache writing**: `writeNavigationCache(string $cacheFile, array $navigation): void` serializes to `<?php return var_export($navigation); ?>`
- **Roles cached**: `admin`, `manager`, `user`, `guest` (hardcoded in `buildAllRoleNavigationCaches()`)

### 2. Navigation Cache Files
- **Location**: `cache/navigation_cache_{role}.php` (one per role)
- **Structure**:
```php
[
  'role' => 'admin',
  'sections' => [ ['key' => 'main', 'title' => 'Main Navigation'], ... ],
  'custom_pages' => [ ['key' => ..., 'title' => ..., 'url' => ..., 'icon' => ..., 'roles' => [...]], ... ],
  'models' => [
    ['name' => 'Books', 'title' => 'Books', 'url' => '/Books', 'icon' => '📚',
     'actions' => [ ['key' => 'create', 'title' => 'Create New', 'action' => 'create', 'icon' => '➕'] ],
     'permissions' => ['list' => true, 'create' => true, 'update' => true, 'delete' => true]
    ],
    ...
  ],
  'generated_at' => '2026-06-06T15:52:11+00:00'
]
```
- The `models` array is currently **flat** — no grouping support

### 3. MetadataEngine
- **File**: `src/Metadata/MetadataEngine.php`
- **`getAvailableModels(): array`** — returns `array_keys` of all models from the metadata cache
- **`getModelMetadata(string $modelName): array`** — returns full metadata for a model; throws `GCException` if not found; uses exact case-sensitive key match
- Metadata cache is loaded at construction time; uses file-based persistent cache at `cache/`

### 4. MetadataEngine::getModelMetadata() return structure
Returns the array from the model's `*_metadata.php` file directly. Top-level keys currently in use:
- `name` — model class name (e.g., `'GoogleOauthTokens'`)
- `table` — database table name
- `fields` — associative array of field definitions
- `rolesAndActions` — per-role permission arrays
- `validationRules` — model-level validation
- `relationships` — array of relationship names
- `ui` — listFields, createFields, editFields, etc.
- `displayColumns` — columns shown in list views
- `apiRoutes` — custom API routes (optional)
- `uniqueConstraints` — unique DB constraints (optional)
- **No `navigation_bar` property currently exists in any metadata file**

### 5. Model Metadata Files — All Existing Models

| Model Name (class) | Metadata File | Notes |
|---|---|---|
| `Books` | `src/Models/books/books_metadata.php` | Public-facing |
| `EmailQueue` | `src/Models/emailqueue/email_queue_metadata.php` | Internal |
| `EventCommitments` | `src/Models/eventcommitments/event_commitments_metadata.php` | Event-related |
| `EventProposedDates` | `src/Models/eventproposeddates/event_proposed_dates_metadata.php` | Event-related |
| `EventReminders` | `src/Models/eventreminders/event_reminders_metadata.php` | Event-related |
| `Events` | `src/Models/events/events_metadata.php` | Event-related |
| `GoogleOauthTokens` | `src/Models/googleoauthtokens/googleoauthtokens_metadata.php` | Auth-internal: should be hidden |
| `Installer` | `src/Models/installer/installer_metadata.php` | Utility |
| `JwtRefreshTokens` | `src/Models/jwtrefreshtokens/jwt_refresh_tokens_metadata.php` | Auth-internal: should be hidden |
| `Movie_Quote_Trivia_Games` | `src/Models/movie_quote_trivia_games/movie_quote_trivia_games_metadata.php` | Trivia |
| `Movie_Quote_Trivia_Questions` | `src/Models/movie_quote_trivia_questions/movie_quote_trivia_questions_metadata.php` | Trivia |
| `Movie_Quotes` | `src/Models/movie_quotes/movie_quotes_metadata.php` | Movies-related |
| `Movies` | `src/Models/movies/movies_metadata.php` | Public-facing |
| `Permissions` | `src/Models/permissions/permissions_metadata.php` | Admin |
| `Projects` | `src/Models/projects/projects_metadata.php` | Public-facing |
| `Roles` | `src/Models/roles/roles_metadata.php` | Admin |
| `Users` | `src/Models/users/users_metadata.php` | Admin |

**Total: 17 models** (16 visible in admin nav cache currently — Installer also appears)

### 6. Models to Hide (navigation_bar: false)
Per spec, these should be excluded:
- `GoogleOauthTokens` — strictly backend auth token storage
- `JwtRefreshTokens` — strictly backend auth token storage

### 7. Models to Group Under "Event Organizer"
Per spec, group these under a single parent nav item:
- `Events`
- `EventCommitments`
- `EventReminders`
- `EventProposedDates`

### 8. Frontend Navigation — NavigationSidebar
- **File**: `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx`
- **Loaded via**: `navigationService.getCurrentUserNavigation()` which calls `GET /navigation`
- **Two separate rendering sections**:
  1. **Custom Pages** (`navigationData.custom_pages`) — rendered using `groupCustomPages()` utility which groups pages by key prefix (e.g., `events_create` becomes child of `events`)
  2. **Models** (`navigationData.models`) — rendered as a flat list (currently no grouping logic)
- **Model item rendering**: Each model shows its title with a link; if it has actions, a chevron expander shows sub-items
- **No grouping logic for models**: The sidebar currently treats `navigationData.models` as a flat array. It iterates `navigationData.models.map(...)` with no concept of groups.

### 9. Frontend Navigation Types
- **File**: `gravitycar-frontend/src/types/navigation.ts`
- **`NavigationData.models`** is typed as `NavigationItem[]` — a flat array
- **`NavigationItem`**: `{ name, title, url, icon?, actions?, permissions? }`
- **No group/submenu concept exists in the types**

### 10. Frontend Navigation Utilities
- **File**: `gravitycar-frontend/src/utils/navigationUtils.ts`
- **`groupCustomPages(pages)`**: Groups custom pages by key prefix convention (existing utility for custom_pages only)
- This utility does NOT handle model grouping

### 11. Navigation Service (Frontend)
- **File**: `gravitycar-frontend/src/services/navigationService.ts`
- Singleton class; caches navigation data in-memory for 5 minutes
- Endpoints used: `GET /navigation`, `GET /navigation/{role}`, `POST /navigation/cache/rebuild`
- **`clearCache()`** is called after cache rebuild

---

## Conventions to Follow

1. **PHP**: All new code in `Gravitycar` namespace, PSR-12, strict types, dependency injection
2. **PHP method signatures**: Max 3 params; use early returns; no nesting > 3 levels
3. **Frontend**: No external UI libs; Tailwind CSS only; TypeScript strict; class-based services
4. **Metadata files**: Plain PHP returning an associative array; lives at `src/Models/{model}/{model}_metadata.php`
5. **Cache files**: PHP files at `cache/` returning PHP arrays via `var_export`
6. **Never hardcode config values** in class files — use `Config` class

---

## Key Design Decisions Needed by Architect

1. **How to structure the new `navigation_bar` metadata property**:
   - `false` = exclude from navigation
   - `[]` or absent = show ungrouped (backward compat)
   - `['Group Name']` = place in named group (spec uses array, allows future multi-level nesting)

2. **How to change the cache file format**: The `models` array in the cache needs to support either:
   - Option A: Keep flat array, add `group` field to each item; frontend groups on render
   - Option B: Change `models` to an associative array keyed by group name; ungrouped items under a special key

3. **Frontend type changes**: `NavigationData.models` type must be updated to support grouped items

4. **Frontend rendering**: `NavigationSidebar.tsx` needs a grouping-aware render path for models (analogous to what `groupCustomPages` already does for custom pages)

5. **NavigationBuilder change**: Add `getModelMetadata()` call per model inside `buildModelNavigation()`, read `navigation_bar` property, skip if `false`, attach group name to item if set

6. **Cache rebuild needed**: After changes, all 4 role caches must be rebuilt

---

## Reusable Components

- `groupCustomPages()` at `gravitycar-frontend/src/utils/navigationUtils.ts` — existing grouping utility that can be adapted or mirrored for model grouping
- `MetadataEngine::getModelMetadata()` — already accessible in `NavigationBuilder` via `$this->metadataEngine`
- `navigationService.clearCache()` — call this after backend cache rebuild
