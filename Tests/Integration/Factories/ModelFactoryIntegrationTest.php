<?php

namespace Gravitycar\Tests\Integration\Factories;

use Gravitycar\Tests\Integration\IntegrationTestCase;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\users\Users;
use Gravitycar\Models\movies\Movies;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;

/**
 * Integration tests for ModelFactory with real model classes and database
 */
class ModelFactoryIntegrationTest extends IntegrationTestCase
{
    /**
     * Test creating real model instances
     */
    public function testCreateRealModelInstances(): void
    {
        // Test Users model
        $user = ModelFactory::new('Users');
        $this->assertInstanceOf(Users::class, $user);
        $this->assertIsArray($user->getFields());
        $this->assertTrue($user->hasField('id'));
        $this->assertTrue($user->hasField('username'));

        // Test Movies model
        $movie = ModelFactory::new('Movies');
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
        $user = ModelFactory::new('Users');
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
        $user = ModelFactory::new('Users');
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

        // Now test retrieval
        $retrievedUser = ModelFactory::retrieve('Users', $userId);
        $this->assertInstanceOf(Users::class, $retrievedUser);
        $this->assertEquals($userId, $retrievedUser->get('id'));
        $this->assertEquals($testUsername, $retrievedUser->get('username'));
        $this->assertEquals($testEmail, $retrievedUser->get('email'));
        $this->assertEquals('Test', $retrievedUser->get('first_name'));
        $this->assertEquals('Retrieve', $retrievedUser->get('last_name'));

        // Clean up
        $retrievedUser->hardDelete();
    }

    /**
     * Test retrieval of non-existent record returns null
     */
    public function testRetrievalOfNonExistentRecord(): void
    {
        $nonExistentId = 'non-existent-' . uniqid();
        $result = ModelFactory::retrieve('Users', $nonExistentId);
        $this->assertNull($result);
    }

    /**
     * Test error handling with non-existent model
     */
    public function testErrorHandlingWithNonExistentModel(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model class not found');

        ModelFactory::new('NonExistentModel');
    }

    /**
     * Test getting available models
     */
    public function testGetAvailableModels(): void
    {
        $availableModels = ModelFactory::getAvailableModels();
        
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
        $movieQuote = ModelFactory::new('Movie_Quotes');
        $this->assertInstanceOf(\Gravitycar\Models\movie_quotes\Movie_Quotes::class, $movieQuote);

        // Test case sensitivity
        $user1 = ModelFactory::new('Users');
        $user2 = ModelFactory::new('Users'); // Same case
        
        $this->assertInstanceOf(Users::class, $user1);
        $this->assertInstanceOf(Users::class, $user2);
        $this->assertEquals(get_class($user1), get_class($user2));
    }

    /**
     * Test that created models have proper initialization
     */
    public function testModelProperInitialization(): void
    {
        $user = ModelFactory::new('Users');

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
            ModelFactory::new('');
            $this->fail('Expected GCException for empty model name');
        } catch (GCException $e) {
            $this->assertStringContainsString('Model name must be a non-empty string', $e->getMessage());
        }

        // Invalid characters
        try {
            ModelFactory::new('User@Model');
            $this->fail('Expected GCException for invalid model name');
        } catch (GCException $e) {
            $this->assertStringContainsString('Model name contains invalid characters', $e->getMessage());
        }

        // Invalid retrieval ID
        try {
            ModelFactory::retrieve('', 'some-id');
            $this->fail('Expected GCException for empty model name in retrieve');
        } catch (GCException $e) {
            $this->assertStringContainsString('Model name must be a non-empty string', $e->getMessage());
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
            $modelsCreated[] = ModelFactory::new('Users');
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
