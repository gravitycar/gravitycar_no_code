# Dynamic Role-Based Navigation System Implementation Plan

## Overview

This document provides a comprehensive implementation plan for building the Dynamic Role-Based Navigation System as outlined in the Navigation_System.md PRD. The system will replace hardcoded navigation with an intelligent, permission-aware navigation that automatically discovers models and filters based on user permissions.

## Architecture Summary

### Backend Components
- **NavigationBuilder**: Service that discovers models and generates navigation data
- **NavigationConfig**: Configuration class for managing custom pages
- **NavigationAPIController**: REST API endpoints for navigation data
- **Role-specific caching**: Separate cache files for each role

### Frontend Components  
- **NavigationSidebar**: React component replacing hardcoded navigation
- **Dynamic menu system**: Permission-based accordion menus
- **API integration**: Real-time navigation loading

**Note**: All navigation elements (custom pages, models, sections) are displayed in source-code order as they appear in the configuration files. No explicit ordering properties are used.

## Implementation Plan

### Phase 1: Backend Navigation Infrastructure (Week 1)

#### 1.0 AuthorizationService Bug Fix
**Duration**: 0.5 days  
**File**: `src/Services/AuthorizationService.php`

**Issue**: The `roleHasPermission()` method on line 183 uses 'model' as search criteria, but the Permissions table uses 'component' field.

**Fix**: Update the criteria in `roleHasPermission()` method:

```php
// Change this line:
$criteria = [
    'roles_permissions.roles_id' => $role->get('id'),
    'action' => $permission,
    'model' => $model  // BUG: Should be 'component'
];

// To this:
$criteria = [
    'roles_permissions.roles_id' => $role->get('id'),
    'action' => $permission,
    'component' => $model  // FIXED: Use 'component' field
];
```

#### 1.1 NavigationConfig Class Creation
**Duration**: 1 day  
**File**: `src/Navigation/NavigationConfig.php`

Create a configuration management class following the framework's Config class pattern:

```php
<?php

namespace Gravitycar\Navigation;

use Gravitycar\Exceptions\GCException;

/**
 * NavigationConfig - Manages custom page navigation configuration
 * Follows framework Config class patterns for consistency
 */
class NavigationConfig
{
    protected array $config = [];
    protected string $configFilePath;

    public function __construct()
    {
        $this->configFilePath = 'src/Navigation/navigation_config.php';
        $this->loadConfig();
    }

    /**
     * Load navigation configuration from file
     */
    protected function loadConfig(): void
    {
        if (!file_exists($this->configFilePath)) {
            throw new GCException('Navigation config file not found', [
                'config_file_path' => $this->configFilePath
            ]);
        }
        
        $config = include $this->configFilePath;
        if (!is_array($config)) {
            throw new GCException('Navigation config file must return an array', [
                'config_file_path' => $this->configFilePath
            ]);
        }
        
        $this->config = $config;
    }

    /**
     * Get navigation configuration value using dot notation
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }

    /**
     * Get all custom pages for a specific role
     */
    public function getCustomPagesForRole(string $role): array
    {
        $customPages = $this->get('custom_pages', []);
        $filteredPages = [];

        foreach ($customPages as $page) {
            $allowedRoles = $page['roles'] ?? [];
            if (in_array($role, $allowedRoles) || in_array('*', $allowedRoles)) {
                $filteredPages[] = $page;
            }
        }

        // Return pages in source-code order (no sorting)
        return $filteredPages;
    }

    /**
     * Get all navigation sections configuration
     */
    public function getNavigationSections(): array
    {
        return $this->get('navigation_sections', []);
    }
}
```

**Configuration File**: `src/Navigation/navigation_config.php`

```php
<?php

return [
    // Custom pages that don't correspond to models
    // NOTE: Navigation elements will be displayed in source-code order
    'custom_pages' => [
        [
            'key' => 'dashboard',
            'title' => 'Dashboard',
            'url' => '/dashboard',
            'icon' => '📊',
            'roles' => ['*'] // All roles
        ],
        [
            'key' => 'trivia',
            'title' => 'Movie Trivia',
            'url' => '/trivia',
            'icon' => '🎬',
            'roles' => ['admin', 'user']
        ]
    ],

    // Navigation section configuration
    'navigation_sections' => [
        [
            'key' => 'main',
            'title' => 'Main Navigation'
        ],
        [
            'key' => 'models',
            'title' => 'Data Management'
        ],
        [
            'key' => 'tools',
            'title' => 'Tools & Utilities'
        ]
    ]
];
```

#### 1.2 NavigationBuilder Service
**Duration**: 2 days  
**File**: `src/Services/NavigationBuilder.php`

Create the core service that discovers models and builds navigation structures:

```php
<?php

namespace Gravitycar\Services;

use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Navigation\NavigationConfig;
use Gravitycar\Factories\ModelFactory;
use Psr\Log\LoggerInterface;

/**
 * NavigationBuilder - Builds role-specific navigation structures
 * Integrates with RBAC system and metadata discovery
 */
class NavigationBuilder
{
    protected LoggerInterface $logger;
    protected MetadataEngineInterface $metadataEngine;
    protected AuthorizationService $authorizationService;
    protected NavigationConfig $navigationConfig;
    protected ModelFactory $modelFactory;

    public function __construct(
        LoggerInterface $logger,
        MetadataEngineInterface $metadataEngine,
        AuthorizationService $authorizationService,
        NavigationConfig $navigationConfig,
        ModelFactory $modelFactory
    ) {
        $this->logger = $logger;
        $this->metadataEngine = $metadataEngine;
        $this->authorizationService = $authorizationService;
        $this->navigationConfig = $navigationConfig;
        $this->modelFactory = $modelFactory;
    }

    /**
     * Build navigation structure for a specific role
     */
    public function buildNavigationForRole(string $role): array
    {
        $this->logger->debug('Building navigation for role', ['role' => $role]);

        $navigation = [
            'role' => $role,
            'sections' => [],
            'custom_pages' => [],
            'models' => [],
            'generated_at' => date('c')
        ];

        // Get custom pages for this role
        $navigation['custom_pages'] = $this->navigationConfig->getCustomPagesForRole($role);

        // Get available models and filter by permissions
        $availableModels = $this->metadataEngine->getAvailableModels();
        $navigation['models'] = $this->buildModelNavigation($availableModels, $role);

        // Get navigation sections
        $navigation['sections'] = $this->navigationConfig->getNavigationSections();

        $this->logger->info('Navigation built successfully', [
            'role' => $role,
            'custom_pages_count' => count($navigation['custom_pages']),
            'models_count' => count($navigation['models'])
        ]);

        return $navigation;
    }

    /**
     * Build model navigation items with permission filtering
     */
    protected function buildModelNavigation(array $modelNames, string $role): array
    {
        $modelNavigation = [];

        // Get the role model instance for permission checking
        $roleModel = $this->getRoleByName($role);
        if (!$roleModel) {
            $this->logger->warning('Role not found for navigation building', ['role' => $role]);
            return [];
        }

        foreach ($modelNames as $modelName) {
            try {
                // Check if role has list permission for this model
                $hasListPermission = $this->authorizationService->roleHasPermission($roleModel, 'list', $modelName);
                
                if (!$hasListPermission) {
                    continue; // Skip this model if no list permission
                }

                // Build model navigation item
                $modelItem = [
                    'name' => $modelName,
                    'title' => $this->generateModelTitle($modelName),
                    'url' => '/' . strtolower($modelName),
                    'icon' => $this->getModelIcon($modelName),
                    'actions' => [],
                    'permissions' => [
                        'list' => true, // Already verified above
                        'create' => false,
                        'update' => false,
                        'delete' => false
                    ]
                ];

                // Check for create permission
                $hasCreatePermission = $this->authorizationService->roleHasPermission($roleModel, 'create', $modelName);
                if ($hasCreatePermission) {
                    $modelItem['actions'][] = [
                        'key' => 'create',
                        'title' => 'Create New',
                        'url' => '/' . strtolower($modelName) . '/create',
                        'icon' => '➕'
                    ];
                    $modelItem['permissions']['create'] = true;
                }

                // Check for update permission (for UI link enabling/disabling)
                $hasUpdatePermission = $this->authorizationService->roleHasPermission($roleModel, 'update', $modelName);
                if ($hasUpdatePermission) {
                    $modelItem['permissions']['update'] = true;
                }

                // Check for delete permission (for UI link enabling/disabling)
                $hasDeletePermission = $this->authorizationService->roleHasPermission($roleModel, 'delete', $modelName);
                if ($hasDeletePermission) {
                    $modelItem['permissions']['delete'] = true;
                }

                $modelNavigation[] = $modelItem;

            } catch (\Exception $e) {
                $this->logger->warning('Failed to build navigation for model', [
                    'model' => $modelName,
                    'role' => $role,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Sort models alphabetically
        usort($modelNavigation, function($a, $b) {
            return strcmp($a['title'], $b['title']);
        });

        return $modelNavigation;
    }

    /**
     * Get a role model by role name
     */
    protected function getRoleByName(string $roleName): ?\Gravitycar\Models\ModelBase
    {
        try {
            $roleModel = $this->modelFactory->new('Roles');
            $roles = $roleModel->find(['name' => $roleName]);
            
            return !empty($roles) ? $roles[0] : null;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get role by name', [
                'role_name' => $roleName,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Generate user-friendly title from model name
     */
    protected function generateModelTitle(string $modelName): string
    {
        // Convert PascalCase to Title Case with spaces
        $title = preg_replace('/(?<!^)[A-Z]/', ' $0', $modelName);
        return trim($title);
    }

    /**
     * Get icon for model (can be customized later)
     */
    protected function getModelIcon(string $modelName): string
    {
        $iconMap = [
            'Users' => '👥',
            'Movies' => '🎬',
            'Movie_Quotes' => '💬',
            'Roles' => '🔑',
            'Permissions' => '🛡️',
            'Books' => '📚'
        ];

        return $iconMap[$modelName] ?? '📋';
    }

    /**
     * Build navigation cache for all roles
     */
    public function buildAllRoleNavigationCaches(): array
    {
        $roles = ['admin', 'manager', 'user', 'guest'];
        $cacheResults = [];

        foreach ($roles as $role) {
            try {
                $navigation = $this->buildNavigationForRole($role);
                $cacheFile = "cache/navigation_cache_{$role}.php";
                
                $this->writeNavigationCache($cacheFile, $navigation);
                $cacheResults[$role] = [
                    'success' => true,
                    'cache_file' => $cacheFile,
                    'items_count' => count($navigation['models']) + count($navigation['custom_pages'])
                ];

            } catch (\Exception $e) {
                $cacheResults[$role] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                
                $this->logger->error('Failed to build navigation cache for role', [
                    'role' => $role,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $cacheResults;
    }

    /**
     * Write navigation data to cache file
     */
    protected function writeNavigationCache(string $cacheFile, array $navigation): void
    {
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $content = '<?php return ' . var_export($navigation, true) . ';';
        
        if (file_put_contents($cacheFile, $content) === false) {
            throw new \Exception("Failed to write navigation cache file: {$cacheFile}");
        }

        $this->logger->debug('Navigation cache written', [
            'cache_file' => $cacheFile,
            'file_size' => filesize($cacheFile)
        ]);
    }
}
```

#### 1.3 NavigationAPIController
**Duration**: 1 day  
**File**: `src/Api/NavigationAPIController.php`

Create the API controller following the framework's wildcard patterns:

```php
<?php

namespace Gravitycar\Api;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Api\Request;
use Gravitycar\Services\NavigationBuilder;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\InternalServerErrorException;

/**
 * NavigationAPIController - Provides navigation data via REST API
 * Uses wildcard routing pattern following MetadataAPIController example
 */
class NavigationAPIController extends ApiControllerBase
{
    protected NavigationBuilder $navigationBuilder;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Gravitycar\Factories\ModelFactory $modelFactory,
        \Gravitycar\Contracts\DatabaseConnectorInterface $databaseConnector,
        \Gravitycar\Contracts\MetadataEngineInterface $metadataEngine,
        \Gravitycar\Core\Config $config,
        \Gravitycar\Contracts\CurrentUserProviderInterface $currentUserProvider,
        NavigationBuilder $navigationBuilder
    ) {
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config, $currentUserProvider);
        $this->navigationBuilder = $navigationBuilder;
    }

    /**
     * Register routes for navigation API
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/navigation',
                'apiClass' => '\\Gravitycar\\Api\\NavigationAPIController',
                'apiMethod' => 'getCurrentUserNavigation',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/navigation/?',
                'apiClass' => '\\Gravitycar\\Api\\NavigationAPIController',
                'apiMethod' => 'getNavigationByRole',
                'parameterNames' => ['roleName']
            ],
            [
                'method' => 'POST',
                'path' => '/navigation/cache/rebuild',
                'apiClass' => '\\Gravitycar\\Api\\NavigationAPIController',
                'apiMethod' => 'rebuildNavigationCache',
                'parameterNames' => []
            ]
        ];
    }

    /**
     * Get navigation data for current user's role
     */
    public function getCurrentUserNavigation(): array
    {
        try {
            $currentUser = $this->currentUserProvider->getCurrentUser();
            if (!$currentUser) {
                throw new BadRequestException('No authenticated user found');
            }

            $userRole = $currentUser->get('user_type') ?? 'guest';
            
            return $this->getNavigationByRoleInternal($userRole);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get current user navigation', [
                'error' => $e->getMessage()
            ]);
            
            throw new InternalServerErrorException(
                'Failed to retrieve navigation data',
                ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Get navigation data for specific role
     */
    public function getNavigationByRole(Request $request): array
    {
        $roleName = $request->get('roleName');
        
        if (!$roleName) {
            throw new BadRequestException('Role name is required');
        }

        // Validate role name
        $validRoles = ['admin', 'manager', 'user', 'guest'];
        if (!in_array($roleName, $validRoles)) {
            throw new BadRequestException('Invalid role name', [
                'provided_role' => $roleName,
                'valid_roles' => $validRoles
            ]);
        }

        return $this->getNavigationByRoleInternal($roleName);
    }

    /**
     * Internal method to get navigation by role with caching
     */
    protected function getNavigationByRoleInternal(string $role): array
    {
        try {
            // Try to load from cache first
            $cacheFile = "cache/navigation_cache_{$role}.php";
            
            if (file_exists($cacheFile)) {
                $cachedNavigation = include $cacheFile;
                if (is_array($cachedNavigation)) {
                    $this->logger->debug('Navigation loaded from cache', [
                        'role' => $role,
                        'cache_file' => $cacheFile
                    ]);

                    return [
                        'success' => true,
                        'status' => 200,
                        'data' => $cachedNavigation,
                        'cache_hit' => true,
                        'timestamp' => date('c')
                    ];
                }
            }

            // Build navigation dynamically if cache miss
            $this->logger->info('Navigation cache miss, building dynamically', ['role' => $role]);
            
            $navigation = $this->navigationBuilder->buildNavigationForRole($role);

            return [
                'success' => true,
                'status' => 200,
                'data' => $navigation,
                'cache_hit' => false,
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get navigation for role', [
                'role' => $role,
                'error' => $e->getMessage()
            ]);

            throw new InternalServerErrorException(
                'Failed to retrieve navigation data for role',
                ['role' => $role, 'original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Rebuild navigation cache for all roles
     */
    public function rebuildNavigationCache(): array
    {
        try {
            $this->logger->info('Rebuilding navigation cache for all roles');
            
            $results = $this->navigationBuilder->buildAllRoleNavigationCaches();

            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $totalRoles = count($results);

            return [
                'success' => true,
                'status' => 200,
                'data' => [
                    'message' => 'Navigation cache rebuild completed',
                    'total_roles' => $totalRoles,
                    'successful_rebuilds' => $successCount,
                    'results' => $results
                ],
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to rebuild navigation cache', [
                'error' => $e->getMessage()
            ]);

            throw new InternalServerErrorException(
                'Failed to rebuild navigation cache',
                ['original_error' => $e->getMessage()]
            );
        }
    }
}
```

#### 1.4 Container Configuration Updates
**Duration**: 0.5 days  
**File**: `src/Core/ContainerConfig.php`

Add NavigationBuilder and NavigationConfig to the dependency injection container:

```php
// Add to the container configuration array:

'navigation_config' => function() {
    return new \Gravitycar\Navigation\NavigationConfig();
},

'navigation_builder' => function($container) {
    return new \Gravitycar\Services\NavigationBuilder(
        $container->get('logger'),
        $container->get('metadata_engine'),
        $container->get('authorization_service'),
        $container->get('navigation_config'),
        $container->get('model_factory')
    );
},
```

### Phase 2: Cache Integration and Setup (Week 1)

#### 2.1 Setup.php Integration
**Duration**: 0.5 days  
**File**: `setup.php`

**IMPORTANT**: Navigation cache building must occur AFTER the PermissionsBuilder has completed its work, since the navigation system relies on the permissions data to determine what models and actions users can access.

Add navigation cache building to the setup process AFTER Step 5.5 (PermissionsBuilder):

```php
// Add after Step 5.5: Building Permissions from Metadata (around line 300):

// Step 6: Build Navigation Cache
printHeader("Step 6: Building Navigation Cache");
printInfo("Building role-specific navigation caches...");

try {
    $container = \Gravitycar\Core\ContainerConfig::getContainer();
    $navigationBuilder = $container->get('navigation_builder');
    
    $cacheResults = $navigationBuilder->buildAllRoleNavigationCaches();
    
    $successCount = count(array_filter($cacheResults, fn($r) => $r['success']));
    $totalRoles = count($cacheResults);
    
    printSuccess("Navigation cache built: {$successCount}/{$totalRoles} roles cached successfully");
    
    foreach ($cacheResults as $role => $result) {
        if ($result['success']) {
            printInfo("  ✓ {$role}: {$result['items_count']} navigation items");
        } else {
            printWarning("  ✗ {$role}: {$result['error']}");
        }
    }
    
} catch (Exception $e) {
    printWarning("Could not build navigation cache: " . $e->getMessage());
}

// Note: Step 7 would then be "Creating Sample Users" (renumbered from Step 6)
```

### Phase 3: Frontend Navigation Component (Week 2)

#### 3.1 Navigation Types
**Duration**: 0.5 days  
**File**: `gravitycar-frontend/src/types/navigation.ts`

Define TypeScript types for navigation data:

```typescript
export interface NavigationItem {
  key: string;
  title: string;
  url: string;
  icon?: string;
  actions?: NavigationAction[];
  permissions?: {
    list: boolean;
    create: boolean;
    update: boolean;
    delete: boolean;
  };
}

export interface NavigationAction {
  key: string;
  title: string;
  url: string;
  icon?: string;
}

export interface CustomPage {
  key: string;
  title: string;
  url: string;
  icon?: string;
  roles: string[];
}

export interface NavigationSection {
  key: string;
  title: string;
}

export interface NavigationData {
  role: string;
  sections: NavigationSection[];
  custom_pages: CustomPage[];
  models: NavigationItem[];
  generated_at: string;
}

export interface NavigationResponse {
  success: boolean;
  status: number;
  data: NavigationData;
  cache_hit: boolean;
  timestamp: string;
}
```

#### 3.2 Navigation Service
**Duration**: 0.5 days  
**File**: `gravitycar-frontend/src/services/navigationService.ts`

Create service for fetching navigation data:

```typescript
import { apiService } from './api';
import { NavigationResponse } from '../types/navigation';

class NavigationService {
  private cache: Map<string, NavigationResponse> = new Map();
  private cacheExpiry: Map<string, number> = new Map();
  private readonly CACHE_TTL = 5 * 60 * 1000; // 5 minutes

  /**
   * Get navigation for current user
   */
  async getCurrentUserNavigation(): Promise<NavigationResponse> {
    const cacheKey = 'current_user';
    
    // Check cache first
    if (this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey)!;
    }

    try {
      const response = await apiService.request<NavigationResponse>({
        method: 'GET',
        url: '/navigation'
      });

      // Cache the response
      this.setCache(cacheKey, response);
      
      return response;

    } catch (error) {
      console.error('Failed to fetch navigation:', error);
      throw error;
    }
  }

  /**
   * Get navigation for specific role
   */
  async getNavigationByRole(role: string): Promise<NavigationResponse> {
    const cacheKey = `role_${role}`;
    
    // Check cache first
    if (this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey)!;
    }

    try {
      const response = await apiService.request<NavigationResponse>({
        method: 'GET',
        url: `/navigation/${role}`
      });

      // Cache the response
      this.setCache(cacheKey, response);
      
      return response;

    } catch (error) {
      console.error(`Failed to fetch navigation for role ${role}:`, error);
      throw error;
    }
  }

  /**
   * Clear navigation cache
   */
  clearCache(): void {
    this.cache.clear();
    this.cacheExpiry.clear();
  }

  /**
   * Check if cached data is still valid
   */
  private isCacheValid(key: string): boolean {
    const expiry = this.cacheExpiry.get(key);
    return expiry ? Date.now() < expiry : false;
  }

  /**
   * Set cache with TTL
   */
  private setCache(key: string, data: NavigationResponse): void {
    this.cache.set(key, data);
    this.cacheExpiry.set(key, Date.now() + this.CACHE_TTL);
  }
}

export const navigationService = new NavigationService();
```

#### 3.3 NavigationSidebar Component
**Duration**: 2 days  
**File**: `gravitycar-frontend/src/components/navigation/NavigationSidebar.tsx`

Create the main navigation component:

```typescript
import React, { useState, useEffect } from 'react';
import { navigationService } from '../../services/navigationService';
import { NavigationData, NavigationItem } from '../../types/navigation';
import { useAuth } from '../../hooks/useAuth';

interface NavigationSidebarProps {
  className?: string;
}

const NavigationSidebar: React.FC<NavigationSidebarProps> = ({ className = '' }) => {
  const { user } = useAuth();
  const [navigationData, setNavigationData] = useState<NavigationData | null>(null);
  const [expandedModel, setExpandedModel] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadNavigation();
  }, [user]);

  const loadNavigation = async () => {
    try {
      setIsLoading(true);
      setError(null);
      
      const response = await navigationService.getCurrentUserNavigation();
      setNavigationData(response.data);
      
    } catch (err) {
      console.error('Failed to load navigation:', err);
      setError('Failed to load navigation');
    } finally {
      setIsLoading(false);
    }
  };

  const handleModelClick = (modelKey: string) => {
    setExpandedModel(expandedModel === modelKey ? null : modelKey);
  };

  if (isLoading) {
    return (
      <nav className={`bg-gray-50 border-r ${className}`}>
        <div className="p-4">
          <div className="animate-pulse">
            <div className="h-4 bg-gray-200 rounded mb-2"></div>
            <div className="h-4 bg-gray-200 rounded mb-2"></div>
            <div className="h-4 bg-gray-200 rounded"></div>
          </div>
        </div>
      </nav>
    );
  }

  if (error) {
    return (
      <nav className={`bg-gray-50 border-r ${className}`}>
        <div className="p-4 text-red-600">
          <p className="text-sm">{error}</p>
          <button 
            onClick={loadNavigation}
            className="mt-2 text-xs underline hover:no-underline"
          >
            Retry
          </button>
        </div>
      </nav>
    );
  }

  if (!navigationData) {
    return (
      <nav className={`bg-gray-50 border-r ${className}`}>
        <div className="p-4 text-gray-500">
          <p className="text-sm">No navigation data available</p>
        </div>
      </nav>
    );
  }

  return (
    <nav className={`bg-gray-50 border-r ${className}`}>
      <div className="p-4">
        {/* Custom Pages Section */}
        {navigationData.custom_pages.length > 0 && (
          <div className="mb-6">
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
              Navigation
            </h3>
            <ul className="space-y-1">
              {navigationData.custom_pages.map((page) => (
                <li key={page.key}>
                  <a
                    href={page.url}
                    className="flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
                  >
                    <span className="mr-2">{page.icon}</span>
                    {page.title}
                  </a>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Models Section */}
        {navigationData.models.length > 0 && (
          <div>
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
              Data Management
            </h3>
            <ul className="space-y-1">
              {navigationData.models.map((model) => (
                <li key={model.name}>
                  <div>
                    {/* Model Name - Always clickable to list view */}
                    <a
                      href={model.url}
                      className="flex items-center justify-between px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
                    >
                      <div className="flex items-center">
                        <span className="mr-2">{model.icon}</span>
                        {model.title}
                      </div>
                      {model.actions && model.actions.length > 0 && (
                        <button
                          onClick={(e) => {
                            e.preventDefault();
                            handleModelClick(model.name);
                          }}
                          className="ml-2 p-1 hover:bg-gray-200 rounded"
                        >
                          <svg 
                            className={`w-4 h-4 transition-transform ${
                              expandedModel === model.name ? 'rotate-180' : ''
                            }`}
                            fill="currentColor" 
                            viewBox="0 0 20 20"
                          >
                            <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                          </svg>
                        </button>
                      )}
                    </a>

                    {/* Expandable Actions */}
                    {expandedModel === model.name && model.actions && model.actions.length > 0 && (
                      <ul className="mt-1 ml-6 space-y-1">
                        {model.actions.map((action) => (
                          <li key={action.key}>
                            <a
                              href={action.url}
                              className="flex items-center px-3 py-1 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                            >
                              <span className="mr-2">{action.icon}</span>
                              {action.title}
                            </a>
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Debug Info (only in development) */}
        {process.env.NODE_ENV === 'development' && (
          <div className="mt-6 pt-4 border-t border-gray-200">
            <p className="text-xs text-gray-400">
              Role: {navigationData.role} | Cache: {navigationData.cache_hit ? 'Hit' : 'Miss'}
            </p>
            {/* Show permissions for debugging */}
            <details className="mt-2">
              <summary className="text-xs text-gray-500 cursor-pointer">Model Permissions</summary>
              <div className="mt-1 text-xs text-gray-400">
                {navigationData.models.map((model) => (
                  <div key={model.name} className="mb-1">
                    <strong>{model.name}:</strong> 
                    {model.permissions && Object.entries(model.permissions)
                      .filter(([, hasPermission]) => hasPermission)
                      .map(([permission]) => permission)
                      .join(', ')}
                  </div>
                ))}
              </div>
            </details>
          </div>
        )}
      </div>
    </nav>
  );
};

export default NavigationSidebar;
```

#### 3.4 Layout Component Integration
**Duration**: 0.5 days  
**File**: `gravitycar-frontend/src/components/layout/Layout.tsx`

Replace the hardcoded navigation with the dynamic component:

```typescript
// Replace the existing navigation section with:

import NavigationSidebar from '../navigation/NavigationSidebar';

// In the Layout component, replace the hardcoded nav section:

{/* Dynamic Navigation Sidebar */}
{isAuthenticated && (
  <NavigationSidebar className="w-64 min-h-screen" />
)}

// Remove the old hardcoded navigation:
{/* Navigation */}
{isAuthenticated && (
  <nav className="bg-gray-50 border-b">
    {/* ... remove all the hardcoded navigation links ... */}
  </nav>
)}
```

### Phase 4: Testing and Integration (Week 3)

#### 4.1 Backend Unit Tests
**Duration**: 2 days

**NavigationConfig Tests** - `Tests/Unit/Navigation/NavigationConfigTest.php`:

```php
<?php

namespace Tests\Unit\Navigation;

use PHPUnit\Framework\TestCase;
use Gravitycar\Navigation\NavigationConfig;
use Gravitycar\Exceptions\GCException;

class NavigationConfigTest extends TestCase
{
    private NavigationConfig $navigationConfig;

    protected function setUp(): void
    {
        // Create temporary config file for testing
        $testConfigDir = 'src/Navigation';
        if (!is_dir($testConfigDir)) {
            mkdir($testConfigDir, 0755, true);
        }
        
        $testConfigFile = $testConfigDir . '/navigation_config.php';
        $testConfig = [
            'custom_pages' => [
                [
                    'key' => 'dashboard',
                    'title' => 'Dashboard',
                    'url' => '/dashboard',
                    'roles' => ['*']
                ],
                [
                    'key' => 'admin_only',
                    'title' => 'Admin Panel',
                    'url' => '/admin',
                    'roles' => ['admin']
                ]
            ]
        ];
        
        file_put_contents($testConfigFile, '<?php return ' . var_export($testConfig, true) . ';');
        
        $this->navigationConfig = new NavigationConfig();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $testConfigFile = 'src/Navigation/navigation_config.php';
        if (file_exists($testConfigFile)) {
            unlink($testConfigFile);
        }
        
        // Remove directory if empty
        $testConfigDir = 'src/Navigation';
        if (is_dir($testConfigDir) && count(scandir($testConfigDir)) === 2) {
            rmdir($testConfigDir);
        }
        
        // Remove src directory if empty
        if (is_dir('src') && count(scandir('src')) === 2) {
            rmdir('src');
        }
    }

    public function testGetCustomPagesForRole(): void
    {
        // Test admin role gets all pages
        $adminPages = $this->navigationConfig->getCustomPagesForRole('admin');
        $this->assertCount(2, $adminPages);
        
        // Test user role only gets pages with '*' or 'user' role
        $userPages = $this->navigationConfig->getCustomPagesForRole('user');
        $this->assertCount(1, $userPages);
        $this->assertEquals('dashboard', $userPages[0]['key']);
    }

    public function testGetConfigValue(): void
    {
        $customPages = $this->navigationConfig->get('custom_pages');
        $this->assertIsArray($customPages);
        $this->assertCount(2, $customPages);
        
        $nonExistent = $this->navigationConfig->get('non_existent', 'default');
        $this->assertEquals('default', $nonExistent);
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
```

**NavigationBuilder Tests** - `Tests/Unit/Services/NavigationBuilderTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Gravitycar\Services\NavigationBuilder;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Navigation\NavigationConfig;
use Gravitycar\Factories\ModelFactory;
use Psr\Log\LoggerInterface;

class NavigationBuilderTest extends TestCase
{
    private NavigationBuilder $navigationBuilder;
    private $mockLogger;
    private $mockMetadataEngine;
    private $mockAuthorizationService;
    private $mockNavigationConfig;
    private $mockModelFactory;

    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockAuthorizationService = $this->createMock(AuthorizationService::class);
        $this->mockNavigationConfig = $this->createMock(NavigationConfig::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);

        $this->navigationBuilder = new NavigationBuilder(
            $this->mockLogger,
            $this->mockMetadataEngine,
            $this->mockAuthorizationService,
            $this->mockNavigationConfig,
            $this->mockModelFactory
        );
    }

    public function testBuildNavigationForRole(): void
    {
        // Mock available models
        $this->mockMetadataEngine->expects($this->once())
            ->method('getAvailableModels')
            ->willReturn(['Users', 'Movies']);

        // Mock custom pages
        $this->mockNavigationConfig->expects($this->once())
            ->method('getCustomPagesForRole')
            ->with('admin')
            ->willReturn([
                ['key' => 'dashboard', 'title' => 'Dashboard', 'url' => '/dashboard']
            ]);

        // Mock navigation sections
        $this->mockNavigationConfig->expects($this->once())
            ->method('getNavigationSections')
            ->willReturn([
                ['key' => 'main', 'title' => 'Main Navigation']
            ]);

        // Mock role model creation
        $mockRole = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $this->mockModelFactory->expects($this->atLeastOnce())
            ->method('new')
            ->with('Roles')
            ->willReturn($mockRole);

        // Mock role finding
        $mockRole->expects($this->once())
            ->method('find')
            ->with(['name' => 'admin'])
            ->willReturn([$mockRole]);

        // Mock permission checks using roleHasPermission
        $this->mockAuthorizationService->expects($this->atLeastOnce())
            ->method('roleHasPermission')
            ->willReturnCallback(function($role, $permission, $model) {
                // Mock permissions: list and create allowed, update/delete varies by test
                return in_array($permission, ['list', 'create', 'update', 'delete']);
            });

        $result = $this->navigationBuilder->buildNavigationForRole('admin');

        $this->assertIsArray($result);
        $this->assertEquals('admin', $result['role']);
        $this->assertArrayHasKey('custom_pages', $result);
        $this->assertArrayHasKey('models', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertArrayHasKey('generated_at', $result);
    }

    public function testBuildModelNavigationFiltersUnauthorizedModels(): void
    {
        $modelNames = ['Users', 'Movies', 'RestrictedModel'];

        // Mock role creation
        $mockRole = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $this->mockModelFactory->method('new')
            ->willReturn($mockRole);

        // Mock role finding
        $mockRole->method('find')
            ->willReturn([$mockRole]);

        // Mock permissions - Users and Movies allowed, RestrictedModel denied
        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturnCallback(function($role, $permission, $model) {
                return $model !== 'RestrictedModel';
            });

        $reflection = new \ReflectionClass($this->navigationBuilder);
        $method = $reflection->getMethod('buildModelNavigation');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->navigationBuilder, [$modelNames, 'user']);

        $this->assertCount(2, $result);
        $modelNames = array_column($result, 'name');
        $this->assertContains('Users', $modelNames);
        $this->assertContains('Movies', $modelNames);
        $this->assertNotContains('RestrictedModel', $modelNames);
    }
}
```

#### 4.2 Frontend Unit Tests
**Duration**: 1 day

**NavigationSidebar Tests** - `gravitycar-frontend/src/components/navigation/__tests__/NavigationSidebar.test.tsx`:

```typescript
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import NavigationSidebar from '../NavigationSidebar';
import { navigationService } from '../../../services/navigationService';
import { useAuth } from '../../../hooks/useAuth';

// Mock dependencies
jest.mock('../../../services/navigationService');
jest.mock('../../../hooks/useAuth');

const mockNavigationService = navigationService as jest.Mocked<typeof navigationService>;
const mockUseAuth = useAuth as jest.MockedFunction<typeof useAuth>;

const mockNavigationData = {
  role: 'admin',
  sections: [
    { key: 'main', title: 'Main Navigation' }
  ],
  custom_pages: [
    {
      key: 'dashboard',
      title: 'Dashboard',
      url: '/dashboard',
      icon: '📊',
      roles: ['*']
    }
  ],
  models: [
    {
      name: 'Users',
      title: 'Users',
      url: '/users',
      icon: '👥',
      actions: [
        {
          key: 'create',
          title: 'Create New',
          url: '/users/create',
          icon: '➕'
        }
      ],
      permissions: {
        list: true,
        create: true,
        update: true,
        delete: false
      }
    }
  ],
  generated_at: '2025-01-01T00:00:00Z'
};

describe('NavigationSidebar', () => {
  beforeEach(() => {
    mockUseAuth.mockReturnValue({
      user: { id: '1', username: 'testuser', user_type: 'admin' },
      isAuthenticated: true,
      isLoading: false,
      login: jest.fn(),
      logout: jest.fn()
    });

    mockNavigationService.getCurrentUserNavigation.mockResolvedValue({
      success: true,
      status: 200,
      data: mockNavigationData,
      cache_hit: true,
      timestamp: '2025-01-01T00:00:00Z'
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('renders loading state initially', () => {
    render(<NavigationSidebar />);
    expect(screen.getByText('Loading...')).toBeInTheDocument();
  });

  it('renders navigation items after loading', async () => {
    render(<NavigationSidebar />);

    await waitFor(() => {
      expect(screen.getByText('Dashboard')).toBeInTheDocument();
      expect(screen.getByText('Users')).toBeInTheDocument();
    });
  });

  it('expands and collapses model actions', async () => {
    const user = userEvent.setup();
    render(<NavigationSidebar />);

    await waitFor(() => {
      expect(screen.getByText('Users')).toBeInTheDocument();
    });

    // Actions should not be visible initially
    expect(screen.queryByText('Create New')).not.toBeInTheDocument();

    // Click the expand button
    const expandButton = screen.getByRole('button');
    await user.click(expandButton);

    // Actions should now be visible
    expect(screen.getByText('Create New')).toBeInTheDocument();

    // Click again to collapse
    await user.click(expandButton);

    // Actions should be hidden again
    expect(screen.queryByText('Create New')).not.toBeInTheDocument();
  });

  it('handles navigation service errors', async () => {
    mockNavigationService.getCurrentUserNavigation.mockRejectedValue(
      new Error('Network error')
    );

    render(<NavigationSidebar />);

    await waitFor(() => {
      expect(screen.getByText('Failed to load navigation')).toBeInTheDocument();
    });
  });

  it('shows correct role and cache status in development', async () => {
    process.env.NODE_ENV = 'development';
    
    render(<NavigationSidebar />);

    await waitFor(() => {
      expect(screen.getByText(/Role: admin.*Cache: Hit/)).toBeInTheDocument();
    });
  });
});
```

#### 4.3 Integration Tests
**Duration**: 1 day

**Navigation API Integration Test** - `Tests/Integration/Api/NavigationAPIIntegrationTest.php`:

```php
<?php

namespace Tests\Integration\Api;

use Tests\TestCase;
use Gravitycar\Core\ContainerConfig;

class NavigationAPIIntegrationTest extends TestCase
{
    private $container;
    private $navigationBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = ContainerConfig::getContainer();
        $this->navigationBuilder = $this->container->get('navigation_builder');
    }

    public function testNavigationEndpointReturnsValidData(): void
    {
        // Build navigation cache first
        $this->navigationBuilder->buildAllRoleNavigationCaches();

        // Test the API endpoint
        $response = $this->get('/navigation/admin');

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        
        $navigationData = $response['data'];
        $this->assertEquals('admin', $navigationData['role']);
        $this->assertArrayHasKey('custom_pages', $navigationData);
        $this->assertArrayHasKey('models', $navigationData);
        $this->assertArrayHasKey('sections', $navigationData);
    }

    public function testNavigationCacheRebuildEndpoint(): void
    {
        $response = $this->post('/navigation/cache/rebuild');

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['success']);
        
        $data = $response['data'];
        $this->assertArrayHasKey('total_roles', $data);
        $this->assertArrayHasKey('successful_rebuilds', $data);
        $this->assertGreaterThan(0, $data['successful_rebuilds']);
    }

    public function testRoleBasedPermissionFiltering(): void
    {
        // Build caches for different roles
        $this->navigationBuilder->buildAllRoleNavigationCaches();

        // Get admin navigation
        $adminResponse = $this->get('/navigation/admin');
        $adminModels = $adminResponse['data']['models'];

        // Get user navigation  
        $userResponse = $this->get('/navigation/user');
        $userModels = $userResponse['data']['models'];

        // Admin should have more or equal models than user
        $this->assertGreaterThanOrEqual(count($userModels), count($adminModels));

        // Test specific permissions
        $adminModelNames = array_column($adminModels, 'name');
        $userModelNames = array_column($userModels, 'name');
        
        // Both should have Users model (assuming basic permissions)
        $this->assertContains('Users', $adminModelNames);
        $this->assertContains('Users', $userModelNames);
    }

    private function get(string $url): array
    {
        // Mock HTTP GET request using your test framework
        // This is a simplified example - adapt to your testing setup
        $router = $this->container->get('router');
        return $router->route('GET', $url);
    }

    private function post(string $url): array
    {
        // Mock HTTP POST request
        $router = $this->container->get('router');
        return $router->route('POST', $url);
    }
}
```

### Phase 5: Documentation and Deployment (Week 3)

#### 5.1 API Documentation
**Duration**: 1 day

Create comprehensive API documentation for the navigation endpoints:

**File**: `docs/api/navigation_api.md`

```markdown
# Navigation API Documentation

## Overview
The Navigation API provides role-based navigation data for the Gravitycar Framework frontend. It automatically discovers available models, filters them based on user permissions, and includes custom pages configuration.

## Endpoints

### GET /navigation
Get navigation data for the current authenticated user.

**Response:**
```json
{
  "success": true,
  "status": 200,
  "data": {
    "role": "admin",
    "sections": [...],
    "custom_pages": [...],
    "models": [...],
    "generated_at": "2025-01-01T00:00:00Z"
  },
  "cache_hit": true,
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### GET /navigation/{role}
Get navigation data for a specific role.

**Parameters:**
- `role` (string): Role name (admin, manager, user, guest)

**Response:** Same as above

### POST /navigation/cache/rebuild
Rebuild navigation cache for all roles.

**Response:**
```json
{
  "success": true,
  "status": 200,
  "data": {
    "message": "Navigation cache rebuild completed",
    "total_roles": 4,
    "successful_rebuilds": 4,
    "results": {...}
  }
}
```

## Configuration

### Custom Pages Configuration
Edit `src/Navigation/navigation_config.php` to add custom pages:

**Note**: Navigation elements are displayed in source-code order (the order they appear in the configuration file).

```php
'custom_pages' => [
    [
        'key' => 'dashboard',
        'title' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => '📊',
        'roles' => ['*'] // All roles
    ]
]
```

### Permission Requirements
The navigation system integrates with the RBAC system:
- Models require 'list' permission to appear in navigation
- Create actions require 'create' permission to show sub-menu
- Custom pages are filtered by role configuration

### Permission Data Structure
Each model in the navigation includes a `permissions` object:
```json
{
  "name": "Users",
  "title": "Users", 
  "url": "/users",
  "permissions": {
    "list": true,
    "create": true,
    "update": false,
    "delete": false
  }
}
```

This allows frontend components to conditionally enable/disable edit and delete functionality based on user permissions.
```

#### 5.2 Developer Guide
**Duration**: 1 day

**File**: `docs/developers/navigation_system_guide.md`

```markdown
# Navigation System Developer Guide

## Architecture Overview

The Dynamic Navigation System consists of three main layers:

1. **Configuration Layer**: NavigationConfig class manages custom pages
2. **Service Layer**: NavigationBuilder generates role-specific navigation
3. **API Layer**: NavigationAPIController provides REST endpoints
4. **Frontend Layer**: NavigationSidebar renders dynamic navigation

## Adding New Custom Pages

### 1. Update Configuration
Edit `src/Navigation/navigation_config.php`:

```php
'custom_pages' => [
    // Existing pages...
    [
        'key' => 'my_new_page',
        'title' => 'My New Page',
        'url' => '/my-new-page',
        'icon' => '🔧',
        'roles' => ['admin', 'manager']
    ]
]
```

### 2. Rebuild Cache
Run setup.php or call the rebuild endpoint:

```bash
php setup.php
# OR
curl -X POST http://localhost:8081/navigation/cache/rebuild
```

### 3. Add Frontend Route
Update `src/App.tsx`:

```typescript
<Route
  path="/my-new-page"
  element={
    <ProtectedRoute>
      <Layout>
        <MyNewPageComponent />
      </Layout>
    </ProtectedRoute>
  }
/>
```

## Extending Model Navigation

### Custom Model Icons
Modify `NavigationBuilder::getModelIcon()`:

```php
protected function getModelIcon(string $modelName): string
{
    $iconMap = [
        'Users' => '👥',
        'Movies' => '🎬',
        'MyModel' => '🆕', // Add new model icon
        // ...
    ];

    return $iconMap[$modelName] ?? '📋';
}
```

### Custom Model Actions
The system automatically checks for 'create' permissions. To add more actions, modify `NavigationBuilder::buildModelNavigation()`.

### Using Permission Data in UI Components
The navigation data includes a `permissions` object for each model that indicates what actions the current user can perform:

```typescript
// Example: Conditionally render edit/delete buttons in a list view
{navigationData.models.find(m => m.name === 'Users')?.permissions?.update && (
  <button onClick={() => editUser(user.id)}>Edit</button>
)}

{navigationData.models.find(m => m.name === 'Users')?.permissions?.delete && (
  <button onClick={() => deleteUser(user.id)}>Delete</button>
)}
```

The permissions structure includes:
- `list`: Can view the model list (always true if model appears in navigation)
- `create`: Can create new records (also controls create action visibility)
- `update`: Can edit existing records (for enabling/disabling edit links)
- `delete`: Can delete records (for enabling/disabling delete links)

## Performance Considerations

### Cache Management
- Navigation is cached per role in `cache/navigation_cache_{role}.php`
- Cache is rebuilt during setup.php execution
- Manual cache rebuild via API endpoint
- Frontend caches responses for 5 minutes

### Permission Optimization
- Uses mock user objects for permission checking during cache build
- Avoids database queries during request handling
- Role-specific pre-computed navigation structures

## Debugging

### Frontend Debug Info
Set `NODE_ENV=development` to see debug information in NavigationSidebar.

### Backend Logging
Navigation building logs detailed information about:
- Model discovery
- Permission checks
- Cache operations
- API requests

### Cache Inspection
Check cache files directly:
```bash
cat cache/navigation_cache_admin.php
```

## Testing

### Unit Tests
```bash
# Backend tests
./vendor/bin/phpunit Tests/Unit/Navigation/
./vendor/bin/phpunit Tests/Unit/Services/NavigationBuilderTest.php

# Frontend tests
cd gravitycar-frontend
npm test -- NavigationSidebar
```

### Integration Tests
```bash
./vendor/bin/phpunit Tests/Integration/Api/NavigationAPIIntegrationTest.php
```
```

## Risk Analysis & Mitigation

### Identified Risks

1. **Permission System Dependency**: Navigation relies heavily on AuthorizationService
   - **Mitigation**: Extensive testing of permission integration, fallback mechanisms, fix identified bug in roleHasPermission()

2. **AuthorizationService Bug**: roleHasPermission() uses 'model' field instead of 'component'
   - **Mitigation**: Fix included in Phase 1.0, update search criteria to use correct 'component' field

2. **Cache Staleness**: Role permissions change not reflected in navigation
   - **Mitigation**: Cache rebuild integrated into setup.php, manual rebuild endpoint

3. **Frontend Fallback**: API failures could break navigation entirely
   - **Mitigation**: Error handling, loading states, retry mechanisms

4. **Performance Impact**: Dynamic permission checking during cache build
   - **Mitigation**: Role-based pre-computation, efficient mock user pattern

## Success Criteria

- ✅ Auto-discovery of new models without navigation updates
- ✅ Permission-based filtering prevents unauthorized access exposure
- ✅ Role-specific caching provides sub-200ms response times
- ✅ Single-click expandable model actions improve UX
- ✅ Custom page configuration enables easy feature additions
- ✅ Frontend gracefully handles API failures and loading states
- ✅ Backend integration with existing RBAC and metadata systems
- ✅ Comprehensive test coverage for navigation logic

## Dependencies

### Backend Dependencies
- MetadataEngine for model discovery
- AuthorizationService for permission filtering (includes bug fix for roleHasPermission)
- ModelFactory for role model retrieval
- Config system for navigation configuration
- Setup.php integration for cache management

### Frontend Dependencies
- React 19+ for NavigationSidebar component
- TypeScript for type safety
- Existing API service layer
- Authentication hooks (useAuth)
- Layout component integration

## Implementation Timeline

**Total Estimated Time**: 3 weeks

- **Week 1**: Backend infrastructure (NavigationConfig, NavigationBuilder, NavigationAPIController)
- **Week 2**: Frontend components (NavigationSidebar, API integration, Layout updates)
- **Week 3**: Testing, documentation, integration, and deployment

This implementation plan provides a complete roadmap for building the Dynamic Role-Based Navigation System with code examples, testing strategies, and detailed technical specifications following the Gravitycar Framework patterns and conventions.