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
            ],
            [
                'method' => 'POST',
                'path' => '/movies/?/tmdb/refresh',
                'apiClass' => '\\Gravitycar\\Api\\Movies\\TMDBController',
                'apiMethod' => 'refresh',
                'parameterNames' => ['movieId']
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
    
    /**
     * Refresh TMDB data for an existing movie
     * POST /movies/{movie_id}/tmdb/refresh
     */
    public function refresh(string $movieId): array {
        try {
            // Load the movie
            $movie = \Gravitycar\Core\ServiceLocator::createModel('\\Gravitycar\\Models\\movies\\Movies');
            
            if (!$movie->findById($movieId)) {
                throw new GCException('Movie not found', ['movie_id' => $movieId]);
            }
            
            // Check if movie has TMDB ID
            $tmdbId = $movie->get('tmdb_id');
            if (empty($tmdbId)) {
                throw new GCException('Movie does not have a TMDB ID to refresh from', ['movie_id' => $movieId]);
            }
            
            // Refresh TMDB data
            $movie->refreshFromTMDB($tmdbId);
            
            // Save the updated movie
            if (!$movie->update()) {
                throw new GCException('Failed to save updated movie data');
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Movie data refreshed from TMDB successfully',
                'data' => [
                    'movie_id' => $movieId,
                    'tmdb_id' => $tmdbId,
                    'updated_fields' => [
                        'synopsis' => $movie->get('synopsis'),
                        'poster_url' => $movie->get('poster_url'),
                        'trailer_url' => $movie->get('trailer_url'),
                        'obscurity_score' => $movie->get('obscurity_score'),
                        'release_year' => $movie->get('release_year')
                    ]
                ]
            ]);
            
            return [
                'success' => true,
                'message' => 'Movie data refreshed from TMDB successfully',
                'data' => [
                    'movie_id' => $movieId,
                    'tmdb_id' => $tmdbId
                ]
            ];
            
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'movie_id' => $movieId
            ], 400);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
