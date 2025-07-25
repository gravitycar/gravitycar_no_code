<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Gravitycar\Core\GCException;
use Gravitycar\Core\Installer;
use Gravitycar\Api\ApiController;
use Gravitycar\Core\Config;

// Set up error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: application/json');

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Check if installation is required
    $installer = new Installer();
    if ($installer->checkInstallationRequired()) {
        // Handle installation requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'install') {
            $input = json_decode(file_get_contents('php://input'), true);
            $dbCredentials = $input['database'] ?? [];
            $adminUsername = $input['admin_username'] ?? 'admin';

            $result = $installer->install($dbCredentials, $adminUsername);
            echo json_encode($result);
            exit;
        }

        // Return installation required response
        echo json_encode([
            'success' => false,
            'installation_required' => true,
            'message' => 'Framework installation required',
            'status' => $installer->getInstallationStatus()
        ]);
        exit;
    }

    // Parse request
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $pathInfo = parse_url($requestUri, PHP_URL_PATH);

    // Remove /api prefix if present
    $pathInfo = preg_replace('/^\/api/', '', $pathInfo);
    $pathParts = array_filter(explode('/', $pathInfo));

    if (empty($pathParts)) {
        throw new GCException('API endpoint required', 400);
    }

    $endpoint = array_shift($pathParts);
    $params = [];

    // Extract ID from path if present
    if (!empty($pathParts) && is_numeric($pathParts[0])) {
        $params['id'] = (int) array_shift($pathParts);
    }

    // Add query parameters
    $params = array_merge($params, $_GET);

    // Get request body for POST/PUT requests
    $requestData = [];
    if (in_array($requestMethod, ['POST', 'PUT'])) {
        $input = file_get_contents('php://input');
        $requestData = json_decode($input, true) ?? [];
    }

    // Route to appropriate controller
    $result = routeRequest($endpoint, $requestMethod, $params, $requestData);

    echo json_encode($result);

} catch (GCException $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getFormattedMessage(),
        'context' => $e->getContext(),
        'metadata' => $e->getMetadata()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}

function routeRequest(string $endpoint, string $method, array $params, array $data): array
{
    // Map endpoints to model classes
    $endpointMap = [
        'users' => 'Gravitycar\\Models\\User',
        'movies' => 'Gravitycar\\Models\\Movie',
        'movie_quotes' => 'Gravitycar\\Models\\MovieQuote'
    ];

    if (!isset($endpointMap[$endpoint])) {
        throw new GCException("Unknown endpoint: {$endpoint}", 404);
    }

    $modelClass = $endpointMap[$endpoint];

    if (!class_exists($modelClass)) {
        throw new GCException("Model class not found: {$modelClass}", 500);
    }

    // Create and use API controller
    $controller = new class($modelClass) extends ApiController {
        public function __construct(string $modelClass) {
            parent::__construct($modelClass);
        }
    };

    return $controller->handleRequest($method, $params, $data);
}
