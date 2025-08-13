<?php
namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * Abstract base class for all API controllers in Gravitycar.
 * Handles CRUD operations, request validation, and response formatting.
 */
abstract class ApiControllerBase {
    /** @var Logger */
    protected Logger $logger;
    /** @var array */
    protected array $metadata;

    public function __construct(array $metadata) {
        $this->metadata = $metadata;
        $this->logger = ServiceLocator::getLogger();
    }

    /**
     * Register all routes for this API controller
     * @return array
     */
    abstract public function registerRoutes(): array;

    /**
     * Handle GET requests (list, detail)
     */
    abstract public function get($id = null);

    /**
     * Handle POST requests (create)
     */
    abstract public function post(array $data);

    /**
     * Handle PUT requests (update)
     */
    abstract public function put($id, array $data);

    /**
     * Handle DELETE requests (soft delete)
     */
    abstract public function delete($id);

    /**
     * Format API response as JSON
     */
    protected function jsonResponse($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
