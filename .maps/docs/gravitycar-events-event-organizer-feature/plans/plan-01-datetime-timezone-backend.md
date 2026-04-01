# Implementation Plan: DateTime Timezone Support - Backend

## Spec Context

The specification requires all DateTime values to be stored in UTC and displayed in the user's configured timezone. The `user_timezone` field already exists in the Users model metadata (line 139 of `users_metadata.php`) and in the TypeScript `User` interface (`gravitycar-frontend/src/types/index.ts`, line 18). The only gap is that `AuthenticationService.formatUserData()` does not include `user_timezone` in its response payload. This plan closes that gap.

- **Catalog item**: 1 - DateTime Timezone Support - Backend
- **Specification section**: Framework Enhancement: DateTime Timezone Support, item 1 (Backend)
- **Acceptance criteria addressed**: AC-20 (partial -- backend half)

## Dependencies

- **Blocked by**: None
- **Uses**: `src/Services/AuthenticationService.php` (existing), `src/Models/users/users_metadata.php` (existing `user_timezone` field)

## File Changes

### Modified Files

- `src/Services/AuthenticationService.php` -- Add `user_timezone` to the `formatUserData()` return array

### New Files

- `tests/Unit/Services/AuthenticationServiceFormatUserDataTest.php` -- Unit test verifying `user_timezone` is included in formatted user data

## Implementation Details

### Modify `formatUserData()` in AuthenticationService

**File**: `src/Services/AuthenticationService.php`

**Current code** (line 718-731):
```php
private function formatUserData(ModelBase $user): array
{
    return [
        'id' => $user->get('id'),
        'email' => $user->get('email'),
        'username' => $user->get('username'),
        'first_name' => $user->get('first_name'),
        'last_name' => $user->get('last_name'),
        'auth_provider' => $user->get('auth_provider'),
        'last_login_method' => $user->get('last_login_method'),
        'profile_picture_url' => $user->get('profile_picture_url'),
        'is_active' => $user->get('is_active')
    ];
}
```

**Changed code** -- add one line before the closing bracket:
```php
private function formatUserData(ModelBase $user): array
{
    return [
        'id' => $user->get('id'),
        'email' => $user->get('email'),
        'username' => $user->get('username'),
        'first_name' => $user->get('first_name'),
        'last_name' => $user->get('last_name'),
        'auth_provider' => $user->get('auth_provider'),
        'last_login_method' => $user->get('last_login_method'),
        'profile_picture_url' => $user->get('profile_picture_url'),
        'is_active' => $user->get('is_active'),
        'user_timezone' => $user->get('user_timezone'),
    ];
}
```

**Logic**: The `user_timezone` field is already stored in the database and loaded by `ModelBase` when a user record is fetched. `$user->get('user_timezone')` will return the stored value (e.g., `"America/New_York"`) or the default `"UTC"` if none is set (per the metadata `defaultValue`). No null-coalescing or fallback logic is needed here because the field is `required: true` with `defaultValue: 'UTC'` in the metadata.

### Call Sites

`formatUserData()` is called in 3 places (lines 103, 161, 707). All three produce user data for API responses (login, registration, token refresh). Since the method is private and all callers simply pass through its return value, no other changes are needed -- all auth flows will automatically include `user_timezone` once the method is updated.

## Error Handling

No new error conditions are introduced. The `$user->get('user_timezone')` call follows the same pattern as every other field access in the method. If the field is missing from a legacy record that predates the `user_timezone` column, the field's `defaultValue` of `'UTC'` (defined in metadata) will be returned by the field system.

## Unit Test Specifications

### Test File

**File**: `tests/Unit/Services/AuthenticationServiceFormatUserDataTest.php`

**Namespace**: `Gravitycar\Tests\Unit\Services`

**Approach**: Since `formatUserData()` is a private method, we test it indirectly through the public methods that call it. The simplest approach is to use PHP reflection to invoke the private method directly with a mocked `ModelBase` object. This keeps the test focused and avoids the complexity of mocking the entire login/registration flow.

**Setup**:
- Create a mock of `ModelBase` that returns known values for `get()` calls.
- Use `ReflectionMethod` to make `formatUserData()` accessible.
- Instantiate `AuthenticationService` with mocked dependencies (Logger, Config, DatabaseConnector, MetadataEngine, FieldFactory, ModelFactory, RelationshipFactory, CurrentUserProvider).

### Test Cases

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Timezone included in output | Mock user with `user_timezone` = `"America/New_York"` | Return array contains key `user_timezone` with value `"America/New_York"` | Verifies the field is present in formatted output |
| UTC default | Mock user with `user_timezone` = `"UTC"` | Return array contains `user_timezone` = `"UTC"` | Verifies default timezone value flows through |
| All existing fields still present | Mock user with all fields set | Return array contains all 9 original keys plus `user_timezone` | Regression: ensures adding the new field did not break existing fields |
| Return array has exactly 10 keys | Mock user with all fields set | `count($result) === 10` | Ensures no accidental additions or removals |

### Key Scenario: Timezone Included in Output

```php
public function testFormatUserDataIncludesUserTimezone(): void
{
    // Setup: Create a mock ModelBase that returns expected field values
    $mockUser = $this->createMock(\Gravitycar\Models\ModelBase::class);
    $mockUser->method('get')->willReturnMap([
        ['id', 'test-uuid-123'],
        ['email', 'test@example.com'],
        ['username', 'testuser'],
        ['first_name', 'Test'],
        ['last_name', 'User'],
        ['auth_provider', 'local'],
        ['last_login_method', 'local'],
        ['profile_picture_url', null],
        ['is_active', true],
        ['user_timezone', 'America/New_York'],
    ]);

    // Action: Invoke formatUserData via reflection
    $service = $this->createAuthServiceWithMocks();
    $reflection = new \ReflectionMethod($service, 'formatUserData');
    $reflection->setAccessible(true);
    $result = $reflection->invoke($service, $mockUser);

    // Assert
    $this->assertArrayHasKey('user_timezone', $result);
    $this->assertSame('America/New_York', $result['user_timezone']);
}
```

### Helper: `createAuthServiceWithMocks()`

The test class will need a helper method that constructs an `AuthenticationService` with mocked dependencies. Examine the `AuthenticationService` constructor to determine which dependencies are injected, then mock each one minimally. The key dependencies to mock:
- `Logger` -- use the `$this->logger` from TestCase
- `Config` -- use the `$this->config` from TestCase
- `DatabaseConnector` -- mock (not used by `formatUserData`)
- `MetadataEngine` -- mock (not used by `formatUserData`)
- `FieldFactory` -- mock (not used by `formatUserData`)
- `ModelFactory` -- mock (not used by `formatUserData`)
- `RelationshipFactory` -- mock (not used by `formatUserData`)
- `CurrentUserProvider` -- mock (not used by `formatUserData`)

Since `formatUserData()` only calls `$user->get()`, most constructor dependencies can be simple mocks with no configured behavior.

## Notes

- This is a minimal, low-risk change: one line added to a private method.
- The frontend already has `user_timezone` in its TypeScript `User` interface, so no frontend type changes are needed. The field will simply start being populated instead of being undefined.
- Catalog Item 2 (DateTime Timezone Support - Frontend) depends on this change being deployed so the frontend `useAuth()` context receives the timezone value.
- No database migration is needed. The `user_timezone` column already exists (it is defined in the metadata and SchemaGenerator would have created it).
