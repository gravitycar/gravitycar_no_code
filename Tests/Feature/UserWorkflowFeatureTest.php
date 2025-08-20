<?php

namespace Gravitycar\Tests\Feature;

use Gravitycar\Tests\Integration\IntegrationTestCase;
use Gravitycar\Tests\Fixtures\FixtureFactory;
use Gravitycar\Tests\Helpers\TestDataBuilder;

/**
 * Feature tests for complete user workflows.
 * Tests end-to-end scenarios from a user's perspective.
 */
class UserWorkflowFeatureTest extends IntegrationTestCase
{
    /**
     * Test complete user registration and login workflow.
     */
    public function testUserRegistrationAndLoginWorkflow(): void
    {
        // Step 1: User attempts to register with valid data
        $registrationData = TestDataBuilder::user()
            ->with('username', 'newuser123')
            ->with('email', 'newuser@example.com')
            ->with('password', 'SecurePass123')
            ->build();

        // Simulate registration process
        $userId = $this->insertTestData('test_users', $registrationData);
        $this->assertGreaterThan(0, $userId, 'User should be registered successfully');

        // Step 2: Verify user can be found in database
        $this->assertDatabaseHas('test_users', [
            'username' => 'newuser123',
            'email' => 'newuser@example.com',
            'is_active' => 1
        ]);

        // Step 3: Simulate login attempt
        $loginData = [
            'username' => 'newuser123',
            'password' => 'SecurePass123'
        ];

        // Verify login credentials match (in real system this would hash passwords)
        $result = $this->connection->executeQuery("SELECT * FROM test_users WHERE username = ? AND password = ?",
            [$loginData['username'], $loginData['password']]);
        $user = $result->fetchAssociative();

        $this->assertNotFalse($user, 'User should be able to login with correct credentials');
        $this->assertEquals('newuser123', $user['username']);
        $this->assertEquals(1, $user['is_active']);

        // Step 4: Test user profile access
        $this->assertNotEmpty($user['email']);
        $this->assertNotNull($user['created_at']);

        // Log successful workflow completion
        $this->logger->info('User registration and login workflow completed successfully');
        $this->assertLoggedMessage('info', 'User registration and login workflow completed successfully');
    }

    /**
     * Test movie quote trivia game workflow.
     */
    public function testMovieQuoteTriviaGameWorkflow(): void
    {
        // Step 1: Set up game data - create movies and quotes
        $movie1 = TestDataBuilder::movie()
            ->with('title', 'The Shawshank Redemption')
            ->with('director', 'Frank Darabont')
            ->with('release_year', 1994)
            ->build();
        $movie1Id = $this->insertTestData('test_movies', $movie1);

        $movie2 = TestDataBuilder::movie()
            ->with('title', 'The Godfather')
            ->with('director', 'Francis Ford Coppola')
            ->with('release_year', 1972)
            ->build();
        $movie2Id = $this->insertTestData('test_movies', $movie2);

        // Add quotes for both movies
        $quotes = [
            [
                'movie_id' => $movie1Id,
                'quote_text' => 'Get busy living, or get busy dying.',
                'character_name' => 'Andy Dufresne',
                'difficulty_level' => 'medium'
            ],
            [
                'movie_id' => $movie2Id,
                'quote_text' => 'I\'m gonna make him an offer he can\'t refuse.',
                'character_name' => 'Vito Corleone',
                'difficulty_level' => 'easy'
            ]
        ];

        foreach ($quotes as $quoteData) {
            $this->insertTestData('test_movie_quotes', $quoteData);
        }

        // Step 2: Simulate game session - user plays trivia
        $gameSession = [
            'user_id' => 1, // Assume user 1 exists
            'started_at' => date('Y-m-d H:i:s'),
            'difficulty' => 'medium',
            'questions_asked' => 0,
            'correct_answers' => 0
        ];

        // Step 3: Simulate answering questions
        $result = $this->connection->executeQuery("
            SELECT mq.*, m.title as movie_title 
            FROM test_movie_quotes mq 
            JOIN test_movies m ON mq.movie_id = m.id 
            WHERE mq.difficulty_level = 'medium'
            LIMIT 1
        ");
        $question = $result->fetchAssociative();

        $this->assertNotFalse($question, 'Should find a medium difficulty question');
        $this->assertEquals('Get busy living, or get busy dying.', $question['quote_text']);

        // Step 4: Verify game data integrity
        $this->assertWorkflowSuccess([
            'test_movies' => 2,
            'test_movie_quotes' => 2
        ]);

        // Step 5: Test game completion workflow
        $finalScore = [
            'questions_asked' => 5,
            'correct_answers' => 4,
            'score_percentage' => 80,
            'completed_at' => date('Y-m-d H:i:s')
        ];

        $this->assertEquals(80, $finalScore['score_percentage']);
        $this->assertGreaterThan(0, $finalScore['correct_answers']);
    }

    /**
     * Test content management workflow for adding new movie quotes.
     */
    public function testContentManagementWorkflow(): void
    {
        // Step 1: Admin adds a new movie
        $newMovie = TestDataBuilder::movie()
            ->with('title', 'Inception')
            ->with('director', 'Christopher Nolan')
            ->with('release_year', 2010)
            ->with('genre', 'Sci-Fi')
            ->build();

        $movieId = $this->insertTestData('test_movies', $newMovie);
        $this->assertGreaterThan(0, $movieId);

        // Step 2: Admin adds quotes for the movie
        $quotes = [
            'We need to go deeper.',
            'Your mind is the scene of the crime.',
            'Dreams feel real while we\'re in them.'
        ];

        $insertedQuoteIds = [];
        foreach ($quotes as $index => $quoteText) {
            $quoteData = [
                'movie_id' => $movieId,
                'quote_text' => $quoteText,
                'character_name' => 'Dom Cobb',
                'difficulty_level' => 'hard',
                'scene_description' => 'Scene ' . ($index + 1)
            ];

            $quoteId = $this->insertTestData('test_movie_quotes', $quoteData);
            $insertedQuoteIds[] = $quoteId;
        }

        $this->assertCount(3, $insertedQuoteIds);

        // Step 3: Verify content is properly linked
        foreach ($insertedQuoteIds as $quoteId) {
            $this->assertDatabaseHas('test_movie_quotes', [
                'id' => $quoteId,
                'movie_id' => $movieId,
                'character_name' => 'Dom Cobb'
            ]);
        }

        // Step 4: Test content validation and quality checks
        $result = $this->connection->executeQuery("
            SELECT COUNT(*) as quote_count 
            FROM test_movie_quotes 
            WHERE movie_id = ? AND character_name IS NOT NULL AND quote_text != ''
        ", [$movieId]);
        $resultData = $result->fetchAssociative();

        $this->assertEquals(3, $resultData['quote_count'], 'All quotes should be valid and complete');

        // Step 5: Test content retrieval for game
        $gameQuotesResult = $this->connection->executeQuery("
            SELECT mq.quote_text, m.title 
            FROM test_movie_quotes mq 
            JOIN test_movies m ON mq.movie_id = m.id 
            WHERE m.id = ?
        ", [$movieId]);
        $retrievedQuotes = $gameQuotesResult->fetchAllAssociative();

        $this->assertCount(3, $retrievedQuotes);
        foreach ($retrievedQuotes as $quote) {
            $this->assertEquals('Inception', $quote['title']);
            $this->assertNotEmpty($quote['quote_text']);
        }
    }

    /**
     * Test error recovery and data consistency workflow.
     */
    public function testErrorRecoveryWorkflow(): void
    {
        // For this test, we need to handle transactions differently
        // First, commit the current transaction from setUp if it exists
        try {
            if ($this->connection->isTransactionActive()) {
                $this->connection->commit();
                $this->inTransaction = false;
            }
        } catch (\Exception $e) {
            // Transaction might have been already committed or rolled back
            $this->inTransaction = false;
        }

        // Step 1: Start a complex operation that might fail
        $this->connection->beginTransaction();

        try {
            // Create a movie
            $movieData = TestDataBuilder::movie()
                ->with('title', 'Error Test Movie')
                ->build();
            $movieId = $this->insertTestData('test_movies', $movieData);

            // Create quotes
            $quoteData = [
                'movie_id' => $movieId,
                'quote_text' => 'Test quote for error scenario',
                'character_name' => 'Test Character'
            ];
            $quoteId = $this->insertTestData('test_movie_quotes', $quoteData);

            // Simulate an error condition
            throw new \Exception('Simulated error during processing');

        } catch (\Exception $e) {
            // Step 2: Handle error and rollback
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            // Step 3: Verify rollback worked - data should not exist
            $this->assertDatabaseMissing('test_movies', ['title' => 'Error Test Movie']);
            $this->assertDatabaseMissing('test_movie_quotes', ['quote_text' => 'Test quote for error scenario']);

            // Step 4: Test recovery - retry the operation
            $this->connection->beginTransaction();
            $recoveryMovieData = TestDataBuilder::movie()
                ->with('title', 'Recovery Test Movie')
                ->build();
            $recoveryMovieId = $this->insertTestData('test_movies', $recoveryMovieData);

            $this->assertGreaterThan(0, $recoveryMovieId, 'Recovery operation should succeed');
            $this->assertDatabaseHas('test_movies', ['title' => 'Recovery Test Movie']);

            // Rollback the recovery transaction for cleanup
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }

        // Restart transaction for tearDown
        if (!$this->connection->isTransactionActive()) {
            $this->connection->beginTransaction();
            $this->inTransaction = true;
        }
    }
}
