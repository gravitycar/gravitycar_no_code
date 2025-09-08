<?php
namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * Abstract base class for all API controllers in Gravitycar.
 * Handles common functionality, request validation, and response formatting.
 */
abstract class ApiControllerBase {
    /** @var Logger */
    protected Logger $logger;

    public function __construct() {
        $this->logger = ServiceLocator::getLogger();
    }

    /**
     * Register all routes for this API controller
     * @return array
     */
    abstract public function registerRoutes(): array;
}
