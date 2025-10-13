<?php
namespace Gravitycar\Services;

use Gravitycar\Services\AuthorizationService;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Factories\APIControllerFactory;
use Gravitycar\Api\APIRouteRegistry;
use Gravitycar\Api\APIPathScorer;
use Gravitycar\Models\ModelBase;
use Gravitycar\Api\Request;
use Gravitycar\Exceptions\GCException;
use Psr\Log\LoggerInterface;

/**
 * OpenAPIPermissionFilter: Filters routes based on user permissions
 * 
 * Tests each route against a 'user' role test user (jane@example.com)
 * to determine which routes should be included in OpenAPI documentation.
 */
class OpenAPIPermissionFilter {
    private AuthorizationService $authorizationService;
    private ModelFactory $modelFactory;
    private APIControllerFactory $apiControllerFactory;
    private APIRouteRegistry $routeRegistry;
    private APIPathScorer $pathScorer;
    private LoggerInterface $logger;
    private array $permissionCache = [];
    private ?ModelBase $testUser = null;
    
    public function __construct(
        AuthorizationService $authorizationService,
        ModelFactory $modelFactory,
        APIControllerFactory $apiControllerFactory,
        APIRouteRegistry $routeRegistry,
        APIPathScorer $pathScorer,
        LoggerInterface $logger
    ) {
        $this->authorizationService = $authorizationService;
        $this->modelFactory = $modelFactory;
        $this->apiControllerFactory = $apiControllerFactory;
        $this->routeRegistry = $routeRegistry;
        $this->pathScorer = $pathScorer;
        $this->logger = $logger;
    }
    
    /**
     * Check if route is accessible to users with 'user' role
     * 
     * For relationship routes, checks permissions on BOTH primary and related models.
     * 
     * @param array $route Route definition with path, method, apiClass, etc.
     * @return bool True if route should be included in OpenAPI docs
     */
    public function isRouteAccessibleToUsers(array $route): bool {
        // Generate cache key from route
        $cacheKey = $route['method'] . ':' . $route['path'];
        
        // Check cache first
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }
        
        try {
            $testUser = $this->getTestUser();
            $request = $this->createTestRequest($route);

            // we have to do all of this to get the apiControllerClassName set on the request so the permissions check will identify Model requests correctly.
            $pathLength = count(explode('/', trim($route['path'], '/')));
            $candidateRoutes = $this->routeRegistry->getRoutesByMethodAndLength($route['method'], $pathLength);
            $bestRoute = $this->pathScorer->findBestMatch($route['method'], $route['path'], $candidateRoutes);

            if (empty($bestRoute['controllerDependencies'])) {
                throw new GCException('No controller dependencies found for route ' . $route['path'] . ' during permission check', ['route' => $route]);
            }


            $controller = $this->apiControllerFactory->createControllerWithDependencyList(
                $bestRoute['apiClass'],
                $bestRoute['controllerDependencies'],
            );
            $request->setApiControllerClassName(get_class($controller));

            /*
            // Check if this is a relationship route requiring dual permission check
            if ($this->isRelationshipRoute($route['path'])) {
                $hasPermission = $this->checkRelationshipRoutePermissions($route, $request, $testUser);
            } else {
                // Standard single-model permission check
                $hasPermission = $this->authorizationService->hasPermissionForRoute(
                    $route,
                    $request,
                    $testUser
                );
            }
            */

            // Standard single-model permission check
            $hasPermission = $this->authorizationService->hasPermissionForRoute(
                $route,
                $request,
                $testUser
            );

            if (!$hasPermission) {
                // This is EXPECTED - user doesn't have permission
                $this->logger->debug('Route excluded from documentation - user lacks permission', [
                    'route' => $route['path'] ?? 'unknown',
                    'method' => $route['method'] ?? 'unknown',
                    'test_user' => $testUser->get('email'),
                    'is_relationship_route' => $this->isRelationshipRoute($route['path'])
                ]);
            }
            
            // Cache the result
            $this->permissionCache[$cacheKey] = $hasPermission;
            
            return $hasPermission;
            
        } catch (\Exception $e) {
            // This is an ERROR - something went wrong during permission checking
            $this->logger->error('Permission check failed with exception - aborting documentation generation', [
                'route' => $route['path'] ?? 'unknown',
                'method' => $route['method'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw to fail OpenAPI generation with 500 error
            throw new \RuntimeException(
                'OpenAPI generation failed during permission checking: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }
    
    /**
     * Get test user with 'user' role (jane@example.com)
     * 
     * @return ModelBase The test user
     * @throws \RuntimeException If test user not found
     */
    private function getTestUser(): ModelBase {
        if ($this->testUser === null) {
            // Retrieve jane@example.com user that has 'user' role
            $userModel = $this->modelFactory->new('Users');
            $users = $userModel->find(['email' => 'jane@example.com']);
            
            if (empty($users)) {
                throw new \RuntimeException('Test user jane@example.com not found. Run setup.php to create test data.');
            }
            
            $this->testUser = $users[0];
        }
        
        return $this->testUser;
    }
    
    /**
     * Create test Request object for permission checking
     * 
     * @param array $route Route definition
     * @return Request Test request object
     */
    private function createTestRequest(array $route): Request {
        $url = $route['path'];
        $method = $route['method'];
        
        // Build parameterNames array from path components
        $pathParts = explode('/', trim($url, '/'));
        $parameterNames = $route['parameterNames'] ?? [];
        
        // If no parameterNames provided, infer from path
        if (empty($parameterNames) && !empty($pathParts)) {
            // Check if first segment looks like a model name (starts with uppercase)
            $firstSegment = $pathParts[0] ?? '';
            if ($route['apiClass'] === 'Gravitycar\\Api\\ModelBaseAPIController') {
                // Model route - first segment is modelName
                $parameterNames = ['modelName'];
                // Additional segments
                for ($i = 1; $i < count($pathParts); $i++) {
                    if (str_contains($pathParts[$i], '{')) {
                        // Parameter placeholder - extract name
                        $parameterNames[] = trim($pathParts[$i], '{}');
                    } else {
                        // Literal segment
                        $parameterNames[] = '';
                    }
                }
            } else {
                // Non-model route (auth, metadata, etc.) - use empty parameterNames
                $parameterNames = array_fill(0, count($pathParts), '');
            }
        }
        
        // Ensure parameterNames matches path components count
        if (count($parameterNames) < count($pathParts)) {
            // Pad with empty strings
            $parameterNames = array_pad($parameterNames, count($pathParts), '');
        } elseif (count($parameterNames) > count($pathParts)) {
            // Trim excess
            $parameterNames = array_slice($parameterNames, 0, count($pathParts));
        }
        
        $requestData = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $url,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '8081'
        ];
        
        // Parse path and add values for parameters
        for ($i = 0; $i < count($pathParts); $i++) {
            if (!empty($parameterNames[$i])) {
                // For dynamic path segments, use example values
                if (str_contains($pathParts[$i], '{')) {
                    $requestData[$parameterNames[$i]] = 'example-value';
                } else {
                    // Use the actual value from the path
                    $requestData[$parameterNames[$i]] = $pathParts[$i];
                }
            }
        }
        
        return new Request($url, $parameterNames, $method, $requestData);
    }
    
    /**
     * Check if route is a relationship route with a specific relationship name
     * 
     * Returns false for generic relationship templates like /link/{relationshipName}
     */
    private function isRelationshipRoute(string $path): bool {
        // Must contain /link/
        if (!str_contains($path, '/link/')) {
            return false;
        }
        
        // Skip generic templates with {relationshipName} placeholder
        if (str_contains($path, '{relationshipName}')) {
            return false;
        }
        
        // This is a specific relationship route
        return true;
    }
    
    /**
     * Check permissions for relationship routes (requires both primary and related model access)
     */
    private function checkRelationshipRoutePermissions(array $route, Request $request, ModelBase $testUser): bool {
        $path = $route['path'];
        $method = $route['method'];
        
        // Extract model name from path (first segment)
        if (!preg_match('#^/([^/]+)#', $path, $matches)) {
            $this->logger->warning('Cannot extract model name from relationship route', [
                'path' => $path
            ]);
            return false;
        }
        $modelName = $matches[1];
        
        // Extract relationship name from path
        // Pattern: /{model}/{id}/link/{relationshipName}[/{idToLink}]
        if (!preg_match('#/link/([^/]+)#', $path, $matches)) {
            $this->logger->warning('Cannot extract relationship name from route', [
                'path' => $path
            ]);
            return false;
        }
        $relationshipName = $matches[1];
        
        // Get related model from relationship metadata
        $relatedModelName = $this->getRelatedModelFromRelationship($modelName, $relationshipName);
        
        if (!$relatedModelName) {
            $this->logger->warning('Cannot determine related model for relationship route', [
                'model' => $modelName,
                'relationship' => $relationshipName,
                'route' => $path
            ]);
            return false; // NO FALLBACK - exclude route if we can't determine permissions
        }
        
        // Determine required actions for primary and related models
        [$primaryAction, $relatedAction] = $this->determineRelationshipActions($route);
        
        // Create test requests for both models
        $primaryRequest = $this->createModelTestRequest($modelName, $primaryAction);
        $relatedRequest = $this->createModelTestRequest($relatedModelName, $relatedAction);
        
        // Check permissions for BOTH models
        $hasPrimaryPermission = $this->authorizationService->hasPermissionForRoute(
            ['apiClass' => 'Gravitycar\\Api\\ModelBaseAPIController'],
            $primaryRequest,
            $testUser
        );
        
        $hasRelatedPermission = $this->authorizationService->hasPermissionForRoute(
            ['apiClass' => 'Gravitycar\\Api\\ModelBaseAPIController'],
            $relatedRequest,
            $testUser
        );
        
        $this->logger->debug('Relationship route permission check', [
            'primary_model' => $modelName,
            'primary_action' => $primaryAction,
            'primary_permission' => $hasPrimaryPermission ? 'GRANTED' : 'DENIED',
            'related_model' => $relatedModelName,
            'related_action' => $relatedAction,
            'related_permission' => $hasRelatedPermission ? 'GRANTED' : 'DENIED',
            'route_accessible' => $hasPrimaryPermission && $hasRelatedPermission ? 'YES' : 'NO'
        ]);
        
        // Both permissions must be granted - NO FALLBACKS
        return $hasPrimaryPermission && $hasRelatedPermission;
    }
    
    /**
     * Determine required actions for relationship routes
     * 
     * @return array [primaryAction, relatedAction]
     */
    private function determineRelationshipActions(array $route): array {
        $method = $route['method'];
        $path = $route['path'];
        
        // Count path segments to determine pattern
        // Pattern 1: /{model}/{id}/link/{relationshipName} = 4 segments
        // Pattern 2: /{model}/{id}/link/{relationshipName}/{idToLink} = 5 segments
        $pathSegments = array_filter(explode('/', $path)); // Remove empty segments
        $segmentCount = count($pathSegments);
        
        // Pattern: /{model}/{id}/link/{relationshipName} (4 segments)
        if ($segmentCount === 4) {
            if ($method === 'GET') {
                return ['read', 'list']; // listRelated
            }
            if ($method === 'POST') {
                return ['read', 'create']; // createAndLink
            }
        }
        
        // Pattern: /{model}/{id}/link/{relationshipName}/{idToLink} (5 segments)
        if ($segmentCount === 5) {
            if ($method === 'PUT') {
                return ['update', 'read']; // link
            }
            if ($method === 'DELETE') {
                return ['update', 'read']; // unlink
            }
        }
        
        // Should never reach here for relationship routes
        $this->logger->warning('Could not determine relationship actions for route', [
            'path' => $path,
            'method' => $method,
            'segment_count' => $segmentCount
        ]);
        return ['read', 'read']; // Safe default
    }
    
    /**
     * Get related model name from relationship metadata
     */
    private function getRelatedModelFromRelationship(string $modelName, string $relationshipName): ?string {
        try {
            $model = $this->modelFactory->new($modelName);
            
            // Check if relationship exists
            $relationship = $model->getRelationship($relationshipName);
            if (!$relationship) {
                $this->logger->debug('Relationship not found on model', [
                    'model' => $modelName,
                    'relationship' => $relationshipName,
                    'available_relationships' => array_keys($model->getRelationships())
                ]);
                return null;
            }
            
            return $relationship->getOtherModel($model)->getName();  
        } catch (\Exception $e) {
            $this->logger->error('Failed to get related model from relationship', [
                'model' => $modelName,
                'relationship' => $relationshipName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Create a test request for model-specific permission checking
     */
    private function createModelTestRequest(string $modelName, string $action): Request {
        $path = "/{$modelName}";
        $method = 'GET';
        
        // Map action to HTTP method and path
        switch ($action) {
            case 'list':
                $method = 'GET';
                $path = "/{$modelName}";
                break;
            case 'read':
                $method = 'GET';
                $path = "/{$modelName}/example-id";
                break;
            case 'create':
                $method = 'POST';
                $path = "/{$modelName}";
                break;
            case 'update':
                $method = 'PUT';
                $path = "/{$modelName}/example-id";
                break;
            case 'delete':
                $method = 'DELETE';
                $path = "/{$modelName}/example-id";
                break;
        }
        
        $pathParts = explode('/', trim($path, '/'));
        
        // Build parameterNames - first segment is always modelName
        $parameterNames = ['modelName'];
        if (count($pathParts) > 1) {
            $parameterNames[] = 'id';
        }
        
        // Ensure parameterNames matches path parts count
        $parameterNames = array_pad($parameterNames, count($pathParts), '');
        
        $requestData = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '8081',
            'modelName' => $modelName  // CRITICAL: Set modelName for AuthorizationService
        ];
        
        // Add ID if present
        if (count($pathParts) > 1) {
            $requestData['id'] = $pathParts[1];
        }
        
        return new Request($path, $parameterNames, $method, $requestData);
    }
}
