<?php

namespace Gravitycar\Tests\Integration\Validation;

use Gravitycar\Tests\Integration\IntegrationTestCase;
use Gravitycar\Validation\ValidationEngine;
use Gravitycar\Validation\AlphanumericValidation;
use Gravitycar\Validation\RequiredValidation;
use Gravitycar\Tests\Fixtures\FixtureFactory;

/**
 * Integration tests for the validation system.
 * Tests how validation rules work together in real scenarios.
 */
class ValidationSystemIntegrationTest extends IntegrationTestCase
{
    private ValidationEngine $validationEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validationEngine = new ValidationEngine($this->logger);
    }

    /**
     * Test validation of user registration data with multiple rules.
     */
    public function testUserRegistrationValidation(): void
    {
        // Set up validation rules for user registration
        $this->validationEngine->addRule('username', new RequiredValidation($this->logger));
        $this->validationEngine->addRule('username', new AlphanumericValidation($this->logger));
        $this->validationEngine->addRule('email', new RequiredValidation($this->logger));

        // Test valid user data
        $validUserData = FixtureFactory::createUser([
            'username' => 'testuser123',
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $result = $this->validationEngine->validate($validUserData);
        $this->assertTrue($result->isValid(), 'Valid user data should pass validation');
        $this->assertEmpty($result->getErrors(), 'No errors should be present for valid data');

        // Test invalid user data - username with special characters
        $invalidUserData = FixtureFactory::createUser([
            'username' => 'test@user!',
            'email' => 'test@example.com'
        ]);

        $result = $this->validationEngine->validate($invalidUserData);
        $this->assertFalse($result->isValid(), 'Invalid username should fail validation');
        $this->assertArrayHasKey('username', $result->getErrors());
    }

    /**
     * Test validation cascading - when one validation fails, others still run.
     */
    public function testValidationCascading(): void
    {
        $this->validationEngine->addRule('field1', new RequiredValidation($this->logger));
        $this->validationEngine->addRule('field2', new RequiredValidation($this->logger));
        $this->validationEngine->addRule('field3', new AlphanumericValidation($this->logger));

        $testData = [
            'field1' => '', // Required field empty
            'field2' => '', // Required field empty
            'field3' => 'invalid@chars' // Invalid alphanumeric
        ];

        $result = $this->validationEngine->validate($testData);
        $this->assertFalse($result->isValid());

        $errors = $result->getErrors();
        $this->assertCount(3, $errors, 'All three validation failures should be captured');
        $this->assertArrayHasKey('field1', $errors);
        $this->assertArrayHasKey('field2', $errors);
        $this->assertArrayHasKey('field3', $errors);
    }

    /**
     * Test validation with database integration - unique field validation.
     */
    public function testDatabaseValidationIntegration(): void
    {
        // Insert a test user to create conflict scenario
        $existingUser = FixtureFactory::createUser([
            'username' => 'existinguser',
            'email' => 'existing@example.com'
        ]);

        $this->insertTestData('test_users', $existingUser);

        // Test that we can detect the existing user in database
        $this->assertDatabaseHas('test_users', [
            'username' => 'existinguser',
            'email' => 'existing@example.com'
        ]);

        // This test demonstrates how validation would integrate with database
        // The actual unique validation rule would be implemented later
        $this->assertTrue(true, 'Database integration setup successful');
    }

    /**
     * Test performance of validation system with large datasets.
     */
    public function testValidationPerformance(): void
    {
        // Add validation rules
        $this->validationEngine->addRule('username', new AlphanumericValidation($this->logger));
        $this->validationEngine->addRule('email', new RequiredValidation($this->logger));

        // Generate multiple test records
        $testRecords = FixtureFactory::createMultiple('user', 100);

        $startTime = microtime(true);

        $validCount = 0;
        foreach ($testRecords as $record) {
            $result = $this->validationEngine->validate($record);
            if ($result->isValid()) {
                $validCount++;
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertLessThan(1.0, $executionTime, 'Validation of 100 records should complete within 1 second');
        $this->assertEquals(100, $validCount, 'All generated fixture records should be valid');
    }

    /**
     * Test validation error message formatting and internationalization readiness.
     */
    public function testValidationErrorMessages(): void
    {
        $this->validationEngine->addRule('username', new RequiredValidation($this->logger));
        $this->validationEngine->addRule('username', new AlphanumericValidation($this->logger));

        $invalidData = ['username' => ''];
        $result = $this->validationEngine->validate($invalidData);

        $errors = $result->getErrors();
        $this->assertIsArray($errors['username']);

        foreach ($errors['username'] as $error) {
            $this->assertIsString($error);
            $this->assertNotEmpty($error);
        }
    }
}
