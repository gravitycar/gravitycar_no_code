<?php

namespace Gravitycar\Services;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Core\Config;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * TMDBApiService
 * Service for interacting with The Movie Database (TMDB) API
 * Provides movie search and detailed movie information retrieval
 */
class TMDBApiService
{
    private const API_BASE_URL = 'https://api.themoviedb.org/3';
    private const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p';
    private const YOUTUBE_BASE_URL = 'https://www.youtube.com/watch?v=';
    
    private string $apiKey;
    private string $readAccessToken;
    private ?Config $config;
    private ?Logger $logger;
    
    public function __construct(Config $config = null, Logger $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        
        $this->apiKey = $this->getConfig()->getEnv('TMDB_API_KEY');
        $this->readAccessToken = $this->getConfig()->getEnv('TMDB_API_READ_ACCESS_TOKEN');
        
        if (!$this->apiKey) {
            throw new GCException('TMDB API key not found in configuration');
        }
        
        if (!$this->readAccessToken) {
            throw new GCException('TMDB read access token not found in configuration');
        }
    }
    
    /**
     * Search for movies by partial title
     * 
     * @param string $query Partial movie title to search for
     * @param int $page Page number for pagination (default: 1)
     * @return array Array of movie search results
     * @throws GCException
     */
    public function searchMovies(string $query, int $page = 1): array
    {
        if (empty(trim($query))) {
            throw new GCException('Search query cannot be empty');
        }
        
        $url = self::API_BASE_URL . '/search/movie';
        $params = [
            'api_key' => $this->apiKey,
            'query' => $query,
            'page' => $page,
            'include_adult' => 'false'
        ];
        
        $response = $this->makeApiRequest($url, $params);
        
        if (!isset($response['results'])) {
            throw new GCException('Invalid response format from TMDB search API');
        }
        
        return array_map([$this, 'formatSearchResult'], $response['results']);
    }
    
    /**
     * Get detailed information about a specific movie
     * 
     * @param int $movieId TMDB movie ID
     * @return array Detailed movie information
     * @throws GCException
     */
    public function getMovieDetails(int $movieId): array
    {
        $url = self::API_BASE_URL . "/movie/{$movieId}";
        $params = [
            'api_key' => $this->apiKey,
            'append_to_response' => 'videos,images'
        ];
        
        $response = $this->makeApiRequest($url, $params);
        
        return $this->formatMovieDetails($response);
    }
    
    /**
     * Get config instance lazily to avoid circular dependencies
     */
    protected function getConfig(): Config {
        if ($this->config === null) {
            $this->config = ServiceLocator::getConfig();
        }
        return $this->config;
    }
    
    /**
     * Get logger instance lazily to avoid circular dependencies
     */
    protected function getLogger(): Logger {
        if ($this->logger === null) {
            $this->logger = ServiceLocator::getLogger();
        }
        return $this->logger;
    }
    
    /**
     * Format search result for consistent output
     * 
     * @param array $movie Raw movie data from TMDB search
     * @return array Formatted movie data
     */
    private function formatSearchResult(array $movie): array
    {
        return [
            'tmdb_id' => $movie['id'],
            'title' => $movie['title'] ?? 'Unknown Title',
            'release_year' => $this->extractYear($movie['release_date'] ?? ''),
            'poster_url' => $this->buildImageUrl($movie['poster_path'] ?? null, 'w500'),
            'overview' => $this->truncateOverview($movie['overview'] ?? ''),
            'popularity' => $movie['popularity'] ?? 0,
            'obscurity_score' => $this->calculateObscurityScore($movie['popularity'] ?? 0),
            'vote_average' => $movie['vote_average'] ?? 0,
            'vote_count' => $movie['vote_count'] ?? 0
        ];
    }
    
    /**
     * Format detailed movie information for consistent output
     * 
     * @param array $movie Raw movie data from TMDB details
     * @return array Formatted detailed movie data
     */
    private function formatMovieDetails(array $movie): array
    {
        $trailer = $this->findTrailer($movie['videos']['results'] ?? []);
        
        return [
            'tmdb_id' => $movie['id'],
            'title' => $movie['title'] ?? 'Unknown Title',
            'release_year' => $this->extractYear($movie['release_date'] ?? ''),
            'overview' => $this->truncateOverview($movie['overview'] ?? ''),
            'poster_url' => $this->buildImageUrl($movie['poster_path'] ?? null, 'w500'),
            'backdrop_url' => $this->buildImageUrl($movie['backdrop_path'] ?? null, 'w1280'),
            'trailer_url' => $trailer,
            'popularity' => $movie['popularity'] ?? 0,
            'obscurity_score' => $this->calculateObscurityScore($movie['popularity'] ?? 0),
            'vote_average' => $movie['vote_average'] ?? 0,
            'vote_count' => $movie['vote_count'] ?? 0,
            'runtime' => $movie['runtime'] ?? null,
            'genres' => array_column($movie['genres'] ?? [], 'name'),
            'imdb_id' => $movie['imdb_id'] ?? null,
            'tagline' => $movie['tagline'] ?? '',
            'release_date' => $movie['release_date'] ?? '',
            'budget' => $movie['budget'] ?? 0,
            'revenue' => $movie['revenue'] ?? 0
        ];
    }
    
    /**
     * Calculate obscurity score based on popularity
     * Scale: 1 = very well known, 5 = very obscure
     * 
     * @param float $popularity TMDB popularity score
     * @return int Obscurity score (1-5)
     */
    private function calculateObscurityScore(float $popularity): int
    {
        // TMDB popularity scores typically range from 0 to 100+
        // Higher popularity = lower obscurity score
        if ($popularity >= 50) {
            return 1; // Very well known
        } elseif ($popularity >= 20) {
            return 2; // Well known
        } elseif ($popularity >= 10) {
            return 3; // Moderately known
        } elseif ($popularity >= 5) {
            return 4; // Somewhat obscure
        } else {
            return 5; // Very obscure
        }
    }
    
    /**
     * Find the best trailer from videos list
     * 
     * @param array $videos List of video objects
     * @return string|null YouTube URL of trailer or null if none found
     */
    private function findTrailer(array $videos): ?string
    {
        // Prioritize official trailers
        foreach ($videos as $video) {
            if ($video['site'] === 'YouTube' && 
                $video['type'] === 'Trailer' && 
                ($video['official'] ?? false)) {
                return self::YOUTUBE_BASE_URL . $video['key'];
            }
        }
        
        // Fallback to any trailer
        foreach ($videos as $video) {
            if ($video['site'] === 'YouTube' && $video['type'] === 'Trailer') {
                return self::YOUTUBE_BASE_URL . $video['key'];
            }
        }
        
        // Fallback to any YouTube video
        foreach ($videos as $video) {
            if ($video['site'] === 'YouTube') {
                return self::YOUTUBE_BASE_URL . $video['key'];
            }
        }
        
        return null;
    }
    
    /**
     * Build full image URL from TMDB path
     * 
     * @param string|null $path Image path from TMDB
     * @param string $size Image size (w92, w154, w185, w342, w500, w780, w1280, original)
     * @return string|null Full image URL or null if no path
     */
    private function buildImageUrl(?string $path, string $size = 'w500'): ?string
    {
        if (!$path) {
            return null;
        }
        
        return self::IMAGE_BASE_URL . "/{$size}{$path}";
    }
    
    /**
     * Extract year from release date
     * 
     * @param string $releaseDate Date in YYYY-MM-DD format
     * @return int|null Year or null if invalid date
     */
    private function extractYear(string $releaseDate): ?int
    {
        if (empty($releaseDate)) {
            return null;
        }
        
        $year = (int) substr($releaseDate, 0, 4);
        return $year > 1800 ? $year : null;
    }
    
    /**
     * Truncate overview to 1-4 sentences
     * 
     * @param string $overview Full overview text
     * @return string Truncated overview
     */
    private function truncateOverview(string $overview): string
    {
        if (empty($overview)) {
            return '';
        }
        
        // Split by sentence endings
        $sentences = preg_split('/[.!?]+/', $overview);
        
        // Remove empty elements and trim
        $sentences = array_filter(array_map('trim', $sentences));
        
        // Take first 1-4 sentences
        $sentences = array_slice($sentences, 0, 4);
        
        if (empty($sentences)) {
            return $overview;
        }
        
        return implode('. ', $sentences) . '.';
    }
    
    /**
     * Make HTTP request to TMDB API
     * 
     * @param string $url API endpoint URL
     * @param array $params Query parameters
     * @return array Decoded JSON response
     * @throws GCException
     */
    private function makeApiRequest(string $url, array $params = []): array
    {
        $logger = $this->getLogger();
        
        $fullUrl = $url . '?' . http_build_query($params);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Gravitycar Framework TMDB Client/1.0'
            ]
        ]);
        
        $logger->info('Making TMDB API request', ['url' => $url, 'params' => $params]);
        
        $response = @file_get_contents($fullUrl, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            $logger->error('TMDB API request failed', [
                'url' => $url,
                'error' => $error['message'] ?? 'Unknown error'
            ]);
            throw new GCException('Failed to connect to TMDB API', [
                'url' => $url,
                'error' => $error['message'] ?? 'Unknown error'
            ]);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $logger->error('TMDB API response decode failed', [
                'url' => $url,
                'json_error' => json_last_error_msg()
            ]);
            throw new GCException('Invalid JSON response from TMDB API', [
                'json_error' => json_last_error_msg()
            ]);
        }
        
        // Check for API errors
        if (isset($decodedResponse['success']) && $decodedResponse['success'] === false) {
            $logger->error('TMDB API error response', [
                'url' => $url,
                'error' => $decodedResponse
            ]);
            throw new GCException('TMDB API error: ' . ($decodedResponse['status_message'] ?? 'Unknown error'), [
                'tmdb_error' => $decodedResponse
            ]);
        }
        
        $logger->info('TMDB API request successful', ['url' => $url]);
        
        return $decodedResponse;
    }
    
    /**
     * Get available image sizes for poster images
     * 
     * @return array List of available poster sizes
     */
    public function getPosterSizes(): array
    {
        return ['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'];
    }
    
    /**
     * Get available image sizes for backdrop images
     * 
     * @return array List of available backdrop sizes
     */
    public function getBackdropSizes(): array
    {
        return ['w300', 'w780', 'w1280', 'original'];
    }
}
