# GoogleOauthTokens Model Directory Structure Fix

## Issue
The GoogleOauthTokens model was not following the framework's directory structure conventions. It was located in `src/Models/google_oauth_tokens/` (with underscores) instead of `src/Models/googleoauthtokens/` (lowercase without underscores) like other models.

This inconsistency was preventing proper model instantiation through the ModelFactory.

## Changes Made

### 1. Directory Structure Migration
- **Old location**: `src/Models/google_oauth_tokens/`
- **New location**: `src/Models/googleoauthtokens/`

### 2. File Movements and Renames
- Moved `GoogleOauthTokens.php` to new directory
- Renamed metadata file: `google_oauth_tokens_metadata.php` → `googleoauthtokens_metadata.php`

### 3. Namespace Update
- Updated namespace in `GoogleOauthTokens.php`:
  - **Old**: `namespace Gravitycar\Models\google_oauth_tokens;`
  - **New**: `namespace Gravitycar\Models\googleoauthtokens;`

### 4. ContainerConfig Mapping Update
- Updated fallback model mapping in `src/Core/ContainerConfig.php`:
  - **Old**: `'GoogleOauthTokens' => 'Gravitycar\\Models\\google_oauth_tokens\\GoogleOauthTokens'`
  - **New**: `'GoogleOauthTokens' => 'Gravitycar\\Models\\googleoauthtokens\\GoogleOauthTokens'`

### 5. Cache Regeneration
- Ran `php setup.php` to rebuild metadata cache and API routes
- Cache now properly reflects the new directory structure

## Verification Results

### ModelFactory Test ✅
Successfully tested model instantiation with:
```php
$googleOauthTokens = $modelFactory->new('GoogleOauthTokens');
```

**Results:**
- ✅ Model created successfully
- ✅ Class name: `Gravitycar\Models\googleoauthtokens\GoogleOauthTokens`
- ✅ Properly derived from ModelBase
- ✅ Table name: `google_oauth_tokens` (correct database table)
- ✅ All custom methods available: `cleanupExpiredTokens`, `findActiveTokenForUser`, `revokeUserTokens`, `isExpired`

### API Endpoint Test ✅
Successfully tested API access:
- ✅ `GET /GoogleOauthTokens` endpoint responds correctly
- ✅ Returns proper pagination and metadata structure
- ✅ Empty result set (expected, no tokens in database)

## Notes
- The database table name remains `google_oauth_tokens` (unchanged)
- Relationship files remain in `src/Relationships/users_google_oauth_tokens/` (unchanged)
- The metadata file was renamed to follow the convention: `{lowercase_model_name}_metadata.php`
- All custom business logic methods in the GoogleOauthTokens class remain functional

## Framework Convention Compliance
The GoogleOauthTokens model now follows the same pattern as other models:
- `src/Models/users/Users.php` + `users_metadata.php`
- `src/Models/roles/Roles.php` + `roles_metadata.php`
- `src/Models/googleoauthtokens/GoogleOauthTokens.php` + `googleoauthtokens_metadata.php`

This ensures consistent model discovery and instantiation throughout the framework.