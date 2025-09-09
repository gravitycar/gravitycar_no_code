<?php

namespace Gravitycar\Api;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Api\Request;
use Gravitycar\Services\GoogleBooksApiService;
use Gravitycar\Services\BookGoogleBooksIntegrationService;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Core\Config;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * GoogleBooksController
 * API controller for Google Books integration endpoints
 */
class GoogleBooksController extends ApiControllerBase
{
    private ?GoogleBooksApiService $googleBooksService = null;
    private ?BookGoogleBooksIntegrationService $integrationService = null;
    
    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null
    )
    {
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config);
    }
    
    private function getGoogleBooksService(): GoogleBooksApiService
    {
        if ($this->googleBooksService === null) {
            $this->googleBooksService = new GoogleBooksApiService();
        }
        return $this->googleBooksService;
    }
    
    private function getIntegrationService(): BookGoogleBooksIntegrationService
    {
        if ($this->integrationService === null) {
            $this->integrationService = new BookGoogleBooksIntegrationService();
        }
        return $this->integrationService;
    }
    
    protected function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = ServiceLocator::getLogger();
        }
        return $this->logger;
    }
    
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/google-books/search',
                'apiClass' => self::class,
                'apiMethod' => 'searchBooks',
                'parameterNames' => []
            ]
        ];
    }

    public function searchBooks(Request $request): array
    {
        try {
            $query = $request->get('q') ?? $request->get('query');
            $title = $request->get('title');
            
            if (empty($query) && empty($title)) {
                throw new GCException('Search query or title is required');
            }
            
            if ($title) {
                $results = $this->getIntegrationService()->searchBook($title);
                
                return [
                    'success' => true,
                    'data' => [
                        'books' => $results['partial_matches'] ?? [],
                        'exact_match' => $results['exact_match'] ?? null,
                        'match_type' => $results['match_type'] ?? 'unknown',
                        'total_results' => $results['total_results'] ?? 0,
                        'query' => $title
                    ]
                ];
            }
            
            $books = $this->getGoogleBooksService()->searchBooks($query);
            
            return [
                'success' => true,
                'data' => [
                    'books' => $books,
                    'total_results' => count($books),
                    'query' => $query
                ]
            ];
            
        } catch (GCException $e) {
            $this->getLogger()->error('Google Books search failed', [
                'query' => $request->get('q') ?? $request->get('query'),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
            
        } catch (\Exception $e) {
            $this->getLogger()->error('Unexpected error during Google Books search', [
                'query' => $request->get('q') ?? $request->get('query'),
                'error' => $e->getMessage()
            ]);
            
            throw new GCException('An unexpected error occurred during book search');
        }
    }
}
