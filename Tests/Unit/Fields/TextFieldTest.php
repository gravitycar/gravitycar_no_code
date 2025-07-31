<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\TextField;

/**
 * Test suite for the TextField class.
 * Tests basic text field functionality and validation.
 */
class TextFieldTest extends UnitTestCase
{
    private TextField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'test_field',
            'type' => 'Text',
            'label' => 'Test Field',
            'required' => false,
            'maxLength' => 255
        ];

        $this->field = new TextField($metadata, $this->logger);
    }

    /**
     * Test constructor and basic property initialization
     */
    public function testConstructor(): void
    {
        $metadata = [
            'name' => 'title',
            'type' => 'Text',
            'label' => 'Title',
            'required' => true,
            'maxLength' => 100
        ];

        $field = new TextField($metadata, $this->logger);

        $this->assertEquals('title', $field->getName());
        $this->assertEquals('Title', $field->getMetadataValue('label'));
        $this->assertEquals(100, $field->getMetadataValue('maxLength'));
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test field name and metadata access
     */
    public function testFieldNameAndMetadata(): void
    {
        $this->assertEquals('test_field', $this->field->getName());
        $this->assertEquals('Test Field', $this->field->getMetadataValue('label'));
        $this->assertEquals(255, $this->field->getMetadataValue('maxLength'));
        $this->assertEquals('Text', $this->field->getMetadataValue('type'));
    }

    /**
     * Test value setting and getting
     */
    public function testValueOperations(): void
    {
        // Test initial value
        $this->assertNull($this->field->getValue());

        // Test setting string values
        $this->field->setValue('Hello World');
        $this->assertEquals('Hello World', $this->field->getValue());

        // Test setting empty string
        $this->field->setValue('');
        $this->assertEquals('', $this->field->getValue());

        // Test setting null
        $this->field->setValue(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test setValue with validation
     */
    public function testSetValueWithValidation(): void
    {
        // Clear any previous validation errors
        $this->clearLogRecords();

        // Test valid string
        $this->field->setValue('Valid text');
        $this->assertEquals('Valid text', $this->field->getValue());

        // Test numeric string (should be allowed)
        $this->field->setValue('12345');
        $this->assertEquals('12345', $this->field->getValue());

        // Test special characters
        $this->field->setValue('Text with !@#$%^&*()');
        $this->assertEquals('Text with !@#$%^&*()', $this->field->getValue());
    }

    /**
     * Test setValueFromTrustedSource method
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource('Trusted value');
        $this->assertEquals('Trusted value', $this->field->getValue());

        // Should not trigger validation
        $this->field->setValueFromTrustedSource('');
        $this->assertEquals('', $this->field->getValue());
    }

    /**
     * Test metadata utility methods
     */
    public function testMetadataUtilities(): void
    {
        // Test hasMetadata
        $this->assertTrue($this->field->hasMetadata('name'));
        $this->assertTrue($this->field->hasMetadata('maxLength'));
        $this->assertFalse($this->field->hasMetadata('nonexistent'));

        // Test metadataEquals
        $this->assertTrue($this->field->metadataEquals('name', 'test_field'));
        $this->assertFalse($this->field->metadataEquals('name', 'wrong_name'));

        // Test metadataIsTrue/metadataIsFalse
        $this->assertFalse($this->field->metadataIsTrue('required'));
        $this->assertTrue($this->field->metadataIsFalse('required'));
    }

    /**
     * Test isDBField method
     */
    public function testIsDBField(): void
    {
        // Should default to true
        $this->assertTrue($this->field->isDBField());

        // Test with explicit isDBField metadata
        $metadata = [
            'name' => 'non_db_field',
            'type' => 'Text',
            'isDBField' => false
        ];
        $nonDbField = new TextField($metadata, $this->logger);
        $this->assertFalse($nonDbField->isDBField());
    }

    /**
     * Test required field functionality
     */
    public function testRequiredField(): void
    {
        $metadata = [
            'name' => 'required_field',
            'type' => 'Text',
            'required' => true
        ];

        $requiredField = new TextField($metadata, $this->logger);
        $this->assertTrue($requiredField->isRequired());
        $this->assertTrue($requiredField->metadataIsTrue('required'));
    }

    /**
     * Test readonly field functionality
     */
    public function testReadonlyField(): void
    {
        $metadata = [
            'name' => 'readonly_field',
            'type' => 'Text',
            'readonly' => true
        ];

        $readonlyField = new TextField($metadata, $this->logger);
        $this->assertTrue($readonlyField->isReadonly());
        $this->assertTrue($readonlyField->metadataIsTrue('readonly'));
    }

    /**
     * Test unique field functionality
     */
    public function testUniqueField(): void
    {
        $metadata = [
            'name' => 'unique_field',
            'type' => 'Text',
            'unique' => true
        ];

        $uniqueField = new TextField($metadata, $this->logger);
        $this->assertTrue($uniqueField->isUnique());
        $this->assertTrue($uniqueField->metadataIsTrue('unique'));
    }

    /**
     * Test table name functionality
     */
    public function testTableName(): void
    {
        // Test default table name
        $this->assertEquals('', $this->field->getTableName());

        // Test setting table name
        $this->field->setTableName('users');
        $this->assertEquals('users', $this->field->getTableName());
    }

    /**
     * Test edge cases and error handling
     */
    public function testEdgeCases(): void
    {
        // Test very long strings (up to maxLength)
        $longString = str_repeat('a', 255);
        $this->field->setValue($longString);
        $this->assertEquals($longString, $this->field->getValue());

        // Test unicode characters
        $unicodeString = 'Hello 世界 émojis 🌍';
        $this->field->setValue($unicodeString);
        $this->assertEquals($unicodeString, $this->field->getValue());

        // Test newlines and special characters
        $specialString = "Line 1\nLine 2\tTabbed";
        $this->field->setValue($specialString);
        $this->assertEquals($specialString, $this->field->getValue());
    }

    /**
     * Test field with default value
     */
    public function testDefaultValue(): void
    {
        $metadata = [
            'name' => 'field_with_default',
            'type' => 'Text',
            'defaultValue' => 'Default Text'
        ];

        $fieldWithDefault = new TextField($metadata, $this->logger);
        $this->assertEquals('Default Text', $fieldWithDefault->getValue());
    }

    /**
     * Test validation errors collection
     */
    public function testValidationErrors(): void
    {
        // Initially should have no validation errors
        $this->assertEmpty($this->field->getValidationErrors());

        // Register some validation errors manually for testing
        $this->field->registerValidationError('Test error 1');
        $this->field->registerValidationError('Test error 2');

        $errors = $this->field->getValidationErrors();
        $this->assertCount(2, $errors);
        $this->assertContains('Test error 1', $errors);
        $this->assertContains('Test error 2', $errors);
    }

    /**
     * Test complete metadata array access
     */
    public function testCompleteMetadata(): void
    {
        $metadata = $this->field->getMetadata();

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('type', $metadata);
        $this->assertArrayHasKey('label', $metadata);
        $this->assertArrayHasKey('maxLength', $metadata);

        $this->assertEquals('test_field', $metadata['name']);
        $this->assertEquals('Text', $metadata['type']);
    }

    /**
     * Test constructor with missing name throws exception
     */
    public function testConstructorWithMissingNameThrowsException(): void
    {
        $this->expectException(\Gravitycar\Exceptions\GCException::class);
        $this->expectExceptionMessage('Field metadata missing name');

        $invalidMetadata = [
            'type' => 'Text',
            'label' => 'Field without name'
        ];

        new TextField($invalidMetadata, $this->logger);
    }
}
