<?php
/**
 * REST API Entry Point for Gravitycar Framework
 * 
 * This file serves as the main entry point for REST API requests.
 * It uses the RestApiHandler class to bootstrap the Gravitycar application,
 * extract route information, and delegate routing to the Router class.
 * 
 * Supported URL patterns:
 * - GET /Users → List all users
 * - PUT /Movies/abc-123 → Update movie with ID abc-123
 * - POST /Books → Create a new book
 * - DELETE /Articles/xyz-789 → Delete article with ID xyz-789
 */

// Prevent direct access when included
if (!defined('GRAVITYCAR_REST_API')) {
    define('GRAVITYCAR_REST_API', true);
}

// Include Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

use Gravitycar\Api\RestApiHandler;

// Only run if accessed directly (not included)
if (!isset($GLOBALS['GRAVITYCAR_TEST_MODE'])) {
    $handler = new RestApiHandler();
    $handler->handleRequest();
}
