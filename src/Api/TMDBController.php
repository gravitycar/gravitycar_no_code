<?php
namespace Gravitycar\Api;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Api\Request;
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
                'apiClass' => '\\Gravitycar\\Api\\TMDBController',
                'apiMethod' => 'search',
                'parameterNames' => []
            ],
            [
                'method' => 'POST',
                'path' => '/movies/tmdb/search',
                'apiClass' => '\\Gravitycar\\Api\\TMDBController',
                'apiMethod' => 'searchPost',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/movies/tmdb/enrich/?',
                'apiClass' => '\\Gravitycar\\Api\\TMDBController',
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
     * Search TMDB for movies (POST version)
     * POST /movies/tmdb/search with JSON body {"title": "movie title"}
     */
    public function searchPost(): array {
        $input = json_decode(file_get_contents('php://input'), true);
        $title = $input['title'] ?? null;
        
        if (empty($title)) {
            throw new GCException('Title parameter is required in request body');
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
    public function enrich(Request $request): array {
        $tmdbId = $request->get('tmdbId');
        
        if (!$tmdbId || !is_numeric($tmdbId)) {
            throw new GCException('Valid TMDB ID is required');
        }
        
        $enrichmentData = $this->tmdbService->enrichMovieData((int)$tmdbId);
        
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
