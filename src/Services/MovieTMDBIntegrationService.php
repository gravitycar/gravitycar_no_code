<?php
namespace Gravitycar\Services;

use Gravitycar\Services\TMDBApiService;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;

class MovieTMDBIntegrationService {
    private TMDBApiService $tmdbService;
    
    public function __construct() {
        $this->tmdbService = new TMDBApiService();
    }
    
    /**
     * Search for movie and return match results
     */
    public function searchMovie(string $title): array {
        $results = $this->tmdbService->searchMovies($title);
        
        return [
            'exact_match' => $this->findExactMatch($results, $title),
            'partial_matches' => $this->filterPartialMatches($results, $title),
            'match_type' => $this->determineMatchType($results, $title)
        ];
    }
    
    /**
     * Find exact title match (case-insensitive)
     */
    private function findExactMatch(array $results, string $title): ?array {
        $normalizedTitle = $this->normalizeTitle($title);
        
        foreach ($results as $movie) {
            if ($this->normalizeTitle($movie['title']) === $normalizedTitle) {
                return $movie;
            }
        }
        
        return null;
    }
    
    /**
     * Filter results for partial matches
     */
    private function filterPartialMatches(array $results, string $title): array {
        // Return top 5 most relevant matches
        return array_slice($results, 0, 5);
    }
    
    /**
     * Determine match type: exact, multiple, none
     */
    private function determineMatchType(array $results, string $title): string {
        if (empty($results)) {
            return 'none';
        }
        
        if ($this->findExactMatch($results, $title)) {
            return 'exact';
        }
        
        return 'multiple';
    }
    
    /**
     * Enrich movie data from TMDB
     */
    public function enrichMovieData(int $tmdbId): array {
        $details = $this->tmdbService->getMovieDetails($tmdbId);
        
        // The TMDBApiService already processes and formats most of the data
        // So we can use the values directly instead of re-processing them
        
        return [
            'tmdb_id' => $details['tmdb_id'] ?? $tmdbId,
            'synopsis' => $details['overview'] ?? '',
            'poster_url' => $details['poster_url'] ?? null,
            'trailer_url' => $details['trailer_url'] ?? null,
            'obscurity_score' => $details['obscurity_score'] ?? null,
            'release_year' => $details['release_year'] ?? null,
            'name' => $details['title'] ?? null
        ];
    }
    
    /**
     * Normalize title for comparison
     */
    private function normalizeTitle(string $title): string {
        // Replace punctuation with spaces, then convert to lowercase
        $normalized = strtolower(trim(preg_replace('/[^\w\s]/', ' ', $title)));
        // Replace multiple spaces with single space
        return preg_replace('/\s+/', ' ', $normalized);
    }
}
