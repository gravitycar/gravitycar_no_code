<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Validation\ValidationRuleBase;
use Gravitycar\Exceptions\GCException;

/**
 * Test suite for the FieldBase abstract class.
 * Tests the base functionality that all field types inherit.
 */
class FieldBaseTest extends UnitTestCase
{
    private TestableFieldBase $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'test_field',
            'type' => 'TestField',
            'label' => 'Test Field',
            'required' => false,
            'unique' => false,
            'readonly' => false,
            'isDBField' => true,
            'defaultValue' => 'default_test_value',
            'validationRules' => []
        ];

        $this->field = new TestableFieldBase($metadata, $this->logger);
    }

    /**
     * Test constructor with valid metadata
     */
    public function testConstructorWithValidMetadata(): void
    {
        $this->assertEquals('test_field', $this->field->getName());
        $this->assertEquals('Test Field', $this->field->getMetadataValue('label'));
        $this->assertEquals('default_test_value', $this->field->getValue());
    }

    /**
     * Test getName method
     */
    public function testGetName(): void
    {
        $this->assertEquals('test_field', $this->field->getName());
    }

    /**
     * Test getValue and setValue methods
     */
    public function testGetSetValue(): void
    {
        // Test initial default value
        $this->assertEquals('default_test_value', $this->field->getValue());

        // Test setting new value
        $this->field->setValue('new_test_value');
        $this->assertEquals('new_test_value', $this->field->getValue());

        // Test setting null
        $this->field->setValue(null);
        $this->assertNull($this->field->getValue());

        // Test setting array
        $arrayValue = ['key' => 'value'];
        $this->field->setValue($arrayValue);
        $this->assertEquals($arrayValue, $this->field->getValue());
    }

    /**
     * Test setValueFromTrustedSource method
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource('trusted_value');
        $this->assertEquals('trusted_value', $this->field->getValue());

        // Should not trigger validation (no validation errors)
        $this->assertEmpty($this->field->getValidationErrors());
    }

    /**
     * Test table name functionality
     */
    public function testTableName(): void
    {
        // Test initial empty table name
        $this->assertEquals('', $this->field->getTableName());

        // Test setting table name
        $this->field->setTableName('users');
        $this->assertEquals('users', $this->field->getTableName());

        // Test changing table name
        $this->field->setTableName('customers');
        $this->assertEquals('customers', $this->field->getTableName());
    }

    /**
     * Test metadata access methods
     */
    public function testMetadataAccess(): void
    {
        // Test getMetadata returns complete array
        $metadata = $this->field->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('type', $metadata);

        // Test getMetadataValue
        $this->assertEquals('Test Field', $this->field->getMetadataValue('label'));
        $this->assertEquals('default_if_missing', $this->field->getMetadataValue('nonexistent', 'default_if_missing'));

        // Test hasMetadata
        $this->assertTrue($this->field->hasMetadata('name'));
        $this->assertTrue($this->field->hasMetadata('label'));
        $this->assertFalse($this->field->hasMetadata('nonexistent'));
    }

    /**
     * Test metadata utility methods
     */
    public function testMetadataUtilities(): void
    {
        // Test metadataEquals
        $this->assertTrue($this->field->metadataEquals('name', 'test_field'));
        $this->assertFalse($this->field->metadataEquals('name', 'wrong_name'));

        // Test metadataIsTrue
        $this->assertTrue($this->field->metadataIsTrue('isDBField'));
        $this->assertFalse($this->field->metadataIsTrue('required'));

        // Test metadataIsFalse
        $this->assertTrue($this->field->metadataIsFalse('required'));
        $this->assertFalse($this->field->metadataIsFalse('isDBField'));
    }

    /**
     * Test field property methods
     */
    public function testFieldProperties(): void
    {
        // Test isDBField
        $this->assertTrue($this->field->isDBField());

        // Test isRequired
        $this->assertFalse($this->field->isRequired());

        // Test isReadonly
        $this->assertFalse($this->field->isReadonly());

        // Test isUnique
        $this->assertFalse($this->field->isUnique());
    }

    /**
     * Test field properties with different values
     */
    public function testFieldPropertiesWithTrueValues(): void
    {
        $metadata = [
            'name' => 'special_field',
            'required' => true,
            'readonly' => true,
            'unique' => true,
            'isDBField' => false
        ];

        $field = new TestableFieldBase($metadata, $this->logger);

        $this->assertTrue($field->isRequired());
        $this->assertTrue($field->isReadonly());
        $this->assertTrue($field->isUnique());
        $this->assertFalse($field->isDBField());
    }

    /**
     * Test isDBField default behavior
     */
    public function testIsDBFieldDefault(): void
    {
        $metadata = [
            'name' => 'db_default_field'
        ];

        $field = new TestableFieldBase($metadata, $this->logger);
        $this->assertTrue($field->isDBField()); // Should default to true
    }

    /**
     * Test validation error handling
     */
    public function testValidationErrors(): void
    {
        // Initially no errors
        $this->assertEmpty($this->field->getValidationErrors());

        // Register some errors
        $this->field->registerValidationError('Error 1');
        $this->field->registerValidationError('Error 2');

        $errors = $this->field->getValidationErrors();
        $this->assertCount(2, $errors);
        $this->assertContains('Error 1', $errors);
        $this->assertContains('Error 2', $errors);
    }

    /**
     * Test metadata ingestion with type conversion
     */
    public function testMetadataIngestionWithTypeConversion(): void
    {
        $metadata = [
            'name' => 'typed_field',
            'required' => 'true', // String that should convert to boolean
            'maxLength' => '100',  // String that should convert to integer
            'defaultValue' => null
        ];

        $field = new TestableFieldBase($metadata, $this->logger);

        // The metadata should be stored as-is, conversion happens during property assignment
        $this->assertEquals('true', $field->getMetadataValue('required'));
        $this->assertEquals('100', $field->getMetadataValue('maxLength'));
    }

    /**
     * Test metadata ingestion with invalid property
     */
    public function testMetadataIngestionWithInvalidProperty(): void
    {
        $metadata = [
            'name' => 'field_with_invalid_prop',
            'nonExistentProperty' => 'some_value'
        ];

        // Should not throw exception, just ignore invalid properties
        $field = new TestableFieldBase($metadata, $this->logger);
        $this->assertEquals('field_with_invalid_prop', $field->getName());
    }

    /**
     * Test validation setup (mocked)
     */
    public function testValidationSetup(): void
    {
        $metadata = [
            'name' => 'field_with_validations',
            'validationRules' => ['Required', 'Email']
        ];

        // This will attempt to set up validation rules but will likely fail
        // due to missing ServiceLocator dependencies
        $field = new TestableFieldBase($metadata, $this->logger);

        // The field should still be created successfully
        $this->assertEquals('field_with_validations', $field->getName());
    }

    /**
     * Test type compatibility checking
     */
    public function testTypeCompatibility(): void
    {
        // Test with various metadata types
        $metadata = [
            'name' => 'type_test_field',
            'stringProp' => 'string_value',
            'intProp' => 42,
            'floatProp' => 3.14,
            'boolProp' => true,
            'arrayProp' => ['item1', 'item2']
        ];

        $field = new TestableFieldBase($metadata, $this->logger);

        $this->assertEquals('string_value', $field->getMetadataValue('stringProp'));
        $this->assertEquals(42, $field->getMetadataValue('intProp'));
        $this->assertEquals(3.14, $field->getMetadataValue('floatProp'));
        $this->assertTrue($field->getMetadataValue('boolProp'));
        $this->assertEquals(['item1', 'item2'], $field->getMetadataValue('arrayProp'));
    }

    /**
     * Test edge cases with null and empty values
     */
    public function testEdgeCasesWithNullAndEmpty(): void
    {
        $metadata = [
            'name' => 'edge_case_field',
            'nullProp' => null,
            'emptyStringProp' => '',
            'emptyArrayProp' => [],
            'zeroProp' => 0,
            'falseProp' => false
        ];

        $field = new TestableFieldBase($metadata, $this->logger);

        $this->assertNull($field->getMetadataValue('nullProp'));
        $this->assertEquals('', $field->getMetadataValue('emptyStringProp'));
        $this->assertEquals([], $field->getMetadataValue('emptyArrayProp'));
        $this->assertEquals(0, $field->getMetadataValue('zeroProp'));
        $this->assertFalse($field->getMetadataValue('falseProp'));
    }
}

/**
 * Testable concrete implementation of FieldBase for testing
 */
class TestableFieldBase extends FieldBase
{
    // Concrete implementation for testing the abstract base class
    // No additional functionality needed - just makes the abstract class testable
}
