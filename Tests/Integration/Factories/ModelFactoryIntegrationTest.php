<?php

namespace Gravitycar\Tests\Integration\Factories;

use Gravitycar\Tests\Integration\IntegrationTestCase;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\users\Users;
use Gravitycar\Models\movies\Movies;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Contracts\MetadataEngineInterface;
use Aura\Di\Container;

/**
 * Integration tests for ModelFactory with real model classes and database
 */
class ModelFactoryIntegrationTest extends IntegrationTestCase
{
    private ModelFactory $modelFactory;
    private array $createdUserIds = []; // Track created users for cleanup
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create the users table for integration testing
        $this->createUsersTable();
        
        // Create ModelFactory with proper dependencies for integration testing
        $mockContainer = $this->createMock(Container::class);
        
        // Create ModelFactory instance using the real database connector and metadata engine from IntegrationTestCase
        // @phpstan-ignore-next-line - Mock objects are compatible at runtime
        /** @var Container $mockContainer */
        /** @var MetadataEngineInterface $metadataEngine */
        $metadataEngine = $this->metadataEngine;
        $this->modelFactory = new ModelFactory(
            $mockContainer,
            $this->logger,
            ServiceLocator::getDatabaseConnector(),
            $metadataEngine
        );
    }
    
    protected function tearDown(): void
    {
        $this->cleanupCreatedUsers();
        parent::tearDown();
    }
    
    /**
     * Create users table with complete schema
     */
    private function createUsersTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255),
            email VARCHAR(255) NOT NULL UNIQUE,
            first_name VARCHAR(255),
            last_name VARCHAR(255) NOT NULL,
            google_id VARCHAR(255) UNIQUE,
            auth_provider ENUM('local', 'google', 'hybrid') NOT NULL DEFAULT 'local',
            last_login_method ENUM('local', 'google'),
            email_verified_at DATETIME,
            profile_picture_url VARCHAR(500),
            last_google_sync DATETIME,
            is_active BOOLEAN NOT NULL DEFAULT 1,
            last_login DATETIME,
            user_type ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
            user_timezone VARCHAR(100) NOT NULL DEFAULT 'UTC',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            created_by VARCHAR(36),
            updated_by VARCHAR(36),
            deleted_by VARCHAR(36)
        )";
        
        $this->connection->executeStatement($sql);
    }
    
    /**
     * Clean up created users
     */
    private function cleanupCreatedUsers(): void
    {
        if (empty($this->createdUserIds)) {
            return;
        }
        
        foreach ($this->createdUserIds as $userId) {
            try {
                $sql = "DELETE FROM users WHERE id = ?";
                $this->connection->executeStatement($sql, [$userId]);
            } catch (\Exception $e) {
                // Log but don't fail the test if cleanup fails
                error_log("Failed to cleanup user record {$userId}: " . $e->getMessage());
            }
        }
        
        $this->createdUserIds = [];
    }
    
    /**
     * Test creating real model instances
     */
    public function testCreateRealModelInstances(): void
    {
        // Test Users model
        $user = $this->modelFactory->new('Users');
        $this->assertInstanceOf(Users::class, $user);
        $this->assertIsArray($user->getFields());
        $this->assertTrue($user->hasField('id'));
        $this->assertTrue($user->hasField('username'));

        // Test Movies model
        $movie = $this->modelFactory->new('Movies');
        $this->assertInstanceOf(Movies::class, $movie);
        $this->assertIsArray($movie->getFields());
        $this->assertTrue($movie->hasField('id'));
        $this->assertTrue($movie->hasField('name'));
    }

    /**
     * Test model creation and population workflow
     */
    public function testModelCreationAndPopulationWorkflow(): void
    {
        // Create a new user
        $user = $this->modelFactory->new('Users');
        $this->assertInstanceOf(Users::class, $user);

        // Set some test data
        $testUsername = 'testuser_' . uniqid();
        $testEmail = 'test_' . uniqid() . '@example.com';
        
        $user->set('username', $testUsername);
        $user->set('email', $testEmail);
        $user->set('password', 'testpassword');
        $user->set('first_name', 'Test');
        $user->set('last_name', 'User');

        // Validate the data was set correctly
        $this->assertEquals($testUsername, $user->get('username'));
        $this->assertEquals($testEmail, $user->get('email'));
        $this->assertEquals('Test', $user->get('first_name'));
        $this->assertEquals('User', $user->get('last_name'));
    }

    /**
     * Test model retrieval with database (if database is available)
     */
    public function testModelRetrievalWithDatabase(): void
    {
        // This test requires a database connection
        try {
            $dbConnector = ServiceLocator::getDatabaseConnector();
            $dbConnector->testConnection();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
        
        // Create a test user first
        $user = $this->modelFactory->new('Users');
        $testUsername = 'testuser_retrieve_' . uniqid();
        $testEmail = 'test_retrieve_' . uniqid() . '@example.com';
        
        $user->set('username', $testUsername);
        $user->set('email', $testEmail);
        $user->set('password', 'testpassword');
        $user->set('first_name', 'Test');
        $user->set('last_name', 'Retrieve');

        // Save to database
        $created = $user->create();
        if (!$created) {
            $this->markTestSkipped('Could not create test user in database');
        }

        $userId = $user->get('id');
        $this->assertNotEmpty($userId);
        
        // Track for cleanup
        $this->createdUserIds[] = $userId;

        // Now test retrieval
        $retrievedUser = $this->modelFactory->retrieve('Users', $userId);
        
        // Debug: check if record exists in database directly
        if ($retrievedUser === null) {
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery([$userId]);
            $row = $result->fetchAssociative();
            
            if ($row) {
                $this->fail("User was found in database directly but ModelFactory->retrieve returned null. User data: " . json_encode($row));
            } else {
                $this->fail("User was not found in database at all. User ID: $userId");
            }
        }
        
        $this->assertInstanceOf(Users::class, $retrievedUser);
        $this->assertEquals($userId, $retrievedUser->get('id'));
        $this->assertEquals($testUsername, $retrievedUser->get('username'));
        $this->assertEquals($testEmail, $retrievedUser->get('email'));
        $this->assertEquals('Test', $retrievedUser->get('first_name'));
        $this->assertEquals('Retrieve', $retrievedUser->get('last_name'));

        // Start a new transaction for proper cleanup
        $this->connection->beginTransaction();
        $this->inTransaction = true;
    }

    /**
     * Test retrieval of non-existent record returns null
     */
    public function testRetrievalOfNonExistentRecord(): void
    {
        $nonExistentId = 'non-existent-' . uniqid();
        $result = $this->modelFactory->retrieve('Users', $nonExistentId);
        $this->assertNull($result);
    }

    /**
     * Test error handling with non-existent model
     */
    public function testErrorHandlingWithNonExistentModel(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model class does not exist');

        $this->modelFactory->new('NonExistentModel');
    }

    /**
     * Test getting available models
     */
    public function testGetAvailableModels(): void
    {
        $availableModels = $this->modelFactory->getAvailableModels();
        
        $this->assertIsArray($availableModels);
        $this->assertNotEmpty($availableModels);
        
        // Should contain known models
        $this->assertContains('Users', $availableModels);
        $this->assertContains('Movies', $availableModels);
        
        // All entries should be strings
        foreach ($availableModels as $model) {
            $this->assertIsString($model);
        }
    }

    /**
     * Test model name variations and edge cases
     */
    public function testModelNameVariations(): void
    {
        // Test with underscores (Movie_Quotes)
        $movieQuote = $this->modelFactory->new('Movie_Quotes');
        $this->assertInstanceOf(\Gravitycar\Models\movie_quotes\Movie_Quotes::class, $movieQuote);

        // Test case sensitivity
        $user1 = $this->modelFactory->new('Users');
        $user2 = $this->modelFactory->new('Users'); // Same case
        
        $this->assertInstanceOf(Users::class, $user1);
        $this->assertInstanceOf(Users::class, $user2);
        $this->assertEquals(get_class($user1), get_class($user2));
    }

    /**
     * Test that created models have proper initialization
     */
    public function testModelProperInitialization(): void
    {
        $user = $this->modelFactory->new('Users');

        // Should have metadata loaded
        $this->assertNotEmpty($user->getFields());
        
        // Should have required core fields
        $this->assertTrue($user->hasField('id'));
        $this->assertTrue($user->hasField('created_at'));
        $this->assertTrue($user->hasField('updated_at'));
        
        // Should have model-specific fields
        $this->assertTrue($user->hasField('username'));
        $this->assertTrue($user->hasField('email'));
        
        // Should be able to get table name
        $tableName = $user->getTableName();
        $this->assertIsString($tableName);
        $this->assertNotEmpty($tableName);
    }

    /**
     * Test error conditions with invalid input
     */
    public function testErrorConditionsWithInvalidInput(): void
    {
        // Empty string
        try {
            $this->modelFactory->new('');
            $this->fail('Expected GCException for empty model name');
        } catch (GCException $e) {
            $this->assertStringContainsString('Invalid model name provided: must be non-empty string', $e->getMessage());
        }

        // Invalid characters
        try {
            $this->modelFactory->new('User@Model');
            $this->fail('Expected GCException for invalid model name');
        } catch (GCException $e) {
            $this->assertStringContainsString('Model class does not exist', $e->getMessage());
        }

        // Invalid retrieval ID
        try {
            $this->modelFactory->retrieve('', 'some-id');
            $this->fail('Expected GCException for empty model name in retrieve');
        } catch (GCException $e) {
            $this->assertStringContainsString("Failed to create model instance for ''", $e->getMessage());
        }
    }

    /**
     * Test performance with multiple model creations
     */
    public function testPerformanceWithMultipleCreations(): void
    {
        $startTime = microtime(true);
        $modelsCreated = [];

        // Create multiple models
        for ($i = 0; $i < 10; $i++) {
            $modelsCreated[] = $this->modelFactory->new('Users');
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should create 10 models reasonably quickly (under 1 second)
        $this->assertLessThan(1.0, $executionTime, 'Model creation should be performant');
        $this->assertCount(10, $modelsCreated);

        // All should be valid instances
        foreach ($modelsCreated as $model) {
            $this->assertInstanceOf(Users::class, $model);
        }
    }
}
