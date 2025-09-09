<?php
namespace Gravitycar\Api;

use Gravitycar\Services\OpenAPIGenerator;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Core\Config;
use Psr\Log\LoggerInterface;
use Monolog\Logger;

/**
 * OpenAPIController: Provides OpenAPI specification endpoint
 */
class OpenAPIController extends ApiControllerBase {
    private OpenAPIGenerator $openAPIGenerator;
    
    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null
    ) {
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config);
        $this->openAPIGenerator = new OpenAPIGenerator();
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
