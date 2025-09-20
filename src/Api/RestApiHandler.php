<?php
namespace Gravitycar\Api;

use Gravitycar\Core\Gravitycar;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Api\Router;
use Gravitycar\Api\Request;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Exceptions\APIException;
use Monolog\Logger;
use Exception;
use Throwable;

/**
 * REST API Handler Class
 * 
 * Handles incoming REST API requests by bootstrapping the Gravitycar framework,
 * extracting request information, routing requests through the Router class,
 * and returning ReactJS-friendly JSON responses.
 * 
 * This class serves as the main orchestrator for the REST API web server integration,
 * providing a clean separation between the web entry point and the API logic.
 */
class RestApiHandler {
    /** @var Logger|null Application logger instance */
    private ?Logger $logger = null;
    
    /** @var Router|null API router instance for request routing */
    private ?Router $router = null;
    
    /** @var Gravitycar|null Main application instance */
    private ?Gravitycar $app = null;

    /**
     * Handle the incoming REST API request
     * 
     * Main entry point that orchestrates the entire request lifecycle:
     * 1. Bootstrap the Gravitycar application
     * 2. Extract request information from HTTP environment
     * 3. Route the request through the Router class
     * 4. Send ReactJS-friendly JSON response
     * 
     * @throws GCException For Gravitycar-specific errors
     * @throws Exception For unexpected errors
     * @throws Throwable For fatal errors
     */
    public function handleRequest(): void {
        try {
            // Handle CORS preflight requests early, before routing
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
                #header('Access-Control-Allow-Origin: *');
                #header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
                #header('Access-Control-Allow-Headers: Content-Type, Authorization');
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(200);
                exit;
            }

            // 1. Bootstrap the Gravitycar application
            $this->bootstrapApplication();

            // 2. Extract request information
            $requestInfo = $this->extractRequestInfo();

            // 3. Route the request
            $result = $this->routeRequest($requestInfo);

            // 4. Send JSON response
            $this->sendJsonResponse($result);

        } catch (APIException $e) {
            $this->handleError($e, 'API Error');
        } catch (GCException $e) {
            $this->handleError($e, 'Gravitycar Exception');
        } catch (Exception $e) {
            $this->handleError($e, 'Unexpected Error');
        } catch (Throwable $e) {
            $this->handleError($e, 'Fatal Error');
        }
    }

    /**
     * Bootstrap Gravitycar application with full services
     * 
     * Initializes the complete Gravitycar framework including:
     * - Core services (Config, Logger, Database)
     * - Metadata engine for model introspection
     * - Router for API request handling
     * 
     * @throws GCException If bootstrap fails
     */
    private function bootstrapApplication(): void {
        try {
            // Create and bootstrap Gravitycar application
            $this->app = new Gravitycar();
            $this->app->bootstrap();

            // Get services from ServiceLocator
            $this->logger = ServiceLocator::getLogger();
            $this->router = ServiceLocator::getContainer()->get('router');

            $this->logger->info('REST API: Application bootstrapped successfully');

        } catch (Exception $e) {
            // If we can't get the logger, create a basic one for error reporting
            if (!$this->logger) {
                error_log('REST API Bootstrap Error: ' . $e->getMessage());
            }
            throw new GCException('Failed to bootstrap Gravitycar application', [
                'error' => $e->getMessage()
            ], 0, $e);
        }
    }

    /**
     * Extract request information from HTTP request and environment
     * 
     * Parses the incoming HTTP request to extract:
     * - HTTP method (GET, POST, PUT, DELETE, PATCH)
     * - Request path from URL
     * - Query parameters from URL
     * - Request body for POST/PUT/PATCH (JSON or form data)
     * 
     * @return array Request information array with keys:
     *               - method: HTTP method
     *               - path: Request path
     *               - additionalParams: Combined query and body parameters
     *               - originalPath: Original request path
     * 
     * @throws GCException If the API path is invalid
     */
    private function extractRequestInfo(): array {
        // Get HTTP method
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Get original path from environment variables or REQUEST_URI
        $originalPath = $_SERVER['ORIGINAL_PATH'] ?? $_SERVER['REQUEST_URI'] ?? '/';
        
        // Clean up the path
        $path = parse_url($originalPath, PHP_URL_PATH);
        if (empty($path) || $path === '/') {
            throw new GCException('Invalid API path', ['path' => $originalPath]);
        }

        // Get query parameters
        $queryParams = $_GET ?? [];

        // Get request body for POST/PUT/PATCH
        $requestBody = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $decoded = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $requestBody = $decoded;
                } else {
                    $requestBody = $_POST ?? [];
                }
            } else {
                $requestBody = $_POST ?? [];
            }
        }

        // Combine all parameters
        $additionalParams = array_merge($queryParams, $requestBody);

        $this->logger->info('REST API: Request extracted', [
            'method' => $method,
            'path' => $path,
            'params_count' => count($additionalParams)
        ]);

        return [
            'method' => $method,
            'path' => $path,
            'additionalParams' => $additionalParams,
            'originalPath' => $originalPath
        ];
    }

    /**
     * Route the request using Gravitycar Router
     * 
     * Delegates the request to the Router class which handles:
     * - Route matching and parameter extraction
     * - Controller instantiation and method execution
     * - Model operations through ModelBaseAPIController
     * 
     * @param array $requestInfo Request information from extractRequestInfo()
     * @return array Router result data
     * 
     * @throws GCException If routing fails
     */
    private function routeRequest(array $requestInfo): array {
        $method = $requestInfo['method'];
        $path = $requestInfo['path'];
        $additionalParams = $requestInfo['additionalParams'];

        $this->logger->info('REST API: Routing request', [
            'method' => $method,
            'path' => $path
        ]);

        // Use Router to handle the request
        $result = $this->router->route($method, $path, $additionalParams);

        // Ensure result is in proper format
        if (!is_array($result)) {
            $result = ['data' => $result];
        }

        return $result;
    }

    /**
     * Send ReactJS-friendly JSON response
     * 
     * Formats and sends the API response in a standardized format that's
     * optimized for frontend frameworks like ReactJS:
     * - Consistent success/error structure
     * - Appropriate HTTP headers (CORS, content-type)
     * - Count metadata for array responses
     * - ISO 8601 timestamps
     * 
     * @param array $result Result data from routing
     */
    private function sendJsonResponse(array $result): void {
        // Set appropriate headers
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        #header('Access-Control-Allow-Origin: *'); // Basic CORS support
        #header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        #header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Handle OPTIONS preflight requests
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Wrap response in ReactJS-friendly format
        $response = [
            'success' => true,
            'status' => 200,
            'data' => $result['data'] ?? $result,
            'timestamp' => date('c')
        ];

        // Add count if data is an array
        if (isset($response['data']) && is_array($response['data'])) {
            $response['count'] = count($response['data']);
        }

        // Preserve pagination metadata if present
        if (isset($result['pagination'])) {
            $response['pagination'] = $result['pagination'];
        }

        // Preserve other metadata if present
        if (isset($result['meta']) && is_array($result['meta'])) {
            $response['meta'] = $result['meta'];
        }

        http_response_code(200);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $this->logger->info('REST API: Response sent successfully', [
            'status' => 200,
            'data_type' => gettype($response['data']),
            'has_count' => isset($response['count'])
        ]);
    }

    /**
     * Handle errors and send error response
     * 
     * Provides consistent error handling and response formatting:
     * - Maps exceptions to appropriate HTTP status codes
     * - Logs detailed error information for debugging
     * - Returns ReactJS-friendly error responses
     * - Includes context information when available
     * 
     * @param Throwable $e The exception to handle
     * @param string $errorType Human-readable error type description
     */
    private function handleError(Throwable $e, string $errorType): void {
        // Determine HTTP status code based on exception type
        $httpStatus = 500; // Default to internal server error
        
        if ($e instanceof APIException) {
            // Use the HTTP status code from the API exception
            $httpStatus = $e->getHttpStatusCode();
        } elseif ($e instanceof GCException) {
            // For backward compatibility, map GCException to appropriate status codes
            $httpStatus = 400; // Bad request for framework exceptions
        }

        // Log the error
        if ($this->logger) {
            $this->logger->error('REST API Error: ' . $errorType, [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'http_status' => $httpStatus
            ]);
        } else {
            error_log("REST API Error ($errorType): " . $e->getMessage());
        }

        // Set error headers
        header('Content-Type: application/json; charset=utf-8');
        #header('Access-Control-Allow-Origin: *');
        http_response_code($httpStatus);

        // Create ReactJS-friendly error response
        $errorResponse = [
            'success' => false,
            'status' => $httpStatus,
            'error' => [
                'message' => $e->getMessage(),
                'type' => $this->getErrorTypeName($e),
                'code' => $httpStatus
            ],
            'timestamp' => date('c')
        ];

        // Add context if it's a GCException or APIException
        if ($e instanceof GCException && $e->getContext()) {
            $errorResponse['error']['context'] = $e->getContext();
        }

        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Get a human-readable error type name for the response
     */
    private function getErrorTypeName(Throwable $e): string {
        if ($e instanceof APIException) {
            return $e->getErrorType();
        }
        
        if ($e instanceof GCException) {
            return 'Framework Error';
        }
        
        return 'Internal Error';
    }
}
