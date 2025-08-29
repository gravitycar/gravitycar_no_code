# UI Metadata Cache Issue Resolution

## Problem
User updated the `users_metadata.php` file to change the `listFields` from including `email` to including `first_name` and `last_name`, then ran `setup.php`. The changes were correctly reflected in `metadata_cache.php`, but the UI was still showing the old field configuration.

## Root Cause Analysis

The Gravitycar Framework has two separate caching systems:

1. **MetadataEngine Cache** (`cache/metadata_cache.php`)
   - Used internally by the PHP backend
   - Cleared and rebuilt by `setup.php`
   - Contains the core metadata from model files

2. **DocumentationCache** (`cache/documentation/model_*.php`)
   - Used by the MetadataAPIController to serve API responses
   - Contains formatted metadata for frontend consumption
   - Was NOT being cleared by `setup.php`

### The Issue
The `setup.php` script only cleared the MetadataEngine cache but not the DocumentationCache. When the frontend requested metadata via the API, the MetadataAPIController was serving stale data from the DocumentationCache.

## Debugging Process

1. **Verified metadata source**: The `users_metadata.php` file contained the correct updated values
2. **Checked MetadataEngine**: When forced to reload from files, it correctly loaded the updated metadata
3. **API Testing**: Direct API calls to `/metadata/model/Users` returned correct data after the documentation cache was manually cleared
4. **Cache Discovery**: Found that DocumentationCache was separate and not being cleared during setup

## Resolution

### Immediate Fix
Manually deleted the stale documentation cache file:
```bash
rm -f cache/documentation/model_Users.php
```

This forced the MetadataAPIController to regenerate the cache with fresh data from the MetadataEngine.

### Permanent Fix
Updated `setup.php` to also clear the DocumentationCache:

```php
// Clear DocumentationCache as well
printInfo("Clearing DocumentationCache...");
$documentationCache = new \Gravitycar\Services\DocumentationCache();
$documentationCache->clearCache();
printSuccess("DocumentationCache cleared");
```

## Verification

1. **Backend API**: Returns correct `listFields` with `first_name, last_name` instead of `email`
2. **Frontend**: Should now receive updated metadata when requesting model information
3. **Cache Consistency**: Both cache systems are now cleared during setup

## Key Lessons

1. **Multiple Cache Systems**: When making metadata changes, ensure all cache layers are cleared
2. **Cache Dependencies**: The DocumentationCache depends on MetadataEngine data but has independent lifecycle
3. **Frontend-Backend Separation**: The frontend receives metadata through API endpoints that may have their own caching

## Related Files Modified

- `setup.php` - Added DocumentationCache clearing
- `cache/documentation/model_Users.php` - Regenerated with correct data
- `src/Models/users/users_metadata.php` - Original change (user-made)

## Testing Recommendations

When making metadata changes:
1. Run `php setup.php` (now clears both caches)
2. Test API endpoint: `curl "http://localhost:8081/metadata/model/Users"`
3. Restart frontend development server if needed
4. Verify UI reflects changes

This ensures metadata changes propagate through all layers of the application.
