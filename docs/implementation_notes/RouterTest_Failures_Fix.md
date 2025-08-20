# RouterTest Failures Fix

## Issue Summary
The RouterTest unit tests were failing with 3 errors, all related to "Missing required route parameter: api". The tests were failing on:
1. `testRouteWithSuccessfulMatch`
2. `testRouteWithFallbackMatching` 
3. `testHandleRequestWithValidRoute`

## Root Cause Analysis
The issue was in the `Router::generateParameterNamesForRequest()` method. For static routes like `/api/users`, the method was generating generic parameter names like `['component1', 'component2']` instead of using the explicit parameter names from the route definition `['api', 'users']`.

This caused a mismatch between:
- What the route validation expected (parameters named `api` and `users`)
- What the Request object actually contained (parameters named `component1` and `component2`)

## Technical Details
The Router workflow is:
1. `Router::route()` finds a matching route
2. Calls `generateParameterNamesForRequest()` to create parameter names for the Request object
3. Creates a `Request` object with the generated parameter names
4. The Request constructor extracts path components and maps them to parameter names
5. `validateRequestParameters()` checks that the Request has all expected parameters from the route

The bug was in step 2 - the parameter name generation logic only used explicit route `parameterNames` when they matched the count of dynamic components (wildcards/placeholders). For static routes with no dynamic components, it fell back to generic names.

## Solution
Modified `Router::generateParameterNamesForRequest()` to prioritize using explicit `parameterNames` from the route definition when they match the total path component count, regardless of whether the route has dynamic components.

### Code Changes
**File:** `src/Api/Router.php`
**Method:** `generateParameterNamesForRequest()`

Added a new priority check:
```php
// Priority 1: If route has explicit parameter names and count matches path components, use them
if (!empty($route['parameterNames'])) {
    // For static routes, parameter names should match all path components
    if (count($route['parameterNames']) === count($pathComponents)) {
        return $route['parameterNames'];
    }
    // ... existing logic for dynamic routes ...
}
```

## Test Results
- **Before Fix:** 3 errors, 29 tests, 39 assertions
- **After Fix:** 0 errors, 29 tests, 42 assertions ✅

All RouterTest tests now pass successfully.

## Impact
This fix ensures that static routes with explicit parameter names work correctly, which is important for:
- API routes like `/api/users` that need to extract `api` and `users` as parameters
- Model-based routing where path components map to specific parameter names
- Route validation and parameter extraction consistency

## Testing
Created a debug script that confirmed:
- **Before fix:** Generated `['component1', 'component2']` for route with `parameterNames: ['api', 'users']`
- **After fix:** Generated `['api', 'users']` correctly

The fix maintains backward compatibility with dynamic routes while properly handling static routes.
