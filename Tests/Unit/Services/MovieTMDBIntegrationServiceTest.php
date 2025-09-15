<?php
namespace Tests\Unit\Services;

use Gravitycar\Tests\TestCase;
use Gravitycar\Services\MovieTMDBIntegrationService;
use Gravitycar\Services\TMDBApiService;
use PHPUnit\Framework\MockObject\MockObject;

class MovieTMDBIntegrationServiceTest extends TestCase {
    private MovieTMDBIntegrationService $service;
    private TMDBApiService|MockObject $mockTMDBApi;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->mockTMDBApi = $this->createMock(TMDBApiService::class);
        
        // Create service with injected dependency
        $this->service = new MovieTMDBIntegrationService($this->mockTMDBApi);
    }
    
    public function testSearchMovieExactMatch(): void {
        $mockResults = [
            [
                'id' => 603,
                'title' => 'The Matrix',
                'release_date' => '1999-03-30',
                'overview' => 'A computer hacker learns from mysterious rebels...',
                'poster_path' => '/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
                'vote_average' => 8.2,
                'popularity' => 45.5
            ]
        ];
        
        $this->mockTMDBApi
            ->expects($this->once())
            ->method('searchMovies')
            ->with('The Matrix')
            ->willReturn($mockResults);
        
        $result = $this->service->searchMovie('The Matrix');
        
        $this->assertEquals('exact', $result['match_type']);
        $this->assertNotNull($result['exact_match']);
        $this->assertEquals('The Matrix', $result['exact_match']['title']);
        $this->assertIsArray($result['partial_matches']);
    }
    
    public function testSearchMovieMultipleMatches(): void {
        $mockResults = [
            [
                'id' => 603,
                'title' => 'The Matrix',
                'release_date' => '1999-03-30',
                'overview' => 'A computer hacker learns...',
                'poster_path' => '/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
                'vote_average' => 8.2,
                'popularity' => 45.5
            ],
            [
                'id' => 604,
                'title' => 'The Matrix Reloaded',
                'release_date' => '2003-05-15',
                'overview' => 'Neo and his allies reveal...',
                'poster_path' => '/9TGHDvWrqKBzwDxDodHYXEmOE6J.jpg',
                'vote_average' => 7.2,
                'popularity' => 35.2
            ]
        ];
        
        $this->mockTMDBApi
            ->expects($this->once())
            ->method('searchMovies')
            ->with('Star Wars')
            ->willReturn($mockResults);
        
        $result = $this->service->searchMovie('Star Wars');
        
        $this->assertEquals('multiple', $result['match_type']);
        $this->assertNull($result['exact_match']);
        $this->assertCount(2, $result['partial_matches']);
    }
    
    public function testSearchMovieNoResults(): void {
        $this->mockTMDBApi
            ->expects($this->once())
            ->method('searchMovies')
            ->with('NonexistentMovieTitle12345')
            ->willReturn([]);
        
        $result = $this->service->searchMovie('NonexistentMovieTitle12345');
        
        $this->assertEquals('none', $result['match_type']);
        $this->assertNull($result['exact_match']);
        $this->assertEmpty($result['partial_matches']);
    }
    
    public function testEnrichMovieData(): void {
        $mockMovieDetails = [
            'id' => 603,
            'title' => 'The Matrix',
            'overview' => 'A computer hacker learns from mysterious rebels about the true nature of his reality.',
            'poster_path' => '/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'release_date' => '1999-03-30',
            'vote_average' => 8.2,
            'popularity' => 45.5,
            'videos' => [
                'results' => [
                    [
                        'type' => 'Trailer',
                        'site' => 'YouTube',
                        'key' => 'vKQi3bBA1y8'
                    ]
                ]
            ]
        ];
        
        $this->mockTMDBApi
            ->expects($this->once())
            ->method('getMovieDetails')
            ->with(603)
            ->willReturn($mockMovieDetails);
        
        $result = $this->service->enrichMovieData(603);
        
        $this->assertEquals(603, $result['tmdb_id']);
        $this->assertArrayHasKey('synopsis', $result);
        $this->assertArrayHasKey('poster_url', $result);
        $this->assertArrayHasKey('release_year', $result);
        $this->assertArrayHasKey('obscurity_score', $result);
        $this->assertArrayHasKey('trailer_url', $result);
    }
    
    public function testNormalizeTitleCaseInsensitive(): void {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizeTitle');
        $method->setAccessible(true);
        
        $title1 = $method->invoke($this->service, 'The Matrix');
        $title2 = $method->invoke($this->service, 'the matrix');
        $title3 = $method->invoke($this->service, 'THE MATRIX');
        
        $this->assertEquals($title1, $title2);
        $this->assertEquals($title2, $title3);
    }
    
    public function testNormalizeTitleRemovesPunctuation(): void {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizeTitle');
        $method->setAccessible(true);
        
        $title1 = $method->invoke($this->service, 'Spider-Man: No Way Home');
        $title2 = $method->invoke($this->service, 'Spider Man No Way Home');
        
        $this->assertEquals('spider man no way home', $title1);
        $this->assertEquals($title1, $title2);
    }
}
