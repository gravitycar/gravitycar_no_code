<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\TMDBApiService;
use Gravitycar\Core\Config;
use Psr\Log\LoggerInterface;
use Gravitycar\Exceptions\GCException;

/**
 * TMDBApiServiceTest with HTTP Mocking
 * 
 * This test class uses a testable TMDBApiService subclass to mock HTTP requests
 * instead of making real API calls to TMDB. This approach ensures tests are
 * fast, reliable, and don't depend on external services.
 */
class TMDBApiServiceTest extends TestCase
{
    private TestableHTTPTMDBApiService $tmdbService;
    private Config|MockObject $mockConfig;
    private LoggerInterface|MockObject $mockLogger;
    
    protected function setUp(): void
    {
        // Create mocks
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Configure mock config to return test API key
        $this->mockConfig->method('getEnv')->willReturnMap([
            ['TMDB_API_KEY', null, 'test_api_key'],
            ['TMDB_API_READ_ACCESS_TOKEN', null, 'test_access_token']
        ]);
        
        // Create testable service with injected dependencies and HTTP mocking capability
        $this->tmdbService = new TestableHTTPTMDBApiService($this->mockConfig, $this->mockLogger);
    }
    
    
    public function testSearchMoviesWithValidQuery(): void
    {
        // Mock successful search response for "Matrix"
        $mockSearchResponse = [
            'results' => [
                [
                    'id' => 603,
                    'title' => 'The Matrix',
                    'release_date' => '1999-03-31',
                    'overview' => 'A computer hacker learns from mysterious rebels about the true nature of his reality and his role in the war against its controllers.',
                    'poster_path' => '/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
                    'popularity' => 85.965,
                    'adult' => false
                ],
                [
                    'id' => 604,
                    'title' => 'The Matrix Reloaded',
                    'release_date' => '2003-05-15',
                    'overview' => 'Six months after the events depicted in The Matrix, Neo has proved to be a good omen for the free humans.',
                    'poster_path' => '/9TGHDvWrqKBzwDxDodHYXEmOE6J.jpg',
                    'popularity' => 42.123,
                    'adult' => false
                ]
            ]
        ];
        
        $this->tmdbService->setMockResponse($mockSearchResponse);
        
        $results = $this->tmdbService->searchMovies('Matrix');
        
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertCount(2, $results);
        
        // Check structure of first result
        $firstResult = $results[0];
        $this->assertArrayHasKey('tmdb_id', $firstResult);
        $this->assertArrayHasKey('title', $firstResult);
        $this->assertArrayHasKey('release_year', $firstResult);
        $this->assertArrayHasKey('overview', $firstResult);
        $this->assertArrayHasKey('poster_url', $firstResult);
        $this->assertArrayHasKey('obscurity_score', $firstResult);
        $this->assertArrayHasKey('popularity', $firstResult);
        
        // Validate actual values
        $this->assertEquals(603, $firstResult['tmdb_id']);
        $this->assertEquals('The Matrix', $firstResult['title']);
        $this->assertEquals(1999, $firstResult['release_year']);
        $this->assertEquals('https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg', $firstResult['poster_url']);
        
        // Validate obscurity score range
        $this->assertGreaterThanOrEqual(1, $firstResult['obscurity_score']);
        $this->assertLessThanOrEqual(5, $firstResult['obscurity_score']);
    }
    
    public function testSearchMoviesWithEmptyQuery(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Search query cannot be empty');
        
        $this->tmdbService->searchMovies('');
    }
    
    public function testSearchMoviesWithNetworkError(): void
    {
        // Mock network failure
        $this->tmdbService->setMockException(
            new GCException('Failed to connect to TMDB API', ['error' => 'Connection timeout'])
        );
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Failed to connect to TMDB API');
        
        $this->tmdbService->searchMovies('Matrix');
    }
    
    public function testSearchMoviesWithInvalidJsonResponse(): void
    {
        // Mock invalid JSON response scenario
        $this->tmdbService->setMockException(
            new GCException('Invalid JSON response from TMDB API', ['json_error' => 'Syntax error'])
        );
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Invalid JSON response from TMDB API');
        
        $this->tmdbService->searchMovies('Matrix');
    }
    
    public function testSearchMoviesWithApiError(): void
    {
        // Mock TMDB API error response
        $mockErrorResponse = [
            'success' => false,
            'status_code' => 401,
            'status_message' => 'Invalid API key: You must be granted a valid key.'
        ];
        
        $this->tmdbService->setMockResponse($mockErrorResponse);
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('TMDB API error: Invalid API key: You must be granted a valid key.');
        
        $this->tmdbService->searchMovies('Matrix');
    }
    
    public function testGetMovieDetailsWithNetworkError(): void
    {
        // Mock network failure for movie details
        $this->tmdbService->setMockException(
            new GCException('Failed to connect to TMDB API', ['error' => 'Connection timeout'])
        );
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Failed to connect to TMDB API');
        
        $this->tmdbService->getMovieDetails(603);
    }
    
    public function testGetMovieDetailsWithValidId(): void
    {
        // Mock movie details response for The Matrix (ID: 603)
        $mockDetailsResponse = [
            'id' => 603,
            'title' => 'The Matrix',
            'release_date' => '1999-03-31',
            'overview' => 'A computer hacker learns from mysterious rebels about the true nature of his reality and his role in the war against its controllers.',
            'poster_path' => '/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'backdrop_path' => '/fNG7i7RqMErkcqhohV2a6cV1Ehy.jpg',
            'popularity' => 85.965,
            'runtime' => 136,
            'imdb_id' => 'tt0133093',
            'genres' => [
                ['id' => 28, 'name' => 'Action'],
                ['id' => 878, 'name' => 'Science Fiction']
            ],
            'videos' => [
                'results' => [
                    [
                        'type' => 'Trailer',
                        'site' => 'YouTube',
                        'key' => 'vKQi3bBA1y8',
                        'official' => true
                    ]
                ]
            ]
        ];
        
        $this->tmdbService->setMockResponse($mockDetailsResponse);
        
        $details = $this->tmdbService->getMovieDetails(603);
        
        $this->assertIsArray($details);
        
        // Check required fields
        $this->assertArrayHasKey('tmdb_id', $details);
        $this->assertArrayHasKey('title', $details);
        $this->assertArrayHasKey('release_year', $details);
        $this->assertArrayHasKey('overview', $details);
        $this->assertArrayHasKey('poster_url', $details);
        $this->assertArrayHasKey('backdrop_url', $details);
        $this->assertArrayHasKey('trailer_url', $details);
        $this->assertArrayHasKey('obscurity_score', $details);
        $this->assertArrayHasKey('popularity', $details);
        $this->assertArrayHasKey('genres', $details);
        $this->assertArrayHasKey('runtime', $details);
        $this->assertArrayHasKey('imdb_id', $details);
        
        // Validate specific values for The Matrix
        $this->assertEquals(603, $details['tmdb_id']);
        $this->assertEquals('The Matrix', $details['title']);
        $this->assertEquals(1999, $details['release_year']);
        $this->assertEquals('https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg', $details['poster_url']);
        $this->assertEquals('https://image.tmdb.org/t/p/w1280/fNG7i7RqMErkcqhohV2a6cV1Ehy.jpg', $details['backdrop_url']);
        $this->assertEquals('https://www.youtube.com/watch?v=vKQi3bBA1y8', $details['trailer_url']);
        $this->assertEquals(136, $details['runtime']);
        $this->assertEquals('tt0133093', $details['imdb_id']);
        $this->assertIsArray($details['genres']);
        $this->assertNotEmpty($details['genres']);
        
        // Validate obscurity score range
        $this->assertGreaterThanOrEqual(1, $details['obscurity_score']);
        $this->assertLessThanOrEqual(5, $details['obscurity_score']);
    }
    
    public function testObscurityScoreCalculation(): void
    {
        // Test different popularity ranges using reflection
        $reflection = new \ReflectionClass($this->tmdbService);
        $method = $reflection->getMethod('calculateObscurityScore');
        $method->setAccessible(true);
        
        // Test popularity score mappings
        $this->assertEquals(1, $method->invoke($this->tmdbService, 50)); // Very well known
        $this->assertEquals(2, $method->invoke($this->tmdbService, 25)); // Well known
        $this->assertEquals(3, $method->invoke($this->tmdbService, 15)); // Moderately known
        $this->assertEquals(4, $method->invoke($this->tmdbService, 7));  // Somewhat obscure
        $this->assertEquals(5, $method->invoke($this->tmdbService, 2));  // Very obscure
    }
    
    public function testImageUrlBuilding(): void
    {
        $reflection = new \ReflectionClass($this->tmdbService);
        $method = $reflection->getMethod('buildImageUrl');
        $method->setAccessible(true);
        
        // Test valid path
        $url = $method->invoke($this->tmdbService, '/abc123.jpg', 'w500');
        $this->assertEquals('https://image.tmdb.org/t/p/w500/abc123.jpg', $url);
        
        // Test null path
        $url = $method->invoke($this->tmdbService, null, 'w500');
        $this->assertNull($url);
        
        // Test empty path
        $url = $method->invoke($this->tmdbService, '', 'w500');
        $this->assertNull($url);
    }
    
    public function testYearExtraction(): void
    {
        $reflection = new \ReflectionClass($this->tmdbService);
        $method = $reflection->getMethod('extractYear');
        $method->setAccessible(true);
        
        // Test valid date
        $year = $method->invoke($this->tmdbService, '1999-03-31');
        $this->assertEquals(1999, $year);
        
        // Test empty date
        $year = $method->invoke($this->tmdbService, '');
        $this->assertNull($year);
        
        // Test invalid year
        $year = $method->invoke($this->tmdbService, '0001-01-01');
        $this->assertNull($year);
    }
    
    public function testOverviewTruncation(): void
    {
        $reflection = new \ReflectionClass($this->tmdbService);
        $method = $reflection->getMethod('truncateOverview');
        $method->setAccessible(true);
        
        // Test long overview
        $longOverview = 'First sentence. Second sentence. Third sentence. Fourth sentence. Fifth sentence. Sixth sentence.';
        $truncated = $method->invoke($this->tmdbService, $longOverview);
        
        // Should have maximum 4 sentences
        $sentenceCount = substr_count($truncated, '.');
        $this->assertLessThanOrEqual(4, $sentenceCount);
        $this->assertStringContainsString('First sentence', $truncated);
        $this->assertStringContainsString('Fourth sentence', $truncated);
        $this->assertStringNotContainsString('Fifth sentence', $truncated);
        
        // Test empty overview
        $empty = $method->invoke($this->tmdbService, '');
        $this->assertEquals('', $empty);
    }
    
    public function testPosterSizes(): void
    {
        $sizes = $this->tmdbService->getPosterSizes();
        $expectedSizes = ['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'];
        
        $this->assertEquals($expectedSizes, $sizes);
    }
    
    public function testBackdropSizes(): void
    {
        $sizes = $this->tmdbService->getBackdropSizes();
        $expectedSizes = ['w300', 'w780', 'w1280', 'original'];
        
        $this->assertEquals($expectedSizes, $sizes);
    }
}

/**
 * TestableHTTPTMDBApiService
 * 
 * Test-specific subclass of TMDBApiService that allows mocking HTTP responses
 * without making real API calls. This enables fast, reliable unit tests.
 */
class TestableHTTPTMDBApiService extends TMDBApiService
{
    private ?array $mockResponse = null;
    private bool $shouldThrowException = false;
    private ?GCException $exceptionToThrow = null;
    
    /**
     * Set a mock response for the next API call
     * 
     * @param array $response Mock response data
     */
    public function setMockResponse(array $response): void
    {
        $this->mockResponse = $response;
        $this->shouldThrowException = false;
        $this->exceptionToThrow = null;
    }
    
    /**
     * Set an exception to throw on the next API call
     * 
     * @param GCException $exception Exception to throw
     */
    public function setMockException(GCException $exception): void
    {
        $this->shouldThrowException = true;
        $this->exceptionToThrow = $exception;
        $this->mockResponse = null;
    }
    
    /**
     * Override makeApiRequest to use mocked responses instead of real HTTP calls
     * 
     * @param string $url API endpoint URL
     * @param array $params Query parameters
     * @return array Mocked response data
     * @throws GCException If configured to throw an exception
     */
    protected function makeApiRequest(string $url, array $params = []): array
    {
        // If configured to throw an exception, do so
        if ($this->shouldThrowException && $this->exceptionToThrow) {
            throw $this->exceptionToThrow;
        }
        
        // If we have a mock response, return it
        if ($this->mockResponse !== null) {
            // Check for API error responses and handle them like the parent class does
            if (isset($this->mockResponse['success']) && $this->mockResponse['success'] === false) {
                throw new GCException('TMDB API error: ' . ($this->mockResponse['status_message'] ?? 'Unknown error'), [
                    'tmdb_error' => $this->mockResponse
                ]);
            }
            
            return $this->mockResponse;
        }
        
        // Default fallback - should not happen in tests
        throw new GCException('No mock response configured for test');
    }
}
