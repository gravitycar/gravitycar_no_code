<?php
namespace Tests\Unit\Api\Movies;

use Gravitycar\Tests\TestCase;
use Gravitycar\Api\Movies\TMDBController;
use Gravitycar\Services\MovieTMDBIntegrationService;
use Gravitycar\Exceptions\GCException;
use PHPUnit\Framework\MockObject\MockObject;

class TMDBControllerTest extends TestCase {
    private TMDBController $controller;
    private MovieTMDBIntegrationService|MockObject $mockService;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->mockService = $this->createMock(MovieTMDBIntegrationService::class);
        $this->controller = new TMDBController();
        
        // Use reflection to inject mock
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('tmdbService');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->mockService);
    }
    
    public function testRegisterRoutes(): void {
        $routes = $this->controller->registerRoutes();
        
        $this->assertIsArray($routes);
        $this->assertCount(2, $routes);
        
        // Check search route
        $searchRoute = $routes[0];
        $this->assertEquals('GET', $searchRoute['method']);
        $this->assertEquals('/movies/tmdb/search', $searchRoute['path']);
        $this->assertEquals('search', $searchRoute['apiMethod']);
        
        // Check enrich route
        $enrichRoute = $routes[1];
        $this->assertEquals('GET', $enrichRoute['method']);
        $this->assertEquals('/movies/tmdb/enrich/?', $enrichRoute['path']);
        $this->assertEquals('enrich', $enrichRoute['apiMethod']);
    }
    
    public function testSearchWithTitle(): void {
        $_GET['title'] = 'The Matrix';
        
        $mockSearchResult = [
            'exact_match' => [
                'tmdb_id' => 603,
                'title' => 'The Matrix',
                'release_year' => 1999
            ],
            'partial_matches' => [],
            'match_type' => 'exact'
        ];
        
        $this->mockService
            ->expects($this->once())
            ->method('searchMovie')
            ->with('The Matrix')
            ->willReturn($mockSearchResult);
        
        // Capture output
        ob_start();
        $result = $this->controller->search();
        $output = ob_get_clean();
        
        $this->assertTrue($result['success']);
        $this->assertEquals($mockSearchResult, $result['data']);
        
        // Clean up
        unset($_GET['title']);
    }
    
    public function testSearchWithoutTitle(): void {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Title parameter is required');
        
        $this->controller->search();
    }
    
    public function testSearchWithEmptyTitle(): void {
        $_GET['title'] = '';
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Title parameter is required');
        
        $this->controller->search();
        
        // Clean up
        unset($_GET['title']);
    }
    
    public function testEnrichWithValidTmdbId(): void {
        $tmdbId = 603;
        $mockEnrichmentData = [
            'tmdb_id' => 603,
            'synopsis' => 'A computer hacker learns from mysterious rebels...',
            'poster_url' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=vKQi3bBA1y8',
            'obscurity_score' => 2,
            'release_year' => 1999
        ];
        
        $this->mockService
            ->expects($this->once())
            ->method('enrichMovieData')
            ->with($tmdbId)
            ->willReturn($mockEnrichmentData);
        
        // Capture output
        ob_start();
        $result = $this->controller->enrich($tmdbId);
        $output = ob_get_clean();
        
        $this->assertTrue($result['success']);
        $this->assertEquals($mockEnrichmentData, $result['data']);
    }
    
    public function testSearchResponseFormat(): void {
        $_GET['title'] = 'Test Movie';
        
        $mockSearchResult = [
            'exact_match' => null,
            'partial_matches' => [
                [
                    'tmdb_id' => 123,
                    'title' => 'Test Movie 1',
                    'release_year' => 2020
                ],
                [
                    'tmdb_id' => 124,
                    'title' => 'Test Movie 2',
                    'release_year' => 2021
                ]
            ],
            'match_type' => 'multiple'
        ];
        
        $this->mockService
            ->expects($this->once())
            ->method('searchMovie')
            ->willReturn($mockSearchResult);
        
        ob_start();
        $result = $this->controller->search();
        $output = ob_get_clean();
        
        // Verify JSON response structure
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertTrue($result['success']);
        
        $data = $result['data'];
        $this->assertArrayHasKey('exact_match', $data);
        $this->assertArrayHasKey('partial_matches', $data);
        $this->assertArrayHasKey('match_type', $data);
        $this->assertEquals('multiple', $data['match_type']);
        $this->assertCount(2, $data['partial_matches']);
        
        // Clean up
        unset($_GET['title']);
    }
}
