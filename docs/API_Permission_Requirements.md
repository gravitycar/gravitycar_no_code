# API Permission Requirements Reference

## Overview

This document describes the permission requirements for all API endpoints in the Gravitycar Framework after the implementation of the RBAC (Role-Based Access Control) system.

## Authentication Requirements

All API endpoints (except public ones) require:
1. **JWT Authentication**: Valid Bearer token in `Authorization` header
2. **Role-Based Permissions**: User must have appropriate role and action permissions
3. **Model-Action Permissions**: Database-verified permissions for the specific model and action

## Permission Check Flow

1. **Authentication**: JWT token validated and user extracted
2. **Action Determination**: HTTP method mapped to action or explicit `RBACAction` used
3. **Model Extraction**: Model name extracted from URL path or request parameters
4. **Role Lookup**: User's roles retrieved from `users_roles` relationship
5. **Permission Verification**: Database query to verify role has required permission for model+action
6. **Access Decision**: Access granted only if user has valid permission

## HTTP Method to Action Mapping

| HTTP Method | Default Action | Description |
|-------------|---------------|-------------|
| GET | `read` | View/retrieve data |
| POST | `create` | Create new records |
| PUT | `update` | Modify existing records |
| DELETE | `delete` | Remove records |

*Note: Explicit `RBACAction` in route configuration overrides default mapping*

## Core Model Endpoints

### Users API (`/Users`)

| Endpoint | Method | Action | Default Roles | Description |
|----------|--------|--------|---------------|-------------|
| `/Users` | GET | `list` | admin, manager | List all users |
| `/Users` | POST | `create` | admin, manager | Create new user |
| `/Users/{id}` | GET | `read` | admin, manager, user* | View user details |
| `/Users/{id}` | PUT | `update` | admin, manager, user* | Update user |
| `/Users/{id}` | DELETE | `delete` | admin | Delete user |

*\*user role can only access own records*

**Permission Override Example:**
```php
// In users_metadata.php
'rolesAndActions' => [
    'admin' => ['*'],
    'manager' => ['list', 'read'], 
    'user' => ['read'], // Only read own profile
    'guest' => []
]
```

### Movies API (`/Movies`)

| Endpoint | Method | Action | Default Roles | Description |
|----------|--------|--------|---------------|-------------|
| `/Movies` | GET | `list` | admin, manager, user, guest* | List movies |
| `/Movies` | POST | `create` | admin, manager, user | Create movie |
| `/Movies/{id}` | GET | `read` | admin, manager, user, guest* | View movie |
| `/Movies/{id}` | PUT | `update` | admin, manager, user | Update movie |
| `/Movies/{id}` | DELETE | `delete` | admin, manager | Delete movie |

*\*guest access configurable via metadata*

### Roles API (`/Roles`)

| Endpoint | Method | Action | Default Roles | Description |
|----------|--------|--------|---------------|-------------|
| `/Roles` | GET | `list` | admin | List all roles |
| `/Roles` | POST | `create` | admin | Create new role |
| `/Roles/{id}` | GET | `read` | admin | View role details |
| `/Roles/{id}` | PUT | `update` | admin | Update role |
| `/Roles/{id}` | DELETE | `delete` | admin | Delete role |

**Highly Restricted:**
```php
'rolesAndActions' => [
    'admin' => ['*'], // Only admins can manage roles
    // All other roles inherit default: no access
]
```

### Permissions API (`/Permissions`)

| Endpoint | Method | Action | Default Roles | Description |
|----------|--------|--------|---------------|-------------|
| `/Permissions` | GET | `list` | admin | List all permissions |
| `/Permissions` | POST | `create` | admin | Create permission |
| `/Permissions/{id}` | GET | `read` | admin | View permission |
| `/Permissions/{id}` | PUT | `update` | admin | Update permission |
| `/Permissions/{id}` | DELETE | `delete` | admin | Delete permission |

## Relationship Endpoints

### User Roles (`/Users/{id}/link/roles`)

| Endpoint | Method | Action | Default Roles | Description |
|----------|--------|--------|---------------|-------------|
| `/Users/{id}/link/roles` | GET | `read` | admin, manager | List user's roles |
| `/Users/{id}/link/roles` | POST | `create` | admin | Assign role to user |
| `/Users/{id}/link/roles/{roleId}` | DELETE | `delete` | admin | Remove role from user |

### Movie Quotes (`/Movies/{id}/link/quotes`)

| Endpoint | Method | Action | Default Roles | Description |
|----------|--------|--------|---------------|-------------|
| `/Movies/{id}/link/quotes` | GET | `read` | admin, manager, user | List movie quotes |
| `/Movies/{id}/link/quotes` | POST | `create` | admin, manager, user | Add quote to movie |
| `/Movies/{id}/link/quotes/{quoteId}` | DELETE | `delete` | admin, manager | Remove quote |

## Special Endpoints

### Authentication Endpoints

| Endpoint | Method | Permissions | Description |
|----------|--------|------------|-------------|
| `/auth/login` | POST | Public | User login |
| `/auth/logout` | POST | Authenticated | User logout |
| `/auth/google` | POST | Public | Google OAuth login |
| `/auth/refresh` | POST | Authenticated | Refresh JWT token |

### System Endpoints

| Endpoint | Method | Permissions | Description |
|----------|--------|------------|-------------|
| `/health` | GET | admin | System health check |
| `/ping` | GET | admin | Simple ping response |
| `/metadata/models/{model}` | GET | admin, manager, user | Model metadata |

### TMDB Integration Endpoints

| Endpoint | Method | Permissions | Description |
|----------|--------|------------|-------------|
| `/movies/tmdb/search` | POST | admin, manager, user | Search TMDB for movies |
| `/movies/tmdb/enrich/{id}` | PUT | admin, manager, user | Enrich movie with TMDB data |

## Custom Action Examples

### Content Moderation

```php
// Custom route with explicit RBACAction
[
    'method' => 'POST',
    'path' => '/Movies/{id}/approve',
    'RBACAction' => 'approve' // Override default 'create' action
]

// Metadata configuration
'rolesAndActions' => [
    'admin' => ['*'],
    'moderator' => ['list', 'read', 'approve', 'reject'],
    'user' => ['list', 'read', 'create']
]
```

### Workflow Actions

```php
// Multi-step workflow
'rolesAndActions' => [
    'admin' => ['*'],
    'editor' => ['list', 'read', 'create', 'update', 'submit'],
    'reviewer' => ['list', 'read', 'review', 'approve', 'reject'],
    'publisher' => ['list', 'read', 'publish', 'unpublish']
]
```

## Error Response Format

### 401 Unauthorized
```json
{
    "success": false,
    "status": 401,
    "error": {
        "message": "Authentication required",
        "type": "Unauthorized",
        "code": 401,
        "context": {
            "route": "/Users",
            "method": "GET"
        }
    },
    "timestamp": "2025-09-24T12:00:00+00:00"
}
```

### 403 Forbidden
```json
{
    "success": false,
    "status": 403,
    "error": {
        "message": "Insufficient permissions for this action",
        "type": "Forbidden",
        "code": 403,
        "context": {
            "route": "/Users",
            "method": "DELETE",
            "user_id": "user-123",
            "required_action": "delete",
            "component": "Users"
        }
    },
    "timestamp": "2025-09-24T12:00:00+00:00"
}
```

## Request Headers

### Required Headers

```http
Authorization: Bearer <jwt_token>
Content-Type: application/json
```

### Optional Headers

```http
X-Request-ID: unique-request-id  # For request tracing
Accept: application/json         # Response format preference
```

## Response Headers

### Success Response

```http
HTTP/1.1 200 OK
Content-Type: application/json
Cache-Control: no-cache
```

### Error Response

```http
HTTP/1.1 403 Forbidden
Content-Type: application/json
WWW-Authenticate: Bearer realm="Gravitycar API"
```

## Rate Limiting

Permission-based rate limiting may be applied:

- **Admin**: Unlimited requests
- **Manager**: 1000 requests/hour
- **User**: 500 requests/hour
- **Guest**: 100 requests/hour

Rate limit headers included in responses:
```http
X-RateLimit-Limit: 500
X-RateLimit-Remaining: 450
X-RateLimit-Reset: 1640995200
```

## Pagination with Permissions

List endpoints respect permissions for filtering:

```http
GET /Users?page=1&limit=10
```

Response includes permission-filtered results:
```json
{
    "success": true,
    "data": [/* Only accessible users */],
    "pagination": {
        "page": 1,
        "limit": 10,
        "total": 15, // Total accessible records
        "pages": 2
    }
}
```

## Search with Permissions

Search endpoints filter results based on user permissions:

```http
GET /Movies?search=action&limit=20
```

Only returns movies the user has `read` permission for.

## Bulk Operations

Bulk operations require individual permission checks:

```http
POST /Movies/bulk
{
    "action": "delete",
    "ids": ["movie-1", "movie-2", "movie-3"]
}
```

Response indicates per-item permission results:
```json
{
    "success": true,
    "results": [
        {"id": "movie-1", "success": true},
        {"id": "movie-2", "success": false, "error": "Insufficient permissions"},
        {"id": "movie-3", "success": true}
    ]
}
```

## Field-Level Permissions

Some endpoints support field-level permission filtering:

```http
GET /Users/user-123
```

Response includes only fields the user has permission to read:
```json
{
    "success": true,
    "data": {
        "id": "user-123",
        "username": "john_doe",
        "email": "john@example.com",
        // "password" field excluded (no permission)
        // "admin_notes" field excluded (no permission)
        "created_at": "2025-01-01T00:00:00Z"
    }
}
```

## Testing API Permissions

### Using cURL

Test authenticated requests:
```bash
# Login to get token
curl -X POST http://localhost:8081/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}'

# Use token for authenticated request
curl -X GET http://localhost:8081/Users \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json"
```

### Using Postman

1. **Setup Environment Variables**:
   - `base_url`: `http://localhost:8081`
   - `token`: `<jwt_token_value>`

2. **Pre-request Script** (for automatic token handling):
```javascript
pm.request.headers.add({
    key: 'Authorization',
    value: 'Bearer ' + pm.environment.get('token')
});
```

### Debug Headers

Add debug parameter for development:
```http
GET /Users?XDEBUG_TRIGGER=mike
```

This enables Xdebug debugging for the request (when configured).

## Migration Notes

### From Legacy Authorization

Old authorization checks are still supported during transition:

```php
// ❌ Old style (still works)
if ($user->get('user_type') === 'admin') {
    // admin logic
}

// ✅ New style (recommended)
if ($authService->hasRole($user, 'admin')) {
    // admin logic
}
```

### API Client Updates

Update API clients to handle new error responses:

```javascript
// JavaScript example
fetch('/api/Users', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
})
.then(response => {
    if (response.status === 403) {
        // Handle permission denied
        throw new Error('Insufficient permissions');
    }
    return response.json();
})
.catch(error => {
    // Handle permission errors gracefully
    console.error('API Error:', error.message);
});
```

## Debugging Permission Issues

### Enable Debug Logging

Add to request URL:
```
?XDEBUG_TRIGGER=mike&debug=1
```

### Check Logs

Monitor permission check logs:
```bash
tail -f logs/gravitycar.log | grep -i "permission\|authorization"
```

### Common Issues

1. **Missing JWT token**: Include `Authorization: Bearer <token>` header
2. **Expired token**: Refresh or re-authenticate
3. **Insufficient permissions**: Check user roles and model permissions
4. **Model not found**: Verify model name in URL path
5. **Action not allowed**: Check `rolesAndActions` configuration

### Debug Endpoints

For development only:

```http
GET /debug/user-permissions
Authorization: Bearer <token>
```

Returns current user's all permissions (admin only).

This completes the API permission requirements reference documentation.