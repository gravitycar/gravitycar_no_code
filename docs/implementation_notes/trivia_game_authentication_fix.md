# Trivia Game Authentication Fix - Implementation Summary

## Problem Identified

The Movie Quote Trivia Game was always creating games linked to the Guest user, even when the player was authenticated as `mike@gravitycar.com`. This happened because:

1. **Frontend Issue**: The trivia game was using raw `fetch()` calls instead of the centralized `apiService` that automatically includes authentication headers
2. **Backend Fallback**: The `ServiceLocator::getCurrentUser()` method correctly falls back to the guest user when no authentication token is found

## Root Cause Analysis

The trivia game's `useGameState` hook was using a custom `apiCall` function that made raw HTTP requests without including the JWT token stored in `localStorage`. Meanwhile, all other parts of the application use the `apiService` which automatically includes the `Authorization: Bearer <token>` header.

```typescript
// BEFORE (in useGameState.ts) - Raw fetch without auth
const apiCall = async (endpoint: string, options: RequestInit = {}) => {
    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
      headers: {
        'Content-Type': 'application/json',
        ...options.headers,  // No auth headers included!
      },
      ...options,
    });
    // ...
};
```

## Solution Implemented

### 1. Added Trivia-Specific Methods to ApiService

Extended `src/services/api.ts` with dedicated trivia game methods that use the authenticated HTTP client:

```typescript
// NEW: Trivia game API methods in ApiService
async startTriviaGame(): Promise<any>
async submitTriviaAnswer(gameId: string, questionId: string, selectedOption: number, timeTaken: number): Promise<any>
async completeTriviaGame(gameId: string): Promise<any>
async getTriviaHighScores(): Promise<any>
```

### 2. Updated Frontend Hook to Use Authenticated Service

Modified `gravitycar-frontend/src/hooks/useGameState.ts` to use the centralized `apiService` instead of raw fetch calls:

```typescript
// AFTER - Using authenticated apiService
const gameData = await apiService.startTriviaGame();
const result = await apiService.submitTriviaAnswer(gameId, questionId, selectedOption, timeTaken);
const result = await apiService.completeTriviaGame(gameId);
const scoresData = await apiService.getTriviaHighScores();
```

### 3. Authentication Flow Verification

The authentication flow now works as follows:

1. **User logs in** → JWT token stored in `localStorage` 
2. **Frontend calls trivia API** → `apiService` automatically includes `Authorization: Bearer <token>` header
3. **Backend receives request** → `ServiceLocator::getAuthTokenFromRequest()` extracts token from header
4. **Token validation** → `AuthenticationService::validateJwtToken()` validates token and returns user
5. **Game creation** → `ModelBase::create()` sets `created_by` field to authenticated user ID

## Files Modified

1. **`gravitycar-frontend/src/services/api.ts`**
   - Added 4 new trivia-specific API methods
   - All methods use the internal authenticated HTTP client

2. **`gravitycar-frontend/src/hooks/useGameState.ts`** 
   - Removed custom `apiCall` function with raw fetch
   - Updated all API calls to use `apiService` methods
   - Added proper import for `apiService`

## Expected Behavior After Fix

- **Authenticated Users**: Trivia games will be linked to the actual user (e.g., `created_by` = mike's user ID, `created_by_name` = "Mike Andersen")
- **Guest Users**: When no authentication token is present, games still fall back to guest user (preserving existing functionality)
- **High Scores**: Will now properly display authenticated user names instead of always showing "Guest User"

## Testing Verification

1. **Backend Test**: Confirmed that without authentication headers, system correctly falls back to guest user
2. **Frontend Integration**: Updated trivia game to use authenticated API service
3. **Authentication Flow**: Verified that `ServiceLocator::getCurrentUser()` properly resolves authenticated users when JWT tokens are present

## Additional Benefits

- **Consistency**: Trivia game now uses the same authentication pattern as all other frontend features
- **Security**: All trivia API calls now include proper authentication headers
- **Maintainability**: Removed duplicate HTTP request handling code
- **Error Handling**: Leverages the centralized error handling in `apiService`

## Testing Instructions

To verify the fix works:

1. Navigate to `http://localhost:3000`
2. Log in as `mike@gravitycar.com` with password `password`
3. Play the trivia game at `http://localhost:3000/trivia`
4. Check the high scores - the game should now be linked to "Mike Andersen" instead of "Guest User"
5. Test guest behavior by logging out and playing - should still work with guest user

The fix maintains backward compatibility while ensuring authenticated users get proper game attribution.
