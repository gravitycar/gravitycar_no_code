# PRD: Dynamic Role-Based Navigation System for Gravitycar Framework

## 1. Product overview

### 1.1 Document title and version

* PRD: Dynamic Role-Based Navigation System for Gravitycar Framework
* Version: 1.0

### 1.2 Product summary

The Dynamic Role-Based Navigation System will replace the current hardcoded navigation in the Gravitycar Framework with an intelligent, permission-aware navigation system. This system will automatically discover available models and features, filter them based on user permissions, and present a clean hierarchical navigation interface that adapts to the user's role and available actions.

The system consists of two main components: a Backend Navigation Service that provides role-appropriate navigation data via a cached JSON API, and a Frontend Navigation Component that renders a vertical sidebar navigation with model links and create sub-options. This solution eliminates manual navigation maintenance while ensuring users only see links they have permission to access.

## 2. Goals

### 2.1 Business goals

* Eliminate manual navigation maintenance when adding new models or features to the framework
* Improve security by ensuring users never see navigation items they cannot access
* Reduce development overhead by auto-discovering navigation items from existing metadata
* Provide consistent user experience across different user roles and permission levels
* Enable rapid feature deployment without requiring navigation updates

### 2.2 User goals

* Access only the features and models they have permission to use
* Easily discover available create functionality for each model
* Navigate efficiently between model list views and creation forms
* Understand their access level through clear visual indicators
* Experience fast navigation loading through intelligent caching

### 2.3 Non-goals

* This system will not modify the existing RBAC permission system
* Will not replace individual page authorization - only navigation visibility
* Will not provide navigation for non-web interfaces (API-only usage)
* Will not handle complex nested hierarchies beyond model → actions structure

## 3. User personas

### 3.1 Key user types

* Framework administrators managing system configuration
* Content managers working with multiple models daily
* Regular users with limited permissions accessing specific features
* Developers adding new models and features to the framework

### 3.2 Basic persona details

* **System Administrator**: Full access to all models and actions, needs comprehensive navigation to manage the entire system efficiently
* **Content Manager**: Access to most models with create/read/update permissions, requires quick access to frequently used features
* **Regular User**: Limited access to specific models, typically read-only with some create permissions, needs simple focused navigation
* **Developer**: Technical user who adds new models and needs navigation to auto-update without manual configuration

### 3.3 Role-based access

* **Admin Role**: Full navigation access to all discovered models and custom pages with all available actions
* **Manager Role**: Access to business-relevant models with standard CRUD operations, excluded from system administration features
* **User Role**: Access to content consumption and creation features, limited administrative capabilities
* **Guest Role**: Minimal navigation showing only public or registration-related features

## 4. Functional requirements

* **Backend Navigation Service** (Priority: High)
  * Discover available models from MetadataEngine automatically
  * Query user permissions from RBAC system to filter navigation items based on 'list' and 'create' actions
  * Generate cached navigation data structure optimized for frontend consumption
  * Provide separate REST API endpoints for each role using wildcard pattern `/navigation/?`
  * Integrate with setup.php to rebuild navigation cache when metadata changes

* **Frontend Navigation Component** (Priority: High)
  * Replace existing hardcoded navigation with dynamic permission-based system
  * Render vertical sidebar navigation with model names linking to list views
  * Display create sub-option under each model when user has create permission
  * Navigate between model list views and creation forms seamlessly
  * Cache navigation data client-side for improved performance

* **Permission Integration** (Priority: High)
  * Integrate with existing AuthorizationService to check user permissions for 'list' and 'create' actions
  * Filter navigation items based on roles_permissions relationship data
  * Support both model-based permissions and custom page access control
  * Handle permission checking for discovered routes and endpoints

* **Custom Page Support** (Priority: Medium)
  * Support non-model pages like Dashboard, Trivia, and administrative interfaces
  * Configure custom pages through dedicated navigation configuration file (src/Navigation/navigation_config.php)
  * Map custom pages to appropriate user roles and permission requirements
  * Provide extensible system for adding new custom navigation items through configuration
  * Follow framework Config class pattern for configuration management

* **Cache Management** (Priority: Medium)
  * Build role-specific navigation caches during setup.php execution alongside existing caches
  * Create separate cache files for each role (admin, manager, user, guest) based on role permissions
  * Load appropriate cache file based on current user's 'user_type' field
  * Implement cache invalidation when role permissions or metadata change
  * Provide cache warming and preloading for improved performance

## 5. User experience

### 5.1 Entry points & first-time user flow

* User logs into the system and is directed to dashboard with role-appropriate navigation visible
* Navigation sidebar automatically populated with available models and custom pages
* Clear visual hierarchy shows models and their available actions
* First-time users see tooltips or indicators explaining navigation functionality

### 5.2 Core experience

* **Model Discovery**: Navigation automatically shows all models user has access to without manual configuration
  * How this ensures a positive experience: Users immediately see all available features without hunting through menus or guessing what they can access

* **Action Visibility**: Create sub-option appears under each model when user has create permission
  * How this ensures a positive experience: Users never encounter permission denied errors because they only see the create option when they can perform it

* **Responsive Updates**: Navigation updates automatically when user permissions change or new models are added
  * How this ensures a positive experience: Consistent interface that grows with the system without requiring user retraining

* **Performance Optimization**: Cached navigation data loads instantly with minimal server requests
  * How this ensures a positive experience: Fast, responsive interface that doesn't slow down user workflows

### 5.3 Advanced features & edge cases

* Graceful handling of permission changes during active sessions
* Support for deeply nested navigation structures if needed in future
* Offline-capable navigation caching for improved reliability
* Integration with search functionality for large model collections
* Support for custom icons and visual indicators for different model types

### 5.4 UI/UX highlights

* Clean vertical sidebar design that complements existing Gravitycar UI
* Click-to-expand model sections with create sub-options when permitted
* Single menu expansion - clicking a model collapses any previously open menu
* Clear visual distinction between models and custom pages
* Consistent styling with existing framework design system
* Responsive design that works on desktop and tablet interfaces

## 6. Narrative

When users log into the Gravitycar Framework, they are immediately presented with a clean, intuitive navigation sidebar that shows exactly what they can access based on their role and permissions. Instead of static links that may lead to permission errors, the dynamic navigation system intelligently discovers all available models and features, checks the user's permissions against the RBAC system, and presents only relevant options. Users can click on model names to navigate to list views, and see a create sub-option when they have create permissions, making their capabilities immediately clear. As the system grows with new models and features, the navigation automatically updates without requiring manual maintenance, ensuring users always have access to new functionality they're authorized to use.

## 7. Success metrics

### 7.1 User-centric metrics

* Reduction in navigation-related permission errors (target: 90% decrease)
* Improved task completion times for common operations (target: 25% faster)
* Increased feature discovery rates as users find relevant functionality easier
* Higher user satisfaction scores related to system usability and clarity

### 7.2 Business metrics

* Reduced development time for adding new models (target: 50% reduction in navigation maintenance)
* Decreased support tickets related to "can't find feature" or "permission denied" issues
* Faster onboarding time for new users understanding system capabilities
* Improved security posture through elimination of unauthorized navigation exposure

### 7.3 Technical metrics

* Navigation load times under 200ms for cached responses
* 99.9% cache hit rate for navigation requests after initial load
* Zero failed deployments due to navigation configuration errors
* Cache rebuild time under 5 seconds during setup.php execution

## 8. Technical considerations

### 8.1 Integration points

* MetadataEngine.getAvailableModels() for automatic model discovery
* AuthorizationService.hasPermissionForRoute() for permission filtering
* Existing RBAC roles_permissions relationship for authorization data
* APIRouteRegistry for endpoint discovery and route validation
* NavigationAPIController following MetadataAPIController wildcard pattern
* NavigationConfig class for managing custom page configuration following framework Config class pattern
* Current React frontend navigation component replacement

### 8.2 Data storage & privacy

* Role-specific navigation cache files stored as cache/navigation_cache_{role}.php (e.g., navigation_cache_admin.php)
* Custom page configuration stored in src/Navigation/navigation_config.php following framework patterns
* Cache file selection based on user's 'user_type' field mapping to role
* User-specific navigation preferences stored in browser local storage
* No sensitive permission data exposed to frontend beyond user's authorized items
* Cache invalidation tied to role permission changes to prevent stale data

### 8.3 Scalability & performance

* Role-based navigation cache shared across users with the same role
* Efficient cache loading by selecting appropriate role cache file based on user_type
* Frontend caching with configurable TTL and manual refresh capability
* Lazy loading of model metadata for large installations
* Efficient permission queries eliminated through pre-computed role caches

### 8.4 Potential challenges

* Role permission matrix calculations during cache generation for each role
* Cache synchronization when role permissions change across multiple cache files
* User role changes requiring immediate cache file switching
* Frontend state management for accordion-style menu expansion (single menu open)
* Backward compatibility with existing hardcoded navigation during transition

## 9. Milestones & sequencing

### 9.1 Project estimate

* Medium: 3-4 weeks development time

### 9.2 Team size & composition

* 1 Full-stack developer: Backend service and frontend component development
* 1 UI/UX consultant: Navigation design and user experience optimization

### 9.3 Suggested phases

* **Phase 1**: Backend Navigation Service Implementation (1.5 weeks)
  * NavigationBuilder service for discovering models and permissions
  * NavigationConfig class for managing custom page configuration
  * NavigationAPIController with wildcard route pattern following MetadataAPIController example
  * Role-specific cache generation and endpoint handling
  * Integration with setup.php for cache management
  * Unit tests for navigation generation logic

* **Phase 2**: Frontend Navigation Component Development (1 week)
  * React NavigationSidebar component replacement
  * API integration for dynamic navigation loading
  * Click-to-expand menu system with single menu open behavior
  * CSS styling consistent with existing framework design

* **Phase 3**: Integration and Testing (0.5 weeks)
  * End-to-end testing of permission-based navigation
  * Performance testing of cache system
  * User acceptance testing with different role scenarios
  * Documentation and deployment preparation

## 10. User stories

### 10.1. Backend navigation service development

* **ID**: NAV-001
* **Description**: As a system architect, I want a backend service that automatically discovers available models and generates role-specific navigation data so that the navigation stays current as the system evolves.
* **Acceptance criteria**:
  * NavigationBuilder service uses MetadataEngine.getAvailableModels() to discover models
  * NavigationConfig class loads custom page definitions from src/Navigation/navigation_config.php
  * Service generates separate cache files for each role (admin, manager, user, guest)
  * Role-specific navigation data cached in cache/navigation_cache_{role}.php files
  * NavigationAPIController registers wildcard route `/navigation/?` with parameterNames ['roleName']
  * API endpoints like `/navigation/admin`, `/navigation/user` return role-specific JSON structure
  * Cache rebuilds automatically during setup.php execution for all roles

### 10.2. Permission-filtered navigation generation

* **ID**: NAV-002
* **Description**: As a system user, I want the navigation to show only items appropriate for my role so that I never encounter unexpected authorization errors.
* **Acceptance criteria**:
  * Navigation service loads cache file based on user's 'user_type' field
  * Users only see models where their role has at least 'list' permission
  * Create sub-option shows only when user's role has 'create' permission for that model
  * Custom pages filtered based on role permission requirements
  * Role changes reflect in navigation immediately by loading different cache file

### 10.3. Frontend navigation component replacement

* **ID**: NAV-003
* **Description**: As a framework user, I want a clean vertical navigation sidebar that shows available models and create options so that I can efficiently navigate the system.
* **Acceptance criteria**:
  * New NavigationSidebar component replaces hardcoded navigation in Layout.tsx
  * Vertical layout with model names as clickable links to list views
  * Clicking model name navigates to list view for that model
  * Create sub-option appears under model name when user has create permission
  * Click interaction to expand/collapse create sub-option
  * Only one model menu can be expanded at a time (accordion-style behavior)
  * Consistent styling with existing Gravitycar framework design
  * Component loads navigation data from role-specific endpoint (e.g., `/navigation/admin`)

### 10.4. Expandable action menus

* **ID**: NAV-004
* **Description**: As a content manager, I want to see a create option for each model when I have create permission so that I can quickly access creation functions.
* **Acceptance criteria**:
  * Create sub-option appears under model name when user has create permission
  * Click model name to expand/collapse the create sub-option
  * Expanding one model's menu automatically collapses any previously open menu
  * Create option links to model creation form when clicked
  * Sub-option only displayed when user has create permission for that model
  * Visual styling clearly indicates the create option as a sub-item
  * Create link opens the appropriate model creation form
  * Current page/section highlighted in navigation

### 10.5. Custom page navigation support

* **ID**: NAV-005
* **Description**: As a system administrator, I want custom pages like Dashboard and Trivia to appear in navigation with appropriate permissions so that all system features are discoverable.
* **Acceptance criteria**:
  * Navigation supports both auto-discovered models and configured custom pages
  * Custom pages defined in src/Navigation/navigation_config.php following framework configuration patterns
  * NavigationConfig class provides methods for reading custom page definitions
  * Dashboard, Trivia, and administrative pages appear with proper role filtering
  * Configuration system extensible for adding new custom navigation items
  * Custom page configuration follows same PHP array return pattern as other framework configs

### 10.6. Navigation cache management

* **ID**: NAV-006
* **Description**: As a system administrator, I want role-specific navigation data cached for performance while staying current with role permission changes so that the system remains responsive and accurate.
* **Acceptance criteria**:
  * Role-specific navigation caches built during setup.php execution alongside metadata cache
  * Separate cache files created for each role (admin, manager, user, guest)
  * Cache invalidation rebuilds all role cache files when permissions or models change
  * Frontend loads appropriate cache based on user's 'user_type' field
  * Manual cache refresh capability rebuilds all role-specific cache files

### 10.7. Performance optimization

* **ID**: NAV-007
* **Description**: As a framework user, I want navigation to load quickly and respond immediately so that system navigation doesn't slow down my workflow.
* **Acceptance criteria**:
  * Role-specific navigation API responses under 200ms for cached data
  * Frontend navigation component renders within 100ms of data receipt
  * Local storage caching reduces API calls during single session
  * Direct cache file loading eliminates permission calculation overhead
  * Graceful degradation when navigation service temporarily unavailable

### 10.8. Security and authorization integration

* **ID**: NAV-008
* **Description**: As a security administrator, I want navigation filtering integrated with the existing RBAC system so that security remains consistent across the application.
* **Acceptance criteria**:
  * Navigation uses same AuthorizationService.hasPermissionForRoute() as other components
  * No sensitive authorization data exposed to frontend beyond user's permissions
  * Permission enumeration attacks prevented by not revealing unauthorized options
  * Session permission changes reflect in navigation without requiring re-login
  * Audit logging for navigation access pattern analysis