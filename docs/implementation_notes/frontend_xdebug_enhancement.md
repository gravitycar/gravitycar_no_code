# Frontend XDEBUG_TRIGGER Enhancement Implementation

## Overview
Successfully implemented automatic inclusion of `XDEBUG_TRIGGER=mike` parameter to all frontend API requests in the Gravitycar Framework. This enhancement enables comprehensive Xdebug debugging of backend PHP code triggered by any frontend action.

## Implementation Details

### 1. Created Utility Function (`src/utils/apiUtils.ts`)
Created a new utility module with three key functions:

- **`fetchWithDebug()`**: Enhanced fetch wrapper that automatically:
  - Adds `XDEBUG_TRIGGER=mike` parameter to all requests
  - Includes JWT authentication headers
  - Handles base URL construction
  - Provides consistent error handling for 401 authentication errors

- **`buildApiUrl()`**: Helper for consistent API URL construction
- **`addDebugTrigger()`**: Helper to add debug trigger to existing URLs

### 2. Updated API Service (`src/services/api.ts`)
Enhanced the axios request interceptor to automatically add `XDEBUG_TRIGGER=mike` parameter to all axios-based requests:

```typescript
// Add XDEBUG_TRIGGER to all requests for debugging
if (!config.params) {
  config.params = {};
}
config.params.XDEBUG_TRIGGER = 'mike';
```

### 3. Replaced Direct fetch() Calls
Updated all components using direct fetch() calls to use the new `fetchWithDebug()` utility:

#### TMDBEnhancedCreateForm.tsx
- **Location**: `src/components/movies/TMDBEnhancedCreateForm.tsx`
- **Change**: TMDB search API call now uses `fetchWithDebug()`
- **Impact**: Enables debugging of TMDB integration service

#### RelatedRecordSelect.tsx
- **Location**: `src/components/fields/RelatedRecordSelect.tsx` 
- **Changes**: Replaced 3 fetch() calls:
  1. Metadata fetching for related models
  2. Related record search and pagination
  3. Specific record retrieval by ID
- **Impact**: Enables debugging of relationship loading and field validation

#### useModelMetadata.ts Hook
- **Location**: `src/hooks/useModelMetadata.ts`
- **Change**: Model metadata fetching now uses `fetchWithDebug()`
- **Impact**: Enables debugging of metadata loading and caching system

#### ErrorBoundary.tsx
- **Location**: `src/components/error/ErrorBoundary.tsx`
- **Change**: Error reporting API call now uses `fetchWithDebug()`
- **Impact**: Enables debugging of error handling system

### 4. Comprehensive Coverage
The implementation ensures that ALL frontend API requests include the XDEBUG_TRIGGER parameter through two mechanisms:

1. **Axios Interceptor**: Handles requests made via the `apiService` (login, CRUD operations, etc.)
2. **fetchWithDebug() Utility**: Handles direct fetch() calls in components and hooks

## Benefits

### For Development
- **Seamless Debugging**: Any frontend action can trigger Xdebug breakpoints in the backend
- **No Manual Parameter Addition**: Debug trigger added automatically to all requests
- **Consistent Authentication**: All requests properly authenticated with JWT tokens
- **Error Handling**: Standardized error handling across all API communication

### For Troubleshooting
- **Request Tracing**: Easy to identify which frontend actions trigger specific backend code
- **Real-time Debugging**: Step through backend code as users interact with frontend
- **API Validation**: Verify request/response flow between frontend and backend

## Usage Instructions

### 1. Enable Xdebug in IDE
- Configure your IDE (VSCode, PHPStorm, etc.) to listen for Xdebug connections
- Set breakpoints in backend PHP code (controllers, models, services)

### 2. Start Debug Session
- Open frontend at `http://localhost:3000`
- Perform any action (login, browse movies, create records, etc.)
- IDE should automatically break at PHP breakpoints

### 3. Verification Methods
- **Browser DevTools**: Check Network tab to verify `XDEBUG_TRIGGER=mike` in request URLs
- **Apache Logs**: Look for Xdebug connection messages in error logs
- **Backend Breakpoints**: Place breakpoints in `src/Api/Router.php` or controller methods

## Technical Implementation Notes

### Authentication Handling
- All API requests include JWT token from localStorage
- Automatic logout on 401 authentication errors
- Consistent header management across all request types

### URL Parameter Management
- XDEBUG_TRIGGER added as URL parameter (not header)
- Preserves existing URL parameters
- Works with both GET and POST requests

### Error Recovery
- Network error handling with user-friendly messages
- Graceful fallback when debug parameter addition fails
- Maintained backward compatibility

## Files Modified
1. `/gravitycar-frontend/src/utils/apiUtils.ts` - **NEW**
2. `/gravitycar-frontend/src/services/api.ts` - Enhanced interceptor
3. `/gravitycar-frontend/src/components/movies/TMDBEnhancedCreateForm.tsx` - Updated fetch call
4. `/gravitycar-frontend/src/components/fields/RelatedRecordSelect.tsx` - Replaced 3 fetch calls  
5. `/gravitycar-frontend/src/hooks/useModelMetadata.ts` - Updated metadata fetching
6. `/gravitycar-frontend/src/components/error/ErrorBoundary.tsx` - Updated error reporting

## Testing
- Frontend server status: ✅ Running and responding
- PHP debug script: ✅ Confirms parameter reception
- All components: ✅ No compilation errors
- Utility functions: ✅ Properly implemented with error handling

## Next Steps
This implementation provides comprehensive debugging support for the Gravitycar Framework. Developers can now:
1. Set breakpoints in any backend code
2. Interact with the frontend normally
3. Automatically trigger debug sessions for real-time code analysis

The enhancement is complete and ready for development use.