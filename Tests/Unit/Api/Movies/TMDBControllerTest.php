<?php
namespace Tests\Unit\Api\Movies;

use Gravitycar\Tests\TestCase;
use Gravitycar\Api\TMDBController;
use Gravitycar\Api\Request;
use Gravitycar\Services\MovieTMDBIntegrationService;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;

class TMDBControllerTest extends TestCase {
    private TMDBController $controller;
    private MovieTMDBIntegrationService|MockObject $mockService;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Create all required mock dependencies
        $mockLogger = $this->createMock(Logger::class);
        $mockModelFactory = $this->createMock(ModelFactory::class);
        $mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $mockConfig = $this->createMock(Config::class);
        $mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
        $this->mockService = $this->createMock(MovieTMDBIntegrationService::class);
        
        // Create controller with proper dependency injection
        $this->controller = new TMDBController(
            $mockLogger,
            $mockModelFactory,
            $mockDatabaseConnector,
            $mockMetadataEngine,
            $mockConfig,
            $mockCurrentUserProvider,
            $this->mockService
        );
    }
    
    public function testRegisterRoutes(): void {
        $routes = $this->controller->registerRoutes();
        
        $this->assertIsArray($routes);
        $this->assertCount(4, $routes);
        
        // Test first route (GET search)
        $this->assertEquals('GET', $routes[0]['method']);
        $this->assertEquals('/movies/tmdb/search', $routes[0]['path']);
        $this->assertEquals('Gravitycar\\Api\\TMDBController', $routes[0]['apiClass']);
        $this->assertEquals('search', $routes[0]['apiMethod']);
        
        // Test second route (POST search)
        $this->assertEquals('POST', $routes[1]['method']);
        $this->assertEquals('/movies/tmdb/search', $routes[1]['path']);
        $this->assertEquals('Gravitycar\\Api\\TMDBController', $routes[1]['apiClass']);
        $this->assertEquals('searchPost', $routes[1]['apiMethod']);
        
        // Test third route (GET enrich)
        $this->assertEquals('GET', $routes[2]['method']);
        $this->assertEquals('/movies/tmdb/enrich/?', $routes[2]['path']);
        $this->assertEquals('Gravitycar\\Api\\TMDBController', $routes[2]['apiClass']);
        $this->assertEquals('enrich', $routes[2]['apiMethod']);
        
        // Test fourth route (POST refresh)
        $this->assertEquals('POST', $routes[3]['method']);
        $this->assertEquals('/movies/?/tmdb/refresh', $routes[3]['path']);
        $this->assertEquals('Gravitycar\\Api\\TMDBController', $routes[3]['apiClass']);
        $this->assertEquals('refresh', $routes[3]['apiMethod']);
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
        
        // Create mock Request object with tmdbId parameter
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->method('get')->with('tmdbId')->willReturn((string)$tmdbId);
        
        $result = $this->controller->enrich($mockRequest);
        
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
