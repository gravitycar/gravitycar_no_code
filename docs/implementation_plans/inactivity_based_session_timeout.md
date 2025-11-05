# Inactivity-Based Session Timeout Implementation Plan

**Date**: November 5, 2025  
**Feature**: Replace absolute login time expiration with inactivity-based session timeout  
**Priority**: Medium  
**Complexity**: Moderate

---

## Current System Analysis

### How Authentication Works Now

1. **JWT Token Generation** (`AuthenticationService::generateJwtToken()`)
   - Creates JWT with `iat` (issued at) = current time
   - Sets `exp` (expiration) = current time + 3600 seconds (1 hour)
   - Token expires **exactly 1 hour after login**, regardless of activity

2. **Token Validation** (`AuthenticationService::validateJwtToken()`)
   - JWT library automatically validates `exp` claim
   - If `exp < current_time`, token is rejected
   - No tracking of user activity or "last request time"

3. **Current Flow**
   - User logs in at 10:00 AM → token expires at 11:00 AM
   - User actively works from 10:00 AM - 12:00 PM
   - At 11:00 AM, token expires even though user is active
   - User must re-authenticate at 11:00 AM

### Current Files Involved

- `src/Services/AuthenticationService.php` - Token generation/validation
- `src/Services/CurrentUserProvider.php` - Extracts JWT from request
- `src/Api/Router.php` - Validates auth on every request
- `src/Models/users/users_metadata.php` - User model fields
- `src/Models/jwtrefreshtokens/jwt_refresh_tokens_metadata.php` - Refresh token storage

---

## Desired Behavior

### Inactivity-Based Timeout

1. **Token Lifetime**: JWT tokens still have 1-hour absolute expiration (for security)
2. **Activity Tracking**: Track user's last activity timestamp in database
3. **Inactivity Window**: 3600 seconds (1 hour) of inactivity allowed
4. **Activity Refresh**: Every authenticated request updates `last_activity` timestamp

### Example Scenarios

**Scenario 1: Continuous Activity**
- 10:00 AM: User logs in, `last_activity` = 10:00 AM
- 10:30 AM: User makes request, `last_activity` updated to 10:30 AM
- 11:00 AM: User makes request, `last_activity` updated to 11:00 AM
- 11:30 AM: User makes request, `last_activity` updated to 11:30 AM
- **Result**: User never logged out (continuous activity)

**Scenario 2: Inactivity Timeout**
- 10:00 AM: User logs in, `last_activity` = 10:00 AM
- 10:30 AM: User makes request, `last_activity` = 10:30 AM
- 12:00 PM: User goes to lunch (no requests)
- 1:15 PM: User returns and makes request
- **Calculation**: Current time (1:15 PM) - last_activity (10:30 AM) = 2 hours 45 minutes > 1 hour
- **Result**: Session expired, redirect to login

---

## Implementation Approach

### Option 1: Database-Based Activity Tracking (Recommended)

**Pros:**
- Accurate tracking across multiple tabs/devices
- Survives browser refresh
- Simple validation logic
- Works with load balancers and multiple servers

**Cons:**
- Database write on every authenticated request
- Slight performance overhead

**Implementation:**

1. Add `last_activity` field to Users table
2. Update `last_activity` on every authenticated request
3. Validate both JWT `exp` and `last_activity` threshold
4. Extend JWT on activity (optional optimization)

### Option 2: JWT Claim-Based (Not Recommended)

**Pros:**
- No database writes
- Stateless authentication

**Cons:**
- Requires short-lived tokens + frequent refresh
- Complex frontend token refresh logic
- Race conditions with multiple tabs
- Doesn't survive browser refresh properly

---

## Recommended Implementation (Option 1)

### Phase 1: Database Schema

#### 1.1 Add `last_activity` Field to Users

**File**: `src/Models/users/users_metadata.php`

```php
'last_activity' => [
    'name' => 'last_activity',
    'type' => 'DateTime',
    'label' => 'Last Activity',
    'required' => false,
    'readOnly' => true,
    'validationRules' => ['DateTime'],
],
```

**Actions:**
- Add field to metadata
- Run `php setup.php` to update database schema
- Field will auto-generate in `users` table

---

### Phase 2: Activity Tracking

#### 2.1 Update AuthenticationService - Login

**File**: `src/Services/AuthenticationService.php`

**Method**: `authenticateWithGoogle()` and `authenticateWithCredentials()`

**Change**: Set `last_activity` on successful login

```php
// After successful authentication, before generating tokens:
$user->set('last_login', date('Y-m-d H:i:s'));
$user->set('last_activity', date('Y-m-d H:i:s')); // NEW
$user->update();
```

#### 2.2 Update CurrentUserProvider - Activity Tracking

**File**: `src/Services/CurrentUserProvider.php`

**Method**: `getAuthenticatedUser()` - Add activity refresh

```php
private function getAuthenticatedUser(): ?ModelBase
{
    // Get JWT token from request headers
    $token = $this->getAuthTokenFromRequest();
    
    if (!$token) {
        return null;
    }
    
    // Validate token and get user
    $user = $this->authService->validateJwtToken($token);
    
    if (!$user) {
        return null;
    }
    
    // NEW: Check inactivity timeout
    if (!$this->isWithinActivityWindow($user)) {
        $this->logger->info('User session expired due to inactivity', [
            'user_id' => $user->get('id'),
            'last_activity' => $user->get('last_activity')
        ]);
        return null;
    }
    
    // NEW: Update last activity timestamp (debounce to avoid excessive writes)
    $this->updateLastActivity($user);
    
    return $user;
}
```

#### 2.3 Add Helper Methods to CurrentUserProvider

```php
/**
 * Check if user's last activity is within allowed window
 * 
 * @param ModelBase $user
 * @return bool
 */
private function isWithinActivityWindow(ModelBase $user): bool
{
    $lastActivity = $user->get('last_activity');
    
    if (!$lastActivity) {
        // No last_activity recorded, allow access (backward compatibility)
        return true;
    }
    
    $lastActivityTime = strtotime($lastActivity);
    $currentTime = time();
    $inactivityTimeout = 3600; // 1 hour in seconds
    
    $timeSinceActivity = $currentTime - $lastActivityTime;
    
    return $timeSinceActivity <= $inactivityTimeout;
}

/**
 * Update user's last activity timestamp with debouncing
 * Only update if last activity was more than 60 seconds ago
 * 
 * @param ModelBase $user
 * @return void
 */
private function updateLastActivity(ModelBase $user): void
{
    try {
        $lastActivity = $user->get('last_activity');
        $currentTime = time();
        
        // Debounce: only update if last update was > 60 seconds ago
        if ($lastActivity) {
            $lastActivityTime = strtotime($lastActivity);
            if (($currentTime - $lastActivityTime) < 60) {
                // Too soon to update again
                return;
            }
        }
        
        // Update last_activity
        $user->set('last_activity', date('Y-m-d H:i:s', $currentTime));
        $user->update();
        
        $this->logger->debug('Updated user last_activity', [
            'user_id' => $user->get('id'),
            'last_activity' => $user->get('last_activity')
        ]);
        
    } catch (\Exception $e) {
        // Log but don't fail the request if activity update fails
        $this->logger->error('Failed to update last_activity', [
            'user_id' => $user->get('id'),
            'error' => $e->getMessage()
        ]);
    }
}
```

---

### Phase 3: Configuration

#### 3.1 Add Timeout Configuration

**File**: `config.php`

```php
// Authentication settings
'auth' => [
    'inactivity_timeout' => 3600, // 1 hour in seconds
    'activity_debounce' => 60,    // Update activity every 60 seconds max
],
```

#### 3.2 Use Config in CurrentUserProvider

Update methods to read from config:

```php
$inactivityTimeout = $this->config->get('auth.inactivity_timeout', 3600);
$debounceInterval = $this->config->get('auth.activity_debounce', 60);
```

---

### Phase 4: Frontend Handling

#### 4.1 Update Error Handling

**File**: `gravitycar-frontend/src/services/api.ts`

Current code already handles 401 responses:

```typescript
// Axios interceptor - response error handling
if (error.response?.status === 401) {
  // Clear auth state
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  
  // Redirect to login
  window.location.href = '/login';
}
```

**Changes needed** - add user-friendly session expiration messaging.

#### 4.2 Add Session Warning (Required)

Update the 401 error handler to distinguish between session expiration and other auth failures:

```typescript
if (error.response?.status === 401) {
  const sessionExpired = error.response?.data?.message?.includes('inactivity') || 
                         error.response?.data?.code === 'SESSION_EXPIRED';
  
  if (sessionExpired) {
    // Show notification for session timeout
    alert('Your session has expired due to inactivity. Please log in again.');
  }
  
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  window.location.href = '/login';
}
```

**Why This Is Required:**
- Users need to understand *why* they were logged out (inactivity vs. invalid credentials)
- Reduces confusion and support tickets
- Improves user experience by providing clear feedback

---

### Phase 5: Backend Error Messaging

#### 5.1 Update Validation Response

**File**: `src/Services/CurrentUserProvider.php`

Return more specific error for inactivity timeout:

```php
if (!$this->isWithinActivityWindow($user)) {
    $this->logger->info('User session expired due to inactivity', [
        'user_id' => $user->get('id'),
        'last_activity' => $user->get('last_activity')
    ]);
    
    // Throw specific exception for better error handling
    throw new \Gravitycar\Exceptions\SessionExpiredException(
        'Your session has expired due to inactivity',
        ['code' => 'SESSION_EXPIRED']
    );
}
```

#### 5.2 Create SessionExpiredException

**File**: `src/Exceptions/SessionExpiredException.php`

```php
<?php

namespace Gravitycar\Exceptions;

/**
 * SessionExpiredException
 * Thrown when user session expires due to inactivity
 */
class SessionExpiredException extends UnauthorizedException
{
    public function __construct(
        string $message = 'Session expired',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        $context['code'] = 'SESSION_EXPIRED';
        parent::__construct($message, $context, $previous);
    }
}
```

---

## Testing Strategy

### Unit Tests

#### Test 1: Activity Tracking on Authentication
```php
public function testLoginSetsLastActivity(): void
{
    $result = $this->authService->authenticateWithCredentials('admin', 'password');
    $user = $this->modelFactory->retrieve('Users', $result['user']['id']);
    
    $this->assertNotNull($user->get('last_activity'));
}
```

#### Test 2: Activity Window Validation
```php
public function testUserWithinActivityWindowIsAllowed(): void
{
    $user = $this->createTestUser();
    $user->set('last_activity', date('Y-m-d H:i:s', time() - 600)); // 10 minutes ago
    $user->update();
    
    // Should still be authenticated
    $currentUser = $this->currentUserProvider->getCurrentUser();
    $this->assertNotNull($currentUser);
}

public function testUserOutsideActivityWindowIsRejected(): void
{
    $user = $this->createTestUser();
    $user->set('last_activity', date('Y-m-d H:i:s', time() - 7200)); // 2 hours ago
    $user->update();
    
    // Should be logged out
    $currentUser = $this->currentUserProvider->getCurrentUser();
    $this->assertNull($currentUser);
}
```

#### Test 3: Debouncing
```php
public function testActivityUpdateDebouncing(): void
{
    $user = $this->createTestUser();
    $firstActivity = date('Y-m-d H:i:s', time() - 30); // 30 seconds ago
    $user->set('last_activity', $firstActivity);
    $user->update();
    
    // Make request - should NOT update (within 60 second debounce)
    $this->currentUserProvider->getCurrentUser();
    $user->refresh();
    $this->assertEquals($firstActivity, $user->get('last_activity'));
    
    // Set to 70 seconds ago
    $oldActivity = date('Y-m-d H:i:s', time() - 70);
    $user->set('last_activity', $oldActivity);
    $user->update();
    
    // Make request - SHOULD update (outside 60 second debounce)
    $this->currentUserProvider->getCurrentUser();
    $user->refresh();
    $this->assertNotEquals($oldActivity, $user->get('last_activity'));
}
```

### Manual Testing

1. **Continuous Activity Test**
   - Log in at time T
   - Make API requests every 30 seconds for 2 hours
   - Verify: Never logged out

2. **Inactivity Timeout Test**
   - Log in at time T
   - Wait 65 minutes without activity
   - Make API request
   - Verify: Redirected to login with session expired message

3. **Multi-Tab Test**
   - Log in on Tab 1
   - Open Tab 2 (same browser)
   - Make requests on Tab 1 only
   - Wait 65 minutes
   - Try request on Tab 2
   - Verify: Session should be active (activity from Tab 1 counts)

---

## Performance Considerations

### Database Write Optimization

**Debouncing Strategy:**
- Only update `last_activity` if last update was > 60 seconds ago
- Reduces database writes from every request to ~1 per minute per active user

**Example:**
- 100 active users
- Each user makes 10 requests per minute
- Without debouncing: 1000 DB writes/minute
- With 60-second debouncing: ~100 DB writes/minute (90% reduction)

### Index Optimization

Add index to Users table for faster lookups:

```sql
CREATE INDEX idx_users_last_activity ON users(last_activity);
```

This happens automatically via `setup.php` when field is added to metadata.

---

## Migration Strategy

### Backward Compatibility

1. **Existing Users Without `last_activity`**
   - Field is nullable
   - `isWithinActivityWindow()` returns `true` if field is null
   - First request after migration sets `last_activity`

2. **Existing JWT Tokens**
   - Still valid until their `exp` time
   - Activity tracking starts on first request with new code

### Rollout Plan

1. **Deploy backend changes** (off-peak hours)
2. **Monitor logs** for activity tracking behavior
3. **Validate** no unexpected logouts
4. **Frontend changes** (optional session warning)
5. **Documentation** update for users

---

## Configuration Values

### Recommended Timeouts

```php
'auth' => [
    'inactivity_timeout' => 3600,  // 1 hour (current requirement)
    'activity_debounce' => 60,     // 60 seconds
],
```

### Alternative Configurations

**Strict Security (Financial Apps):**
```php
'inactivity_timeout' => 900,   // 15 minutes
'activity_debounce' => 30,     // 30 seconds
```

**Relaxed (Internal Tools):**
```php
'inactivity_timeout' => 7200,  // 2 hours
'activity_debounce' => 120,    // 2 minutes
```

---

## Success Criteria

### Must Have
- ✅ Users can work continuously for > 1 hour without logout
- ✅ Users are logged out after 1 hour of inactivity
- ✅ Activity tracking works across multiple tabs
- ✅ Database writes are optimized with debouncing
- ✅ Existing JWT tokens still work during migration
- ✅ Clear user-facing message when session expires due to inactivity

### Should Have
- ✅ Clear error message for inactivity timeout (backend logging)
- ✅ Logging of session expiration events
- ✅ Configurable timeout values
- ✅ Unit tests for all activity tracking logic

### Nice to Have
- 🔲 Frontend warning before session expires (e.g., "5 minutes remaining")
- 🔲 Admin dashboard showing active users by `last_activity`
- 🔲 Analytics on session duration and timeout frequency

---

## Files to Modify

### Backend
1. `src/Models/users/users_metadata.php` - Add `last_activity` field
2. `src/Services/AuthenticationService.php` - Set activity on login
3. `src/Services/CurrentUserProvider.php` - Track and validate activity
4. `src/Exceptions/SessionExpiredException.php` - New exception class
5. `config.php` - Add timeout configuration

### Frontend
1. `gravitycar-frontend/src/services/api.ts` - Enhanced 401 handling with session expiration messaging

### Testing
1. `Tests/Unit/Services/CurrentUserProviderTest.php` - Activity tracking tests
2. `Tests/Integration/SessionTimeoutTest.php` - End-to-end flow tests

---

## Risk Assessment

### Low Risk
- Database schema change (simple field addition)
- Activity debouncing (reduces load)
- Backward compatibility (null field handling)

### Medium Risk
- Performance impact of additional DB writes
  - **Mitigation**: Debouncing + monitoring
- Race conditions with multiple tabs
  - **Mitigation**: Database timestamp is source of truth

### High Risk
- None identified

---

## Estimated Effort

- **Backend Development**: 4-6 hours
- **Testing**: 2-3 hours
- **Frontend Enhancement**: 1-2 hours
- **Total**: 8-11 hours

---

## Dependencies

- PHPUnit 10.5+ (already installed)
- MySQL/MariaDB (already configured)
- Existing JWT authentication system (already implemented)

---

## Next Steps

1. **Review this plan** with stakeholders
2. **Get approval** for database schema change
3. **Create feature branch**: `feature/inactivity-timeout`
4. **Implement Phase 1** (database schema)
5. **Run `php setup.php`** to update schema
6. **Implement Phase 2** (activity tracking)
7. **Write unit tests**
8. **Manual testing** with multiple scenarios
9. **Deploy to staging** for validation
10. **Deploy to production** (off-peak hours)
11. **Monitor logs** for first 24 hours
12. **Documentation** update

---

**Status**: 📋 **PLANNED** - Ready for Implementation  
**Reviewer**: _Pending_  
**Approval**: _Pending_
