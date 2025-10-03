<?php
namespace Gravitycar\Services;

use Gravitycar\Services\AuthorizationService;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\ModelBase;
use Gravitycar\Api\Request;
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
    private LoggerInterface $logger;
    private array $permissionCache = [];
    private ?ModelBase $testUser = null;
    
    public function __construct(
        AuthorizationService $authorizationService,
        ModelFactory $modelFactory,
        LoggerInterface $logger
    ) {
        $this->authorizationService = $authorizationService;
        $this->modelFactory = $modelFactory;
        $this->logger = $logger;
    }
    
    /**
     * Check if route is accessible to users with 'user' role
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
                    'test_user' => $testUser->get('email')
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
        
        // Parse path and add example values for dynamic segments
        for ($i = 0; $i < count($pathParts); $i++) {
            if (!empty($parameterNames[$i])) {
                // For dynamic path segments, use example values
                if (str_contains($pathParts[$i], '{')) {
                    $requestData[$parameterNames[$i]] = 'example-value';
                } else {
                    $requestData[$parameterNames[$i]] = $pathParts[$i];
                }
            }
        }
        
        return new Request($url, $parameterNames, $method, $requestData);
    }
}
