<?php
namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Exceptions\UnauthorizedException;
use Gravitycar\Exceptions\ForbiddenException;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Services\AuthorizationService;
use Monolog\Logger;

/**
 * API Router: Routes API requests to appropriate handlers
 */
class Router {
    /** @var APIRouteRegistry */
    protected APIRouteRegistry $routeRegistry;
    /** @var Logger */
    protected Logger $logger;
    /** @var APIPathScorer */
    protected APIPathScorer $pathScorer;
    /** @var \Gravitycar\Metadata\MetadataEngine */
    protected $metadataEngine;

    public function __construct($serviceLocator) {
        if ($serviceLocator instanceof ServiceLocator) {
            $this->logger = $serviceLocator->get('logger');
            $this->metadataEngine = $serviceLocator->get('metadataEngine');
        } else {
            // Backward compatibility - assume it's MetadataEngine for old constructor
            $this->metadataEngine = $serviceLocator;
            $this->logger = ServiceLocator::getLogger();
        }
        
        $this->routeRegistry = new APIRouteRegistry($this->logger);
        $this->pathScorer = new APIPathScorer($this->logger);
    }

    /**
     * Route an API request to the correct controller and handler
     */
    public function route(string $method, string $path, array $requestData = []): mixed {
        // 1. Get routes from registry grouped by method and path length
        $pathLength = count($this->parsePathComponents($path));
        $candidateRoutes = $this->routeRegistry->getRoutesByMethodAndLength($method, $pathLength);
        
        // 2. Use APIPathScorer to find best match
        $bestRoute = null;
        if (!empty($candidateRoutes)) {
            $bestRoute = $this->pathScorer->findBestMatch($method, $path, $candidateRoutes);
        }
        
        // 3. If no exact length match, try other lengths for wildcard matching
        if (!$bestRoute) {
            $bestRoute = $this->findMatchingRoute($method, $path);
        }
        
        if (!$bestRoute) {
            $allRoutes = $this->routeRegistry->getRoutes();
            throw new GCException("No matching route found for $method $path", [
                'method' => $method, 
                'path' => $path, 
                'available_routes' => array_map(function($route) {
                    return $route['method'] . ' ' . $route['path'];
                }, array_slice($allRoutes, 0, 10))
            ]);
        }
        
        // 4. Create enhanced Request object with request data
        $request = new Request($path, $bestRoute['parameterNames'], $method, $requestData);
        
        // 5. Attach request helpers and perform validation
        $this->attachRequestHelpers($request);
        
        // 6. Execute route with enhanced Request object
        return $this->executeRoute($bestRoute, $request);
    }

    /**
     * Handle incoming HTTP request
     */
    public function handleRequest(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Get request data (query params, POST data, JSON body)
        $requestData = $this->getRequestParams();

        $this->logger->info("Routing request: $method $path");

        try {
            // Use new route() method with requestData
            $result = $this->route($method, $path, $requestData);

            // Only output if not in CLI/test mode
            if (php_sapi_name() !== 'cli') {
                // Set content type and output result
                header('Content-Type: application/json');
                echo json_encode($result);
            }

        } catch (GCException $e) {
            $this->logger->error("Routing error: " . $e->getMessage());

            // Only output if not in CLI/test mode
            if (php_sapi_name() !== 'cli') {
                http_response_code(404);
                echo json_encode(['error' => 'Route not found', 'message' => $e->getMessage()]);
            }

            // Re-throw the exception for proper error handling in tests
            throw $e;
        }
    }

    /**
     * Find matching route using all available path lengths
     */
    protected function findMatchingRoute(string $method, string $path): ?array {
        $pathLength = count($this->parsePathComponents($path));
        
        // Try other path lengths for wildcard matching
        $allMethodRoutes = $this->routeRegistry->getGroupedRoutes()[$method] ?? [];
        foreach ($allMethodRoutes as $length => $routes) {
            if ($length !== $pathLength) {
                $bestRoute = $this->pathScorer->findBestMatch($method, $path, $routes);
                if ($bestRoute) {
                    return $bestRoute;
                }
            }
        }
        
        return null;
    }

    /**
     * Execute route with enhanced Request object
     */
    protected function executeRoute(array $route, Request $request): mixed {
        $controllerClass = $route['apiClass'];
        $handlerMethod = $route['apiMethod'];
        
        if (!class_exists($controllerClass)) {
            throw new GCException("API controller class not found: $controllerClass", [
                'controller_class' => $controllerClass,
                'route' => $route['path']
            ]);
        }
        
        $controller = new $controllerClass($this->logger);
        
        if (!method_exists($controller, $handlerMethod)) {
            throw new GCException("Handler method not found: $handlerMethod in $controllerClass", [
                'handler_method' => $handlerMethod,
                'controller_class' => $controllerClass
            ]);
        }
        
        // Authentication and authorization middleware
        $this->handleAuthentication($route, $request);
        
        // Validate Request parameters
        $this->validateRequestParameters($request, $route);
        
        // Call controller method with enhanced Request object (no additionalParams)
        return $controller->$handlerMethod($request);
    }

    /**
     * Attach helper classes to Request object and perform parameter parsing/validation
     */
    protected function attachRequestHelpers(Request $request): void {
        try {
            // 1. Initialize and attach parameter parser
            $parameterParser = new RequestParameterParser();
            $request->setParameterParser($parameterParser);
            
            // 2. Initialize filter criteria and search engine helpers
            $filterCriteria = new FilterCriteria();
            $searchEngine = new SearchEngine();
            $request->setFilterCriteria($filterCriteria);
            $request->setSearchEngine($searchEngine);
            
            // 3. Initialize and attach response formatter
            $responseFormatter = new ResponseFormatter();
            $request->setResponseFormatter($responseFormatter);
            
            // 4. Parse request parameters using format detection
            $parsedParams = $parameterParser->parseUnified($request->getRequestData());
            $request->setParsedParams($parsedParams);
            
            // 5. Attempt model-aware validation if model can be determined
            $model = $this->getModel($request);
            if ($model) {
                $validatedParams = $this->performValidationWithModel($request, $model, $parsedParams);
                $request->setValidatedParams($validatedParams);
            } else {
                // No model available - set empty validated params (graceful fallback)
                $request->setValidatedParams([
                    'filters' => [],
                    'search' => [],
                    'sorting' => [],
                    'pagination' => $parsedParams['pagination'] ?? []
                ]);
            }
            
            $this->logger->debug('Request helpers attached successfully', [
                'has_model' => $model !== null,
                'detected_format' => $parsedParams['responseFormat'] ?? 'unknown',
                'filters_count' => count($parsedParams['filters'] ?? []),
                'sorts_count' => count($parsedParams['sorting'] ?? [])
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to attach request helpers', [
                'error' => $e->getMessage(),
                'request_url' => $request->getUrl(),
                'request_method' => $request->getMethod()
            ]);
            
            // Re-throw as ParameterValidationException for consistent error handling
            if ($e instanceof ParameterValidationException) {
                throw $e;
            }
            
            $validationException = new ParameterValidationException(
                'Request parameter processing failed', 
                [['field' => 'general', 'error' => $e->getMessage(), 'value' => null]]
            );
            $validationException->addSuggestion('Check parameter format and try again');
            throw $validationException;
        }
    }

    /**
     * Safely get model instance from request parameters
     */
    protected function getModel(Request $request): ?\Gravitycar\Models\ModelBase {
        try {
            $modelName = $request->get('modelName');
            if (!$modelName) {
                return null; // No model parameter available
            }
            
            return \Gravitycar\Factories\ModelFactory::new($modelName);
            
        } catch (\Exception $e) {
            $this->logger->warning('Could not instantiate model for validation', [
                'model_name' => $request->get('modelName'),
                'error' => $e->getMessage()
            ]);
            return null; // Graceful fallback
        }
    }

    /**
     * Perform comprehensive validation with model context
     */
    protected function performValidationWithModel(Request $request, \Gravitycar\Models\ModelBase $model, array $parsedParams): array {
        $validationException = new ParameterValidationException();
        $validatedParams = [];
        
        // Get helper instances from request
        $filterCriteria = $request->getFilterCriteria();
        $searchEngine = $request->getSearchEngine();
        
        // Validate filters against model
        try {
            $validatedFilters = [];
            if (!empty($parsedParams['filters']) && $filterCriteria) {
                $validatedFilters = $filterCriteria->validateAndFilterForModel($parsedParams['filters'], $model);
            }
            $validatedParams['filters'] = $validatedFilters;
        } catch (\Exception $e) {
            $validationException->addError('filters', 'Filter validation failed: ' . $e->getMessage());
            $validatedParams['filters'] = [];
        }
        
        // Validate search against model
        try {
            $validatedSearch = [];
            if (!empty($parsedParams['search']) && $searchEngine) {
                $validatedSearch = $searchEngine->validateSearchForModel($parsedParams['search'], $model);
            }
            $validatedParams['search'] = $validatedSearch;
        } catch (\Exception $e) {
            $validationException->addError('search', 'Search validation failed: ' . $e->getMessage());
            $validatedParams['search'] = [];
        }
        
        // Validate sorting against model (simple validation - check fields exist and are DB fields)
        try {
            $validatedSorting = [];
            if (!empty($parsedParams['sorting'])) {
                $modelFields = $model->getFields();
                foreach ($parsedParams['sorting'] as $sort) {
                    $fieldName = $sort['field'] ?? '';
                    
                    if (isset($modelFields[$fieldName]) && $modelFields[$fieldName]->isDBField()) {
                        $validatedSorting[] = [
                            'field' => $fieldName,
                            'direction' => in_array(strtolower($sort['direction'] ?? 'asc'), ['asc', 'desc']) 
                                ? strtolower($sort['direction']) 
                                : 'asc',
                            'priority' => $sort['priority'] ?? 0
                        ];
                    } else {
                        $this->logger->warning('Sort field validation failed', [
                            'field' => $fieldName,
                            'exists' => isset($modelFields[$fieldName]),
                            'is_db_field' => isset($modelFields[$fieldName]) ? $modelFields[$fieldName]->isDBField() : false
                        ]);
                    }
                }
            }
            $validatedParams['sorting'] = $validatedSorting;
        } catch (\Exception $e) {
            $validationException->addError('sorting', 'Sorting validation failed: ' . $e->getMessage());
            $validatedParams['sorting'] = [];
        }
        
        // Pagination validation (basic - just ensure reasonable values)
        try {
            $pagination = $parsedParams['pagination'] ?? [];
            $validatedParams['pagination'] = [
                'page' => max(1, (int) ($pagination['page'] ?? 1)),
                'pageSize' => min(1000, max(1, (int) ($pagination['pageSize'] ?? 20))),
                'offset' => max(0, (int) ($pagination['offset'] ?? 0))
            ];
        } catch (\Exception $e) {
            $validationException->addError('pagination', 'Pagination validation failed: ' . $e->getMessage());
            $validatedParams['pagination'] = ['page' => 1, 'pageSize' => 20, 'offset' => 0];
        }
        
        // If we collected any validation errors, throw them
        if ($validationException->hasErrors()) {
            throw $validationException;
        }
        
        $this->logger->info('Model validation completed successfully', [
            'model' => get_class($model),
            'validated_filters' => count($validatedParams['filters']),
            'validated_search_fields' => count($validatedParams['search']['fields'] ?? []),
            'validated_sorts' => count($validatedParams['sorting']),
            'pagination' => $validatedParams['pagination']
        ]);
        
        return $validatedParams;
    }

    /**
     * Handle authentication and authorization for the route
     */
    protected function handleAuthentication(array $route, Request $request): void {
        // Check if route requires authentication
        $allowedRoles = $route['allowedRoles'] ?? null;
        
        // Public routes (no authentication required)
        if ($allowedRoles === null || in_array('*', $allowedRoles) || in_array('all', $allowedRoles)) {
            return;
        }
        
        try {
            // Get current user from JWT token
            $currentUser = ServiceLocator::getCurrentUser();
            
            if (!$currentUser) {
                throw new UnauthorizedException('Authentication required', [
                    'route' => $route['path'],
                    'method' => $request->getMethod()
                ]);
            }
            
            // Check if user has required role
            $authorizationService = ServiceLocator::getAuthorizationService();
            $hasRequiredRole = false;
            
            foreach ($allowedRoles as $role) {
                if ($authorizationService->hasRole($currentUser, $role)) {
                    $hasRequiredRole = true;
                    break;
                }
            }
            
            if (!$hasRequiredRole) {
                throw new ForbiddenException('Insufficient permissions', [
                    'route' => $route['path'],
                    'required_roles' => $allowedRoles,
                    'user_id' => $currentUser->get('id')
                ]);
            }
            
            // Additional permission checking for model-based routes
            $this->checkModelPermissions($route, $request, $currentUser);
            
        } catch (UnauthorizedException | ForbiddenException $e) {
            // Re-throw authentication/authorization exceptions
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Authentication error: ' . $e->getMessage());
            throw new UnauthorizedException('Authentication failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check model-specific permissions for CRUD operations
     */
    protected function checkModelPermissions(array $route, Request $request, $user): void {
        // Extract model name from route path or controller class
        $modelName = $this->extractModelName($route);
        
        if (!$modelName) {
            return; // No model-specific permissions needed
        }
        
        // Map HTTP methods to actions
        $method = $request->getMethod();
        $action = $this->mapMethodToAction($method, $route['path']);
        
        if ($action) {
            $authorizationService = ServiceLocator::getAuthorizationService();
            
            if (!$authorizationService->hasPermission($action, $modelName)) {
                throw new ForbiddenException("Insufficient permissions for $action on $modelName", [
                    'action' => $action,
                    'model' => $modelName,
                    'user_id' => $user->get('id')
                ]);
            }
        }
    }

    /**
     * Extract model name from route information
     */
    protected function extractModelName(array $route): ?string {
        // Check if controller is ModelBaseAPIController
        $controllerClass = $route['apiClass'];
        
        if (strpos($controllerClass, 'ModelBaseAPIController') !== false) {
            // Extract model name from path (e.g., /api/users -> Users)
            $pathComponents = $this->parsePathComponents($route['path']);
            if (count($pathComponents) >= 2 && $pathComponents[0] === 'api') {
                return ucfirst($pathComponents[1]); // users -> Users
            }
        }
        
        return null;
    }

    /**
     * Map HTTP method to permission action
     */
    protected function mapMethodToAction(string $method, string $path): ?string {
        switch (strtoupper($method)) {
            case 'GET':
                // Check if it's a list or read operation
                return $this->isListOperation($path) ? 'list' : 'read';
            case 'POST':
                return 'create';
            case 'PUT':
            case 'PATCH':
                return 'update';
            case 'DELETE':
                return 'delete';
            default:
                return null;
        }
    }

    /**
     * Determine if GET operation is a list or single record read
     */
    protected function isListOperation(string $path): bool {
        $pathComponents = $this->parsePathComponents($path);
        
        // If path ends with model name (no ID), it's a list operation
        // e.g., /api/users (list) vs /api/users/123 (read)
        return count($pathComponents) === 2 && $pathComponents[0] === 'api';
    }

    /**
     * Validate Request object has required parameters
     */
    protected function validateRequestParameters(Request $request, array $route): void {
        $expectedParams = array_filter($route['parameterNames']); // Remove empty parameter names
        
        foreach ($expectedParams as $paramName) {
            if (!$request->has($paramName)) {
                throw new GCException("Missing required route parameter: $paramName", [
                    'route' => $route['path'],
                    'expected_params' => $expectedParams,
                    'available_params' => array_keys($request->all())
                ]);
            }
        }
    }

    /**
     * Get request parameters from various sources
     */
    protected function getRequestParams(): array {
        $params = [];

        // GET parameters
        $params = array_merge($params, $_GET);

        // POST/PUT/PATCH body
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            $jsonData = json_decode($input, true);
            if ($jsonData) {
                $params = array_merge($params, $jsonData);
            } else {
                $params = array_merge($params, $_POST);
            }
        }

        return $params;
    }

    /**
     * Parse a path string into components
     */
    protected function parsePathComponents(string $path): array {
        if (empty($path) || $path === '/') {
            return [];
        }

        // Remove leading and trailing slashes, then split
        $path = trim($path, '/');
        return explode('/', $path);
    }
}
