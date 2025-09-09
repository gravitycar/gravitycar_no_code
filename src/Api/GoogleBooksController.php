<?php

namespace Gravitycar\Api;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Api\Request;
use Gravitycar\Services\GoogleBooksApiService;
use Gravitycar\Services\BookGoogleBooksIntegrationService;
use Gravitycar\Exceptions\GCException;

/**
 * GoogleBooksController
 * API controller for Google Books integration endpoints
 */
class GoogleBooksController extends ApiControllerBase
{
    private GoogleBooksApiService $googleBooksService;
    private BookGoogleBooksIntegrationService $integrationService;
    
    public function __construct()
    {
        parent::__construct();
        $this->googleBooksService = new GoogleBooksApiService();
        $this->integrationService = new BookGoogleBooksIntegrationService();
    }
    
    /**
     * Register API routes for Google Books endpoints
     * 
     * @return array Array of route definitions
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/google-books/search',
                'apiClass' => self::class,
                'apiMethod' => 'searchBooks',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/google-books/search-isbn',
                'apiClass' => self::class,
                'apiMethod' => 'searchByISBN',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/google-books/details/?',
                'apiClass' => self::class,
                'apiMethod' => 'getBookDetails',
                'parameterNames' => ['volumeId']
            ],
            [
                'method' => 'GET',
                'path' => '/google-books/enrich/?',
                'apiClass' => self::class,
                'apiMethod' => 'enrichBookDataGet',
                'parameterNames' => ['googleBooksId']
            ],
            [
                'method' => 'POST',
                'path' => '/google-books/enrich',
                'apiClass' => self::class,
                'apiMethod' => 'enrichBookData',
                'parameterNames' => []
            ]
        ];
    }
    
    /**
     * Search for books using Google Books API
     * 
     * @param Request $request HTTP request object
     * @return array API response
     */
    public function searchBooks(Request $request): array
    {
        try {
            $query = $request->get('q') ?? $request->get('query');
            $title = $request->get('title');
            $author = $request->get('author');
            $maxResults = (int)($request->get('maxResults') ?? 10);
            $startIndex = (int)($request->get('startIndex') ?? 0);
            
            // Validate input
            if (empty($query) && empty($title)) {
                throw new GCException('Search query or title is required');
            }
            
            // Limit maxResults to prevent abuse
            $maxResults = min($maxResults, 40);
            
            // If we have title and author, use integration service for better matching
            if ($title) {
                $results = $this->integrationService->searchBook($title, $author);
                
                // Combine exact match and partial matches, with exact match first
                $allBooks = [];
                if (!empty($results['exact_match'])) {
                    $allBooks[] = $results['exact_match'];
                }
                if (!empty($results['partial_matches'])) {
                    // Add partial matches, but avoid duplicating the exact match
                    foreach ($results['partial_matches'] as $book) {
                        $isDuplicate = false;
                        if (!empty($results['exact_match'])) {
                            $isDuplicate = ($book['google_books_id'] === $results['exact_match']['google_books_id']);
                        }
                        if (!$isDuplicate) {
                            $allBooks[] = $book;
                        }
                    }
                }
                
                // Limit to first 10 results
                $allBooks = array_slice($allBooks, 0, 10);
                
                return [
                    'success' => true,
                    'data' => [
                        'books' => $allBooks,
                        'exact_match' => $results['exact_match'],
                        'match_type' => $results['match_type'],
                        'total_results' => count($allBooks),
                        'query' => $title . ($author ? " by {$author}" : '')
                    ]
                ];
            }
            
            // Otherwise use direct API search
            $books = $this->googleBooksService->searchBooks($query, $maxResults, $startIndex);
            
            return [
                'success' => true,
                'data' => [
                    'books' => $books,
                    'total_results' => count($books),
                    'query' => $query,
                    'start_index' => $startIndex,
                    'max_results' => $maxResults
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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new GCException('An unexpected error occurred during book search');
        }
    }
    
    /**
     * Search for books by ISBN
     * 
     * @param Request $request HTTP request object
     * @return array API response
     */
    public function searchByISBN(Request $request): array
    {
        try {
            $isbn = $request->get('isbn');
            
            if (empty($isbn)) {
                throw new GCException('ISBN is required');
            }
            
            $results = $this->integrationService->searchByISBN($isbn);
            
            return [
                'success' => true,
                'data' => [
                    'books' => $results['partial_matches'],
                    'exact_match' => $results['exact_match'],
                    'match_type' => $results['match_type'],
                    'total_results' => $results['total_results'],
                    'isbn' => $isbn
                ]
            ];
            
        } catch (GCException $e) {
            $this->logger->error('Google Books ISBN search failed', [
                'isbn' => $request->get('isbn'),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during Google Books ISBN search', [
                'isbn' => $request->get('isbn'),
                'error' => $e->getMessage()
            ]);
            
            throw new GCException('An unexpected error occurred during ISBN search');
        }
    }
    
    /**
     * Get detailed information about a specific book
     * 
     * @param Request $request HTTP request object
     * @return array API response
     */
    public function getBookDetails(Request $request): array
    {
        try {
            $volumeId = $request->get('volumeId');
            
            if (empty($volumeId)) {
                throw new GCException('Volume ID is required');
            }
            
            $bookDetails = $this->googleBooksService->getBookDetails($volumeId);
            
            return [
                'success' => true,
                'data' => [
                    'book' => $bookDetails,
                    'volume_id' => $volumeId
                ]
            ];
            
        } catch (GCException $e) {
            $this->logger->error('Google Books details retrieval failed', [
                'volume_id' => $request->get('volumeId'),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during Google Books details retrieval', [
                'volume_id' => $request->get('volumeId'),
                'error' => $e->getMessage()
            ]);
            
            throw new GCException('An unexpected error occurred while retrieving book details');
        }
    }
    
    /**
     * Enrich book data from Google Books (GET method with ID in path)
     * 
     * @param Request $request HTTP request object
     * @return array API response
     */
    public function enrichBookDataGet(Request $request): array
    {
        try {
            $googleBooksId = $request->get('googleBooksId');
            
            if (empty($googleBooksId)) {
                throw new GCException('Google Books ID is required');
            }
            
            $enrichedData = $this->integrationService->enrichBookData($googleBooksId);
            
            return [
                'success' => true,
                'data' => [
                    'enriched_data' => $enrichedData,
                    'google_books_id' => $googleBooksId
                ]
            ];
            
        } catch (GCException $e) {
            $this->logger->error('Google Books data enrichment failed (GET)', [
                'google_books_id' => $request->get('googleBooksId'),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during Google Books data enrichment (GET)', [
                'google_books_id' => $request->get('googleBooksId'),
                'error' => $e->getMessage()
            ]);
            
            throw new GCException('An unexpected error occurred during book data enrichment');
        }
    }

    /**
     * Enrich book data from Google Books
     * 
     * @param Request $request HTTP request object
     * @return array API response
     */
    public function enrichBookData(Request $request): array
    {
        try {
            $googleBooksId = $request->get('google_books_id');
            
            if (empty($googleBooksId)) {
                throw new GCException('Google Books ID is required');
            }
            
            $enrichedData = $this->integrationService->enrichBookData($googleBooksId);
            
            return [
                'success' => true,
                'data' => [
                    'enriched_data' => $enrichedData,
                    'google_books_id' => $googleBooksId
                ]
            ];
            
        } catch (GCException $e) {
            $this->logger->error('Google Books data enrichment failed', [
                'google_books_id' => $request->get('google_books_id'),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during Google Books data enrichment', [
                'google_books_id' => $request->get('google_books_id'),
                'error' => $e->getMessage()
            ]);
            
            throw new GCException('An unexpected error occurred during book data enrichment');
        }
    }
}
