# Google OAuth Keystroke Bug Fix

## Problem
During login, every keystroke in the username/password input fields was triggering a Google Sign-In API call to `https://accounts.google.com/gsi/button`. This caused excessive network requests and potentially poor performance.

## Root Cause
The issue was in the React component lifecycle management:

1. **Callback Recreation**: The `handleGoogleSuccess` and `handleGoogleError` callbacks in `GoogleSignInButton.tsx` were being recreated on every render
2. **Effect Re-execution**: These recreated callbacks were passed to `useGoogleOAuth` hook, causing the `initializeGoogle` callback to be recreated
3. **Google API Re-initialization**: The `useEffect` in `useGoogleOAuth` was re-running on every keystroke because of the changing `initializeGoogle` dependency
4. **Script Reloading**: This caused the Google Identity Services script to be potentially reloaded or reinitialized repeatedly

## Solution
Applied the following fixes:

### 1. Memoized Callbacks in GoogleSignInButton.tsx
```typescript
// Before: Functions recreated on every render
const handleGoogleSuccess = async (credentialResponse: CredentialResponse) => { ... };
const handleGoogleError = (error: any) => { ... };

// After: Memoized with useCallback
const handleGoogleSuccess = useCallback(async (credentialResponse: CredentialResponse) => { ... }, [loginWithGoogle]);
const handleGoogleError = useCallback((error: any) => { ... }, []);
```

### 2. Used Refs for Callback Storage in useGoogleOAuth.ts
```typescript
// Store callbacks in refs to prevent dependency changes
const onSuccessRef = useRef(onSuccess);
const onErrorRef = useRef(onError);

// Update refs when props change
useEffect(() => {
  onSuccessRef.current = onSuccess;
  onErrorRef.current = onError;
}, [onSuccess, onError]);

// Use refs in the callback instead of direct props
const config: GoogleIdConfiguration = {
  callback: (response: CredentialResponse) => {
    onSuccessRef.current(response); // Use ref instead of prop
  }
  // ...
};
```

### 3. Prevented Multiple Initializations
```typescript
const isInitializedRef = useRef(false);

const initializeGoogle = useCallback(() => {
  // Prevent multiple initializations
  if (isInitializedRef.current) {
    console.log('✅ Google OAuth already initialized, skipping...');
    return;
  }
  
  // ... initialization code
  isInitializedRef.current = true;
}, []); // Empty dependencies since we use refs
```

### 4. Prevented Button Re-rendering
```typescript
// Check if button is already rendered before clearing content
if (!existingButton?.hasChildNodes()) {
  if (buttonRef.current) {
    buttonRef.current.innerHTML = '';
  }
  renderButton('google-signin-button');
}
```

## Testing
To test that the fix works:

1. Open browser dev tools and go to the Network tab
2. Navigate to the login page
3. Type in the username and password fields
4. Verify that no `https://accounts.google.com/gsi/button` requests are made during typing
5. The Google Sign-In button should still work properly when clicked

## Files Modified
- `/gravitycar-frontend/src/components/auth/GoogleSignInButton.tsx`
- `/gravitycar-frontend/src/hooks/useGoogleOAuth.ts`

## Prevention
To prevent similar issues in the future:

1. Always memoize callbacks passed to custom hooks using `useCallback`
2. Use refs to store callback functions that change frequently
3. Keep useEffect dependencies stable by avoiding recreated functions
4. Add guards to prevent multiple initializations of external APIs
5. Test component behavior during form input to catch similar performance issues

Date: August 28, 2025
