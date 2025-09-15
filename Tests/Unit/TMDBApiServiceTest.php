<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Services\TMDBApiService;
use Gravitycar\Core\Config;
use Psr\Log\LoggerInterface;
use Gravitycar\Exceptions\GCException;

class TMDBApiServiceTest extends TestCase
{
    private TMDBApiService $tmdbService;
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
        
        // Create service with injected dependencies
        $this->tmdbService = new TMDBApiService($this->mockConfig, $this->mockLogger);
    }
    
    public function testSearchMoviesWithValidQuery(): void
    {
        $results = $this->tmdbService->searchMovies('Matrix');
        
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        
        // Check structure of first result
        $firstResult = $results[0];
        $this->assertArrayHasKey('tmdb_id', $firstResult);
        $this->assertArrayHasKey('title', $firstResult);
        $this->assertArrayHasKey('release_year', $firstResult);
        $this->assertArrayHasKey('overview', $firstResult);
        $this->assertArrayHasKey('poster_url', $firstResult);
        $this->assertArrayHasKey('obscurity_score', $firstResult);
        $this->assertArrayHasKey('popularity', $firstResult);
        
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
    
    public function testGetMovieDetailsWithValidId(): void
    {
        // Using The Matrix (ID: 603) as test case
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
