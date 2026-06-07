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
            'role'                        => $role,
            'custom_pages_count'          => count($navigation['custom_pages']),
            'top_level_nav_entries_count' => count($navigation['models']),
        ]);

        return $navigation;
    }

    /**
     * Build model navigation items with permission filtering and grouping.
     * Models with navigation_bar === false are hidden; non-empty string values
     * place the model into a named group; empty/absent values leave it ungrouped.
     */
    protected function buildModelNavigation(array $modelNames, string $role): array
    {
        $roleModel = $this->getRoleByName($role);
        if (!$roleModel) {
            $this->logger->warning('Role not found for navigation building', ['role' => $role]);
            return [];
        }

        $groups    = [];   // string $label => array[] $modelItems
        $ungrouped = [];   // array[] $modelItems

        foreach ($modelNames as $modelName) {
            try {
                $hasListPermission = $this->authorizationService->roleHasPermission($roleModel, 'list', $modelName);
                if (!$hasListPermission) {
                    continue;
                }

                $metadata      = $this->metadataEngine->getModelMetadata($modelName);
                $navigationBar = $metadata['navigation_bar'] ?? '';

                if ($navigationBar === false) {
                    continue;  // Hidden model — skip entirely
                }

                $modelItem = $this->buildModelItem($modelName, $roleModel);

                if (is_string($navigationBar) && $navigationBar !== '') {
                    $groups[$navigationBar][] = $modelItem;
                } else {
                    $ungrouped[] = $modelItem;
                }

            } catch (\Exception $e) {
                $this->logger->warning('Failed to build navigation for model', [
                    'model' => $modelName,
                    'role'  => $role,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->assembleNavigationResult($groups, $ungrouped);
    }

    /**
     * Sort groups and ungrouped items, then merge into the final ordered result array.
     * Groups appear first (sorted alphabetically by label), followed by ungrouped items
     * (sorted alphabetically by title).
     */
    private function assembleNavigationResult(array $groups, array $ungrouped): array
    {
        // Sort items within each group alphabetically by title
        foreach ($groups as &$groupItems) {
            usort($groupItems, fn(array $a, array $b) => strcmp($a['title'], $b['title']));
        }
        unset($groupItems);

        // Sort group labels alphabetically
        ksort($groups);

        // Sort ungrouped items alphabetically by title
        usort($ungrouped, fn(array $a, array $b) => strcmp($a['title'], $b['title']));

        // Build result: groups first, then ungrouped items
        $result = [];
        foreach ($groups as $label => $items) {
            $result[] = [
                'type'  => 'group',
                'label' => $label,
                'items' => $items,
            ];
        }
        foreach ($ungrouped as $item) {
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Count total individual model items across all groups and ungrouped entries.
     */
    private function countTotalModelItems(array $modelEntries): int
    {
        $count = 0;
        foreach ($modelEntries as $entry) {
            if ($entry['type'] === 'group') {
                $count += count($entry['items']);
            } else {
                $count += 1;
            }
        }
        return $count;
    }

    /**
     * Build a single navigation item array for a model.
     * Always includes 'type' => 'item' as the first key.
     */
    protected function buildModelItem(string $modelName, object $roleModel): array
    {
        $modelItem = [
            'type'        => 'item',
            'name'        => $modelName,
            'title'       => $this->generateModelTitle($modelName),
            'url'         => '/' . $modelName,
            'icon'        => $this->getModelIcon($modelName),
            'actions'     => [],
            'permissions' => [
                'list'   => true,
                'create' => false,
                'update' => false,
                'delete' => false,
            ],
        ];

        $hasCreatePermission = $this->authorizationService->roleHasPermission($roleModel, 'create', $modelName);
        if ($hasCreatePermission) {
            $modelItem['actions'][] = [
                'key'    => 'create',
                'title'  => 'Create New',
                'action' => 'create',
                'icon'   => '➕',
            ];
            $modelItem['permissions']['create'] = true;
        }

        $hasUpdatePermission = $this->authorizationService->roleHasPermission($roleModel, 'update', $modelName);
        if ($hasUpdatePermission) {
            $modelItem['permissions']['update'] = true;
        }

        $hasDeletePermission = $this->authorizationService->roleHasPermission($roleModel, 'delete', $modelName);
        if ($hasDeletePermission) {
            $modelItem['permissions']['delete'] = true;
        }

        return $modelItem;
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
            'Books' => '📚',
            'Events' => '📅'
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

                $totalModelItems = $this->countTotalModelItems($navigation['models']);

                $cacheResults[$role] = [
                    'success'                 => true,
                    'cache_file'              => $cacheFile,
                    'total_model_items_count' => $totalModelItems + count($navigation['custom_pages']),
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
