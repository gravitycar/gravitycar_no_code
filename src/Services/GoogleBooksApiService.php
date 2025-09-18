<?php

namespace Gravitycar\Services;

use Gravitycar\Core\Config;
use Gravitycar\Exceptions\GCException;
use Psr\Log\LoggerInterface;

/**
 * GoogleBooksApiService
 * Service for interacting with Google Books API
 * Provides book search and detailed book information retrieval
 */
class GoogleBooksApiService
{
    private const API_BASE_URL = 'https://www.googleapis.com/books/v1';
    
    private string $apiKey;
    private Config $config;
    private LoggerInterface $logger;
    
    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        
        // Try environment variable first, then config value, then fallback
        $this->apiKey = $this->config->getEnv('GOOGLE_BOOKS_API_KEY') 
            ?? $this->config->get('google_books_api_key') 
            ?? '';
        
        if (!$this->apiKey) {
            $this->logger->warning('Google Books API key not found in configuration - Google Books integration will not work');
        }
    }
    
    /**
     * Search for books by query (title, author, ISBN, etc.)
     * 
     * @param string $query Search query
     * @param int $maxResults Maximum number of results (1-40)
     * @param int $startIndex Starting index for pagination
     * @return array Array of book search results
     * @throws GCException
     */
    public function searchBooks(string $query, int $maxResults = 10, int $startIndex = 0): array
    {
        if (empty(trim($query))) {
            throw new GCException('Search query cannot be empty');
        }
        
        if ($maxResults < 1 || $maxResults > 40) {
            throw new GCException('Max results must be between 1 and 40');
        }
        
        $url = self::API_BASE_URL . '/volumes';
        $params = [
            'q' => $query,
            'key' => $this->apiKey,
            'maxResults' => $maxResults,
            'startIndex' => $startIndex,
            'printType' => 'books'
        ];
        
        $response = $this->makeApiRequest($url, $params);
        
        if (!isset($response['items'])) {
            return []; // No results found
        }
        
        return array_map([$this, 'formatSearchResult'], $response['items']);
    }
    
    /**
     * Search for books by ISBN
     * 
     * @param string $isbn ISBN-10 or ISBN-13
     * @return array Array of book search results
     * @throws GCException
     */
    public function searchByISBN(string $isbn): array
    {
        $cleanIsbn = preg_replace('/[^0-9X]/', '', $isbn);
        
        if (empty($cleanIsbn)) {
            throw new GCException('Invalid ISBN format');
        }
        
        return $this->searchBooks("isbn:{$cleanIsbn}");
    }
    
    /**
     * Get detailed information about a specific book
     * 
     * @param string $volumeId Google Books volume ID
     * @return array Detailed book information
     * @throws GCException
     */
    public function getBookDetails(string $volumeId): array
    {
        if (empty($volumeId)) {
            throw new GCException('Volume ID cannot be empty');
        }
        
        $url = self::API_BASE_URL . "/volumes/{$volumeId}";
        $params = [
            'key' => $this->apiKey
        ];
        
        $response = $this->makeApiRequest($url, $params);
        
        return $this->formatBookDetails($response);
    }
    
    /**
     * Format search result for consistent output
     * 
     * @param array $book Raw book data from Google Books search
     * @return array Formatted book data
     */
    private function formatSearchResult(array $book): array
    {
        $volumeInfo = $book['volumeInfo'] ?? [];
        
        return [
            'google_books_id' => $book['id'] ?? null,
            'title' => $volumeInfo['title'] ?? 'Unknown Title',
            'subtitle' => $volumeInfo['subtitle'] ?? null,
            'authors' => $this->formatAuthors($volumeInfo['authors'] ?? []),
            'publisher' => $volumeInfo['publisher'] ?? null,
            'publication_date' => $this->formatPublicationDate($volumeInfo['publishedDate'] ?? ''),
            'synopsis' => $this->truncateDescription($volumeInfo['description'] ?? ''),
            'cover_image_url' => $this->extractCoverImage($volumeInfo['imageLinks'] ?? []),
            'page_count' => $volumeInfo['pageCount'] ?? null,
            'genres' => $this->formatGenres($volumeInfo['categories'] ?? []),
            'language' => $volumeInfo['language'] ?? 'en',
            'average_rating' => $volumeInfo['averageRating'] ?? null,
            'ratings_count' => $volumeInfo['ratingsCount'] ?? null,
            'maturity_rating' => $volumeInfo['maturityRating'] ?? null,
            'isbn_13' => $this->extractISBN($volumeInfo['industryIdentifiers'] ?? [], 'ISBN_13'),
            'isbn_10' => $this->extractISBN($volumeInfo['industryIdentifiers'] ?? [], 'ISBN_10')
        ];
    }
    
    /**
     * Format detailed book information for consistent output
     * 
     * @param array $book Raw book data from Google Books details
     * @return array Formatted detailed book data
     */
    private function formatBookDetails(array $book): array
    {
        return $this->formatSearchResult($book);
    }
    
    /**
     * Format authors array into comma-separated string
     * 
     * @param array $authors Array of author names
     * @return string|null Comma-separated author names or null if empty
     */
    private function formatAuthors(array $authors): ?string
    {
        if (empty($authors)) {
            return null;
        }
        
        return implode(', ', $authors);
    }
    
    /**
     * Format publication date to standardized format
     * 
     * @param string $publishedDate Date string from API
     * @return string|null Formatted date (YYYY-MM-DD) or null if invalid
     */
    private function formatPublicationDate(string $publishedDate): ?string
    {
        if (empty($publishedDate)) {
            return null;
        }
        
        // Handle various date formats from Google Books
        // Can be YYYY, YYYY-MM, or YYYY-MM-DD
        if (preg_match('/^(\d{4})(-\d{2})?(-\d{2})?/', $publishedDate, $matches)) {
            $year = $matches[1];
            $month = isset($matches[2]) ? substr($matches[2], 1) : '01';
            $day = isset($matches[3]) ? substr($matches[3], 1) : '01';
            
            return "{$year}-{$month}-{$day}";
        }
        
        return null;
    }
    
    /**
     * Truncate description to reasonable length
     * 
     * @param string $description Full description text
     * @return string|null Truncated description or null if empty
     */
    private function truncateDescription(string $description): ?string
    {
        if (empty($description)) {
            return null;
        }
        
        // Remove HTML tags
        $description = strip_tags($description);
        
        // Truncate to 5000 characters (matching our field limit)
        if (strlen($description) > 5000) {
            $description = substr($description, 0, 4997) . '...';
        }
        
        return $description;
    }
    
    /**
     * Extract cover image URL from imageLinks
     * 
     * @param array $imageLinks Image links from API
     * @return string|null Cover image URL or null if not available
     */
    private function extractCoverImage(array $imageLinks): ?string
    {
        // Prefer thumbnail over smallThumbnail
        if (isset($imageLinks['thumbnail'])) {
            return $imageLinks['thumbnail'];
        }
        
        if (isset($imageLinks['smallThumbnail'])) {
            return $imageLinks['smallThumbnail'];
        }
        
        return null;
    }
    
    /**
     * Format genres array into comma-separated string
     * 
     * @param array $categories Array of category names
     * @return string|null Comma-separated genre names or null if empty
     */
    private function formatGenres(array $categories): ?string
    {
        if (empty($categories)) {
            return null;
        }
        
        return implode(', ', $categories);
    }
    
    /**
     * Extract ISBN from industry identifiers
     * 
     * @param array $identifiers Array of industry identifiers
     * @param string $type ISBN type ('ISBN_10' or 'ISBN_13')
     * @return string|null ISBN value or null if not found
     */
    private function extractISBN(array $identifiers, string $type): ?string
    {
        foreach ($identifiers as $identifier) {
            if (($identifier['type'] ?? '') === $type) {
                return $identifier['identifier'] ?? null;
            }
        }
        
        return null;
    }
    
    /**
     * Make HTTP request to Google Books API
     * 
     * @param string $url API endpoint URL
     * @param array $params Query parameters
     * @return array Decoded JSON response
     * @throws GCException
     */
    private function makeApiRequest(string $url, array $params = []): array
    {
        $logger = $this->logger;
        
        $fullUrl = $url . '?' . http_build_query($params);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Gravitycar Framework Google Books Client/1.0'
            ]
        ]);
        
        $logger->info('Making Google Books API request', ['url' => $url, 'params' => array_merge($params, ['key' => '[REDACTED]'])]);
        
        $response = @file_get_contents($fullUrl, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            $logger->error('Google Books API request failed', [
                'url' => $url,
                'error' => $error['message'] ?? 'Unknown error'
            ]);
            throw new GCException('Failed to connect to Google Books API', [
                'url' => $url,
                'error' => $error['message'] ?? 'Unknown error'
            ]);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $logger->error('Google Books API response decode failed', [
                'url' => $url,
                'json_error' => json_last_error_msg()
            ]);
            throw new GCException('Invalid JSON response from Google Books API', [
                'json_error' => json_last_error_msg()
            ]);
        }
        
        // Check for API errors
        if (isset($decodedResponse['error'])) {
            $error = $decodedResponse['error'];
            $logger->error('Google Books API error response', [
                'url' => $url,
                'error' => $error
            ]);
            throw new GCException('Google Books API error: ' . ($error['message'] ?? 'Unknown error'), [
                'google_books_error' => $error
            ]);
        }
        
        $logger->info('Google Books API request successful', ['url' => $url]);
        
        return $decodedResponse;
    }
}
