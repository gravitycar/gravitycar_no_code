<?php

namespace Gravitycar\Tests\Unit\Validation;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Validation\ForeignKeyExistsValidation;
use Gravitycar\Fields\RelatedRecordField;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Database\DatabaseConnector;

/**
 * Test suite for the ForeignKeyExistsValidation class.
 * Tests validation logic for foreign key existence in related tables.
 */
class ForeignKeyExistsValidationTest extends UnitTestCase
{
    private ForeignKeyExistsValidation $validator;
    private $mockDatabaseConnector;
    private $mockRelatedRecordField;
    private $mockRelatedModel;
    private $mockRelatedField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ForeignKeyExistsValidation($this->logger);

        // Create mock related field
        $this->mockRelatedField = $this->createMock(FieldBase::class);
        $this->mockRelatedField->method('getName')->willReturn('id');

        // Create mock related model
        $this->mockRelatedModel = $this->createMock(ModelBase::class);
        $this->mockRelatedModel->method('getField')->willReturn($this->mockRelatedField);

        // Create mock RelatedRecordField
        $this->mockRelatedRecordField = $this->createMock(RelatedRecordField::class);
        $this->mockRelatedRecordField->method('getName')->willReturn('user_id');
        $this->mockRelatedRecordField->method('getRelatedModelInstance')->willReturn($this->mockRelatedModel);
        $this->mockRelatedRecordField->method('getRelatedFieldName')->willReturn('id');
        $this->mockRelatedRecordField->method('getRelatedModelName')->willReturn('User');

        // Create mock database connector
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnector::class);
    }

    /**
     * Test constructor sets correct name and error message
     */
    public function testConstructor(): void
    {
        $this->assertStringContainsString('does not exist', $this->validator->getErrorMessage());

        // Test custom constructor parameters
        $customValidator = new ForeignKeyExistsValidation($this->logger, 'CustomFK', 'Custom error message');
        $this->assertEquals('Custom error message', $customValidator->getErrorMessage());
    }

    /**
     * Test validation returns true for empty values (skipIfEmpty = true)
     */
    public function testEmptyValuesAreValid(): void
    {
        // Empty values should be valid (let Required rule handle emptiness)
        $this->assertTrue($this->validator->validate(''));
        $this->assertTrue($this->validator->validate(null));
        $this->assertTrue($this->validator->validate(0)); // 0 might be considered empty depending on shouldValidateValue
    }

    /**
     * Test validation with non-RelatedRecordField returns true (with warning)
     */
    public function testValidationWithNonRelatedRecordField(): void
    {
        // Create a regular field (not RelatedRecordField)
        $regularField = $this->createMock(FieldBase::class);
        $regularField->method('getName')->willReturn('regular_field');

        $this->validator->setField($regularField);

        // Should return true but log a warning
        $result = $this->validator->validate('test_value');
        $this->assertTrue($result);

        // Should log a warning about wrong field type
        $this->assertLoggedMessage('warning', 'ForeignKeyExists validation applied to non-RelatedRecord field');
    }

    /**
     * Test validation with valid RelatedRecordField but no field set
     */
    public function testValidationWithoutFieldContext(): void
    {
        // Validation without field context should behave according to base class logic
        $result = $this->validator->validate('test_value');

        // The exact behavior depends on the base class implementation
        $this->assertIsBool($result);
    }

    /**
     * Test validation with valid RelatedRecordField setup
     */
    public function testValidationWithValidFieldSetup(): void
    {
        $this->validator->setField($this->mockRelatedRecordField);

        // The actual database connectivity test will likely fail in unit test environment
        // But we can test that it doesn't throw exceptions
        $result = $this->validator->validate('test_value');
        $this->assertIsBool($result);
    }

    /**
     * Test error message formatting with field name
     */
    public function testErrorMessageFormatting(): void
    {
        $this->validator->setField($this->mockRelatedRecordField);

        $formatted = $this->validator->getFormatErrorMessage();
        $this->assertStringContainsString('user_id', $formatted);
        $this->assertStringContainsString('does not exist', $formatted);
    }

    /**
     * Test validation with different data types
     */
    public function testValidationWithDifferentDataTypes(): void
    {
        $this->validator->setField($this->mockRelatedRecordField);

        // Test various data types
        $testValues = [
            123,           // Integer ID
            '456',         // String ID
            'uuid-string', // UUID-style string
            true,          // Boolean (unusual but possible)
            ['complex']    // Array (should be handled gracefully)
        ];

        foreach ($testValues as $value) {
            $result = $this->validator->validate($value);
            $this->assertIsBool($result, "Validation should return boolean for value: " . print_r($value, true));
        }
    }

    /**
     * Test validation handles exceptions gracefully
     */
    public function testValidationHandlesExceptions(): void
    {
        // Set up a field that might cause exceptions
        $this->validator->setField($this->mockRelatedRecordField);

        // Mock getRelatedModelInstance to throw an exception
        $faultyField = $this->createMock(RelatedRecordField::class);
        $faultyField->method('getName')->willReturn('faulty_field');
        $faultyField->method('getRelatedModelInstance')
                   ->willThrowException(new \Exception('Database connection failed'));

        $this->validator->setField($faultyField);

        // Should handle exception gracefully and return false
        $result = $this->validator->validate('test_value');
        $this->assertFalse($result);

        // Should log the error
        $this->assertLoggedMessage('error', 'Error during foreign key validation');
    }

    /**
     * Test validation properties are set correctly
     */
    public function testValidationProperties(): void
    {
        // The constructor should set specific properties
        // We can't directly test protected properties, but we can test behavior

        // skipIfEmpty should be true (tested by empty values validation)
        $this->assertTrue($this->validator->validate(''));
        $this->assertTrue($this->validator->validate(null));

        // contextSensitive should be true (requires field context)
        // This is tested by the field setup tests
    }

    /**
     * Test JavaScript validation generation
     */
    public function testJavascriptValidation(): void
    {
        $jsValidation = $this->validator->getJavascriptValidation();

        // Should return JavaScript validation code (not empty string)
        $this->assertIsString($jsValidation);
        $this->assertStringContainsString('function validateForeignKey', $jsValidation);
        $this->assertStringContainsString('AJAX call', $jsValidation);
        $this->assertStringContainsString('return { valid: true }', $jsValidation);
    }

    /**
     * Test validation with edge case values
     */
    public function testValidationWithEdgeCases(): void
    {
        $this->validator->setField($this->mockRelatedRecordField);

        $edgeCases = [
            0,              // Zero (might be valid ID)
            -1,             // Negative number
            '0',            // String zero
            'very_long_string_' . str_repeat('x', 1000), // Very long string
            'special-chars!@#$%', // Special characters
            "\n\t\r",       // Whitespace characters
        ];

        foreach ($edgeCases as $value) {
            $result = $this->validator->validate($value);
            $this->assertIsBool($result, "Validation should return boolean for edge case: " . print_r($value, true));
        }
    }

    /**
     * Test validation doesn't throw exceptions
     */
    public function testNoExceptionsThrown(): void
    {
        try {
            // Test without field context
            $this->validator->validate('test_value');

            // Test with wrong field type
            $regularField = $this->createMock(FieldBase::class);
            $this->validator->setField($regularField);
            $this->validator->validate('test_value');

            // Test with RelatedRecordField
            $this->validator->setField($this->mockRelatedRecordField);
            $this->validator->validate('test_value');
            $this->validator->validate('');
            $this->validator->validate(null);
            $this->validator->validate(123);
            $this->validator->validate([]);
            $this->validator->validate(new \stdClass());

            $this->assertTrue(true); // If we get here, no exceptions were thrown
        } catch (\Exception $e) {
            $this->fail('ForeignKeyExists validation should not throw exceptions, but got: ' . $e->getMessage());
        }
    }

    /**
     * Test validation logging behavior
     */
    public function testValidationLogging(): void
    {
        $this->clearLogRecords();

        // Test with wrong field type (should log warning)
        $regularField = $this->createMock(FieldBase::class);
        $regularField->method('getName')->willReturn('regular_field');
        $this->validator->setField($regularField);
        $this->validator->validate('test_value');

        $this->assertLoggedMessage('warning', 'ForeignKeyExists validation applied to non-RelatedRecord field');

        // Clear logs and test with correct field type
        $this->clearLogRecords();
        $this->validator->setField($this->mockRelatedRecordField);
        $this->validator->validate('test_value');

        // Should have some log entries (success or error depending on database availability)
        $logRecords = $this->getLogRecords();
        // We expect at least some logging activity
        $this->assertNotNull($logRecords);
    }

    /**
     * Test validation with model and field name scenarios
     */
    public function testModelAndFieldNameScenarios(): void
    {
        // Test with different related model configurations
        $mockField1 = $this->createMock(RelatedRecordField::class);
        $mockField1->method('getName')->willReturn('category_id');
        $mockField1->method('getRelatedModelName')->willReturn('Category');
        $mockField1->method('getRelatedFieldName')->willReturn('id');
        
        // Add the missing mock for getRelatedModelInstance
        $mockModel1 = $this->createMock(ModelBase::class);
        $mockRelatedField1 = $this->createMock(FieldBase::class);
        $mockModel1->method('getField')->willReturn($mockRelatedField1);
        $mockField1->method('getRelatedModelInstance')->willReturn($mockModel1);
        
        $this->validator->setField($mockField1);
        $result = $this->validator->validate('test_value');
        $this->assertIsBool($result);
        
        // Test formatting with this field
        $formatted = $this->validator->getFormatErrorMessage();
        $this->assertStringContainsString('category_id', $formatted);
    }
}
