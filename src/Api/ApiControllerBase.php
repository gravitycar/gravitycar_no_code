<?php
namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Core\Config;
use Monolog\Logger;

/**
 * Abstract base class for all API controllers in Gravitycar.
 * Handles common functionality, request validation, and response formatting.
 * Phase 15: Enhanced with dependency injection support
 */
abstract class ApiControllerBase {
    protected Logger $logger;
    protected ModelFactory $modelFactory;
    protected DatabaseConnectorInterface $databaseConnector;
    protected MetadataEngineInterface $metadataEngine;
    protected Config $config;

    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null
    ) {
        // Backward compatibility: use ServiceLocator if dependencies not provided
        $this->logger = $logger ?? ServiceLocator::getLogger();
        $this->modelFactory = $modelFactory ?? ServiceLocator::getModelFactory();
        $this->databaseConnector = $databaseConnector ?? ServiceLocator::getDatabaseConnector();
        $this->metadataEngine = $metadataEngine ?? ServiceLocator::get('metadata_engine');
        $this->config = $config ?? ServiceLocator::getConfig();
    }

    /**
     * Get the current authenticated user from context
     * @return ?\Gravitycar\Models\ModelBase Current user or null if not authenticated
     */
    protected function getCurrentUser(): ?\Gravitycar\Models\ModelBase
    {
        return ServiceLocator::getCurrentUser();
    }

    /**
     * Register all routes for this API controller
     * @return array
     */
    abstract public function registerRoutes(): array;
}
