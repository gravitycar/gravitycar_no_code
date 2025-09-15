<?php

namespace Gravitycar\Services;

use Gravitycar\Services\GoogleBooksApiService;
use Gravitycar\Exceptions\GCException;

/**
 * BookGoogleBooksIntegrationService
 * Business logic for integrating Google Books API with Books model
 */
class BookGoogleBooksIntegrationService
{
    private GoogleBooksApiService $googleBooksService;
    
    public function __construct(GoogleBooksApiService $googleBooksService)
    {
        $this->googleBooksService = $googleBooksService;
    }
    
    /**
     * Search for book and return match results
     * 
     * @param string $title Book title
     * @param string|null $author Optional author name for more precise search
     * @return array Search results with match analysis
     */
    public function searchBook(string $title, ?string $author = null): array
    {
        $query = $this->buildSearchQuery($title, $author);
        $results = $this->googleBooksService->searchBooks($query);
        
        return [
            'exact_match' => $this->findExactMatch($results, $title, $author),
            'partial_matches' => $this->filterPartialMatches($results, $title, $author),
            'match_type' => $this->determineMatchType($results, $title, $author),
            'total_results' => count($results)
        ];
    }
    
    /**
     * Search for book by ISBN
     * 
     * @param string $isbn ISBN-10 or ISBN-13
     * @return array Search results
     */
    public function searchByISBN(string $isbn): array
    {
        $results = $this->googleBooksService->searchByISBN($isbn);
        
        return [
            'exact_match' => $results[0] ?? null,
            'partial_matches' => $results,
            'match_type' => empty($results) ? 'none' : 'exact',
            'total_results' => count($results)
        ];
    }
    
    /**
     * Enrich book data from Google Books
     * 
     * @param string $googleBooksId Google Books volume ID
     * @return array Enriched book data
     */
    public function enrichBookData(string $googleBooksId): array
    {
        $details = $this->googleBooksService->getBookDetails($googleBooksId);
        
        // Return data formatted for Books model fields
        return [
            'google_books_id' => $details['google_books_id'] ?? $googleBooksId,
            'title' => $details['title'] ?? null,
            'subtitle' => $details['subtitle'] ?? null,
            'authors' => $details['authors'] ?? null,
            'synopsis' => $details['synopsis'] ?? null,
            'cover_image_url' => $details['cover_image_url'] ?? null,
            'publisher' => $details['publisher'] ?? null,
            'publication_date' => $details['publication_date'] ?? null,
            'page_count' => $details['page_count'] ?? null,
            'genres' => $details['genres'] ?? null,
            'language' => $details['language'] ?? null,
            'average_rating' => $details['average_rating'] ?? null,
            'ratings_count' => $details['ratings_count'] ?? null,
            'maturity_rating' => $details['maturity_rating'] ?? null,
            'isbn_13' => $details['isbn_13'] ?? null,
            'isbn_10' => $details['isbn_10'] ?? null
        ];
    }
    
    /**
     * Build search query from title and optional author
     * 
     * @param string $title Book title
     * @param string|null $author Optional author name
     * @return string Formatted search query
     */
    private function buildSearchQuery(string $title, ?string $author = null): string
    {
        $query = 'intitle:' . $title;
        
        if (!empty($author)) {
            $query .= ' inauthor:' . $author;
        }
        
        return $query;
    }
    
    /**
     * Find exact title and author match (case-insensitive)
     * 
     * @param array $results Search results
     * @param string $title Book title
     * @param string|null $author Optional author name
     * @return array|null Exact match or null if not found
     */
    private function findExactMatch(array $results, string $title, ?string $author = null): ?array
    {
        $normalizedTitle = $this->normalizeText($title);
        $normalizedAuthor = $author ? $this->normalizeText($author) : null;
        
        foreach ($results as $book) {
            $bookTitle = $this->normalizeText($book['title'] ?? '');
            
            // Check title match
            if ($bookTitle !== $normalizedTitle) {
                continue;
            }
            
            // If author specified, check author match
            if ($normalizedAuthor) {
                $bookAuthors = $this->normalizeText($book['authors'] ?? '');
                if (strpos($bookAuthors, $normalizedAuthor) === false) {
                    continue;
                }
            }
            
            return $book;
        }
        
        return null;
    }
    
    /**
     * Filter results for partial matches
     * 
     * @param array $results Search results
     * @param string $title Book title
     * @param string|null $author Optional author name
     * @return array Filtered partial matches (top 5)
     */
    private function filterPartialMatches(array $results, string $title, ?string $author = null): array
    {
        // Score and sort results by relevance
        $scoredResults = [];
        
        foreach ($results as $book) {
            $score = $this->calculateRelevanceScore($book, $title, $author);
            $scoredResults[] = [
                'book' => $book,
                'score' => $score
            ];
        }
        
        // Sort by score (highest first)
        usort($scoredResults, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Return top 5 books only
        $topResults = array_slice($scoredResults, 0, 5);
        
        return array_map(function($item) {
            return $item['book'];
        }, $topResults);
    }
    
    /**
     * Calculate relevance score for a book result
     * 
     * @param array $book Book data
     * @param string $title Search title
     * @param string|null $author Search author
     * @return int Relevance score (higher is better)
     */
    private function calculateRelevanceScore(array $book, string $title, ?string $author = null): int
    {
        $score = 0;
        
        $bookTitle = $this->normalizeText($book['title'] ?? '');
        $normalizedTitle = $this->normalizeText($title);
        
        // Title scoring
        if ($bookTitle === $normalizedTitle) {
            $score += 100; // Exact title match
        } elseif (strpos($bookTitle, $normalizedTitle) !== false) {
            $score += 50; // Title contains search term
        } elseif (strpos($normalizedTitle, $bookTitle) !== false) {
            $score += 30; // Search term contains title
        }
        
        // Author scoring
        if ($author) {
            $bookAuthors = $this->normalizeText($book['authors'] ?? '');
            $normalizedAuthor = $this->normalizeText($author);
            
            if (strpos($bookAuthors, $normalizedAuthor) !== false) {
                $score += 25; // Author match
            }
        }
        
        // Boost for books with more data
        if (!empty($book['isbn_13']) || !empty($book['isbn_10'])) {
            $score += 10; // Has ISBN
        }
        
        if (!empty($book['cover_image_url'])) {
            $score += 5; // Has cover image
        }
        
        if (!empty($book['synopsis'])) {
            $score += 5; // Has description
        }
        
        return $score;
    }
    
    /**
     * Determine match type: exact, multiple, none
     * 
     * @param array $results Search results
     * @param string $title Book title
     * @param string|null $author Optional author name
     * @return string Match type
     */
    private function determineMatchType(array $results, string $title, ?string $author = null): string
    {
        if (empty($results)) {
            return 'none';
        }
        
        if ($this->findExactMatch($results, $title, $author)) {
            return 'exact';
        }
        
        return 'multiple';
    }
    
    /**
     * Normalize text for comparison
     * 
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    private function normalizeText(string $text): string
    {
        // Convert to lowercase and remove punctuation
        $normalized = strtolower(trim($text));
        
        // Replace punctuation and special characters with spaces
        $normalized = preg_replace('/[^\w\s]/', ' ', $normalized);
        
        // Replace multiple spaces with single space
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return trim($normalized);
    }
}
