<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\IntegerField;

/**
 * Test suite for the IntegerField class.
 * Tests integer field functionality with range validation and step controls.
 */
class IntegerFieldTest extends UnitTestCase
{
    private IntegerField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'age',
            'type' => 'Integer',
            'label' => 'Age',
            'required' => false,
            'defaultValue' => null,
            'minValue' => null,
            'maxValue' => null,
            'allowNegative' => true,
            'step' => 1,
            'placeholder' => 'Enter a number'
        ];

        $this->field = new IntegerField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('age', $this->field->getName());
        $this->assertEquals('Age', $this->field->getMetadataValue('label'));
        $this->assertEquals(1, $this->field->getMetadataValue('step'));
        $this->assertTrue($this->field->getMetadataValue('allowNegative'));
        $this->assertEquals('Enter a number', $this->field->getMetadataValue('placeholder'));
        $this->assertEquals('Integer', $this->field->getMetadataValue('type'));
    }

    /**
     * Test setting integer values
     */
    public function testIntegerValues(): void
    {
        // Test positive integer
        $this->field->setValue(25);
        $this->assertEquals(25, $this->field->getValue());

        // Test negative integer
        $this->field->setValue(-10);
        $this->assertEquals(-10, $this->field->getValue());

        // Test zero
        $this->field->setValue(0);
        $this->assertEquals(0, $this->field->getValue());

        // Test large integer
        $this->field->setValue(999999);
        $this->assertEquals(999999, $this->field->getValue());
    }

    /**
     * Test string numeric values
     */
    public function testStringNumericValues(): void
    {
        // Numeric strings should be stored as-is
        $this->field->setValue('123');
        $this->assertEquals('123', $this->field->getValue());

        $this->field->setValue('-456');
        $this->assertEquals('-456', $this->field->getValue());

        $this->field->setValue('0');
        $this->assertEquals('0', $this->field->getValue());
    }

    /**
     * Test float values (should be stored as-is)
     */
    public function testFloatValues(): void
    {
        // Float values should be stored as-is (conversion/validation happens elsewhere)
        $this->field->setValue(123.45);
        $this->assertEquals(123.45, $this->field->getValue());

        $this->field->setValue(-67.89);
        $this->assertEquals(-67.89, $this->field->getValue());
    }

    /**
     * Test null and empty values
     */
    public function testNullAndEmptyValues(): void
    {
        // Test null
        $this->field->setValue(null);
        $this->assertNull($this->field->getValue());

        // Test empty string
        $this->field->setValue('');
        $this->assertEquals('', $this->field->getValue());
    }

    /**
     * Test min/max value constraints
     */
    public function testMinMaxValues(): void
    {
        $metadata = [
            'name' => 'rating',
            'type' => 'Integer',
            'minValue' => 1,
            'maxValue' => 10
        ];

        $field = new IntegerField($metadata, $this->logger);
        $this->assertEquals(1, $field->getMetadataValue('minValue'));
        $this->assertEquals(10, $field->getMetadataValue('maxValue'));
    }

    /**
     * Test negative values handling
     */
    public function testNegativeValuesHandling(): void
    {
        // Test allowing negative values (default)
        $this->assertTrue($this->field->getMetadataValue('allowNegative'));

        // Test disallowing negative values
        $metadata = [
            'name' => 'positive_only',
            'type' => 'Integer',
            'allowNegative' => false
        ];

        $field = new IntegerField($metadata, $this->logger);
        $this->assertFalse($field->getMetadataValue('allowNegative'));
    }

    /**
     * Test step values
     */
    public function testStepValues(): void
    {
        // Test custom step
        $metadata = [
            'name' => 'step_field',
            'type' => 'Integer',
            'step' => 5
        ];

        $field = new IntegerField($metadata, $this->logger);
        $this->assertEquals(5, $field->getMetadataValue('step'));

        // Test step of 10
        $metadata['step'] = 10;
        $field = new IntegerField($metadata, $this->logger);
        $this->assertEquals(10, $field->getMetadataValue('step'));
    }

    /**
     * Test default properties
     */
    public function testDefaultProperties(): void
    {
        $minimalMetadata = [
            'name' => 'simple_integer',
            'type' => 'Integer'
        ];

        $field = new IntegerField($minimalMetadata, $this->logger);
        $this->assertNull($field->getMetadataValue('defaultValue'));
        $this->assertNull($field->getMetadataValue('minValue'));
        $this->assertNull($field->getMetadataValue('maxValue'));
        $this->assertTrue($field->getMetadataValue('allowNegative'));
        $this->assertEquals(1, $field->getMetadataValue('step'));
        $this->assertEquals('Enter a number', $field->getMetadataValue('placeholder'));
    }

    /**
     * Test required integer field
     */
    public function testRequiredIntegerField(): void
    {
        $metadata = [
            'name' => 'required_integer',
            'type' => 'Integer',
            'required' => true
        ];

        $field = new IntegerField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test default value
     */
    public function testDefaultValue(): void
    {
        $metadata = [
            'name' => 'integer_with_default',
            'type' => 'Integer',
            'defaultValue' => 42
        ];

        $field = new IntegerField($metadata, $this->logger);
        $this->assertEquals(42, $field->getValue());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource(789);
        $this->assertEquals(789, $this->field->getValue());

        $this->field->setValueFromTrustedSource('456');
        $this->assertEquals('456', $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test extreme integer values
     */
    public function testExtremeValues(): void
    {
        // Test maximum safe integer in PHP
        $maxInt = PHP_INT_MAX;
        $this->field->setValue($maxInt);
        $this->assertEquals($maxInt, $this->field->getValue());

        // Test minimum safe integer in PHP
        $minInt = PHP_INT_MIN;
        $this->field->setValue($minInt);
        $this->assertEquals($minInt, $this->field->getValue());
    }

    /**
     * Test non-numeric values
     */
    public function testNonNumericValues(): void
    {
        // Non-numeric values should be stored as-is (validation happens elsewhere)
        $this->field->setValue('not-a-number');
        $this->assertEquals('not-a-number', $this->field->getValue());

        $this->field->setValue([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $this->field->getValue());

        $this->field->setValue(true);
        $this->assertTrue($this->field->getValue());

        $this->field->setValue(false);
        $this->assertFalse($this->field->getValue());
    }

    /**
     * Test age-specific constraints
     */
    public function testAgeConstraints(): void
    {
        $metadata = [
            'name' => 'person_age',
            'type' => 'Integer',
            'minValue' => 0,
            'maxValue' => 150,
            'allowNegative' => false
        ];

        $field = new IntegerField($metadata, $this->logger);
        $this->assertEquals(0, $field->getMetadataValue('minValue'));
        $this->assertEquals(150, $field->getMetadataValue('maxValue'));
        $this->assertFalse($field->getMetadataValue('allowNegative'));
    }

    /**
     * Test quantity field constraints
     */
    public function testQuantityConstraints(): void
    {
        $metadata = [
            'name' => 'quantity',
            'type' => 'Integer',
            'minValue' => 1,
            'step' => 1,
            'allowNegative' => false,
            'placeholder' => 'Enter quantity'
        ];

        $field = new IntegerField($metadata, $this->logger);
        $this->assertEquals(1, $field->getMetadataValue('minValue'));
        $this->assertFalse($field->getMetadataValue('allowNegative'));
        $this->assertEquals('Enter quantity', $field->getMetadataValue('placeholder'));
    }

    /**
     * Test year field constraints
     */
    public function testYearConstraints(): void
    {
        $currentYear = (int)date('Y');
        $metadata = [
            'name' => 'year',
            'type' => 'Integer',
            'minValue' => 1900,
            'maxValue' => $currentYear + 10,
            'step' => 1
        ];

        $field = new IntegerField($metadata, $this->logger);
        $this->assertEquals(1900, $field->getMetadataValue('minValue'));
        $this->assertEquals($currentYear + 10, $field->getMetadataValue('maxValue'));
    }
}
