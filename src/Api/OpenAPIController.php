<?php
namespace Gravitycar\Api;

use Gravitycar\Services\OpenAPIGenerator;
use Gravitycar\Core\ServiceLocator;
use Psr\Log\LoggerInterface;

/**
 * OpenAPIController: Provides OpenAPI specification endpoint
 */
class OpenAPIController {
    private OpenAPIGenerator $openAPIGenerator;
    private LoggerInterface $logger;
    
    public function __construct() {
        $this->openAPIGenerator = new OpenAPIGenerator();
        $this->logger = ServiceLocator::getLogger();
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
