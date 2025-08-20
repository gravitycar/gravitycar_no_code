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

        // Initialize metadata engine for integration tests using singleton pattern
        // ServiceLocator will handle the dependency injection
        $this->metadataEngine = MetadataEngine::getInstance();

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
        // Check if we're in a transaction and handle appropriately
        $wasInTransaction = $this->connection->isTransactionActive();
        
        if ($wasInTransaction) {
            // Commit current transaction to allow DDL operations
            try {
                $this->connection->commit();
            } catch (\Exception $e) {
                // Transaction might have been rolled back already, ignore
            }
        }
        
        // Drop tables in reverse order to respect foreign key constraints
        $tablesToDrop = array_reverse($this->testTables);
        
        // Disable foreign key checks for cleanup in MySQL
        $platform = $this->connection->getDatabasePlatform();
        if ($platform->getName() === 'mysql') {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        }
        
        foreach ($tablesToDrop as $table) {
            try {
                $this->connection->executeStatement("DROP TABLE IF EXISTS {$table}");
            } catch (\Exception $e) {
                // Table might not exist, continue with cleanup
            }
        }
        
        // Re-enable foreign key checks
        if ($platform->getName() === 'mysql') {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
        
        // Restart transaction for the parent tearDown method
        if ($wasInTransaction) {
            $this->connection->beginTransaction();
            $this->inTransaction = true;
        }
    }

    /**
     * Get database-appropriate auto-increment syntax.
     */
    protected function getAutoIncrementSyntax(): string
    {
        $platform = $this->connection->getDatabasePlatform();
        switch ($platform->getName()) {
            case 'sqlite':
                return 'INTEGER PRIMARY KEY AUTOINCREMENT';
            case 'mysql':
                return 'INT AUTO_INCREMENT PRIMARY KEY';
            default:
                return 'INT AUTO_INCREMENT PRIMARY KEY';
        }
    }

    /**
     * Create a test users table.
     */
    protected function createTestUserTable(): void
    {
        $autoIncrement = $this->getAutoIncrementSyntax();
        $sql = "
            CREATE TABLE IF NOT EXISTS test_users (
                id {$autoIncrement},
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
        $autoIncrement = $this->getAutoIncrementSyntax();
        $sql = "
            CREATE TABLE IF NOT EXISTS test_movies (
                id {$autoIncrement},
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
        $autoIncrement = $this->getAutoIncrementSyntax();
        $sql = "
            CREATE TABLE IF NOT EXISTS test_movie_quotes (
                id {$autoIncrement},
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
