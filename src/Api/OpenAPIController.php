<?php
namespace Gravitycar\Api;

use Gravitycar\Services\OpenAPIGenerator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Psr\Log\LoggerInterface;
use Monolog\Logger;

/**
 * OpenAPIController: Provides OpenAPI specification endpoint
 * Pure dependency injection - all dependencies explicitly injected via constructor.
 */
class OpenAPIController extends ApiControllerBase {
    private ?OpenAPIGenerator $openAPIGenerator;
    
    /**
     * Pure dependency injection constructor - all dependencies explicitly provided
     * 
     * @param Logger $logger
     * @param ModelFactory $modelFactory
     * @param DatabaseConnectorInterface $databaseConnector
     * @param MetadataEngineInterface $metadataEngine
     * @param Config $config
     * @param CurrentUserProviderInterface $currentUserProvider
     * @param OpenAPIGenerator $openAPIGenerator
     */
    public function __construct(
        Logger $logger,
        ModelFactory $modelFactory,
        DatabaseConnectorInterface $databaseConnector,
        MetadataEngineInterface $metadataEngine,
        Config $config,
        CurrentUserProviderInterface $currentUserProvider,
        OpenAPIGenerator $openAPIGenerator
    ) {
        // All dependencies explicitly injected - no ServiceLocator fallbacks
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config, $currentUserProvider);
        $this->openAPIGenerator = $openAPIGenerator;
    }
    
    /**
     * Register routes for this controller
     */
    public function registerRoutes(): array {
        return [
            [
                'method' => 'GET',
                'path' => '/openapi.json',
                'apiClass' => '\\Gravitycar\\Api\\OpenAPIController',
                'apiMethod' => 'getOpenAPISpec',
                'parameterNames' => []
            ]
        ];
    }
    
    /**
     * Get OpenAPI specification
     */
    public function getOpenAPISpec(): array {
        try {
            $spec = $this->openAPIGenerator->generateSpecification();
            
            // Return JSON directly (will be encoded by the API handler)
            return $spec;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate OpenAPI specification: ' . $e->getMessage());
            
            return [
                'error' => 'Failed to generate OpenAPI specification',
                'message' => $e->getMessage()
            ];
        }
    }
}
