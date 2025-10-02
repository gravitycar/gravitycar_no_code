<?php

namespace Gravitycar\Api;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Api\Request;
use Gravitycar\Services\NavigationBuilder;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\InternalServerErrorException;
use Gravitycar\Exceptions\NavigationBuilderException;

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