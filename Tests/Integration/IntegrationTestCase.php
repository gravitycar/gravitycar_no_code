<?php

namespace Gravitycar\Tests\Integration;

use Gravitycar\Tests\Unit\DatabaseTestCase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Core\Config;

/**
 * Base class for integration tests.
 * Tests interactions between multiple components working together.
 */
abstract class IntegrationTestCase extends DatabaseTestCase
{
    protected MetadataEngine $metadataEngine;
    protected array $testTables = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize metadata engine for integration tests with correct parameters
        // Use test-specific directories that don't need to exist for basic integration tests
        $this->metadataEngine = new MetadataEngine(
            $this->logger,
            'tests/fixtures/models',     // modelsDirPath
            'tests/fixtures/relationships', // relationshipsDirPath
            'tests/cache'               // cacheDirPath
        );

        // For testing, we'll skip service locator registration since the container may be locked
        // Integration tests will work with direct object instances instead

        // Set up test database schema if needed
        $this->setUpTestSchema();
    }

    protected function tearDown(): void
    {
        // Clean up test tables
        $this->cleanUpTestSchema();

        parent::tearDown();
    }

    /**
     * Set up test database schema for integration tests.
     */
    protected function setUpTestSchema(): void
    {
        // Create test tables that might be needed for integration tests
        $this->createTestUserTable();
        $this->createTestMovieTable();
        $this->createTestMovieQuoteTable();
    }

    /**
     * Clean up test database schema.
     */
    protected function cleanUpTestSchema(): void
    {
        foreach ($this->testTables as $table) {
            $this->connection->executeStatement("DROP TABLE IF EXISTS {$table}");
        }
    }

    /**
     * Create a test users table.
     */
    protected function createTestUserTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(50),
                last_name VARCHAR(50),
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->connection->executeStatement($sql);
        $this->testTables[] = 'test_users';
    }

    /**
     * Create a test movies table.
     */
    protected function createTestMovieTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS test_movies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                director VARCHAR(100),
                release_year INTEGER,
                genre VARCHAR(50),
                rating VARCHAR(10),
                duration_minutes INTEGER,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->connection->executeStatement($sql);
        $this->testTables[] = 'test_movies';
    }

    /**
     * Create a test movie quotes table.
     */
    protected function createTestMovieQuoteTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS test_movie_quotes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                movie_id INTEGER NOT NULL,
                character_name VARCHAR(100),
                quote_text TEXT NOT NULL,
                scene_description TEXT,
                difficulty_level VARCHAR(10) DEFAULT 'medium',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (movie_id) REFERENCES test_movies(id) ON DELETE CASCADE
            )
        ";
        $this->connection->executeStatement($sql);
        $this->testTables[] = 'test_movie_quotes';
    }

    /**
     * Assert that a complete workflow executed successfully.
     */
    protected function assertWorkflowSuccess(array $expectedResults): void
    {
        foreach ($expectedResults as $table => $expectedCount) {
            $result = $this->connection->executeQuery("SELECT COUNT(*) FROM {$table}");
            $actualCount = $result->fetchOne();
            $this->assertEquals($expectedCount, $actualCount,
                "Expected {$expectedCount} records in {$table}, but found {$actualCount}");
        }
    }

    /**
     * Execute a complete CRUD workflow and assert results.
     */
    protected function executeCrudWorkflow(string $model, array $testData): array
    {
        // This will be implemented as we build out the model system
        // For now, return empty array
        return [];
    }
}
