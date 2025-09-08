<?php

namespace Gravitycar\Models\books;

use Gravitycar\Models\ModelBase;

/**
 * Books Model
 * Represents a book record with Google Books API integration
 */
class Books extends ModelBase
{
    /**
     * Get books by author
     * 
     * @param string $author Author name
     * @return array Array of Books instances
     */
    public function getByAuthor(string $author): array
    {
        $conditions = [
            'authors' => ['operator' => 'like', 'value' => "%{$author}%"]
        ];
        
        return $this->find($conditions);
    }
    
    /**
     * Get books by genre
     * 
     * @param string $genre Genre name
     * @return array Array of Books instances
     */
    public function getByGenre(string $genre): array
    {
        $conditions = [
            'genres' => ['operator' => 'like', 'value' => "%{$genre}%"]
        ];
        
        return $this->find($conditions);
    }
    
    /**
     * Get books by publication year
     * 
     * @param int $year Publication year
     * @return array Array of Books instances
     */
    public function getByPublicationYear(int $year): array
    {
        $conditions = [
            'publication_date' => ['operator' => 'like', 'value' => "{$year}-%"]
        ];
        
        return $this->find($conditions);
    }
    
    /**
     * Get books by ISBN
     * 
     * @param string $isbn ISBN-10 or ISBN-13
     * @return Books|null Books instance or null if not found
     */
    public function getByISBN(string $isbn): ?self
    {
        $cleanIsbn = preg_replace('/[^0-9X]/', '', $isbn);
        
        $conditions = [
            'OR' => [
                'isbn_10' => ['operator' => 'equals', 'value' => $cleanIsbn],
                'isbn_13' => ['operator' => 'equals', 'value' => $cleanIsbn]
            ]
        ];
        
        $results = $this->find($conditions, [], ['limit' => 1]);
        
        return $results[0] ?? null;
    }
    
    /**
     * Get books by Google Books ID
     * 
     * @param string $googleBooksId Google Books volume ID
     * @return Books|null Books instance or null if not found
     */
    public function getByGoogleBooksId(string $googleBooksId): ?self
    {
        $conditions = [
            'google_books_id' => ['operator' => 'equals', 'value' => $googleBooksId]
        ];
        
        $results = $this->find($conditions, [], ['limit' => 1]);
        
        return $results[0] ?? null;
    }
    
    /**
     * Search books by title (fuzzy search)
     * 
     * @param string $title Book title or partial title
     * @return array Array of Books instances
     */
    public function searchByTitle(string $title): array
    {
        $conditions = [
            'OR' => [
                'title' => ['operator' => 'like', 'value' => "%{$title}%"],
                'subtitle' => ['operator' => 'like', 'value' => "%{$title}%"]
            ]
        ];
        
        return $this->find($conditions);
    }
    
    /**
     * Get books with ratings above threshold
     * 
     * @param float $minRating Minimum average rating
     * @return array Array of Books instances
     */
    public function getHighlyRated(float $minRating = 4.0): array
    {
        $conditions = [
            'average_rating' => ['operator' => 'greaterThanOrEqual', 'value' => $minRating]
        ];
        
        return $this->find($conditions);
    }
    
    /**
     * Get recently published books
     * 
     * @param int $years Number of years back to search
     * @return array Array of Books instances
     */
    public function getRecentlyPublished(int $years = 5): array
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$years} years"));
        
        $conditions = [
            'publication_date' => ['operator' => 'greaterThan', 'value' => $cutoffDate]
        ];
        
        return $this->find($conditions);
    }
    
    /**
     * Check if book has Google Books integration
     * 
     * @return bool True if book has Google Books ID
     */
    public function hasGoogleBooksIntegration(): bool
    {
        return !empty($this->get('google_books_id'));
    }
    
    /**
     * Get display title including subtitle if available
     * 
     * @return string Full title with subtitle
     */
    public function getFullTitle(): string
    {
        $title = $this->get('title') ?? 'Unknown Title';
        $subtitle = $this->get('subtitle');
        
        if ($subtitle) {
            return "{$title}: {$subtitle}";
        }
        
        return $title;
    }
    
    /**
     * Get formatted author list
     * 
     * @return string Formatted author names or "Unknown Author"
     */
    public function getFormattedAuthors(): string
    {
        $authors = $this->get('authors');
        
        if (empty($authors)) {
            return 'Unknown Author';
        }
        
        return $authors;
    }
    
    /**
     * Get formatted publication info
     * 
     * @return string Publication info (Publisher, Year)
     */
    public function getPublicationInfo(): string
    {
        $publisher = $this->get('publisher');
        $date = $this->get('publication_date');
        $year = $date ? substr($date, 0, 4) : null;
        
        $info = [];
        
        if ($publisher) {
            $info[] = $publisher;
        }
        
        if ($year) {
            $info[] = $year;
        }
        
        return implode(', ', $info) ?: 'Publication info not available';
    }
}
