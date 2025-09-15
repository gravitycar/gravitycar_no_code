<?php
namespace Gravitycar\Api;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Monolog\Logger;

/**
 * Abstract base class for all API controllers in Gravitycar.
 * Handles common functionality, request validation, and response formatting.
 * Pure dependency injection - all dependencies explicitly injected via constructor.
 */
abstract class ApiControllerBase {
    protected ?Logger $logger;
    protected ?ModelFactory $modelFactory;
    protected ?DatabaseConnectorInterface $databaseConnector;
    protected ?MetadataEngineInterface $metadataEngine;
    protected ?Config $config;
    protected ?CurrentUserProviderInterface $currentUserProvider;

    /**
     * Pure dependency injection constructor - all dependencies explicitly provided
     * For backwards compatibility during route discovery, all parameters are optional with null defaults
     * 
     * @param Logger $logger
     * @param ModelFactory $modelFactory
     * @param DatabaseConnectorInterface $databaseConnector
     * @param MetadataEngineInterface $metadataEngine
     * @param Config $config
     * @param CurrentUserProviderInterface $currentUserProvider
     */
    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null,
        CurrentUserProviderInterface $currentUserProvider = null
    ) {
        // All dependencies explicitly injected - no ServiceLocator fallbacks
        $this->logger = $logger;
        $this->modelFactory = $modelFactory;
        $this->databaseConnector = $databaseConnector;
        $this->metadataEngine = $metadataEngine;
        $this->config = $config;
        $this->currentUserProvider = $currentUserProvider;
    }

    /**
     * Get the current authenticated user from the provider
     * @return ?\Gravitycar\Models\ModelBase Current user or null if not authenticated
     */
    protected function getCurrentUser(): ?\Gravitycar\Models\ModelBase
    {
        return $this->currentUserProvider?->getCurrentUser();
    }

    /**
     * Register all routes for this API controller
     * @return array
     */
    abstract public function registerRoutes(): array;
}
