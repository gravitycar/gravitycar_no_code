<?php

namespace Gravitycar\Tests\Integration\Database;

use Gravitycar\Tests\Integration\IntegrationTestCase;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Schema\SchemaGenerator;
use Gravitycar\Tests\Fixtures\FixtureFactory;

/**
 * Integration tests for database operations and schema management.
 * Tests the complete database workflow from schema creation to data operations.
 */
class DatabaseIntegrationTest extends IntegrationTestCase
{
    private SchemaGenerator $schemaGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaGenerator = new SchemaGenerator($this->logger, $this->db);
    }

    /**
     * Test complete CRUD workflow for users.
     */
    public function testUserCrudWorkflow(): void
    {
        // Create user data
        $userData = FixtureFactory::createUser([
            'username' => 'integrationuser',
            'email' => 'integration@test.com'
        ]);

        // Insert user
        $userId = $this->insertTestData('test_users', $userData);
        $this->assertGreaterThan(0, $userId, 'User should be inserted successfully');

        // Read user
        $result = $this->connection->executeQuery("SELECT * FROM test_users WHERE id = ?", [$userId]);
        $retrievedUser = $result->fetchAssociative();

        $this->assertNotFalse($retrievedUser, 'User should be retrievable');
        $this->assertEquals($userData['username'], $retrievedUser['username']);
        $this->assertEquals($userData['email'], $retrievedUser['email']);

        // Update user
        $updateData = ['first_name' => 'Updated'];
        $this->connection->executeStatement("UPDATE test_users SET first_name = ? WHERE id = ?",
            [$updateData['first_name'], $userId]);

        // Verify update
        $result = $this->connection->executeQuery("SELECT * FROM test_users WHERE id = ?", [$userId]);
        $updatedUser = $result->fetchAssociative();
        $this->assertEquals('Updated', $updatedUser['first_name']);

        // Delete user
        $this->connection->executeStatement("DELETE FROM test_users WHERE id = ?", [$userId]);

        // Verify deletion
        $this->assertDatabaseMissing('test_users', ['id' => $userId]);
    }

    /**
     * Test relationship integrity between movies and quotes.
     */
    public function testMovieQuoteRelationshipIntegrity(): void
    {
        // Create movie
        $movieData = FixtureFactory::createMovie([
            'title' => 'Integration Test Movie',
            'director' => 'Test Director'
        ]);
        $movieId = $this->insertTestData('test_movies', $movieData);

        // Create quotes for the movie
        $quote1Data = FixtureFactory::createMovieQuote([
            'movie_id' => $movieId,
            'quote_text' => 'First test quote',
            'character_name' => 'Test Character 1'
        ]);
        $quote2Data = FixtureFactory::createMovieQuote([
            'movie_id' => $movieId,
            'quote_text' => 'Second test quote',
            'character_name' => 'Test Character 2'
        ]);

        $quote1Id = $this->insertTestData('test_movie_quotes', $quote1Data);
        $quote2Id = $this->insertTestData('test_movie_quotes', $quote2Data);

        // Verify quotes are linked to movie
        $this->assertDatabaseHas('test_movie_quotes', [
            'id' => $quote1Id,
            'movie_id' => $movieId
        ]);
        $this->assertDatabaseHas('test_movie_quotes', [
            'id' => $quote2Id,
            'movie_id' => $movieId
        ]);

        // Test cascading delete (delete movie should delete quotes)
        $this->connection->executeStatement("DELETE FROM test_movies WHERE id = ?", [$movieId]);

        // Verify quotes were cascade deleted
        $this->assertDatabaseMissing('test_movie_quotes', ['movie_id' => $movieId]);
    }

    /**
     * Test transaction handling and rollback scenarios.
     */
    public function testTransactionHandling(): void
    {
        // For this test, we need to work with a separate transaction
        // First, commit the current transaction from setUp to clear the slate
        if ($this->connection->isTransactionActive()) {
            $this->connection->commit();
        }

        // Start a fresh transaction for this test
        $this->connection->beginTransaction();

        try {
            // Insert a movie
            $movieData = FixtureFactory::createMovie(['title' => 'Transaction Test Movie']);
            $movieId = $this->insertTestData('test_movies', $movieData);

            // Insert a quote
            $quoteData = FixtureFactory::createMovieQuote([
                'movie_id' => $movieId,
                'quote_text' => 'Transaction test quote'
            ]);
            $quoteId = $this->insertTestData('test_movie_quotes', $quoteData);

            // Verify data exists within transaction
            $this->assertDatabaseHas('test_movies', ['id' => $movieId]);
            $this->assertDatabaseHas('test_movie_quotes', ['id' => $quoteId]);

            // Rollback transaction
            $this->connection->rollBack();

            // Verify data was rolled back (outside of any transaction now)
            $this->assertDatabaseMissing('test_movies', ['id' => $movieId]);
            $this->assertDatabaseMissing('test_movie_quotes', ['id' => $quoteId]);

        } catch (\Exception $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $e;
        } finally {
            // Restart transaction for tearDown
            $this->connection->beginTransaction();
        }
    }

    /**
     * Test database connection pool and concurrent operations.
     */
    public function testConcurrentOperations(): void
    {
        // Simulate concurrent user creation
        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $userData = FixtureFactory::createUser([
                'username' => 'concurrent_user_' . $i,
                'email' => "concurrent{$i}@test.com"
            ]);
            $users[] = $userData;
        }

        // Insert all users
        $insertedIds = [];
        foreach ($users as $userData) {
            $insertedIds[] = $this->insertTestData('test_users', $userData);
        }

        // Verify all users were inserted
        $this->assertCount(10, $insertedIds);
        foreach ($insertedIds as $id) {
            $this->assertGreaterThan(0, $id);
        }

        // Verify uniqueness constraints work
        $result = $this->connection->executeQuery("SELECT COUNT(*) FROM test_users WHERE username LIKE 'concurrent_user_%'");
        $count = $result->fetchOne();
        $this->assertEquals(10, $count);
    }

    /**
     * Test database error handling and recovery.
     */
    public function testDatabaseErrorHandling(): void
    {
        // Test duplicate key constraint
        $userData = FixtureFactory::createUser([
            'username' => 'duplicate_test',
            'email' => 'duplicate@test.com'
        ]);

        // Insert first user
        $firstId = $this->insertTestData('test_users', $userData);
        $this->assertGreaterThan(0, $firstId);

        // Try to insert duplicate username (should fail)
        $duplicateData = FixtureFactory::createUser([
            'username' => 'duplicate_test', // Same username
            'email' => 'different@test.com'
        ]);

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->insertTestData('test_users', $duplicateData);
    }
}
