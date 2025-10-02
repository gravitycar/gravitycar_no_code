<?php

namespace Gravitycar\Services;

use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Navigation\NavigationConfig;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\NavigationBuilderException;
use Psr\Log\LoggerInterface;

/**
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
                        'action' => 'create', // Use action instead of url for modal trigger
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
        // First replace underscores with spaces
        $title = str_replace('_', ' ', $modelName);
        
        // Then convert PascalCase to Title Case with spaces, but only if no space precedes
        $title = preg_replace('/(?<!\s)(?<!^)[A-Z]/', ' $0', $title);
        
        // Clean up any multiple spaces
        $title = preg_replace('/\s+/', ' ', $title);
        
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
            throw new NavigationBuilderException("Failed to write navigation cache file: {$cacheFile}");
        }

        $this->logger->debug('Navigation cache written', [
            'cache_file' => $cacheFile,
            'file_size' => filesize($cacheFile)
        ]);
    }
}