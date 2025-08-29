# Gravitycar Tools Extension Updates

## Summary of Changes

I've updated both the Gravitycar API and Server Control tools to fix the reported issues and make them more robust and generic.

## Issues Fixed

### 1. Gravitycar API Tool (`gravitycar_api_call`)

**Issues Found:**
- `get_users_metadata` was mapping to `/metadata/Users` instead of `/metadata/model/Users`
- Hard-coded specific operations instead of generic ones
- Missing support for dynamic model names in metadata operations

**Fixes Applied:**
- ✅ Fixed `get_users_metadata` to use correct endpoint `/metadata/model/Users`
- ✅ Added generic CRUD operations: `get_list`, `get_by_id`, `create`, `update`, `delete`
- ✅ Added support for dynamic model names via `parameters.modelName`
- ✅ Improved error messages with available operations list
- ✅ Added custom operations: `custom_get`, `custom_post`, `custom_put`, `custom_delete`
- ✅ Enhanced parameter structure for better organization
- ✅ Added query parameter support for pagination and search

**New Operation Examples:**
```javascript
// Generic operations (recommended)
gravitycar_api_call({
  operation: "get_list",
  parameters: { modelName: "Users", page: 1, limit: 10 }
})

gravitycar_api_call({
  operation: "get_model_metadata", 
  parameters: { modelName: "Movies" }
})

gravitycar_api_call({
  operation: "create",
  parameters: { 
    modelName: "Users",
    data: { username: "newuser", email: "user@example.com" }
  }
})
```

### 2. Gravitycar Server Control Tool (`gravitycar_server_control`)

**Issues Found:**
- Action enum in package.json used hyphens (`restart-apache`) but tool expected underscores (`restart_apache`)
- Missing `service` parameter support
- Limited error handling for incompatible service/action combinations

**Fixes Applied:**
- ✅ Added support for both hyphenated and underscore action names
- ✅ Added `service` parameter to control backend, frontend, or both
- ✅ Improved commands for service-specific operations
- ✅ Added health-check operation
- ✅ Enhanced error messages for invalid combinations
- ✅ Fixed frontend restart command

**New Examples:**
```javascript
// Backend only
gravitycar_server_control({
  action: "status",
  service: "backend"
})

// Frontend only  
gravitycar_server_control({
  action: "restart-frontend",
  service: "frontend"
})

// Both services (default)
gravitycar_server_control({
  action: "health-check"
})
```

## Files Modified

1. **`src/tools/gravitycarApiTool.ts`**
   - Updated interface to use structured `parameters` object
   - Added generic CRUD operations
   - Fixed metadata endpoint mappings
   - Added query parameter support
   - Enhanced error handling

2. **`src/tools/gravitycarServerTool.ts`**
   - Added `service` parameter support
   - Normalized action name handling (hyphens vs underscores)
   - Improved command logic for service-specific operations
   - Added validation for service/action combinations

3. **`package.json`**
   - Added new operations to API tool enum
   - Added `health-check` to server control actions
   - Added `endpoint` parameter for custom operations
   - Updated tool descriptions

## Activation Required

**⚠️ Important:** VS Code extension changes require reloading VS Code to take effect.

To activate the updates:
1. Save all changes
2. Reload VS Code window (Ctrl+Shift+P → "Developer: Reload Window")
3. Or restart VS Code completely

## Testing the Updates

Once VS Code is reloaded, test the improvements:

```javascript
// Test generic API operations
gravitycar_api_call({
  operation: "get_model_metadata",
  parameters: { modelName: "Users" }
})

// Test server control with service parameter
gravitycar_server_control({
  action: "status", 
  service: "backend"
})
```

## Benefits

1. **More Generic:** Operations work with any model name dynamically
2. **Better Organization:** Structured parameters instead of flat data object
3. **Enhanced Functionality:** Query parameters, service-specific controls
4. **Improved Reliability:** Better error handling and validation
5. **Backward Compatible:** Legacy operations still work during transition

## Migration Guide

**From:** Hard-coded model operations
```javascript
gravitycar_api_call({ operation: "get_users" })
```

**To:** Generic operations
```javascript
gravitycar_api_call({ 
  operation: "get_list",
  parameters: { modelName: "Users" }
})
```

**From:** Simple server actions
```javascript
gravitycar_server_control({ action: "status" })
```

**To:** Service-specific actions  
```javascript
gravitycar_server_control({ 
  action: "status",
  service: "backend" 
})
```
