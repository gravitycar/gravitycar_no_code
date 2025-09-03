<?php
namespace Gravitycar\Api\Movies;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Services\MovieTMDBIntegrationService;
use Gravitycar\Exceptions\GCException;

class TMDBController extends ApiControllerBase {
    private MovieTMDBIntegrationService $tmdbService;
    
    public function __construct() {
        parent::__construct();
        $this->tmdbService = new MovieTMDBIntegrationService();
    }
    
    /**
     * Register routes for this controller
     */
    public function registerRoutes(): array {
        return [
            [
                'method' => 'GET',
                'path' => '/movies/tmdb/search',
                'apiClass' => '\\Gravitycar\\Api\\Movies\\TMDBController',
                'apiMethod' => 'search',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/movies/tmdb/enrich/?',
                'apiClass' => '\\Gravitycar\\Api\\Movies\\TMDBController',
                'apiMethod' => 'enrich',
                'parameterNames' => ['tmdbId']
            ]
        ];
    }
    
    /**
     * Search TMDB for movies
     * GET /movies/tmdb/search?title=movie+title
     */
    public function search(): array {
        $title = $_GET['title'] ?? null;
        
        if (empty($title)) {
            throw new GCException('Title parameter is required');
        }
        
        $results = $this->tmdbService->searchMovie($title);
        
        $this->jsonResponse([
            'success' => true,
            'data' => $results
        ]);
        
        return [
            'success' => true,
            'data' => $results
        ];
    }
    
    /**
     * Get enrichment data for specific TMDB ID
     * GET /movies/tmdb/enrich/{tmdb_id}
     */
    public function enrich(int $tmdbId): array {
        $enrichmentData = $this->tmdbService->enrichMovieData($tmdbId);
        
        $this->jsonResponse([
            'success' => true,
            'data' => $enrichmentData
        ]);
        
        return [
            'success' => true,
            'data' => $enrichmentData
        ];
    }
}
