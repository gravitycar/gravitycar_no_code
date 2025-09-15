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
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Monolog\Logger;

/**
 * GoogleBooksController
 * API controller for Google Books integration endpoints
 * Pure dependency injection - all dependencies explicitly injected via constructor.
 */
class GoogleBooksController extends ApiControllerBase
{
    private ?GoogleBooksApiService $googleBooksService;
    private ?BookGoogleBooksIntegrationService $integrationService;
    
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
     * @param GoogleBooksApiService $googleBooksService
     * @param BookGoogleBooksIntegrationService $integrationService
     */
    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null,
        CurrentUserProviderInterface $currentUserProvider = null,
        GoogleBooksApiService $googleBooksService = null,
        BookGoogleBooksIntegrationService $integrationService = null
    )
    {
        // All dependencies explicitly injected - no ServiceLocator fallbacks
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config, $currentUserProvider);
        $this->googleBooksService = $googleBooksService;
        $this->integrationService = $integrationService;
    }
    
    private function getGoogleBooksService(): ?GoogleBooksApiService
    {
        return $this->googleBooksService;
    }
    
    private function getIntegrationService(): ?BookGoogleBooksIntegrationService
    {
        return $this->integrationService;
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
            $this->logger->error('Google Books search failed', [
                'query' => $request->get('q') ?? $request->get('query'),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during Google Books search', [
                'query' => $request->get('q') ?? $request->get('query'),
                'error' => $e->getMessage()
            ]);
            
            throw new GCException('An unexpected error occurred during book search');
        }
    }
}
