# User Model Relationships Update - Implementation Summary

## Overview
Added missing relationships to the Users and Permissions model metadata files, created new relationship metadata files for JWT and OAuth tokens, and updated the corresponding model metadata to use the proper Gravitycar relationship format.

## Changes Made

### 1. Users Model (`src/Models/users/users_metadata.php`)
**Before:**
```php
'relationships' => ['users_roles'],
```

**After:**
```php
'relationships' => ['users_roles', 'users_permissions', 'users_jwt_refresh_tokens', 'users_google_oauth_tokens'],
```

### 2. Permissions Model (`src/Models/permissions/permissions_metadata.php`)
**Before:**
```php
'relationships' => ['roles_permissions'],
```

**After:**
```php
'relationships' => ['roles_permissions', 'users_permissions'],
```

### 3. JWT Refresh Tokens Model (`src/Models/jwtrefreshtokens/jwt_refresh_tokens_metadata.php`)
**Before (old format):**
```php
'relationships' => [
    'user' => [
        'type' => 'BelongsTo',
        'model' => 'Users',
        'foreignKey' => 'user_id',
        'localKey' => 'id',
    ],
],
```

**After (new format):**
```php
'relationships' => ['users_jwt_refresh_tokens'],
```

### 4. Google OAuth Tokens Model (`src/Models/google_oauth_tokens/google_oauth_tokens_metadata.php`)
**Before (old format):**
```php
'relationships' => [
    'user' => [
        'type' => 'BelongsTo',
        'model' => 'Users',
        'foreignKey' => 'user_id',
        'localKey' => 'id',
    ],
],
```

**After (new format):**
```php
'relationships' => ['users_google_oauth_tokens'],
```

## New Relationship Metadata Files Created

### 1. `src/Relationships/users_jwt_refresh_tokens/users_jwt_refresh_tokens_metadata.php`
```php
<?php

return [
    'name' => 'users_jwt_refresh_tokens',
    'type' => 'OneToMany',
    'modelOne' => 'Users',
    'modelMany' => 'JwtRefreshTokens',
    'constraints' => [],
    'additionalFields' => []
];
```

### 2. `src/Relationships/users_google_oauth_tokens/users_google_oauth_tokens_metadata.php`
```php
<?php

return [
    'name' => 'users_google_oauth_tokens',
    'type' => 'OneToMany',
    'modelOne' => 'Users',
    'modelMany' => 'GoogleOauthTokens',
    'constraints' => [],
    'additionalFields' => []
];
```

## Validation Results

✅ **All Tests Passing:**
- ✅ Users model now has 4 relationships: `users_roles`, `users_permissions`, `users_jwt_refresh_tokens`, `users_google_oauth_tokens`
- ✅ Permissions model now has 2 relationships: `roles_permissions`, `users_permissions`
- ✅ JWT Refresh Tokens model properly configured with `users_jwt_refresh_tokens` relationship
- ✅ All relationship metadata files exist and are properly structured
- ✅ Metadata cache rebuilt successfully (7 relationships total, up from 5)

## Framework Impact

### Benefits
1. **Complete Relationship Coverage**: Users model now properly declares all its relationships
2. **Consistent Format**: All models now use the standardized Gravitycar relationship format
3. **Enhanced Functionality**: AuthorizationService and other services can now use relationship queries for JWT and OAuth tokens
4. **Framework Compliance**: Eliminates the old relationship format in favor of the current metadata-driven approach

### Database Schema
- The relationships use existing foreign key columns (`user_id` in JWT and OAuth token tables)
- No database migration required as the underlying table structure remains the same
- The framework will automatically handle JOIN operations through the relationship system

## Files Modified

### Model Metadata Files (4)
- `src/Models/users/users_metadata.php` - Added 3 relationships
- `src/Models/permissions/permissions_metadata.php` - Added 1 relationship  
- `src/Models/jwtrefreshtokens/jwt_refresh_tokens_metadata.php` - Updated to new format
- `src/Models/google_oauth_tokens/google_oauth_tokens_metadata.php` - Updated to new format

### New Relationship Metadata Files (2)
- `src/Relationships/users_jwt_refresh_tokens/users_jwt_refresh_tokens_metadata.php` - OneToMany relationship
- `src/Relationships/users_google_oauth_tokens/users_google_oauth_tokens_metadata.php` - OneToMany relationship

### Test Files (1)
- `tmp/test_user_relationships.php` - Validation script

## Usage Examples

Now that these relationships are properly configured, they can be used in the framework:

```php
// Get user's JWT refresh tokens
$user = $modelFactory->new('Users');
$tokens = $user->getRelated('users_jwt_refresh_tokens');

// Get user's permissions directly (not through roles)
$permissions = $user->getRelated('users_permissions');

// Get user's OAuth tokens
$oauthTokens = $user->getRelated('users_google_oauth_tokens');

// Use in DatabaseConnector queries with relationship criteria
$criteria = ['users_jwt_refresh_tokens.user_id' => $userId];
$tokens = $databaseConnector->find($jwtModel, $criteria);
```

## Conclusion

The Users model relationships have been successfully updated to include all missing relationships. The framework now has complete relationship coverage for the authentication and authorization system, enabling proper use of the relationship system throughout the codebase.