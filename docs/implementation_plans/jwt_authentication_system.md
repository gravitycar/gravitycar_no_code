# Google OAuth 2.0 + JWT Authentication System Implementation Plan

## 1. Feature Overview

This plan focuses on implementing a comprehensive Google OAuth 2.0 + JWT authentication and authorization system for the Gravitycar Framework to support ReactJS frontend applications. The system will provide seamless Google OAuth integration with automatic user creation, JWT token management, session handling, and role-based access control.

### Key Features:
- **Shared Google Authentication**: Users logged into Google are automatically authenticated in the framework
- **Auto-User Creation**: First-time Google users get accounts created automatically
- **JWT Token Management**: Secure token-based session management for API access
- **Hybrid Authentication**: Support both Google OAuth and traditional username/password
- **Role-Based Access Control**: Granular permission system

## 2. Current State Assessment

**Current State**: No authentication system - `getCurrentUserId()` returns null
**Proposed State**: Google OAuth 2.0 integration with automatic user provisioning
**Impact**: Critical for any production ReactJS application with enterprise-grade authentication
**Priority**: HIGH - Week 1-3 implementation (extended for OAuth integration)

## 3. Requirements

### 3.1 Functional Requirements
- **Google OAuth 2.0 Integration**
  - Google Sign-In button for React frontend
  - Server-side Google OAuth token validation
  - Automatic user account creation from Google profile
  - Google profile data synchronization
- **JWT Token Management**
  - JWT token generation after OAuth validation
  - Token refresh mechanism
  - Secure token validation for API requests
- **Hybrid Authentication**
  - Support both Google OAuth and traditional login
  - User preference for authentication method
- **User Management**
  - Auto-creation of users from Google OAuth
  - Profile synchronization with Google data
  - Role assignment for new OAuth users
- **Authorization & Security**
  - Role-based access control
  - Permission-based restrictions
  - Session management across devices

### 3.2 Non-Functional Requirements
- **Security**
  - Google OAuth 2.0 compliance
  - Secure JWT token handling
  - HTTPS enforcement for OAuth flows
- **Performance**
  - Fast OAuth token validation
  - Minimal API latency impact
  - Efficient user lookup and creation
- **User Experience**
  - Seamless Google sign-in experience
  - Automatic authentication for Google users
  - Clear fallback for non-Google users
- **React Integration**
  - Google Sign-In React component integration
  - Token storage guidance for React
  - Automatic session management

## 4. Design

### 4.1 Architecture Components

```php
// Google OAuth Service
class GoogleOAuthService {
    public function getAuthorizationUrl(): string;
    public function validateOAuthToken(string $googleToken): array;
    public function getUserProfile(string $googleToken): array;
    public function refreshGoogleToken(string $refreshToken): array;
}

// Enhanced Authentication Service
class AuthenticationService {
    // Google OAuth methods
    public function authenticateWithGoogle(string $googleToken): ?array;
    public function createUserFromGoogle(array $googleProfile): User;
    
    // Traditional authentication methods
    public function authenticate(string $username, string $password): ?array;
    
    // JWT token management
    public function generateJwtToken(array $user): string;
    public function validateJwtToken(string $token): ?array;
    public function refreshJwtToken(string $token): string;
    public function revokeToken(string $token): bool;
}

// Authentication Middleware
class AuthenticationMiddleware {
    public function handle(Request $request): bool;
    public function extractTokenFromHeader(Request $request): ?string;
    public function requireAuthentication(): void;
    public function requireRole(string $role): void;
    public function requirePermission(string $permission): void;
}

// Enhanced User Service
class UserService {
    // Traditional user management
    public function createUser(array $userData): User;
    public function updateUser(int $userId, array $userData): User;
    public function getUserByCredentials(string $username, string $password): ?User;
    public function getUserById(int $userId): ?User;
    
    // Google OAuth user management
    public function findUserByGoogleId(string $googleId): ?User;
    public function createUserFromGoogleProfile(array $profile): User;
    public function syncUserWithGoogleProfile(User $user, array $profile): User;
}
```

### 4.2 OAuth 2.0 Flow Architecture

```
React Frontend                 Gravitycar Backend              Google OAuth
     |                             |                            |
     |-- 1. Google Sign-In ------->|                            |
     |                             |                            |
     |                             |-- 2. Validate Token ----->|
     |                             |<-- 3. User Profile --------|
     |                             |                            |
     |                             |-- 4. Create/Update User    |
     |                             |-- 5. Generate JWT Token    |
     |                             |                            |
     |<-- 6. JWT + User Data ------|                            |
     |                             |                            |
     |-- 7. API Requests + JWT --->|                            |
     |<-- 8. Protected Resources --|                            |
```

### 4.3 Database Schema Design

> **IMPORTANT**: The SQL statements below are **REFERENCE EXAMPLES ONLY** to illustrate the expected database structure. All actual schema generation must be accomplished through the Gravitycar Framework's metadata-driven approach using metadata files and the SchemaGenerator. Never execute these SQL statements directly.

#### Enhanced Users Table (Updated via Metadata)
```sql
-- REFERENCE ONLY - Generated automatically by SchemaGenerator from users_metadata.php
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Traditional authentication fields
    username VARCHAR(255) UNIQUE NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NULL, -- NULL for OAuth-only users
    
    -- Google OAuth fields (added via metadata)
    google_id VARCHAR(255) UNIQUE NULL,
    google_email VARCHAR(255) NULL,
    google_verified_email BOOLEAN DEFAULT FALSE,
    
    -- Profile information (synced from Google)
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    profile_picture_url VARCHAR(500) NULL, -- ImageField with width/height metadata
    
    -- Authorization fields
    role VARCHAR(50) DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Authentication method tracking
    auth_provider ENUM('local', 'google', 'hybrid') DEFAULT 'local',
    last_login_method VARCHAR(50) NULL,
    
    -- Timestamps
    email_verified_at TIMESTAMP NULL,
    last_google_sync TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_google_id (google_id),
    INDEX idx_google_email (google_email),
    INDEX idx_auth_provider (auth_provider)
);
```

#### New Authentication Models

**JwtRefreshTokens Model Table**
```sql
-- REFERENCE ONLY - Generated automatically by SchemaGenerator from jwt_refresh_tokens_metadata.php
CREATE TABLE jwt_refresh_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at)
);
```

**GoogleOauthTokens Model Table**
```sql
-- REFERENCE ONLY - Generated automatically by SchemaGenerator from google_oauth_tokens_metadata.php
CREATE TABLE google_oauth_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    access_token_hash VARCHAR(255) NULL, -- Store hash, not actual token
    refresh_token_hash VARCHAR(255) NULL,
    token_expires_at TIMESTAMP NULL,
    scope TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (token_expires_at)
);
```

**Roles Model Table**
```sql
-- REFERENCE ONLY - Generated automatically by SchemaGenerator from roles_metadata.php
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    is_oauth_default BOOLEAN DEFAULT FALSE, -- Default role for OAuth users
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Permissions Model Table**
```sql
-- REFERENCE ONLY - Generated automatically by SchemaGenerator from permissions_metadata.php
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(100) NOT NULL,
    model VARCHAR(100) DEFAULT '' NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_permission_model (action, model),
    INDEX idx_model (model),
    INDEX idx_action (action)
);
```

#### Relationship Junction Tables

**Roles-Permissions Relationship (Many-to-Many)**
```sql
-- REFERENCE ONLY - Generated automatically by SchemaGenerator from roles_permissions_metadata.php
CREATE TABLE role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

**Users-Permissions Relationship (Many-to-Many)**  
```sql
-- REFERENCE ONLY - Generated automatically by SchemaGenerator from users_permissions_metadata.php
CREATE TABLE user_permissions (
    user_id INT,
    permission_id INT,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

### 4.4 Route-Based Permission System with Role Arrays

#### Enhanced Permission Structure
The system supports both model-specific and route-specific permissions with role-based access control:

**Permissions Table Structure:**
```sql
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(100) NOT NULL,
    model VARCHAR(100) DEFAULT '' NOT NULL,
    allowed_roles JSON NOT NULL DEFAULT '[]',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_permission_model (action, model),
    INDEX idx_model (model),
    INDEX idx_action (action)
);
```

#### Route-Level Permission Definition
API controllers can define permissions directly in their route registration:

```php
// Example: MetadataAPIController
public function registerRoutes(): array {
    return [
        [
            'method' => 'GET',
            'path' => '/metadata',
            'apiClass' => 'MetadataAPIController',
            'apiMethod' => 'getMetadata',
            'allowedRoles' => ['admin', 'developer'], // Route-specific permissions
            'permissionAction' => 'metadata.view' // Optional explicit action
        ],
        [
            'method' => 'POST',
            'path' => '/metadata/refresh',
            'apiClass' => 'MetadataAPIController',
            'apiMethod' => 'refreshMetadata',
            'allowedRoles' => ['admin']
        ]
    ];
}

// Example: ModelBaseAPIController with granular permissions
public function registerRoutes(): array {
    return [
        [
            'method' => 'GET',
            'path' => '/?',
            'parameterNames' => ['modelName'],
            'apiClass' => 'ModelBaseAPIController',
            'apiMethod' => 'list',
            'allowedRoles' => ['user', 'manager', 'admin'], // Default for list
            'permissionAction' => 'default.list'
        ],
        [
            'method' => 'GET',
            'path' => '/?/deleted',
            'parameterNames' => ['modelName', ''],
            'apiClass' => 'ModelBaseAPIController',
            'apiMethod' => 'listDeleted',
            'allowedRoles' => ['admin'], // More restrictive than list
            'permissionAction' => 'default.list_deleted'
        ],
        [
            'method' => 'PUT',
            'path' => '/?/?/restore',
            'parameterNames' => ['modelName', 'id', ''],
            'apiClass' => 'ModelBaseAPIController',
            'apiMethod' => 'restore',
            'allowedRoles' => ['manager', 'admin'], // Different from update
            'permissionAction' => 'default.restore'
        ]
    ];
}
```

#### Permission Fallback Hierarchy
1. **Route-Specific**: Check `allowedRoles` array in route definition
2. **Model-Specific**: Check permission with specific model name
3. **Default/Wildcard**: Check permission with model='*' (default permissions)
4. **Deny**: No permission found = access denied

#### APIRouteRegistry Enhancement for Permission Sync
Enhance the existing APIRouteRegistry to support route permission synchronization:

```php
// Enhanced APIRouteRegistry with permission support
class APIRouteRegistry {
    
    private RoutePermissionSynchronizer $permissionSync;
    
    public function __construct() {
        $this->permissionSync = ServiceLocator::getRoutePermissionSynchronizer();
    }
    
    /**
     * Discover routes and sync permissions to database
     */
    public function discoverAndSyncRoutes(): array {
        // Existing route discovery logic
        $discoveredRoutes = $this->discoverAllRoutes();
        
        // NEW: Sync route permissions to database
        $this->permissionSync->syncRoutesToPermissions($discoveredRoutes);
        
        return $discoveredRoutes;
    }
    
    /**
     * Get discovered routes with permission metadata
     */
    public function getDiscoveredRoutesWithPermissions(): array {
        $routes = $this->getCachedRoutes();
        
        // Add permission metadata to each route
        foreach ($routes as &$route) {
            if (!isset($route['allowedRoles'])) {
                // Set default permissions for routes without explicit definition
                $route['allowedRoles'] = $this->getDefaultRolesForRoute($route);
            }
        }
        
        return $routes;
    }
    
    /**
     * Get default roles for routes without explicit permission definition
     */
    private function getDefaultRolesForRoute(array $route): array {
        // Public endpoints
        $publicEndpoints = ['/health', '/auth/google', '/auth/google/callback'];
        if (in_array($route['path'], $publicEndpoints)) {
            return ['all']; // Public access using 'all' keyword
        }
        
        // Model endpoints default to basic user access
        if (isset($route['parameterNames']) && in_array('modelName', $route['parameterNames'])) {
            return ['user', 'manager', 'admin']; // Basic model access
        }
        
        // System endpoints default to admin only
        return ['admin'];
    }
    
    /**
     * Bootstrap route permissions during system initialization
     */
    public function initializePermissions(): void {
        // Discover all routes
        $routes = $this->discoverAndSyncRoutes();
        
        // Log permission sync results
        $this->logger->info('Route permission initialization complete', [
            'total_routes' => count($routes),
            'routes_with_permissions' => count(array_filter($routes, fn($r) => isset($r['allowedRoles']))),
            'sync_timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Integration with bootstrap process
// In setup.php or bootstrap.php:
$routeRegistry = ServiceLocator::getAPIRouteRegistry();
$routeRegistry->initializePermissions(); // Sync routes to permissions table
```

#### Cache Invalidation Strategy
**Admin-Based Cache Cleaning Approach** (as requested):

```php
// Admin cache management endpoint
class CacheAdminController extends APIControllerBase {
    
    public function registerRoutes(): array {
        return [
            [
                'method' => 'POST',
                'path' => '/admin/cache/clear',
                'apiClass' => 'CacheAdminController',
                'apiMethod' => 'clearAllCaches',
                'allowedRoles' => ['admin'], // Admin-only cache management
                'permissionAction' => 'cache.admin'
            ],
            [
                'method' => 'POST',
                'path' => '/admin/permissions/sync',
                'apiClass' => 'CacheAdminController',
                'apiMethod' => 'syncPermissions',
                'allowedRoles' => ['admin'],
                'permissionAction' => 'permissions.sync'
            ]
        ];
    }
    
    public function clearAllCaches(): array {
        // Clear route cache
        $this->clearFile('/cache/api_routes.php');
        
        // Clear metadata cache
        $this->clearFile('/cache/metadata_cache.php');
        
        // Re-sync route permissions
        $routeRegistry = ServiceLocator::getAPIRouteRegistry();
        $routeRegistry->initializePermissions();
        
        return [
            'success' => true,
            'message' => 'All caches cleared and permissions re-synchronized',
            'cleared_caches' => ['routes', 'metadata', 'permissions'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    public function syncPermissions(): array {
        $routeRegistry = ServiceLocator::getAPIRouteRegistry();
        $routes = $routeRegistry->discoverAndSyncRoutes();
        
        return [
            'success' => true,
            'message' => 'Route permissions synchronized',
            'synced_routes' => count($routes),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

#### Default Model-Aware Permissions
```sql
-- Default permissions (model = '*') - apply to all models unless overridden
INSERT INTO permissions (action, model, allowed_roles, description) VALUES
('default.list', '*', '["user", "manager", "admin"]', 'List records for any model'),
('default.read', '*', '["user", "manager", "admin"]', 'Read individual records'),
('default.create', '*', '["manager", "admin"]', 'Create records in any model'),
('default.update', '*', '["manager", "admin"]', 'Update records in any model'),
('default.delete', '*', '["admin"]', 'Delete records from any model'),
('default.restore', '*', '["admin"]', 'Restore soft-deleted records'),
('default.list_deleted', '*', '["admin"]', 'List soft-deleted records'),

-- Model-specific overrides (more restrictive for sensitive models)
('list', 'Users', '["admin"]', 'List users - admin only'),
('create', 'Users', '["admin"]', 'Create users - admin only'),
('update', 'Users', '["manager", "admin"]', 'Update users'),
('delete', 'Users', '["admin"]', 'Delete users - admin only'),

-- Non-model permissions for special endpoints
('metadata.view', '', '["admin", "developer"]', 'View system metadata'),
('metadata.refresh', '', '["admin"]', 'Refresh metadata cache'),
('system.admin', '', '["admin"]', 'Full system administration'),
('api.access', '', '["user", "manager", "admin"]', 'Basic API access');
```

#### Default Role-Permission Assignments
```sql
-- Example role-permission assignments for a "deny by default" system

-- 1. ADMIN ROLE (id=1) - Gets ALL permissions
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM permissions; -- Admin gets every permission

-- 2. MANAGER ROLE (id=2) - Gets elevated but not system admin
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 2, id FROM permissions 
WHERE action IN ('create', 'read', 'update', 'list') 
AND model IN ('Users', 'JwtRefreshTokens', 'GoogleOauthTokens');

-- 3. USER ROLE (id=3) - OAuth default, minimal permissions
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 3, id FROM permissions 
WHERE (action = 'read' AND model = 'Users') -- Can read their own profile
OR (action = 'update' AND model = 'Users')   -- Can update their own profile
OR (action = 'api.access' AND model = '');   -- Basic API access

-- 4. GUEST ROLE (id=4) - Read-only access
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 4, id FROM permissions 
WHERE action = 'read' AND model != '';       -- Read-only for all models
```

#### Permission Seeding Helper
```php
// Helper class for managing model permissions
class PermissionSeeder {
    
    /**
     * Automatically create CRUD permissions for a new model
     */
    public function seedModelPermissions(string $modelName): void {
        $actions = ['create', 'read', 'update', 'delete', 'list', 'restore'];
        
        foreach ($actions as $action) {
            $this->createPermission($action, $modelName, "
            echo "Added permission: {$action} {$modelName}\\n";
        }
    }
    
    /**
     * Create standard role assignments for a model
     */
    public function assignStandardRoles(string $modelName): void {
        // Admin gets all permissions for this model
        $this->assignRolePermissions('admin', $modelName, ['create', 'read', 'update', 'delete', 'list', 'restore']);
        
        // Manager gets most permissions except delete
        $this->assignRolePermissions('manager', $modelName, ['create', 'read', 'update', 'list', 'restore']);
        
        // User gets read-only
        $this->assignRolePermissions('user', $modelName, ['read', 'list']);
        
        echo "Assigned standard roles for {$modelName}\\n";
    }
}

// Usage for new models:
$seeder = new PermissionSeeder();
$seeder->seedModelPermissions('Movies');
$seeder->assignStandardRoles('Movies');
```

**Permission Checking Logic:**
- **Model-Specific**: `hasPermission('create', 'Users')` checks for permission with action='create' AND model='Users'
- **Global**: `hasPermission('system.admin')` checks for permission with action='system.admin' AND model=''
- **ModelBaseAPIController Integration**: Automatically maps HTTP methods to permissions:
  - `GET /api/users` → `hasPermission('list', 'Users')`
  - `POST /api/users` → `hasPermission('create', 'Users')`
  - `PUT /api/users/123` → `hasPermission('update', 'Users')`
  - `DELETE /api/users/123` → `hasPermission('delete', 'Users')`

### 4.5 Default Permission Behavior & Security Policy

#### Security Approach: "Deny by Default" (Whitelist)
The system implements a **"deny by default"** security policy, which means:

**❌ SCENARIO: Missing Permission**
```php
// User tries to create a Movie record
// No permission exists for ('create', 'Movies') in database
$canCreate = $authService->hasPermission($user, 'create', 'Movies');
// Result: FALSE - Access DENIED
```

**✅ SCENARIO: Explicit Permission**
```php
// Permission exists: ('create', 'Movies', 'Create movie records')
$canCreate = $authService->hasPermission($user, 'create', 'Movies');
// Result: TRUE - Access GRANTED
```

#### Default Behavior Examples

**Case 1: New Model Added**
- Developer creates new `Movies` model using framework
- No permissions exist for Movies in database yet
- **Result**: ALL operations on Movies are denied until permissions are added

**Case 2: New User Registration**
- User registers via Google OAuth
- Automatically assigned "user" role (is_oauth_default=true)
- "user" role has NO permissions by default
- **Result**: User cannot access any API endpoints until permissions are granted

**Case 3: Model-Specific vs Global Permissions**
```sql
-- User has this permission:
('create', '', 'Create records in any model')

-- User tries to create Movies:
hasPermission($user, 'create', 'Movies') → FALSE
-- Because it looks for exact match: ('create', 'Movies')

-- Alternative: Use fallback logic
hasPermissionWithFallback($user, 'create', 'Movies') → TRUE
-- Because it falls back to ('create', '') which matches
```

#### Permission Setup Requirements

**For Each New Model** (e.g., Movies):
```sql
-- Required permissions to add:
INSERT INTO permissions (action, model, description) VALUES
('create', 'Movies', 'Create new movie records'),
('read', 'Movies', 'View movie details'),
('update', 'Movies', 'Update movie information'),
('delete', 'Movies', 'Delete movie records'),
('list', 'Movies', 'List and search movies');

-- Then assign to roles:
INSERT INTO role_permissions (role_id, permission_id) VALUES
(1, <permission_id>), -- Admin gets all
(3, <read_permission_id>), -- User gets read-only
(3, <list_permission_id>); -- User gets list access
```

#### Alternative Security Approaches (Not Recommended)

**"Allow by Default" (Blacklist) - NOT IMPLEMENTED:**
- Would allow all operations unless explicitly denied
- Less secure, requires defensive permission setup
- Risk of accidental data exposure

**Hybrid Approach - AVAILABLE via hasPermissionWithFallback():**
- Explicit model permissions take precedence
- Falls back to global permissions: ('create', '') applies to all models
- Falls back to super admin: ('system.admin', '') grants everything
- Still denies if no fallback matches

#### Implementation Strategy

**Recommended Approach:**
1. **Start Restrictive**: Use `hasPermission()` (deny by default)
2. **Setup Core Permissions**: Define permissions for essential models (Users, Roles, Permissions)
3. **Expand Gradually**: Add permissions for new models as they're created
4. **Use Roles**: Assign permissions to roles, assign roles to users

**Development vs Production:**
- **Development**: Consider using `hasPermissionWithFallback()` for easier testing
- **Production**: Use strict `hasPermission()` for maximum security

## 5. Implementation Steps

### 5.1 Phase 1: Dependencies & Google OAuth Setup (Week 1)

#### Step 1: Install Dependencies
```bash
composer require google/auth
composer require league/oauth2-google
composer require firebase/php-jwt
```

#### Step 2: Google OAuth Configuration
> **NOTE**: These steps must be performed by you as they require Google account access and administrative decisions.

##### 2.1: Set up Google Cloud Console Project

**Navigate to Google Cloud Console:**
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Sign in with your Google account that will manage this project

**Create or Select Project:**
1. Click the project dropdown at the top of the page (next to "Google Cloud Platform")
2. Click "NEW PROJECT" in the popup window
3. **Project Details:**
   - **Project name**: `gravitycar-auth` (or your preferred name)
   - **Organization**: Select your organization (if applicable)
   - **Location**: Select parent organization or "No organization"
4. Click "CREATE"
5. Wait for project creation (usually takes 30-60 seconds)
6. Make sure your new project is selected in the project dropdown

##### 2.2: Enable Required APIs

**Enable Google+ API:**
1. In the left sidebar, navigate to "APIs & Services" > "Library"
2. Search for "Google+ API"
3. Click on "Google+ API" from the results
4. Click "ENABLE" button
5. Wait for enablement confirmation

**Enable People API:**
1. While still in the API Library, search for "People API"
2. Click on "Google People API" from the results
3. Click "ENABLE" button
4. Wait for enablement confirmation

##### 2.3: Configure OAuth Consent Screen

**Navigate to OAuth Consent:**
1. Go to "APIs & Services" > "OAuth consent screen" in the left sidebar
2. **Choose User Type:**
   - Select "External" (for public applications)
   - Select "Internal" only if you have a Google Workspace organization
3. Click "CREATE"

**OAuth Consent Screen Configuration:**

**App Information Tab:**
- **App name**: `Gravitycar Authentication` (or your app name)
- **User support email**: Your email address
- **App logo**: (Optional) Upload your app logo (120x120px recommended)
- **App domain**: 
  - Application home page: `https://yourdomain.com`
  - Application privacy policy link: `https://yourdomain.com/privacy`
  - Application terms of service link: `https://yourdomain.com/terms`
- **Authorized domains**: Add your domain (e.g., `yourdomain.com`)
- **Developer contact information**: Your email address

**Scopes Tab:**
1. Click "ADD OR REMOVE SCOPES"
2. **Required Scopes to Add:**
   - `../auth/userinfo.email` - See your primary Google Account email address
   - `../auth/userinfo.profile` - See your personal info, including any personal info you've made publicly available
   - `openid` - Associate you with your personal info on Google
3. Click "UPDATE" then "SAVE AND CONTINUE"

**Test Users Tab:** (for External apps in testing)
1. Click "ADD USERS"
2. Add email addresses of users who can test the OAuth flow
3. Click "SAVE AND CONTINUE"

**Summary Tab:**
1. Review all settings
2. Click "BACK TO DASHBOARD"

##### 2.4: Create OAuth 2.0 Credentials

**Navigate to Credentials:**
1. Go to "APIs & Services" > "Credentials" in the left sidebar
2. Click "CREATE CREDENTIALS" dropdown
3. Select "OAuth client ID"

**Configure OAuth Client:**
1. **Application type**: Select "Web application"
2. **Name**: `GravitycarWebClient`
3. **Authorized JavaScript origins:**
   ```
   http://localhost:3000
   https://yourdomain.com
   ```
   (Add both development and production URLs)

4. **Authorized redirect URIs:**
   ```
   http://localhost:3000/auth/google/callback
   https://yourdomain.com/auth/google/callback
   ```
   (These are where Google will redirect after authentication)

5. Click "CREATE"

**Save Your Credentials:**
1. **Client ID**: Copy this value (looks like `123456789-abcdefg.apps.googleusercontent.com`)
2. **Client Secret**: Copy this value (looks like `GOCSPX-abcdefghijklmnop`)
3. Click "OK" to close the popup
4. **Download JSON**: Click the download icon next to your OAuth client for backup

##### 2.5: Configure Environment Variables

**Create/Update .env file:**
Create or update your `.env` file in the project root with:

```bash
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your_actual_client_id_here
GOOGLE_CLIENT_SECRET=your_actual_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost:3000/auth/google/callback

# For Production, also add:
# GOOGLE_REDIRECT_URI_PROD=https://yourdomain.com/auth/google/callback

# JWT Configuration (generate strong random keys)
JWT_SECRET_KEY=your_256_bit_secret_key_here
JWT_REFRESH_SECRET=your_256_bit_refresh_secret_here
JWT_ACCESS_TOKEN_LIFETIME=3600
JWT_REFRESH_TOKEN_LIFETIME=2592000

# OAuth Settings
OAUTH_DEFAULT_ROLE=user
OAUTH_AUTO_CREATE_USERS=true
OAUTH_SYNC_PROFILE_ON_LOGIN=true
```

**Generate Secure JWT Keys:**
Use this command to generate secure keys:
```bash
# For JWT_SECRET_KEY
openssl rand -base64 32

# For JWT_REFRESH_SECRET  
openssl rand -base64 32
```

##### 2.6: Security Configuration Notes

**Important Security Settings:**
1. **Never commit credentials**: Add `.env` to your `.gitignore` file
2. **Use different credentials for development/production**
3. **Restrict API keys**: In Google Cloud Console, go to "APIs & Services" > "Credentials" and restrict your API keys by HTTP referrers
4. **Monitor usage**: Set up billing alerts and monitor API usage

**Testing Your Configuration:**
1. You can test OAuth flow using Google's OAuth 2.0 Playground: https://developers.google.com/oauthplayground
2. Use your Client ID and configure the playground to test scopes
3. Verify that your redirect URIs work correctly

**Common Issues and Solutions:**
- **"redirect_uri_mismatch" error**: Ensure your redirect URI in the code exactly matches what's configured in Google Cloud Console
- **"invalid_client" error**: Check that your Client ID and Secret are correct
- **"access_denied" error**: User cancelled authorization or your app needs verification for sensitive scopes
- **"unauthorized_client" error**: Your OAuth client configuration is incorrect

**Production Considerations:**
1. **Domain Verification**: For production, verify your domain in Google Search Console
2. **App Verification**: For apps using sensitive scopes, Google may require app verification
3. **Quota Management**: Monitor and potentially increase API quotas for production use
4. **SSL/HTTPS**: Always use HTTPS in production for OAuth flows

This completes the Google OAuth setup. Once you have these credentials configured, we can proceed with the code implementation in the subsequent steps.

#### Step 3: Enhanced User Model Metadata
- Update `src/Models/users/users_metadata.php` with Google OAuth fields
- Add the following new fields to the metadata:
  - `google_id` (TextField) - Google user ID
  - `google_email` (EmailField) - Email from Google profile  
  - `google_verified_email` (BooleanField) - Google email verification status
  - `profile_picture_url` (ImageField) - Google profile picture URL with width/height metadata
  - `auth_provider` (EnumField) - Authentication method ('local', 'google', 'hybrid')
  - `last_login_method` (TextField) - Last used authentication method
  - `email_verified_at` (DateTimeField) - Email verification timestamp
  - `last_google_sync` (DateTimeField) - Last Google profile sync
  - `is_active` (BooleanField) - User active status
- Update field constraints (make username and password optional for OAuth users)
- Update validation rules appropriately

#### Step 4: Generate Updated Database Schema
> **METADATA-DRIVEN APPROACH**: All database changes are accomplished through metadata file updates, never direct SQL execution.

- Run schema generator to add new OAuth columns to users table
- Verify new columns are created correctly
- Test existing user records are preserved

#### Step 6: Create Additional Authentication Models and Relationships
**New Models (extend ModelBase):**
- Create `src/Models/jwt_refresh_tokens/` directory with:
  - `JwtRefreshTokens.php` (ModelBase class)
  - `jwt_refresh_tokens_metadata.php` (field definitions)
- Create `src/Models/google_oauth_tokens/` directory with:
  - `GoogleOauthTokens.php` (ModelBase class)  
  - `google_oauth_tokens_metadata.php` (field definitions)
- Create `src/Models/roles/` directory with:
  - `Roles.php` (ModelBase class)
  - `roles_metadata.php` (field definitions)
- Create `src/Models/permissions/` directory with:
  - `Permissions.php` (ModelBase class)
  - `permissions_metadata.php` (field definitions)

**New Relationships (many-to-many junction tables):**
- Create `src/Relationships/roles_permissions/` directory with:
  - `roles_permissions_metadata.php` (relationship definition between Roles and Permissions)
- Create `src/Relationships/users_permissions/` directory with:
  - `users_permissions_metadata.php` (relationship definition between Users and Permissions)

**Model-to-User Relationships (foreign key relationships):**
- `jwt_refresh_tokens` has foreign key to `users` (OneToMany: User -> JwtRefreshTokens)
- `google_oauth_tokens` has foreign key to `users` (OneToMany: User -> GoogleOauthTokens)

**Schema Generation:**
> **FRAMEWORK-DRIVEN**: Use `php setup.php` to generate all tables from metadata - never execute SQL directly.

- Generate schema for all new models and relationships
- Verify foreign key constraints are properly created

#### Step 7: Create Model Classes and Metadata Files

**For each new model, create both the PHP class and metadata file:**

**JwtRefreshTokens Model:**
```php
// src/Models/jwt_refresh_tokens/JwtRefreshTokens.php
<?php
namespace Gravitycar\Models\jwt_refresh_tokens;

use Gravitycar\Models\ModelBase;

class JwtRefreshTokens extends ModelBase {
    // Custom methods for token validation and cleanup
}
```

```php
// src/Models/jwt_refresh_tokens/jwt_refresh_tokens_metadata.php
<?php
return [
    'name' => 'JwtRefreshTokens',
    'table' => 'jwt_refresh_tokens',
    'fields' => [
        'user_id' => [
            'name' => 'user_id',
            'type' => 'RelatedRecordField',
            'label' => 'User ID',
            'required' => true,
            'relatedModel' => 'Users',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'user_name',
            'validationRules' => ['Required'],
        ],
        'user_name' => [
            'name' => 'user_name',
            'type' => 'Text',
            'label' => 'User Name',
            'description' => 'Display name of the user who owns this token',
            'required' => false,
            'readOnly' => true,
            'isDBField' => false,
            'nullable' => true,
            'validationRules' => [],
        ],
        'token_hash' => [
            'name' => 'token_hash',
            'type' => 'Text',
            'label' => 'Token Hash',
            'required' => true,
            'maxLength' => 255,
            'validationRules' => ['Required'],
        ],
        'expires_at' => [
            'name' => 'expires_at',
            'type' => 'DateTime',
            'label' => 'Expires At',
            'required' => true,
            'validationRules' => ['Required', 'DateTime'],
        ],
        // Core fields (id, created_at, updated_at) automatically added
    ],
];
```

```php
// src/Models/google_oauth_tokens/google_oauth_tokens_metadata.php
<?php
return [
    'name' => 'GoogleOauthTokens',
    'table' => 'google_oauth_tokens',
    'fields' => [
        'user_id' => [
            'name' => 'user_id',
            'type' => 'RelatedRecordField',
            'label' => 'User ID',
            'required' => true,
            'relatedModel' => 'Users',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'user_name',
            'validationRules' => ['Required'],
        ],
        'user_name' => [
            'name' => 'user_name',
            'type' => 'Text',
            'label' => 'User Name',
            'description' => 'Display name of the user who owns this OAuth token',
            'required' => false,
            'readOnly' => true,
            'isDBField' => false,
            'nullable' => true,
            'validationRules' => [],
        ],
        'access_token_hash' => [
            'name' => 'access_token_hash',
            'type' => 'Text',
            'label' => 'Access Token Hash',
            'required' => false,
            'maxLength' => 255,
            'nullable' => true,
            'validationRules' => [],
        ],
        'refresh_token_hash' => [
            'name' => 'refresh_token_hash',
            'type' => 'Text',
            'label' => 'Refresh Token Hash',
            'required' => false,
            'maxLength' => 255,
            'nullable' => true,
            'validationRules' => [],
        ],
        'token_expires_at' => [
            'name' => 'token_expires_at',
            'type' => 'DateTime',
            'label' => 'Token Expires At',
            'required' => false,
            'nullable' => true,
            'validationRules' => ['DateTime'],
        ],
        'scope' => [
            'name' => 'scope',
            'type' => 'BigTextField',
            'label' => 'OAuth Scope',
            'required' => false,
            'nullable' => true,
            'validationRules' => [],
        ],
        // Core fields (id, created_at, updated_at) automatically added
    ],
];
```

```php
// src/Models/roles/roles_metadata.php
<?php
return [
    'name' => 'Roles',
    'table' => 'roles',
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'Text',
            'label' => 'Role Name',
            'required' => true,
            'maxLength' => 50,
            'validationRules' => ['Required'],
        ],
        'description' => [
            'name' => 'description',
            'type' => 'BigTextField',
            'label' => 'Role Description',
            'required' => false,
            'nullable' => true,
            'validationRules' => [],
        ],
        'is_oauth_default' => [
            'name' => 'is_oauth_default',
            'type' => 'Boolean',
            'label' => 'Default OAuth Role',
            'description' => 'Whether this role is automatically assigned to new OAuth users',
            'required' => false,
            'defaultValue' => false,
            'validationRules' => [],
        ],
        // Core fields (id, created_at, updated_at) automatically added
    ],
];
```

```php
// src/Models/permissions/permissions_metadata.php
<?php
return [
    'name' => 'Permissions',
    'table' => 'permissions',
    'fields' => [
        'action' => [
            'name' => 'action',
            'type' => 'Text',
            'label' => 'Permission Action',
            'description' => 'Action or operation name (e.g., create, read, update, delete)',
            'required' => true,
            'maxLength' => 100,
            'validationRules' => ['Required'],
        ],
        'model' => [
            'name' => 'model',
            'type' => 'Text',
            'label' => 'Model Name',
            'description' => 'Model this permission applies to (empty for global permissions)',
            'required' => true,
            'maxLength' => 100,
            'defaultValue' => '',
            'validationRules' => [],
        ],
        'description' => [
            'name' => 'description',
            'type' => 'BigTextField',
            'label' => 'Permission Description',
            'required' => false,
            'nullable' => true,
            'validationRules' => [],
        ],
        // Core fields (id, created_at, updated_at) automatically added
    ],
];
```

**Relationship Metadata Files:**

```php
// src/Relationships/roles_permissions/roles_permissions_metadata.php
<?php
return [
    'name' => 'roles_permissions',
    'type' => 'ManyToMany',
    'modelOne' => 'Roles',
    'modelMany' => 'Permissions',
    'constraints' => [],
    'additionalFields' => [],
];
```

```php
// src/Relationships/users_permissions/users_permissions_metadata.php  
<?php
return [
    'name' => 'users_permissions',
    'type' => 'ManyToMany',
    'modelOne' => 'Users',
    'modelMany' => 'Permissions',
    'constraints' => [],
    'additionalFields' => [],
];
```

#### Step 8: Test Enhanced Models and Relationships
- Run `php setup.php` to regenerate schema with new OAuth fields and models
- Verify all new tables are created correctly:
  - Users table has new OAuth columns
  - jwt_refresh_tokens table with foreign key to users
  - google_oauth_tokens table with foreign key to users  
  - roles and permissions tables
  - role_permissions and user_permissions junction tables
- Test that existing user data is preserved
- Validate that field constraints and relationships work correctly
- Test ModelFactory can create instances of all new models
- Verify relationships between models function properly

### 5.2 Phase 2: Authentication Services (Week 2)

#### Step 1: Enhanced Authentication Service
- Create AuthenticationService with OAuth support
- Implement Google token validation workflow
- Add automatic user creation from Google profiles
- Implement JWT token generation for authenticated users

#### Step 1a: Route-Based Authorization Service with Permission Synchronization
Create an enhanced authorization service that handles both route-level and model-level permissions:

```php
// src/Services/AuthorizationService.php
class AuthorizationService {
    
    /**
     * Check if user has permission for a specific action and model
     * Enhanced to support route-based role checking
     */
    public function hasPermission(string $action, string $model = '', $user = null): bool {
        // Get current user if not provided
        if ($user === null) {
            $user = ServiceLocator::getCurrentUser();
            if (!$user) {
                return false;
            }
        }
        
        $userRoles = $this->getUserRoles($user);
        $userRoleNames = array_column($userRoles, 'name');
        
        // Check permission using fallback hierarchy
        return $this->checkPermissionHierarchy($action, $model, $userRoleNames);
    }
    
    /**
     * Check permission using fallback hierarchy
     */
    private function checkPermissionHierarchy(string $action, string $model, array $userRoleNames): bool {
        // 1. Check model-specific permission first (if model provided)
        if ($model && $model !== '*') {
            if ($this->checkRolePermission($action, $model, $userRoleNames)) {
                return true;
            }
        }
        
        // 2. Fallback to default/wildcard permission
        if ($this->checkRolePermission($action, '*', $userRoleNames)) {
            return true;
        }
        
        // 3. Check super admin permission
        if ($this->checkRolePermission('system.admin', '', $userRoleNames)) {
            return true;
        }
        
        // 4. Default deny
        return false;
    }
    
    /**
     * Check if user roles have permission
     */
    private function checkRolePermission(string $action, string $model, array $userRoleNames): bool {
        $permission = $this->getPermission($action, $model);
        if (!$permission) {
            return false;
        }
        
        $allowedRoles = json_decode($permission['allowed_roles'], true) ?? [];
        
        // Check for wildcard access
        if (in_array('*', $allowedRoles)) {
            return true;
        }
        
        // Check role intersection
        return !empty(array_intersect($userRoleNames, $allowedRoles));
    }
    
    /**
     * Authorize request using route-defined roles (preferred method)
     */
    public function authorizeByRoute(array $route, $user = null): bool {
        if (!isset($route['allowedRoles'])) {
            return false; // No route permissions defined
        }
        
        if ($user === null) {
            $user = ServiceLocator::getCurrentUser();
            if (!$user) {
                return false;
            }
        }
        
        $userRoles = $this->getUserRoles($user);
        $userRoleNames = array_column($userRoles, 'name');
        $allowedRoles = $this->normalizeAllowedRoles($route['allowedRoles']);
        
        // Check for public access (wildcard or 'all' keyword)
        if (in_array('*', $allowedRoles) || in_array('all', $allowedRoles)) {
            return true;
        }
        
        // Check role intersection
        return !empty(array_intersect($userRoleNames, $allowedRoles));
    }
    
    /**
     * Normalize allowed roles array, converting 'all' to '*' for consistency
     */
    private function normalizeAllowedRoles(array $allowedRoles): array {
        return array_map(function($role) {
            return strtolower($role) === 'all' ? '*' : $role;
        }, $allowedRoles);
    }
    
    private function getPermission(string $action, string $model): ?array {
        // Database query to get permission
        $db = ServiceLocator::getDatabaseConnector();
        $result = $db->select('permissions', [
            'action' => $action,
            'model' => $model
        ]);
        
        return $result[0] ?? null;
    }
    
    private function getUserRoles($user): array {
        // Get user roles from database
        $db = ServiceLocator::getDatabaseConnector();
        return $db->query("
            SELECT r.* FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ?
        ", [$user->get('id')]);
    }
}

// src/Services/RoutePermissionSynchronizer.php
class RoutePermissionSynchronizer {
    
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = ServiceLocator::getDatabaseConnector();
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Sync all discovered routes to permissions table
     */
    public function syncRoutesToPermissions(array $routes): void {
        $this->logger->info('Starting route permission synchronization');
        
        $syncedCount = 0;
        foreach ($routes as $route) {
            if (isset($route['allowedRoles'])) {
                $this->syncRoutePermission($route);
                $syncedCount++;
            }
        }
        
        $this->logger->info("Synchronized {$syncedCount} route permissions");
    }
    
    /**
     * Sync individual route to permissions table
     */
    private function syncRoutePermission(array $route): void {
        $permissionAction = $this->getPermissionAction($route);
        $model = $this->extractModel($route);
        $allowedRoles = $this->normalizeAllowedRoles($route['allowedRoles']);
        $description = $this->generateDescription($route);
        
        // Check if permission exists
        $existing = $this->db->select('permissions', [
            'action' => $permissionAction,
            'model' => $model
        ]);
        
        if (!empty($existing)) {
            // Update existing permission
            $this->db->update('permissions', [
                'allowed_roles' => $allowedRoles,
                'description' => $description,
                'updated_at' => date('Y-m-d H:i:s')
            ], [
                'action' => $permissionAction,
                'model' => $model
            ]);
        } else {
            // Insert new permission
            $this->db->insert('permissions', [
                'action' => $permissionAction,
                'model' => $model,
                'allowed_roles' => $allowedRoles,
                'description' => $description
            ]);
        }
        
        $this->logger->debug("Synced permission: {$permissionAction} ({$model})");
    }
    
    /**
     * Normalize allowed roles, converting 'all' keyword to '*'
     */
    private function normalizeAllowedRoles(array $allowedRoles): string {
        // Convert 'all' keyword to '*' for consistency
        $normalizedRoles = array_map(function($role) {
            return strtolower($role) === 'all' ? '*' : $role;
        }, $allowedRoles);
        
        return json_encode($normalizedRoles);
    }
    
    /**
     * Generate permission action from route
     */
    private function getPermissionAction(array $route): string {
        // Use explicit permissionAction if provided
        if (isset($route['permissionAction'])) {
            return $route['permissionAction'];
        }
        
        // Generate from route path and method
        $path = trim($route['path'], '/');
        $method = strtolower($route['method']);
        
        // Handle special cases
        if (str_contains($path, 'deleted')) {
            return 'default.list_deleted';
        }
        if (str_contains($path, 'restore')) {
            return 'default.restore';
        }
        if (str_contains($path, 'metadata')) {
            return 'metadata.view';
        }
        
        // Map HTTP methods to actions
        $actionMap = [
            'get' => str_contains($path, '/?/?') ? 'default.read' : 'default.list',
            'post' => 'default.create',
            'put' => 'default.update',
            'patch' => 'default.update',
            'delete' => 'default.delete'
        ];
        
        return $actionMap[$method] ?? 'default.access';
    }
    
    /**
     * Extract model name from route
     */
    private function extractModel(array $route): string {
        $parameterNames = $route['parameterNames'] ?? [];
        
        // Check if route has modelName parameter
        if (in_array('modelName', $parameterNames)) {
            return '*'; // Wildcard for generic model routes
        }
        
        // Non-model routes use empty string
        return '';
    }
    
    /**
     * Generate human-readable description
     */
    private function generateDescription(array $route): string {
        $method = strtoupper($route['method']);
        $path = $route['path'];
        $roles = implode(', ', $route['allowedRoles']);
        
        return "Route permission: {$method} {$path} (Roles: {$roles})";
    }
}
```
    
    /**
     * Authorize ModelBaseAPIController request
     */
    public function authorizeModelRequest(string $method, string $modelName, $user = null): bool {
        $actionMap = [
            'GET' => 'list',     // For list operations
            'POST' => 'create',
            'PUT' => 'update',
            'DELETE' => 'delete'
        ];
        
        // For specific record retrieval, use 'read' instead of 'list'
        $action = $actionMap[$method] ?? 'read';
        
        return $this->hasPermission($action, $modelName, $user);
    }
    
    /**
     * Get permission requirements for ModelBaseAPIController routes
     */
    public function getModelPermissionRequirement(string $route, string $method): array {
        // Parse route to extract model name and determine action
        // Examples:
        // GET /api/users -> ['action' => 'list', 'model' => 'Users']
        // POST /api/users -> ['action' => 'create', 'model' => 'Users']
        // PUT /api/users/123 -> ['action' => 'update', 'model' => 'Users']
        // DELETE /api/users/123 -> ['action' => 'delete', 'model' => 'Users']
        
        $segments = explode('/', trim($route, '/'));
        $modelName = ucfirst($segments[1] ?? ''); // Convert to PascalCase
        
        $actionMap = [
            'GET' => isset($segments[2]) ? 'read' : 'list',
            'POST' => 'create',
            'PUT' => 'update',
            'DELETE' => 'delete'
        ];
        
        return [
            'action' => $actionMap[$method] ?? 'read',
            'model' => $modelName
        ];
    }
}
```

#### Step 1b: Permission Configuration Options
The authorization system can be configured for different security approaches:

```php
// src/Core/Config.php - Add authorization configuration
class Config {
    
    public static function getAuthorizationConfig(): array {
        return [
            // SECURITY POLICY: Choose one
            'default_behavior' => 'deny', // 'deny' (recommended) or 'allow'
            
            // PERMISSION CHECKING METHOD: Choose one
            'permission_method' => 'strict', // 'strict', 'fallback', or 'hybrid'
            
            // DEVELOPMENT OPTIONS
            'dev_mode_bypass' => false, // Set true to bypass permissions in development
            'log_permission_denials' => true, // Log all denied requests for debugging
            
            // AUTO-SETUP OPTIONS
            'auto_create_model_permissions' => false, // Auto-create permissions for new models
            'default_new_model_roles' => ['admin'], // Which roles get new model permissions
        ];
    }
}

// Enhanced AuthorizationService with configuration
class AuthorizationService {
    
    private array $config;
    
    public function __construct() {
        $this->config = Config::getAuthorizationConfig();
    }
    
    public function hasPermission($user, string $action, string $model = ''): bool {
        // Development bypass (use with caution!)
        if ($this->config['dev_mode_bypass'] && Config::isDevEnvironment()) {
            $this->logPermissionCheck($user, $action, $model, 'BYPASSED');
            return true;
        }
        
        $hasPermission = match($this->config['permission_method']) {
            'strict' => $this->hasExplicitPermission($user, $action, $model),
            'fallback' => $this->hasPermissionWithFallback($user, $action, $model),
            'hybrid' => $this->hasHybridPermission($user, $action, $model),
            default => $this->hasExplicitPermission($user, $action, $model)
        };
        
        // Log permission denials for debugging
        if (!$hasPermission && $this->config['log_permission_denials']) {
            $this->logPermissionDenial($user, $action, $model);
        }
        
        return $hasPermission;
    }
    
    private function logPermissionDenial($user, string $action, string $model): void {
        $this->logger->warning('Permission denied', [
            'user_id' => $user->get('id'),
            'action' => $action,
            'model' => $model,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
```

**Configuration Examples:**

**Production (Maximum Security):**
```php
'default_behavior' => 'deny',
'permission_method' => 'strict',
'dev_mode_bypass' => false,
'auto_create_model_permissions' => false,
```

**Development (Easier Testing):**
```php
'default_behavior' => 'deny',
'permission_method' => 'fallback',
'dev_mode_bypass' => true, // DANGEROUS - remove for production
'auto_create_model_permissions' => true,
'log_permission_denials' => true,
```

**Enterprise (Flexible but Secure):**
```php
'default_behavior' => 'deny',
'permission_method' => 'hybrid',
'auto_create_model_permissions' => true,
'default_new_model_roles' => ['admin', 'manager'],
```

#### Step 1c: ServiceLocator getCurrentUser() Implementation
Update the ServiceLocator to provide access to the authenticated user:

```php
// src/Core/ServiceLocator.php - Enhanced getCurrentUser implementation
class ServiceLocator {
    
    private static ?\Gravitycar\Models\ModelBase $currentUser = null;
    private static ?string $jwtToken = null;
    
    /**
     * Get the current authenticated user
     */
    public static function getCurrentUser(): ?\Gravitycar\Models\ModelBase {
        // Return cached user if available
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }
        
        // Try to get user from JWT token
        try {
            $jwt = self::getJwtFromRequest();
            if ($jwt) {
                $userId = self::validateJwtAndExtractUserId($jwt);
                if ($userId) {
                    self::$currentUser = self::loadUserById($userId);
                    return self::$currentUser;
                }
            }
        } catch (Exception $e) {
            self::getLogger()->warning('Failed to get current user from JWT: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Set current user (for testing and session management)
     */
    public static function setCurrentUser(?\Gravitycar\Models\ModelBase $user): void {
        self::$currentUser = $user;
    }
    
    /**
     * Clear current user (for logout)
     */
    public static function clearCurrentUser(): void {
        self::$currentUser = null;
        self::$jwtToken = null;
    }
    
    private static function getJwtFromRequest(): ?string {
        // Check Authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7); // Remove "Bearer " prefix
        }
        
        // Fallback: Check cookie or session
        return $_COOKIE['jwt_token'] ?? $_SESSION['jwt_token'] ?? null;
    }
    
    private static function validateJwtAndExtractUserId(string $jwt): ?string {
        $authService = new \Gravitycar\Services\AuthenticationService();
        $payload = $authService->validateJwtToken($jwt);
        return $payload['user_id'] ?? null;
    }
    
    private static function loadUserById(string $userId): ?\Gravitycar\Models\ModelBase {
        $userFactory = \Gravitycar\Factories\ModelFactory::new('Users');
        return $userFactory->find(['id' => $userId])[0] ?? null;
    }
    
    /**
     * Get authorization service instance
     */
    public static function getAuthorizationService(): \Gravitycar\Services\AuthorizationService {
        static $authService = null;
        if ($authService === null) {
            $authService = new \Gravitycar\Services\AuthorizationService();
        }
        return $authService;
    }
    
    /**
     * Get route permission synchronizer instance
     */
    public static function getRoutePermissionSynchronizer(): \Gravitycar\Services\RoutePermissionSynchronizer {
        static $permissionSync = null;
        if ($permissionSync === null) {
            $permissionSync = new \Gravitycar\Services\RoutePermissionSynchronizer();
        }
        return $permissionSync;
    }
    
    /**
     * Get API route registry instance
     */
    public static function getAPIRouteRegistry(): \Gravitycar\Services\APIRouteRegistry {
        static $routeRegistry = null;
        if ($routeRegistry === null) {
            $routeRegistry = new \Gravitycar\Services\APIRouteRegistry();
        }
        return $routeRegistry;
    }
}
```

**Authentication Flow Integration:**
```php
// When user logs in via OAuth or traditional auth:
$user = $authService->authenticateUser($credentials);
ServiceLocator::setCurrentUser($user);

// When user logs out:
ServiceLocator::clearCurrentUser();

// Automatic JWT-based user resolution in Router:
$currentUser = ServiceLocator::getCurrentUser(); // Automatically loads from JWT
```

#### Step 2: User Service Enhancement
- Add methods for Google user lookup
- Implement user creation from Google profile
- Add profile synchronization functionality
- Handle user linking (same email, different auth methods)

#### Step 3: Authentication API Endpoints
```php
// Enhanced API Endpoints
POST /auth/google           - Google OAuth authentication
POST /auth/login            - Traditional username/password login
POST /auth/logout           - Universal logout
POST /auth/refresh          - JWT token refresh
GET  /auth/me              - Current user info
POST /auth/register        - Traditional registration
GET  /auth/google/url      - Get Google OAuth authorization URL
```

### 5.3 Phase 3: Middleware & Integration (Week 3)

#### Step 1: Authentication Middleware
- Create middleware for protected routes
- Integrate with existing RestApiHandler
- Add Google token and JWT token support
- Implement automatic authentication detection

#### Step 1a: Router-Based Authorization Integration
**PREFERRED APPROACH**: Implement centralized permission checking in the Router using route-based permissions with role arrays.

```php
// Enhanced Router with route-based authorization
class Router {
    
    protected AuthorizationService $authService;
    protected RoutePermissionSynchronizer $permissionSync;
    
    public function __construct($serviceLocator) {
        // Existing constructor code...
        $this->authService = new AuthorizationService();
        $this->permissionSync = new RoutePermissionSynchronizer();
    }
    
    public function route(string $method, string $path, array $additionalParams = []): mixed {
        // 1. Find matching route (existing logic)
        $bestRoute = $this->findBestMatchingRoute($method, $path);
        
        if (!$bestRoute) {
            throw new NotFoundException("Route not found: {$method} {$path}");
        }
        
        // 2. Create Request object for parameter extraction
        $request = new Request($path, $bestRoute['parameterNames'], $method);
        
        // 3. AUTHORIZATION CHECK - Use route-based permissions
        $this->authorizeRequestByRoute($bestRoute);
        
        // 4. Execute route (existing logic)
        return $this->executeRoute($bestRoute, $request, $additionalParams);
    }
    
    protected function authorizeRequestByRoute(array $route): void {
        // Check if route has permission configuration
        if (!isset($route['allowedRoles'])) {
            // No route permissions defined = deny by default
            throw new ForbiddenException("Access denied: No permissions defined for this route", [
                'route' => $route['path'],
                'method' => $route['method']
            ]);
        }
        
        // Public routes (allow all) - supports both '*' and 'all' keywords
        $allowedRoles = $route['allowedRoles'];
        if (in_array('*', $allowedRoles) || in_array('all', $allowedRoles)) {
            return; // Public access allowed
        }
        
        // Use route-based authorization
        $authorized = $this->authService->authorizeByRoute($route);
        
        if (!$authorized) {
            throw new ForbiddenException("Access denied: Insufficient role permissions", [
                'required_roles' => $route['allowedRoles'],
                'route' => $route['path'],
                'method' => $route['method']
            ]);
        }
        
        $this->logger->info('Route authorization successful', [
            'route' => $route['path'],
            'method' => $route['method'],
            'allowed_roles' => $route['allowedRoles'],
            'user_id' => ServiceLocator::getCurrentUser()?->get('id')
        ]);
    }
    
    /**
     * Initialize router with route-based permissions
     * Called during bootstrap to sync route permissions to database
     */
    public function initializeRoutePermissions(): void {
        // Discover all routes with their role definitions
        $allRoutes = $this->getAllDiscoveredRoutes();
        
        // Sync to permissions table
        $this->permissionSync->syncRoutesToPermissions($allRoutes);
        
        $this->logger->info('Route permissions synchronized to database');
    }
    
    /**
     * Get all discovered routes including permission metadata
     */
    private function getAllDiscoveredRoutes(): array {
        // This would be integrated with APIRouteRegistry
        $registry = ServiceLocator::getAPIRouteRegistry();
        return $registry->getDiscoveredRoutesWithPermissions();
    }
}
```

**Router Authorization Benefits:**
- ✅ **Route-Level Security**: Permissions defined at route discovery time
- ✅ **Role-Based Access**: Uses allowedRoles arrays for granular control
- ✅ **Automatic Sync**: Route permissions synchronized to database
- ✅ **Fallback Hierarchy**: Route → Model → Default permission checking
- ✅ **Public Routes**: Explicit '*' role for public endpoints
- ✅ **Deny by Default**: Routes without allowedRoles are automatically denied

**Route Permission Examples:**
```php
// Examples of route-based permission checking:
// Route with allowedRoles: ['admin', 'manager'] → Check user has admin OR manager role
// Route with allowedRoles: ['*'] → Public access (no authentication required)
// Route with allowedRoles: ['all'] → Public access (semantic alternative to '*')
// Route with no allowedRoles → Automatic denial (secure by default)
// Route with allowedRoles: ['user'] → Only authenticated users with 'user' role

// Example usage of 'all' keyword for public endpoints:
[
    'method' => 'GET',
    'path' => '/health',
    'apiClass' => 'HealthController',
    'apiMethod' => 'getHealth',
    'allowedRoles' => ['all'], // Public health check endpoint
],
[
    'method' => 'GET',
    'path' => '/auth/google',
    'apiClass' => 'AuthController',
    'apiMethod' => 'redirectToGoogle',
    'allowedRoles' => ['all'], // Public OAuth initiation
],
[
    'method' => 'GET',
    'path' => '/api/public-data',
    'apiClass' => 'PublicAPIController',
    'apiMethod' => 'getPublicData',
    'allowedRoles' => ['all'], // Public API endpoint
]
```

#### Step 2: Authorization System
- Implement role-based access control
- Add permission checking
- Create authorization decorators/attributes
- Set up default roles for OAuth users

#### Step 2a: Model-Aware Permission System
The permission system is designed to work seamlessly with the framework's `ModelBaseAPIController`, which dynamically handles CRUD operations for any model. Each permission has both an action (`name`) and an optional `model` field:

**Permission Structure:**
- **name**: The action/operation (e.g., "create", "read", "update", "delete", "list", "restore")
- **model**: The model name (e.g., "Users", "Products", "Orders") or empty string for global permissions
- **description**: Human-readable description

**Permission Checking Pattern:**
```php
// Model-specific permission check
$hasPermission = $authService->hasPermission('create', 'Users');
$hasPermission = $authService->hasPermission('update', 'Products');

// Global permission check (model field is empty)
$hasPermission = $authService->hasPermission('system.admin');
$hasPermission = $authService->hasPermission('metadata.view');
```

**ModelBaseAPIController Integration:**
The `ModelBaseAPIController` can dynamically check permissions based on the route:
- `GET /api/users` → Check permission: action="list", model="Users"  
- `POST /api/products` → Check permission: action="create", model="Products"
- `PUT /api/orders/123` → Check permission: action="update", model="Orders"
- `DELETE /api/customers/456` → Check permission: action="delete", model="Customers"

**Default Permission Examples:**
```sql
-- Model-specific permissions
('create', 'Users', 'Create new users'),
('read', 'Users', 'View user profiles'),
('update', 'Users', 'Update user profiles'),
('delete', 'Users', 'Delete users'),
('list', 'Users', 'List all users'),

-- Global permissions (model = '')
('system.admin', '', 'Full system administration'),
('metadata.view', '', 'View system metadata'),
('api.access', '', 'Basic API access'),
```

#### Step 3: ModelBase Integration
- Update `getCurrentUserId()` to return authenticated user
- Add user context to API operations
- Implement ownership-based restrictions
- Add authentication state management

## 6. API Endpoints Specification

### 6.1 Google OAuth Authentication Endpoints

#### GET /auth/google/url
```json
Response (200):
{
  "success": true,
  "status": 200,
  "data": {
    "authorization_url": "https://accounts.google.com/oauth/authorize?client_id=...",
    "state": "random_state_string"
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

#### POST /auth/google
```json
Request:
{
  "google_token": "ya29.a0AfH6SMC...",
  "state": "random_state_string"
}

Response (200) - Existing User:
{
  "success": true,
  "status": 200,
  "data": {
    "user": {
      "id": 1,
      "email": "user@gmail.com",
      "first_name": "John",
      "last_name": "Doe",
      "role": "user",
      "auth_provider": "google",
      "google_id": "1234567890",
      "profile_picture_url": "https://lh3.googleusercontent.com/..."
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 3600,
    "token_type": "Bearer"
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}

Response (201) - New User Created:
{
  "success": true,
  "status": 201,
  "data": {
    "user": {
      "id": 2,
      "email": "newuser@gmail.com",
      "first_name": "Jane",
      "last_name": "Smith",
      "role": "user",
      "auth_provider": "google",
      "google_id": "0987654321",
      "profile_picture_url": "https://lh3.googleusercontent.com/...",
      "created_from_oauth": true
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 3600,
    "token_type": "Bearer"
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}

Error Response (401):
{
  "success": false,
  "status": 401,
  "error": {
    "message": "Invalid Google token",
    "code": "INVALID_GOOGLE_TOKEN"
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

### 6.2 Traditional Authentication Endpoints

#### POST /auth/login
```json
Request:
{
  "username": "user@example.com",
  "password": "securepassword"
}

Response (200):
{
  "success": true,
  "status": 200,
  "data": {
    "user": {
      "id": 1,
      "username": "user@example.com",
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "role": "user",
      "auth_provider": "local"
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 3600,
    "token_type": "Bearer"
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

### 6.3 Universal Endpoints

#### POST /auth/refresh
```json
Request:
{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}

Response (200):
{
  "success": true,
  "status": 200,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 3600,
    "token_type": "Bearer"
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

#### GET /auth/me
```json
Headers: Authorization: Bearer {access_token}

Response (200):
{
  "success": true,
  "status": 200,
  "data": {
    "id": 1,
    "email": "user@gmail.com",
    "first_name": "John",
    "last_name": "Doe",
    "role": "user",
    "auth_provider": "google",
    "google_id": "1234567890",
    "profile_picture_url": "https://lh3.googleusercontent.com/...",
    "permissions": ["read_users", "update_profile"],
    "last_login_method": "google",
    "last_google_sync": "2025-08-14T10:25:00+00:00"
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

#### POST /auth/logout
```json
Headers: Authorization: Bearer {access_token}

Response (200):
{
  "success": true,
  "status": 200,
  "data": {
    "message": "Successfully logged out"
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

## 7. Security Considerations

### 7.1 Google OAuth Security
- **Token Validation**: Always validate Google tokens server-side
- **Scope Management**: Request minimal necessary scopes from Google
- **State Parameter**: Use CSRF protection with state parameter
- **Token Storage**: Never store Google access tokens long-term
- **Profile Verification**: Verify email addresses are Google-verified

### 7.2 JWT Token Security
- Use strong secret keys for JWT signing (256-bit minimum)
- Implement proper token expiration (15-30 minutes for access tokens)
- Use longer expiration for refresh tokens (7-30 days)
- Store refresh tokens securely in database with hashing
- Implement token blacklisting for logout

### 7.3 Hybrid Authentication Security
- **Password Security**: Use PHP's `password_hash()` with PASSWORD_DEFAULT
- **Rate Limiting**: Implement rate limiting for both OAuth and traditional login
- **Account Linking**: Secure email verification for linking accounts
- **Session Management**: Clear sessions on authentication method change

### 7.4 React Frontend Security
- **Token Storage**: Store JWT access tokens in memory (not localStorage)
- **Automatic Refresh**: Implement automatic token refresh before expiration
- **HTTPS Only**: Enforce HTTPS for all authentication flows
- **CSP Headers**: Implement Content Security Policy headers

## 8. Testing Strategy

### 8.1 Unit Tests
- GoogleOAuthService token validation
- AuthenticationService methods (both OAuth and traditional)
- User model validation and OAuth field handling
- JWT token generation and validation
- User creation from Google profiles

### 8.2 Integration Tests
- Complete Google OAuth flow
- Automatic user creation workflow
- Authentication endpoints (OAuth and traditional)
- Middleware functionality with both auth types
- Protected route access with different token types
- Token refresh flow for both OAuth and JWT tokens

### 8.3 Security Tests
- Invalid Google token handling
- Expired token scenarios (both Google and JWT)
- Authorization bypass attempts
- CSRF protection with state parameter
- SQL injection prevention in user creation
- Account linking security

### 8.4 User Experience Tests
- Seamless Google sign-in flow
- Automatic authentication detection
- Profile synchronization accuracy
- Mixed authentication method handling

## 9. Documentation Requirements

### 9.1 API Documentation
- Google OAuth endpoint documentation
- Traditional authentication endpoint documentation
- Token usage examples for both auth types
- Error response formats
- Rate limiting information

### 9.2 React Integration Guide

#### Google Sign-In Button Integration

**Install Required Dependencies:**
```bash
npm install @google-cloud/local-auth google-auth-library
# or
npm install react-google-login  # Alternative: community package
```

**Method 1: Using Google Identity Services (Recommended)**

**1. Add Google Identity Script to index.html:**
```html
<!-- public/index.html -->
<script src="https://accounts.google.com/gsi/client" async defer></script>
```

**2. Create Google Login Component:**
```jsx
// components/GoogleLoginButton.jsx
import React, { useEffect } from 'react';

const GoogleLoginButton = ({ onSuccess, onError }) => {
  useEffect(() => {
    // Initialize Google Identity Services
    if (window.google) {
      window.google.accounts.id.initialize({
        client_id: process.env.REACT_APP_GOOGLE_CLIENT_ID,
        callback: handleCredentialResponse,
        auto_select: false,
        cancel_on_tap_outside: true,
      });

      // Render the sign-in button
      window.google.accounts.id.renderButton(
        document.getElementById('google-signin-button'),
        {
          theme: 'outline',
          size: 'large',
          type: 'standard',
          shape: 'rectangular',
          text: 'signin_with',
          logo_alignment: 'left',
        }
      );
    }
  }, []);

  const handleCredentialResponse = async (response) => {
    try {
      // Send the credential to your backend
      const result = await fetch('/api/auth/google', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          google_token: response.credential,
        }),
      });

      const data = await result.json();
      
      if (data.success) {
        onSuccess(data);
      } else {
        onError(data.error);
      }
    } catch (error) {
      onError({ message: 'Authentication failed', error });
    }
  };

  return (
    <div>
      <div id="google-signin-button"></div>
    </div>
  );
};

export default GoogleLoginButton;
```

**Method 2: Using react-google-login Package**

```jsx
// components/GoogleLoginAlternative.jsx
import React from 'react';
import { GoogleLogin } from 'react-google-login';

const GoogleLoginAlternative = ({ onSuccess, onError }) => {
  const handleSuccess = async (response) => {
    try {
      const result = await fetch('/api/auth/google', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          google_token: response.tokenId,
        }),
      });

      const data = await result.json();
      onSuccess(data);
    } catch (error) {
      onError(error);
    }
  };

  return (
    <GoogleLogin
      clientId={process.env.REACT_APP_GOOGLE_CLIENT_ID}
      buttonText="Sign in with Google"
      onSuccess={handleSuccess}
      onFailure={onError}
      cookiePolicy={'single_host_origin'}
      responseType="code,token"
    />
  );
};

export default GoogleLoginAlternative;
```

#### Token Storage Best Practices

**Authentication Context with Secure Token Management:**

```jsx
// contexts/AuthContext.jsx
import React, { createContext, useContext, useReducer, useEffect } from 'react';

const AuthContext = createContext();

const authReducer = (state, action) => {
  switch (action.type) {
    case 'LOGIN_SUCCESS':
      return {
        ...state,
        isAuthenticated: true,
        user: action.payload.user,
        accessToken: action.payload.access_token,
        loading: false,
        error: null,
      };
    case 'LOGIN_ERROR':
      return {
        ...state,
        isAuthenticated: false,
        user: null,
        accessToken: null,
        loading: false,
        error: action.payload,
      };
    case 'LOGOUT':
      return {
        ...state,
        isAuthenticated: false,
        user: null,
        accessToken: null,
        loading: false,
        error: null,
      };
    case 'TOKEN_REFRESHED':
      return {
        ...state,
        accessToken: action.payload.access_token,
      };
    case 'SET_LOADING':
      return {
        ...state,
        loading: action.payload,
      };
    default:
      return state;
  }
};

const initialState = {
  isAuthenticated: false,
  user: null,
  accessToken: null,
  loading: true,
  error: null,
};

export const AuthProvider = ({ children }) => {
  const [state, dispatch] = useReducer(authReducer, initialState);

  // Store refresh token in httpOnly cookie (handled by backend)
  // Store access token in memory only (security best practice)
  
  useEffect(() => {
    // Check for existing session on app load
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const response = await fetch('/api/auth/me', {
        credentials: 'include', // Include httpOnly cookies
      });
      
      if (response.ok) {
        const data = await response.json();
        dispatch({
          type: 'LOGIN_SUCCESS',
          payload: {
            user: data.data,
            access_token: data.access_token,
          },
        });
      } else {
        dispatch({ type: 'SET_LOADING', payload: false });
      }
    } catch (error) {
      dispatch({ type: 'SET_LOADING', payload: false });
    }
  };

  const login = async (authData) => {
    try {
      dispatch({ type: 'SET_LOADING', payload: true });
      
      // Store access token in memory
      dispatch({
        type: 'LOGIN_SUCCESS',
        payload: authData.data,
      });
      
      // Refresh token is automatically stored in httpOnly cookie by backend
    } catch (error) {
      dispatch({
        type: 'LOGIN_ERROR',
        payload: error.message,
      });
    }
  };

  const logout = async () => {
    try {
      await fetch('/api/auth/logout', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Authorization': `Bearer ${state.accessToken}`,
        },
      });
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      dispatch({ type: 'LOGOUT' });
    }
  };

  return (
    <AuthContext.Provider
      value={{
        ...state,
        login,
        logout,
        dispatch,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
```

#### Axios Interceptor for Automatic Authentication

```jsx
// utils/apiClient.js
import axios from 'axios';
import { useAuth } from '../contexts/AuthContext';

const apiClient = axios.create({
  baseURL: process.env.REACT_APP_API_BASE_URL || '/api',
  withCredentials: true, // Include cookies for refresh token
});

// Request interceptor to add access token
apiClient.interceptors.request.use(
  (config) => {
    const token = getAccessToken(); // Get from auth context
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor for automatic token refresh
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;

      try {
        // Attempt to refresh token
        const refreshResponse = await axios.post('/api/auth/refresh', {}, {
          withCredentials: true,
        });

        const newAccessToken = refreshResponse.data.data.access_token;
        
        // Update token in auth context
        updateAccessToken(newAccessToken);
        
        // Retry original request with new token
        originalRequest.headers.Authorization = `Bearer ${newAccessToken}`;
        return apiClient(originalRequest);
      } catch (refreshError) {
        // Refresh failed, redirect to login
        logout();
        window.location.href = '/login';
        return Promise.reject(refreshError);
      }
    }

    return Promise.reject(error);
  }
);

export default apiClient;
```

#### Authentication Hook Examples

```jsx
// hooks/useAuthenticatedApi.js
import { useAuth } from '../contexts/AuthContext';
import apiClient from '../utils/apiClient';

export const useAuthenticatedApi = () => {
  const { accessToken, logout } = useAuth();

  const authenticatedFetch = async (url, options = {}) => {
    try {
      const response = await apiClient(url, {
        ...options,
        headers: {
          ...options.headers,
          Authorization: `Bearer ${accessToken}`,
        },
      });
      return response.data;
    } catch (error) {
      if (error.response?.status === 401) {
        logout();
      }
      throw error;
    }
  };

  return { authenticatedFetch };
};
```

#### Complete Login Page Example

```jsx
// pages/Login.jsx
import React, { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import GoogleLoginButton from '../components/GoogleLoginButton';

const Login = () => {
  const { login, loading, error } = useAuth();
  const [loginMethod, setLoginMethod] = useState('google');
  const [credentials, setCredentials] = useState({
    username: '',
    password: '',
  });

  const handleGoogleSuccess = async (response) => {
    await login(response);
  };

  const handleGoogleError = (error) => {
    console.error('Google login failed:', error);
  };

  const handleTraditionalLogin = async (e) => {
    e.preventDefault();
    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(credentials),
        credentials: 'include',
      });

      const data = await response.json();
      if (data.success) {
        await login(data);
      }
    } catch (error) {
      console.error('Traditional login failed:', error);
    }
  };

  if (loading) {
    return <div>Loading...</div>;
  }

  return (
    <div className="login-container">
      <h2>Sign In</h2>
      
      {error && (
        <div className="error-message">
          {error}
        </div>
      )}

      <div className="login-methods">
        <div className="google-login">
          <h3>Sign in with Google</h3>
          <GoogleLoginButton
            onSuccess={handleGoogleSuccess}
            onError={handleGoogleError}
          />
        </div>

        <div className="divider">OR</div>

        <div className="traditional-login">
          <h3>Sign in with Email</h3>
          <form onSubmit={handleTraditionalLogin}>
            <input
              type="email"
              placeholder="Email"
              value={credentials.username}
              onChange={(e) =>
                setCredentials({ ...credentials, username: e.target.value })
              }
              required
            />
            <input
              type="password"
              placeholder="Password"
              value={credentials.password}
              onChange={(e) =>
                setCredentials({ ...credentials, password: e.target.value })
              }
              required
            />
            <button type="submit">Sign In</button>
          </form>
        </div>
      </div>
    </div>
  );
};

export default Login;
```

#### Environment Configuration

**Environment Variables (.env):**
```bash
# React App Environment Variables
REACT_APP_API_BASE_URL=http://localhost:8000/api
REACT_APP_GOOGLE_CLIENT_ID=your_google_client_id_here

# Production
REACT_APP_API_BASE_URL=https://api.yourdomain.com
REACT_APP_GOOGLE_CLIENT_ID=your_production_google_client_id
```

#### Logout Handling for Different Authentication Methods

```jsx
// components/LogoutButton.jsx
import React from 'react';
import { useAuth } from '../contexts/AuthContext';

const LogoutButton = () => {
  const { logout, user } = useAuth();

  const handleLogout = async () => {
    try {
      // Call backend logout endpoint
      await logout();
      
      // If user was logged in via Google, also sign out from Google
      if (user?.auth_provider === 'google' && window.google) {
        window.google.accounts.id.disableAutoSelect();
      }
      
      // Redirect to login page
      window.location.href = '/login';
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  return (
    <button onClick={handleLogout} className="logout-button">
      Sign Out
    </button>
  );
};

export default LogoutButton;
```

This comprehensive React integration provides secure token management, automatic authentication handling, and support for both Google OAuth and traditional authentication methods.

### 9.3 Google OAuth Setup Guide

#### Complete Google Cloud Console Configuration

**Prerequisites:**
- Google account with admin privileges
- Domain name for production deployment (optional for development)
- Basic understanding of OAuth 2.0 flow

#### Step-by-Step Configuration Process

**1. Project Creation and Setup**

*What you'll see:* Google Cloud Console dashboard with project selector at top
*Action required:* Create new project or select existing one

**Project Configuration Details:**
```
Project Name: gravitycar-auth-[environment]
Project ID: Auto-generated (e.g., gravitycar-auth-123456)
Billing Account: Link if using paid features
Organization: Your organization or "No organization"
```

**2. API Enablement Process**

*What you'll see:* API Library with search functionality
*APIs to enable:*
- Google+ API (for basic profile access)
- People API (for detailed profile information)
- Identity and Access Management (IAM) API (for advanced features)

**3. OAuth Consent Screen Configuration**

*Critical Settings:*
```
User Type: External (for public apps)
App Name: Your Application Name
User Support Email: your-email@domain.com
Developer Contact: your-email@domain.com
Authorized Domains: yourdomain.com, localhost (for dev)
```

*Scopes Configuration:*
```
Required Scopes:
- openid
- email
- profile
- https://www.googleapis.com/auth/userinfo.email
- https://www.googleapis.com/auth/userinfo.profile
```

**4. OAuth 2.0 Client Creation**

*Application Type:* Web application
*Client Configuration:*
```
Authorized JavaScript Origins:
- http://localhost:3000 (development)
- http://localhost:8080 (alternative dev port)
- https://yourdomain.com (production)
- https://www.yourdomain.com (production with www)

Authorized Redirect URIs:
- http://localhost:3000/auth/google/callback
- https://yourdomain.com/auth/google/callback
- https://api.yourdomain.com/auth/google/callback (if API on subdomain)
```

#### Environment Variable Configuration

**Development Environment (.env.local):**
```bash
# Google OAuth - Development
GOOGLE_CLIENT_ID=123456789-abcdefghijklmnop.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-abcdefghijklmnopqrstuvwxyz
GOOGLE_REDIRECT_URI=http://localhost:3000/auth/google/callback

# JWT Configuration
JWT_SECRET_KEY=base64_encoded_secret_key_here
JWT_REFRESH_SECRET=base64_encoded_refresh_secret_here
JWT_ACCESS_TOKEN_LIFETIME=3600
JWT_REFRESH_TOKEN_LIFETIME=2592000

# OAuth Behavior
OAUTH_DEFAULT_ROLE=user
OAUTH_AUTO_CREATE_USERS=true
OAUTH_SYNC_PROFILE_ON_LOGIN=true
OAUTH_REQUIRE_VERIFIED_EMAIL=true
```

**Production Environment (.env.production):**
```bash
# Google OAuth - Production
GOOGLE_CLIENT_ID=production_client_id_here
GOOGLE_CLIENT_SECRET=production_client_secret_here
GOOGLE_REDIRECT_URI=https://yourdomain.com/auth/google/callback

# Use different JWT secrets for production
JWT_SECRET_KEY=production_jwt_secret_here
JWT_REFRESH_SECRET=production_refresh_secret_here
```

#### Security Key Generation

**Generate Secure JWT Keys:**
```bash
# Method 1: Using OpenSSL
openssl rand -base64 32

# Method 2: Using Node.js
node -e "console.log(require('crypto').randomBytes(32).toString('base64'))"

# Method 3: Using PHP
php -r "echo base64_encode(random_bytes(32));"
```

#### Validation and Testing

**OAuth Flow Testing:**
1. **Google OAuth Playground**: https://developers.google.com/oauthplayground
   - Enter your Client ID in settings
   - Test authorization flow with your scopes
   - Verify token exchange works

2. **Manual Testing Process:**
   ```
   1. Navigate to authorization URL
   2. Complete Google sign-in
   3. Verify redirect to your callback URL
   4. Check that authorization code is present
   5. Exchange code for access token
   6. Fetch user profile with access token
   ```

#### Common Configuration Issues

**Issue: "redirect_uri_mismatch"**
*Cause:* Redirect URI in request doesn't match configured URIs
*Solution:* 
- Verify exact match including protocol (http/https)
- Check for trailing slashes
- Ensure port numbers match

**Issue: "invalid_client"**
*Cause:* Incorrect Client ID or Secret
*Solution:*
- Re-copy credentials from Google Cloud Console
- Check for extra spaces or characters
- Verify environment variables are loading correctly

**Issue: "access_denied"**
*Cause:* User declined authorization or app not verified
*Solution:*
- For development: Add test users in OAuth consent screen
- For production: Submit app for verification if using sensitive scopes

**Issue: "unauthorized_client"**
*Cause:* OAuth client configuration problems
*Solution:*
- Verify application type is "Web application"
- Check that all required APIs are enabled
- Ensure OAuth consent screen is properly configured

#### Production Deployment Checklist

**Pre-Production:**
- [ ] Domain ownership verified in Google Search Console
- [ ] Production redirect URIs configured
- [ ] SSL certificate installed and working
- [ ] Different OAuth client for production environment
- [ ] App verification completed (if required)
- [ ] Billing account configured for API quotas

**Security Checklist:**
- [ ] Client secrets stored securely (not in code)
- [ ] Environment-specific configurations
- [ ] API key restrictions configured
- [ ] Monitoring and alerting set up
- [ ] Backup of OAuth credentials stored securely

**Monitoring Setup:**
- [ ] Google Cloud Console API usage monitoring
- [ ] Error rate monitoring for OAuth endpoints
- [ ] User conversion tracking (successful vs failed logins)
- [ ] Security incident monitoring

#### Scope Selection Recommendations

**Minimal Required Scopes:**
```
openid              - OpenID Connect identifier
email               - User's email address
profile             - Basic profile information (name, picture)
```

**Optional Extended Scopes:**
```
https://www.googleapis.com/auth/user.birthday.read    - Birthday info
https://www.googleapis.com/auth/user.phonenumbers.read - Phone numbers
https://www.googleapis.com/auth/contacts.readonly      - Contact list access
```

**Scope Request Best Practices:**
- Request minimal scopes initially
- Use incremental authorization for additional scopes
- Clearly explain why each scope is needed to users
- Handle scope denial gracefully

This comprehensive setup ensures secure, production-ready Google OAuth integration with your Gravitycar Framework application.

### 9.4 User Management Guide
- Automatic user provisioning from Google
- Profile synchronization workflows
- Account linking strategies
- Role assignment for OAuth users

## 10. Success Criteria

- [ ] Users can authenticate with Google OAuth 2.0 seamlessly
- [ ] Automatic user account creation from Google profiles works
- [ ] Users already logged into Google are automatically authenticated
- [ ] Traditional username/password authentication still works
- [ ] JWT tokens are generated and validated correctly for both auth types
- [ ] Protected endpoints require valid authentication (Google or traditional)
- [ ] Token refresh works seamlessly for both OAuth and JWT tokens
- [ ] Role-based access control functions with OAuth users
- [ ] Profile synchronization keeps Google data up-to-date
- [ ] Security best practices are implemented for OAuth flow
- [ ] React integration examples work with Google Sign-In
- [ ] All tests pass with >90% coverage
- [ ] Google OAuth setup documentation is complete and accurate

## 11. Dependencies

### 11.1 External Libraries
- `google/auth` - Official Google Auth Library for PHP
- `league/oauth2-google` - OAuth 2.0 Google provider
- `firebase/php-jwt` - JWT handling
- Google APIs Client Library (optional, for extended Google services)

### 11.2 Framework Integration
- ModelBase for enhanced User model with OAuth fields
- RestApiHandler for endpoint routing and middleware
- Database connectivity for user and token storage
- Exception handling system for OAuth-specific errors
- ServiceLocator for dependency injection of OAuth services

### 11.3 External Services
- Google Cloud Console project with OAuth 2.0 configured
- Google APIs access for user profile information
- HTTPS endpoints for secure OAuth flows

## 12. Risks and Mitigations

### 12.1 Google OAuth Risks
- **Risk**: Google API changes or service disruption
- **Mitigation**: Fallback to traditional authentication, comprehensive error handling

- **Risk**: OAuth token expiration handling
- **Mitigation**: Implement refresh token workflow, graceful degradation

- **Risk**: Google account linking conflicts
- **Mitigation**: Email verification, clear account linking policies

### 12.2 Security Risks
- **Risk**: JWT secret key exposure
- **Mitigation**: Environment variables, key rotation, separate keys per environment

- **Risk**: OAuth token hijacking
- **Mitigation**: HTTPS enforcement, short token expiration, state parameter validation

- **Risk**: User impersonation via Google token
- **Mitigation**: Server-side Google token validation, verified email requirement

### 12.3 Performance Risks
- **Risk**: Google API latency affecting authentication
- **Mitigation**: Caching strategies, timeout handling, async processing

- **Risk**: Token validation overhead
- **Mitigation**: Token caching, efficient algorithms, connection pooling

### 12.4 Integration Risks
- **Risk**: Breaking existing API functionality
- **Mitigation**: Backward compatibility, gradual rollout, comprehensive testing

- **Risk**: React frontend integration complexity
- **Mitigation**: Clear documentation, example implementations, step-by-step guides

## 13. Estimated Timeline

**Total Time: 3 weeks**

- **Week 1**: Google OAuth setup, dependencies, core services, database schema
- **Week 2**: Authentication services, API endpoints, user management with OAuth
- **Week 3**: Middleware integration, authorization, testing, documentation

## 14. Configuration Requirements

### 14.1 Environment Variables
```bash
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://yourapp.com/auth/google/callback

# JWT Configuration
JWT_SECRET_KEY=your_strong_secret_key
JWT_REFRESH_SECRET=your_refresh_secret_key
JWT_ACCESS_TOKEN_LIFETIME=3600
JWT_REFRESH_TOKEN_LIFETIME=2592000

# OAuth Settings
OAUTH_DEFAULT_ROLE=user
OAUTH_AUTO_CREATE_USERS=true
OAUTH_SYNC_PROFILE_ON_LOGIN=true
```

### 14.2 Google Cloud Console Setup
1. Create or select a Google Cloud project
2. Enable Google+ API and Google People API
3. Create OAuth 2.0 credentials (Web application)
4. Configure authorized redirect URIs
5. Set up consent screen with required scopes

## 15. Implementation Notes

### 15.1 Gravitycar Framework Integration Approach

This implementation leverages Gravitycar's metadata-driven architecture:

#### Authentication Models Structure

**Models (Independent Entities):**
1. **JwtRefreshTokens** (`src/Models/jwt_refresh_tokens/`)
   - Extends ModelBase
   - Manages JWT refresh tokens
   - Foreign key relationship to Users model
   - Fields: token_hash, expires_at, user_id, created_at

2. **GoogleOauthTokens** (`src/Models/google_oauth_tokens/`)
   - Extends ModelBase  
   - Manages Google OAuth token storage
   - Foreign key relationship to Users model
   - Fields: access_token_hash, refresh_token_hash, token_expires_at, scope, user_id

3. **Roles** (`src/Models/roles/`)
   - Extends ModelBase
   - Defines user roles (admin, manager, user, etc.)
   - Fields: name, description, is_oauth_default

4. **Permissions** (`src/Models/permissions/`)
   - Extends ModelBase
   - Defines individual permissions  
   - Fields: name, description

**Relationships (Many-to-Many Associations):**
1. **roles_permissions** (`src/Relationships/roles_permissions/`)
   - ManyToManyRelationship between Roles and Permissions
   - Junction table: role_permissions
   - No additional fields

2. **users_permissions** (`src/Relationships/users_permissions/`)
   - ManyToManyRelationship between Users and Permissions
   - Junction table: user_permissions  
   - No additional fields

**One-to-Many Relationships (via Foreign Keys):**
- Users -> JwtRefreshTokens (one user has many refresh tokens)
- Users -> GoogleOauthTokens (one user has many OAuth token records)

#### Users Table Enhancement via Metadata
- **Metadata File**: `src/Models/users/users_metadata.php` updated with OAuth fields
- **Field Types Used**:
  - `TextField` for `google_id`, `last_login_method`
  - `EmailField` for `google_email`
  - `BooleanField` for `google_verified_email`, `is_active`
  - `ImageField` for `profile_picture_url` with width/height metadata
  - `EnumField` for `auth_provider` with options
  - `DateTimeField` for `email_verified_at`, `last_google_sync`
- **Schema Generation**: Automatic via SchemaGenerator based on metadata
- **Validation**: Built-in via field-level validation rules

#### Key Metadata Changes Made
1. **Optional Authentication**: `username` and `password` fields made optional for OAuth users
2. **Google OAuth Fields**: Added fields for Google ID, email, verification status
3. **Authentication Tracking**: Added provider and method tracking fields
4. **Profile Integration**: Added profile picture URL and sync timestamp
5. **User Status**: Added active status field for account management

#### Database Column Mapping
- `TextField` → VARCHAR(255)
- `ImageField` → VARCHAR(500) with metadata for width/height
- `BooleanField` → BOOLEAN
- `DateTimeField` → DATETIME
- `EnumField` → VARCHAR with constraint options

This approach ensures all changes are metadata-driven and automatically generate the correct database schema while maintaining the framework's extensibility principles.

## 8. Model-Aware Permission System Summary

### 8.1 Key Benefits

**Router-Based Authorization Architecture:**
- ✅ **Centralized Security**: All authorization happens in Router.route() before controllers execute
- ✅ **Automatic Coverage**: Every API route automatically gets permission checking
- ✅ **Zero Controller Changes**: Existing ModelBaseAPIController requires no modifications
- ✅ **Performance Optimized**: Single permission check per request at routing level
- ✅ **Cannot Be Bypassed**: Impossible to accidentally skip authorization

**Enhanced Permission Method Signature:**
```php
// Old: hasPermission($user, $action, $model) - Required explicit user
// New: hasPermission($action, $model, $user = null) - Auto-resolves current user
```

**Automatic User Resolution:**
- ServiceLocator::getCurrentUser() extracts user from JWT token in request headers
- Router calls hasPermission() without user parameter → automatic current user lookup
- Supports manual user override for testing: hasPermission('create', 'Users', $specificUser)

**Dynamic ModelBaseAPIController Integration:**
- Automatic permission checking for all CRUD operations
- No need to manually define permissions for each model
- Seamless scaling as new models are added to the system
- Consistent security enforcement across all API endpoints

**Granular Permission Control:**
- Model-specific permissions: `('create', 'Users')`, `('update', 'Products')`
- Global permissions: `('system.admin', '')`, `('metadata.view', '')`
- Action-based permissions: create, read, update, delete, list, restore

### 8.1a Practical Example: Movies Model

**Scenario**: Developer creates new Movies model, regular user tries to create a movie

**Step 1: Model Creation**
```php
// Developer creates Movies model with metadata
// ModelBaseAPIController automatically handles: POST /api/movies
```

**Step 2: User Attempts Access**
```php
// Regular user (role: 'user') attempts: POST /api/movies
// ModelBaseAPIController calls: hasPermission($user, 'create', 'Movies')
// Permission lookup: WHERE action='create' AND model='Movies'
// Database result: No matching permission found
// Authorization result: FALSE - Access DENIED with 403 Forbidden
```

**Step 3: Permission Setup Required**
```sql
-- Admin must explicitly add Movies permissions:
INSERT INTO permissions (action, model, description) VALUES
('create', 'Movies', 'Create new movie records'),
('read', 'Movies', 'View movie details'),
('update', 'Movies', 'Update movie information'),
('delete', 'Movies', 'Delete movie records'),
('list', 'Movies', 'List and search movies');

-- Then assign to roles:
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'user' AND p.action = 'create' AND p.model = 'Movies';
```

**Step 4: Access Granted**
```php
// Same user attempts: POST /api/movies
// Permission lookup: WHERE action='create' AND model='Movies'
// Database result: Permission found and assigned to user's role
// Authorization result: TRUE - Access GRANTED
```

**Security Implications:**
- ✅ **Secure by Default**: New models are protected until explicitly configured
- ✅ **Explicit Setup**: Forces deliberate permission decisions for each model
- ✅ **No Accidents**: Cannot accidentally expose sensitive new models
- ⚠️ **Setup Required**: Requires admin action for each new model
- Flexible role-permission assignments

**Framework Integration:**
- Works with existing `ModelBaseAPIController` wildcard routing
- Leverages framework's metadata-driven architecture
- Supports both direct user permissions and role-based permissions
- Compatible with OAuth and traditional authentication methods

### 8.2 Permission Structure Evolution

**Traditional Approach (Not Suitable):**
```sql
-- Static, model-agnostic permissions
('user.create', 'Create users'),
('product.update', 'Update products'),
('order.delete', 'Delete orders')
-- Problem: Requires manual definition for every model
```

**Model-Aware Approach (Implemented):**
```sql
-- Dynamic, model-aware permissions
('create', 'Users', 'Create new user records'),
('update', 'Products', 'Update product records'),
('delete', 'Orders', 'Delete order records'),
('system.admin', '', 'Global system administration')
-- Benefit: Scales automatically with any model
```

### 8.3 Integration Points

1. **ModelBaseAPIController**: Automatic permission checking in all CRUD methods
2. **AuthorizationService**: Centralized permission logic with model-awareness
3. **JWT Middleware**: User authentication and permission context
4. **Metadata System**: Role and permission definitions through framework metadata
5. **Database Schema**: Optimized with proper indexes for performance

This model-aware permission system provides the flexibility and granularity needed for a comprehensive authentication and authorization framework while maintaining the simplicity and scalability that the Gravitycar Framework is designed for.
