<?php
namespace Tests\Integration\Models;

use Gravitycar\Tests\Integration\IntegrationTestCase;
use Gravitycar\Models\movies\Movies;
use Gravitycar\Services\MovieTMDBIntegrationService;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Database\DatabaseConnector;
use PHPUnit\Framework\MockObject\MockObject;

class MoviesModelTMDBIntegrationTest extends IntegrationTestCase {
    private Movies $movie;
    private MovieTMDBIntegrationService|MockObject $mockTMDBService;
    private ModelFactory $modelFactory;
    private array $createdMovieIds = []; // Track created movie IDs for cleanup
    
    protected function setUp(): void {
        parent::setUp();
        
        // Get database connection from ServiceLocator for consistency with integration tests
        $this->db = ServiceLocator::getDatabaseConnector();
        
        $this->setupTestDatabase();
        
        $this->modelFactory = ServiceLocator::getModelFactory();
        $this->movie = $this->modelFactory->new('Movies');
        $this->mockTMDBService = $this->createMock(MovieTMDBIntegrationService::class);
        
        // Use reflection to inject mock TMDB service
        $reflection = new \ReflectionClass($this->movie);
        $property = $reflection->getProperty('tmdbIntegration');
        $property->setAccessible(true);
        $property->setValue($this->movie, $this->mockTMDBService);
    }
    
    protected function tearDown(): void {
        $this->cleanupCreatedRecords();
        $this->cleanupTestDatabase();
        parent::tearDown();
    }
    
    public function testSearchTMDBMovies(): void {
        $expectedResult = [
            'exact_match' => [
                'tmdb_id' => 6030000,
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
            'tmdb_id' => 6030000,
            'synopsis' => 'A computer hacker learns from mysterious rebels about the true nature of his reality.',
            'poster_url' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=vKQi3bBA1y8',
            'obscurity_score' => 2,
            'release_year' => 1999
        ];
        
        $this->mockTMDBService
            ->expects($this->once())
            ->method('enrichMovieData')
            ->with(6030000)
            ->willReturn($enrichmentData);
        
        // Set up movie with basic data
        $this->movie->set('name', 'The Matrix');
        
        $this->movie->enrichFromTMDB(6030000);

        // Verify fields were set
        $this->assertEquals(6030000, $this->movie->get('tmdb_id'));
        $this->assertEquals($enrichmentData['synopsis'], $this->movie->get('synopsis'));
        $this->assertEquals($enrichmentData['poster_url'], $this->movie->get('poster_url'));
        $this->assertEquals($enrichmentData['trailer_url'], $this->movie->get('trailer_url'));
        $this->assertEquals($enrichmentData['obscurity_score'], $this->movie->get('obscurity_score'));
        $this->assertEquals($enrichmentData['release_year'], $this->movie->get('release_year'));
    }
    
    public function testEnrichFromTMDBSkipsEmptyValues(): void {
        $enrichmentData = [
            'tmdb_id' => 6030000,
            'synopsis' => '',  // Empty value should be skipped
            'poster_url' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'trailer_url' => null,  // Null value should be skipped
            'obscurity_score' => 2,
            'release_year' => 1999
        ];
        
        $this->mockTMDBService
            ->expects($this->once())
            ->method('enrichMovieData')
            ->with(6030000)
            ->willReturn($enrichmentData);
        
        // Set original synopsis that should not be overwritten
        $this->movie->set('synopsis', 'Original synopsis');
        
        $this->movie->enrichFromTMDB(6030000);
        
        // Verify empty values didn't overwrite existing data
        $this->assertEquals('Original synopsis', $this->movie->get('synopsis'));
        
        // Verify non-empty values were set
        $this->assertEquals(6030000, $this->movie->get('tmdb_id'));
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
        
        // Track created movie for cleanup
        $movieId = $this->movie->get('id');
        if ($movieId) {
            $this->createdMovieIds[] = $movieId;
        }
        
        // Check that name field is now read-only
        $nameFieldAfter = $this->movie->getField('name');
        $this->assertTrue($nameFieldAfter->isReadOnly(), 'Name field should be read-only after creation');
    }
    
    public function testCreateWithTMDBEnrichment(): void {
        $enrichmentData = [
            'tmdb_id' => 6030000,
            'synopsis' => 'A computer hacker learns from mysterious rebels about the true nature of his reality.',
            'poster_url' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=vKQi3bBA1y8',
            'obscurity_score' => 2,
            'release_year' => 1999
        ];
        
        $this->mockTMDBService
            ->expects($this->once())
            ->method('enrichMovieData')
            ->with(6030000)
            ->willReturn($enrichmentData);
        
        // Set up movie with TMDB enrichment
        $this->movie->set('name', 'The Matrix');
        $this->movie->enrichFromTMDB(6030000);
        
        // Create the movie
        $result = $this->movie->create();
        $this->assertTrue($result, 'Movie creation with TMDB data should succeed');
        
        // Verify TMDB data was saved
        $savedMovieId = $this->movie->get('id');
        $this->assertNotEmpty($savedMovieId);
        
        // Track created movie for cleanup
        if ($savedMovieId) {
            $this->createdMovieIds[] = $savedMovieId;
        }
        
        // Verify the current movie object has the correct data
        $this->assertEquals('The Matrix', $this->movie->get('name'));
        $this->assertEquals(6030000, $this->movie->get('tmdb_id'));
        $this->assertEquals($enrichmentData['synopsis'], $this->movie->get('synopsis'));
        $this->assertEquals($enrichmentData['poster_url'], $this->movie->get('poster_url'));
    }
    
    /**
     * Clean up individual movie records created during tests
     */
    private function cleanupCreatedRecords(): void {
        if (empty($this->createdMovieIds) || !$this->db) {
            return;
        }
        
        foreach ($this->createdMovieIds as $movieId) {
            try {
                // Delete the movie record using the actual movies table
                $sql = "DELETE FROM movies WHERE id = ?";
                $this->db->getConnection()->executeStatement($sql, [$movieId]);
            } catch (\Exception $e) {
                // Log but don't fail the test if cleanup fails
                error_log("Failed to cleanup movie record {$movieId}: " . $e->getMessage());
            }
        }
        
        // Clear the tracking array
        $this->createdMovieIds = [];
    }
    
    /**
     * Set up test database with movies table
     */
    private function setupTestDatabase(): void {
        if (!$this->db) {
            return;
        }
        
        // Ensure the movies table exists with the proper schema
        // This will create the table if it doesn't exist, or do nothing if it does
        $sql = "
            CREATE TABLE IF NOT EXISTS movies (
                id CHAR(36) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                synopsis TEXT,
                poster_url VARCHAR(500),
                trailer_url VARCHAR(500),
                tmdb_id INT,
                obscurity_score INT,
                release_year INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                created_by CHAR(36) NULL,
                updated_by CHAR(36) NULL,
                deleted_by CHAR(36) NULL
            )
        ";
        
        $this->db->getConnection()->executeStatement($sql);
    }
    
    /**
     * Clean up test database
     */
    private function cleanupTestDatabase(): void {
        // No longer dropping the entire table since we're using the real movies table
        // Individual record cleanup is handled in cleanupCreatedRecords()
    }
}
