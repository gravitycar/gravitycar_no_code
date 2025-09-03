<?php
namespace Tests\Integration\Models;

use Gravitycar\Tests\TestCase;
use Gravitycar\Models\movies\Movies;
use Gravitycar\Services\MovieTMDBIntegrationService;
use PHPUnit\Framework\MockObject\MockObject;

class MoviesModelTMDBIntegrationTest extends TestCase {
    private Movies $movie;
    private MovieTMDBIntegrationService|MockObject $mockTMDBService;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->setupTestDatabase();
        
        $this->movie = new Movies();
        $this->mockTMDBService = $this->createMock(MovieTMDBIntegrationService::class);
        
        // Use reflection to inject mock TMDB service
        $reflection = new \ReflectionClass($this->movie);
        $property = $reflection->getProperty('tmdbIntegration');
        $property->setAccessible(true);
        $property->setValue($this->movie, $this->mockTMDBService);
    }
    
    protected function tearDown(): void {
        $this->cleanupTestDatabase();
        parent::tearDown();
    }
    
    public function testSearchTMDBMovies(): void {
        $expectedResult = [
            'exact_match' => [
                'tmdb_id' => 603,
                'title' => 'The Matrix',
                'release_year' => 1999
            ],
            'partial_matches' => [],
            'match_type' => 'exact'
        ];
        
        $this->mockTMDBService
            ->expects($this->once())
            ->method('searchMovie')
            ->with('The Matrix')
            ->willReturn($expectedResult);
        
        $result = $this->movie->searchTMDBMovies('The Matrix');
        
        $this->assertEquals($expectedResult, $result);
    }
    
    public function testEnrichFromTMDB(): void {
        $enrichmentData = [
            'tmdb_id' => 603,
            'synopsis' => 'A computer hacker learns from mysterious rebels about the true nature of his reality.',
            'poster_url' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=vKQi3bBA1y8',
            'obscurity_score' => 2,
            'release_year' => 1999
        ];
        
        $this->mockTMDBService
            ->expects($this->once())
            ->method('enrichMovieData')
            ->with(603)
            ->willReturn($enrichmentData);
        
        // Set up movie with basic data
        $this->movie->set('name', 'The Matrix');
        
        $this->movie->enrichFromTMDB(603);
        
        // Verify fields were set
        $this->assertEquals(603, $this->movie->get('tmdb_id'));
        $this->assertEquals($enrichmentData['synopsis'], $this->movie->get('synopsis'));
        $this->assertEquals($enrichmentData['poster_url'], $this->movie->get('poster_url'));
        $this->assertEquals($enrichmentData['trailer_url'], $this->movie->get('trailer_url'));
        $this->assertEquals($enrichmentData['obscurity_score'], $this->movie->get('obscurity_score'));
        $this->assertEquals($enrichmentData['release_year'], $this->movie->get('release_year'));
    }
    
    public function testEnrichFromTMDBSkipsEmptyValues(): void {
        $enrichmentData = [
            'tmdb_id' => 603,
            'synopsis' => '',  // Empty value should be skipped
            'poster_url' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'trailer_url' => null,  // Null value should be skipped
            'obscurity_score' => 2,
            'release_year' => 1999
        ];
        
        $this->mockTMDBService
            ->expects($this->once())
            ->method('enrichMovieData')
            ->with(603)
            ->willReturn($enrichmentData);
        
        // Set original synopsis that should not be overwritten
        $this->movie->set('synopsis', 'Original synopsis');
        
        $this->movie->enrichFromTMDB(603);
        
        // Verify empty values didn't overwrite existing data
        $this->assertEquals('Original synopsis', $this->movie->get('synopsis'));
        
        // Verify non-empty values were set
        $this->assertEquals(603, $this->movie->get('tmdb_id'));
        $this->assertEquals($enrichmentData['poster_url'], $this->movie->get('poster_url'));
        $this->assertEquals($enrichmentData['obscurity_score'], $this->movie->get('obscurity_score'));
        $this->assertEquals($enrichmentData['release_year'], $this->movie->get('release_year'));
    }
    
    public function testCreateMakesNameFieldReadOnlyAfterSaving(): void {
        // Set up movie data
        $this->movie->set('name', 'The Matrix');
        $this->movie->set('synopsis', 'A computer hacker learns...');
        
        // Get name field before creation
        $nameField = $this->movie->getField('name');
        $this->assertFalse($nameField->isReadOnly(), 'Name field should be editable before creation');
        
        // Create the movie
        $result = $this->movie->create();
        $this->assertTrue($result, 'Movie creation should succeed');
        
        // Check that name field is now read-only
        $nameFieldAfter = $this->movie->getField('name');
        $this->assertTrue($nameFieldAfter->isReadOnly(), 'Name field should be read-only after creation');
    }
    
    public function testCreateWithTMDBEnrichment(): void {
        $enrichmentData = [
            'tmdb_id' => 603,
            'synopsis' => 'A computer hacker learns from mysterious rebels about the true nature of his reality.',
            'poster_url' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=vKQi3bBA1y8',
            'obscurity_score' => 2,
            'release_year' => 1999
        ];
        
        $this->mockTMDBService
            ->expects($this->once())
            ->method('enrichMovieData')
            ->with(603)
            ->willReturn($enrichmentData);
        
        // Set up movie with TMDB enrichment
        $this->movie->set('name', 'The Matrix');
        $this->movie->enrichFromTMDB(603);
        
        // Create the movie
        $result = $this->movie->create();
        $this->assertTrue($result, 'Movie creation with TMDB data should succeed');
        
        // Verify TMDB data was saved
        $savedMovieId = $this->movie->get('id');
        $this->assertNotEmpty($savedMovieId);
        
        // Verify the current movie object has the correct data
        $this->assertEquals('The Matrix', $this->movie->get('name'));
        $this->assertEquals(603, $this->movie->get('tmdb_id'));
        $this->assertEquals($enrichmentData['synopsis'], $this->movie->get('synopsis'));
        $this->assertEquals($enrichmentData['poster_url'], $this->movie->get('poster_url'));
    }
    
    /**
     * Set up test database with movies table
     */
    private function setupTestDatabase(): void {
        if (!$this->db) {
            return;
        }
        
        // Create a test movies table if it doesn't exist
        $sql = "
            CREATE TABLE IF NOT EXISTS movies_test (
                id CHAR(36) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                synopsis TEXT,
                poster_url VARCHAR(500),
                trailer_url VARCHAR(500),
                tmdb_id INT,
                obscurity_score INT,
                release_year INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        
        $this->db->getConnection()->executeStatement($sql);
        
        // Override the table name for testing
        $reflection = new \ReflectionClass($this->movie);
        $property = $reflection->getProperty('tableName');
        $property->setAccessible(true);
        $property->setValue($this->movie, 'movies_test');
    }
    
    /**
     * Clean up test database
     */
    private function cleanupTestDatabase(): void {
        if (!$this->db) {
            return;
        }
        
        $this->db->getConnection()->executeStatement("DROP TABLE IF EXISTS movies_test");
    }
}
