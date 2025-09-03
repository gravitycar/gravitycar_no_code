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
        
        // Extract trailer URL from videos
        $trailerUrl = null;
        if (isset($details['videos']['results'])) {
            foreach ($details['videos']['results'] as $video) {
                if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                    $trailerUrl = 'https://www.youtube.com/watch?v=' . $video['key'];
                    break;
                }
            }
        }
        
        // Calculate obscurity score (inverse of popularity)
        $obscurityScore = isset($details['popularity']) ? max(1, 100 - $details['popularity']) : 50;
        
        // Extract release year
        $releaseYear = null;
        if (isset($details['release_date'])) {
            $releaseYear = (int) substr($details['release_date'], 0, 4);
        }
        
        // Build poster URL
        $posterUrl = null;
        if (isset($details['poster_path'])) {
            $posterUrl = 'https://image.tmdb.org/t/p/w500' . $details['poster_path'];
        }
        
        return [
            'tmdb_id' => $details['id'] ?? $tmdbId,
            'synopsis' => $details['overview'] ?? '',
            'poster_url' => $posterUrl,
            'trailer_url' => $trailerUrl,
            'obscurity_score' => $obscurityScore,
            'release_year' => $releaseYear
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
